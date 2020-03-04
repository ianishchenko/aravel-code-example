<?php
/**
 * FIle ReportXlsInterface
 *
 * PHP version 7
 *
 * @package    App\Reports
 */

namespace App\Reports;

/**
 * Interface ReportXlsInterface
 *
 * PHP version 7
 *
 * @package    App\Reports
 */
interface ReportXlsInterface
{
    /**
     * Generating report
     */
    public function build();

    /**
     * @param array $data
     *
     * @return mixed
     */
    public function setData(array $data);

    /**
     * @return mixed
     */
    public function download();
}