<?php

declare(strict_types=1);

namespace EidCloud\Memory\Scoring;

use EidCloud\Memory\Models\MemoryItem;

class DecayEngine
{
    /**
     * @param float $recencyWeight Weight of recency (0.0 - 1.0)
     * @param float $importanceWeight Weight of intrinsic importance (0.0 - 1.0)
     * @param float $relevanceWeight Weight of semantic/associative relevance (0.0 - 1.0)
     * @param float $halfLifeHours Half-life in hours for exponential time decay
     */
    public function __construct(
        public float $recencyWeight = 0.25,
        public float $importanceWeight = 0.35,
        public float $relevanceWeight = 0.40,
        public float $halfLifeHours = 72.0
    ) {
    }

    /**
     * Computes the recency factor using exponential decay based on last accessed time.
     * Retains value between 0.0 and 1.0.
     */
    public function computeRecencyFactor(int $lastAccessedAt, ?int $currentTime = null): float
    {
        $now = $currentTime ?? time();
        $elapsedSeconds = max(0, $now - $lastAccessedAt);
        $elapsedHours = $elapsedSeconds / 3600.0;

        // Exponential decay: e^(-lambda * t), where lambda = ln(2) / halfLife
        $lambda = 0.693147 / max(0.1, $this->halfLifeHours);
        return exp(-$lambda * $elapsedHours);
    }

    /**
     * Computes composite score for ranking memory retrieval.
     * Score is in range [0.0, 1.0].
     */
    public function computeCompositeScore(
        MemoryItem $item,
        float $relevance,
        ?int $currentTime = null
    ): float {
        $recency = $this->computeRecencyFactor($item->lastAccessedAt, $currentTime);
        $normalizedImportance = ($item->importance - 1.0) / 9.0; // scale 1..10 to 0..1

        // Access frequency booster (diminishing returns logarithm)
        $frequencyBonus = min(0.15, log10(1 + $item->accessCount) * 0.1);

        $composite = (
            ($recency * $this->recencyWeight) +
            ($normalizedImportance * $this->importanceWeight) +
            ($relevance * $this->relevanceWeight) +
            $frequencyBonus
        );

        return min(1.0, max(0.0, $composite));
    }

    /**
     * Determines whether a memory item should be retained, archived/summarized, or forgotten.
     * Returns: 'retain', 'summarize', or 'forget'
     */
    public function evaluateLifespan(MemoryItem $item, ?int $currentTime = null): string
    {
        $recency = $this->computeRecencyFactor($item->lastAccessedAt, $currentTime);
        $normalizedImportance = ($item->importance - 1.0) / 9.0;

        // High importance memories are always retained
        if ($item->importance >= 8.0) {
            return 'retain';
        }

        // Composite persistence score
        $survivalScore = ($recency * 0.4) + ($normalizedImportance * 0.6);

        if ($survivalScore >= 0.45) {
            return 'retain';
        } elseif ($survivalScore >= 0.20 || $item->accessCount >= 2) {
            return 'summarize';
        }

        return 'forget';
    }
}
