<?php

declare(strict_types=1);

namespace EidCloud\Memory\Storage;

use EidCloud\Memory\Models\MemoryItem;
use EidCloud\Memory\Models\MemoryTier;

class JsonStorage implements StorageInterface
{
    private string $filePath;
    /** @var array<string, MemoryItem> */
    private array $items = [];
    private bool $loaded = false;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->items = [];
        if (file_exists($this->filePath)) {
            $content = file_get_contents($this->filePath);
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
        $this->loaded = true;
    }

    private function persist(): void
    {
        $data = [];
        foreach ($this->items as $item) {
            $data[] = $item->toArray();
        }
        file_put_contents($this->filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function save(MemoryItem $item): void
    {
        $this->load();
        $this->items[$item->id] = $item;
        $this->persist();
    }

    public function saveBatch(array $items): void
    {
        $this->load();
        foreach ($items as $item) {
            $this->items[$item->id] = $item;
        }
        $this->persist();
    }

    public function get(string $id): ?MemoryItem
    {
        $this->load();
        return $this->items[$id] ?? null;
    }

    public function getAll(?MemoryTier $tier = null): array
    {
        $this->load();
        $list = array_values($this->items);
        if ($tier !== null) {
            $list = array_values(array_filter($list, fn(MemoryItem $item) => $item->tier === $tier));
        }

        usort($list, fn(MemoryItem $a, MemoryItem $b) => $b->lastAccessedAt <=> $a->lastAccessedAt);
        return $list;
    }

    public function delete(string $id): bool
    {
        $this->load();
        if (isset($this->items[$id])) {
            unset($this->items[$id]);
            $this->persist();
            return true;
        }
        return false;
    }

    public function clear(?MemoryTier $tier = null): void
    {
        $this->load();
        if ($tier !== null) {
            $this->items = array_filter($this->items, fn(MemoryItem $item) => $item->tier !== $tier);
        } else {
            $this->items = [];
        }
        $this->persist();
    }

    public function count(?MemoryTier $tier = null): int
    {
        return count($this->getAll($tier));
    }
}
