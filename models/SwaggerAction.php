<?php

namespace steroids\swagger\models;

use steroids\swagger\extractors\ClassMethodExtractor;
use yii\base\BaseObject;
use yii\helpers\Inflector;

class SwaggerAction extends BaseObject
{
    public string $controllerClass;
    public string $methodName;
    public string $moduleId;
    public string $controllerId;
    public string $actionId;
    public string $url;
    public string $httpMethod;
    public ?SwaggerProperty $inputProperty;
    public ?SwaggerProperty $outputProperty;

    /**
     * @param SwaggerContext $context
     * @throws \ReflectionException
     * @throws \yii\base\Exception
     */
    public function extract(SwaggerContext $context)
    {
        $context = new SwaggerContext(['className' => $this->controllerClass, 'refsStorage' => $context->refsStorage]);

        $this->inputProperty = ClassMethodExtractor::extract($context->child(['isInput' => true, 'isInputForGetMethod' => $this->httpMethod === 'get']), $this->methodName);
        $this->outputProperty = ClassMethodExtractor::extract($context->child(['isInput' => false]), $this->methodName);
    }

    public function export()
    {
        $label = preg_replace('/^\/?api\/[^\/]+/', '', $this->url);
        $title = $this->inputProperty->description ?: $this->outputProperty->description ?: '';

        return [
            'summary' => $label,
            'description' => '<b>' . strtoupper($this->httpMethod) . ' /' . ltrim($this->url, '/') . '</b><br/>' . $title,
            'tags' => [$this->moduleId],
            'consumes' => [
                'application/json'
            ],
            'parameters' => !$this->inputProperty->isEmpty()
                ? [
                    [
                        'in' => 'body',
                        'name' => 'request',
                        'schema' => $this->inputProperty->export(),
                    ]
                ]
                : null,
            'responses' => [
                200 => [
                    'description' => 'Successful operation',
                    'content' => [
                        'application/json' => [
                            'schema' => !$this->outputProperty->isEmpty() ? $this->outputProperty->export() : null,
                        ],
                    ],
                ],
                400 => [
                    'description' => 'Validation errors',
                    'content' => [
                        'application/json' => [
                            'schema' => $this->httpMethod !== 'delete'
                                ? [
                                    'type' => 'object',
                                    'properties' => [
                                        'errors' => [
                                            'type' => 'object',
                                        ],
                                    ],
                                ]
                                : null,
                        ],
                    ],
                ],
            ],
        ];
    }
}
