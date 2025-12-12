/**
 * @file
 * Jest tests for FeatureFlagManager.
 */

// Load base classes first
const BaseAlgorithm = require('../../js/base/BaseAlgorithm.js');
const BaseCondition = require('../../js/base/BaseCondition.js');

// Make base classes available globally
global.BaseAlgorithm = BaseAlgorithm;
global.BaseCondition = BaseCondition;

// Now load the dependent classes
const FeatureFlagManager = require('../../js/base/FeatureFlagManager.js');
const FeatureFlagConfig = require('../../js/base/FeatureFlagConfig.js');
const FeatureFlagResult = require('../../js/base/FeatureFlagResult.js');
const PercentageRollout = require('../../js/algorithm/PercentageRollout.js');
const UserIdCondition = require('../../js/condition/UserId.js');
const UserTierCondition = require('../../js/condition/UserTier.js');
const generateUuid = require('../../js/utils/uuid.js');

// Make classes available globally for FeatureFlagManager
global.PercentageRollout = PercentageRollout;
global.UserIdCondition = UserIdCondition;
global.UserTierCondition = UserTierCondition;
global.FeatureFlagConfig = FeatureFlagConfig;
global.FeatureFlagResult = FeatureFlagResult;
global.generateUuid = generateUuid;

describe('FeatureFlagManager', () => {
  let manager;

  beforeEach(() => {
    // Reset drupalSettings
    global.drupalSettings = {
      featureFlags: {
        flags: {},
        settings: {},
      },
    };

    // Clear localStorage
    localStorage.clear();

    // Reset document event listeners
    document.dispatchEvent.mockClear();

    manager = new FeatureFlagManager();
  });

  describe('constructor', () => {
    test('initializes with default values', () => {
      expect(manager.initialContext).toEqual({});
      expect(manager.settings).toEqual({});
      expect(manager.flags).toEqual({});
    });

    test('accepts initial context', () => {
      const context = { user_id: '123', custom_field: 'value' };
      const customManager = new FeatureFlagManager(context);

      expect(customManager.initialContext).toEqual(context);
    });

    test('reads settings from drupalSettings', () => {
      global.drupalSettings.featureFlags.settings = {
        debug: true,
        persist: true,
      };

      const customManager = new FeatureFlagManager();
      expect(customManager.settings.debug).toBe(true);
      expect(customManager.settings.persist).toBe(true);
    });
  });

  describe('buildContext', () => {
    test('fires featureFlags:provideContext event', async () => {
      await manager.buildContext();

      expect(document.dispatchEvent).toHaveBeenCalledTimes(1);
      const event = document.dispatchEvent.mock.calls[0][0];
      expect(event.type).toBe('featureFlags:provideContext');
      expect(event.detail).toHaveProperty('addContext');
    });

    test('includes initial context', async () => {
      const initialContext = { user_id: '123', custom: 'value' };
      const customManager = new FeatureFlagManager(initialContext);

      const context = await customManager.buildContext();

      expect(context.user_id).toBe('123');
      expect(context.custom).toBe('value');
    });

    test('allows event listeners to add context', async () => {
      document.addEventListener = jest.fn((eventType, callback) => {
        if (eventType === 'featureFlags:provideContext') {
          // Simulate the actual event dispatch
          const event = new CustomEvent('featureFlags:provideContext', {
            detail: {
              addContext: jest.fn(),
            },
          });
          callback(event);
        }
      });

      // Mock dispatchEvent to actually call listeners
      const listeners = [];
      document.addEventListener = jest.fn((eventType, callback) => {
        listeners.push({ eventType, callback });
      });
      document.dispatchEvent = jest.fn(event => {
        listeners.forEach(listener => {
          if (listener.eventType === event.type) {
            listener.callback(event);
          }
        });
      });

      // Add a listener that adds context
      document.addEventListener('featureFlags:provideContext', event => {
        event.detail.addContext('user_tier', 'premium');
        event.detail.addContext('session_id', 'abc123');
      });

      const context = await manager.buildContext();

      expect(context.user_tier).toBe('premium');
      expect(context.session_id).toBe('abc123');
    });

    test('generates default user_id if not provided', async () => {
      const context = await manager.buildContext();

      expect(context.user_id).toBeDefined();
      expect(typeof context.user_id).toBe('string');
      expect(context.user_id.length).toBeGreaterThan(0);
    });

    test('does not override existing user_id', async () => {
      const customManager = new FeatureFlagManager({ user_id: 'existing-id' });
      const context = await customManager.buildContext();

      expect(context.user_id).toBe('existing-id');
    });
  });

  describe('resolve', () => {
    test('throws error when flag not found', async () => {
      await expect(manager.resolve('nonexistent')).rejects.toThrow(
        'Feature flag "nonexistent" not found',
      );
    });

    test('returns FeatureFlagResult on success', async () => {
      global.drupalSettings.featureFlags.flags.test_flag = {
        id: 'test_flag',
        label: 'Test Flag',
        variants: [
          { uuid: 'variant-a', label: 'Control', value: '{"enabled": false}' },
          { uuid: 'variant-b', label: 'Treatment', value: '{"enabled": true}' },
        ],
        algorithms: [
          {
            uuid: 'algo-1',
            pluginId: 'percentage_rollout',
            jsClass: 'PercentageRollout',
            weight: 0,
            configuration: {
              percentages: {
                'variant-a': 50,
                'variant-b': 50,
              },
            },
            conditions: [],
          },
        ],
      };

      const result = await manager.resolve('test_flag');

      expect(result).toBeInstanceOf(FeatureFlagResult);
      expect(['variant-a', 'variant-b']).toContain(result.getVariantUuid());
    });

    test('evaluates catch-all algorithm (no conditions)', async () => {
      global.drupalSettings.featureFlags.flags.test_flag = {
        id: 'test_flag',
        label: 'Test Flag',
        variants: [
          { uuid: 'variant-a', label: 'Control', value: '{"enabled": false}' },
        ],
        algorithms: [
          {
            uuid: 'algo-1',
            pluginId: 'percentage_rollout',
            jsClass: 'PercentageRollout',
            weight: 0,
            configuration: {
              percentages: { 'variant-a': 100 },
            },
            conditions: [],
          },
        ],
      };

      const result = await manager.resolve('test_flag');

      expect(result.getVariantUuid()).toBe('variant-a');
    });

    test('evaluates algorithms in weight order', async () => {
      global.drupalSettings.featureFlags.flags.test_flag = {
        id: 'test_flag',
        label: 'Test Flag',
        variants: [
          { uuid: 'variant-a', label: 'A', value: '{"value": "a"}' },
          { uuid: 'variant-b', label: 'B', value: '{"value": "b"}' },
          { uuid: 'variant-c', label: 'C', value: '{"value": "c"}' },
        ],
        algorithms: [
          {
            uuid: 'algo-1',
            pluginId: 'percentage_rollout',
            jsClass: 'PercentageRollout',
            weight: 0,
            configuration: {
              percentages: { 'variant-a': 100 },
            },
            conditions: [],
          },
          {
            uuid: 'algo-2',
            pluginId: 'percentage_rollout',
            jsClass: 'PercentageRollout',
            weight: 1,
            configuration: {
              percentages: { 'variant-b': 100 },
            },
            conditions: [],
          },
        ],
      };

      const result = await manager.resolve('test_flag');

      // First algorithm (weight 0) should win
      expect(result.getVariantUuid()).toBe('variant-a');
    });

    test('uses later algorithm when earlier conditions do not match', async () => {
      // Mock dispatchEvent to provide context
      const listeners = [];
      document.addEventListener = jest.fn((eventType, callback) => {
        listeners.push({ eventType, callback });
      });
      document.dispatchEvent = jest.fn(event => {
        listeners.forEach(listener => {
          if (listener.eventType === event.type) {
            listener.callback(event);
          }
        });
      });

      // Add listener to provide user_id
      document.addEventListener('featureFlags:provideContext', event => {
        event.detail.addContext('user_id', 'user-999');
      });

      global.drupalSettings.featureFlags.flags.test_flag = {
        id: 'test_flag',
        label: 'Test Flag',
        variants: [
          { uuid: 'variant-a', label: 'A', value: '{"value": "a"}' },
          { uuid: 'variant-b', label: 'B', value: '{"value": "b"}' },
        ],
        algorithms: [
          {
            uuid: 'algo-1',
            pluginId: 'percentage_rollout',
            jsClass: 'PercentageRollout',
            weight: 0,
            configuration: {
              percentages: { 'variant-a': 100 },
            },
            conditions: [
              {
                uuid: 'cond-1',
                pluginId: 'user_id',
                jsClass: 'UserIdCondition',
                operator: 'OR',
                configuration: {
                  values: ['user-1', 'user-2'],
                },
              },
            ],
          },
          {
            uuid: 'algo-2',
            pluginId: 'percentage_rollout',
            jsClass: 'PercentageRollout',
            weight: 1,
            configuration: {
              percentages: { 'variant-b': 100 },
            },
            conditions: [],
          },
        ],
      };

      const result = await manager.resolve('test_flag');

      // First algorithm conditions don't match user-999, so second algorithm (catch-all) wins
      expect(result.getVariantUuid()).toBe('variant-b');
    });

    test('returns parsed JSON value', async () => {
      global.drupalSettings.featureFlags.flags.test_flag = {
        id: 'test_flag',
        label: 'Test Flag',
        variants: [
          {
            uuid: 'variant-a',
            label: 'Control',
            value: '{"enabled": true, "color": "blue", "count": 42}',
          },
        ],
        algorithms: [
          {
            uuid: 'algo-1',
            pluginId: 'percentage_rollout',
            jsClass: 'PercentageRollout',
            weight: 0,
            configuration: {
              percentages: { 'variant-a': 100 },
            },
            conditions: [],
          },
        ],
      };

      const result = await manager.resolve('test_flag');
      const value = result.getValue();

      expect(typeof value).toBe('object');
      expect(value.enabled).toBe(true);
      expect(value.color).toBe('blue');
      expect(value.count).toBe(42);
    });
  });

  describe('persistence caching', () => {
    test('caches decision in localStorage when persistence enabled', async () => {
      global.drupalSettings.featureFlags.settings.persist = true;
      global.drupalSettings.featureFlags.flags.test_flag = {
        id: 'test_flag',
        label: 'Test Flag',
        variants: [{ uuid: 'variant-a', label: 'A', value: '{"value": "a"}' }],
        algorithms: [
          {
            uuid: 'algo-1',
            pluginId: 'percentage_rollout',
            jsClass: 'PercentageRollout',
            weight: 0,
            configuration: {
              percentages: { 'variant-a': 100 },
            },
            conditions: [],
          },
        ],
      };

      await manager.resolve('test_flag');

      const cached = localStorage.getItem('feature_flags:test_flag');
      expect(cached).toBeDefined();
      expect(cached).not.toBeNull();

      const parsed = JSON.parse(cached);
      expect(parsed.variantUuid).toBe('variant-a');
      expect(parsed.timestamp).toBeDefined();
    });

    test('uses cached decision on subsequent resolves', async () => {
      global.drupalSettings.featureFlags.settings.persist = true;
      global.drupalSettings.featureFlags.flags.test_flag = {
        id: 'test_flag',
        label: 'Test Flag',
        variants: [
          { uuid: 'variant-a', label: 'A', value: '{"value": "a"}' },
          { uuid: 'variant-b', label: 'B', value: '{"value": "b"}' },
        ],
        algorithms: [
          {
            uuid: 'algo-1',
            pluginId: 'percentage_rollout',
            jsClass: 'PercentageRollout',
            weight: 0,
            configuration: {
              percentages: {
                'variant-a': 50,
                'variant-b': 50,
              },
            },
            conditions: [],
          },
        ],
      };

      const result1 = await manager.resolve('test_flag');
      const result2 = await manager.resolve('test_flag');
      const result3 = await manager.resolve('test_flag');

      // All should return same variant from cache
      expect(result1.getVariantUuid()).toBe(result2.getVariantUuid());
      expect(result2.getVariantUuid()).toBe(result3.getVariantUuid());
    });

    test('does not cache when persistence disabled', async () => {
      global.drupalSettings.featureFlags.settings.persist = false;
      global.drupalSettings.featureFlags.flags.test_flag = {
        id: 'test_flag',
        label: 'Test Flag',
        variants: [{ uuid: 'variant-a', label: 'A', value: '{"value": "a"}' }],
        algorithms: [
          {
            uuid: 'algo-1',
            pluginId: 'percentage_rollout',
            jsClass: 'PercentageRollout',
            weight: 0,
            configuration: {
              percentages: { 'variant-a': 100 },
            },
            conditions: [],
          },
        ],
      };

      await manager.resolve('test_flag');

      const cached = localStorage.getItem('feature_flags:test_flag');
      expect(cached).toBeNull();
    });
  });

  describe('evaluateConditions', () => {
    test('returns true for empty conditions array (catch-all)', async () => {
      const result = await manager.evaluateConditions([], {});
      expect(result).toBe(true);
    });

    test('returns true for null conditions (catch-all)', async () => {
      const result = await manager.evaluateConditions(null, {});
      expect(result).toBe(true);
    });

    test('returns true when at least one condition passes (OR logic)', async () => {
      const conditions = [
        {
          uuid: 'cond-1',
          pluginId: 'user_id',
          jsClass: 'UserIdCondition',
          operator: 'OR',
          configuration: {
            values: ['user-1'],
          },
        },
        {
          uuid: 'cond-2',
          pluginId: 'user_tier',
          jsClass: 'UserTierCondition',
          operator: 'OR',
          configuration: {
            values: ['premium'],
          },
        },
      ];

      const context = { user_id: 'user-999', user_tier: 'premium' };
      const result = await manager.evaluateConditions(conditions, context);

      // Second condition passes
      expect(result).toBe(true);
    });

    test('returns false when all conditions fail', async () => {
      const conditions = [
        {
          uuid: 'cond-1',
          pluginId: 'user_id',
          jsClass: 'UserIdCondition',
          operator: 'OR',
          configuration: {
            values: ['user-1'],
          },
        },
        {
          uuid: 'cond-2',
          pluginId: 'user_tier',
          jsClass: 'UserTierCondition',
          operator: 'OR',
          configuration: {
            values: ['premium'],
          },
        },
      ];

      const context = { user_id: 'user-999', user_tier: 'free' };
      const result = await manager.evaluateConditions(conditions, context);

      expect(result).toBe(false);
    });
  });

  describe('debug logging', () => {
    test('logs when debug mode enabled', async () => {
      global.drupalSettings.featureFlags.settings.debug_mode = true;
      const consoleSpy = jest.spyOn(console, 'debug').mockImplementation();

      manager.debugLog('Test message', { data: 'value' });

      expect(consoleSpy).toHaveBeenCalledWith(
        '[Feature Flags]',
        'Test message',
        { data: 'value' },
      );

      consoleSpy.mockRestore();
    });

    test('does not log when debug mode disabled', () => {
      global.drupalSettings.featureFlags.settings.debug_mode = false;
      const consoleSpy = jest.spyOn(console, 'debug').mockImplementation();

      manager.debugLog('Test message');

      expect(consoleSpy).not.toHaveBeenCalled();

      consoleSpy.mockRestore();
    });

    test('supports alternative debug setting name', async () => {
      global.drupalSettings.featureFlags.settings.debug = true;
      const consoleSpy = jest.spyOn(console, 'debug').mockImplementation();

      manager.debugLog('Test message');

      expect(consoleSpy).toHaveBeenCalled();

      consoleSpy.mockRestore();
    });
  });

  describe('error handling', () => {
    test('handles missing condition class gracefully', async () => {
      const conditions = [
        {
          uuid: 'cond-1',
          pluginId: 'nonexistent',
          jsClass: 'NonexistentCondition',
          operator: 'OR',
          configuration: {},
        },
      ];

      const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
      const result = await manager.evaluateConditions(conditions, {});

      expect(consoleSpy).toHaveBeenCalled();
      expect(result).toBe(false);

      consoleSpy.mockRestore();
    });

    test('handles missing algorithm class gracefully', async () => {
      const algorithmConfig = {
        uuid: 'algo-1',
        pluginId: 'nonexistent',
        jsClass: 'NonexistentAlgorithm',
        configuration: {},
      };

      const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
      const result = await manager.executeAlgorithm(algorithmConfig, [], {});

      expect(consoleSpy).toHaveBeenCalled();
      expect(result).toBeNull();

      consoleSpy.mockRestore();
    });

    test('handles localStorage errors gracefully', () => {
      // Mock localStorage to throw
      const originalSetItem = localStorage.setItem;
      localStorage.setItem = jest.fn(() => {
        throw new Error('QuotaExceededError');
      });

      // Should not throw
      expect(() => {
        manager.cacheDecision('test_flag', 'variant-a');
      }).not.toThrow();

      localStorage.setItem = originalSetItem;
    });

    test('handles localStorage read errors gracefully', () => {
      const originalGetItem = localStorage.getItem;
      localStorage.getItem = jest.fn(() => {
        throw new Error('StorageError');
      });

      // Should not throw, should return null
      const result = manager.getCachedDecision('test_flag');
      expect(result).toBeNull();

      localStorage.getItem = originalGetItem;
    });
  });
});
