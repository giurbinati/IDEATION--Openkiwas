<?php

namespace Drupal\toolstable\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;


class ToolstableForm extends FormBase
{

  private const STATE_KEY_LAST_FID = 'toolstable.last_fid';
  private const STATE_KEY_LAST_SHEET = 'toolstable.last_sheet';

  public function getFormId(): string
  {
    return 'toolstable_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $form['#attributes']['class'][] = 'viewscss';

    $last_sheet = (int) \Drupal::state()->get(self::STATE_KEY_LAST_SHEET, 0);

    $can_manage = \Drupal::currentUser()->hasPermission('administer site configuration');
    if ($can_manage) {

      $form['xlsx'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Upload XLSX file'),
        '#upload_location' => 'public://toolstable/',
        '#upload_validators' => [
          'file_validate_extensions' => ['xlsx'],
        ],
        '#required' => FALSE,
        '#description' => $this->t('Upload a new file to update the table (the latest uploaded file is kept).'),
        '#attributes' => [
          'class' => ['xlsx-hidden-input'],
        ],
      ];

      $form['sheet_index'] = [
        '#type' => 'number',
        '#title' => $this->t('Sheet index (0 = first sheet)'),
        '#default_value' => $last_sheet,
        '#min' => 0,
      ];

      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Upload / Update Table'),
        '#attributes' => [
          'class' => ['wp3-btn'],
        ],
      ];
    }

    $form['#attached']['library'][] = 'toolstable/ideation_table';

    // Mostra sempre l'ultima tabella caricata (persistente)
    $last_fid = (int) \Drupal::state()->get(self::STATE_KEY_LAST_FID, 0);
    if ($last_fid > 0) {
      $file = File::load($last_fid);
      if ($file) {
        $table = $this->buildTableFromFile($file, $last_sheet);

        // NIENTE details (evita problemi di rendering nascosto)
        // Wrapper generale (centratura/stili pagina)
        $form['table_wrapper'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['viewscss'],
          ],
        ];

        $form['table_wrapper']['description'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['openkiwas-description'],
          ],
          'text' => [
            '#markup' => '
      <p>
        This catalogue is a dataset of data models, AI technologies, simulation tools, intelligent systems, and digital twins for inland water management.
The catalogue includes hydrological models for predicting flow and quality, social models for water usage, and hydraulic models for urban distribution. AI technologies, such as machine learning algorithms for processing hydrological data and analysing satellite images, are identified. Simulation tools are catalogued based on their ability to predict event outcomes. Intelligent systems integrating cyber-physical architectures, sensors, actuators, and software are analysed for their roles in monitoring, event detection, risk assessment, and decision support. Digital twins, which combine real data with models and simulations to create virtual system replicas, are also included.
      </p>
    ',
          ],
        ];


        // SEARCH (FUORI dallo scroll orizzontale)
        $form['table_wrapper']['search'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Search'),
          '#title_display' => 'invisible',
          '#attributes' => [
            'class' => ['xlsx-search'],
            'placeholder' => 'Search...',
            'aria-label' => 'Search',
          ],
        ];

        // Wrapper SOLO per la tabella (QUI metti overflow-x)
        $form['table_wrapper']['table_scroll'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['xlsx-table-scroll'],
          ],
        ];

        // Tabella + pager (ritorna da buildTableFromFile)
        $form['table_wrapper']['table_scroll']['table_render'] = $table;


      }
    }

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void
  {
    $fids = $form_state->getValue('xlsx');
    if (!empty($fids) && !is_array($fids)) {
      $form_state->setErrorByName('xlsx', $this->t('Upload non valido.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    if (!\Drupal::currentUser()->hasPermission('administer site configuration')) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }
    $fids = $form_state->getValue('xlsx');
    $sheetIndex = (int) $form_state->getValue('sheet_index');

    if (empty($fids) || empty($fids[0])) {
      $this->messenger()->addStatus($this->t('Nessun nuovo file caricato. Mostro l’ultimo disponibile.'));
      return;
    }

    $file = File::load((int) $fids[0]);
    if (!$file) {
      $this->messenger()->addError($this->t('File non trovato.'));
      return;
    }

    $file->setPermanent();
    $file->save();

    \Drupal::state()->set(self::STATE_KEY_LAST_FID, (int) $file->id());
    \Drupal::state()->set(self::STATE_KEY_LAST_SHEET, $sheetIndex);

    try {
      $this->buildTableFromFile($file, $sheetIndex);
      $this->messenger()->addStatus($this->t('OK: file caricato e tabella aggiornata.'));
    } catch (\Throwable $e) {
      \Drupal::logger('toolstable')->error('Errore XLSX: @e', ['@e' => $e->getMessage()]);
      $this->messenger()->addError($this->t('Errore leggendo XLSX: @msg', ['@msg' => $e->getMessage()]));
    }

    $form_state->setRedirect('<current>');
  }

  private function buildTableFromFile(File $file, int $sheetIndex = 0): array
  {
    $realPath = \Drupal::service('file_system')->realpath($file->getFileUri());

    \Drupal::logger('toolstable')->notice('Loading XLSX: @path sheet=@sheet', [
      '@path' => $realPath,
      '@sheet' => $sheetIndex,
    ]);

    $spreadsheet = IOFactory::load($realPath);
    $sheet = $spreadsheet->getSheet($sheetIndex);

    $highestRow = $sheet->getHighestDataRow();
    $highestColumn = $sheet->getHighestDataColumn();
    $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

    \Drupal::logger('toolstable')->notice('Detected range: rows=@r cols=@c', [
      '@r' => $highestRow,
      '@c' => $highestColumnIndex,
    ]);

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

    \Drupal::logger('toolstable')->notice('Headers found: @h', [
      '@h' => implode(' | ', $headers),
    ]);

    if (empty($headers)) {
      return ['#markup' => '<p>Header non trovato nel foglio Excel.</p>'];
    }

    // Helper: estrai testo “visibile” da una cella (stringa o render array).
    $cellToText = function ($cell): string {
      if (is_string($cell)) {
        return trim($cell);
      }
      if (is_array($cell)) {
        if (isset($cell['data']['#title'])) {
          return trim((string) $cell['data']['#title']);
        }
        if (isset($cell['#plain_text'])) {
          return trim((string) $cell['#plain_text']);
        }
      }
      return '';
    };

    $firstColIndex = array_key_first($headers);

    // ====== BUILD RIGHE ======
    $rows = [];

    for ($rowNum = 2; $rowNum <= $highestRow; $rowNum++) {
      $table_row = [];

      foreach ($headers as $colIndex => $label) {
        $cellRef = Coordinate::stringFromColumnIndex($colIndex) . (string) $rowNum;
        $value = (string) $sheet->getCell($cellRef)->getFormattedValue();
        $value = trim(preg_replace('/\s+/', ' ', $value));

        if ($colIndex === $firstColIndex) {
          // Prima colonna = link al dettaglio.
          $table_row[] = [
            'data' => [
              '#type' => 'link',
              '#title' => $value,
              '#url' => \Drupal\Core\Url::fromRoute('toolstable.detail', [
                'row' => $rowNum - 1,
              ]),
            ],
          ];
        } else {
          $table_row[] = $value;
        }
      }

      // Testo della prima colonna (quella a sinistra)
      $firstCellText = $cellToText($table_row[0] ?? '');

      //Salta la riga se la prima colonna è vuota/null
      if ($firstCellText === '' || $firstCellText === null) {
        continue;
      }

      // Aggiungi riga solo se contiene almeno un valore valido
      if (array_filter(array_map($cellToText, $table_row))) {
        $rows[] = $table_row;
      }
    }

    // ====== SORTING (header cliccabili) ======
    // Creiamo keys stabili: c1, c2, ... (una per colonna visibile)
    $colKeys = [];
    $pos = 0;
    foreach ($headers as $colIndex => $label) {
      $pos++;
      $colKeys["c{$pos}"] = $label;
    }
    $colPosByKey = array_keys($colKeys); // [c1,c2,c3...]

    $request = \Drupal::request();
    $sort = (string) $request->query->get('sort', array_key_first($colKeys)); // es c1
    $order = strtoupper((string) $request->query->get('order', 'ASC'));

    if (!isset($colKeys[$sort])) {
      $sort = array_key_first($colKeys);
    }
    $order = ($order === 'DESC') ? 'DESC' : 'ASC';

    // Header cliccabili con freccia
    $header_row = [];
    foreach ($colKeys as $key => $label) {
      $isActive = ($key === $sort);
      $nextOrder = ($isActive && $order === 'ASC') ? 'DESC' : 'ASC';

      $query = $request->query->all();
      $query['sort'] = $key;
      $query['order'] = $nextOrder;
      $query['page'] = 0; // quando cambi sort, riparti da pagina 1

      $title = $label . ($isActive ? ($order === 'ASC' ? ' ▲' : ' ▼') : '');

      $header_row[] = [
        'data' => [
          '#type' => 'link',
          '#title' => $title,
          '#url' => \Drupal\Core\Url::fromRoute('<current>', [], ['query' => $query]),
        ],
      ];
    }

    // Trova indice di colonna da usare per il sort
    $sortIndex = array_search($sort, $colPosByKey, TRUE); // 0-based

    usort($rows, function ($a, $b) use ($sortIndex, $order, $cellToText) {
      $va = $cellToText($a[$sortIndex] ?? '');
      $vb = $cellToText($b[$sortIndex] ?? '');

      // numerico se possibile
      $va2 = str_replace(',', '.', $va);
      $vb2 = str_replace(',', '.', $vb);
      $na = is_numeric($va2) ? (float) $va2 : null;
      $nb = is_numeric($vb2) ? (float) $vb2 : null;

      if ($na !== null && $nb !== null) {
        $cmp = $na <=> $nb;
      } else {
        $cmp = strnatcasecmp($va, $vb);
      }

      return ($order === 'DESC') ? -$cmp : $cmp;
    });

    // ====== PAGER (10 righe) ======
    $per_page = 10;
    $total = count($rows);
    $pager = \Drupal::service('pager.manager')->createPager($total, $per_page);
    $current_page = $pager->getCurrentPage();

    $rows_page = array_slice($rows, $current_page * $per_page, $per_page);

    \Drupal::logger('toolstable')->notice('Rendering table: header=@h rows_total=@rt rows_page=@rp sort=@s order=@o', [
      '@h' => count($header_row),
      '@rt' => $total,
      '@rp' => count($rows_page),
      '@s' => $sort,
      '@o' => $order,
    ]);

    // Ritorno tabella + pager
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['xlsx-table-wrap']],
      'table' => [
        '#type' => 'table',
        '#header' => $header_row,
        '#rows' => $rows_page,
        '#empty' => $this->t('No rows found.'),
        '#attributes' => [
          'class' => ['views-table', 'responsive-enabled', 'xlsx-data-table'],
        ],
      ],
      'pager' => [
        '#type' => 'pager',
      ],
    ];
  }


}
