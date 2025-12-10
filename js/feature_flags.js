/**
 * @file
 * Drupal behavior for Feature Flags initialization.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Initialize the Feature Flags manager.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the feature flags behavior.
   */
  Drupal.behaviors.featureFlags = {
    attach: function (context, settings) {
      once('feature-flags-init', 'html', context).forEach(function () {
        // Initialize the global Feature Flag Manager instance.
        if (!Drupal.featureFlags) {
          Drupal.featureFlags = new FeatureFlagManager();

          if (settings.featureFlags?.settings?.debug_mode || settings.featureFlags?.settings?.debug) {
            console.debug('[Feature Flags] Manager initialized');
            console.debug('[Feature Flags] Settings:', settings.featureFlags?.settings);
            console.debug('[Feature Flags] Available flags:', Object.keys(settings.featureFlags?.flags || {}));
          }
        }
      });
    }
  };

})(Drupal, once);
