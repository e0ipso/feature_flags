/**
 * @file
 * Feature Flag Manager - main entry point for resolving feature flags.
 */

/**
 * Manages feature flag resolution and decision-making.
 */
class FeatureFlagManager {
  /**
   * Constructs a FeatureFlagManager object.
   *
   * @param {Object} initialContext - Optional initial context object.
   */
  constructor(initialContext = {}) {
    this.initialContext = initialContext;
    this.settings = drupalSettings.featureFlags?.settings || {};
    this.flags = drupalSettings.featureFlags?.flags || {};
    this.libraries = drupalSettings.featureFlags?.libraries || {};
  }

  /**
   * Resolves a feature flag to a variant.
   *
   * @param {string} flagId - The feature flag machine name.
   * @return {Promise<FeatureFlagResult>} The resolved result.
   */
  async resolve(flagId) {
    this.debugLog(`Resolving flag: ${flagId}`);

    // Get flag configuration.
    const flagConfig = this.flags[flagId];
    if (!flagConfig) {
      throw new Error(`Feature flag "${flagId}" not found`);
    }

    const featureFlag = new FeatureFlagConfig(flagConfig);

    // Check for cached decision if persistence is enabled.
    if (this.settings.persist) {
      const cached = this.getCachedDecision(flagId);
      if (cached) {
        const variant = featureFlag.getVariant(cached.variantUuid);
        if (variant) {
          this.debugLog(`Using cached decision for ${flagId}: variant ${variant.label}`);
          return new FeatureFlagResult(featureFlag, variant);
        }
      }
    }

    // Build context.
    const context = await this.buildContext();
    this.debugLog('Context:', context);

    // Evaluate algorithms in order.
    const algorithms = featureFlag.getAlgorithms();
    for (const algorithmConfig of algorithms) {
      this.debugLog(`Evaluating algorithm: ${algorithmConfig.pluginId}`);

      // Check if all conditions pass.
      const conditionsPassed = await this.evaluateConditions(
        algorithmConfig.conditions || [],
        context
      );

      this.debugLog(`Algorithm ${algorithmConfig.pluginId} conditions met: ${conditionsPassed}`);

      if (conditionsPassed) {
        // Execute this algorithm.
        const variant = await this.executeAlgorithm(
          algorithmConfig,
          featureFlag.getVariants(),
          context
        );

        if (variant) {
          this.debugLog(`Decision: variant ${variant.label} (${variant.uuid})`);

          // Cache the decision if persistence is enabled.
          if (this.settings.persist) {
            this.cacheDecision(flagId, variant.uuid);
          }

          return new FeatureFlagResult(featureFlag, variant);
        }
      }
    }

    // No algorithm matched - this shouldn't happen if validation is correct.
    throw new Error(`No matching algorithm found for feature flag "${flagId}"`);
  }

  /**
   * Builds the context object by dispatching the context event.
   *
   * Supports both synchronous and asynchronous context providers.
   * Async providers can return Promises that will be awaited.
   *
   * @return {Promise<Object>} The context object.
   */
  async buildContext() {
    const context = { ...this.initialContext };
    const promises = [];

    // Dispatch context event to allow other code to add context.
    const event = new CustomEvent('featureFlags:provideContext', {
      detail: {
        addContext: (key, value) => {
          // Support both sync values and promises
          if (value && typeof value.then === 'function') {
            promises.push(value.then(resolvedValue => {
              context[key] = resolvedValue;
            }));
          } else {
            context[key] = value;
          }
        }
      }
    });

    document.dispatchEvent(event);

    // Wait for all async context providers to complete.
    if (promises.length > 0) {
      await Promise.all(promises);
    }

    // Provide default user_id if not set.
    if (!context.user_id) {
      // Generate a random UUID for anonymous users.
      context.user_id = this.generateUuid();
    }

    return context;
  }

  /**
   * Evaluates all conditions for an algorithm.
   *
   * @param {Array} conditions - Array of condition configurations.
   * @param {Object} context - The context object.
   * @return {Promise<boolean>} True if any condition passes (OR logic).
   */
  async evaluateConditions(conditions, context) {
    // No conditions means this is a catch-all algorithm.
    if (!conditions || conditions.length === 0) {
      return true;
    }

    // Multiple conditions use OR logic - any passing condition is sufficient.
    for (const conditionConfig of conditions) {
      const result = await this.evaluateCondition(conditionConfig, context);
      this.debugLog(`Condition ${conditionConfig.pluginId} (${conditionConfig.operator}): ${result}`);

      if (result) {
        // At least one condition passed, so the algorithm applies.
        return true;
      }
    }

    // No conditions passed.
    return false;
  }

  /**
   * Evaluates a single condition.
   *
   * @param {Object} conditionConfig - The condition configuration.
   * @param {Object} context - The context object.
   * @return {Promise<boolean>} True if the condition passes.
   */
  async evaluateCondition(conditionConfig, context) {
    const className = conditionConfig.jsClass;
    const ConditionClass = window[className];

    if (!ConditionClass) {
      console.error(`[Feature Flags] Condition class "${className}" not found`);
      return false;
    }

    const condition = new ConditionClass(
      conditionConfig.configuration,
      conditionConfig.operator
    );

    return condition.evaluate(context);
  }

  /**
   * Executes an algorithm to select a variant.
   *
   * @param {Object} algorithmConfig - The algorithm configuration.
   * @param {Array} variants - Array of variants.
   * @param {Object} context - The context object.
   * @return {Promise<Object>} The selected variant.
   */
  async executeAlgorithm(algorithmConfig, variants, context) {
    const className = algorithmConfig.jsClass;
    const AlgorithmClass = window[className];

    if (!AlgorithmClass) {
      console.error(`[Feature Flags] Algorithm class "${className}" not found`);
      return null;
    }

    const algorithm = new AlgorithmClass(variants, algorithmConfig.configuration);
    return await algorithm.decide(context);
  }

  /**
   * Gets a cached decision from localStorage.
   *
   * @param {string} flagId - The feature flag ID.
   * @return {Object|null} The cached decision or null.
   */
  getCachedDecision(flagId) {
    try {
      const key = `feature_flags:${flagId}`;
      const cached = localStorage.getItem(key);
      if (cached) {
        return JSON.parse(cached);
      }
    }
    catch (e) {
      // Ignore localStorage errors.
    }
    return null;
  }

  /**
   * Caches a decision in localStorage.
   *
   * @param {string} flagId - The feature flag ID.
   * @param {string} variantUuid - The variant UUID.
   */
  cacheDecision(flagId, variantUuid) {
    try {
      const key = `feature_flags:${flagId}`;
      const data = {
        variantUuid: variantUuid,
        timestamp: Date.now()
      };
      localStorage.setItem(key, JSON.stringify(data));
    }
    catch (e) {
      // Ignore localStorage errors.
    }
  }

  /**
   * Logs debug messages if debug mode is enabled.
   *
   * @param {...*} args - Arguments to log.
   */
  debugLog(...args) {
    if (this.settings.debug_mode || this.settings.debug) {
      console.debug('[Feature Flags]', ...args);
    }
  }

  /**
   * Generates a simple UUID v4.
   *
   * @return {string} A UUID string.
   */
  generateUuid() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
      const r = Math.random() * 16 | 0;
      const v = c === 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
  }
}

// Export for module usage.
if (typeof module !== 'undefined' && module.exports) {
  module.exports = FeatureFlagManager;
}
