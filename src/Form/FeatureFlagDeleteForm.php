<?php

declare(strict_types=1);

namespace Drupal\feature_flags\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Builds the form to delete Feature Flag entities.
 */
class FeatureFlagDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the feature flag %name?', [
      '%name' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('entity.feature_flag.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone. The feature flag and all its configuration will be permanently deleted.');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    // Attach delete form styling library.
    $form['#attached']['library'][] = 'feature_flags/admin_delete';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->entity->delete();

    $this->messenger()->addStatus(
      $this->t('Feature flag %label has been deleted.', [
        '%label' => $this->entity->label(),
      ])
    );

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
