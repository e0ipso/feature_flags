<?php

declare(strict_types=1);

namespace Drupal\feature_flags\Plugin\DecisionAlgorithm;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\feature_flags\Attribute\DecisionAlgorithm;

/**
 * Percentage rollout decision algorithm.
 *
 * Distributes users across variants based on configurable percentages.
 * Uses deterministic hashing when persistence is enabled to ensure
 * users consistently receive the same variant.
 */
#[DecisionAlgorithm(
  id: 'percentage_rollout',
  label: new TranslatableMarkup('Percentage Rollout'),
  description: new TranslatableMarkup('Distribute users across variants based on configurable percentages. When persistence is enabled, users consistently receive the same variant.'),
  js_library: 'feature_flags/algorithm.percentage_rollout',
)]
class PercentageRollout extends DecisionAlgorithmPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'percentages' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $variants = $this->getVariantsFromFormState($form_state);

    // Prevent configuration without variants to avoid confusing empty form.
    if (empty($variants)) {
      $form['no_variants'] = [
        '#markup' => '<p>' . $this->t('Please add variants in the Variants tab before configuring this algorithm.') . '</p>',
      ];
      return $form;
    }

    $form['percentages'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Variant percentages'),
      '#description' => $this->t('Specify what percentage of users should receive each variant. Total must equal 100%.'),
    ];

    $percentages = $this->configuration['percentages'];

    foreach ($variants as $variant) {
      $uuid = $variant['uuid'] ?? '';
      $label = $variant['label'] ?? 'Unnamed variant';

      $form['percentages'][$uuid] = [
        '#type' => 'number',
        '#title' => $label,
        '#default_value' => $percentages[$uuid] ?? 0,
        '#min' => 0,
        '#max' => 100,
        '#step' => 1,
        '#field_suffix' => '%',
        '#required' => TRUE,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::validateConfigurationForm($form, $form_state);

    // Get all percentage values.
    $percentages_values = $form_state->getValue('percentages', []);

    if (empty($percentages_values)) {
      return;
    }

    // Calculate the sum.
    $sum = array_sum($percentages_values);

    // Validate that sum equals 100.
    if ($sum !== 100) {
      $form_state->setError(
        $form['percentages'],
        $this->t('The sum of all percentages must equal exactly 100%. Current sum: @sum%', ['@sum' => $sum])
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['percentages'] = $form_state->getValue('percentages', []);
  }

  /**
   * {@inheritdoc}
   */
  public function getJavaScriptSettings(): array {
    return [
      'percentages' => $this->configuration['percentages'],
    ];
  }

}
