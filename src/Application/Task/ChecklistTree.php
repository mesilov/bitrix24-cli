<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Failure;

final class ChecklistTree
{
    private array $nodes = [];
    private array $children = [];
    private array $roots = [];

    public function __construct(int $taskId, array $nodes)
    {
        if (count($nodes) > 10000) {
            throw Failure::binding('Checklist node budget exceeded.');
        }

        foreach ($nodes as $node) {
            if (!is_int($node['id'] ?? null) || $node['id'] < 1 || !is_int($node['parentId'] ?? null) || $node['parentId'] < 0 || ($node['taskId'] ?? null) !== $taskId || !is_int($node['sortIndex'] ?? null) || !is_string($node['title'] ?? null) || !is_bool($node['isComplete'] ?? null) || isset($this->nodes[$node['id']])) {
                throw Failure::binding('The checklist tree has missing or conflicting node data.');
            }

            $this->nodes[$node['id']] = $node;
            $this->children[$node['parentId']][] = $node;
        }

        foreach ($this->children as &$siblings) {
            usort($siblings, static fn (array $a, array $b): int => [$a['sortIndex'], $a['id']] <=> [$b['sortIndex'], $b['id']]);
        }

        unset($siblings);
        foreach (array_keys($this->nodes) as $id) {
            $this->rootOf($id);
        }
    }

    public function roots(): array
    {
        return $this->children[0] ?? [];
    }

    public function root(int $id): array
    {
        $node = $this->nodes[$id] ?? null;
        if ($node === null || $node['parentId'] !== 0) {
            throw Failure::binding('The selected checklist root does not belong to this task.');
        }

        return $node;
    }

    public function item(int $id): array
    {
        $node = $this->nodes[$id] ?? null;
        if ($node === null || $node['parentId'] === 0) {
            throw Failure::binding('Select an item inside this task; a checklist root is not an item.');
        }

        return $node;
    }

    public function parent(int $rootId, ?int $parentId): int
    {
        $this->root($rootId);
        if ($parentId === null) {
            return $rootId;
        }

        $this->item($parentId);
        if ($this->rootOf($parentId) !== $rootId) {
            throw Failure::binding('The parent item belongs to another checklist.');
        }

        return $parentId;
    }

    public function descendants(int $id): array
    {
        $out = [];
        $pending = array_reverse($this->children[$id] ?? []);
        while ($pending !== []) {
            $node = array_pop($pending);
            $out[] = $node;
            array_push($pending, ...array_reverse($this->children[$node['id']] ?? []));
        }

        return $out;
    }

    private function rootOf(int $id): int
    {
        $path = [];
        $current = $id;
        while (!isset($this->roots[$current])) {
            if (isset($path[$current]) || !isset($this->nodes[$current])) {
                throw Failure::binding('Checklist parents are missing or cyclic.');
            }

            $path[$current] = true;
            if ($this->nodes[$current]['parentId'] === 0) {
                $this->roots[$current] = $current;
                break;
            }

            $current = $this->nodes[$current]['parentId'];
        }

        foreach (array_keys($path) as $nodeId) {
            $this->roots[$nodeId] = $this->roots[$current];
        }

        return $this->roots[$id];
    }
}
