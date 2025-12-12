/**
 * @file
 * Feature Flag Result class.
 */

/**
 * Encapsulates the result of a feature flag resolution.
 */
class FeatureFlagResult {
  /**
   * Constructs a FeatureFlagResult object.
   *
   * @param {FeatureFlagConfig} featureFlag - The feature flag configuration.
   * @param {Object} variant - The selected variant object.
   */
  constructor(featureFlag, variant) {
    this.featureFlag = featureFlag;
    this.variant = variant;

    // Parse the JSON value.
    try {
      this.result = JSON.parse(variant.value);
    } catch (e) {
      console.error(
        `[Feature Flags] Failed to parse variant value for ${featureFlag.id}:`,
        e,
      );
      this.result = variant.value;
    }
  }

  /**
   * Gets the parsed result value.
   *
   * @return {*} The parsed JSON value.
   */
  getValue() {
    return this.result;
  }

  /**
   * Gets the variant object.
   *
   * @return {Object} The variant object with uuid, label, and value.
   */
  getVariant() {
    return this.variant;
  }

  /**
   * Gets the feature flag configuration.
   *
   * @return {FeatureFlagConfig} The feature flag configuration.
   */
  getFeatureFlag() {
    return this.featureFlag;
  }

  /**
   * Gets the variant UUID.
   *
   * @return {string} The variant UUID.
   */
  getVariantUuid() {
    return this.variant.uuid;
  }

  /**
   * Gets the variant label.
   *
   * @return {string} The variant label.
   */
  getVariantLabel() {
    return this.variant.label;
  }
}

// Export for module usage.
if (typeof module !== 'undefined' && module.exports) {
  module.exports = FeatureFlagResult;
}
