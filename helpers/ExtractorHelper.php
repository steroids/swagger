<?php

namespace steroids\swagger\helpers;

use Doctrine\Common\Annotations\TokenParser;
use steroids\swagger\models\SwaggerProperty;
use yii\base\Exception;
use yii\base\Model;
use yii\db\ActiveQuery;
use yii\db\ActiveQueryInterface;
use yii\helpers\ArrayHelper;

abstract class ExtractorHelper
{
    public static function normalizePrimitiveType($string)
    {
        $map = [
            'int' => 'integer',
            'bool' => 'boolean',
            'double' => 'float',
            'true' => 'boolean',
            'false' => 'boolean',
        ];
        return ArrayHelper::getValue($map, $string, $string);
    }

    public static function isPrimitiveType($string)
    {
        $list = [
            'string',
            'integer',
            'float',
            'boolean',
            'array',
            'resource',
            'null',
            'callable',
            'mixed',
            'void',
            'object',
        ];
        return in_array(static::normalizePrimitiveType($string), $list);
    }

    public static function fixJson($json)
    {
        if (!in_array(substr($json, 0, 1), ['[', '{'])) {
            return $json;
        }

        $newJSON = '';
        $jsonLength = strlen($json);
        for ($i = 0; $i < $jsonLength; $i++) {
            if ($json[$i] == '"' || $json[$i] == "'") {
                $nextQuote = strpos($json, $json[$i], $i + 1);
                $quoteContent = substr($json, $i + 1, $nextQuote - $i - 1);
                $newJSON .= '"' . str_replace('"', "'", $quoteContent) . '"';
                $i = $nextQuote;
            } else {
                $newJSON .= $json[$i];
            }
        }
        return $newJSON;
    }

    /**
     * @param $modelClassName
     * @param $name
     * @return ActiveQuery
     */
    public static function safeGetRelation($modelClassName, $name)
    {
        $model = static::safeCreateInstance($modelClassName);

        if (!$model || !is_string($name)) {
            return null;
        }

        try {
            $methodInfo = new \ReflectionMethod($model, 'get' . ucfirst($name));
        } catch (\ReflectionException $e) {
            return null;
        }

        foreach ($methodInfo->getParameters() as $parameter) {
            if (!$parameter->isOptional()) {
                return null;
            }
        }

        if (!method_exists($model, 'getRelation')) {
            return null;
        }

        return $model->getRelation($name, false);
    }

    public static function parseCommentType($line)
    {
        $line = trim($line);
        $line = trim($line, '*/');

        $result = [
            'tag' => null,
            'type' => null,
            'variable' => null,
            'description' => null,
        ];
        $isFullType = false;

        foreach (explode(' ', $line) as $str) {
            $str = trim($str);

            // Tag
            if (strpos($str, '@') === 0) {
                $result['tag'] = substr($str, 1);
                continue;
            }

            // Type
            if (in_array($result['tag'], ['var', 'type'])) {
                if (!$result['type']) {
                    $result['type'] = $str;
                    $isFullType = strpos($str, '<') === false;
                    continue;
                } elseif (!$isFullType) {
                    $result['type'] .= ' ' . $str;
                    $isFullType = true;
                    continue;
                }
            }

            // Variable
            if (strpos($str, '$') === 0) {
                $result['variable'] = substr($str, 1);
                continue;
            }

            // Other: comment
            $result['description'] .= ($result['description'] && $str ? ' ' : '') . $str;
        }

        return $result;
    }

    protected static function safeCreateInstance($modelClass)
    {
        if (!class_exists($modelClass)) {
            return null;
        }
        $modelClassInfo = new \ReflectionClass($modelClass);
        if ($modelClassInfo->isAbstract()) {
            return null;
        }

        $model = null;
        try {
            $model = new $modelClass();
        } catch (\Exception $e) {
        }
        return $model;
    }
}