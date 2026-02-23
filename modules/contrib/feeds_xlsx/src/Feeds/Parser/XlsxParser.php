<?php

namespace Drupal\feeds_xlsx\Feeds\Parser;

use Drupal\feeds\Exception\EmptyFeedException;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\Feeds\Item\DynamicItem;
use Drupal\feeds\Feeds\Parser\ParserBase;
use Drupal\feeds\Result\FetcherResultInterface;
use Drupal\feeds\Result\ParserResult;
use Drupal\feeds\StateInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Defines XLSX feed parser.
 *
 * @FeedsParser(
 *   id = "xlsx",
 *   title = "XLSX",
 *   description = @Translation("Parse XLSX files."),
 *   form = {
 *     "configuration" = "Drupal\feeds\Feeds\Parser\Form\XlsxParserForm",
 *     "feed" = "Drupal\feeds\Feeds\Parser\Form\XlsxParserFeedForm",
 *   },
 * )
 */
class XlsxParser extends ParserBase {

  /**
   * {@inheritdoc}
   */
  public function parse(FeedInterface $feed, FetcherResultInterface $fetcher_result, StateInterface $state) {
    // Get sources.
    $sources = [];
    $skip_sources = [];
    foreach ($feed->getType()->getMappingSources() as $key => $info) {
      if (isset($info['type']) && $info['type'] != 'xlsx') {
        $skip_sources[$key] = $key;
        continue;
      }
      if (isset($info['value']) && trim(strval($info['value'])) !== '') {
        $sources[$info['value']] = $key;
      }
    }

    $feed_config = $feed->getConfigurationFor($this);

    if (!filesize($fetcher_result->getFilePath())) {
      throw new EmptyFeedException();
    }

    $reader = IOFactory::createReader('Xlsx');
    $path = \Drupal::service('file_system')->realpath($fetcher_result->getFilePath());
    $spreadsheet = $reader->load($path);
    if (!empty($feed_config['sheet_name'])) {
      $spreadsheet->setActiveSheetIndexByName($feed_config['sheet_name']);
    }
    $worksheet = $spreadsheet->getActiveSheet();
    $worksheet_array = $worksheet->toArray();

    if (!$feed_config['no_headers']) {
      $header = array_shift($worksheet_array);
    }

    $result = new ParserResult();

    foreach ($worksheet_array as $row) {

      $item = new DynamicItem();

      foreach ($row as $delta => $cell) {
        $key = isset($header[$delta]) ? $header[$delta] : $delta;
        if (isset($skip_sources[$key])) {
          // Skip custom sources that are not of type "xlsx".
          continue;
        }

        // Pick machine name of source, if one is found.
        if (isset($sources[$key])) {
          $key = $sources[$key];
        }
        $item->set($key, $cell);
      }

      $result->addItem($item);
    }

    // Report progress.
    $state->total = count($result);
    $state->pointer = count($result);
    $state->progress($state->total, $state->pointer);

    // Set progress to complete if no more results are parsed.
    if (!$result->count()) {
      $state->setCompleted();
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getMappingSources() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedCustomSourcePlugins(): array {
    return ['xlsx'];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultFeedConfiguration() {
    return [
      'sheet_name' => $this->configuration['sheet_name'],
      'no_headers' => $this->configuration['no_headers'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'sheet_name' => '',
      'no_headers' => 0,
      'line_limit' => 100,
    ];
  }

}
