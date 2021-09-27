<?php

namespace steroids\swagger\extractors;

use steroids\core\base\Type;
use steroids\swagger\helpers\ExtractorHelper;
use steroids\swagger\models\SwaggerContext;
use steroids\swagger\models\SwaggerProperty;
use yii\base\Model;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;
use yii\helpers\ArrayHelper;

class ModelRequestExtractor
{
    /**
     * @param SwaggerContext $context
     * @param string $className
     * @param array|null $fields
     * @return SwaggerProperty
     * @throws \Exception
     */
    public static function extract(SwaggerContext $context, string $className, array $fields = null)
    {
        /** @var Model|ActiveRecord $model */
        $model = new $className();

        if ($fields === null) {
            $fields = $model->safeAttributes();
        }

        $items = [];
        foreach ($fields as $attributes) {
            $attributes = explode('.', $attributes);
            $attribute = array_shift($attributes);

            if ($model instanceof BaseActiveRecord && $relation = ExtractorHelper::safeGetRelation(get_class($model), $attribute)) {
                // Relation
                /** @var Model|ActiveRecord $relationModel */
                $relationModelClass = $relation->modelClass;
                $relationModel = new $relationModelClass();
                $property = static::extract($context, $relationModelClass, array_merge($relationModel->safeAttributes(), $attributes));

                // Check hasMany relation
                if ($relation->multiple) {
                    $property = [
                        'type' => 'array',
                        'items' => $property,
                    ];
                }
            } else {
                $property = SwaggerProperty::createFromAttribute($context, $className, $attribute);

                // Steroids meta model
                if ($property && method_exists($className, 'meta')) {
                    $property->description = $model->getAttributeLabel($attribute)
                        ? $model->getAttributeLabel($attribute)
                        : ArrayHelper::getValue($property, 'description');
                    $property->example = ArrayHelper::getValue($className::meta(), [$attribute, 'example'], ArrayHelper::getValue($property, 'example'));

                    /** @var Type $appType */
                    $appType = \Yii::$app->types->getTypeByModel($model, $attribute);
                    $appType->prepareSwaggerProperty($className, $attribute, $property);
                }
            }

            if ($property) {
                $property->name = $attribute;
                $items[] = $property;
            }
        }

        return new SwaggerProperty([
            'items' => $items,
        ]);
    }

}