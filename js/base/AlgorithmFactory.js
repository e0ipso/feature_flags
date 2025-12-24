/**
 * @file
 * Factory for creating algorithm instances from configuration.
 */

/**
 * Factory for instantiating algorithm objects.
 *
 * Reads algorithm configurations from feature flag configurations and
 * pre-instantiates all algorithm objects, returning a Map keyed by
 * algorithm UUID with fail-fast validation.
 */
class AlgorithmFactory {
  /**
   * Creates algorithm instances from drupalSettings configuration.
   *
   * Iterates through all flags and algorithms to instantiate each algorithm
   * class. Returns a Map for direct UUID-based lookup during flag resolution.
   *
   * @param {Object} config - Full drupalSettings object for feature flags.
   * @return {Map<string, Object>} Map keyed by algorithm UUID.
   * @throws {Error} If algorithm JS class not found in window.
   */
  static createInstances(config) {
    const instances = new Map();
    const flags = config?.flags || {};

    // Iterate all flags to find all algorithms.
    Object.values(flags).forEach(flag => {
      const algorithms = flag.algorithms || [];

      algorithms.forEach(algorithmConfig => {
        const { pluginId } = algorithmConfig;
        const AlgorithmClass = this.classMap.get(pluginId);

        // Fail-fast validation.
        if (!AlgorithmClass) {
          throw new Error(`Algorithm class for '${pluginId}' not found.`);
        }

        // Instantiate with configuration and variants.
        const instance = new AlgorithmClass(
          flag.variants,
          algorithmConfig.configuration,
        );

        // Store by UUID for direct lookup during resolution.
        instances.set(algorithmConfig.uuid, instance);
      });
    });

    return instances;
  }

  /** @var {Map<string, Function>} */
  static classMap = new Map();
}
