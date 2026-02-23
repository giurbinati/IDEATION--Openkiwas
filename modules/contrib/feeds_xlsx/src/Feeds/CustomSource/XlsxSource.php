<?php

namespace Drupal\feeds_xlsx\Feeds\CustomSource;

use Drupal\Core\Form\FormStateInterface;
use Drupal\feeds\Feeds\CustomSource\BlankSource;

/**
 * An XLSX source.
 *
 * @FeedsCustomSource(
 *   id = "xlsx",
 *   title = @Translation("XLSX column"),
 * )
 */
class XlsxSource extends BlankSource {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Add description.
    $form['value']['#description'] = $this->configSourceDescription();
    return $form;
  }

  /**
   * Returns the description for a single source.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   A translated string if there's a description. Null otherwise.
   */
  protected function configSourceDescription() {
    if ($this->feedType->getParser()->getConfiguration('no_headers')) {
      return $this->t('Enter which column number of the Xlsx file to use: 0, 1, 2, etc.');
    }
    return $this->t('Enter the exact Xlsx column name. This is case-sensitive.');
  }

}
