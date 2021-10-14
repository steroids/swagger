<?php

namespace steroids\swagger;

use steroids\core\base\Module;
use yii\base\BootstrapInterface;

class SwaggerModule extends Module implements BootstrapInterface
{
    /**
     * Список путей (из sitemap), которые не должны попасть в документацию
     * @example: gii, *.admin
     * @var string[]
     */
    public array $skipPaths = [
        'gii',
    ];

    public function bootstrap($app)
    {
        if (!YII_ENV_DEV || !YII_DEBUG) {
            return;
        }

        // TODO Analyze code changes...
    }
}