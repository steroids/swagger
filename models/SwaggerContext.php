<?php

namespace steroids\swagger\models;

use yii\base\BaseObject;

class SwaggerContext extends BaseObject
{
    public bool $isInput = false;

    public bool $isInputForGetMethod = false;

    public ?string $className = null;

    public ?string $methodName = null;

    public ?string $attribute = null;

    public ?array $fields = null;

    public ?array $scopes = [];

    public ?string $comment = null;

    public ?SwaggerRefsStorage $refsStorage = null;

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
                'isInputForGetMethod' => $this->isInputForGetMethod,
                'refsStorage' => $this->refsStorage,
                'scopes' => $this->scopes,
                'className' => $this->className,
            ],
            $params,
            [
                'parent' => $this,
            ]
        ));
    }

    public function addScopes($scopes)
    {
        $this->scopes = array_unique(array_merge($this->scopes ?: [], $scopes ?: []));
    }
}
