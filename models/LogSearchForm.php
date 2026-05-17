<?php

declare(strict_types=1);

namespace app\models;

use DateTimeImmutable;
use Yii;
use yii\base\Model;
use yii\data\ArrayDataProvider;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\Query;

class LogSearchForm extends Model
{
    public ?string $dateFrom = null;
    public ?string $dateTo = null;
    public mixed $operatingSystemId = null;
    public mixed $architectureId = null;

    public function rules(): array
    {
        return [
            [['dateFrom', 'dateTo'], 'date', 'format' => 'php:Y-m-d'],
            [['operatingSystemId', 'architectureId'], 'integer'],
            ['dateTo', 'validateDateRange'],
        ];
    }

    public function formName(): string
    {
        return 'LogReportSearch';
    }

    /**
     * @throws \Exception
     */
    public function validateDateRange(): void
    {
        if ($this->hasErrors('dateFrom') || $this->hasErrors('dateTo')) {
            return;
        }

        if ($this->dateFrom === null || $this->dateTo === null) {
            return;
        }

        $from = new DateTimeImmutable($this->dateFrom);
        $to = new DateTimeImmutable($this->dateTo);

        if ($from > $to) {
            $this->addError('dateTo', 'Дата окончания должна быть больше или равна дате начала.');
            return;
        }

        if ($from->modify('+1 year') < $to) {
            $this->addError('dateTo', 'Диапазон дат не должен превышать 1 год.');
        }
    }

    public function load($data, $formName = null): bool
    {
        $loaded = parent::load($data, $formName);

        $this->operatingSystemId = $this->operatingSystemId ? (int)$this->operatingSystemId : null;
        $this->architectureId = $this->architectureId ? (int)$this->architectureId : null;

        return $loaded;
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function applyDefaultDates(): void
    {
        if ($this->dateFrom !== null || $this->dateTo !== null) {
            return;
        }

        $maxDate = Yii::$app->db->createCommand(
            'SELECT DATE(MAX(requested_at)) FROM logs',
        )->queryScalar();

        if ($maxDate === false || $maxDate === null) {
            return;
        }

        $to = new DateTimeImmutable((string)$maxDate);
        $this->dateTo = $to->format('Y-m-d');
        $this->dateFrom = $to->modify('-1 year')->modify('+1 day')->format('Y-m-d');
    }

    public function search(): array
    {
        if (!$this->validate()) {
            return [
                'dataProvider' => $this->createDataProvider([]),
                'requestChart' => ['labels' => [], 'values' => []],
                'browserChart' => ['labels' => [], 'datasets' => []],
            ];
        }

        $totals = $this->getDailyTotals();
        $popularUrls = $this->getDailyPopularValues('url');
        $popularBrowsers = $this->getDailyPopularValues('browser');
        $rows = [];

        foreach ($totals as $date => $count) {
            $rows[] = [
                'date' => $date,
                'requestCount' => $count,
                'popularUrl' => $popularUrls[$date] ?? '',
                'popularBrowser' => $popularBrowsers[$date] ?? '',
            ];
        }

        return [
            'dataProvider' => $this->createDataProvider($rows),
            'requestChart' => [
                'labels' => array_keys($totals),
                'values' => array_values($totals),
            ],
            'browserChart' => $this->getBrowserShareChart($totals),
        ];
    }

    public function getOperatingSystemItems(): array
    {
        return OperatingSystem::find()
            ->select(['name', 'id'])
            ->orderBy(['name' => SORT_ASC])
            ->indexBy('id')
            ->column();
    }

    public function getArchitectureItems(): array
    {
        return Architecture::find()
            ->select(['name', 'id'])
            ->orderBy(['name' => SORT_ASC])
            ->indexBy('id')
            ->column();
    }

    private function createDataProvider(array $rows): ArrayDataProvider
    {
        return new ArrayDataProvider([
            'allModels' => $rows,
            'pagination' => [
                'pageSize' => 31,
            ],
            'sort' => [
                'attributes' => [
                    'date',
                    'requestCount',
                    'popularUrl',
                    'popularBrowser',
                ],
                'defaultOrder' => [
                    'date' => SORT_ASC,
                ],
            ],
        ]);
    }

    private function getDailyTotals(): array
    {
        $rows = $this->createBaseQuery()
            ->select([
                'date' => new Expression('DATE(l.requested_at)'),
                'requestCount' => new Expression('COUNT(*)'),
            ])
            ->groupBy(new Expression('DATE(l.requested_at)'))
            ->orderBy(['date' => SORT_ASC])
            ->all();

        $totals = [];

        foreach ($rows as $row) {
            $totals[$row['date']] = (int)$row['requestCount'];
        }

        return $totals;
    }

    private function getDailyPopularValues(string $type): array
    {
        $selectExpression = $type === 'browser' ? 'b.name' : 'l.url';

        $rows = $this->createBaseQuery()
            ->select([
                'date' => new Expression('DATE(l.requested_at)'),
                'value' => new Expression($selectExpression),
                'requestCount' => new Expression('COUNT(*)'),
            ])
            ->groupBy([
                new Expression('DATE(l.requested_at)'),
                new Expression($selectExpression),
            ])
            ->orderBy([
                'date' => SORT_ASC,
                'requestCount' => SORT_DESC,
                'value' => SORT_ASC,
            ])
            ->all();

        $popular = [];

        foreach ($rows as $row) {
            $popular[$row['date']] ??= (string)$row['value'];
        }

        return $popular;
    }

    private function getBrowserShareChart(array $totals): array
    {
        if ($totals === []) {
            return ['labels' => [], 'datasets' => []];
        }

        $topBrowsers = $this->createBaseQuery()
            ->select([
                'id' => 'b.id',
                'name' => 'b.name',
                'requestCount' => new Expression('COUNT(*)'),
            ])
            ->groupBy(['b.id', 'b.name'])
            ->orderBy(['requestCount' => SORT_DESC])
            ->limit(3)
            ->all();

        if ($topBrowsers === []) {
            return ['labels' => array_keys($totals), 'datasets' => []];
        }

        $browserIds = array_column($topBrowsers, 'id');
        $dailyRows = $this->createBaseQuery()
            ->select([
                'date' => new Expression('DATE(l.requested_at)'),
                'browserId' => 'b.id',
                'requestCount' => new Expression('COUNT(*)'),
            ])
            ->andWhere(['b.id' => $browserIds])
            ->groupBy([
                new Expression('DATE(l.requested_at)'),
                'b.id',
            ])
            ->all();

        $counts = [];

        foreach ($dailyRows as $row) {
            $counts[$row['browserId']][$row['date']] = (int)$row['requestCount'];
        }

        $datasets = [];

        foreach ($topBrowsers as $browser) {
            $values = [];

            foreach ($totals as $date => $total) {
                $count = $counts[$browser['id']][$date] ?? 0;
                $values[] = $total > 0 ? round($count / $total * 100, 2) : 0;
            }

            $datasets[] = [
                'label' => $browser['name'],
                'data' => $values,
            ];
        }

        return [
            'labels' => array_keys($totals),
            'datasets' => $datasets,
        ];
    }

    private function createBaseQuery(): Query
    {
        $query = (new \yii\db\Query())
            ->from(['l' => 'logs'])
            ->innerJoin(['b' => 'browsers'], 'b.id = l.browser_id')
            ->innerJoin(['os' => 'operating_systems'], 'os.id = l.operating_system_id')
            ->innerJoin(['a' => 'architectures'], 'a.id = l.architecture_id')
            ->andWhere(['not', ['l.requested_at' => null]]);

        if ($this->dateFrom !== null) {
            $query->andWhere(['>=', 'l.requested_at', $this->dateFrom . ' 00:00:00']);
        }

        if ($this->dateTo !== null) {
            $query->andWhere(['<=', 'l.requested_at', $this->dateTo . ' 23:59:59']);
        }

        if ($this->operatingSystemId !== null) {
            $query->andWhere(['l.operating_system_id' => $this->operatingSystemId]);
        }

        if ($this->architectureId !== null) {
            $query->andWhere(['l.architecture_id' => $this->architectureId]);
        }

        return $query;
    }
}
