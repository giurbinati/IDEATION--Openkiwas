<?php

namespace Drupal\metereologicaltable\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\file\Entity\File;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class MetereologicaltableDetailController extends ControllerBase
{

  private const STATE_KEY_LAST_FID = 'metereologicaltable.last_fid';
  private const STATE_KEY_LAST_SHEET = 'metereologicaltable.last_sheet';

  public function view(int $row): array
  {
    $fid = (int) \Drupal::state()->get(self::STATE_KEY_LAST_FID, 0);
    $sheetIndex = (int) \Drupal::state()->get(self::STATE_KEY_LAST_SHEET, 0);

    if (!$fid) {
      return ['#markup' => '<p>Nessun file caricato.</p>'];
    }

    $file = File::load($fid);
    if (!$file) {
      return ['#markup' => '<p>File non trovato.</p>'];
    }

    $realPath = \Drupal::service('file_system')->realpath($file->getFileUri());
    $spreadsheet = IOFactory::load($realPath);
    $sheet = $spreadsheet->getSheet($sheetIndex);

    $highestColumn = $sheet->getHighestDataColumn();
    $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

    // HEADER (riga 1)
    $headers = [];
    for ($col = 1; $col <= $highestColumnIndex; $col++) {
      $cellRef = Coordinate::stringFromColumnIndex($col) . '1';
      $value = (string) $sheet->getCell($cellRef)->getFormattedValue();
      $value = trim(preg_replace('/\s+/', ' ', $value));
      if ($value !== '') {
        $headers[$col] = $value;
      }
    }

    if (empty($headers)) {
      return ['#markup' => '<p>Header non trovato.</p>'];
    }

    // Riga Excel (i dati partono da riga 2)
    $excelRow = $row + 1;

    $rows = [];
    foreach ($headers as $colIndex => $label) {
      $cellRef = Coordinate::stringFromColumnIndex($colIndex) . (string) $excelRow;
      $value = (string) $sheet->getCell($cellRef)->getFormattedValue();
      $value = trim(preg_replace('/\s+/', ' ', $value));

      $rows[] = [
        'label' => $label,
        'value' => $value,
      ];
    }

    // Layout finale senza titolo duplicato
    $items = [];
    foreach ($rows as $item) {
      $items[] = [
        '#markup' => '<div class="xlsx-detail-row">
          <strong>' . $this->t('@k', ['@k' => $item['label']]) . ':</strong><br>
          ' . nl2br(htmlspecialchars($item['value'])) . '
        </div>',
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['xlsx-detail-wrapper']],
      '#attached' => [
        'library' => [
          'metereologicaltable/ideation_table',
        ],
      ],
      'content' => [
        '#theme' => 'item_list',
        '#items' => $items,
        '#attributes' => ['class' => ['opendatatable-detail']],
      ],
      '#cache' => ['max-age' => 0],
    ];

  }

}
