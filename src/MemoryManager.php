<?php

declare(strict_types=1);

namespace EidCloud\Memory;

use EidCloud\Memory\Extraction\KeywordExtractor;
use EidCloud\Memory\Models\MemoryItem;
use EidCloud\Memory\Models\MemoryTier;
use EidCloud\Memory\Scoring\DecayEngine;
use EidCloud\Memory\Storage\MemoryBufferStorage;
use EidCloud\Memory\Storage\SqliteStorage;
use EidCloud\Memory\Storage\StorageInterface;

class MemoryManager
{
    private StorageInterface $storage;
    private DecayEngine $decayEngine;
    private KeywordExtractor $keywordExtractor;

    public function __construct(
        ?StorageInterface $storage = null,
        ?DecayEngine $decayEngine = null,
        ?KeywordExtractor $keywordExtractor = null
    ) {
        $this->storage = $storage ?? new SqliteStorage();
        $this->decayEngine = $decayEngine ?? new DecayEngine();
        $this->keywordExtractor = $keywordExtractor ?? new KeywordExtractor();
    }

    public function getStorage(): StorageInterface
    {
        return $this->storage;
    }

    public function getDecayEngine(): DecayEngine
    {
        return $this->decayEngine;
    }

    public function getKeywordExtractor(): KeywordExtractor
    {
        return $this->keywordExtractor;
    }

    /**
     * Stores a memory item across specified tier.
     * Automatically extracts keywords if tags are not explicitly provided.
     */
    public function remember(
        string $content,
        MemoryTier $tier = MemoryTier::SHORT_TERM,
        float $importance = 5.0,
        array $tags = [],
        array $metadata = [],
        ?string $id = null
    ): MemoryItem {
        $autoTags = empty($tags) ? $this->keywordExtractor->extractKeywords($content, 5) : $tags;

        $item = MemoryItem::create(
            content: $content,
            tier: $tier,
            importance: $importance,
            tags: array_values(array_unique(array_merge($tags, $autoTags))),
            metadata: $metadata,
            id: $id
        );

        $this->storage->save($item);
        return $item;
    }

    /**
     * Semantic and associative query across stored memories.
     * Computes Composite Score = Recency + Importance + Semantic/Associative Relevance.
     *
     * @return array<MemoryItem>
     */
    public function recall(
        string $query,
        int $topK = 5,
        ?MemoryTier $tier = null,
        float $minScore = 0.05
    ): array {
        $allItems = $this->storage->getAll($tier);
        if (empty($allItems)) {
            return [];
        }

        $now = time();
        $scored = [];

        foreach ($allItems as $item) {
            $relevance = $this->keywordExtractor->calculateAssociativeRelevance(
                $query,
                $item->content,
                $item->tags
            );

            $compositeScore = $this->decayEngine->computeCompositeScore($item, $relevance, $now);

            if ($compositeScore >= $minScore) {
                // Record access and save update
                $item->recordAccess();
                $item->score = round($compositeScore, 4);
                $this->storage->save($item);

                $scored[] = $item;
            }
        }

        usort($scored, fn(MemoryItem $a, MemoryItem $b) => ($b->score <=> $a->score));

        return array_slice($scored, 0, $topK);
    }

    /**
     * Working memory scratchpad for active reasoning turn.
     * Promotes or resets temporary states.
     */
    public function setWorkingMemory(string $key, mixed $value, float $importance = 5.0): MemoryItem
    {
        $content = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $this->remember(
            content: $content,
            tier: MemoryTier::WORKING,
            importance: $importance,
            tags: ['working_memory', $key],
            metadata: ['key' => $key, 'raw_type' => gettype($value)],
            id: 'working_' . md5($key)
        );
    }

    public function getWorkingMemory(string $key): ?MemoryItem
    {
        return $this->storage->get('working_' . md5($key));
    }

    /**
     * Episodic memory turn ingestion (e.g. user-agent dialogue turns).
     */
    public function logEpisodicTurn(
        string $role,
        string $message,
        int $turnIndex,
        float $importance = 4.0,
        array $extraTags = []
    ): MemoryItem {
        $tags = array_merge(['turn_' . $turnIndex, $role], $extraTags);
        return $this->remember(
            content: "[{$role}]: {$message}",
            tier: MemoryTier::EPISODIC,
            importance: $importance,
            tags: $tags,
            metadata: [
                'role' => $role,
                'turn_index' => $turnIndex,
            ]
        );
    }

    /**
     * Prunes and compresses memories according to importance & decay lifespan.
     * - 'forget': Deleted from storage.
     * - 'summarize': Condensed/consolidated into episodic or long-term digest.
     * - 'retain': Preserved as is.
     *
     * @return array{forgotten: int, summarized: int, retained: int}
     */
    public function pruneAndConsolidate(?MemoryTier $tier = null): array
    {
        $items = $this->storage->getAll($tier);
        $forgottenCount = 0;
        $summarizedCount = 0;
        $retainedCount = 0;

        $summariesByTier = [];
        $now = time();

        foreach ($items as $item) {
            $verdict = $this->decayEngine->evaluateLifespan($item, $now);

            if ($verdict === 'forget') {
                $this->storage->delete($item->id);
                $forgottenCount++;
            } elseif ($verdict === 'summarize') {
                $summariesByTier[$item->tier->value][] = $item;
                $this->storage->delete($item->id);
                $summarizedCount++;
            } else {
                $retainedCount++;
            }
        }

        // Create consolidated summary items for summarized groups
        foreach ($summariesByTier as $tierKey => $summarizedItems) {
            $combinedSnippets = [];
            $allTags = ['consolidated_summary'];
            foreach ($summarizedItems as $sumItem) {
                $combinedSnippets[] = $sumItem->content;
                $allTags = array_merge($allTags, $sumItem->tags);
            }

            $summaryContent = "Consolidated summary (" . count($summarizedItems) . " items): " . implode(' | ', $combinedSnippets);
            $this->remember(
                content: $summaryContent,
                tier: MemoryTier::LONG_TERM,
                importance: 6.5,
                tags: array_values(array_unique($allTags)),
                metadata: [
                    'source_tier' => $tierKey,
                    'consolidated_count' => count($summarizedItems),
                    'consolidated_at' => $now
                ]
            );
        }

        return [
            'forgotten' => $forgottenCount,
            'summarized' => $summarizedCount,
            'retained' => $retainedCount,
        ];
    }

    /**
     * Builds structured context prompt suitable for injection into LLM context window.
     */
    public function formatContextWindow(string $query, int $tokenBudget = 2000): string
    {
        $memories = $this->recall($query, topK: 10);
        $workingItems = $this->storage->getAll(MemoryTier::WORKING);

        $lines = ["### AGENT MEMORY CONTEXT"];

        if (!empty($workingItems)) {
            $lines[] = "\n[Active Working Memory]:";
            foreach ($workingItems as $wItem) {
                $lines[] = "- {$wItem->content}";
            }
        }

        if (!empty($memories)) {
            $lines[] = "\n[Retrieved Associative Knowledge]:";
            foreach ($memories as $mItem) {
                $tags = !empty($mItem->tags) ? ' (' . implode(', ', $mItem->tags) . ')' : '';
                $lines[] = sprintf("- [%s] %s%s [score: %.2f]", strtoupper($mItem->tier->value), $mItem->content, $tags, $mItem->score ?? 0);
            }
        }

        $result = implode("\n", $lines);
        return $result;
    }

    /**
     * Statistics breakdown of stored memories.
     */
    public function getStats(): array
    {
        $all = $this->storage->getAll();
        $tierBreakdown = [];
        $totalImportance = 0.0;

        foreach ($all as $item) {
            $tierName = $item->tier->value;
            $tierBreakdown[$tierName] = ($tierBreakdown[$tierName] ?? 0) + 1;
            $totalImportance += $item->importance;
        }

        $totalCount = count($all);
        return [
            'total_memories' => $totalCount,
            'tier_breakdown' => $tierBreakdown,
            'avg_importance' => $totalCount > 0 ? round($totalImportance / $totalCount, 2) : 0.0,
            'storage_driver' => (new \ReflectionClass($this->storage))->getShortName(),
        ];
    }
}
