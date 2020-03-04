<?php
/**
 * File Report
 *
 * PHP version 7
 *
 * @package    App\Reports
 */

namespace App\Reports\Daily;

use App\Models\Counter;
use App\Models\ForecastModel;
use App\Models\InternalTemperatureFromICE;
use App\Models\NormalHeating;
use App\Reports\Hourly\Report as HourlyReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Class Report
 *
 * PHP version 7
 *
 * @package    App\Reports
 */
class Report extends HourlyReport
{
    /**
     * @var string
     */
    protected $dateFormat = 'Y-m-d';

    /**
     * @var string
     */
    protected $previousDateFormat = 'Y-m-d';

    /**
     * @var array
     */
    protected $consumptionDates = [];

    /**
     * @var array
     */
    protected $volumeDates = [];

    /**
     * @var bool
     */
    protected $isHourlyReport = false;

    /**
     * @var
     */
    protected $dateForCalc = null;

    /**
     * @return Report
     */
    public function build()
    {
        $counter = Counter::find($this->counter);
        $this->setDates();
        $this->setDefaultCountNotNullValues();
        $data = $this->getCalculatedData();

        $needCalculateConsumption = $this->fieldsConfig['consumed'][
            'need_calculation'
        ];
        $needCalculateVolume = $this->fieldsConfig['volume'][
            'need_calculation'
        ];

        if ($needCalculateConsumption) {
            $consumedTagId = $this->fieldsConfig['consumed'][
                'consumption_tag_id'
            ];
            $subtrahend = !is_null(current($this->consumptionDates))
                ? current($this->consumptionDates)
                : null;
            $minuend = end($this->consumptionDates);

            if (!is_null($minuend) && !is_null($subtrahend)) {
                $data['sum']['consumed'] =
                    ($minuend - $subtrahend) * self::COEFFICIENT;
            }

            reset($this->consumptionDates);
            next($data['rows']);

            for ($i = 0; $i < sizeof($this->consumptionDates); $i++) {
                $valueKey = key($data['rows']);
                $minuend = current($data['rows'])[$consumedTagId] ?? null;
                $subtrahend = !is_null(current($this->consumptionDates))
                    ? current($this->consumptionDates)
                    : null;

                if (!is_null($minuend) && !is_null($subtrahend)) {
                    $data['rows'][$valueKey]['consumed'] =
                        ($minuend - $subtrahend) * self::COEFFICIENT;
                }

                next($this->consumptionDates);
                next($data['rows']);
            }
        }
        if ($needCalculateVolume) {
            $volumeTagId = $this->fieldsConfig['volume']['volume_tag_id'];
            $subtrahend = !is_null(current($this->volumeDates))
                ? current($this->volumeDates)
                : null;
            $minuend = end($this->volumeDates);

            if (!is_null($minuend) && !is_null($subtrahend)) {
                $data['sum']['volume'] = $minuend - $subtrahend;
            }

            reset($this->volumeDates);
            reset($data['rows']);
            next($data['rows']);

            for ($i = 0; $i < sizeof($this->volumeDates); $i++) {
                $valueKey = key($data['rows']);
                $minuend = current($data['rows'])[$volumeTagId] ?? null;
                $subtrahend = !is_null(current($this->volumeDates))
                    ? current($this->volumeDates)
                    : null;

                if (!is_null($minuend) && !is_null($subtrahend)) {
                    $data['rows'][$valueKey]['volume'] = $minuend - $subtrahend;
                }

                next($this->volumeDates);
                next($data['rows']);
            }
        }

        if ($this->fieldsConfig['avg_outer_temperature']['need_calculation']) {
            $averageOuterTemperatures = $this->getAverageOuterTemperature();
            $countDays = 0;
            $averageOuterTemperatureSum = 0;

            foreach ($averageOuterTemperatures as $temp) {
                $key = $temp['date'];
                $data['rows'][$key]['avg_outer_temperature'] = $temp['temp'];
                $countDays++;
                $averageOuterTemperatureSum += $temp['temp'];
            }

            $data['sum']['avg_outer_temperature'] =
                $averageOuterTemperatureSum / $countDays;
        }

        if ($this->fieldsConfig['avg_inner_temperature']['need_calculation']) {
            $averageInnerTemperature = $this->getAverageInnerTemperature(
                $counter->building_id
            );

            $countDays = 0;
            $averageInnerTemperatureSum = 0;

            foreach ($averageInnerTemperature as $date => $temp) {
                $data['rows'][$date]['avg_inner_temperature'] = $temp;
                if (!is_null($temp)) {
                    $countDays++;
                }
                $averageInnerTemperatureSum += $temp;
            }

            $data['sum']['avg_inner_temperature'] = $countDays
                ? ($averageInnerTemperatureSum / $countDays)
                : null;
        }

        if ($this->fieldsConfig['normal_heating']['need_calculation']) {
            $normalHeating = NormalHeating::query()
                ->select('date', 'value')
                ->where('building_id', $counter->building_id)
                ->where('date', '>=', $this->from)
                ->where('date', '<=', $this->to)
                ->get()
                ->toArray();
            $totalNormalHeating = 0;

            foreach ($normalHeating as $value) {
                $data['rows'][$value['date']]['normal_heating'] = $value[
                    'value'
                ];
                $totalNormalHeating += $value['value'];
            }

            $data['sum']['normal_heating'] = $totalNormalHeating;
        }

        if ($this->fieldsConfig['errors']['show']) {
            foreach ($data['rows'] as $date => &$value) {
                $value['errors'] =
                    $this->getEquipmentErrors(false)[$date] ?? [];
            }
        }

        $data['date'] = $this->getDates();
        $data['counter'] = $counter->name;
        $data['building'] = $counter->building->name;
        $data['config'] = $this->fieldsConfig;

        $this->data = $data;

        return $this;
    }

    /**
     * @param array $result
     * @param array $data
     * @param bool  $with
     * @param string $consumptionTag
     * @param bool  $volumeTag
     * @param float $coefficient
     *
     * @return array
     */
    protected function calculate(
        array $result,
        array $data,
        bool $with = true,
        $consumptionTag,
        $volumeTag = false,
        $coefficient
    ): array {
        $countNotNullValues = $this->countNotNullValues;
        foreach ($data as $value) {
            $tagId = $value->equipment_tag;
            $time = $value->time;
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $time);

            if (!isset($result[$tagId])) {
                $result[$tagId] = null;
            }

            if (!in_array($tagId, $this->fieldsConfig['no_totals'])) {
                $result[$tagId] += $value->value;
            } else {
                $result[$tagId] = null;
            }

            if ($with && !is_null($value->value)) {
                $countNotNullValues[$tagId]++;
            }

            if (
                !is_null($value->value) &&
                ($tagId === $consumptionTag || $tagId === $volumeTag)
            ) {
                $result[$tagId] = $value->value;

                if ($tagId === $consumptionTag) {
                    $result[$tagId] /= $coefficient;
                }
            }
            if (is_null($this->dateForCalc)) {
                $this->dateForCalc = Carbon::createFromFormat('Y-m-d H:i:s', $date);
            }
        }

        if (!empty($result)) {
            $this->consumptionDates[
                $this->dateForCalc->format($this->dateFormat)
            ] = $result[$consumptionTag];
            $this->volumeDates[
                $this->dateForCalc->format($this->dateFormat)
            ] = $result[$volumeTag];
            $this->dateForCalc->addDay();
        }

        return ['data' => $result, 'countNotNullValues' => $countNotNullValues];
    }

    /**
     * Set start and end dates
     */
    protected function setDates()
    {
        $now = Carbon::now();
        $date = clone $this->date;
        $startDate = $date->startOfMonth()->format($this->dateFormat);
        $endDate = $date->endOfMonth()->format($this->dateFormat);

        if ($now->format('Y-m') === $this->date->format('Y-m')) {
            $endDate = $now->subDay()->format($this->dateFormat);
        }

        $this->from = $startDate;
        $this->to = $endDate;
    }

    /**
     * Set previous dates
     */
    protected function setPreviousDates()
    {
        $this->previousFrom = Carbon::createFromFormat(
            $this->previousDateFormat,
            $this->from
        )
            ->subMonth()
            ->format($this->previousDateFormat);
        $this->previousTo = Carbon::createFromFormat(
            $this->previousDateFormat,
            $this->to
        )
            ->subMonth()
            ->format($this->previousDateFormat);
    }

    /**
     * @return array
     */
    protected function getDates()
    {
        return [
            'from' => Carbon::createFromFormat($this->dateFormat, $this->from),
            'to' => Carbon::createFromFormat($this->dateFormat, $this->to)
        ];
    }

    /**
     * @param string $startDate
     * @param string $endDate
     * @param bool|int   $tagId
     *
     * @return array|object
     */
    protected function getDataForCalculation(
        string $startDate,
        string $endDate,
        $tagId = false
    ) {
        $data = DB::table('values AS v')
            ->join('equipment AS e', 'v.equipment_id', '=', 'e.id')
            ->join('equipment_tags AS et', 'e.id', '=', 'et.equipment_id')
            ->select(
                'v.value',
                'v.created_at AS time',
                'et.id AS equipment_tag'
            )
            ->where('e.counter_id', '=', $this->counter)
            ->whereIn('et.id', $this->getFields())
            ->groupBy('v.created_at', 'et.id', 'v.value');

        if ($tagId) {
            $data = $data
                ->where('et.id', '=', $tagId)
                ->where('v.created_at', '<=', $endDate)
                ->orderBy('v.created_at', 'desc')
                ->first();
        } else {
            $data = $data
                ->where('v.created_at', '>=', $startDate)
                ->where('v.created_at', '<', $endDate)
                ->get()
                ->toArray();
        }

        return $data;
    }

    /**
     * @return array
     */
    protected function getAverageOuterTemperature(): array
    {
        $data = ForecastModel::query()
            ->select('date', 'average_temperature as temp')
            ->where('date', '>=', $this->from)
            ->where('date', '<=', $this->to)
            ->get()
            ->toArray();

        return $data;
    }

    /**
     * @param int $buildingId
     *
     * @return array
     */
    protected function getAverageInnerTemperature(int $buildingId): array
    {
        $startDate = Carbon::createFromFormat($this->dateFormat, $this->from);
        $endDate = Carbon::createFromFormat($this->dateFormat, $this->to);

        $internalTemperatures = InternalTemperatureFromICE::select()
            ->where('date', '>=', $this->from)
            ->where('date', '<=', $this->to)
            ->where('building_id', $buildingId)
            ->get();

        $result = [];

        for ($i = $startDate; $i <= $endDate; $i->addDay()) {
            $stringDate = $i->toDateString();
            $result[$stringDate] = $internalTemperatures[$stringDate] ?? null;
        }

        foreach ($internalTemperatures as $internalTemperature) {
            $result[$internalTemperature->date] = $internalTemperature->value;
        }

        return $result;
    }

    /**
     * @return array
     */
    protected function getCalculatedData(): array
    {
        $from = Carbon::createFromFormat(
            $this->previousDateFormat,
            $this->from
        );
        $to = Carbon::createFromFormat($this->previousDateFormat, $this->to);
        $consumedTagId = $this->fieldsConfig['consumed']['consumption_tag_id'];
        $volumeTagId = $this->fieldsConfig['volume']['volume_tag_id'];
        $result = ['rows' => [], 'sum' => []];
        $start = $from->format('Y-m-d');
        $consumptionData = $this->getDataForCalculation(
            $start . ' 00',
            $start . ' 00',
            $consumedTagId
        );
        $volumeData =  $this->getDataForCalculation(
            $start . ' 00',
            $start . ' 00',
            $volumeTagId
        );
        $this->consumptionDates[$start] =
            $consumptionData ? $consumptionData->value / self::COEFFICIENT : null;
        $this->volumeDates[$start] = $volumeData ? $volumeData->value : null;
        $coefficient = self::COEFFICIENT;

        for ($date = $from; $date <= $to; $date->addDay()) {
            $fromDate = clone $date;
            $fromDate = $fromDate->format('Y-m-d 01');
            $toDate = clone $date;
            $toDate->addDay();
            $toDate = $toDate->format('Y-m-d 01');

            $data = $this->calculate(
                [],
                $this->getDataForCalculation($fromDate, $toDate),
                true,
                $consumedTagId,
                $volumeTagId,
                $coefficient
            );
            foreach ($data['data'] as $tag => &$value) {
                $countHours = $data['countNotNullValues'][$tag] ?? 0;
                if (
                    in_array($tag, $this->fieldsConfig['average_value_tags']) &&
                    !in_array($tag, $this->fieldsConfig['no_totals'])
                ) {
                    $value = $countHours !== 0 ? ($value /= $countHours) : null;
                }
            }

            $result['rows'][$date->format($this->dateFormat)] = $data['data'];
        }

        $result['sum'] = $this->getTotals($result['rows']);

        return $result;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function getTotals($data): array
    {
        $result = [];
        $countNotNullValues = $this->countNotNullValues;

        foreach ($data as $values) {
            foreach ($values as $tag => $value) {
                if (!isset($result[$tag])) {
                    $result[$tag] = null;
                }
                if (!in_array($tag, $this->fieldsConfig['no_totals'])) {
                    $result[$tag] += $value;
                } else {
                    $result[$tag] = '';
                }

                if (!is_null($value)) {
                    $countNotNullValues[$tag]++;
                }
            }
        }

        foreach ($result as $tag => &$value) {
            $countHours = $countNotNullValues[$tag] ?? 0;
            if (
                in_array($tag, $this->fieldsConfig['average_value_tags']) &&
                !in_array($tag, $this->fieldsConfig['no_totals'])
            ) {
                $value = $countHours !== 0 ? ($value /= $countHours) : null;
            }
        }

        return $result;
    }
}
