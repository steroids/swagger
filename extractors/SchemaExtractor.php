<?php

namespace steroids\swagger\extractors;

use steroids\core\base\BaseSchema;
use steroids\swagger\models\SwaggerContext;
use steroids\swagger\models\SwaggerProperty;
use yii\helpers\StringHelper;

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

        // Refs
        if ($context->refsStorage && !$fields && !$context->isInput) {
            $refKey = StringHelper::basename($className);
            if ($context->refsStorage->hasRef($refKey)) {
                return $context->refsStorage->getRef($refKey);
            }
        }

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
            if (is_array($value)) {
                // Relation
                list($subContext) = ClassAttributeExtractor::prepare($context, 'model');
                $items[] = ClassAttributeExtractor::extract($subContext, $key);
            } elseif (is_string($value)) {
                $attributes = explode('.', $value);
                if (count($attributes) > 1) {
                    $childContext = ModelExtractor::prepareContextByPath($childContext, $attributes);
                    $items[] = ClassAttributeExtractor::extract($childContext, $childContext->attribute);
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
        }


        $resultProperty = new SwaggerProperty([
            'items' => $items,
        ]);

        // Refs
        if (isset($refKey)) {
            $resultProperty->refName = $refKey;
            $resultProperty->refsStorage = $context->refsStorage;
            $context->refsStorage->setRef($refKey, $resultProperty);
        }

        return $resultProperty;
    }
}