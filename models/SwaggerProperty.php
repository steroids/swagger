<?php

namespace steroids\swagger\models;

use steroids\core\interfaces\ISwaggerProperty;
use steroids\swagger\helpers\ExtractorHelper;
use yii\base\BaseObject;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

class SwaggerProperty extends BaseObject implements ISwaggerProperty
{
    const DEFAULT_TYPE = 'string';

    const SINGLE_MAPPING = [
        'string' => 'string',
        'integer' => 'number',
        'float' => 'number',
        'boolean' => 'boolean',
        'array' => 'array',
        'resource' => 'object',
        'null' => 'object',
        'callable' => 'object',
        'mixed' => 'object',
        'void' => 'object',
        'object' => 'object',
    ];

    /**
     * Attribute or property name
     * @var string|null
     */
    public ?string $name = null;

    /**
     * Reference name for entity (class/model) only
     * @var string|null
     */
    public ?string $refName = null;

    /**
     * @var SwaggerRefsStorage|null
     */
    public ?SwaggerRefsStorage $refsStorage = null;

    /**
     * Source php type - primitive or class name
     * @var string|null
     */
    public ?string $phpType = null;

    /**
     * Swagger format
     * @var string|null
     */
    public ?string $format = null;

    /**
     * Required flag
     * @var bool
     */
    public ?bool $isRequired = false;

    /**
     * Swagger enum keys
     * @var string[]
     */
    public ?array $enum = null;

    /**
     * Property description
     * @var string|null
     */
    public ?string $description = null;

    /**
     * Example of property value
     * @var string|null
     */
    public ?string $example = null;

    /**
     * Is array of type?
     * @var bool|null
     */
    public ?bool $isArray = false;

    /**
     * Depth array of arrays
     * @var int
     */
    public int $arrayDepth = 1;

    /**
     * Is primitive type?
     * @var bool|null
     */
    public ?bool $isPrimitive = false;

    /**
     * Source phpdoc
     * @var string|null
     */
    public ?string $phpdoc = null;

    /**
     * @var static[]|null
     */
    public ?array $items = null;

    public function setPhpType(string $value)
    {
        $this->phpType = $value;
    }

    public function setFormat(string $value)
    {
        $this->format = $value;
    }

    public function setIsArray(bool $value)
    {
        $this->isArray = $value;
    }

    public function setEnum(array $keys)
    {
        $this->enum = $keys;
    }

    public function isEmpty()
    {
        return !$this->phpType && !$this->items;
    }

    public function export($skipRefs = false)
    {
        if (!$skipRefs && $this->refName && $this->refsStorage && $this->refsStorage->isInDefinitions($this->refName)) {
            $schema = [
                '$ref' => '#/definitions/' . $this->refName,
            ];
        } else {
            // Get description and example from phpdoc
            if ($this->phpdoc) {
                // Class description
                if (preg_match('/@property(-read)? +[^ ]+ \$[^ ]+ (.*)/u', $this->phpdoc, $matches)) {
                    if (!empty($matches[2])) {
                        $this->description = $matches[2];
                    }
                } else {
                    // Find first comment line as description
                    foreach (explode("\n", $this->phpdoc) as $line) {
                        $line = preg_replace('/^\s*\\/?\*+/', '', $line);
                        $line = trim($line);
                        if ($line && $line !== '/' && substr($line, 0, 1) !== '@') {
                            $this->description = $line;
                            break;
                        }
                    }

                    if (!$this->example && preg_match('/@example (.*)/u', $this->phpdoc, $matches)) {
                        $this->example = trim($matches[1]);
                    }

                    // Get description from type param
                    $parsedLine = ExtractorHelper::parseCommentType($this->phpdoc);
                    if ($parsedLine['description']) {
                        $this->description = $parsedLine['description'];
                    }
                }
            }

            // Support array/object examples
            if (!empty($schema['example']) && in_array(substr($schema['example'], 0, 1), ['[', '{'])) {
                $schema['example'] = Json::decode(ExtractorHelper::fixJson($schema['example']));
            }

            $properties = null;
            $required = null;
            if (!$this->isPrimitive && $this->items) {
                $properties = [];
                $required = [];
                foreach ($this->items as $item) {
                    if ($item->name === null) {
                        throw new Exception('Not found name for item: ...');
                    }
                    $properties[$item->name] = $item->export();
                    if ($item->isRequired) {
                        $required[] = $item->name;
                    }
                }
            }

            $schema = [
                'type' => !$this->isPrimitive && $this->items
                    ? 'object'
                    : (ArrayHelper::getValue(self::SINGLE_MAPPING, $this->phpType) ?: self::DEFAULT_TYPE),
            ];

            if ($this->description) {
                $schema['description'] = $this->description;
            }
            if ($this->example) {
                $schema['example'] = $this->example;
            }
            if ($this->format) {
                $schema['format'] = $this->format;
            }
            if (!empty($properties)) {
                $schema['properties'] = $properties;
            }
        }

        if ($this->isArray) {
            for ($i = 0; $i < $this->arrayDepth; $i++) {
                $schema = [
                    'type' => 'array',
                    'items' => $schema,
                ];
            }
        }

        return $schema;
    }
}




