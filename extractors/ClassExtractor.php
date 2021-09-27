<?php

namespace steroids\swagger\extractors;

use Doctrine\Common\Annotations\TokenParser;
use steroids\core\base\BaseSchema;
use steroids\swagger\models\SwaggerContext;
use steroids\swagger\models\SwaggerProperty;
use yii\base\Exception;
use yii\base\Model;
use yii\helpers\ArrayHelper;

class ClassExtractor
{
    /**
     * @param SwaggerContext $context
     * @param string $className
     * @param array|null $fields
     * @return SwaggerProperty
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function extract(SwaggerContext $context, string $className, array $fields = null): SwaggerProperty
    {
        $className = static::resolveClassName($className, $context->className);
        $childContext = $context->child([
            'className' => $className,
        ]);

        if (is_subclass_of($className, BaseSchema::class)) {
            return SchemaExtractor::extract($childContext, $fields);
        } elseif (is_subclass_of($className, Model::class)) {
            return ModelExtractor::extract($childContext, $fields);
        }
        return ObjectExtractor::extract($childContext);
    }


    /**
     * @param string $shortName
     * @param string $inClassName
     * @return string
     * @throws \ReflectionException
     */
    public static function resolveClassName(string $shortName, string $inClassName): string
    {
        $classInfo = new \ReflectionClass($inClassName);
        $namespace = $classInfo->getNamespaceName();

        // Check name with namespace
        if (strpos($shortName, '\\') !== false) {
            return $shortName;
        }

        // Fetch use statements
        $tokenParser = new TokenParser(file_get_contents($classInfo->getFileName()));
        $useStatements = $tokenParser->parseUseStatements($namespace);
        $tokenParser = new TokenParser(file_get_contents($classInfo->getParentClass()->getFileName()));
        $useStatements = array_merge($tokenParser->parseUseStatements($namespace), $useStatements);

        $className = ArrayHelper::getValue($useStatements, strtolower($shortName), $namespace . '\\' . $shortName);
        $className = '\\' . ltrim($className, '\\');
        return $className;
    }
}