/* global BaseCondition */

/**
 * @file
 * User ID condition implementation.
 */

/**
 * User ID condition.
 *
 * Matches against specific user IDs in the context.
 */
class UserIdCondition extends BaseCondition {
  /**
   * {@inheritdoc}
   *
   * @param {Object} context - The context object with user data.
   * @return {boolean} True if the condition passes.
   */
  evaluate(context) {
    const userId = this.getContextValue(context, 'user_id');
    const values = this.config.values || [];

    if (!userId) {
      return this.applyOperator(false);
    }

    // Check if user ID is in the configured values.
    const matches = this.valueInArray(userId, values);

    return this.applyOperator(matches);
  }
}

// Make available globally for Drupal.
if (typeof window !== 'undefined') {
  window.UserIdCondition = UserIdCondition;
}

// Export for module usage.
if (typeof module !== 'undefined' && module.exports) {
  module.exports = UserIdCondition;
}
