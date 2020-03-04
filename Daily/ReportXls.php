<?php
/**
 * Class ReportXls
 *
 * PHP version 7
 *
 * @package    App\Reports
 */

namespace App\Reports\Daily;

use App\Reports\Hourly\ReportXls as HourlyReportXls;

/**
 * Class ReportXls
 *
 * PHP version 7
 *
 * @package    App\Reports
 */
class ReportXls extends HourlyReportXls
{
    /**
     * @var string
     */
    protected $fromDateFormat = 'Y-m-d';

    /**
     * @var string
     */
    protected $toDateFormat = 'd.m.y';

    /**
     * @var string report name
     */
    public $filename = 'ReportDaily';

    /**
     * @var bool
     */
    protected $isHourly = false;

    /**
     * @return string
     */
    protected function getTitle(): string
    {
        $from = $this->data['date']['from']->format('d.m.Y');
        $to = $this->data['date']['to']->format('d.m.Y');
        $building = $this->data['building'];
        $counter = $this->data['counter'];

        return $this->translate('reports.hourly.title', ['from' => $from, 'to' => $to, 'building' => $building, 'counter' => $counter]);
    }

    /**
    * @return string
    */
    protected function setTableHeaders()
    {
        parent::setTableHeaders();
        $this->tableHeadersMapping['date'] = $this->translate('reports.daily.date';
        $this->tableHeadersMapping['volume'] = $this->translate('reports.daily.volume';
        $this->tableHeadersMapping['T1'] = $this->translate('reports.daily.t1';
        $this->tableHeadersMapping['T2'] = $this->translate('reports.daily.t2';
        $this->tableHeadersMapping['T11'] = $this->translate('reports.daily.t11';
        $this->tableHeadersMapping['T22'] = $this->translate('reports.daily.t22';
    }



    /**
     * @return HourlyReportXls
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function build()
    {
        $this->setTableHeaders();

        return parent::build();
    }
}
