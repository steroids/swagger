<?php

namespace steroids\swagger\controllers;

use steroids\swagger\components\SwaggerBuilder;
use steroids\swagger\helpers\TypeScriptHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\Controller;

class SwaggerController extends Controller
{
    public $redocUrl = 'https://cdn.jsdelivr.net/npm/redoc@next/bundles/redoc.standalone.js';

    public static function siteMap($baseUrl = 'api')
    {
        return [
            'swagger' => [
                'label' => 'Документация',
                'url' => ['index'],
                'urlRule' => $baseUrl,
                'items' => [
                    'json' => [
                        'url' => ['json'],
                        'urlRule' => $baseUrl . '/swagger.json',
                    ],
                    'types' => [
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
        //\Yii::$app->response->format = Response::FORMAT_JSON;

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
        return (new SwaggerBuilder())->buildJson();
    }

}

