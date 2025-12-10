<?php

declare(strict_types=1);

namespace Drupal\feature_flags\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\feature_flags\Entity\FeatureFlag;

/**
 * Form handler for the Feature Flag add and edit forms.
 */
class FeatureFlagForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\feature_flags\Entity\FeatureFlag $feature_flag */
    $feature_flag = $this->entity;

    // Use vertical tabs for better organization.
    $form['tabs'] = [
      '#type' => 'vertical_tabs',
      '#weight' => 99,
    ];

    // Basic Information tab.
    $form['basic'] = [
      '#type' => 'details',
      '#title' => $this->t('Basic Information'),
      '#group' => 'tabs',
      '#weight' => 0,
    ];

    $form['basic']['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $feature_flag->label(),
      '#description' => $this->t('Human-readable name for this feature flag.'),
      '#required' => TRUE,
    ];

    $form['basic']['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $feature_flag->id(),
      '#machine_name' => [
        'exists' => [$this, 'exist'],
        'source' => ['basic', 'label'],
      ],
      '#disabled' => !$feature_flag->isNew(),
      '#maxlength' => 64,
    ];

    $form['basic']['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $feature_flag->getDescription(),
      '#description' => $this->t('Internal notes about this feature flag.'),
      '#rows' => 3,
    ];

    $form['basic']['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $feature_flag->status(),
      '#description' => $this->t('Whether this feature flag is active.'),
    ];

    // Variants tab.
    $form['variants_tab'] = [
      '#type' => 'details',
      '#title' => $this->t('Variants'),
      '#group' => 'tabs',
      '#weight' => 1,
      '#description' => $this->t('Define the possible values this feature flag can resolve to. Minimum 2 variants required.'),
    ];

    // Placeholder for variants - will be enhanced with AJAX in future.
    $form['variants_tab']['variants_placeholder'] = [
      '#markup' => '<p>' . $this->t('Variant management will be implemented with AJAX support.') . '</p>',
    ];

    // Store variants as a hidden field for now.
    $form['variants'] = [
      '#type' => 'value',
      '#value' => $feature_flag->getVariants(),
    ];

    // Algorithms tab.
    $form['algorithms_tab'] = [
      '#type' => 'details',
      '#title' => $this->t('Decision Algorithms'),
      '#group' => 'tabs',
      '#weight' => 2,
      '#description' => $this->t('Configure algorithms that determine which variant a user receives.'),
    ];

    // Placeholder for algorithms - will be enhanced with AJAX in future.
    $form['algorithms_tab']['algorithms_placeholder'] = [
      '#markup' => '<p>' . $this->t('Algorithm management will be implemented with AJAX support.') . '</p>',
    ];

    // Store algorithms as a hidden field for now.
    $form['algorithms'] = [
      '#type' => 'value',
      '#value' => $feature_flag->getAlgorithms(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    // Validate minimum 2 variants.
    $variants = $form_state->getValue('variants', []);
    if (count($variants) < 2) {
      $form_state->setErrorByName('variants', $this->t('At least 2 variants are required.'));
    }

    // Validate at least one algorithm.
    $algorithms = $form_state->getValue('algorithms', []);
    if (empty($algorithms)) {
      $form_state->setErrorByName('algorithms', $this->t('At least one algorithm is required.'));
    }

    // Validate catch-all algorithm (one with no conditions).
    $has_catch_all = FALSE;
    foreach ($algorithms as $algorithm) {
      if (empty($algorithm['conditions'])) {
        $has_catch_all = TRUE;
        break;
      }
    }
    if (!$has_catch_all) {
      $form_state->setErrorByName('algorithms', $this->t('At least one algorithm without conditions (catch-all) is required.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\feature_flags\Entity\FeatureFlag $feature_flag */
    $feature_flag = $this->entity;

    // Set variants and algorithms from form state.
    $feature_flag->setVariants($form_state->getValue('variants', []));
    $feature_flag->setAlgorithms($form_state->getValue('algorithms', []));

    $status = $feature_flag->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('Created the %label feature flag.', [
        '%label' => $feature_flag->label(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('Updated the %label feature flag.', [
        '%label' => $feature_flag->label(),
      ]));
    }

    $form_state->setRedirectUrl($feature_flag->toUrl('collection'));

    return $status;
  }

  /**
   * Helper function to check whether a Feature Flag configuration entity exists.
   */
  public function exist(string $id): bool {
    $entity = $this->entityTypeManager
      ->getStorage('feature_flag')
      ->getQuery()
      ->condition('id', $id)
      ->accessCheck(FALSE)
      ->execute();
    return (bool) $entity;
  }

}
