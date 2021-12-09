<?php

namespace steroids\swagger\models;

use steroids\core\interfaces\ISwaggerProperty;
use steroids\swagger\helpers\ExtractorHelper;
use yii\base\BaseObject;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\helpers\StringHelper;

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

    public function clone()
    {
        return new static([
            'name' => $this->name,
            'refName' => $this->refName,
            'refsStorage' => $this->refsStorage,
            'phpType' => $this->phpType,
            'format' => $this->format,
            'isRequired' => $this->isRequired,
            'enum' => $this->enum,
            'description' => $this->description,
            'example' => $this->example,
            'isArray' => $this->isArray,
            'arrayDepth' => $this->arrayDepth,
            'isPrimitive' => $this->isPrimitive,
            'phpdoc' => $this->phpdoc,
            'items' => $this->items,
        ]);
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
                    foreach (explode("\n", $this->phpdoc) as $line) {
                        $parsedLine = ExtractorHelper::parseCommentType($line);
                        if ($parsedLine['description']) {
                            $this->description = $parsedLine['description'];
                        }
                    }
                }
            }

            // Support array/object examples
            if (!empty($schema['example']) && in_array(substr($schema['example'], 0, 1), ['[', '{'])) {
                $schema['example'] = Json::decode(ExtractorHelper::fixJson($schema['example']));
            }

            $properties = null;
            $required = null; // TODO
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

        if ($this->isArray && !$skipRefs) {
            for ($i = 0; $i < $this->arrayDepth; $i++) {
                $schema = [
                    'type' => 'array',
                    'items' => $schema,
                ];
            }
        }

        return $schema;
    }

    public function exportTsType($indent = '', $skipRefs = false, &$usedRefs = [])
    {
        $type = 'any';

        if (!$skipRefs && $this->refName) {
            $type = 'I' . $this->refName;
            $usedRefs[] = $this->refName;
        } elseif (!empty($this->items)) {
            return implode(
                "\n",
                [
                    '{',
                    ...array_map(
                        function ($item) use ($indent, &$usedRefs) {
                            $lines = [''];
                            if ($item->description || $item->example) {
                                $lines[] = '/**';
                                if ($item->description) {
                                    $lines[] = ' * ' . $item->description;
                                }
                                if ($item->example) {
                                    $lines[] = ' * @example ' . ExtractorHelper::fixJson($item->example);
                                }
                                $lines[] = ' */';
                            }
                            $lines[] = $item->name . '?: ' . $item->exportTsType($indent . '    ', false, $usedRefs) . ',';

                            return implode(
                                "\n",
                                array_map(
                                    fn($line) => $line ? $indent . '    ' . $line : '',
                                    $lines,
                                ),
                            );
                        },
                        $this->items,
                    ),
                    $indent . '}',
                ]
            );
        } elseif ($this->isPrimitive && isset(self::SINGLE_MAPPING[$this->phpType]) && $this->phpType !== 'array') {
            $type = self::SINGLE_MAPPING[$this->phpType];
        }

        // TS linter recommendation - Don't use `object` as a type. The `object` type is currently hard to use.
        if ($type === 'object') {
            $type = 'Record<string, unknown>';
        }

        // todo enum
//        ...array_map(
//        fn(string $value) => str_replace('"', "'", Json::encode($value)),
//        ArrayHelper::getValue($property, 'enum', [])
//    ),

        if ($this->isArray) {
            $type .= '[]';
        }

        return $type;
    }
}
