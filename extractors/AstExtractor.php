<?php

namespace steroids\swagger\extractors;

use PhpParser\Comment;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\ParserFactory;
use steroids\swagger\extractors\ClassExtractor;
use steroids\swagger\extractors\TypeExtractor;
use steroids\swagger\helpers\ExtractorHelper;
use steroids\swagger\models\SwaggerContext;
use steroids\swagger\models\SwaggerProperty;
use yii\base\Exception;

abstract class AstExtractor
{
    /**
     * @param SwaggerContext $context
     * @param string $method
     * @return array|SwaggerProperty[]
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function extract(SwaggerContext $context, string $method)
    {
        $classInfo = new \ReflectionClass($context->className);
        $classCode = file_get_contents($classInfo->getFileName());

        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        $ast = $parser->parse($classCode);

        $classNode = static::findFirst($ast, function ($node) use ($classInfo) {
            return $node instanceof Class_ && $node->name->name === $classInfo->getShortName();
        });

        /** @var ClassMethod $methodNode */
        $methodNode = static::findFirst($classNode, function ($node) use ($method) {
            return $node instanceof ClassMethod && $node->name->name === $method;
        });

        /** @var Assign[] $assignNodes */
        $variables = [];
        $assignNodes = static::findAll($methodNode, function ($node) use ($method) {
            return $node instanceof Assign;
        });
        foreach ($assignNodes as $assignNode) {
            if ($assignNode->var instanceof Variable) {
                $variables[$assignNode->var->name] = $assignNode->expr;
            }
        }


        /** @var SwaggerProperty[] $properties */
        $properties = [];
        foreach ($methodNode->stmts as $stmt) {
            if ($stmt instanceof Return_) {
                $properties[] = static::nodeToProperty($context, $stmt->expr, $variables);
            }
        }

        return array_filter($properties);
    }

    /**
     * @param SwaggerContext $context
     * @param $node
     * @param $variables
     * @return SwaggerProperty|string[]|null
     * @throws Exception
     * @throws \ReflectionException
     */
    protected static function nodeToProperty(SwaggerContext $context, $node, $variables)
    {
        // Array list
        if ($node instanceof Array_) {
            $property = new SwaggerProperty();
            $property->items = [];
            foreach ($node->items as $i => $item) {
                $subProperty = static::nodeToProperty($context, $item, $variables);
                if ($subProperty) {
                    if (!$subProperty->name) {
                        $subProperty->name = (string)$i;
                    }
                    $property->items[] = $subProperty;
                }
            }
            return $property;
        }

        // Array item
        if ($node instanceof ArrayItem) {
            $property = static::nodeToProperty($context, $node->value, $variables);
            if ($property) {
                $rawComments = $node->getDocComment()
                    ? [$node->getDocComment()]
                    : $node->getComments();
                $comments = [];
                $examples = [];
                foreach ($rawComments as $comment) {
                    /** @var $comment Comment\Doc|Comment */
                    foreach (explode("\n", $comment->getText()) as $line) {
                        $line = preg_replace('/^\/\/|^\s*\/?\*+\/?|\*\/$/', '', $line);
                        $line = trim($line);
                        if ($line) {
                            if (preg_match('/^@(var|type) *([^ ]+) *(.*)/', $line, $match)) {
                                $tmpProperty = TypeExtractor::extract($context, $match[2]);
                                if ($tmpProperty && !$tmpProperty->isPrimitive && $tmpProperty->phpType) {
                                    $tmpProperty = ClassExtractor::extract($context, $tmpProperty->phpType);
                                }
                                if ($tmpProperty) {
                                    $tmpProperty->name = $property->name;
                                    $property = $tmpProperty;
                                }
                                if (!empty($match[3])) {
                                    $comments[] = $match[3];
                                }
                            } elseif (preg_match('/^@example *(.+)/', $line, $match)) {
                                $examples[] = ExtractorHelper::fixJson($match[1]);
                            } else {
                                $comments[] = $line;
                            }
                        }
                    }
                }
                $property->description = implode("\n", $comments);
                $property->example = implode("\n", $examples);

                if ($node->key instanceof String_) {
                    $property->name = $node->key->value;
                }
                if ($node->key instanceof LNumber) {
                    $property->name = (string)$node->key->value;
                }
            }
            return $property;
        }

        // Create instance (new Foo())
        if ($node instanceof New_ && isset($node->class->parts) && count($node->class->parts) === 1) {
            return ClassExtractor::extract(
                $context->child(),
                $node->class->parts[0]
            );
        }

        // Active query one() and all() calls
        if ($node instanceof MethodCall && in_array($node->name->name, ['one', 'all'])) {
            $staticCallNode = static::findFirst($node, function($subNode) {
                return $subNode instanceof StaticCall && $subNode->name->name === 'find';
            });
            if ($staticCallNode && count($staticCallNode->class->parts) === 1) {
                $property = ClassExtractor::extract(
                    $context->child(),
                    $staticCallNode->class->parts[0],
                );
                $property->isArray = $node->name->name === 'all';
                return $property;
            }
        }

        // Active query findOne() and findAll() calls
        if ($node instanceof StaticCall && in_array($node->name->name, ['findOne', 'findAll']) && count($node->class->parts) === 1) {
            $property = ClassExtractor::extract(
                $context->child(),
                $node->class->parts[0],
            );
            $property->isArray = $node->name->name === 'findAll';
            return $property;
        }

        // Variable
        if ($node instanceof Variable && isset($variables[$node->name])) {
            return static::nodeToProperty($context, $variables[$node->name], $variables);
        }

        // Primitive types
        $primitives = [
            ConstFetch::class => 'boolean',
            String_::class => 'boolean',
            LNumber::class => 'integer',
            DNumber::class => 'float',
        ];
        foreach ($primitives as $primitiveClass => $primitivesType) {
            if ($node instanceof $primitiveClass) {
                return new SwaggerProperty([
                    'isPrimitive' => true,
                    'phpType' => $primitivesType,
                ]);
            }
        }

        return new SwaggerProperty([
            'isPrimitive' => true,
            'phpType' => 'string',
        ]);
    }

    protected static function findFirst($astNode, $callback)
    {
        $result = static::findAll($astNode, $callback, true);
        return count($result) === 1 ? $result[0] : null;
    }

    protected static function findAll($astNode, $callback, $onlyFirst = false)
    {
        $stmts = [];
        if (is_array($astNode)) {
            $stmts = $astNode;
        } elseif (isset($astNode->stmts)) {
            $stmts = $astNode->stmts;
        } elseif (isset($astNode->var)) {
            $stmts = [$astNode->var];
        } elseif (isset($astNode->expr)) {
            $stmts = [$astNode->expr];
        }

        $result = [];
        foreach ($stmts as $stmt) {
            if (call_user_func($callback, $stmt)) {
                $result[] = $stmt;

                if ($onlyFirst) {
                    break;
                }
            }
            $result = array_merge($result, static::findAll($stmt, $callback, $onlyFirst));
        }
        return $result;
    }
}