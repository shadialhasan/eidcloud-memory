<?php

declare(strict_types=1);

namespace EidCloud\Memory\Tests;

use EidCloud\Memory\MemoryManager;
use EidCloud\Memory\Models\MemoryItem;
use EidCloud\Memory\Models\MemoryTier;
use EidCloud\Memory\Scoring\DecayEngine;
use EidCloud\Memory\Extraction\KeywordExtractor;
use EidCloud\Memory\Storage\SqliteStorage;
use EidCloud\Memory\Storage\JsonStorage;
use EidCloud\Memory\Storage\MemoryBufferStorage;

class MemoryTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function runAll(): bool
    {
        $this->testKeywordExtraction();
        $this->testDecayEngineScoring();
        $this->testSqliteStorage();
        $this->testJsonStorage();
        $this->testMemoryBufferStorageWithSnapshot();
        $this->testMemoryManagerRememberAndRecall();
        $this->testWorkingMemory();
        $this->testEpisodicLogging();
        $this->testPruningAndConsolidation();
        $this->testContextWindowFormatting();

        echo "\n" . str_repeat('=', 50) . "\n";
        echo "Test Results: {$this->passed} passed, {$this->failed} failed\n";
        echo str_repeat('=', 50) . "\n";

        return $this->failed === 0;
    }

    private function assert(bool $condition, string $testName): void
    {
        if ($condition) {
            $this->passed++;
            echo "  ✔ {$testName}\n";
        } else {
            $this->failed++;
            echo "  ✖ FAIL: {$testName}\n";
        }
    }

    public function testKeywordExtraction(): void
    {
        echo "\n[1] Testing KeywordExtractor...\n";
        $extractor = new KeywordExtractor();

        $text = "The user prefers dark mode in their IDE and writes code in PHP and TypeScript.";
        $keywords = $extractor->extractKeywords($text, 5);

        $this->assert(in_array('mode', $keywords) || in_array('prefers', $keywords) || in_array('php', $keywords), "Extracts salient keywords from text");

        $rel1 = $extractor->calculateAssociativeRelevance("dark mode settings", "The user prefers dark mode", ["preferences"]);
        $rel2 = $extractor->calculateAssociativeRelevance("quantum mechanics astrophysics", "The user prefers dark mode", ["preferences"]);

        $this->assert($rel1 > $rel2, "Relevant query produces higher score than irrelevant query");
        $this->assert($rel1 > 0.4, "Relevant query scores moderately high");
    }

    public function testDecayEngineScoring(): void
    {
        echo "\n[2] Testing DecayEngine...\n";
        $decay = new DecayEngine(halfLifeHours: 24.0);

        $now = time();
        $fresh = $decay->computeRecencyFactor($now, $now);
        $oneDayOld = $decay->computeRecencyFactor($now - 86400, $now);
        $twoDaysOld = $decay->computeRecencyFactor($now - (86400 * 2), $now);

        $this->assert(abs($fresh - 1.0) < 0.001, "Fresh memory has recency factor of 1.0");
        $this->assert(abs($oneDayOld - 0.5) < 0.05, "24-hour memory approaches 0.5 half-life");
        $this->assert($fresh > $oneDayOld && $oneDayOld > $twoDaysOld, "Recency decays monotonically over time");

        $itemHigh = MemoryItem::create("Important fact", MemoryTier::LONG_TERM, importance: 9.5);
        $itemLow = MemoryItem::create("Ephemeral fact", MemoryTier::SHORT_TERM, importance: 2.0);

        $scoreHigh = $decay->computeCompositeScore($itemHigh, relevance: 0.8, currentTime: $now);
        $scoreLow = $decay->computeCompositeScore($itemLow, relevance: 0.8, currentTime: $now);
        $this->assert($scoreHigh > $scoreLow, "High importance elevates composite retrieval score");

        $lifespanHigh = $decay->evaluateLifespan($itemHigh, $now + (86400 * 30));
        $this->assert($lifespanHigh === 'retain', "High importance item is retained regardless of age");
    }

    public function testSqliteStorage(): void
    {
        echo "\n[3] Testing SqliteStorage...\n";
        $storage = new SqliteStorage(':memory:');

        $item = MemoryItem::create("Database connection pooling setup", MemoryTier::LONG_TERM, 7.5, ["db", "pool"]);
        $storage->save($item);

        $fetched = $storage->get($item->id);
        $this->assert($fetched !== null, "Stored item is retrievable by ID");
        $this->assert($fetched?->content === $item->content, "Retrieved content matches");
        $this->assert($storage->count() === 1, "Count returns accurate total");

        $storage->delete($item->id);
        $this->assert($storage->count() === 0, "Delete operation removes item");
    }

    public function testJsonStorage(): void
    {
        echo "\n[4] Testing JsonStorage...\n";
        $tmpJson = sys_get_temp_dir() . '/eidcloud_test_' . uniqid() . '.json';
        $storage = new JsonStorage($tmpJson);

        $item1 = MemoryItem::create("JSON Item 1", MemoryTier::SHORT_TERM, 6.0);
        $item2 = MemoryItem::create("JSON Item 2", MemoryTier::WORKING, 8.0);
        $storage->saveBatch([$item1, $item2]);

        $this->assert(file_exists($tmpJson), "JSON storage file created on disk");
        $this->assert($storage->count() === 2, "JsonStorage count matches saved batch");
        $this->assert($storage->get($item1->id)?->content === "JSON Item 1", "JsonStorage get returns valid entity");

        if (file_exists($tmpJson)) {
            unlink($tmpJson);
        }
    }

    public function testMemoryBufferStorageWithSnapshot(): void
    {
        echo "\n[5] Testing MemoryBufferStorage...\n";
        $tmpSnap = sys_get_temp_dir() . '/eidcloud_snap_' . uniqid() . '.json';
        $storage = new MemoryBufferStorage($tmpSnap, snapshotThreshold: 2);

        $item1 = MemoryItem::create("Buffered 1", MemoryTier::SHORT_TERM, 5.0);
        $item2 = MemoryItem::create("Buffered 2", MemoryTier::SHORT_TERM, 6.0);
        $storage->save($item1);
        $storage->save($item2); // Reaches threshold 2 -> triggers snapshot

        $this->assert(file_exists($tmpSnap), "MemoryBufferStorage triggered automated snapshot");

        // Reload from snapshot in new instance
        $storage2 = new MemoryBufferStorage($tmpSnap);
        $this->assert($storage2->count() === 2, "MemoryBufferStorage restores state from snapshot");

        if (file_exists($tmpSnap)) {
            unlink($tmpSnap);
        }
    }

    public function testMemoryManagerRememberAndRecall(): void
    {
        echo "\n[6] Testing MemoryManager Remember & Recall...\n";
        $manager = new MemoryManager(new SqliteStorage(':memory:'));

        $manager->remember("PHP 8.2 introduced readonly classes and standalone null/true/false types", MemoryTier::LONG_TERM, 9.0, ["php", "features"]);
        $manager->remember("Python 3.12 improved error messages and speed optimizations", MemoryTier::LONG_TERM, 6.0, ["python", "features"]);
        $manager->remember("The capital of France is Paris", MemoryTier::LONG_TERM, 3.0, ["geography"]);

        $results = $manager->recall("What are modern PHP features?", topK: 2);
        $this->assert(!empty($results), "Recall returns matching items");
        $this->assert(str_contains($results[0]->content, "PHP 8.2"), "Most relevant PHP memory is ranked first");
        $this->assert($results[0]->accessCount > 0, "Memory recall updates access counter");
    }

    public function testWorkingMemory(): void
    {
        echo "\n[7] Testing Working Memory...\n";
        $manager = new MemoryManager(new SqliteStorage(':memory:'));

        $manager->setWorkingMemory("active_goal", "Refactor authentication layer", 9.0);
        $item = $manager->getWorkingMemory("active_goal");

        $this->assert($item !== null, "Working memory item is set and retrieved");
        $this->assert($item?->content === "Refactor authentication layer", "Working memory content matches");
        $this->assert($item?->tier === MemoryTier::WORKING, "Working memory assigned WORKING tier");
    }

    public function testEpisodicLogging(): void
    {
        echo "\n[8] Testing Episodic Logging...\n";
        $manager = new MemoryManager(new SqliteStorage(':memory:'));

        $turn1 = $manager->logEpisodicTurn("user", "Can you build a high-performance memory cache?", 1, 5.0);
        $turn2 = $manager->logEpisodicTurn("assistant", "Yes, I will use SQLite and pure PHP 8.2 algorithms.", 2, 7.0);

        $this->assert($turn1->tier === MemoryTier::EPISODIC, "Episodic turn assigned EPISODIC tier");
        $this->assert(str_contains($turn2->content, "[assistant]:"), "Episodic content contains structured role prefix");
        $this->assert($manager->getStorage()->count(MemoryTier::EPISODIC) === 2, "Episodic store count is 2");
    }

    public function testPruningAndConsolidation(): void
    {
        echo "\n[9] Testing Pruning and Consolidation...\n";
        $manager = new MemoryManager(new SqliteStorage(':memory:'));

        // High importance -> retain
        $manager->remember("Core agent identity and directive", MemoryTier::LONG_TERM, 9.5);
        // Low importance and artificially aged -> forget
        $oldForgotten = MemoryItem::create("Temporary noise message", MemoryTier::SHORT_TERM, 1.5);
        $oldForgotten->lastAccessedAt = time() - (86400 * 30); // 30 days old
        $manager->getStorage()->save($oldForgotten);

        // Moderate importance and aged -> summarize
        $oldSummarize1 = MemoryItem::create("User looked up weather in Damascus", MemoryTier::EPISODIC, 4.0);
        $oldSummarize1->lastAccessedAt = time() - (86400 * 15);
        $manager->getStorage()->save($oldSummarize1);

        $report = $manager->pruneAndConsolidate();
        $this->assert($report['retained'] >= 1, "Retained count is at least 1");
        $this->assert($report['forgotten'] >= 1, "Forgotten count is at least 1");
        $this->assert($report['summarized'] >= 1, "Summarized count recorded");
    }

    public function testContextWindowFormatting(): void
    {
        echo "\n[10] Testing Context Window Formatting...\n";
        $manager = new MemoryManager(new SqliteStorage(':memory:'));

        $manager->setWorkingMemory("task", "Deploy to production");
        $manager->remember("Production deployment credentials in AWS Secret Manager", MemoryTier::LONG_TERM, 8.5, ["deployment", "aws"]);

        $prompt = $manager->formatContextWindow("deployment credentials");
        $this->assert(str_contains($prompt, "### AGENT MEMORY CONTEXT"), "Prompt contains header");
        $this->assert(str_contains($prompt, "Deploy to production"), "Prompt includes working memory");
        $this->assert(str_contains($prompt, "AWS Secret Manager"), "Prompt includes associative recalled knowledge");
    }
}
