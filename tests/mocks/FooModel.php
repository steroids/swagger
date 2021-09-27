<?php

namespace steroids\swagger\tests\mocks;

use steroids\core\base\Model;

/**
 * Class FooModel
 * @package tests\mocks\FooModel
 * @property-read int $id Primary key
 * @property-read int $title Role
 */
class FooModel extends Model
{
    /**
     * @inheritDoc
     */
    public static function meta()
    {
        return [
            'id' => [
                'appType' => 'primaryKey',
                'label' => 'Primary key from meta',
            ],
            'title' => [
                'label' => 'Role from meta',
            ],
        ];
    }

    public function fields()
    {
        return [
            'id',
            'name',
        ];
    }

    /**
     * @inheritDoc
     */
    public function rules()
    {
        return [
            ['id', 'integer'],
            ['title', 'string'],
            ['title', 'required'],
        ];
    }

    public function attributes()
    {
        // simulate database
        return ['id', 'title'];
    }

    /**
     * Foo name
     * @return string
     */
    public function getName()
    {
        return '';
    }
}
