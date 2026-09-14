/* eslint no-use-before-define: ["error", { "functions": false }] */
(function mavePicker(Drupal, drupalSettings, once) {
  const activePickers = new WeakMap();
  let pickerNumber = 0;
  const validId = (id) =>
    typeof id === 'string' && /^[a-zA-Z0-9]{15}$/.test(id);
  const isVideo = (item) =>
    item &&
    validId(item.id) &&
    item.object === 'video' &&
    (item.last_upload || (item.renditions || []).length);
  const isCollection = (item) =>
    item && validId(item.id) && item.object === 'collection';

  function createPicker(root) {
    const settings = drupalSettings.mave;
    const input = root.querySelector('.mave-embed-input');
    const abort = new AbortController();
    let disposed = false;
    let page = 1;
    let path = [];
    let requestNumber = 0;
    let searchTimer;
    let searchCache;
    let uploader;
    let uploading = false;
    let loading = false;
    let previewCleanup = () => {};
    let selectedName = root.dataset.selectedName || Drupal.t('Video');
    let selectedDuration = 0;
    const { t } = Drupal;
    const el = (tag, className, text) => {
      const node = document.createElement(tag);
      if (className) node.className = className;
      if (text) node.textContent = text;
      return node;
    };
    const button = (text, handler, className = 'button button--small') => {
      const node = el('button', className, text);
      node.type = 'button';
      node.addEventListener('click', handler, { signal: abort.signal });
      return node;
    };
    const makeLoader = (text) => {
      const node = el('div', 'mave-loading');
      node.setAttribute('role', 'status');
      const spinner = el('span', 'ajax-progress__throbber mave-spinner');
      spinner.setAttribute('aria-hidden', 'true');
      node.append(spinner, el('span', 'mave-loading-label', text));
      return node;
    };
    const box = el('div', 'mave-browser');
    const toolbar = el('div', 'mave-toolbar');
    const status = el('div', 'mave-status messages');
    status.setAttribute('role', 'status');
    status.hidden = true;
    const grid = el('div', 'mave-grid');
    grid.setAttribute('aria-label', t('Mave videos and folders'));
    const loadingView = makeLoader(t('Loading Mave library…'));
    const libraryState = el('div', 'mave-library-state');
    libraryState.setAttribute('role', 'status');
    libraryState.hidden = true;
    const preview = el('details', 'mave-preview');
    const previewSummary = el('summary');
    const previewThumbnail = el('span', 'mave-preview-thumbnail');
    previewThumbnail.setAttribute('aria-hidden', 'true');
    const previewImage = el('img');
    previewImage.alt = '';
    previewImage.addEventListener(
      'load',
      () => {
        previewImage.hidden = false;
      },
      { signal: abort.signal },
    );
    previewImage.addEventListener(
      'error',
      () => {
        previewImage.hidden = true;
      },
      { signal: abort.signal },
    );
    previewThumbnail.append(previewImage);
    const previewInfo = el('span', 'mave-preview-info');
    const previewLabel = el('span', 'mave-preview-label');
    const previewTitle = el('span', 'mave-preview-title');
    previewInfo.append(previewLabel, previewTitle);
    const previewAction = el('span', 'mave-preview-action');
    previewSummary.append(previewThumbnail, previewInfo, previewAction);
    const previewBody = el('div', 'mave-preview-body');
    preview.append(previewSummary, previewBody);
    preview.hidden = true;
    const progress = el('progress', 'mave-upload-progress');
    progress.max = 100;
    progress.value = 0;
    progress.hidden = true;
    progress.setAttribute('aria-label', t('Video upload progress'));
    const say = (text, error = false) => {
      status.textContent = text;
      status.hidden = !text;
      status.classList.toggle('messages--error', error);
      status.classList.toggle('messages--status', !error);
    };
    const searchField = el('div', 'form-item mave-search-field');
    const search = el('input', 'form-search form-element');
    search.type = 'search';
    pickerNumber += 1;
    search.id = `mave-search-${pickerNumber}`;
    search.placeholder = t('Search the Mave library');
    const searchLabel = el('label', 'form-item__label', t('Search videos'));
    searchLabel.htmlFor = search.id;
    searchField.append(searchLabel, search);
    const breadcrumbs = el('nav', 'mave-breadcrumbs');
    breadcrumbs.setAttribute('aria-label', t('Mave folders'));
    const resultCount = el('span', 'mave-result-count');
    const location = el('div', 'mave-location');
    location.append(breadcrumbs, resultCount);
    toolbar.append(searchField);
    box.append(
      toolbar,
      status,
      progress,
      location,
      loadingView,
      libraryState,
      grid,
    );
    root.append(box, preview);
    const addButton = root.closest('form')?.querySelector('.mave-continue');
    function updateSelection() {
      if (addButton)
        addButton.disabled = !validId(input.value) || loading || uploading;
      root.dataset.selectionLabel = validId(input.value)
        ? t('Selected: @name', { '@name': selectedName })
        : t('No video selected');
      root.dispatchEvent(
        new CustomEvent('mave:selectionchange', { bubbles: true }),
      );
    }

    function thumbnail(id) {
      if (!validId(id)) return '';
      return `${settings.componentsConfig.cdn.endpoint
        .replace(
          /\$\{this\.spaceId\}|\$\{spaceId\}|\{spaceId\}/g,
          id.slice(0, 5),
        )
        .replace(/\/$/, '')}/${id.slice(5)}/thumbnail.jpg`;
    }
    function durationLabel(duration) {
      const seconds = Math.floor(Number(duration));
      return seconds > 0
        ? `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`
        : '';
    }
    function showPreview() {
      previewCleanup();
      previewBody.replaceChildren();
      previewBody.removeAttribute('aria-busy');
      preview.hidden = !validId(input.value);
      if (preview.hidden) {
        preview.open = false;
        return;
      }
      previewTitle.textContent = selectedName;
      const duration = durationLabel(selectedDuration);
      previewLabel.textContent = duration
        ? t('Selected video · @duration', { '@duration': duration })
        : t('Selected video');
      previewAction.textContent = preview.open
        ? t('Close preview')
        : t('Preview');
      previewSummary.setAttribute(
        'aria-label',
        preview.open
          ? t('Close preview: @name', { '@name': selectedName })
          : t('Preview video: @name', { '@name': selectedName }),
      );
      if (previewImage.dataset.id !== input.value) {
        previewImage.dataset.id = input.value;
        previewImage.hidden = true;
        previewImage.src = thumbnail(input.value);
      }
      if (!preview.open) return;
      const frame = el('div', 'mave-preview-frame');
      const loader = makeLoader(t('Loading video preview…'));
      const player = el('mave-player', 'mave-video');
      player.setAttribute('embed', input.value);
      player.setAttribute('theme', settings.playerTheme || 'default');
      if (settings.playerColor)
        player.setAttribute('color', settings.playerColor);
      let active = true;
      const timer = setTimeout(
        () =>
          fail(
            t('The preview is taking longer than expected. Please try again.'),
          ),
        30000,
      );
      previewCleanup = () => {
        active = false;
        clearTimeout(timer);
      };
      const finish = () => {
        if (!active) return;
        clearTimeout(timer);
        loader.hidden = true;
        previewBody.removeAttribute('aria-busy');
      };
      function fail(message) {
        if (!active) return;
        finish();
        active = false;
        const error = el('div', 'mave-preview-error');
        error.setAttribute('role', 'alert');
        error.append(
          el('strong', '', t('Preview unavailable')),
          el('p', '', message),
          button(t('Retry preview'), () => {
            previewSummary.focus({ preventScroll: true });
            showPreview();
          }),
        );
        frame.replaceChildren(error);
      }
      player.addEventListener('loadedmetadata', finish, {
        signal: abort.signal,
      });
      player.addEventListener(
        'error',
        () => fail(t('The video preview could not be loaded.')),
        { signal: abort.signal },
      );
      previewBody.setAttribute('aria-busy', 'true');
      frame.append(player, loader);
      previewBody.append(frame);
      Drupal.maveLoadComponents().catch(() =>
        fail(t('The video preview could not be loaded.')),
      );
    }
    preview.addEventListener(
      'toggle',
      () => {
        showPreview();
        // Bring the expanded card into view without moving focus out of its toggle.
        if (preview.open)
          requestAnimationFrame(() => {
            if (!disposed) preview.scrollIntoView({ block: 'nearest' });
          });
      },
      { signal: abort.signal },
    );
    function select(item) {
      input.value = item.id;
      selectedName = item.name || t('Video');
      selectedDuration = item.duration || 0;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      const name = root
        .closest('form')
        ?.querySelector('input[name="name[0][value]"]');
      if (name && !name.value) name.value = item.name || item.id;
      grid
        .querySelectorAll('.mave-card[aria-pressed]')
        .forEach((card) =>
          card.setAttribute(
            'aria-pressed',
            String(card.dataset.id === item.id),
          ),
        );
      Drupal.announce(t('Selected: @name', { '@name': selectedName }));
    }
    async function request(url, options = {}) {
      let response;
      try {
        response = await fetch(url, {
          credentials: 'same-origin',
          signal: AbortSignal.any([abort.signal, AbortSignal.timeout(30000)]),
          ...options,
        });
      } catch (error) {
        if (disposed) throw error;
        throw new Error(
          t('Could not connect to Mave. Check your connection and try again.'),
        );
      }
      const data = await response.json().catch(() => ({}));
      if (!response.ok)
        throw new Error(
          data.error ||
            t(
              'Could not complete the request. Check your permissions and Mave settings.',
            ),
        );
      return data;
    }
    function fetchPage(collection, number, perPage = 24) {
      const url = new URL(settings.videosUrl, window.location.origin);
      url.searchParams.set('page', number);
      url.searchParams.set('per_page', perPage);
      if (collection) url.searchParams.set('collection', collection);
      return request(url);
    }
    async function allVideos(collection, seen = new Set()) {
      if (seen.has(collection)) return [];
      seen.add(collection);
      const first = await fetchPage(collection, 1, 100);
      let items = first.data || [];
      for (let n = 2; n <= (first.total_pages || 1); n++) {
        if (disposed) return [];
        // Keep pagination sequential to bound requests and allow cancellation.
        // eslint-disable-next-line no-await-in-loop
        items = items.concat((await fetchPage(collection, n, 100)).data || []);
      }
      let result = items.filter(isVideo);
      const folders = items.filter(isCollection);
      for (let index = 0; index < folders.length; index++) {
        // Walk one folder at a time, sharing cycle detection across the tree.
        // eslint-disable-next-line no-await-in-loop
        result = result.concat(await allVideos(folders[index].id, seen));
      }
      return result;
    }
    function navigate(depth) {
      path = path.slice(0, depth);
      page = 1;
      search.value = '';
      clearTimeout(searchTimer);
      load();
    }
    function renderBreadcrumbs() {
      const list = el('ol');
      const parts = [{ name: t('Mave library') }, ...path];
      if (search.value.trim())
        parts.splice(1, parts.length, { name: t('Search results') });
      parts.forEach((part, index) => {
        const item = el('li');
        if (index === parts.length - 1) {
          const current = el('span', '', part.name);
          current.setAttribute('aria-current', 'location');
          item.append(current);
        } else
          item.append(
            button(part.name, () => navigate(index), 'mave-breadcrumb-link'),
          );
        list.append(item);
      });
      breadcrumbs.replaceChildren(list);
    }
    function render(items) {
      grid.replaceChildren();
      items.forEach((item) => {
        const folder = isCollection(item);
        const card = button(
          '',
          () => {
            if (folder) {
              path.push(item);
              page = 1;
              load();
            } else if (input.value === item.id) {
              say('');
              input.value = '';
              input.dispatchEvent(new Event('change', { bubbles: true }));
              card.setAttribute('aria-pressed', 'false');
              Drupal.announce(t('Video selection cleared.'));
            } else {
              say('');
              select(item);
            }
          },
          'mave-card',
        );
        card.dataset.id = item.id;
        card.setAttribute(
          'aria-label',
          (folder ? t('Open folder: ') : t('Select video: ')) +
            (item.name || item.id),
        );
        const poster = el(
          'span',
          folder ? 'mave-thumbnail mave-folder' : 'mave-thumbnail',
        );
        if (!folder) {
          card.setAttribute('aria-pressed', String(input.value === item.id));
          const image = el('img');
          image.src = thumbnail(item.id);
          image.alt = '';
          image.loading = 'lazy';
          image.addEventListener(
            'error',
            () => {
              image.remove();
              poster.append(el('span', 'mave-no-thumbnail', t('No thumbnail')));
            },
            { once: true },
          );
          const check = el('span', 'mave-card-check');
          check.setAttribute('aria-hidden', 'true');
          poster.append(image, check);
          const duration = durationLabel(item.duration);
          if (duration) {
            poster.append(el('span', 'mave-duration', duration));
          }
        }
        poster.setAttribute('aria-hidden', 'true');
        card.append(
          poster,
          el('span', 'mave-card-title', item.name || item.id),
        );
        grid.append(card);
      });
    }
    const pager = el('nav', 'mave-pagination');
    pager.setAttribute('aria-label', t('Library pages'));
    pager.hidden = true;
    const previous = button(t('Previous'), () => {
      page -= 1;
      load();
    });
    const pageLabel = el('span');
    const next = button(t('Next'), () => {
      page += 1;
      load();
    });
    pager.append(previous, pageLabel, next);
    box.append(pager);
    function startLoading() {
      loading = true;
      loadingView.querySelector('.mave-loading-label').textContent =
        search.value.trim()
          ? t('Searching Mave library…')
          : t('Loading Mave library…');
      loadingView.hidden = false;
      libraryState.hidden = true;
      grid.hidden = true;
      grid.setAttribute('aria-busy', 'true');
      pager.hidden = true;
      resultCount.textContent = '';
      previous.disabled = true;
      next.disabled = true;
      renderBreadcrumbs();
      updateSelection();
    }
    function showLibraryState(title, description, action) {
      libraryState.replaceChildren(
        el('h3', '', title),
        el('p', '', description),
      );
      if (action) libraryState.append(action);
      libraryState.hidden = false;
    }
    async function load() {
      requestNumber += 1;
      const number = requestNumber;
      const query = search.value.trim().toLowerCase();
      startLoading();
      try {
        let items;
        let pages;
        let total;
        if (query) {
          if (!searchCache)
            searchCache = allVideos(settings.rootCollection || '').catch(
              (e) => {
                searchCache = undefined;
                throw e;
              },
            );
          const unique = [
            ...new Map(
              (await searchCache).map((item) => [item.id, item]),
            ).values(),
          ];
          const matches = unique.filter((item) =>
            String(item.name || item.id)
              .toLowerCase()
              .includes(query),
          );
          total = matches.length;
          pages = Math.max(1, Math.ceil(total / 24));
          items = matches.slice((page - 1) * 24, page * 24);
        } else {
          const collection = path.length
            ? path[path.length - 1].id
            : settings.rootCollection;
          const data = await fetchPage(collection, page);
          items = data.data || [];
          total = data.total_items ?? items.length;
          pages = Math.max(1, data.total_pages || 1);
        }
        if (disposed || number !== requestNumber) return;
        items = items.filter(
          (item) =>
            item && validId(item.id) && (isVideo(item) || isCollection(item)),
        );
        render(items);
        grid.scrollTop = 0;
        grid.hidden = items.length === 0;
        previous.disabled = page <= 1;
        next.disabled = page >= pages;
        pager.hidden = pages <= 1;
        pageLabel.textContent = t('Page @page of @pages', {
          '@page': page,
          '@pages': pages,
        });
        resultCount.textContent = query
          ? Drupal.formatPlural(total, '1 video', '@count videos')
          : Drupal.formatPlural(total, '1 item', '@count items');
        if (!items.length)
          showLibraryState(
            query ? t('No videos found') : t('This folder is empty'),
            query
              ? t('Try a different search term or return to the library.')
              : t('Upload a video or choose another folder.'),
            query
              ? button(t('Clear search'), () => {
                  search.value = '';
                  page = 1;
                  load();
                  search.focus();
                })
              : null,
          );
      } catch (error) {
        if (!disposed && number === requestNumber)
          showLibraryState(
            t('Could not load the library'),
            error.message,
            button(t('Try again'), load),
          );
      } finally {
        if (!disposed && number === requestNumber) {
          loading = false;
          loadingView.hidden = true;
          grid.removeAttribute('aria-busy');
          updateSelection();
        }
      }
    }
    search.addEventListener(
      'input',
      () => {
        clearTimeout(searchTimer);
        // Invalidate the old response immediately, including during debounce.
        requestNumber += 1;
        startLoading();
        searchTimer = setTimeout(() => {
          page = 1;
          load();
        }, 300);
      },
      { signal: abort.signal },
    );
    search.addEventListener(
      'keydown',
      (event) => {
        if (event.key === 'Enter') {
          event.preventDefault();
          clearTimeout(searchTimer);
          page = 1;
          load();
        }
      },
      { signal: abort.signal },
    );
    input.addEventListener(
      'change',
      () => {
        updateSelection();
        showPreview();
      },
      { signal: abort.signal },
    );
    showPreview();
    updateSelection();
    if (root.dataset.canBrowse === '1') load();
    else {
      toolbar.hidden = true;
      location.hidden = true;
      loadingView.hidden = true;
      grid.hidden = true;
      showLibraryState(
        t('Library unavailable'),
        t('You do not have permission to browse Mave videos.'),
      );
    }

    if (root.dataset.canUpload === '1') {
      const fileInput = el('input');
      fileInput.type = 'file';
      fileInput.accept = 'video/*,audio/*';
      fileInput.hidden = true;
      const uploadButton = button(
        t('Upload video'),
        () => fileInput.click(),
        'button mave-upload-button',
      );
      toolbar.append(uploadButton, fileInput);
      // Upload permission can be granted independently from browse permission.
      if (root.dataset.canBrowse !== '1') {
        toolbar.hidden = false;
        searchField.hidden = true;
      }
      fileInput.addEventListener(
        'change',
        async () => {
          const file = fileInput.files[0];
          fileInput.value = '';
          if (!file) return;
          uploading = true;
          updateSelection();
          uploadButton.disabled = true;
          progress.hidden = false;
          progress.removeAttribute('value');
          say(t('Connecting to Mave…'));
          try {
            await Drupal.maveLoadComponents();
            const csrfResponse = await fetch(settings.csrfUrl, {
              credentials: 'same-origin',
              signal: abort.signal,
            });
            if (!csrfResponse.ok)
              throw new Error(
                t('Could not authorize the upload. Sign in again.'),
              );
            const csrf = await csrfResponse.text();
            const { token } = await request(settings.tokenUrl, {
              method: 'POST',
              headers: { 'X-CSRF-Token': csrf },
            });
            if (disposed) return;
            if (uploader) uploader.remove();
            uploader = el('mave-upload', 'mave-upload-driver');
            const fail = (event) => {
              uploading = false;
              updateSelection();
              uploadButton.disabled = false;
              progress.hidden = true;
              say(
                event.detail?.message ||
                  event.detail?.error?.message ||
                  t('Upload failed. Please try again.'),
                true,
              );
            };
            ['failed', 'invalid', 'error'].forEach((name) =>
              uploader.addEventListener(name, fail, { signal: abort.signal }),
            );
            uploader.addEventListener(
              'statechange',
              (event) => {
                const detail = event.detail || {};
                if (typeof detail.progress === 'number')
                  progress.value = detail.progress;
                if (detail.state === 'uploading')
                  say(t('Uploading @name…', { '@name': file.name }));
                if (detail.state === 'processing') {
                  progress.removeAttribute('value');
                  say(t('Upload received. Mave is processing the video…'));
                }
                if (detail.state === 'error') fail(event);
              },
              { signal: abort.signal },
            );
            uploader.addEventListener(
              'completed',
              (event) => {
                uploading = false;
                updateSelection();
                uploadButton.disabled = false;
                progress.hidden = true;
                searchCache = undefined;
                const id = event.detail?.embed;
                if (validId(id)) {
                  select({ id, name: file.name });
                  say(
                    t('@name is ready and selected.', { '@name': file.name }),
                  );
                } else
                  say(
                    t(
                      'Upload finished but returned no video ID. Refresh the library.',
                    ),
                    true,
                  );
              },
              { signal: abort.signal },
            );
            uploader.setAttribute('token', token);
            root.append(uploader);
            await new Promise((resolve, reject) => {
              const node = uploader;
              let timer;
              let observer;
              const cleanup = () => {
                observer.disconnect();
                clearTimeout(timer);
                abort.signal.removeEventListener('abort', cancel);
              };
              const check = () => {
                if (
                  node.hasAttribute('ready') &&
                  typeof node.upload === 'function'
                ) {
                  cleanup();
                  resolve();
                }
              };
              function cancel() {
                cleanup();
                reject(new Error(t('Upload cancelled.')));
              }
              observer = new MutationObserver(check);
              observer.observe(node, {
                attributes: true,
                attributeFilter: ['ready'],
              });
              timer = setTimeout(() => {
                cleanup();
                reject(
                  new Error(
                    t(
                      'Mave upload connection timed out. Check the WebSocket endpoint.',
                    ),
                  ),
                );
              }, 20000);
              abort.signal.addEventListener('abort', cancel, { once: true });
              check();
            });
            if (uploader.isSupportedFile && !uploader.isSupportedFile(file))
              throw new Error(t('This file type is not supported.'));
            uploader.upload(file);
          } catch (error) {
            uploading = false;
            updateSelection();
            uploadButton.disabled = false;
            progress.hidden = true;
            if (!disposed) say(error.message, true);
          }
        },
        { signal: abort.signal },
      );
    }
    return () => {
      disposed = true;
      abort.abort();
      clearTimeout(searchTimer);
      previewCleanup();
      if (uploader) uploader.remove();
    };
  }
  Drupal.behaviors.mavePicker = {
    attach(context) {
      once('mave-picker', '.mave-picker', context).forEach((root) =>
        activePickers.set(root, createPicker(root)),
      );
    },
    detach(context, settings, trigger) {
      if (trigger === 'unload') {
        once.remove('mave-picker', '.mave-picker', context).forEach((root) => {
          activePickers.get(root)?.();
          activePickers.delete(root);
        });
      }
    },
  };
})(Drupal, drupalSettings, once);
