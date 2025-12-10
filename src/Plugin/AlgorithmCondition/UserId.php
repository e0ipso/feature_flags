<?php

declare(strict_types=1);

namespace Drupal\feature_flags\Plugin\AlgorithmCondition;

use Drupal\Core\Form\FormStateInterface;

/**
 * User ID condition plugin.
 *
 * Matches against specific user IDs provided in the context.
 *
 * @AlgorithmCondition(
 *   id = "user_id",
 *   label = @Translation("User ID"),
 *   description = @Translation("Match against specific user IDs"),
 *   context_key = "user_id",
 *   js_library = "feature_flags/condition.user_id",
 *   js_class = "UserIdCondition"
 * )
 */
class UserId extends AlgorithmConditionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $values = $this->configuration['values'] ?? [];
    $values_string = is_array($values) ? implode(', ', $values) : '';

    $form['values'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User IDs'),
      '#description' => $this->t('Enter user IDs separated by commas (e.g., "1, 5, 10")'),
      '#default_value' => $values_string,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::validateConfigurationForm($form, $form_state);

    $values_string = $form_state->getValue('values', '');

    if (empty(trim($values_string))) {
      $form_state->setError(
        $form['values'],
        $this->t('Please enter at least one user ID.')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);

    $values_string = $form_state->getValue('values', '');

    // Split by comma and trim whitespace from each value.
    $values = array_map('trim', explode(',', $values_string));

    // Filter out empty values.
    $values = array_filter($values, fn($v) => $v !== '');

    $this->configuration['values'] = array_values($values);
  }

  /**
   * {@inheritdoc}
   */
  public function getJavaScriptSettings(): array {
    return [
      'values' => $this->configuration['values'],
    ];
  }

}
