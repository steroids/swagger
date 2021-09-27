<?php

namespace steroids\swagger\tests\mocks;

use steroids\auth\AuthModule;
use steroids\auth\models\AuthConfirm;
use steroids\auth\models\AuthSocial;
use yii\web\Controller;

class FirstController extends Controller
{
    public static function apiMap()
    {
        return [
            'test.first' => [
                'items' => [
                    'index' => '/api/<version>/test/first',
                    'get-detail' => '/api/<version>/test/first/detail',
                ],
            ],
        ];
    }

    /**
     * @return FooModel
     */
    public function actionIndex()
    {
        return new FooModel();
    }

    public function actionGetDetail()
    {
        $model = new FooModel();

        return [
            // Foo property
            'foo' => [
                // Foo model
                'model' => new FooModel(),

                'model2' => $model,

                /**
                 * Model3 custom type
                 * @var BarObject
                 */
                'model3' => $model,

                // Confirm model with id 1
                'confirm' => AuthConfirm::find()->where(['id' => 1])->one(),
                // Confirms list
                'confirms' => AuthConfirm::find()->where(['id' => 1])->all(),
                'confirmShort' => AuthConfirm::findOne(['id' => 1]),
                'confirmsShort' => AuthConfirm::findAll(['id' => 1]),
                'title' => 'Foo',
                'count' => 20,
                'total' => 33.5,
                'strings' => ['a', 'b', 'c'], // This is comment not worked
            ],
            /** Start comment before flag */
            'flag' => true,

            /**
             * Multiline comment for "six" value
             * Second line here...
             * @example {'foo': 99}
             */
            6 => 'six',
        ];
    }
}
