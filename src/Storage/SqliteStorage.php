<?php

declare(strict_types=1);

namespace EidCloud\Memory\Storage;

use EidCloud\Memory\Models\MemoryItem;
use EidCloud\Memory\Models\MemoryTier;
use PDO;

class SqliteStorage implements StorageInterface
{
    private PDO $pdo;

    public function __construct(string $path = ':memory:')
    {
        $dir = dirname($path);
        if ($path !== ':memory:' && !is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->pdo = new PDO('sqlite:' . $path);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->initializeSchema();
    }

    private function initializeSchema(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS memories (
                id TEXT PRIMARY KEY,
                content TEXT NOT NULL,
                tier TEXT NOT NULL,
                importance REAL NOT NULL,
                tags TEXT NOT NULL,
                metadata TEXT NOT NULL,
                created_at INTEGER NOT NULL,
                last_accessed_at INTEGER NOT NULL,
                access_count INTEGER NOT NULL DEFAULT 0
            );
            CREATE INDEX IF NOT EXISTS idx_memories_tier ON memories (tier);
            CREATE INDEX IF NOT EXISTS idx_memories_importance ON memories (importance);
            CREATE INDEX IF NOT EXISTS idx_memories_last_accessed ON memories (last_accessed_at);
        ";
        $this->pdo->exec($sql);
    }

    public function save(MemoryItem $item): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO memories (id, content, tier, importance, tags, metadata, created_at, last_accessed_at, access_count)
            VALUES (:id, :content, :tier, :importance, :tags, :metadata, :created_at, :last_accessed_at, :access_count)
            ON CONFLICT(id) DO UPDATE SET
                content = excluded.content,
                tier = excluded.tier,
                importance = excluded.importance,
                tags = excluded.tags,
                metadata = excluded.metadata,
                last_accessed_at = excluded.last_accessed_at,
                access_count = excluded.access_count
        ");

        $stmt->execute([
            ':id' => $item->id,
            ':content' => $item->content,
            ':tier' => $item->tier->value,
            ':importance' => $item->importance,
            ':tags' => json_encode($item->tags, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ':metadata' => json_encode($item->metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ':created_at' => $item->createdAt,
            ':last_accessed_at' => $item->lastAccessedAt,
            ':access_count' => $item->accessCount,
        ]);
    }

    public function saveBatch(array $items): void
    {
        $this->pdo->beginTransaction();
        try {
            foreach ($items as $item) {
                $this->save($item);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function get(string $id): ?MemoryItem
    {
        $stmt = $this->pdo->prepare("SELECT * FROM memories WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return $this->hydrateItem($row);
    }

    public function getAll(?MemoryTier $tier = null): array
    {
        if ($tier !== null) {
            $stmt = $this->pdo->prepare("SELECT * FROM memories WHERE tier = :tier ORDER BY last_accessed_at DESC");
            $stmt->execute([':tier' => $tier->value]);
        } else {
            $stmt = $this->pdo->query("SELECT * FROM memories ORDER BY last_accessed_at DESC");
        }

        $results = [];
        while ($row = $stmt->fetch()) {
            $results[] = $this->hydrateItem($row);
        }
        return $results;
    }

    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM memories WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function clear(?MemoryTier $tier = null): void
    {
        if ($tier !== null) {
            $stmt = $this->pdo->prepare("DELETE FROM memories WHERE tier = :tier");
            $stmt->execute([':tier' => $tier->value]);
        } else {
            $this->pdo->exec("DELETE FROM memories");
        }
    }

    public function count(?MemoryTier $tier = null): int
    {
        if ($tier !== null) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM memories WHERE tier = :tier");
            $stmt->execute([':tier' => $tier->value]);
            return (int)$stmt->fetchColumn();
        }
        return (int)$this->pdo->query("SELECT COUNT(*) FROM memories")->fetchColumn();
    }

    private function hydrateItem(array $row): MemoryItem
    {
        return new MemoryItem(
            id: $row['id'],
            content: $row['content'],
            tier: MemoryTier::from($row['tier']),
            importance: (float)$row['importance'],
            tags: json_decode($row['tags'] ?? '[]', true) ?: [],
            metadata: json_decode($row['metadata'] ?? '{}', true) ?: [],
            createdAt: (int)$row['created_at'],
            lastAccessedAt: (int)$row['last_accessed_at'],
            accessCount: (int)$row['access_count']
        );
    }
}
