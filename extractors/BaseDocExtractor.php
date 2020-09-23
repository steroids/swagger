<?php

namespace steroids\swagger\extractors;

use steroids\core\base\BaseSchema;
use steroids\core\base\FormModel;
use steroids\core\base\Model;
use steroids\core\base\SearchModel;
use steroids\swagger\helpers\ExtractorHelper;
use steroids\swagger\models\SwaggerJson;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;

abstract class BaseDocExtractor extends BaseObject
{
    /**
     * @var SwaggerJson
     */
    public $swaggerJson;

    /**
     * @var string[]
     */
    public $listenRelations = [];

    /**
     * @var array
     */
    public $params = [];

    public function createTypeExtractor($type, $url, $method)
    {
        if (ExtractorHelper::isPrimitiveType($type)) {
            // TODO
        } else if (class_exists($type)) {
            if (is_subclass_of($type, SearchModel::class)) {
                return new SearchModelDocExtractor([
                    'swaggerJson' => $this->swaggerJson,
                    'className' => $type,
                    'url' => $url,
                    'method' => $method,
                ]);
            }
            if (is_subclass_of($type, FormModel::class) || is_subclass_of($type, Model::class) || is_subclass_of($type, BaseSchema::class)) {
                return new FormModelDocExtractor([
                    'swaggerJson' => $this->swaggerJson,
                    'className' => $type,
                    'url' => $url,
                    'method' => $method,
                ]);
            }
        }
    }

    protected function applyParamsToRequestSchema($requestSchema)
    {
        if (count($this->params) > 0) {
            if (empty($requestSchema)) {
                $requestSchema = [
                    'type' => 'object',
                    'properties' => [],
                ];
            }

            $requestSchema['properties'] = ArrayHelper::merge($this->params, $requestSchema['properties']);
        }
        return $requestSchema;
    }

    abstract function run();
}
