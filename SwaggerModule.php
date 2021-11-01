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

    /**
     * Директория до папки, где будет созданы *.ts файлы с интерфейсами апи
     * @var string|null
     */
    public ?string $typesOutputDir = null;

    public function init()
    {
        parent::init();

        if (!$this->typesOutputDir) {
            $this->typesOutputDir = STEROIDS_ROOT_DIR . '/types';
        }
    }

    public function bootstrap($app)
    {
        if (!YII_ENV_DEV || !YII_DEBUG) {
            return;
        }

        // TODO Analyze code changes...
    }
}