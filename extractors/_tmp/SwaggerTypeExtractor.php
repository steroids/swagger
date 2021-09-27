<?php

namespace steroids\swagger\extractors;

use steroids\core\base\BaseSchema;
use yii\base\BaseObject;
use yii\base\Model;

/**
 * Class ControllerDocExtractor
 * @property-read string|null $actionId
 * @property-read string|null $controllerId
 * @property-read string|null $moduleId
 */
class SwaggerTypeExtractor extends BaseObject
{

    private static $_instance;

    /**
     * @var array
     */
    public $refs = [];

    /**
     * @return static
     */
    public static function getInstance()
    {
        if (!static::$_instance) {
            static::$_instance = new static();
        }
        return static::$_instance;
    }

    public function extract($className, $fields = null, $refName = null)
    {
//        if ($refName && isset($this->refs[$refName])) {
//            return ['$ref' => '#/definitions/' . $refName];
//        }

        if (is_subclass_of($className, BaseSchema::class)) {
            return SchemaExtractor::extract($className, $fields)->export();
            //$schema = $this->extractSchema($className, $fields);
        } elseif (is_subclass_of($className, Model::class)) {
            return ModelExtractor::extractOutput($className, $fields)->export();
            //$schema = $this->extractModel($className, $fields);
        } else {
            return ObjectExtractor::extract($className)->export();
            //$schema = $this->extractObject($className, $fields);
        }

//        if ($refName && !isset($schema['$ref']) && !isset($this->refs[$refName])) {
//            $this->refs[$refName] = $schema;
//            return ['$ref' => '#/definitions/' . $refName];
//        }

//        return $schema;
    }






}
