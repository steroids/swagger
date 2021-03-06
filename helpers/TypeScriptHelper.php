<?php

namespace steroids\swagger\helpers;

use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\helpers\StringHelper;

abstract class TypeScriptHelper
{
    /**
     * @param array $json
     * @return string
     * @throws \Exception
     */
    public static function jsonToTypes(array $json)
    {
        $result = [];
        foreach (ArrayHelper::getValue($json, 'definitions', []) as $name => $definition) {
            $result[] = "export interface I$name " . static::property($definition, 1);
        }
        return implode("\n\n", $result);
    }

    /**
     * @param string $text
     * @param int $level
     * @return string
     */
    protected static function jsdoc(string $text, $level = 0)
    {
        return static::indent($level) . implode("\n" . static::indent($level), [
            '/**',
            ...array_map(fn(string $line) => ' * ' . $line, explode("\n", $text)),
            ' */',
        ]) . "\n";
    }

    /**
     * @param array $property
     * @param int $level
     * @return string
     * @throws \Exception
     */
    protected static function property(array $property, $level = 0)
    {
        $ref = ArrayHelper::getValue($property, '$ref');
        if ($ref) {
            return 'I' . StringHelper::basename($ref);
        }

        switch (ArrayHelper::getValue($property, 'type')) {
            case 'object':
                $result = [];
                foreach ($property['properties'] as $name => $property) {
                    $item = '';
                    $doc = array_filter([
                        ArrayHelper::getValue($property, 'description'),
                        ArrayHelper::getValue($property, 'example')
                            ? '@example ' . ArrayHelper::getValue($property, 'example')
                            : null,
                    ]);
                    if (!empty($doc)) {
                        $item .= static::jsdoc(implode("\n", $doc), $level);
                    }

                    $item .= static::indent($level) . $name . '?: ' . static::property($property, $level + 1) . "\n";
                    $result[] = $item;
                }
                return "{\n\n" . implode("\n", $result) . static::indent($level - 1) . '}';

            case 'array':
                if (!empty($property['items'])) {
                    return trim(static::property($property['items'], $level)) . '[]';
                } else {
                    return 'array';
                }

            case 'number':
            case 'boolean':
            case 'string':
                return implode(' | ', [
                    ...array_map(
                        fn(string $value) => str_replace('"', "'", Json::encode($value)),
                        ArrayHelper::getValue($property, 'enum', [])
                    ),
                    $property['type']
                ]);
        }

        throw new \Exception('Unsupported type: ' . Json::encode($property));
    }

    protected static function indent($level)
    {
        return str_repeat('    ', $level);
    }
}