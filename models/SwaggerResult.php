<?php

namespace steroids\swagger\models;

use steroids\swagger\events\SwaggerExportEvent;
use yii\base\Component;
use yii\helpers\ArrayHelper;

class SwaggerResult extends Component
{
    const EVENT_EXPORT = 'export';

    public $version = 'v1';
    public $siteName;
    public $hostName;
    public $adminEmail;

    public SwaggerRefsStorage $refsStorage;

    /**
     * @var SwaggerAction[]
     */
    public array $actions = [];

    protected $tags = [];
    protected $paths = [];

    public function init()
    {
        parent::init();

        $this->refsStorage = new SwaggerRefsStorage();
    }

    /**
     * @param SwaggerAction[] $actions
     */
    public function addActions(array $actions)
    {
        $this->actions = array_merge($this->actions, $actions);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        ArrayHelper::multisort($this->tags, 'name');
        $json = [
            'swagger' => '2.0',
            'info' => [
                'version' => $this->version,
                'title' => $this->siteName . ' API',
                'description' => $this->siteName . ' API',
                'termsOfService' => 'http://swagger.io/terms/',
                'contact' => $this->adminEmail ? ['email' => $this->adminEmail] : (object)[],
                'license' => [
                    'name' => 'Apache 2.0',
                    'url' => 'http://www.apache.org/licenses/LICENSE-2.0.html',
                ]
            ],
            'host' => $this->hostName,
            'basePath' => $this->getBasePath(),
            'schemes' => [\Yii::$app->request->isSecureConnection ? 'https' : 'http'],
            'tags' => [],
            'paths' => [],
            'definitions' => $this->refsStorage->exportDefinitions(),
        ];

        // Add paths
        $tags = [];
        foreach ($this->actions as $action) {
            // Export
            $schema = $action->export();

            // Add path schema
            $json['paths'][$action->url][$action->httpMethod] = $schema;

            // Add tags
            $tags = array_merge($tags, ArrayHelper::getValue($schema, 'tags', []));
        }

        // Add tags
        $json['tags'] = array_values(array_map(fn ($name) => ['name' => $name], array_unique($tags)));

        $event = new SwaggerExportEvent([
            'json' => $json,
        ]);
        $this->trigger(self::EVENT_EXPORT, $event);

        return $event->json;
    }

    public function getBasePath()
    {
        return '/api/' . preg_replace('/\.[^\.]+$/', '', $this->version);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_SLASHES);
    }
}




