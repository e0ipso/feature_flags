/* global ConditionFactory, UserIdCondition */

// Register immediately when module loads, not in a behavior.
// This ensures the factory is populated before feature_flags.js tries to use it.
if (ConditionFactory && UserIdCondition) {
  ConditionFactory.classMap.set('user_id', UserIdCondition);
}
