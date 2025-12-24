/* global AlgorithmFactory, PercentageRollout */

((Drupal, once) => {
  Drupal.behaviors.percentageRolloutRegistration = {
    attach(context, settings) {
      const [canDo] = once('percentage-rollout-registration', document.body);
      if (!canDo) {
        return;
      }
      AlgorithmFactory.classMap.set('percentage_rollout', PercentageRollout);
    },
  };
})(Drupal, once);
