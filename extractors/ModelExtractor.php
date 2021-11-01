<?php

namespace steroids\swagger\extractors;

use steroids\core\base\FormModel;
use steroids\core\base\SearchModel;
use steroids\swagger\models\SwaggerContext;
use steroids\swagger\models\SwaggerProperty;
use yii\base\Exception;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use yii\helpers\StringHelper;

class ModelExtractor
{
    /**
     * @param SwaggerContext $context
     * @param array|null $fields
     * @return SwaggerProperty
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function extract(SwaggerContext $context, array $fields = null): SwaggerProperty
    {
        if ($context->isInputForGetMethod) {
            return new SwaggerProperty();
        }

        if (is_array($fields)) {
            $context->fields = $fields;
        } else {
            $fields = $context->fields;
        }
        $className = $context->className;

        if (!is_string($className) || !class_exists($className)) {
            throw new \Exception('Invalid class name: ' . $className);
        }

        $model = new $className();

        // Refs
        if ($context->refsStorage && !$context->fields && !$context->isInput && !($model instanceof FormModel) && !($model instanceof SearchModel)) {
            $refKey = StringHelper::basename($className);
            if (!empty($context->scopes)) {
                $refKey .= 'Scope';
                foreach ($context->scopes ?: [] as $scope) {
                    $refKey .= ucfirst($scope);
                }
            }
            if ($context->refsStorage->hasRef($refKey)) {
                return $context->refsStorage->getRef($refKey);
            }
        }

        if ($context->isInput) {
            $fields = [];
            if ($model instanceof Model) {
                foreach ($model->safeAttributes() as $attribute) {
                    // Skip params from url
                    // TODO need??
//                if (stripos($this->url, '{' . $attribute . '}') !== false) {
//                    continue;
//                }

                    // Write only params
                    if ($model->canSetProperty($attribute)) {
                        $fields[] = $attribute;
                    }
                }
            }
        } else {
            // Output schema
            if ($model instanceof FormModel) {
                $schema = $model->fieldsSchema();
                if ($schema) {
                    return SchemaExtractor::extract($schema, !empty($fields) ? $fields : null);
                }
            }

            if ($fields === null) {
                $fields = static::getModelFields($context, $model);
            }
        }

        // Detect * => model.*
        foreach ($fields as $key => $name) {
            // Syntax: * => model.*
            if ($key === '*' && preg_match('/\.*$/', $name) !== false) {
                unset($fields[$key]);

                $attribute = substr($name, 0, -2);

                $subProperty = ClassAttributeExtractor::extract($context->child(), $attribute);
                if ($subProperty) {
                    $subClassName = $subProperty->phpType;
                    if (class_exists($subClassName)) {
                        $subModel = new $subClassName();
                        foreach (static::getModelFields($context, $subModel) as $key2 => $name2) {
                            $key2 = is_int($key2) ? $name2 : $key2;
                            $fields[$key2] = $attribute . '.' . $name2;
                        }
                    }
                }
            }
        }

        $items = [];
        foreach ($fields as $key => $attributes) {
            if (is_int($key) && is_string($attributes)) {
                $key = $attributes;
            }

            $childContext = $context->child([
                'attribute' => $key,
                'fields' => is_array($attributes) ? $attributes : null,
            ]);

            // Function: 'user' => function($model) { return ... },
            if (is_callable($attributes)) {
                $items[] = ClassAttributeExtractor::extract($childContext, $key);
                continue;
            }

            // Path to attribute: 'name' => 'user.name'
            if (is_string($attributes)) {
                $childContext = static::prepareContextByPath($childContext, explode('.', $attributes));
                $items[] = ClassAttributeExtractor::extract($childContext, $childContext->attribute);
            }
        }

        $resultProperty = new SwaggerProperty([
            'items' => $items,
        ]);

        // Refs
        if (isset($refKey)) {
            $resultProperty->refName = $refKey;
            $resultProperty->refsStorage = $context->refsStorage;
            $context->refsStorage->setRef($className, $refKey, $resultProperty);
        }

        return $resultProperty;
    }

    public static function prepareContextByPath(SwaggerContext $context, array $attributes)
    {
        $attribute = array_pop($attributes);

        // Find sub model for attributes map case
        if (count($attributes) > 0) {
            foreach ($attributes as $subAttribute) {
                $context->attribute = $subAttribute;

                list($subContext, $subModelClass) = ClassAttributeExtractor::prepare($context, $subAttribute);
                $subModelClass = ClassExtractor::resolveClassName($subModelClass, $subContext->className);
                if (!$subModelClass || !class_exists($subModelClass)) {
                    break;
                }

                $context = $context->child([
                    'className' => $subModelClass,
                ]);
            }

            $context = $context->child([
                'attribute' => $attribute,
            ]);
        }

        return $context;
    }

    /**
     * @param SwaggerContext $context
     * @param FormModel|Model $model
     * @return null
     */
    protected static function getModelFields(SwaggerContext $context, $model)
    {
        $fields = null;
        if (method_exists($model, 'frontendFields')) {
            $scopes = $context->scopes ?: [];
            if (!in_array(\steroids\core\base\Model::SCOPE_DEFAULT, $scopes)) {
                array_unshift($scopes, \steroids\core\base\Model::SCOPE_DEFAULT);
            }
            $frontendFields = $model->frontendFields();
            if ($frontendFields !== null) {
                $fields = [];
                foreach ($scopes as $scope) {
                    foreach (ArrayHelper::getValue($frontendFields, $scope, []) as $key => $value) {
                        if (!is_int($key)) {
                            $fields[$key] = $value;
                        } elseif (!in_array($value, $fields) && !isset($fields[$value])) {
                            $fields[] = $value;
                        }
                    }
                }
            }
        }

        if (!$fields) {
            $fields = $model->fields();
        }

        return $fields;
    }
}