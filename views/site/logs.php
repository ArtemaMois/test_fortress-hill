<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\LogSearchForm $searchModel */
/** @var yii\data\ArrayDataProvider $dataProvider */
/** @var array $requestChart */
/** @var array $browserChart */

use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\widgets\ActiveForm;

$this->title = 'Logs';
$this->params['breadcrumbs'][] = $this->title;

$this->registerJsFile('https://cdn.jsdelivr.net/npm/chart.js@4.4.9/dist/chart.umd.min.js', [
    'position' => yii\web\View::POS_END,
]);

$requestChartJson = Json::htmlEncode($requestChart);
$browserChartJson = Json::htmlEncode($browserChart);

$this->registerJs(<<<JS
const requestChart = {$requestChartJson};
const browserChart = {$browserChartJson};

const palette = [
    '#2563eb',
    '#16a34a',
    '#dc2626',
];

new Chart(document.getElementById('requests-chart'), {
    type: 'line',
    data: {
        labels: requestChart.labels,
        datasets: [{
            label: 'Requests',
            data: requestChart.values,
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.12)',
            fill: true,
            tension: 0.2,
            pointRadius: 2,
        }],
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0,
                },
            },
        },
    },
});

new Chart(document.getElementById('browser-share-chart'), {
    type: 'line',
    data: {
        labels: browserChart.labels,
        datasets: browserChart.datasets.map((dataset, index) => ({
            ...dataset,
            borderColor: palette[index % palette.length],
            backgroundColor: palette[index % palette.length],
            tension: 0.2,
            pointRadius: 2,
        })),
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    callback: value => value + '%',
                },
            },
        },
    },
});
JS);
?>

<div class="site-logs py-4">
    <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
        <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <?php $form = ActiveForm::begin([
                'method' => 'get',
                'action' => ['site/logs'],
                'options' => ['class' => 'row gy-3 gx-3 align-items-end'],
                'fieldConfig' => [
                    'options' => ['class' => 'col-12 col-md-6 col-lg-3'],
                    'labelOptions' => ['class' => 'form-label'],
                    'inputOptions' => ['class' => 'form-control'],
                ],
            ]) ?>

            <?= $form->field($searchModel, 'dateFrom')->input('date')->label('Дата от') ?>
            <?= $form->field($searchModel, 'dateTo')->input('date')->label('Дата до') ?>
            <?= $form->field($searchModel, 'operatingSystemId')->dropDownList(
                $searchModel->getOperatingSystemItems(),
                ['prompt' => 'Все ОС', 'class' => 'form-select'],
            )->label('ОС') ?>
            <?= $form->field($searchModel, 'architectureId')->dropDownList(
                $searchModel->getArchitectureItems(),
                ['prompt' => 'Все архитектуры', 'class' => 'form-select'],
            )->label('Архитектура') ?>

            <div class="col-12 d-flex gap-2">
                <?= Html::submitButton('Применить', ['class' => 'btn btn-primary']) ?>
                <?= Html::a('Сбросить', ['site/logs'], ['class' => 'btn btn-outline-secondary']) ?>
            </div>

            <?php ActiveForm::end() ?>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Запросы по датам</h2>
                    <div style="height: 320px;">
                        <canvas id="requests-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Доля популярных браузеров</h2>
                    <div style="height: 320px;">
                        <canvas id="browser-share-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'tableOptions' => ['class' => 'table table-striped table-hover align-middle'],
        'layout' => "{items}\n{summary}\n{pager}",
        'emptyText' => 'Нет данных для выбранных фильтров.',
        'columns' => [
            [
                'attribute' => 'date',
                'label' => 'Дата',
            ],
            [
                'attribute' => 'requestCount',
                'label' => 'Число запросов',
                'contentOptions' => ['class' => 'text-nowrap'],
            ],
            [
                'attribute' => 'popularUrl',
                'label' => 'Самый популярный URL',
                'format' => 'text',
            ],
            [
                'attribute' => 'popularBrowser',
                'label' => 'Самый популярный браузер',
            ],
        ],
    ]) ?>
</div>
