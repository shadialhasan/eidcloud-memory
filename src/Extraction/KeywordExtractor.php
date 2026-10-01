<?php

declare(strict_types=1);

namespace EidCloud\Memory\Extraction;

class KeywordExtractor
{
    private const STOP_WORDS = [
        'a', 'about', 'above', 'after', 'again', 'against', 'all', 'am', 'an', 'and', 'any',
        'are', 'aren\'t', 'as', 'at', 'be', 'because', 'been', 'before', 'being', 'below',
        'between', 'both', 'but', 'by', 'can', 'can\'t', 'cannot', 'could', 'couldn\'t',
        'did', 'didn\'t', 'do', 'does', 'doesn\'t', 'doing', 'don\'t', 'down', 'during',
        'each', 'few', 'for', 'from', 'further', 'had', 'hadn\'t', 'has', 'hasn\'t', 'have',
        'haven\'t', 'having', 'he', 'he\'d', 'he\'ll', 'he\'s', 'her', 'here', 'here\'s',
        'hers', 'herself', 'him', 'himself', 'his', 'how', 'how\'s', 'i', 'i\'d', 'i\'ll',
        'i\'m', 'i\'ve', 'if', 'in', 'into', 'is', 'isn\'t', 'it', 'it\'s', 'its', 'itself',
        'let\'s', 'me', 'more', 'most', 'mustn\'t', 'my', 'myself', 'no', 'nor', 'not', 'of',
        'off', 'on', 'once', 'only', 'or', 'other', 'ought', 'our', 'ours', 'ourselves',
        'out', 'over', 'own', 'same', 'shan\'t', 'she', 'she\'d', 'she\'ll', 'she\'s',
        'should', 'shouldn\'t', 'so', 'some', 'such', 'than', 'that', 'that\'s', 'the',
        'their', 'theirs', 'them', 'themselves', 'then', 'there', 'there\'s', 'these',
        'they', 'they\'d', 'they\'ll', 'they\'re', 'they\'ve', 'this', 'those', 'through',
        'to', 'too', 'under', 'until', 'up', 'very', 'was', 'wasn\'t', 'we', 'we\'d',
        'we\'ll', 'we\'re', 'we\'ve', 'were', 'weren\'t', 'what', 'what\'s', 'when',
        'when\'s', 'where', 'where\'s', 'which', 'while', 'who', 'who\'s', 'whom', 'why',
        'why\'s', 'with', 'won\'t', 'would', 'wouldn\'t', 'you', 'you\'d', 'you\'ll',
        'you\'re', 'you\'ve', 'your', 'yours', 'yourself', 'yourselves'
    ];

    /**
     * Extracts salient keywords/tokens from text.
     *
     * @param string $text
     * @param int $maxKeywords
     * @return array<string>
     */
    public function extractKeywords(string $text, int $maxKeywords = 10): array
    {
        $normalized = mb_strtolower($text, 'UTF-8');
        // Extract words with alphanumeric characters and underscores/hyphens
        preg_match_all('/[a-z0-9_\-\.]{2,}/u', $normalized, $matches);
        $words = $matches[0] ?? [];

        $stopSet = array_flip(self::STOP_WORDS);
        $frequencies = [];

        foreach ($words as $word) {
            $cleaned = trim($word, '.-_');
            if (strlen($cleaned) < 2 || isset($stopSet[$cleaned]) || is_numeric($cleaned)) {
                continue;
            }
            $frequencies[$cleaned] = ($frequencies[$cleaned] ?? 0) + 1;
        }

        arsort($frequencies);
        return array_slice(array_keys($frequencies), 0, $maxKeywords);
    }

    /**
     * Calculates Jaccard & lexical associative similarity between two strings or sets of terms.
     */
    public function calculateAssociativeRelevance(string $query, string $content, array $tags = []): float
    {
        $queryTokens = $this->tokenize($query);
        if (empty($queryTokens)) {
            return 0.0;
        }

        $contentTokens = $this->tokenize($content);
        $tagTokens = [];
        foreach ($tags as $tag) {
            $tagTokens = array_merge($tagTokens, $this->tokenize($tag));
        }

        $allDocTokens = array_unique(array_merge($contentTokens, $tagTokens));

        // Direct token overlap
        $common = array_intersect($queryTokens, $allDocTokens);
        $overlapRatio = count($common) / count($queryTokens);

        // Tag exact match bonus
        $tagMatches = array_intersect($queryTokens, array_unique($tagTokens));
        $tagBonus = !empty($tagMatches) ? 0.25 : 0.0;

        // Substring presence bonus
        $cleanQuery = mb_strtolower(trim($query));
        $cleanContent = mb_strtolower(trim($content));
        $substringBonus = (strlen($cleanQuery) >= 3 && str_contains($cleanContent, $cleanQuery)) ? 0.3 : 0.0;

        // Levenshtein / fuzzy similarity for near-matches if overlap is low
        $fuzzyScore = 0.0;
        if (empty($common)) {
            foreach ($queryTokens as $qToken) {
                foreach ($allDocTokens as $dToken) {
                    $dist = levenshtein($qToken, $dToken);
                    $maxLen = max(strlen($qToken), strlen($dToken));
                    if ($maxLen > 3 && $dist <= 2) {
                        $sim = 1.0 - ($dist / $maxLen);
                        if ($sim > $fuzzyScore) {
                            $fuzzyScore = $sim;
                        }
                    }
                }
            }
        }

        $score = ($overlapRatio * 0.7) + $tagBonus + $substringBonus + ($fuzzyScore * 0.2);
        return min(1.0, max(0.0, $score));
    }

    private function tokenize(string $text): array
    {
        $normalized = mb_strtolower($text, 'UTF-8');
        preg_match_all('/[a-z0-9_\-]{2,}/u', $normalized, $matches);
        $tokens = [];
        $stopSet = array_flip(self::STOP_WORDS);

        foreach ($matches[0] ?? [] as $m) {
            $cleaned = trim($m, '-_');
            if (strlen($cleaned) >= 2 && !isset($stopSet[$cleaned])) {
                $tokens[] = $cleaned;
            }
        }
        return array_unique($tokens);
    }
}
