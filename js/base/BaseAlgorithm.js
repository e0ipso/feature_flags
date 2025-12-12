/**
 * @file
 * Base class for decision algorithm implementations.
 */

/**
 * Abstract base class for decision algorithms.
 */
class BaseAlgorithm {
  /**
   * Constructs a BaseAlgorithm object.
   *
   * @param {Array} variants - Array of variant objects from the feature flag.
   * @param {Object} config - Algorithm configuration from PHP plugin.
   */
  constructor(variants, config) {
    if (this.constructor === BaseAlgorithm) {
      throw new Error(
        'BaseAlgorithm is an abstract class and cannot be instantiated directly',
      );
    }

    this.variants = variants;
    this.config = config;
  }

  /**
   * Decides which variant to return.
   *
   * This method must be implemented by subclasses.
   *
   * @param {Object} context - The context object with user data.
   * @return {Promise<Object>} The selected variant object.
   * @abstract
   */
  async decide(context) {
    throw new Error('decide() must be implemented by subclass');
  }

  /**
   * Gets a variant by UUID.
   *
   * Uses find() to locate variant, which is more efficient than filter()
   * for single results.
   *
   * @param {string} uuid - The variant UUID.
   * @return {Object|null} The variant object or null if not found.
   */
  getVariantByUuid(uuid) {
    return this.variants.find(v => v.uuid === uuid) || null;
  }

  /**
   * Generates a deterministic hash for a string.
   *
   * Used for consistent bucketing when persistence is enabled.
   *
   * @param {string} str - The string to hash.
   * @return {number} A number between 0 and 99.
   */
  hashString(str) {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
      const char = str.charCodeAt(i);
      // Standard hash algorithm requires bitwise left shift for distribution.
      // eslint-disable-next-line no-bitwise
      hash = (hash << 5) - hash + char;
      // Convert to 32-bit integer using bitwise AND (standard JS pattern).
      // eslint-disable-next-line no-bitwise
      hash &= hash;
    }
    // Convert to positive number between 0 and 99.
    return Math.abs(hash) % 100;
  }

  /**
   * Gets a random number between 0 and 99.
   *
   * Used for non-persistent random selection.
   *
   * @return {number} A random number between 0 and 99.
   */
  getRandomBucket() {
    return Math.floor(Math.random() * 100);
  }
}

// Export for module usage.
if (typeof module !== 'undefined' && module.exports) {
  module.exports = BaseAlgorithm;
}
