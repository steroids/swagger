<?php

namespace steroids\swagger\models;

use yii\base\BaseObject;
use yii\helpers\ArrayHelper;

class SwaggerRefsStorage extends BaseObject
{
    /**
     * @var SwaggerProperty[]
     */
    protected array $properties = [];

    protected array $counter = [];

    public function hasRef(string $name): bool
    {
        return !!ArrayHelper::getValue($this->properties, $name);
    }

    public function getRef(string $name)
    {
        return ArrayHelper::getValue($this->properties, $name);
    }

    public function isInDefinitions(string $name): bool
    {
        return true;
        //return ArrayHelper::getValue($this->counter, $name, 0) > 1;
    }

    public function setRef(string $name, SwaggerProperty $value)
    {
        $this->counter[$name] = ArrayHelper::getValue($this->counter, $name, 0) + 1;
        $this->properties[$name] = $value;
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




