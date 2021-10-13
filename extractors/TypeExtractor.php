<?php

namespace steroids\swagger\extractors;

use steroids\swagger\models\SwaggerContext;
use steroids\swagger\models\SwaggerProperty;
use yii\base\Exception;
use yii\helpers\ArrayHelper;

class TypeExtractor
{
    const TYPE_ALIASES = [
        'int' => 'integer',
        'bool' => 'boolean',
        'double' => 'float',
        'true' => 'boolean',
        'false' => 'boolean',
    ];

    /**
     * @param SwaggerContext $context
     * @param string $rawType
     * @return SwaggerProperty
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function extract(SwaggerContext $context, string $rawType)
    {
        // Detect generic array
        if (preg_match('/array<(([^>,]+),\s*)?([^>]+)>/i', $rawType, $match)) {
            // TODO How to says, that is key-array object?..
            $property = static::extract($context, $match[3]);
            $property->isArray = true;
            $property->arrayDepth++;
            return $property;
        }

        // Get only one type
        // TODO Support multiple types
        $separatorPos = strpos($rawType, '|');
        if ($separatorPos !== false) {
            $rawType = substr($rawType, 0, $separatorPos);
        }

        // Detect array
        $isArray = preg_match('/\[\]$/', $rawType);
        $rawType = preg_replace('/\[\]$/', '', $rawType);

        // Normalize for single type
        $rawType = ArrayHelper::getValue(self::TYPE_ALIASES, $rawType, $rawType);

        // Check is single
        $isPrimitive = ArrayHelper::keyExists($rawType, SwaggerProperty::SINGLE_MAPPING);

        // Create instance
        $property = !$isPrimitive && $rawType
            ? ClassExtractor::extract($context, $rawType)
            : new SwaggerProperty(['phpType' => $rawType]);
        $property->isPrimitive = $isPrimitive;
        $property->isArray = $isArray;

        return $property;
    }
}