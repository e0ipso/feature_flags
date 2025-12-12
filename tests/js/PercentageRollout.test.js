/**
 * @file
 * Jest tests for PercentageRollout algorithm.
 */

const BaseAlgorithm = require('../../js/base/BaseAlgorithm.js');

// Make BaseAlgorithm available globally for PercentageRollout
global.BaseAlgorithm = BaseAlgorithm;

const PercentageRollout = require('../../js/algorithm/PercentageRollout.js');

describe('PercentageRollout', () => {
  let variants;

  beforeEach(() => {
    variants = [
      { uuid: 'variant-a', label: 'Control', value: '{"enabled": false}' },
      { uuid: 'variant-b', label: 'Treatment', value: '{"enabled": true}' },
    ];
  });

  describe('deterministic hashing', () => {
    test('returns same variant for same user_id when persistence enabled', async () => {
      global.drupalSettings.featureFlags.settings.persist_decisions = true;

      const config = {
        percentages: {
          'variant-a': 50,
          'variant-b': 50,
        },
      };

      const algorithm = new PercentageRollout(variants, config);
      const context = { user_id: 'user-123' };

      // Call decide multiple times with same user_id
      const result1 = await algorithm.decide(context);
      const result2 = await algorithm.decide(context);
      const result3 = await algorithm.decide(context);

      // Should always return the same variant
      expect(result1.uuid).toBe(result2.uuid);
      expect(result2.uuid).toBe(result3.uuid);
    });

    test('returns different variants for different user_ids', async () => {
      global.drupalSettings.featureFlags.settings.persist_decisions = true;

      const config = {
        percentages: {
          'variant-a': 50,
          'variant-b': 50,
        },
      };

      const algorithm = new PercentageRollout(variants, config);

      // Test with many different user IDs to find at least one different result
      const results = new Set();
      for (let i = 0; i < 100; i++) {
        const result = await algorithm.decide({ user_id: `user-${i}` });
        results.add(result.uuid);
      }

      // With 50/50 split and 100 users, we should see both variants
      expect(results.size).toBe(2);
      expect(results.has('variant-a')).toBe(true);
      expect(results.has('variant-b')).toBe(true);
    });
  });

  describe('distribution approximation', () => {
    test('50/50 split distributes correctly over many calls', async () => {
      global.drupalSettings.featureFlags.settings.persist_decisions = true;

      const config = {
        percentages: {
          'variant-a': 50,
          'variant-b': 50,
        },
      };

      const algorithm = new PercentageRollout(variants, config);
      const counts = { 'variant-a': 0, 'variant-b': 0 };

      // Test with 1000 different user IDs
      for (let i = 0; i < 1000; i++) {
        const result = await algorithm.decide({ user_id: `user-${i}` });
        counts[result.uuid]++;
      }

      // Each variant should get approximately 50% (±10% tolerance)
      expect(counts['variant-a']).toBeGreaterThan(400);
      expect(counts['variant-a']).toBeLessThan(600);
      expect(counts['variant-b']).toBeGreaterThan(400);
      expect(counts['variant-b']).toBeLessThan(600);
    });

    test('0% variant never selected', async () => {
      global.drupalSettings.featureFlags.settings.persist_decisions = true;

      const config = {
        percentages: {
          'variant-a': 0,
          'variant-b': 100,
        },
      };

      const algorithm = new PercentageRollout(variants, config);
      const counts = { 'variant-a': 0, 'variant-b': 0 };

      // Test with 100 different user IDs
      for (let i = 0; i < 100; i++) {
        const result = await algorithm.decide({ user_id: `user-${i}` });
        counts[result.uuid]++;
      }

      // Variant A should never be selected
      expect(counts['variant-a']).toBe(0);
      expect(counts['variant-b']).toBe(100);
    });

    test('3-way split distributes correctly', async () => {
      global.drupalSettings.featureFlags.settings.persist_decisions = true;

      const threeVariants = [
        { uuid: 'variant-a', label: 'Control', value: '{"enabled": false}' },
        { uuid: 'variant-b', label: 'Treatment 1', value: '{"enabled": true}' },
        {
          uuid: 'variant-c',
          label: 'Treatment 2',
          value: '{"enabled": true, "color": "blue"}',
        },
      ];

      const config = {
        percentages: {
          'variant-a': 25,
          'variant-b': 25,
          'variant-c': 50,
        },
      };

      const algorithm = new PercentageRollout(threeVariants, config);
      const counts = { 'variant-a': 0, 'variant-b': 0, 'variant-c': 0 };

      // Test with 1000 different user IDs
      for (let i = 0; i < 1000; i++) {
        const result = await algorithm.decide({ user_id: `user-${i}` });
        counts[result.uuid]++;
      }

      // Variant A should get ~25% (±10% tolerance)
      expect(counts['variant-a']).toBeGreaterThan(150);
      expect(counts['variant-a']).toBeLessThan(350);

      // Variant B should get ~25% (±10% tolerance)
      expect(counts['variant-b']).toBeGreaterThan(150);
      expect(counts['variant-b']).toBeLessThan(350);

      // Variant C should get ~50% (±10% tolerance)
      expect(counts['variant-c']).toBeGreaterThan(400);
      expect(counts['variant-c']).toBeLessThan(600);
    });
  });

  describe('random selection without persistence', () => {
    test('uses random bucketing when persistence disabled', async () => {
      global.drupalSettings.featureFlags.settings.persist_decisions = false;

      const config = {
        percentages: {
          'variant-a': 50,
          'variant-b': 50,
        },
      };

      const algorithm = new PercentageRollout(variants, config);

      // Mock getRandomBucket to return predictable values
      const originalGetRandomBucket = algorithm.getRandomBucket;
      let callCount = 0;

      algorithm.getRandomBucket = () => {
        // Return 25 then 75 alternately to hit different buckets
        const value = callCount % 2 === 0 ? 25 : 75;
        callCount++;
        return value;
      };

      const result1 = await algorithm.decide({ user_id: 'user-123' });
      const result2 = await algorithm.decide({ user_id: 'user-123' });

      // With 50/50 split:
      // - bucket 0-49 (cumulative 50) -> variant-a
      // - bucket 50-99 (cumulative 100) -> variant-b
      // bucket 25 < 50 -> variant-a
      // bucket 75 >= 50 but < 100 -> variant-b
      expect(result1.uuid).toBe('variant-a');
      expect(result2.uuid).toBe('variant-b');

      // Restore original method
      algorithm.getRandomBucket = originalGetRandomBucket;
    });
  });

  describe('hash function', () => {
    test('hashString returns consistent value for same input', () => {
      const algorithm = new PercentageRollout(variants, {});

      const hash1 = algorithm.hashString('user-123');
      const hash2 = algorithm.hashString('user-123');
      const hash3 = algorithm.hashString('user-123');

      expect(hash1).toBe(hash2);
      expect(hash2).toBe(hash3);
    });

    test('hashString returns value between 0 and 99', () => {
      const algorithm = new PercentageRollout(variants, {});

      for (let i = 0; i < 100; i++) {
        const hash = algorithm.hashString(`user-${i}`);
        expect(hash).toBeGreaterThanOrEqual(0);
        expect(hash).toBeLessThan(100);
      }
    });

    test('hashString distributes values across range', () => {
      const algorithm = new PercentageRollout(variants, {});
      const buckets = new Array(10).fill(0);

      // Hash 1000 different user IDs
      for (let i = 0; i < 1000; i++) {
        const hash = algorithm.hashString(`user-${i}`);
        const bucket = Math.floor(hash / 10);
        buckets[bucket]++;
      }

      // Each bucket (0-9, 10-19, ..., 90-99) should have some values
      // With good distribution, each should have roughly 100 values (±50 tolerance)
      buckets.forEach(count => {
        expect(count).toBeGreaterThan(50);
        expect(count).toBeLessThan(150);
      });
    });
  });

  describe('fallback behavior', () => {
    test('returns first variant when no percentages configured', async () => {
      const config = { percentages: {} };
      const algorithm = new PercentageRollout(variants, config);

      const result = await algorithm.decide({ user_id: 'user-123' });

      expect(result.uuid).toBe('variant-a');
    });

    test('handles missing user_id when persistence enabled', async () => {
      global.drupalSettings.featureFlags.settings.persist_decisions = true;

      const config = {
        percentages: {
          'variant-a': 50,
          'variant-b': 50,
        },
      };

      const algorithm = new PercentageRollout(variants, config);

      // Should fall back to random bucketing
      const result = await algorithm.decide({});

      expect(['variant-a', 'variant-b']).toContain(result.uuid);
    });
  });
});
