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
   */
  async decide(context) {
    const percentages = this.config.percentages || {};
    const userId = context.user_id;

    // Determine the bucket (0-99).
    let bucket;
    const persist = drupalSettings.featureFlags?.settings?.persist_decisions ||
                    drupalSettings.featureFlags?.settings?.persist;

    if (persist && userId) {
      // Use deterministic hashing for consistent bucketing.
      const hashInput = `${userId}`;
      bucket = this.hashString(hashInput);
    }
    else {
      // Use random bucketing.
      bucket = this.getRandomBucket();
    }

    // Map bucket to variant based on cumulative percentages.
    let cumulative = 0;
    for (const [variantUuid, percentage] of Object.entries(percentages)) {
      cumulative += percentage;
      if (bucket < cumulative) {
        return this.getVariantByUuid(variantUuid);
      }
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
