<?php

declare(strict_types=1);

namespace EidCloud\Memory\Models;

class MemoryItem
{
    public function __construct(
        public string $id,
        public string $content,
        public MemoryTier $tier = MemoryTier::SHORT_TERM,
        public float $importance = 5.0, // 1.0 to 10.0 scale
        public array $tags = [],
        public array $metadata = [],
        public int $createdAt = 0,
        public int $lastAccessedAt = 0,
        public int $accessCount = 0,
        public ?float $score = null // dynamic score during query
    ) {
        $now = time();
        if ($this->createdAt === 0) {
            $this->createdAt = $now;
        }
        if ($this->lastAccessedAt === 0) {
            $this->lastAccessedAt = $now;
        }
        $this->importance = max(1.0, min(10.0, $this->importance));
    }

    public static function create(
        string $content,
        MemoryTier $tier = MemoryTier::SHORT_TERM,
        float $importance = 5.0,
        array $tags = [],
        array $metadata = [],
        ?string $id = null
    ): self {
        $id = $id ?? bin2hex(random_bytes(8));
        return new self(
            id: $id,
            content: trim($content),
            tier: $tier,
            importance: $importance,
            tags: array_values(array_unique(array_filter(array_map('trim', $tags)))),
            metadata: $metadata
        );
    }

    public function recordAccess(): void
    {
        $this->accessCount++;
        $this->lastAccessedAt = time();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'tier' => $this->tier->value,
            'importance' => $this->importance,
            'tags' => $this->tags,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
            'last_accessed_at' => $this->lastAccessedAt,
            'access_count' => $this->accessCount,
            'score' => $this->score,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? bin2hex(random_bytes(8)),
            content: $data['content'] ?? '',
            tier: isset($data['tier']) ? MemoryTier::from($data['tier']) : MemoryTier::SHORT_TERM,
            importance: (float)($data['importance'] ?? 5.0),
            tags: $data['tags'] ?? [],
            metadata: $data['metadata'] ?? [],
            createdAt: (int)($data['created_at'] ?? time()),
            lastAccessedAt: (int)($data['last_accessed_at'] ?? time()),
            accessCount: (int)($data['access_count'] ?? 0),
            score: isset($data['score']) ? (float)$data['score'] : null
        );
    }
}
