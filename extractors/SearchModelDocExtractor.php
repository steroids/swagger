<?php

namespace steroids\swagger\extractors;

use steroids\core\base\BaseSchema;
use steroids\core\base\Model;
use steroids\core\base\SearchModel;
use yii\helpers\ArrayHelper;
use yii\helpers\StringHelper;

/**
 * @property-read string $definitionName
 */
class SearchModelDocExtractor extends FormModelDocExtractor
{
    public function run()
    {
        /** @var SearchModel $searchModel */
        $searchClassName = $this->className;
        $searchModel = new $searchClassName();

        /** @var Model|BaseSchema $modelClassName */
        $modelClassName = $searchModel->fieldsSchema() ?: $searchModel->createQuery()->modelClass;
        $modelObject = new $modelClassName();

        $required = [];
        $requestSchema = SwaggerTypeExtractor::getInstance()->extractModelByMeta($this->className);
        $requestSchema = $this->applyParamsToRequestSchema($requestSchema);

        $metaSchema = SwaggerTypeExtractor::getInstance()->extract($this->className, ['meta']);

        $refName = StringHelper::basename($this->className) . 'Item';
        $responseSchema = SwaggerTypeExtractor::getInstance()->extract($modelClassName, $modelObject->fields(), $refName);

        $responseProperties = [
            'meta' => array_merge(
                [
                    'description' => 'Additional meta information',
                    'type' => 'object',
                ],
                isset($metaSchema['properties']['meta']) ? $metaSchema['properties']['meta'] : []
            ),
            'total' => [
                'description' => 'Total items count',
                'type' => 'number',
            ],
            'items' => [
                'description' => 'Fined items',
                'type' => 'array',
                'items' => $responseSchema,
            ],
        ];

        $requestSchema['properties'] = array_merge($requestSchema['properties'], [
            'page' => [
                'description' => 'Page',
                'type' => 'number',
            ],
            'pageSize' => [
                'description' => 'Page size',
                'type' => 'number',
            ],
        ]);

        $this->swaggerJson->updatePath($this->url, $this->method, [
            'parameters' => [
                [
                    'in' => 'body',
                    'name' => 'request',
                    'schema' => array_merge($requestSchema, [
                        'required' => $required,
                    ]),
                ],
            ],
            'responses' => [
                200 => [
                    'description' => 'Successful operation',
                    'content' => [
                        'application/json' => [
                            'schema' => empty($responseSchema) ? null : [
                                'type' => 'object',
                                'properties' => $responseProperties,
                            ],
                        ],
                    ],
                ],
                400 => [
                    'description' => 'Validation errors',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => array_merge(
                                    $responseProperties,
                                    [
                                        'errors' => [
                                            'type' => 'object',
                                        ],
                                    ]
                                ),
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return string
     * @throws \ReflectionException
     */
    public function getDefinitionName()
    {
        return (new \ReflectionClass($this->className))->getShortName();
    }
}

