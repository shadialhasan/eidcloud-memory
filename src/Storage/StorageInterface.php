<?php

declare(strict_types=1);

namespace EidCloud\Memory\Storage;

use EidCloud\Memory\Models\MemoryItem;
use EidCloud\Memory\Models\MemoryTier;

interface StorageInterface
{
    public function save(MemoryItem $item): void;

    /**
     * @param array<MemoryItem> $items
     */
    public function saveBatch(array $items): void;

    public function get(string $id): ?MemoryItem;

    /**
     * @return array<MemoryItem>
     */
    public function getAll(?MemoryTier $tier = null): array;

    public function delete(string $id): bool;

    public function clear(?MemoryTier $tier = null): void;

    public function count(?MemoryTier $tier = null): int;
}
