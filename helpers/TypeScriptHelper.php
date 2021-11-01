<?php

namespace steroids\swagger\helpers;

use steroids\gii\helpers\GiiHelper;
use steroids\swagger\models\SwaggerAction;
use steroids\swagger\models\SwaggerProperty;
use steroids\swagger\models\SwaggerRefsStorage;
use yii\helpers\ArrayHelper;
use yii\helpers\Inflector;
use yii\helpers\Json;
use yii\helpers\StringHelper;

abstract class TypeScriptHelper
{
    /**
     * @param SwaggerProperty[] $properties
     * @param string $relativePath
     * @param SwaggerRefsStorage $refsStorage
     * @return string
     */
    public static function generateInterfaces(array $properties, string $relativePath, SwaggerRefsStorage $refsStorage, $imports = [], $isExportDefault = false)
    {
        $interfaces = [];
        foreach ($properties as $name => $property) {
            if ($refsStorage->getRef($name) && !$isExportDefault) {
                $importPath = $refsStorage->getRefRelativePath($name, $relativePath);
                $importPath = preg_replace('/\.(ts|tsx|js|jsx)$/', '', $importPath);
                $imports[] = "import I$name from '$importPath';";
            } else {
                $usedRefs = [];
                $interfaces[] = 'export ' . ($isExportDefault ? 'default ' : '')
                    . "interface I$name " . $property->exportTsType('', true, $usedRefs) . "\n";
                foreach ($usedRefs as $refName) {
                    $importPath = $refsStorage->getRefRelativePath($refName, $relativePath);
                    $importPath = preg_replace('/\.(ts|tsx|js|jsx)$/', '', $importPath);

                    $interfaceName = StringHelper::basename($importPath);
                    $imports[] = "import $interfaceName from '$importPath';";
                }
            }
        }
        $imports = array_unique($imports);

        return implode(
            "\n",
            [
                ...$imports,
                ...(!empty($imports) ? [''] : []),
                ...$interfaces,
            ]
        );
    }


    /**
     * @param SwaggerAction[] $actions
     * @param $relativePath
     * @param SwaggerRefsStorage $refsStorage
     * @return string
     */
    public static function generateApi($actions, $relativePath, $refsStorage)
    {
        $properties = [];

        foreach ($actions as $action) {
            if ($action->inputRefName) {
                $properties[$action->inputRefName] = $action->inputProperty;
            }
            if ($action->outputRefName) {
                $properties[$action->outputRefName] = $action->outputProperty;
            }
        }

        // TODO Move gii method o core?
        $utilsPath = GiiHelper::getRelativePath($relativePath, 'utils');

        return implode(
            "\n",
            [
                static::generateInterfaces($properties, $relativePath, $refsStorage, [
                    "import {createMethod} from '$utilsPath';",
                ]),
                ...array_map(
                    function ($action) {
                        $input = $action->inputTsType && $action->inputTsType !== 'any' ? 'I' . $action->inputTsType : 'null';
                        $output = $action->outputTsType && $action->outputTsType !== 'any' ? 'I' . $action->outputTsType : 'null';
                        $method = lcfirst(Inflector::id2camel($action->actionId));
                        return "export const $method = createMethod<$input, $output>({\n"
                            . "    method: '$action->httpMethod',\n"
                            . "    url: '$action->url',\n"
                            . "});\n";
                    },
                    $actions,
                ),
            ]
        );
    }

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