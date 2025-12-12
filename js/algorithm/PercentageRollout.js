/* global BaseAlgorithm */

/**
 * @file
 * Percentage Rollout algorithm implementation.
 */

/**
 * Percentage Rollout algorithm.
 *
 * Distributes users across variants based on configured percentages.
 * Uses deterministic hashing when persistence is enabled.
 */
class PercentageRollout extends BaseAlgorithm {
  /**
   * {@inheritdoc}
   *
   * @param {Object} context - The context object with user data.
   * @return {Promise<Object>} The selected variant object.
   */
  async decide(context) {
    const percentages = this.config.percentages || {};
    const userId = context.user_id;

    // Determine the bucket (0-99).
    let bucket;
    const persist =
      drupalSettings.featureFlags?.settings?.persist_decisions ||
      drupalSettings.featureFlags?.settings?.persist;

    if (persist && userId) {
      // Use deterministic hashing for consistent bucketing.
      const hashInput = `${userId}`;
      bucket = this.hashString(hashInput);
    } else {
      // Use random bucketing.
      bucket = this.getRandomBucket();
    }

    // Map bucket to variant based on cumulative percentages.
    const entries = Object.entries(percentages);
    let cumulative = 0;

    const selectedEntry = entries.find(([, percentage]) => {
      cumulative += percentage;
      return bucket < cumulative;
    });

    if (selectedEntry) {
      return this.getVariantByUuid(selectedEntry[0]);
    }

    // Fallback to first variant (shouldn't happen if percentages sum to 100).
    return this.variants[0];
  }
}

// Make available globally for Drupal.
if (typeof window !== 'undefined') {
  window.PercentageRollout = PercentageRollout;
}

// Export for module usage.
if (typeof module !== 'undefined' && module.exports) {
  module.exports = PercentageRollout;
}
