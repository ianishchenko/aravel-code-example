<?php
/**
 * File Report
 *
 * PHP version 7
 *
 * @package    App\Reports
 */

namespace App\Reports\Hourly;

use App\Models\Counter;
use App\Reports\ReportAbstract;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Class Report
 *
 * PHP version 7
 *
 * @package    App\Reports
 */
class Report extends ReportAbstract
{
    /**
     * @var float
     */
    const COEFFICIENT = 1;

    /**
     * @var \DateTime
     */
    protected $date;

    /**
     * @var int
     */
    protected $counter;

    /**
     * @var array
     */
    protected $fieldsConfig;

    /**
     * @var string
     */
    protected $from;

    /**
     * @var string
     */
    protected $to;

    /**
     * @var string
     */
    protected $previousFrom;

    /**
     * @var string
     */
    protected $previousTo;

    /**
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * @var string
     */
    protected $dateFormatWithHours = 'Y-m-d H';

    /**
     * @var array
     */
    protected $countNotNullValues = [];

    /**
     * @var bool
     */
    protected $isHourlyReport = true;

    /**
     * @var bool
     */
    protected $today = false;

    /**
     * @param string $date
     *
     * @return $this
     */
    public function setDate($date): Report
    {
        $this->date = Carbon::createFromFormat($this->dateFormat, $date);

        return $this;
    }

    /**
     * @param App\Models\Counter $counter
     *
     * @return $this
     */
    public function setCounter($counter): Report
    {
        $this->counter = $counter;

        return $this;
    }

    /**
     * @param bool $isListShort
     *
     * @return $this
     */
    public function setFieldsConfig(bool $isListShort): Report
    {
        $field = $isListShort
            ? 'short_reports_list_config'
            : (
                $this->isHourlyReport
                    ? 'reports_list_config_hourly'
                    : 'reports_list_config_daily'
            );

        $config = DB::select("SELECT $field FROM counters WHERE id = ?", [
            $this->counter
        ])[0];

        $this->fieldsConfig = json_decode($config->{$field}, true);

        return $this;
    }

    /**
     * @return Report
     */
    public function build()
    {
        if ($this->isDataExist()) {
            $counter = Counter::find($this->counter);
            $this->setDates();
            $consumedTagId = $this->fieldsConfig['consumed'][
                'consumption_tag_id'
            ];
            $this->setDefaultCountNotNullValues();
            $data = $this->preFillDates();
            $dateTo = Carbon::createFromFormat(
                $this->dateFormatWithHours,
                $this->to
            )
                ->addHour()
                ->format($this->dateFormatWithHours);
            $data = $this->groupData(
                $data,
                $this->getDataForCalculation($this->from, $dateTo),
                true,
                $consumedTagId,
                self::COEFFICIENT
            );

            $needCalculateConsumption = $this->fieldsConfig['consumed'][
                'need_calculation'
            ];
            $needCalculateVolume = $this->fieldsConfig['volume'][
                'need_calculation'
            ];

            if ($needCalculateConsumption || $needCalculateVolume) {
                $this->setPreviousDates();
                $endDate = Carbon::createFromFormat(
                    $this->dateFormatWithHours,
                    $this->previousTo
                )->format('Y-m-d 01');

                if ($this->today) {
                    $endDate = Carbon::createFromFormat(
                        $this->dateFormatWithHours,
                        $this->from
                    )->format($this->dateFormatWithHours);
                }

                $previousDayData = $this->groupData(
                    [],
                    $this->getDataForCalculation($this->previousFrom, $endDate),
                    false,
                    $consumedTagId,
                    self::COEFFICIENT
                );

                if ($needCalculateConsumption) {
                    $minuendConsumption =
                        end($data['rows'])[$consumedTagId] ?? null;

                    if (is_null($minuendConsumption)) {
                        $minuendConsumption = $this->getPreviousRecursive(
                            $data,
                            $consumedTagId
                        );
                    }

                    $subtrahendConsumption = $this->getPreviousValue(
                        $consumedTagId,
                        $this->previousTo
                    );

                    if (
                        !is_null($minuendConsumption) &&
                        !is_null($subtrahendConsumption)
                    ) {
                        $data['sum']['consumed'] =
                            $minuendConsumption * self::COEFFICIENT -
                            $subtrahendConsumption;
                    }

                    reset($data['rows']);

                    for ($i = 0; $i < sizeof($data['rows']); $i++) {
                        $key = key($data['rows']);

                        if (isset($data['rows'][$key][$consumedTagId])) {
                            $minuendConsumption = $data['rows'][$key][
                                $consumedTagId
                            ];

                            if (!isset($data['rows'][$key]['consumed'])) {
                                $data['rows'][$key]['consumed'] = null;
                            }

                            if (!$i) {
                                $subtrahendConsumption = !empty(
                                    $previousDayData['rows']
                                )
                                    ? (
                                        end($previousDayData['rows'])[
                                            $consumedTagId
                                        ] ?? null
                                    )
                                    : null;
                            } else {
                                $subtrahendConsumption =
                                    prev($data['rows'])[$consumedTagId] ?? null;
                                next($data['rows']);
                            }

                            if (
                                !is_null($minuendConsumption) &&
                                !is_null($subtrahendConsumption)
                            ) {
                                $data['rows'][$key]['consumed'] =
                                    (
                                        $minuendConsumption -
                                            $subtrahendConsumption
                                    ) * self::COEFFICIENT;
                            }
                        }

                        next($data['rows']);
                    }
                }

                if ($needCalculateVolume) {
                    $volumeTagId = $this->fieldsConfig['volume'][
                        'volume_tag_id'
                    ];
                    $minuendVolume = end($data['rows'])[$volumeTagId] ?? null;

                    if (is_null($minuendVolume)) {
                        $minuendVolume = $this->getPreviousRecursive(
                            $data,
                            $volumeTagId
                        );
                    }

                    $subtrahendVolume = $this->getPreviousValue(
                        $volumeTagId,
                        $this->previousTo
                    );

                    if (
                        !is_null($minuendVolume) &&
                        !is_null($subtrahendVolume)
                    ) {
                        $data['sum']['volume'] = (
                            $minuendVolume - $subtrahendVolume
                        );
                    }

                    reset($data['rows']);

                    for ($i = 0; $i < sizeof($data['rows']); $i++) {
                        $key = key($data['rows']);
                        if (isset($data['rows'][$key][$volumeTagId])) {
                            $minuendVolume = $data['rows'][$key][$volumeTagId];
                            if (!isset($data['rows'][$key]['volume'])) {
                                $data['rows'][$key]['volume'] = null;
                            }

                            if (!$i) {
                                $subtrahendVolume = !empty(
                                    $previousDayData['rows']
                                )
                                    ? (
                                        end($previousDayData['rows'])[
                                            $volumeTagId
                                        ] ?? null
                                    )
                                    : null;
                            } else {
                                $subtrahendVolume =
                                    prev($data['rows'])[$volumeTagId] ?? null;
                                next($data['rows']);
                            }

                            if (
                                !is_null($minuendVolume) &&
                                !is_null($subtrahendVolume)
                            ) {
                                $data['rows'][$key]['volume'] = (
                                    $minuendVolume - $subtrahendVolume
                                );
                            }
                        }
                        next($data['rows']);
                    }
                }
            }

            if (!empty($this->fieldsConfig['average_value_tags'])) {
                foreach ($data['sum'] as $tag => &$value) {
                    $countHours = $this->countNotNullValues[$tag] ?? 0;

                    if (
                        in_array(
                            $tag,
                            $this->fieldsConfig['average_value_tags']
                        ) &&
                        !in_array($tag, $this->fieldsConfig['no_totals'])
                    ) {
                        $value =
                            $countHours !== 0 ? ($value /= $countHours) : null;
                    }
                }
            }

            if ($this->fieldsConfig['errors']['show']) {
                foreach ($data['rows'] as $date => &$value) {
                    $value['errors'] = $this->getEquipmentErrors()[$date] ?? [];
                }
            }

            $data['date'] = $this->getDates();
            $data['counter'] = $counter->name;
            $data['building'] = $counter->building->name;
            $data['config'] = $this->fieldsConfig;

            $this->data = $data;
        }

        return $this;
    }

    /**
     * @param string $tagId
     * @param string $startDate
     *
     * @return mixed
     */
    protected function getPreviousValue(string $tagId, string $startDate)
    {
        $data = DB::table('values AS v')
            ->join('equipment AS e', 'v.equipment_id', '=', 'e.id')
            ->join('equipment_tags AS et', 'e.id', '=', 'et.equipment_id')
            ->select('v.value', DB::raw('MAX(v.created_at) AS time'), 'et.id')
            ->where('e.counter_id', '=', $this->counter)
            ->where('v.created_at', '<=', $startDate)
            ->where('et.id', '=', $tagId)
            ->whereNotNull('v.value')
            ->groupBy('v.created_at', 'v.value', 'et.id')
            ->orderBy('v.created_at', 'DESC')
            ->limit(1)
            ->first();

        return $data->value;
    }

    /**
     * @param array $data
     * @param string $tag
     *
     * @return null|float
     */
    protected function getPreviousRecursive($data, $tag)
    {
        $value = prev($data['rows'])[$tag] ?? null;

        if (!is_null($value)) {
            return $value;
        } else {
            return $this->getPreviousRecursive($data, $tag);
        }
    }

    /**
     * @param array $result
     * @param array $data
     * @param bool  $withCountNotNull
     * @param int   $consumptionTagId
     * @param float $coefficient
     *
     * @return array
     */
    protected function groupData(
        array $result,
        array $data,
        bool $withCountNotNull = true,
        int $consumptionTagId,
        float $coefficient = 0
    ): array {
        foreach ($data as $value) {
            $tagId = $value->equipment_tag;
            $time = $value->time;

            if (!isset($result['rows'][$time])) {
                $result['rows'][$time] = [];
            }
            if (!isset($result['rows'][$time][$tagId])) {
                $result['rows'][$time][$tagId] = null;
            }
            if (!isset($result['sum'][$tagId])) {
                $result['sum'][$tagId] = null;
            }

            $result['rows'][$time][$tagId] = $value->value;

            if ($tagId === $consumptionTagId) {
                $result['rows'][$time][$tagId] /= $coefficient;
            }

            if (!in_array($tagId, $this->fieldsConfig['no_totals'])) {
                $result['sum'][$tagId] += $result['rows'][$time][$tagId];
            } else {
                $result['sum'][$tagId] = '';
            }

            if ($withCountNotNull && !is_null($value->value)) {
                $this->countNotNullValues[$tagId]++;
            }
        }

        return $result;
    }

    /**
     * @param string $startDate
     * @param string $endDate
     *
     * @return array|object
     */
    protected function getDataForCalculation(
        string $startDate,
        string $endDate
    ): array {
        $data = DB::table('values AS v')
            ->join('equipment AS e', 'v.equipment_id', '=', 'e.id')
            ->join('equipment_tags AS et', 'e.id', '=', 'et.equipment_id')
            ->select(
                'v.value',
                DB::raw('DATE_FORMAT(v.created_at, "%Y-%m-%d %H") AS time'),
                'et.id AS equipment_tag'
            )
            ->where('e.counter_id', '=', $this->counter)
            ->where('v.created_at', '>=', $startDate)
            ->where('v.created_at', '<', $endDate)
            ->whereIn('et.id', $this->getFields())
            ->groupBy('v.created_at', 'et.id', 'v.value')
            ->get()
            ->toArray();

        return $data;
    }

    /**
     * @return array
     */
    protected function getFields(): array
    {
        $fields = $this->fieldsConfig['equipment_tags'];
        !$this->fieldsConfig['consumed']['need_calculation']
            ?: array_push(
                $fields,
                $this->fieldsConfig['consumed']['consumption_tag_id']
            );

        return $fields;
    }

    /**
     * @return array
     */
    protected function getErrorFields(): array
    {
        return $this->fieldsConfig['errors']['equipment_tags'];
    }

    /**
     * @return bool
     */
    public function isDataExist(): bool
    {
        $this->setDates();

        $data = DB::table('values AS v')
            ->join('equipment AS e', 'v.equipment_id', '=', 'e.id')
            ->join('equipment_tags AS et', 'e.id', '=', 'et.equipment_id')
            ->select('v.id')
            ->where('e.counter_id', '=', $this->counter)
            ->where('v.created_at', '>=', $this->from)
            ->where('v.created_at', '<=', $this->to)
            ->whereIn('et.id', $this->getFields())
            ->count();

        return (bool) $data;
    }

    /**
     * Set start and end dates
     */
    protected function setDates()
    {
        $now = Carbon::now();
        $date = clone $this->date;
        $startDate = $date->format('Y-m-d 01');
        $endDate = $date->addDay()->format('Y-m-d 00');

        if ($now->format('Y-m-d') === $this->date->format('Y-m-d')) {
            $date = clone $this->date;
            $endHour = $now->format('H');
            $startHour = $now->addHour()->format('H');
            $endDate = $date->format('Y-m-d ') . $endHour;
            $startDate = $date->subDay()->format('Y-m-d ') . $startHour;

            $this->today = true;
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
            $this->dateFormatWithHours,
            $this->from
        )
            ->subDay()
            ->format($this->dateFormatWithHours);
        $this->previousTo = Carbon::createFromFormat(
            $this->dateFormatWithHours,
            $this->to
        )
            ->subDay()
            ->format($this->dateFormatWithHours);
    }

    /**
     * @return \DateTime
     */
    protected function getDates()
    {
        return $this->date;
    }

    /**
     * Set defaults to CountNotNullValues
     */
    protected function setDefaultCountNotNullValues()
    {
        foreach ($this->fieldsConfig['equipment_tags'] as $field) {
            $this->countNotNullValues[$field] = 0;
        }
    }

    /**
     * @return array
     */
    protected function preFillDates(): array
    {
        $result = ['rows' => []];
        $from = Carbon::createFromFormat(
            $this->dateFormatWithHours,
            $this->from
        );
        $to = Carbon::createFromFormat($this->dateFormatWithHours, $this->to);

        for ($date = $from; $date <= $to; $date->addHour()) {
            $result['rows'][$date->format($this->dateFormatWithHours)] = [];
        }

        return $result;
    }

    /**
     * @param bool $withHours
     *
     * @return array
     */
    protected function getEquipmentErrors(bool $withHours = true): array
    {
        $from = $this->from;

        if ($withHours) {
            $dateFormat = "%Y-%m-%d %H";
            $to = $this->to;
        } else {
            $dateFormat = "%Y-%m-%d";
            $to = $this->to . ' 23:59:59';
        }

        $errors = DB::table('equipment_logs AS el')
            ->join('equipment AS e', 'el.equipment_id', '=', 'e.id')
            ->select(
                'el.message',
                'e.name',
                DB::raw("DATE_FORMAT(el.date, '$dateFormat') as time"),
                DB::raw('count(el.message) count_messages')
            )
            ->where('el.date', '>=', $from)
            ->where('el.date', '<=', $to)
            ->whereIn('e.id', $this->getErrorFields())
            ->groupBy(
                'el.message',
                'e.name',
                DB::raw("DATE_FORMAT(el.date, '$dateFormat')")
            )
            ->get()
            ->toArray();

        return $this->formErrors($errors);
    }

    /**
     * @param array $errors
     *
     * @return array
     */
    protected function formErrors(array $errors): array
    {
        $result = [];

        foreach ($errors as $error) {
            if (!isset($result[$error->time])) {
                $result[$error->time] = [];
            }

            $result[
                $error->time
            ][] = "{$error->message} ({$error->name})[{$error->count_messages}]";
        }

        return $result;
    }
}
