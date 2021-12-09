<?php

namespace steroids\swagger\extractors;

use steroids\core\base\BaseSchema;
use steroids\core\base\Type;
use steroids\swagger\helpers\ExtractorHelper;
use steroids\swagger\models\SwaggerContext;
use steroids\swagger\models\SwaggerProperty;
use yii\db\ActiveQueryInterface;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

class ClassAttributeExtractor
{
    public static function prepare(SwaggerContext $context, string $attribute)
    {
        $rawType = '';
        $description = null;
        $classInfo = new \ReflectionClass($context->className);
        $childContext = $context->child([
            'attribute' => $attribute,
            'fields' => $context->fields,
        ]);

        // Find is class php doc (or parent classes)
        $nowClassInfo = $classInfo;
        while (true) {
            foreach (explode("\n", $nowClassInfo->getDocComment()) as $line) {
                if (!preg_match('/@property(-read)/', $line)) {
                    continue;
                }
                $parsedLine = ExtractorHelper::parseCommentType($line);
                if ($parsedLine['variable'] === $attribute && $parsedLine['type']) {
                    $rawType = $parsedLine['type'];
                    if (!$description && $parsedLine['description']) {
                        $description = $parsedLine['description'];
                    }
                    break;
                }
            }
            // TODO
            // TODO
            // TODO foreach lines...
            // TODO
            // TODO
//            if (preg_match('/@property(-read)? +([^ |\n]+) \$' . preg_quote($attribute) . '(\s|\n)[^\n]*/u', $nowClassInfo->getDocComment(), $matchClass)) {
//                $rawType = $matchClass[2];
//                $parsedLine = ExtractorHelper::parseCommentType($matchClass[0]);
//                if ($parsedLine['type']) {
//                    $rawType = $parsedLine['type'];
//                    if (!$description && $parsedLine['description']) {
//                        $description = $parsedLine['description'];
//                    }
//                }
//            }

            $nowClassInfo = $nowClassInfo->getParentClass();
            if (!$nowClassInfo) {
                break;
            }
        }

        // Find in class property php doc
        if (!$rawType) {
            $propertyInfo = $classInfo->hasProperty($attribute) ? $classInfo->getProperty($attribute) : null;
            if ($propertyInfo) {
                if ($propertyInfo->getType() && $propertyInfo->getType()->getName()) {
                    $rawType = $propertyInfo->getType()->getName();
                }

                foreach (explode("\n", $propertyInfo->getDocComment()) as $line) {
                    $parsedLine = ExtractorHelper::parseCommentType($line);
                    if (in_array($parsedLine['tag'], ['var', 'type'])) {
                        if (!$rawType && $parsedLine['type']) {
                            $rawType = $parsedLine['type'];
                            $childContext->className = $propertyInfo->getDeclaringClass()->getName();
                        }
                    }
                    if (!$description && $parsedLine['description']) {
                        $description = $parsedLine['description'];
                    }
                }
            }
        }

        // Find in getter method
        if (!$rawType || !$description) {
            $getter = 'get' . ucfirst($attribute);
            $methodInfo = $classInfo->hasMethod($getter) ? $classInfo->getMethod($getter) : null;
            if ($methodInfo) {
                if (!$rawType && preg_match('/@return +([^ |\n]+)/u', $methodInfo->getDocComment(), $matchMethod)) {
                    $rawType = $matchMethod[1];
                    $childContext->className = $methodInfo->getDeclaringClass()->getName();
                    $childContext->comment = $methodInfo->getDocComment();
                }

                if (!$description) {
                    foreach (explode("\n", $methodInfo->getDocComment()) as $line) {
                        $parsedLine = ExtractorHelper::parseCommentType($line);
                        if ($parsedLine['description']) {
                            $description = $parsedLine['description'];
                            break;
                        }
                    }
                }
            }
        }

        // Normalize
        $rawType = trim($rawType);

        return [$childContext, $rawType, $description];
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

        list($childContext, $rawType, $description) = static::prepare($context, $attribute);

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
                $property->name = $attribute;
                $property->isArray = $relation->multiple;
                return $property;
            }
        }

        // Single
        $property = TypeExtractor::extract($childContext, $rawType);
        $property->name = $attribute;
        $property->phpdoc = $childContext->comment;

        // Get description from phpdoc
        if (!$property->description && $description) {
            $property->description = $description;
        }

        // Get description, label from relation attribute
        if (is_subclass_of($childContext->className, ActiveRecord::class)) {
            $relation = ExtractorHelper::safeGetRelation($childContext->className, $attribute);
            if ($relation) {
                $tmpProperty = static::extract($context, array_values($relation->link)[0]);
                if (!$property->description) {
                    $property->description = $tmpProperty->description;
                }
            }
        }

        // Meta model
        if (method_exists($childContext->className, 'meta')
            && ($meta = ArrayHelper::getValue($childContext->className::meta(), $attribute))
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
            if (!$property->description) {
                $property->description = ArrayHelper::getValue($meta, 'label');
            }
            if (!$property->example) {
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