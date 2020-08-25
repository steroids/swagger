<?php

namespace steroids\docs\controllers;

use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\Controller;
use yii\helpers\ArrayHelper;
use steroids\core\components\SiteMapItem;
use steroids\docs\models\SwaggerJson;
use steroids\docs\extractors\SiteMapDocExtractor;
use yii\web\Response;

class DocsController extends Controller
{
    /**
     * Relative URL that should lead to SPA Swagger viewer
     * @example 'swagger-docs'
     * @var string
     */
    public static string $baseUrl = 'docs';

    private const REDOC_URL = 'https://cdn.jsdelivr.net/npm/redoc/bundles/redoc.standalone.js';
    private const JSON_ROUTE = ['/docs/docs/json'];

    public static function siteMap()
    {
        return [
            'docs' => [
                'label' => 'Документация',
                'url' => ['/docs/docs/index'],
                'urlRule' => static::$baseUrl,
                'items' => [
                    'json' => [
                        'visible' => false,
                        'url' => self::JSON_ROUTE,
                        'urlRule' => static::$baseUrl . '/swagger.json',
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
        $this->view->registerJsFile(self::REDOC_URL);

        return $this->renderContent(
            Html::tag('redoc', '', ['spec-url' => Url::to(self::JSON_ROUTE)])
        );
    }

    /**
     * @return array
     * @throws \yii\base\InvalidConfigException
     * @throws \Exception
     */
    public function actionJson()
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

        \Yii::$app->response->format = Response::FORMAT_JSON;

        return $swaggerJson->toArray();
    }
}

