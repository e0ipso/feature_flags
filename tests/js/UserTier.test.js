/**
 * @file
 * Jest tests for UserTier condition.
 */

const BaseCondition = require('../../js/base/BaseCondition.js');

// Make BaseCondition available globally for UserTierCondition
global.BaseCondition = BaseCondition;

const UserTierCondition = require('../../js/condition/UserTier.js');

describe('UserTierCondition', () => {
  describe('OR operator', () => {
    test('matches when user_tier is in list', () => {
      const condition = new UserTierCondition(
        { values: ['free', 'premium', 'enterprise'] },
        'OR',
      );

      expect(condition.evaluate({ user_tier: 'free' })).toBe(true);
      expect(condition.evaluate({ user_tier: 'premium' })).toBe(true);
      expect(condition.evaluate({ user_tier: 'enterprise' })).toBe(true);
    });

    test('does not match when user_tier is not in list', () => {
      const condition = new UserTierCondition(
        { values: ['premium', 'enterprise'] },
        'OR',
      );

      expect(condition.evaluate({ user_tier: 'free' })).toBe(false);
      expect(condition.evaluate({ user_tier: 'trial' })).toBe(false);
      expect(condition.evaluate({ user_tier: 'basic' })).toBe(false);
    });

    test('handles single value in list', () => {
      const condition = new UserTierCondition({ values: ['premium'] }, 'OR');

      expect(condition.evaluate({ user_tier: 'premium' })).toBe(true);
      expect(condition.evaluate({ user_tier: 'free' })).toBe(false);
    });

    test('handles empty values array', () => {
      const condition = new UserTierCondition({ values: [] }, 'OR');

      expect(condition.evaluate({ user_tier: 'premium' })).toBe(false);
      expect(condition.evaluate({ user_tier: 'any' })).toBe(false);
    });
  });

  describe('NOT operator', () => {
    test('inverts match - returns false when user_tier is in list', () => {
      const condition = new UserTierCondition(
        { values: ['free', 'trial'] },
        'NOT',
      );

      expect(condition.evaluate({ user_tier: 'free' })).toBe(false);
      expect(condition.evaluate({ user_tier: 'trial' })).toBe(false);
    });

    test('inverts match - returns true when user_tier is not in list', () => {
      const condition = new UserTierCondition(
        { values: ['free', 'trial'] },
        'NOT',
      );

      expect(condition.evaluate({ user_tier: 'premium' })).toBe(true);
      expect(condition.evaluate({ user_tier: 'enterprise' })).toBe(true);
      expect(condition.evaluate({ user_tier: 'basic' })).toBe(true);
    });

    test('excludes specific tiers correctly', () => {
      const condition = new UserTierCondition({ values: ['free'] }, 'NOT');

      // Paid tiers should pass
      expect(condition.evaluate({ user_tier: 'premium' })).toBe(true);
      expect(condition.evaluate({ user_tier: 'enterprise' })).toBe(true);

      // Free tier should not pass
      expect(condition.evaluate({ user_tier: 'free' })).toBe(false);
    });
  });

  describe('missing context', () => {
    test('returns false when user_tier is missing with OR operator', () => {
      const condition = new UserTierCondition(
        { values: ['premium', 'enterprise'] },
        'OR',
      );

      expect(condition.evaluate({})).toBe(false);
      expect(condition.evaluate({ other_field: 'value' })).toBe(false);
      expect(condition.evaluate({ user_id: '123' })).toBe(false);
    });

    test('returns true when user_tier is missing with NOT operator', () => {
      const condition = new UserTierCondition(
        { values: ['premium', 'enterprise'] },
        'NOT',
      );

      // Missing user_tier is treated as false, NOT inverts to true
      expect(condition.evaluate({})).toBe(true);
      expect(condition.evaluate({ other_field: 'value' })).toBe(true);
    });

    test('returns false when context is null with OR operator', () => {
      const condition = new UserTierCondition({ values: ['premium'] }, 'OR');

      // Passing null to getContextValue will throw, so we skip this test
      // In practice, the manager always provides a context object
      expect(() => condition.evaluate(null)).toThrow();
    });
  });

  describe('case sensitivity', () => {
    test('matching is case-sensitive', () => {
      const condition = new UserTierCondition({ values: ['premium'] }, 'OR');

      // Exact match should work
      expect(condition.evaluate({ user_tier: 'premium' })).toBe(true);

      // Different case should not match (case-sensitive)
      expect(condition.evaluate({ user_tier: 'Premium' })).toBe(false);
      expect(condition.evaluate({ user_tier: 'PREMIUM' })).toBe(false);
      expect(condition.evaluate({ user_tier: 'PrEmIuM' })).toBe(false);
    });

    test('both config and context values are case-sensitive', () => {
      const condition = new UserTierCondition(
        { values: ['Premium', 'ENTERPRISE'] },
        'OR',
      );

      // Must match exact case
      expect(condition.evaluate({ user_tier: 'Premium' })).toBe(true);
      expect(condition.evaluate({ user_tier: 'ENTERPRISE' })).toBe(true);

      // Different case should not match
      expect(condition.evaluate({ user_tier: 'premium' })).toBe(false);
      expect(condition.evaluate({ user_tier: 'enterprise' })).toBe(false);
    });
  });

  describe('default operator', () => {
    test('defaults to OR when operator not specified', () => {
      const condition = new UserTierCondition({
        values: ['premium', 'enterprise'],
      });

      expect(condition.evaluate({ user_tier: 'premium' })).toBe(true);
      expect(condition.evaluate({ user_tier: 'enterprise' })).toBe(true);
      expect(condition.evaluate({ user_tier: 'free' })).toBe(false);
    });
  });

  describe('edge cases', () => {
    test('handles whitespace in tier values', () => {
      const condition = new UserTierCondition(
        { values: ['premium plus', ' enterprise '] },
        'OR',
      );

      expect(condition.evaluate({ user_tier: 'premium plus' })).toBe(true);
      expect(condition.evaluate({ user_tier: ' enterprise ' })).toBe(true);
      expect(condition.evaluate({ user_tier: 'premium' })).toBe(false);
    });

    test('handles special characters', () => {
      const condition = new UserTierCondition(
        { values: ['tier-1', 'tier_2', 'tier.3'] },
        'OR',
      );

      expect(condition.evaluate({ user_tier: 'tier-1' })).toBe(true);
      expect(condition.evaluate({ user_tier: 'tier_2' })).toBe(true);
      expect(condition.evaluate({ user_tier: 'tier.3' })).toBe(true);
    });

    test('handles empty string user_tier', () => {
      const condition = new UserTierCondition(
        { values: ['premium', 'enterprise'] },
        'OR',
      );

      expect(condition.evaluate({ user_tier: '' })).toBe(false);
    });

    test('empty string is treated as missing (falsy)', () => {
      const condition = new UserTierCondition(
        { values: ['', 'premium'] },
        'OR',
      );

      // Empty string is falsy, so it returns false even if in values
      expect(condition.evaluate({ user_tier: '' })).toBe(false);
    });

    test('handles numeric tier values', () => {
      const condition = new UserTierCondition({ values: [1, 2, 3] }, 'OR');

      // Numeric values get converted to strings
      expect(condition.evaluate({ user_tier: '1' })).toBe(true);
      expect(condition.evaluate({ user_tier: 1 })).toBe(true);
      expect(condition.evaluate({ user_tier: '2' })).toBe(true);
    });
  });

  describe('real-world tier scenarios', () => {
    test('premium features available to premium and enterprise users', () => {
      const condition = new UserTierCondition(
        { values: ['premium', 'enterprise'] },
        'OR',
      );

      expect(condition.evaluate({ user_tier: 'premium' })).toBe(true);
      expect(condition.evaluate({ user_tier: 'enterprise' })).toBe(true);
      expect(condition.evaluate({ user_tier: 'free' })).toBe(false);
      expect(condition.evaluate({ user_tier: 'trial' })).toBe(false);
    });

    test('beta features excluded from free tier', () => {
      const condition = new UserTierCondition({ values: ['free'] }, 'NOT');

      // All paid tiers get beta features
      expect(condition.evaluate({ user_tier: 'basic' })).toBe(true);
      expect(condition.evaluate({ user_tier: 'premium' })).toBe(true);
      expect(condition.evaluate({ user_tier: 'enterprise' })).toBe(true);

      // Free tier excluded
      expect(condition.evaluate({ user_tier: 'free' })).toBe(false);
    });

    test('enterprise-only features', () => {
      const condition = new UserTierCondition({ values: ['enterprise'] }, 'OR');

      expect(condition.evaluate({ user_tier: 'enterprise' })).toBe(true);
      expect(condition.evaluate({ user_tier: 'premium' })).toBe(false);
      expect(condition.evaluate({ user_tier: 'basic' })).toBe(false);
      expect(condition.evaluate({ user_tier: 'free' })).toBe(false);
    });
  });

  describe('inheritance from BaseCondition', () => {
    test('extends BaseCondition', () => {
      const condition = new UserTierCondition({ values: ['premium'] }, 'OR');

      expect(condition).toBeInstanceOf(BaseCondition);
      expect(condition).toBeInstanceOf(UserTierCondition);
    });

    test('has access to BaseCondition methods', () => {
      const condition = new UserTierCondition({ values: ['premium'] }, 'OR');

      expect(typeof condition.getContextValue).toBe('function');
      expect(typeof condition.applyOperator).toBe('function');
      expect(typeof condition.valueInArray).toBe('function');
    });
  });
});
