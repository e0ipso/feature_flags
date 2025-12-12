/**
 * @file
 * Admin form enhancements for Feature Flags module.
 */

(function initAdminForm(Drupal, once) {
  /**
   * Enhance the feature flag admin form.
   */
  Drupal.behaviors.featureFlagsAdminForm = {
    attach(context, settings) {
      // Add any additional UI enhancements here.
      // Most functionality is handled server-side via AJAX.
    },
  };
})(Drupal, once);
