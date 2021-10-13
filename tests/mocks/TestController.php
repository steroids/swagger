<?php

namespace steroids\swagger\tests\mocks;

use yii\web\Controller;

class TestController extends Controller
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

    /**
     * @param int|null $pageSize Page size
     * @return int
     */
    public function actionCustomGetParam(int $pageSize = null): int
    {
        return 1;
    }

    /**
     * @param-get int $pageSize Page size
     * @return int
     */
    public function actionCustomGetAliasParam(): int
    {
        return 1;
    }

    /**
     * @param-post $query Search query
     * @return int
     */
    public function actionCustomPostParam(): int
    {
        return 1;
    }
}
