/* global FeatureFlagManager */

/**
 * @file
 * Drupal behavior for Feature Flags initialization.
 */

(function initFeatureFlags(Drupal, once) {
  /**
   * Initialize the Feature Flags manager.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the feature flags behavior.
   */
  Drupal.behaviors.featureFlags = {
    attach(context, settings) {
      once('feature-flags-init', 'html', context).forEach(
        function initializeFeatureFlagsManager() {
          // Initialize the global Feature Flag Manager instance.
          if (!Drupal.featureFlags) {
            Drupal.featureFlags = new FeatureFlagManager();

            if (
              settings.featureFlags?.settings?.debug_mode ||
              settings.featureFlags?.settings?.debug
            ) {
              console.debug('[Feature Flags] Manager initialized');
              console.debug(
                '[Feature Flags] Settings:',
                settings.featureFlags?.settings,
              );
              console.debug(
                '[Feature Flags] Available flags:',
                Object.keys(settings.featureFlags?.flags || {}),
              );
            }
          }
        },
      );
    },
  };
})(Drupal, once);
