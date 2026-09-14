(function (Drupal, $) {
  'use strict';

  function syncDialogs() {
    document.querySelectorAll('.ui-dialog:has(.mave-library-add-form)').forEach((dialog) => {
      const form = dialog.querySelector('.mave-library-add-form');
      const picker = form.querySelector('.mave-picker');
      const content = dialog.querySelector('.ui-dialog-content');
      const source = form.querySelector('.mave-continue');
      const button = dialog.querySelector('.ui-dialog-buttonpane .mave-continue');
      // Drupal copies form actions into the footer, but not their disabled state.
      if (source && button) button.disabled = source.disabled;
      if ($(content).data('ui-dialog')) {
        $(content).dialog('option', 'title', picker ? Drupal.t('Select a Mave video') : Drupal.t('Video details'));
      }
      const pane = dialog.querySelector('.ui-dialog-buttonpane');
      if (!pane) return;
      let selection = pane.querySelector('.mave-dialog-selection');
      if (!picker) { selection?.remove(); return; }
      if (!selection) {
        selection = document.createElement('p');
        selection.className = 'mave-dialog-selection';
        pane.prepend(selection);
      }
      selection.textContent = picker.dataset.selectionLabel || Drupal.t('No video selected');
      selection.title = selection.textContent;
    });
  }

  Drupal.behaviors.maveDialog = { attach: () => requestAnimationFrame(syncDialogs) };
  window.addEventListener('dialog:aftercreate', syncDialogs);
  document.addEventListener('dialogButtonsChange', () => requestAnimationFrame(syncDialogs));
  document.addEventListener('mave:selectionchange', syncDialogs);
})(Drupal, jQuery);
