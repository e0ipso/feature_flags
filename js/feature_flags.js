/* global FeatureFlagManager, AlgorithmFactory, ConditionFactory */

/**
 * @file
 * Drupal behavior for Feature Flags initialization.
 */

(function (Drupal, once) {
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
      once('feature-flags-init', 'html', context).forEach(() => {
        // Early return if already initialized.
        if (Drupal.featureFlags) {
          return;
        }

        const featureFlagsSettings = settings?.featureFlags || {};

        // Create algorithm instances using factory.
        let algorithmInstances;
        try {
          algorithmInstances =
            AlgorithmFactory.createInstances(featureFlagsSettings);
        } catch (error) {
          console.error(
            '[Feature Flags] Failed to create algorithm instances:',
            error,
          );
          return;
        }

        // Create condition instances using factory.
        let conditionInstances;
        try {
          conditionInstances =
            ConditionFactory.createInstances(featureFlagsSettings);
        } catch (error) {
          console.error(
            '[Feature Flags] Failed to create condition instances:',
            error,
          );
          return;
        }

        // Initialize the global Feature Flag Manager instance with pre-instantiated plugins.
        Drupal.featureFlags = new FeatureFlagManager(
          featureFlagsSettings,
          {}, // initialContext
          algorithmInstances,
          conditionInstances,
        );

        if (
          featureFlagsSettings?.settings?.debug_mode ||
          featureFlagsSettings?.settings?.debug
        ) {
          console.debug('[Feature Flags] Manager initialized');
          console.debug(
            '[Feature Flags] Algorithm instances:',
            algorithmInstances.size,
          );
          console.debug(
            '[Feature Flags] Condition instances:',
            conditionInstances.size,
          );
          console.debug(
            '[Feature Flags] Available flags:',
            Object.keys(featureFlagsSettings?.flags || {}),
          );
        }
      });
    },
  };
})(Drupal, once);
