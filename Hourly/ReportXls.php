<?php
/**
 * File ReportXls
 *
 * PHP version 7
 *
 * @package    App\Reports
 */

namespace App\Reports\Hourly;

use App\Reports\ReportXlsAbstract;
use Carbon\Carbon;

/**
 * Class ReportXls
 *
 * PHP version 7
 *
 * @package    App\Reports
 */
class ReportXls extends ReportXlsAbstract
{
    /**
     * @var array
     */
    protected $tableHeadersMapping = [];

    /**
     * @var int
     */
    private $row = 1;

    /**
     * @var string
     */
    private $lastColumn;

    /**
     * @var string report name
     */
    public $filename = 'ReportHourly';

    /**
     * @var array
     */
    protected $firstRowTableElements;

    /**
     * @var array
     */
    protected $secondRowTableElements;

    /**
     * @var array
     */
    protected $fieldsList;

    /**
     * @var bool
     */
    protected $needSecondRow = false;

    /**
     * @var string
     */
    protected $fromDateFormat = 'Y-m-d H';

    /**
     * @var string
     */
    protected $toDateFormat = 'd.m.y H:00';

    /**
     * @var bool
     */
    protected $isHourly = true;

    /**
     * @return $this
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function build()
    {
        $this->setTableHeaders();
        $this->lastColumn = $this->getLastColumnPosition();
        $this->formFieldsConfiguration();
        $this->setHeader();
        $this->setBody();
        $this->setStyles();
        $this->activeSheet->setTitle($this->sheetTitle);

        return $this;
    }

    /**
     * @return string
     */
    protected function setTableHeaders()
    {
        $this->tableHeadersMapping = [
            'date' =>  $this->translate('reports.hourly.date'),
            'temperature' => $this->translate('reports.hourly.temperature'),
            'outer_temp_local' => $this->translate('reports.hourly.outer_temp_local'),
            'T1' => $this->translate('reports.hourly.t1'),
            'T2' => $this->translate('reports.hourly.t2'),
            'T11' => $this->translate('reports.hourly.t11'),
            'T22' => $this->translate('reports.hourly.t22'),
            'volume' => $this->translate('reports.hourly.volume'),
            'V1' => $this->translate('reports.hourly.v1'),
            'avg_outer_temperature' => $this->translate('reports.hourly.avg_outer_temperature'),
            'normal_heating' => $this->translate('reports.hourly.normal_heating'),
            'avg_inner_temperature' => $this->translate('reports.hourly.avg_inner_temperature'),
            'counter_value' => $this->translate('reports.hourly.counter_value'),
            'consumed' => $this->translate('reports.hourly.consumed'),
            'rk' => $this->translate('reports.hourly.rk'),
            'errors' => $this->translate('reports.hourly.errors')
        ];
    }

    /**
     * @return string
     */
    protected function getTitle(): string
    {
        $date = $this->data['date']->format('d.m.Y');
        $building = $this->data['building'];
        $counter = $this->data['counter'];

        return $this->translate('reports.hourly.title', ['date' => $date, 'building' => $building, 'counter' => $counter]);
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function setHeader()
    {
        $sheet = $this->activeSheet;
        $row = $this->row;
        $column = 'A';
        $cell = $column . $row;

        $sheet->setCellValue($cell, $this->getTitle());
        $sheet->mergeCells($cell . ':' . $this->lastColumn . $row);
        $this->setRowHeight($row, $this->titleRowHeight)
            ->setAlignmentCenter($cell)
            ->setFontSize($cell, $this->titleRowFontSize)
            ->setBold($cell);

        $row++;
        $this->row = $row;
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function setBody()
    {
        $this->setTableHeaders();
        $this->setTableData();
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function setTableHeaders()
    {
        $sheet = $this->activeSheet;
        $row = $this->row;
        $column = 'A';

        foreach ($this->firstRowTableElements as $header) {
            $cell = $column . $row;
            $sheet->setCellValue(
                $cell,
                $this->tableHeadersMapping[$header['name']]
            );
            $width = 14.3;

            if ($header['name'] === 'errors') {
                $width = 65;
            } elseif ($header['name'] === 'date') {
                $width = 14;
            } elseif (!$this->isHourly && $header['name'] === 'volume') {
                $width = 15.2;
            }

            $sheet->getColumnDimension($column)->setWidth($width);
            $this->setAlignmentCenter($cell)
                ->setFontSize($cell, $this->fontSize)
                ->setBold($cell);

            if ($header['mergeRows']) {
                $sheet->mergeCells($cell . ':' . $column . ($row + 1));
            }
            if ($header['mergeColumns']) {
                $toColumn = $this->calculateColumn(
                    $column,
                    $header['mergeColumns']
                );
                $sheet->mergeCells($cell . ':' . $toColumn . $row);
                $column = $toColumn;
            }

            $column++;
        }

        $row++;

        foreach ($this->secondRowTableElements as $header) {
            $width = 14.3;
            $cell = $header['column'] . $row;
            $sheet->setCellValue(
                $cell,
                $this->tableHeadersMapping[$header['name']]
            );
            $this->setAlignmentCenter($cell)
                ->setFontSize($cell, $this->fontSize)
                ->setBold($cell);
            $sheet->getColumnDimension($header['column'])->setWidth($width);
        }

        $col = 'A';
        $row++;

        foreach ($this->fieldsList as $index => $value) {
            $sheet->setCellValue($col . $row, ++$index);
            $this->setAlignmentCenter($col . $row)
                ->setFontSize($col . $row, $this->fontSize)
                ->setBold($col . $row);
            $col++;
        }

        $this->row = ++$row;
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function setTableData()
    {
        $sheet = $this->activeSheet;
        $row = $this->row;
        $column = 'A';
        $startRow = $row;

        foreach ($this->fieldsList as $field) {
            $row = $startRow;
            foreach ($this->data['rows'] as $date => $value) {
                $cell = $column . $row;
                $this->setAlignRight($cell)->setFontSize(
                    $cell,
                    $this->fontSize
                );

                if ($field['id'] === 'date') {
                    $sheet->setCellValue(
                        $cell,
                        Carbon::createFromFormat(
                            $this->fromDateFormat,
                            $date
                        )->format($this->toDateFormat)
                    );
                } elseif ($field['id'] === 'errors') {
                    $sheet->setCellValue(
                        $cell,
                        implode(",\n", $value[$field['id']])
                    );
                    $this->setAlignLeft($cell)->setFontSize(
                        $cell,
                        $this->fontSize
                    );
                } else {
                    $canBeZero =
                        $field['id'] === 'avg_inner_temperature' ? false : true;

                    $val = $this->setValue(
                        $value[$field['id']] ?? null,
                        $field['format'],
                        $canBeZero
                    );
                    $sheet->setCellValue($cell, $val);
                }

                $row++;
            }

            $cell = $column . $row;
            // set summary row
            if ($field['id'] === 'date') {
                $sheet->setCellValue($cell, $this->summaryFieldTitle);
            } elseif ($field['id'] === 'errors') {
            } else {
                $canBeZero =
                    $field['id'] === 'avg_inner_temperature' ? false : true;

                $sheet->setCellValue(
                    $cell,
                    $this->setValue(
                        $this->data['sum'][$field['id']] ?? null,
                        $field['format'],
                        $canBeZero,
                        in_array(
                            $field['id'],
                            $this->data['config']['no_totals']
                        )
                    )
                );
            }

            $this->setAlignRight($cell)
                ->setFontSize($cell, $this->totalRowFontSize)
                ->setBold($cell);

            $column++;
        }
        $this->row = $row;
    }

    /**
     * @return string
     */
    protected function getLastColumnPosition()
    {
        $lastField = 'A';

        for ($i = 1; $i <= count($this->data['config']['formats']); $i++) {
            $lastField++;
        }
        return $lastField;
    }

    /**
     * Generate configuration
     */
    protected function formFieldsConfiguration()
    {
        $list = [];
        $firstRowElements = [];
        $secondRowElements = [];
        $sorting = $this->data['config']['sorting'];
        $headers = $this->data['config']['headers'];
        $formats = $this->data['config']['formats'];
        $countRows = 1;

        // define needing in second table header row
        foreach ($sorting as $key => $value) {
            if (is_array($value)) {
                $countRows = 2;
            }
        }

        // define table header rows
        $column = 'A';

        foreach ($sorting as $value) {
            if (is_array($value)) {
                foreach ($value as $key => $values) {
                    $firstRowElements[] = [
                        'name' => $key,
                        'mergeRows' => false,
                        'mergeColumns' => sizeof($values)
                    ];
                    foreach ($values as $subRow) {
                        $secondRowElements[] = [
                            'name' => $subRow,
                            'column' => $column
                        ];
                        $list[] = ['name' => $subRow];
                        $column++;
                    }
                }
            } else {
                $firstRowElements[] = [
                    'name' => $value,
                    'mergeRows' => $countRows,
                    'mergeColumns' => false
                ];
                $list[] = ['name' => $value];
                $column++;
            }
        }

        // set headers mapping
        foreach ($list as &$field) {
            if (isset($headers[$field['name']])) {
                $field['id'] = $headers[$field['name']];
            } else {
                foreach ($headers as $key => $element) {
                    if (isset($element[$field['name']])) {
                        $field['id'] = $element[$field['name']];
                    }
                }
            }
        }

        // set values format
        foreach ($list as &$field) {
            $field['format'] = $formats[$field['name']];
        }

        // set errors column
        if ($this->data['config']['errors']['show']) {
            $firstRowElements[] = [
                'name' => 'errors',
                'mergeRows' => 2,
                'mergeColumns' => false
            ];
            $list[] = ['name' => 'errors', 'id' => 'errors'];
        }

        $this->firstRowTableElements = $firstRowElements;
        $this->secondRowTableElements = $secondRowElements;
        $this->fieldsList = $list;
        $this->needSecondRow = $countRows === 2;
    }

    /**
     * @param $fromPosition
     * @param $addPositions
     *
     * @return mixed
     */
    protected function calculateColumn($fromPosition, $addPositions)
    {
        for ($i = 1; $i < $addPositions; $i++) {
            $fromPosition++;
        }

        return $fromPosition;
    }

    /**
     * @param      $val
     * @param int  $precision
     * @param bool $canBeZero
     * @param bool $isEmpty
     *
     * @return string
     */
    protected function setValue(
        $val,
        int $precision,
        bool $canBeZero = false,
        bool $isEmpty = false
    ) {
        $value = $this->noDataMessage;

        if ($isEmpty) {
            return '';
        }

        if (floatval($val) || ($canBeZero && !is_null($val))) {
            $value = number_format($val, $precision, ',', false);
        }

        return $value;
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function setStyles()
    {
        $this->setAllBorders('A1:' . $this->lastColumn . $this->row);
        $this->setFontNameForAll();
        $this->excelObject->getActiveSheet()->setSelectedCells('A1');
    }
}
