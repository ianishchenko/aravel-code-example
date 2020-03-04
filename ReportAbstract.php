<?php
/**
 * File AbstractReport
 *
 * PHP version 7
 *
 * @package    App\Reports
 */

namespace App\Reports;

/**
 * Class AbstractReport
 *
 * PHP version 7
 *
 * @package    App\Reports
 */
abstract class ReportAbstract implements ReportInterface
{
    /**
     * @var array
     */
    protected $data = [];

    /**
     * Get report data
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
}