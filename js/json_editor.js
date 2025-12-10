/**
 * @file
 * JSON Editor integration using CodeMirror.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Initialize CodeMirror JSON editors.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the JSON editor behavior.
   */
  Drupal.behaviors.featureFlagsJsonEditor = {
    attach: function (context, settings) {
      // Check if CodeMirror is available.
      if (typeof CodeMirror === 'undefined') {
        console.warn('[Feature Flags] CodeMirror library not loaded');
        return;
      }

      // Initialize CodeMirror on all textareas with data-json-editor attribute.
      once('json-editor', 'textarea[data-json-editor="true"]', context).forEach(function (textarea) {
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
          indentWithTabs: false
        });

        // Store the editor instance on the textarea for later access.
        textarea.codemirrorInstance = editor;

        // Sync CodeMirror content back to textarea on change.
        editor.on('change', function () {
          editor.save();
        });

        // Ensure content is synced on form submit.
        const form = textarea.closest('form');
        if (form) {
          form.addEventListener('submit', function () {
            editor.save();
          });
        }

        // Add a class to the wrapper for styling.
        const wrapper = editor.getWrapperElement();
        wrapper.classList.add('feature-flags-json-editor');

        // Set a reasonable height.
        editor.setSize(null, '200px');
      });
    }
  };

})(Drupal, once);
