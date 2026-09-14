(function mavePlayer(Drupal, drupalSettings, once) {
  let componentsPromise;
  Drupal.maveLoadComponents = function maveLoadComponents() {
    const settings = drupalSettings.mave || {};
    if (!componentsPromise) {
      window.__maveComponentsConfig = settings.componentsConfig || {};
      componentsPromise = import(settings.componentsSrc)
        .then((module) => {
          if (module.configureMave)
            module.configureMave(settings.componentsConfig || {});
          return module;
        })
        .catch((error) => {
          componentsPromise = undefined;
          throw error;
        });
    }
    return componentsPromise;
  };
  Drupal.behaviors.mavePlayer = {
    attach(context) {
      // Load before CKEditor inserts its asynchronously fetched media preview.
      if (
        once('mave-player', 'mave-player, [data-mave-editor]', context).length
      ) {
        Drupal.maveLoadComponents().catch(() => {
          Drupal.announce(Drupal.t('The video player could not be loaded.'));
        });
      }
    },
  };
})(Drupal, drupalSettings, once);
