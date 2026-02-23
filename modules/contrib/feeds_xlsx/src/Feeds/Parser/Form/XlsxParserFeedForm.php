<?php

namespace Drupal\feeds_xlsx\Feeds\Parser\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\Plugin\Type\ExternalPluginFormBase;

/**
 * Provides a form on the feed edit page for the XlsxParser.
 */
class XlsxParserFeedForm extends ExternalPluginFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FeedInterface $feed = NULL) {
    $feed_config = $feed->getConfigurationFor($this->plugin);
    $form['sheet_name'] = [
      '#type' => 'textfield',
      '#title' => t('Sheet name'),
      '#default_value' => $feed_config['sheet_name'],
      '#maxlength' => '254',
      '#description' => t('Name of the sheet to be set as active sheet before importing.'),
    ];

    $form['no_headers'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('No Headers'),
      '#description' => $this->t("Check if the imported XLSX file does not start with a header row. If checked, mapping sources must be named '0', '1', '2' etc."),
      '#default_value' => $feed_config['no_headers'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state, FeedInterface $feed = NULL) {
    $feed->setConfigurationFor($this->plugin, $form_state->getValues());
  }

}
