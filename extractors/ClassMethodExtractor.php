<?php

namespace steroids\swagger\extractors;

use steroids\core\base\CrudApiController;
use steroids\swagger\helpers\ExtractorHelper;
use steroids\swagger\models\SwaggerContext;
use steroids\swagger\models\SwaggerProperty;
use yii\base\Exception;

class ClassMethodExtractor
{
    /**
     * @param SwaggerContext $context
     * @param string $methodName
     * @return SwaggerProperty
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function extract(SwaggerContext $context, string $methodName)
    {
        $context->methodName = $methodName;

        $classInfo = new \ReflectionClass($context->className);
        if (!$classInfo) {
            throw new Exception('Not found class: ' . $context->className);
        }

        $methodInfo = $classInfo->getMethod($methodName);
        if (!$methodInfo) {
            throw new Exception('Not found method "' . $methodName . '" in class: ' . $context->className);
        }

        $comment = $methodInfo->getDocComment();

        $childContext = $context->child([
            'className' => $methodInfo->getDeclaringClass()->name,
            'methodName' => $methodInfo->name,
        ]);

        // Detect CRUD controller
        $className = $context->className;
        if (is_subclass_of($className, CrudApiController::class) && in_array($context->methodName, ['actionIndex', 'actionCreate', 'actionUpdate', 'actionView'])) {
            // Get model class
            $modelClass = $className::modelClass() ?: $className::$modelClass;
            if ($context->methodName === 'actionIndex') {
                $modelClass = $className::searchModelClass() ?: $className::$searchModelClass ?: $modelClass;
            } elseif ($context->isInput) {
                $viewSchema = $className::viewSchema();
                if ($viewSchema) {
                    return SchemaExtractor::extract($childContext->child([
                        'className' => $viewSchema,
                    ]));
                }
            }

            // Check model exists
            if (!$modelClass) {
                return new SwaggerProperty();
            }

            return $context->methodName === 'actionIndex'
                ? SearchModelExtractor::extract($childContext->child([
                    'className' => $modelClass,
                    'fields' => (new $className('tmp', 'tmp'))->fields(),
                ]))
                : ClassExtractor::extract($childContext, $modelClass);
        }

        // Listens
        if ($childContext->isInput && preg_match_all('/@request-listen-relation\s+([^\s]+)/i', $comment, $listenMatch)) {
            $childContext->fields = $listenMatch[1];
        }

        $property = null;

        // Find return type in phpdoc
        if (preg_match('/@return ([a-z0-9_]+)/i', $comment, $returnMatch)) {
            $returnProperty = TypeExtractor::extract($childContext, $returnMatch[1]);
            if ($returnProperty->items) {
                $property = $returnProperty;
            }
        }

        // Find return type in source AST
        if (!$property) {
            $properties = AstExtractor::extract($childContext, $methodInfo->name);
            if (count($properties) > 0 && (!$childContext->isInput || !$properties[0]->isPrimitive)) {
                $property = $properties[0];
            }
        }

        // Request params from phpdoc
        $requestProperties = [];
        if ($childContext->isInput) {
            foreach (explode("\n", $comment) as $line) {
                $parsedLine = ExtractorHelper::parseCommentType($line);
                if (in_array($parsedLine['tag'], ['param', 'param-get', 'param-post'])) {
                    $paramProperty = TypeExtractor::extract($childContext, $parsedLine['type'] ?: '');
                    $paramProperty->name = $parsedLine['variable'];
                    $paramProperty->description = $parsedLine['description'];
                    $requestProperties[] = $paramProperty;
                }
            }
        }
        /*if ($childContext->isInput && preg_match_all('/@param(-post)? +([^\s\n]+) +([^\s\n]+)( [^\n]+)?/i', $comment, $paramsMatch, PREG_SET_ORDER)) {
            foreach ($paramsMatch as $paramMatch) {
                $paramContext = $childContext->child([
                    'methodName' => $methodInfo->name,
                    'comment' => $paramMatch[0],
                ]);

                if (strpos($paramMatch[2], '$') === 0) {
                    $paramProperty = TypeExtractor::extract($paramContext, 'string');
                    $paramProperty->name = substr($paramMatch[2], 1);
                    $requestProperties[] = $paramProperty;
                } elseif (strpos($paramMatch[3], '$') === 0) {
                    $paramProperty = TypeExtractor::extract($paramContext, $paramMatch[2]);
                    $paramProperty->name = substr($paramMatch[3], 1);
                    $requestProperties[] = $paramProperty;
                }
            }
        }*/
        if (count($requestProperties) > 0) {
            if (!$property) {
                $property = new SwaggerProperty();
                $property->items = $requestProperties;
            }
        }

        // Blank type
        if (!$property) {
            $property = TypeExtractor::extract($childContext, '');
        }

        return $property;
    }
}
