<?php

namespace steroids\swagger\tests\mocks;

use yii\web\Controller;

class AstController extends Controller
{
    public function actionScope()
    {
        return [
            /** @type FooModel Model with scope: SCOPE_DETAIL */
            'foo' => new FooModel(),

            /** @type FooModel Bar with scope: SCOPE_DEFAULT */
            'bar' => new FooModel(),
        ];
    }

    public function actionGenericType()
    {
        /** @var array<string, BarObject[]> $items */
        $items = [
            'str' => [
                new BarObject(),
            ],
        ];

        return [
            'items' => $items,
        ];
    }
}
