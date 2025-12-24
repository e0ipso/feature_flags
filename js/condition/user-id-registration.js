/* global ConditionFactory, UserIdCondition */

((Drupal, once) => {
  Drupal.behaviors.userIdRegistration = {
    attach(context, settings) {
      const [canDo] = once('user-id-registration', document.body);
      if (!canDo) {
        return;
      }
      ConditionFactory.classMap.set('user_id', UserIdCondition);
    },
  };
})(Drupal, once);
