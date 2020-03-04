<?php
/**
 * File ReportInterface
 *
 * PHP version 7
 *
 * @package    App\Reports
 */

namespace App\Reports;

/**
 * Interface ReportInterface
 *
 * PHP version 7
 *
 * @package    App\Reports
 */
interface ReportInterface
{
    /**
     * Get report data
     *
     * @return array
     */
    public function getData(): array;

    /**
     * Get information about data existing
     *
     * @return bool
     */
    public function isDataExist(): bool;
}