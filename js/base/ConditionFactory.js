/**
 * @file
 * Factory for creating condition instances from configuration.
 */

/**
 * Factory for instantiating condition objects.
 *
 * Reads condition configurations from algorithm configurations and
 * pre-instantiates all condition objects, returning a Map keyed by
 * condition UUID with fail-fast validation.
 */
class ConditionFactory {
  /**
   * Creates condition instances from drupalSettings configuration.
   *
   * Iterates through all flags, algorithms, and conditions to instantiate
   * each condition class. Returns a Map for direct UUID-based lookup during
   * flag resolution.
   *
   * @param {Object} config - Full drupalSettings object for feature flags.
   * @return {Map<string, Object>} Map keyed by condition UUID.
   * @throws {Error} If condition JS class not found in window.
   */
  static createInstances(config) {
    const instances = new Map();
    const flags = config?.flags || {};

    // Iterate all flags.
    Object.values(flags).forEach(flag => {
      const algorithms = flag.algorithms || [];

      // Iterate all algorithms to find conditions.
      algorithms.forEach(algorithmConfig => {
        const conditions = algorithmConfig.conditions || [];

        // Iterate all conditions.
        conditions.forEach(conditionConfig => {
          const { pluginId } = conditionConfig;
          const ConditionClass = this.classMap.get(pluginId);

          // Fail-fast validation.
          if (!ConditionClass) {
            throw new Error(`Condition class for '${pluginId}' not found.`);
          }

          // Instantiate with configuration and operator.
          const instance = new ConditionClass(
            conditionConfig.configuration,
            conditionConfig.operator,
          );

          // Store by UUID for direct lookup during resolution.
          instances.set(conditionConfig.uuid, instance);
        });
      });
    });

    return instances;
  }

  /** @var {Map<string, Function>} */
  static classMap = new Map();
}
