<?php

namespace steroids\swagger\extractors;

use steroids\core\base\Model;
use steroids\core\base\SearchModel;
use steroids\swagger\models\SwaggerContext;
use steroids\swagger\models\SwaggerProperty;

class SearchModelExtractor
{
    /**
     * @param SwaggerContext $context
     * @param array|null $fields
     * @return SwaggerProperty
     * @throws \yii\base\Exception
     */
    public static function extract(SwaggerContext $context, array $fields = null)
    {
        if (is_subclass_of($context->className, SearchModel::class)) {
            $searchModel = new $context->className;
            $modelClassName = $searchModel->fieldsSchema() ?: $searchModel->createQuery()->modelClass;
        } else {
            $modelClassName = $context->className;
        }

        if ($context->isInput) {
            // TODO page, pageSize need? or always have?
            /*
            'page' => [
                'description' => 'Page',
                'type' => 'number',
                'example' => 2,
            ],
            'pageSize' => [
                'description' => 'Page size',
                'type' => 'number',
                'example' => 50,
            ],
             */

            if (is_subclass_of($context->className, SearchModel::class)) {
                return ModelExtractor::extract($context->child(['isInputForGetMethod' => false]), $fields);
            } else {
                return ModelExtractor::extract($context->child(['className' => SearchModel::class, 'isInputForGetMethod' => false]), $fields);
            }
        }

        $itemsProperty = ModelExtractor::extract($context->child([
            'className' => $modelClassName,
            'fields'=> $fields,
            'scope' => Model::SCOPE_LIST,
        ]));
        $itemsProperty->name = 'items';
        $itemsProperty->description = 'Fined items';
        $itemsProperty->isArray = true;

        return new SwaggerProperty([
            'items' => [
                ClassAttributeExtractor::extract($context, 'meta'),
                new SwaggerProperty([
                    'name' => 'total',
                    'description' => 'Total items count',
                    'phpType' => 'integer',
                    'isPrimitive' => true,
                ]),
                $itemsProperty,
            ],
        ]);
    }
}