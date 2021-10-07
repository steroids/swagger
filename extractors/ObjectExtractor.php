<?php

namespace steroids\swagger\extractors;

use steroids\swagger\models\SwaggerContext;
use steroids\swagger\models\SwaggerProperty;

class ObjectExtractor
{
    /**
     * @param SwaggerContext $context
     * @return SwaggerProperty
     * @throws \ReflectionException
     */
    public static function extract(SwaggerContext $context)
    {
        if ($context->isInput) {
            return new SwaggerProperty();
        }

        return new SwaggerProperty([
            'items' => array_filter(
                array_map(
                    function (\ReflectionProperty $propertyInfo) use ($context) {
                        return $propertyInfo->isPublic()
                            ? ClassAttributeExtractor::extract(
                                $context->child(['attribute' => $propertyInfo->name]),
                                $propertyInfo->name
                            )
                            : null;
                    },
                    (new \ReflectionClass($context->className))->getProperties()
                )
            ),
        ]);
    }

}