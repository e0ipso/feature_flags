<?php

declare(strict_types=1);

namespace Drupal\feature_flags\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Feature Flags settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'feature_flags_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['feature_flags.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('feature_flags.settings');

    $form['debug_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Debug mode'),
      '#description' => $this->t('When enabled, the JavaScript client will log decision-making details to the browser console using console.debug().'),
      '#default_value' => $config->get('debug_mode') ?? FALSE,
    ];

    $form['persist_decisions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Persist decisions'),
      '#description' => $this->t('When enabled, feature flag decisions will be stored in localStorage for consistent user experiences across page loads.'),
      '#default_value' => $config->get('persist_decisions') ?? FALSE,
    ];

    $form['exclude_from_config_export'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Exclude from configuration export'),
      '#description' => $this->t('When enabled, feature flag configurations will be excluded during configuration export/import operations.'),
      '#default_value' => $config->get('exclude_from_config_export') ?? FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('feature_flags.settings')
      ->set('debug_mode', $form_state->getValue('debug_mode'))
      ->set('persist_decisions', $form_state->getValue('persist_decisions'))
      ->set('exclude_from_config_export', $form_state->getValue('exclude_from_config_export'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
