/* global AlgorithmFactory, PercentageRollout */

// Register immediately when module loads, not in a behavior.
// This ensures the factory is populated before feature_flags.js tries to use it.
if (AlgorithmFactory && PercentageRollout) {
  AlgorithmFactory.classMap.set('percentage_rollout', PercentageRollout);
}
