<?php

declare(strict_types=1);

namespace Rector\Core\PhpParser\Node;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeFinder;
use Rector\Core\Exception\ShouldNotHappenException;
use Rector\Core\PhpParser\Printer\BetterStandardPrinter;
use Rector\NodeNameResolver\NodeNameResolver;
use Rector\NodeTypeResolver\Node\AttributeKey;

/**
 * @see \Rector\Core\Tests\PhpParser\Node\BetterNodeFinder\BetterNodeFinderTest
 */
final class BetterNodeFinder
{
    /**
     * @var NodeFinder
     */
    private $nodeFinder;

    /**
     * @var NodeNameResolver
     */
    private $nodeNameResolver;

    /**
     * @var BetterStandardPrinter
     */
    private $betterStandardPrinter;

    public function __construct(
        BetterStandardPrinter $betterStandardPrinter,
        NodeFinder $nodeFinder,
        NodeNameResolver $nodeNameResolver
    ) {
        $this->nodeFinder = $nodeFinder;
        $this->nodeNameResolver = $nodeNameResolver;
        $this->betterStandardPrinter = $betterStandardPrinter;
    }

    /**
     * @param class-string|class-string[] $type
     */
    public function findFirstParentInstanceOf(Node $node, $type): ?Node
    {
        $types = ! is_array($type) ? [$type] : $type;

        foreach ($types as $singleType) {
            $this->ensureIsNodeClass($singleType, __METHOD__, 1);
        }

        /** @var Node|null $parentNode */
        $parentNode = $node->getAttribute(AttributeKey::PARENT_NODE);

        if ($parentNode === null) {
            return null;
        }

        do {
            if ($this->isTypes($parentNode, $types)) {
                return $parentNode;
            }

            if ($parentNode === null) {
                return null;
            }
        } while ($parentNode = $parentNode->getAttribute(AttributeKey::PARENT_NODE));

        return null;
    }

    public function findFirstAncestorInstanceOf(Node $node, string $type): ?Node
    {
        $currentNode = $node->getAttribute(AttributeKey::PARENT_NODE);
        while ($currentNode !== null) {
            if ($currentNode instanceof $type) {
                return $currentNode;
            }

            $currentNode = $currentNode->getAttribute(AttributeKey::PARENT_NODE);
        }

        return null;
    }

    /**
     * @param string[] $types
     */
    public function findFirstAncestorInstancesOf(Node $node, array $types): ?Node
    {
        $currentNode = $node->getAttribute(AttributeKey::PARENT_NODE);
        while ($currentNode !== null) {
            foreach ($types as $type) {
                if (is_a($currentNode, $type, true)) {
                    return $currentNode;
                }
            }

            $currentNode = $currentNode->getAttribute(AttributeKey::PARENT_NODE);
        }

        return null;
    }

    /**
     * @param Node|Node[]|Stmt[] $nodes
     * @return Node[]
     */
    public function findInstanceOf($nodes, string $type): array
    {
        $this->ensureIsNodeClass($type, __METHOD__, 1);

        return $this->nodeFinder->findInstanceOf($nodes, $type);
    }

    /**
     * @param Node|Node[] $nodes
     */
    public function findFirstInstanceOf($nodes, string $type): ?Node
    {
        $this->ensureIsNodeClass($type, __METHOD__, 1);

        return $this->nodeFinder->findFirstInstanceOf($nodes, $type);
    }

    /**
     * @param Node|Node[] $nodes
     */
    public function hasInstanceOfName($nodes, string $type, string $name): bool
    {
        $this->ensureIsNodeClass($type, __METHOD__, 1);

        return (bool) $this->findInstanceOfName($nodes, $type, $name);
    }

    /**
     * @param Node|Node[] $nodes
     */
    public function hasVariableOfName($nodes, string $name): bool
    {
        return (bool) $this->findVariableOfName($nodes, $name);
    }

    /**
     * @param Node|Node[] $nodes
     */
    public function findVariableOfName($nodes, string $name): ?Node
    {
        return $this->findInstanceOfName($nodes, Variable::class, $name);
    }

    /**
     * @param Node|Node[] $nodes
     * @param class-string[] $types
     */
    public function hasInstancesOf($nodes, array $types): bool
    {
        foreach ($types as $type) {
            $this->ensureIsNodeClass($type, __METHOD__, 1);
            $nodeFinderFindFirstInstanceOf = $this->nodeFinder->findFirstInstanceOf($nodes, $type);

            if ($nodeFinderFindFirstInstanceOf === null) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param Node|Node[] $nodes
     */
    public function findLastInstanceOf($nodes, string $type): ?Node
    {
        $this->ensureIsNodeClass($type, __METHOD__, 1);

        $foundInstances = $this->nodeFinder->findInstanceOf($nodes, $type);
        if ($foundInstances === []) {
            return null;
        }

        return array_pop($foundInstances);
    }

    /**
     * @param Node|Node[] $nodes
     * @return Node[]
     */
    public function find($nodes, callable $filter): array
    {
        return $this->nodeFinder->find($nodes, $filter);
    }

    /**
     * Excludes anonymous classes!
     *
     * @param Node[] $nodes
     * @return ClassLike[]
     */
    public function findClassLikes(array $nodes): array
    {
        return $this->find($nodes, function (Node $node): bool {
            if (! $node instanceof ClassLike) {
                return false;
            }
            // skip anonymous classes
            return ! ($node instanceof Class_ && $node->isAnonymous());
        });
    }

    /**
     * @param Node[] $nodes
     */
    public function findFirstNonAnonymousClass(array $nodes): ?Node
    {
        return $this->findFirst($nodes, function (Node $node): bool {
            if (! $node instanceof ClassLike) {
                return false;
            }

            // skip anonymous classes
            return ! ($node instanceof Class_ && $node->isAnonymous());
        });
    }

    /**
     * @param Node|Node[] $nodes
     */
    public function findFirst($nodes, callable $filter): ?Node
    {
        return $this->nodeFinder->findFirst($nodes, $filter);
    }

    public function findPreviousAssignToExpr(Expr $expr): ?Node
    {
        return $this->findFirstPrevious($expr, function (Node $node) use ($expr): bool {
            if (! $node instanceof Assign) {
                return false;
            }

            return $this->betterStandardPrinter->areNodesEqual($node->var, $expr);
        });
    }

    public function findFirstPrevious(Node $node, callable $filter): ?Node
    {
        $node = $node instanceof Expression ? $node : $node->getAttribute(AttributeKey::CURRENT_STATEMENT);
        if ($node === null) {
            return null;
        }

        $foundNode = $this->findFirst([$node], $filter);
        // we found what we need
        if ($foundNode !== null) {
            return $foundNode;
        }

        // move to next expression
        $previousStatement = $node->getAttribute(AttributeKey::PREVIOUS_STATEMENT);
        if ($previousStatement === null) {
            return null;
        }

        return $this->findFirstPrevious($previousStatement, $filter);
    }

    /**
     * @param class-string[] $types
     */
    public function findFirstPreviousOfTypes(Node $mainNode, array $types): ?Node
    {
        return $this->findFirstPrevious($mainNode, function (Node $node) use ($types): bool {
            foreach ($types as $type) {
                if (! is_a($node, $type, true)) {
                    continue;
                }

                return true;
            }

            return false;
        });
    }

    private function ensureIsNodeClass(string $type, string $location, int $argumentPosition): void
    {
        if (is_a($type, Node::class, true)) {
            return;
        }

        $errorMessage = sprintf(
            'Type given to "%s()" method on %d position must be child of "%s". "%s" given.',
            $argumentPosition,
            $location,
            Node::class,
            $type
        );

        throw new ShouldNotHappenException($errorMessage);
    }

    /**
     * @param class-string[] $types
     */
    private function isTypes(Node $node, array $types): bool
    {
        foreach ($types as $type) {
            $this->ensureIsNodeClass($type, __METHOD__, 1);

            if (is_a($node, $type, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Node|Node[] $nodes
     * @param class-string $type
     */
    private function findInstanceOfName($nodes, string $type, string $name): ?Node
    {
        $this->ensureIsNodeClass($type, __METHOD__, 1);

        $foundInstances = $this->nodeFinder->findInstanceOf($nodes, $type);

        foreach ($foundInstances as $foundInstance) {
            if ($this->nodeNameResolver->isName($foundInstance, $name)) {
                return $foundInstance;
            }
        }

        return null;
    }
}
