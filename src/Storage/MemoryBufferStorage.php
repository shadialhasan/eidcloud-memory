<?php

declare(strict_types=1);

namespace EidCloud\Memory\Storage;

use EidCloud\Memory\Models\MemoryItem;
use EidCloud\Memory\Models\MemoryTier;

class MemoryBufferStorage implements StorageInterface
{
    /** @var array<string, MemoryItem> */
    private array $items = [];
    private ?string $snapshotPath;
    private int $opsSinceSnapshot = 0;
    private int $snapshotThreshold;

    public function __construct(?string $snapshotPath = null, int $snapshotThreshold = 20)
    {
        $this->snapshotPath = $snapshotPath;
        $this->snapshotThreshold = $snapshotThreshold;

        if ($this->snapshotPath !== null && file_exists($this->snapshotPath)) {
            $this->loadSnapshot();
        }
    }

    public function save(MemoryItem $item): void
    {
        $this->items[$item->id] = $item;
        $this->checkSnapshot();
    }

    public function saveBatch(array $items): void
    {
        foreach ($items as $item) {
            $this->items[$item->id] = $item;
        }
        $this->checkSnapshot();
    }

    public function get(string $id): ?MemoryItem
    {
        return $this->items[$id] ?? null;
    }

    public function getAll(?MemoryTier $tier = null): array
    {
        $list = array_values($this->items);
        if ($tier !== null) {
            $list = array_values(array_filter($list, fn(MemoryItem $item) => $item->tier === $tier));
        }

        usort($list, fn(MemoryItem $a, MemoryItem $b) => $b->lastAccessedAt <=> $a->lastAccessedAt);
        return $list;
    }

    public function delete(string $id): bool
    {
        if (isset($this->items[$id])) {
            unset($this->items[$id]);
            $this->checkSnapshot();
            return true;
        }
        return false;
    }

    public function clear(?MemoryTier $tier = null): void
    {
        if ($tier !== null) {
            $this->items = array_filter($this->items, fn(MemoryItem $item) => $item->tier !== $tier);
        } else {
            $this->items = [];
        }
        $this->checkSnapshot();
    }

    public function count(?MemoryTier $tier = null): int
    {
        return count($this->getAll($tier));
    }

    public function snapshot(): void
    {
        if ($this->snapshotPath === null) {
            return;
        }

        $dir = dirname($this->snapshotPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = array_map(fn(MemoryItem $item) => $item->toArray(), array_values($this->items));
        file_put_contents($this->snapshotPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->opsSinceSnapshot = 0;
    }

    private function loadSnapshot(): void
    {
        if ($this->snapshotPath === null || !file_exists($this->snapshotPath)) {
            return;
        }

        $content = file_get_contents($this->snapshotPath);
        if (!empty($content)) {
            $data = json_decode($content, true);
            if (is_array($data)) {
                foreach ($data as $itemData) {
                    $item = MemoryItem::fromArray($itemData);
                    $this->items[$item->id] = $item;
                }
            }
        }
    }

    private function checkSnapshot(): void
    {
        $this->opsSinceSnapshot++;
        if ($this->snapshotPath !== null && $this->opsSinceSnapshot >= $this->snapshotThreshold) {
            $this->snapshot();
        }
    }

    public function __destruct()
    {
        if ($this->snapshotPath !== null && $this->opsSinceSnapshot > 0) {
            $this->snapshot();
        }
    }
}
