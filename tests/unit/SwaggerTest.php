<?php

namespace steroids\swagger\tests\unit;

use PHPUnit\Framework\TestCase;
use steroids\swagger\extractors\AstExtractor;
use steroids\swagger\extractors\ClassMethodExtractor;
use steroids\swagger\extractors\ModelExtractor;
use steroids\swagger\extractors\ObjectExtractor;
use steroids\swagger\models\SwaggerContext;
use steroids\swagger\tests\mocks\AstController;
use steroids\swagger\tests\mocks\BarObject;
use steroids\swagger\tests\mocks\FirstController;
use steroids\swagger\tests\mocks\FooModel;
use steroids\swagger\tests\mocks\ScopeController;
use steroids\swagger\tests\mocks\ScopeControllerv;
use yii\helpers\ArrayHelper;

class SwaggerTest extends TestCase
{
    public function testObject()
    {
        $property = ObjectExtractor::extract(new SwaggerContext(['className' => BarObject::class]));
        $this->assertFalse($property->isPrimitive);
        $this->assertEquals(1, count($property->items));
        $this->assertEquals('count', $property->items[0]->name);
        $this->assertEquals('Count items', $property->items[0]->export()['description']);
        $this->assertEquals('number', $property->items[0]->export()['type']);
    }

    public function testModel()
    {
        $property = ModelExtractor::extract(new SwaggerContext(['className' => FooModel::class]));
        $this->assertFalse($property->isPrimitive);
        $this->assertEquals(2, count($property->items));
        $this->assertEquals('id', $property->items[0]->name);
        $this->assertEquals('number', $property->items[0]->export()['type']);
        $this->assertEquals('Primary key', $property->items[0]->export()['description']);
        $this->assertEquals('name', $property->items[1]->name);
        $this->assertEquals('string', $property->items[1]->export()['type']);
        $this->assertEquals('Foo name', $property->items[1]->export()['description']);
        $this->assertEquals('object', $property->export()['type']);
    }

    public function testMethodPhpdoc()
    {
        $context = new SwaggerContext(['className' => FirstController::class]);

        $property = ClassMethodExtractor::extract($context->child(['isInput' => true]), 'actionIndex');
        $this->assertEquals('id,title', implode(',', ArrayHelper::getColumn($property->items, 'name')));

        $property = ClassMethodExtractor::extract($context->child(['isInput' => false]), 'actionIndex');
        $this->assertEquals('id,name', implode(',', ArrayHelper::getColumn($property->items, 'name')));
    }

    public function testMethodAst()
    {
        $properties = AstExtractor::extract(new SwaggerContext(['className' => FirstController::class]), 'actionGetDetail');
        $this->assertEquals(
            'foo,flag,6',
            implode(',', ArrayHelper::getColumn($properties[0]->items, 'name'))
        );
        $this->assertEquals(
            'Foo property,Start comment before flag,Multiline comment for "six" value_Second line here...',
            str_replace("\n", '_', implode(',', ArrayHelper::getColumn($properties[0]->items, 'description')))
        );
        $this->assertEquals(
            ',,{"foo": 99}',
            implode(',', ArrayHelper::getColumn($properties[0]->items, 'example'))
        );
        $this->assertEquals(
            'model,model2,model3,confirm,confirms,confirmShort,confirmsShort,title,count,total,strings',
            implode(',', ArrayHelper::getColumn($properties[0]->items[0]->items, 'name'))
        );
        $this->assertEquals(
            'Foo model,,Model3 custom type,Confirm model with id 1,Confirms list,,,,,,',
            implode(',', ArrayHelper::getColumn($properties[0]->items[0]->items, 'description'))
        );
        $this->assertEquals(
            'id,name', // model
            implode(',', ArrayHelper::getColumn($properties[0]->items[0]->items[0]->items, 'name'))
        );
        $this->assertEquals(
            'id,name', // model2
            implode(',', ArrayHelper::getColumn($properties[0]->items[0]->items[1]->items, 'name'))
        );
        $this->assertEquals(
            'count', // model3
            implode(',', ArrayHelper::getColumn($properties[0]->items[0]->items[2]->items, 'name'))
        );
    }

    public function testScope()
    {
        $properties = AstExtractor::extract(new SwaggerContext(['className' => AstController::class]), 'actionScope');

        $this->assertEquals('foo', $properties[0]->items[0]->name);
        $this->assertEquals('Model with scope: SCOPE_DETAIL', $properties[0]->items[0]->description);
        $this->assertEquals(
            'id,name,role',
            implode(',', ArrayHelper::getColumn($properties[0]->items[0]->items, 'name'))
        );

        $this->assertEquals('bar', $properties[0]->items[1]->name);
        $this->assertEquals('Bar with scope: SCOPE_DEFAULT', $properties[0]->items[1]->description);
        $this->assertEquals(
            'id,name',
            implode(',', ArrayHelper::getColumn($properties[0]->items[1]->items, 'name'))
        );
    }

    public function testGenericType()
    {
        $properties = AstExtractor::extract(new SwaggerContext(['className' => AstController::class]), 'actionGenericType');

        $this->assertEquals('items', $properties[0]->items[0]->name);
        $this->assertEquals(true, $properties[0]->items[0]->isArray);
        $this->assertEquals(2, $properties[0]->items[0]->arrayDepth);
        $this->assertEquals('{"type":"object","properties":{"items":{"type":"array","items":{"type":"array","items":{"type":"object","properties":{"count":{"type":"number","description":"Count items"}}}}}}}', json_encode($properties[0]->export()));
    }
}
