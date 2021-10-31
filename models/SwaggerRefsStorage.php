<?php

namespace steroids\swagger\models;

use steroids\core\helpers\ClassFile;
use steroids\gii\helpers\GiiHelper;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;

class SwaggerRefsStorage extends BaseObject
{
    /**
     * @var SwaggerProperty[]
     */
    protected array $properties = [];

    protected array $classNames = [];

    protected array $counter = [];

    public function hasRef(string $name): bool
    {
        return !!ArrayHelper::getValue($this->properties, $name);
    }

    public function getAll()
    {
        return $this->properties;
    }

    public function getRef(string $name)
    {
        return ArrayHelper::getValue($this->properties, $name);
    }

    public function getRefRelativePath(string $name, $cwd = null)
    {
        // Get property class name
        $className = ArrayHelper::getValue($this->classNames, $name);
        if (!$className) {
            return null;
        }

        // Detect module id by class name
        $classFile = ClassFile::createByClass(ltrim($className, '\\'), '');
        if (!$classFile->moduleId) {
            return null;
        }

        $path = str_replace('.', DIRECTORY_SEPARATOR, $classFile->moduleId)
            . DIRECTORY_SEPARATOR . 'interfaces'
            . DIRECTORY_SEPARATOR . 'I' . $name . '.ts';

        if ($cwd) {
            // TODO Move gii method o core?
            $path = GiiHelper::getRelativePath($cwd, $path);
        }

        return $path;
    }

    public function getRefCount(string $name)
    {
        return ArrayHelper::getValue($this->counter, $name, 0);
    }

    public function increaseRefCount(string $name)
    {
        $this->counter[$name] = ArrayHelper::getValue($this->counter, $name, 0) + 1;
    }

    public function isInDefinitions(string $name): bool
    {
        return true;
        //return ArrayHelper::getValue($this->counter, $name, 0) > 1;
    }

    public function setRef(string $className, string $name, SwaggerProperty $value)
    {
        $this->properties[$name] = $value;
        $this->classNames[$name] = $className;
    }

    public function exportDefinitions()
    {
        $result = [];
        foreach ($this->properties as $name => $property) {
            if ($this->isInDefinitions($name)) {
                $result[$name] = $property->export(true);
            }
        }

        return !empty($result) ? $result : (object)[];
    }
}




