/* global CodeMirror */

/**
 * @file
 * JSON Editor integration using CodeMirror.
 */

(function initJsonEditor(Drupal, once) {
  /**
   * Initialize CodeMirror JSON editors.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the JSON editor behavior.
   */
  Drupal.behaviors.featureFlagsJsonEditor = {
    attach(context, settings) {
      // Check if CodeMirror is available.
      if (typeof CodeMirror === 'undefined') {
        console.warn('[Feature Flags] CodeMirror library not loaded');
        return;
      }

      // Store all editor instances for batch refresh.
      const editors = [];

      // Initialize CodeMirror on all textareas with data-json-editor attribute.
      once('json-editor', 'textarea[data-json-editor="true"]', context).forEach(
        function initializeCodeMirror(textarea) {
          // Store the initial value before CodeMirror takes over.
          const initialValue = textarea.value;

          // Create CodeMirror instance.
          const editor = CodeMirror.fromTextArea(textarea, {
            mode: 'application/json',
            lineNumbers: true,
            lineWrapping: true,
            matchBrackets: true,
            autoCloseBrackets: true,
            gutters: ['CodeMirror-lint-markers'],
            lint: true,
            theme: 'default',
            indentUnit: 2,
            tabSize: 2,
            indentWithTabs: false,
          });

          // Store the editor instance on the textarea for later access.
          textarea.codemirrorInstance = editor;
          editors.push(editor);

          // Explicitly set the value and refresh to ensure proper display.
          editor.setValue(initialValue);

          // Sync CodeMirror content back to textarea on change.
          editor.on('change', function syncCodeMirrorContent() {
            editor.save();
          });

          // Ensure content is synced on form submit.
          const form = textarea.closest('form');
          if (form) {
            form.addEventListener('submit', function saveCodeMirrorOnSubmit() {
              editor.save();
            });
          }

          // Add a class to the wrapper for styling.
          const wrapper = editor.getWrapperElement();
          wrapper.classList.add('feature-flags-json-editor');

          // Set a reasonable height.
          editor.setSize(null, '200px');
        },
      );

      // Refresh all editors when they become visible.
      function refreshAllEditors() {
        editors.forEach(function (editor) {
          editor.refresh();
        });
      }

      // Listen for vertical tabs being opened.
      once(
        'json-editor-vertical-tabs',
        '.vertical-tabs__menu-item a',
        context,
      ).forEach(function (tabLink) {
        tabLink.addEventListener('click', function () {
          // Refresh all editors after a short delay to ensure tab is visible.
          setTimeout(refreshAllEditors, 50);
        });
      });

      // Initial refresh with increasing delays to handle CDN loading.
      setTimeout(refreshAllEditors, 100);
      setTimeout(refreshAllEditors, 250);
      setTimeout(refreshAllEditors, 500);
    },
  };
})(Drupal, once);
