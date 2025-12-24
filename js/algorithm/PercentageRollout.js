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

    // Map bucket to variant based on cumulative percentages.
    const entries = Object.entries(percentages);
    let cumulative = 0;

    const selectedEntry = entries.find(([, percentage]) => {
      cumulative += percentage;
      return this.getRandomBucket() < cumulative;
    });

    if (selectedEntry) {
      return this.getVariantByUuid(selectedEntry[0]);
    }

    // Fallback to first variant (shouldn't happen if percentages sum to 100).
    throw new Error('Percentages in rollout do not sum up to 100.');
  }
}
