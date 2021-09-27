<?php

namespace steroids\swagger\extractors;

use steroids\core\base\BaseSchema;
use steroids\swagger\models\SwaggerContext;
use steroids\swagger\models\SwaggerProperty;

class SchemaExtractor
{
    /**
     * @param SwaggerContext $context
     * @param array|null $fields
     * @return SwaggerProperty
     * @throws \yii\base\Exception
     */
    public static function extract(SwaggerContext $context, array $fields = null)
    {
        $className = $context->className;
//        $schemaName = (new \ReflectionClass($className))->getShortName();
//        if (!isset($this->refs[$schemaName])) {
        /** @var BaseSchema $schema */
        $schema = new $className();

        if ($fields === null) {
            $fields = $schema->fields();
        }

        $items = [];
        foreach ($fields as $key => $value) {
            if (is_int($key) && is_string($value)) {
                $key = $value;
            }

            $childContext = $context->child([
                'attribute' => $key,
            ]);

            $property = null;
            $attributes = explode('.', $value);
            if (count($attributes) > 1) {
                $childContext = ModelExtractor::prepareContextByPath($childContext, $attributes);
                $items[] = ClassAttributeExtractor::extract($childContext, $childContext->attribute);
//
//
//                $attribute = array_pop($attributes);
//                foreach ($attributes as $item) {
//                    $nextModelClass = ClassAttributeExtractor::extract($childContext, $item);
//                    if (is_subclass_of($nextModelClass, ActiveQueryInterface::class)) {
//                        $childContext->className = (new $childContext->className())->getRelation($item)->modelClass;
//                    } else {
//                        $childContext->className = $nextModelClass;
//                    }
//                }
//
//                $property = ArrayHelper::getValue(ModelExtractor::extract($childContext, [$attribute]), 'items.0');
            } else {
                $attribute = $value;
                if ($schema->canGetProperty($attribute, true, false)) {
                    $items[] = ClassAttributeExtractor::extract($childContext, $attribute);
                } else {
                    $rootProperty = ClassAttributeExtractor::extract($childContext, 'model');
                    if ($rootProperty->items && count($rootProperty->items) === 1) {
                        $items[] = $rootProperty->items[0];
                    }
                }
            }
        }

//            $this->refs[$schemaName] = new SwaggerProperty([
//                'items' => $items,
//            ]);
//        }

        return new SwaggerProperty([
            'items' => $items,
        ]);
//        return [
//            //'type' => 'schema',
//            '$ref' => '#/definitions/' . $schemaName,
//        ];
    }
}