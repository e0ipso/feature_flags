/* global FeatureFlagConfig, FeatureFlagResult */

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
   * @param {Object} config - Feature flags drupal settings.
   * @param {Object} initialContext - Optional initial context object.
   * @param {Map} algorithmInstances - Map of algorithm UUID to instance.
   * @param {Map} conditionInstances - Map of condition UUID to instance.
   */
  constructor(
    config,
    initialContext = {},
    algorithmInstances = new Map(),
    conditionInstances = new Map(),
  ) {
    this.initialContext = initialContext;
    this.settings = config?.settings || {};
    this.flags = config?.flags || {};
    this.libraries = config?.libraries || {};
    this.algorithmInstances = algorithmInstances;
    this.conditionInstances = conditionInstances;
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
          this.debugLog(
            `Using cached decision for ${flagId}: variant ${variant.label}`,
          );
          return new FeatureFlagResult(featureFlag, variant);
        }
      }
    }

    // Build context.
    const context = await this.buildContext();
    this.debugLog('Context:', context);

    // Evaluate algorithms in order until one returns a variant.
    // Must be sequential to respect weight-based ordering and early exit.
    const algorithms = featureFlag.getAlgorithms();
    const variant = await algorithms.reduce(
      async (previousPromise, algorithmConfig) => {
        // Wait for previous iteration (maintains sequential execution).
        const previousResult = await previousPromise;

        // If we already found a variant, skip further evaluation.
        if (previousResult) {
          return previousResult;
        }

        this.debugLog(`Evaluating algorithm: ${algorithmConfig.pluginId}`);

        // Check if all conditions pass.
        const conditionsPassed = await this.evaluateConditions(
          algorithmConfig.conditions || [],
          context,
        );

        this.debugLog(
          `Algorithm ${algorithmConfig.pluginId} conditions met: ${conditionsPassed}`,
        );

        if (conditionsPassed) {
          // Execute this algorithm.
          const selectedVariant = await this.executeAlgorithm(
            algorithmConfig.uuid,
            algorithmConfig.configuration,
            featureFlag.getVariants(),
            context,
          );

          if (selectedVariant) {
            this.debugLog(
              `Decision: variant ${selectedVariant.label} (${selectedVariant.uuid})`,
            );
            return selectedVariant;
          }
        }

        return null;
      },
      Promise.resolve(null),
    );

    if (variant) {
      // Cache the decision if persistence is enabled.
      if (this.settings.persist) {
        this.cacheDecision(flagId, variant.uuid);
      }

      return new FeatureFlagResult(featureFlag, variant);
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
            promises.push(
              value.then(resolvedValue => {
                context[key] = resolvedValue;
              }),
            );
          } else {
            context[key] = value;
          }
        },
      },
    });

    document.dispatchEvent(event);

    // Wait for all async context providers to complete.
    if (promises.length > 0) {
      await Promise.all(promises);
    }

    // Provide default user_id if not set.
    if (!context.user_id) {
      // Generate a random ID for anonymous users.
      context.user_id = Math.random().toString(36).substring(2);
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
    // Evaluate all conditions in parallel and check if any passed.
    const conditionResults = await Promise.all(
      conditions.map(async conditionConfig => {
        const result = await this.evaluateCondition(
          conditionConfig.uuid,
          conditionConfig.configuration,
          conditionConfig.operator,
          context,
        );
        this.debugLog(
          `Condition ${conditionConfig.pluginId} (${conditionConfig.operator}): ${result}`,
        );
        return result;
      }),
    );

    // Return true if at least one condition passed.
    return conditionResults.some(result => result);
  }

  /**
   * Evaluates a single condition using pre-instantiated plugin.
   *
   * @param {string} conditionUuid - The condition UUID.
   * @param {Object} configuration - The condition configuration.
   * @param {string} operator - The condition operator.
   * @param {Object} context - The context object.
   * @return {Promise<boolean>} True if the condition passes.
   */
  async evaluateCondition(conditionUuid, configuration, operator, context) {
    const condition = this.conditionInstances.get(conditionUuid);

    if (!condition) {
      throw new Error(
        `Condition instance with UUID "${conditionUuid}" not found`,
      );
    }

    return condition.evaluate(context);
  }

  /**
   * Executes an algorithm to select a variant using pre-instantiated plugin.
   *
   * @param {string} algorithmUuid - The algorithm UUID.
   * @param {Object} configuration - The algorithm configuration.
   * @param {Array} variants - Array of variants.
   * @param {Object} context - The context object.
   * @return {Promise<Object>} The selected variant.
   */
  async executeAlgorithm(algorithmUuid, configuration, variants, context) {
    const algorithm = this.algorithmInstances.get(algorithmUuid);

    if (!algorithm) {
      throw new Error(
        `Algorithm instance with UUID "${algorithmUuid}" not found`,
      );
    }

    return algorithm.decide(context);
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
    } catch (e) {
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
        variantUuid,
        timestamp: Date.now(),
      };
      localStorage.setItem(key, JSON.stringify(data));
    } catch (e) {
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
}
