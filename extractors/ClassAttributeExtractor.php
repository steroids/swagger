<?php

namespace steroids\swagger\extractors;

use steroids\core\base\BaseSchema;
use steroids\core\base\Model;
use steroids\core\base\Type;
use steroids\swagger\helpers\ExtractorHelper;
use steroids\swagger\models\SwaggerContext;
use steroids\swagger\models\SwaggerProperty;
use yii\db\ActiveQueryInterface;
use yii\helpers\ArrayHelper;

class ClassAttributeExtractor
{
    public static function prepare(SwaggerContext $context, string $attribute)
    {
        $rawType = '';
        $classInfo = new \ReflectionClass($context->className);
        $childContext = $context->child([
            'attribute' => $attribute,
            'fields' => $context->fields,
        ]);

        // Find is class php doc (or parent classes)
        $nowClassInfo = $classInfo;
        while (true) {
            if (preg_match('/@property(-read)? +([^ |\n]+) \$' . preg_quote($attribute) . '.*/u', $nowClassInfo->getDocComment(), $matchClass)) {
                $rawType = $matchClass[2];
                $childContext->comment = $matchClass[0];
            }

            $nowClassInfo = $nowClassInfo->getParentClass();
            if (!$nowClassInfo) {
                break;
            }
        }

        // Find in class property php doc
        if (!$rawType) {
            $propertyInfo = $classInfo->hasProperty($attribute) ? $classInfo->getProperty($attribute) : null;
            if ($propertyInfo && $propertyInfo->getType() && $propertyInfo->getType()->getName()) {
                $rawType = $propertyInfo->getType()->getName();
            }
            if ($propertyInfo && preg_match('/@(var|type) +([^ |\n]+)/u', $propertyInfo->getDocComment(), $matchProperty)) {
                if (!$rawType) {
                    $rawType = $matchProperty[2];
                    $childContext->className = $propertyInfo->getDeclaringClass()->getName();
                }
                $childContext->comment = $propertyInfo->getDocComment();
            }
        }

        // Find in getter method
        if (!$rawType) {
            $getter = 'get' . ucfirst($attribute);
            $methodInfo = $classInfo->hasMethod($getter) ? $classInfo->getMethod($getter) : null;
            if ($methodInfo && preg_match('/@return +([^ |\n]+)/u', $methodInfo->getDocComment(), $matchMethod)) {
                $rawType = $matchMethod[1];
                $childContext->className = $methodInfo->getDeclaringClass()->getName();
                $childContext->comment = $methodInfo->getDocComment();
            }
        }

        // Normalize
        $rawType = trim($rawType);

        return [$childContext, $rawType];
    }

    /**
     * @param SwaggerContext $context
     * @param string $attribute
     * @return SwaggerProperty
     * @throws \ReflectionException
     * @throws \yii\base\Exception
     */
    public static function extract(SwaggerContext $context, string $attribute): SwaggerProperty
    {
        $context->attribute = $attribute;

        list($childContext, $rawType) = static::prepare($context, $attribute);

        // Normalize
        $rawType = trim($rawType);

        // Relation detect
        if ($rawType && class_exists($rawType) && is_subclass_of($rawType, ActiveQueryInterface::class)) {
            $relation = ExtractorHelper::safeGetRelation($childContext->className, $attribute);
            if ($relation) {
                $relationContext = $childContext->child([
                    'className' => $relation->modelClass,
                    'attribute' => $attribute,
                    'fields' => $childContext->fields,
                ]);
                $property = ModelExtractor::extract($relationContext);
                $property->isArray = $relation->multiple;
                return $property;
            }
        }

        // Single
        $property = TypeExtractor::extract($childContext, $rawType);
        $property->name = $attribute;
        $property->phpdoc = $childContext->comment;

        // Meta model
        if (method_exists($childContext->className, 'meta')
            && $meta = ArrayHelper::getValue($childContext->className::meta(), $attribute)
                && (
                    is_subclass_of($childContext->className, \yii\base\Model::class)
                    || is_subclass_of($childContext->className, BaseSchema::class)
                )) {
            /** @var array $meta */

            // Required
            if ($childContext->isInput) {
                $property->isRequired = ArrayHelper::getValue($meta, 'isRequired') === true;
            }

            // Description & example
            if ($property->description) {
                $property->description = ArrayHelper::getValue($meta, 'label');
            }
            if ($property->example) {
                $property->example = ArrayHelper::getValue($meta, 'example');
            }

            // Type props
            /** @var Type $appType */
            \Yii::$app->types
                ->getTypeByModel($childContext->className, $attribute)
                ->prepareSwaggerProperty($childContext->className, $attribute, $property);
        }

        return $property;
    }
}