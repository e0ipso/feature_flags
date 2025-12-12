/**
 * @file
 * Base class for condition implementations.
 */

/**
 * Abstract base class for algorithm conditions.
 */
class BaseCondition {
  /**
   * Constructs a BaseCondition object.
   *
   * @param {Object} config - Condition configuration from PHP plugin.
   * @param {string} operator - The logical operator: "AND", "OR", or "NOT".
   */
  constructor(config, operator) {
    if (this.constructor === BaseCondition) {
      throw new Error(
        'BaseCondition is an abstract class and cannot be instantiated directly',
      );
    }

    this.config = config;
    this.operator = operator || 'OR';
  }

  /**
   * Evaluates the condition against the context.
   *
   * This method must be implemented by subclasses.
   *
   * @param {Object} context - The context object with user data.
   * @return {boolean} True if the condition is satisfied.
   * @abstract
   */
  evaluate(context) {
    throw new Error('evaluate() must be implemented by subclass');
  }

  /**
   * Gets a value from the context by key.
   *
   * @param {Object} context - The context object.
   * @param {string} key - The key to retrieve.
   * @return {*} The value or undefined if not found.
   */
  getContextValue(context, key) {
    return context[key];
  }

  /**
   * Applies the operator logic to a match result.
   *
   * For simple conditions (checking if one value is in a list), the logic is:
   * - OR: Return true if value is in the list
   * - NOT: Return true if value is NOT in the list
   * - AND: Same as OR for single-value checks (all values must match)
   *
   * @param {boolean} matches - Whether the condition matched.
   * @return {boolean} The result after applying the operator.
   */
  applyOperator(matches) {
    if (this.operator === 'NOT') {
      return !matches;
    }
    return matches;
  }

  /**
   * Checks if a value is in an array.
   *
   * @param {*} value - The value to check.
   * @param {Array} array - The array to search in.
   * @return {boolean} True if the value is in the array.
   */
  valueInArray(value, array) {
    if (!Array.isArray(array)) {
      return false;
    }
    // Convert both to strings for comparison.
    const valueStr = String(value);
    return array.some(item => String(item) === valueStr);
  }
}

// Export for module usage.
if (typeof module !== 'undefined' && module.exports) {
  module.exports = BaseCondition;
}
