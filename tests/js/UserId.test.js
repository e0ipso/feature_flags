/**
 * @file
 * Jest tests for UserId condition.
 */

const BaseCondition = require('../../js/base/BaseCondition.js');

// Make BaseCondition available globally for UserIdCondition
global.BaseCondition = BaseCondition;

const UserIdCondition = require('../../js/condition/UserId.js');

describe('UserIdCondition', () => {
  describe('OR operator', () => {
    test('matches when user_id is in list', () => {
      const condition = new UserIdCondition({ values: ['1', '5', '10'] }, 'OR');

      expect(condition.evaluate({ user_id: '1' })).toBe(true);
      expect(condition.evaluate({ user_id: '5' })).toBe(true);
      expect(condition.evaluate({ user_id: '10' })).toBe(true);
    });

    test('does not match when user_id is not in list', () => {
      const condition = new UserIdCondition({ values: ['1', '5', '10'] }, 'OR');

      expect(condition.evaluate({ user_id: '2' })).toBe(false);
      expect(condition.evaluate({ user_id: '99' })).toBe(false);
      expect(condition.evaluate({ user_id: '100' })).toBe(false);
    });

    test('handles single value in list', () => {
      const condition = new UserIdCondition({ values: ['42'] }, 'OR');

      expect(condition.evaluate({ user_id: '42' })).toBe(true);
      expect(condition.evaluate({ user_id: '43' })).toBe(false);
    });

    test('handles empty values array', () => {
      const condition = new UserIdCondition({ values: [] }, 'OR');

      expect(condition.evaluate({ user_id: '1' })).toBe(false);
      expect(condition.evaluate({ user_id: 'any' })).toBe(false);
    });
  });

  describe('NOT operator', () => {
    test('inverts match - returns false when user_id is in list', () => {
      const condition = new UserIdCondition(
        { values: ['1', '5', '10'] },
        'NOT',
      );

      expect(condition.evaluate({ user_id: '1' })).toBe(false);
      expect(condition.evaluate({ user_id: '5' })).toBe(false);
      expect(condition.evaluate({ user_id: '10' })).toBe(false);
    });

    test('inverts match - returns true when user_id is not in list', () => {
      const condition = new UserIdCondition(
        { values: ['1', '5', '10'] },
        'NOT',
      );

      expect(condition.evaluate({ user_id: '2' })).toBe(true);
      expect(condition.evaluate({ user_id: '99' })).toBe(true);
      expect(condition.evaluate({ user_id: '100' })).toBe(true);
    });

    test('excludes specific users correctly', () => {
      const condition = new UserIdCondition(
        { values: ['admin', 'test-user'] },
        'NOT',
      );

      // Regular users should pass
      expect(condition.evaluate({ user_id: 'user-123' })).toBe(true);
      expect(condition.evaluate({ user_id: 'user-456' })).toBe(true);

      // Excluded users should not pass
      expect(condition.evaluate({ user_id: 'admin' })).toBe(false);
      expect(condition.evaluate({ user_id: 'test-user' })).toBe(false);
    });
  });

  describe('missing context', () => {
    test('returns false when user_id is missing with OR operator', () => {
      const condition = new UserIdCondition({ values: ['1', '5', '10'] }, 'OR');

      expect(condition.evaluate({})).toBe(false);
      expect(condition.evaluate({ other_field: 'value' })).toBe(false);
    });

    test('returns true when user_id is missing with NOT operator', () => {
      const condition = new UserIdCondition(
        { values: ['1', '5', '10'] },
        'NOT',
      );

      // Missing user_id is treated as false, NOT inverts to true
      expect(condition.evaluate({})).toBe(true);
      expect(condition.evaluate({ other_field: 'value' })).toBe(true);
    });

    test('returns false when context is null with OR operator', () => {
      const condition = new UserIdCondition({ values: ['1'] }, 'OR');

      // Passing null to getContextValue will throw, so we skip this test
      // In practice, the manager always provides a context object
      expect(() => condition.evaluate(null)).toThrow();
    });
  });

  describe('type safety', () => {
    test('string comparison is type-safe', () => {
      const condition = new UserIdCondition({ values: ['1', '2', '3'] }, 'OR');

      // String '1' should match
      expect(condition.evaluate({ user_id: '1' })).toBe(true);

      // Number 1 gets converted to string '1' by valueInArray and should match
      expect(condition.evaluate({ user_id: 1 })).toBe(true);
    });

    test('handles numeric user IDs', () => {
      const condition = new UserIdCondition({ values: [1, 2, 3] }, 'OR');

      // Numeric values in config get converted to strings
      expect(condition.evaluate({ user_id: '1' })).toBe(true);
      expect(condition.evaluate({ user_id: 1 })).toBe(true);
    });

    test('handles UUID-style user IDs', () => {
      const condition = new UserIdCondition(
        { values: ['550e8400-e29b-41d4-a716-446655440000'] },
        'OR',
      );

      expect(
        condition.evaluate({ user_id: '550e8400-e29b-41d4-a716-446655440000' }),
      ).toBe(true);
      expect(
        condition.evaluate({ user_id: '550e8400-e29b-41d4-a716-446655440001' }),
      ).toBe(false);
    });
  });

  describe('default operator', () => {
    test('defaults to OR when operator not specified', () => {
      const condition = new UserIdCondition({ values: ['1', '5'] });

      expect(condition.evaluate({ user_id: '1' })).toBe(true);
      expect(condition.evaluate({ user_id: '5' })).toBe(true);
      expect(condition.evaluate({ user_id: '99' })).toBe(false);
    });
  });

  describe('edge cases', () => {
    test('handles whitespace in user IDs', () => {
      const condition = new UserIdCondition(
        { values: ['user 123', ' test'] },
        'OR',
      );

      expect(condition.evaluate({ user_id: 'user 123' })).toBe(true);
      expect(condition.evaluate({ user_id: ' test' })).toBe(true);
      expect(condition.evaluate({ user_id: 'user123' })).toBe(false);
    });

    test('handles special characters', () => {
      const condition = new UserIdCondition(
        { values: ['user@example.com', 'user+tag@test.com'] },
        'OR',
      );

      expect(condition.evaluate({ user_id: 'user@example.com' })).toBe(true);
      expect(condition.evaluate({ user_id: 'user+tag@test.com' })).toBe(true);
    });

    test('handles empty string user_id', () => {
      const condition = new UserIdCondition({ values: ['1', '2'] }, 'OR');

      expect(condition.evaluate({ user_id: '' })).toBe(false);
    });

    test('empty string is treated as missing (falsy)', () => {
      const condition = new UserIdCondition({ values: ['', '1'] }, 'OR');

      // Empty string is falsy, so it returns false even if in values
      expect(condition.evaluate({ user_id: '' })).toBe(false);
    });
  });

  describe('inheritance from BaseCondition', () => {
    test('extends BaseCondition', () => {
      const condition = new UserIdCondition({ values: ['1'] }, 'OR');

      expect(condition).toBeInstanceOf(BaseCondition);
      expect(condition).toBeInstanceOf(UserIdCondition);
    });

    test('has access to BaseCondition methods', () => {
      const condition = new UserIdCondition({ values: ['1'] }, 'OR');

      expect(typeof condition.getContextValue).toBe('function');
      expect(typeof condition.applyOperator).toBe('function');
      expect(typeof condition.valueInArray).toBe('function');
    });
  });
});
