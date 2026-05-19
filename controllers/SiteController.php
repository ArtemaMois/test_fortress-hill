<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use app\models\ContactForm;
use app\models\LogSearchForm;
use yii\captcha\CaptchaAction;
use yii\db\Exception;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\base\Security;
use yii\mail\MailerInterface;
use yii\web\Controller;
use yii\web\ErrorAction;
use yii\web\Response;

class SiteController extends Controller
{
    public function __construct(
        $id,
        $module,
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions(): array
    {
        return [
            'error' => [
                'class' => ErrorAction::class,
            ],
            'captcha' => [
                'class' => CaptchaAction::class,
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
                'transparent' => true,
            ],
        ];
    }

    public function actionIndex(): string
    {
        return $this->render('index');
    }

    /**
     * @throws Exception
     */
    public function actionLogs(): string
    {
        $searchModel = new LogSearchForm();
        $searchModel->load($this->request->get());
        $searchModel->applyDefaultDates();
        $report = $searchModel->search();

        return $this->render('logs', [
            'searchModel' => $searchModel,
            'dataProvider' => $report['dataProvider'],
            'requestChart' => $report['requestChart'],
            'browserChart' => $report['browserChart'],
        ]);
    }
}
