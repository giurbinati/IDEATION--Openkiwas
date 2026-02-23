<?php

namespace Drupal\feeds_xlsx\Feeds\Parser\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\feeds\Plugin\Type\ExternalPluginFormBase;

/**
 * The configuration form for the XLSX parser.
 */
class XlsxParserForm extends ExternalPluginFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['sheet_name'] = [
      '#type' => 'textfield',
      '#title' => t('Sheet name'),
      '#default_value' => $this->plugin->getConfiguration('sheet_name'),
      '#maxlength' => '254',
      '#description' => t('Name of the sheet to be set as active sheet before importing. Empty value will load active sheet.'),
    ];

    $form['no_headers'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('No headers'),
      '#description' => $this->t("Check if the imported XLSX file does not start with a header row. If checked, mapping sources must be named '0', '1', '2' etc."),
      '#default_value' => $this->plugin->getConfiguration('no_headers'),
    ];

    return $form;
  }

}
