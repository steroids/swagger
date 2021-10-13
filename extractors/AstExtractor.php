<?php

namespace steroids\swagger\extractors;

use PhpParser\Comment;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\ParserFactory;
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
        if (!$methodNode) {
            return [];
        }

        /** @var Variable[] $variables */
        $variables = [];

        /** @var Assign[] $assignNodes */
        $assignNodes = static::findAll($methodNode, function ($node) use ($method) {
            return $node instanceof Assign;
        });
        foreach ($assignNodes as $assignNode) {
            if ($assignNode->var instanceof Variable) {
                $variables[$assignNode->var->name] = $assignNode;
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
     * @param Variable[] $variables
     * @return SwaggerProperty|string[]|null
     * @throws Exception
     * @throws \ReflectionException
     */
    protected static function nodeToProperty(SwaggerContext $context, $node, array $variables)
    {
        // Array list
        if ($node instanceof Array_) {
            $property = new SwaggerProperty();
            $property->items = [];
            foreach ($node->items as $i => $item) {
                $subProperty = static::nodeToProperty($context->child(), $item, $variables);
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
            $parsedComment = static::parseNodeComments($context, $node);
            $property = static::nodeToProperty($context, $node->value, $variables);
            if ($parsedComment['property']) {
                $parsedComment['property']->name = $property->name;
                $property = $parsedComment['property'];
            }
            if ($property) {
                $property->description = implode("\n", $parsedComment['comments']) ?: $property->description;
                $property->example = implode("\n", $parsedComment['examples']) ?: $property->example;


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
            $context->addScopes(static::findScopes($node));
            return ClassExtractor::extract(
                $context->child(),
                $node->class->parts[0]
            );
        }

        // Active query one() and all() calls
        if ($node instanceof MethodCall && in_array($node->name->name, ['one', 'all'])) {
            $context->addScopes(static::findScopes($node));

            $staticCallNode = static::findFirst($node, function ($subNode) {
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
            $context->addScopes(static::findScopes($node));

            $property = ClassExtractor::extract(
                $context->child(),
                $node->class->parts[0],
            );
            $property->isArray = $node->name->name === 'findAll';
            return $property;
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

        // Variable
        if ($node instanceof Variable && isset($variables[$node->name])) {
            $parsedComment = static::parseNodeComments($context, $variables[$node->name]);
            if ($parsedComment['property']) {
                return $parsedComment['property'];
            }

            return static::nodeToProperty($context, $variables[$node->name]->expr, $variables);
        } elseif ($node instanceof MethodCall && $node->var instanceof Variable && isset($variables[$node->var->name])) {
            $parsedComment = static::parseNodeComments($context, $variables[$node->var->name]);
            if ($parsedComment['property']) {
                return $parsedComment['property'];
            }
        }


        return new SwaggerProperty();
    }

    protected static function parseNodeComments(SwaggerContext $context, $node)
    {
        $comments = [];
        $examples = [];
        $parsedLines = [];

        $context->addScopes(static::findScopes($node));

        $rawComments = $node->getDocComment() ? [$node->getDocComment()] : $node->getComments();
        foreach ($rawComments as $comment) {
            /** @var $comment Comment\Doc|Comment */
            foreach (explode("\n", $comment->getText()) as $line) {
                $line = preg_replace('/^\/\/|^\s*\/?\*+\/?|\*\/$/', '', $line);
                $line = trim($line);
                if ($line) {
                    $parsedLine = ExtractorHelper::parseCommentType($line);
                    if (in_array($parsedLine['tag'], ['var', 'type'])) {
                        $parsedLines[] = $parsedLine;


                    }

                    // Example
                    if ($parsedLine['example']) {
                        $examples[] = ExtractorHelper::fixJson($parsedLine['example']);
                    }

                    // Comment
                    if ($parsedLine['description']) {
                        $comments[] = $parsedLine['description'];

                        // Scopes
                        if (preg_match_all('/SCOPE_([A-Z0-9_]+)/', $parsedLine['description'], $scopeMatches)) {
                            $context->addScopes(array_map(fn($s) => strtolower($s), $scopeMatches[1]));
                        }
                    }
                }
            }
        }

        // Find property after scopes defined
        $property = null;
        foreach ($parsedLines as $parsedLine) {
            if ($parsedLine['type']) {
                $property = TypeExtractor::extract($context, $parsedLine['type']);
                if ($parsedLine['variable']) {
                    $property->name = $parsedLine['variable'];
                }
                if (!$property->isEmpty()) {
                    break;
                }
            }
        }

        if ($property) {
            $property->description = implode("\n", $comments);
            $property->example = implode("\n", $examples);
        }

        return [
            'comments' => $comments,
            'examples' => $examples,
            'property' => $property,
        ];

    }

    protected static function findScopes($astNode)
    {
        /** @var ClassConstFetch $node */
        $node = static::findFirst($astNode, function ($node) {
            return $node instanceof ClassConstFetch && $node->name instanceof Identifier && strpos($node->name->name, 'SCOPE_') === 0;
        });

        // TODO Find multiple
        $scope = $node ? mb_strtolower(preg_replace('/^SCOPE_/', '', $node->name->name)) : null;

        return $scope ? [$scope] : [];
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
            $stmts = array_merge($stmts, $astNode);
        }
        if (isset($astNode->stmts)) {
            $stmts = array_merge($stmts, $astNode->stmts);
        }
        if (isset($astNode->var)) {
            $stmts[] = $astNode->var;
        }
        if (isset($astNode->expr)) {
            $stmts[] = $astNode->expr;
        }
        if (isset($astNode->value)) {
            $stmts[] = $astNode->value;
        }
        if (isset($astNode->items)) {
            $stmts = array_merge($stmts, $astNode->items);
        }
        if (isset($astNode->args)) {
            $stmts = array_merge($stmts, $astNode->args);
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