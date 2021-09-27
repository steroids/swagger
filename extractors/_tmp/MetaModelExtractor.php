<?php

namespace steroids\swagger\extractors;

use steroids\core\base\Type;
use steroids\swagger\models\SwaggerContext;
use steroids\swagger\models\SwaggerProperty;
use yii\base\Model;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

class MetaModelExtractor
{
    /**
     * @param SwaggerContext $context
     * @param string $className
     * @return SwaggerProperty|null
     * @throws \Exception
     */
    public static function extract(SwaggerContext $context, string $className)
    {
        if (!method_exists($className, 'meta')) {
            return null;
        }

        /** @var Model|ActiveRecord $model */
        $model = new $className();

        $items = [];
        $metaFields = $className::meta();

        foreach ($metaFields as $attribute => $metaAttributeData) {
            $property = new SwaggerProperty([
                'name' => $attribute,
                'description' => $model->getAttributeLabel($attribute),
                'example' => ArrayHelper::getValue($metaAttributeData, 'example'),
            ]);

            /** @var Type $appType */
            $appType = \Yii::$app->types->getTypeByModel($model, $attribute);
            $appType->prepareSwaggerProperty($className, $attribute, $property);

            $items[] = $property;
        }

        return new SwaggerProperty([
            'items' => $items,
        ]);
    }
}