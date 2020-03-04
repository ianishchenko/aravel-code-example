<?php
/**
 * File ReportXlsAbstract
 *
 * PHP version 7
 *
 * @package    App\Reports
 */

namespace App\Reports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use \PhpOffice\PhpSpreadsheet\Style\Border as Border;
use \PhpOffice\PhpSpreadsheet\Style\Alignment as Alignment;
use Illuminate\Support\Facades\Lang;

/**
 * Class ReportXlsAbstract
 *
 * PHP version 7
 *
 * @package    App\Reports
 */
abstract class ReportXlsAbstract implements ReportXlsInterface
{
    /**
     * @var array
     */
    protected $data;

    /**
     * @var string
     */
    protected $filename = 'filename';

    /**
     * @var \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
     */
    protected $activeSheet;

    /**
     * @var Spreadsheet
     */
    protected $excelObject;

    /**
     * @var int
     */
    protected $titleRowHeight = 70;

    /**
     * @var int
     */
    protected $titleRowFontSize = 14;

    /**
     * @var int
     */
    protected $fontSize = 10;

    /**
     * @var int
     */
    protected $totalRowFontSize = 12;

    /**
     * ReportXlsAbstract constructor.
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function __construct()
    {
        $this->excelObject = new Spreadsheet();
        $this->activeSheet = $this->excelObject->getActiveSheet();
        $this->setDefaultParams();
    }

    /**
    * @param string $message
    *
    * return $string
    */
    protected function translate($message): string {
        return Lang::get($message)
    }

    /**
     * @param array $data
     *
     * @return ReportXlsAbstract
     */
    public function setData(array $data): ReportXlsAbstract
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function download()
    {
        $writer = new Xls($this->excelObject);
        header('Content-Type: application/vnd.ms-excel');
        header("Content-Disposition: attachment; filename='$this->filename.xls'");
        header('Cache-Control: max-age=0');
        $writer->save("php://output");
    }

    /**
     * Set all borders
     *
     * @param string $pCellCoordinate
     *
     * @return $this
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function setAllBorders(string $pCellCoordinate)
    {
        $this->activeSheet->getStyle($pCellCoordinate)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ]);

        return $this;
    }

    /**
     * Set font for all document
     *
     * @param string $name
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function setFontNameForAll($name = 'Times New Roman')
    {
        $this->activeSheet->getStyle($this->activeSheet->calculateWorksheetDimension())->getFont()->setName($name);
    }

    /**
     * Set font name
     *
     * @param        $cell
     * @param string $name
     *
     * @return $this
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function setFontName($cell, $name = 'Times New Roman')
    {
        $this->activeSheet->getCell($cell)->getStyle()->getFont()->setName($name);

        return $this;
    }

    /**
     * Set size of font
     *
     * @param $cell
     * @param $size
     *
     * @return $this
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function setFontSize($cell, $size)
    {
        $this->activeSheet->getCell($cell)->getStyle()->getFont()->setSize($size);

        return $this;
    }

    /**
     * Set alignment to center of cell
     *
     * @param $cell
     *
     * @return $this
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function setAlignmentCenter($cell)
    {
        $this->activeSheet->getCell($cell)->getStyle()->applyFromArray(
            [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER
                ]
            ]
        );

        return $this;
    }

    /**
     * Set alignment to left of cell
     *
     * @param $cell
     *
     * @return $this
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function setAlignmentLeft($cell)
    {
        $this->activeSheet->getCell($cell)->getStyle()->applyFromArray(
            [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical'   => Alignment::VERTICAL_CENTER
                ]
            ]
        );

        return $this;
    }

    /**
     * Set alignment to center and up
     *
     * @param string $cell
     *
     * @return $this
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function setAlignmentCenterAndUp($cell)
    {
        $this->activeSheet->getCell($cell)->getStyle()->applyFromArray(
            [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_TOP
                ]
            ]
        );

        return $this;
    }

    /**
     * Set bold font style
     *
     * @param string $cell
     *
     * @return $this
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function setBold($cell)
    {
        $this->activeSheet->getCell($cell)->getStyle()->getFont()->setBold(true);

        return $this;
    }

    /**
     * Set italic font style
     *
     * @param string $cell
     *
     * @return $this
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function setItalic($cell)
    {
        $this->activeSheet->getCell($cell)->getStyle()->getFont()->setItalic(true);

        return $this;
    }

    /**
     * Set height of row
     *
     * @param string $row
     * @param string $size
     *
     * @return $this
     */
    protected function setRowHeight($row, $size)
    {
        $this->activeSheet->getRowDimension($row)->setRowHeight($size);

        return $this;
    }

    /**
     * Set alignment to left
     *
     * @param $cell
     *
     * @return $this
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function setAlignLeft($cell)
    {
        $this->activeSheet->getCell($cell)->getStyle()->applyFromArray(
            [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT
                ]
            ]
        );

        return $this;
    }

    /**
     * Set alignment to right
     *
     * @param $cell
     *
     * @return $this
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function setAlignRight($cell)
    {
        $this->activeSheet->getCell($cell)->getStyle()->applyFromArray(
            [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_RIGHT
                ]
            ]
        );

        return $this;
    }

    /**
     * Transform first char of string to lower case
     *
     * @param string $value
     *
     * @return string
     */
    protected function lcFirst(String $value): String
    {
        return mb_convert_case($value, MB_CASE_LOWER, 'UTF-8');
    }

    /**
     * Set default params for PHP Excel
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function setDefaultParams()
    {
        $this->excelObject->getDefaultStyle()->getAlignment()->setWrapText(true);
        $this->activeSheet->getDefaultRowDimension()->setRowHeight(-1);
    }
}