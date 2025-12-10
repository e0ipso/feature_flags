/**
 * @file
 * Feature Flag Configuration class.
 */

/**
 * Represents a feature flag configuration.
 */
class FeatureFlagConfig {
  /**
   * Constructs a FeatureFlagConfig object.
   *
   * @param {Object} config - The configuration object from drupalSettings.
   * @param {string} config.id - The feature flag machine name.
   * @param {string} config.label - The human-readable label.
   * @param {Array} config.variants - Array of variant definitions.
   * @param {Array} config.algorithms - Array of algorithm configurations.
   */
  constructor(config) {
    this.id = config.id;
    this.label = config.label;
    this.variants = config.variants || [];
    this.algorithms = config.algorithms || [];
  }

  /**
   * Gets a variant by UUID.
   *
   * @param {string} uuid - The variant UUID.
   * @return {Object|null} The variant object or null if not found.
   */
  getVariant(uuid) {
    return this.variants.find(v => v.uuid === uuid) || null;
  }

  /**
   * Gets all variants.
   *
   * @return {Array} Array of variant objects.
   */
  getVariants() {
    return this.variants;
  }

  /**
   * Gets all algorithms in order.
   *
   * @return {Array} Array of algorithm configurations.
   */
  getAlgorithms() {
    return this.algorithms;
  }
}

// Export for module usage.
if (typeof module !== 'undefined' && module.exports) {
  module.exports = FeatureFlagConfig;
}
