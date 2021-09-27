<?php

namespace steroids\swagger\models;

use yii\base\BaseObject;

class SwaggerContext extends BaseObject
{
    public bool $isInput = false;

    public ?string $className = null;

    public ?string $methodName = null;

    public ?string $attribute = null;

    public ?array $fields = null;

    public ?string $comment = null;

    /**
     * @var SwaggerContext
     */
    public $parent;

    /**
     * @param array $params
     * @return $this
     */
    public function child(array $params = [])
    {
        return new static(array_merge(
            [
                'isInput' => $this->isInput,
                'className' => $this->className,
            ],
            $params,
            [
                'parent' => $this,
            ]
        ));
    }
}
