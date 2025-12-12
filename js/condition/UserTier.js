/* global BaseCondition */

/**
 * @file
 * User Tier condition implementation.
 */

/**
 * User Tier condition.
 *
 * Matches against user tier values in the context.
 * Matching is case-sensitive.
 */
class UserTierCondition extends BaseCondition {
  /**
   * {@inheritdoc}
   *
   * @param {Object} context - The context object with user data.
   * @return {boolean} True if the condition passes.
   */
  evaluate(context) {
    const userTier = this.getContextValue(context, 'user_tier');
    const values = this.config.values || [];

    if (!userTier) {
      return this.applyOperator(false);
    }

    // Check if user tier is in the configured values (case-sensitive).
    const matches = this.valueInArray(userTier, values);

    return this.applyOperator(matches);
  }
}

// Make available globally for Drupal.
if (typeof window !== 'undefined') {
  window.UserTierCondition = UserTierCondition;
}

// Export for module usage.
if (typeof module !== 'undefined' && module.exports) {
  module.exports = UserTierCondition;
}
