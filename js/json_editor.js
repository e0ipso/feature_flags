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

          // Explicitly set the value and refresh to ensure proper display.
          // This fixes a race condition where the initial value may not render correctly.
          editor.setValue(initialValue);

          // Refresh the editor when it becomes visible.
          // CodeMirror doesn't render correctly in hidden containers (like collapsed vertical tabs).
          function refreshWhenVisible() {
            editor.refresh();
          }

          // Listen for the details element (vertical tab) being opened.
          const detailsElement = textarea.closest('details');
          if (detailsElement) {
            once(
              'json-editor-details-listener',
              detailsElement,
              context,
            ).forEach(function attachDetailsListener(details) {
              details.addEventListener('toggle', function onDetailsToggle() {
                if (details.open) {
                  // Details was opened, refresh all CodeMirror instances inside.
                  setTimeout(refreshWhenVisible, 10);
                }
              });
            });
          }

          // Initial refresh with a delay to ensure the editor is fully rendered.
          // Use a longer delay to ensure CDN resources are loaded and DOM is ready.
          setTimeout(refreshWhenVisible, 100);

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
    },
  };
})(Drupal, once);
