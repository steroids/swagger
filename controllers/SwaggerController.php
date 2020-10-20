<?php

namespace steroids\swagger\controllers;

use steroids\swagger\helpers\TypeScriptHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\Controller;
use yii\helpers\ArrayHelper;
use steroids\core\components\SiteMapItem;
use steroids\swagger\models\SwaggerJson;
use steroids\swagger\extractors\SiteMapDocExtractor;
use yii\web\Response;

class SwaggerController extends Controller
{
    public $redocUrl = 'https://cdn.jsdelivr.net/npm/redoc/bundles/redoc.standalone.js';

    public static function siteMap($baseUrl = 'api')
    {
        return [
            'swagger' => [
                'label' => 'Документация',
                'url' => ['index'],
                'urlRule' => $baseUrl,
                'items' => [
                    'json' => [
                        'visible' => false,
                        'url' => ['json'],
                        'urlRule' => $baseUrl . '/swagger.json',
                    ],
                    'types' => [
                        'visible' => false,
                        'url' => ['types'],
                        'urlRule' => $baseUrl . '/types',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return string
     * @throws \yii\base\InvalidConfigException
     */
    public function actionIndex()
    {
        $this->layout = '@steroids/core/views/layout-blank';
        $this->view->registerJsFile($this->redocUrl);

        return $this->renderContent(
            Html::tag('redoc', '', ['spec-url' => Url::to(['/swagger/swagger/json'])])
        );
    }

    /**
     * @return array
     * @throws \yii\base\InvalidConfigException
     * @throws \Exception
     */
    public function actionJson()
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;

        return $this->generateJson();
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function actionTypes()
    {
        return '<pre>' . TypeScriptHelper::jsonToTypes($this->generateJson());
    }

    protected function generateJson()
    {
        $swaggerJson = new SwaggerJson(
            [
                'siteName' => \Yii::$app->name,
                'hostName' => \Yii::$app->request->hostName,
                'adminEmail' => ArrayHelper::getValue(\Yii::$app->params, 'adminEmail', ''),
            ]
        );

        $siteItems = ArrayHelper::getValue(\Yii::$app->siteMap->getItem('api'), 'items', []);
        $visibleSiteMapItems = array_filter($siteItems, function (SiteMapItem $item) {
            return $item->getVisible(false);
        });

        $extractor = new SiteMapDocExtractor([
            'items' => $visibleSiteMapItems,
            'swaggerJson' => $swaggerJson,
        ]);
        $extractor->run();

        return $swaggerJson->toArray();
    }

}

