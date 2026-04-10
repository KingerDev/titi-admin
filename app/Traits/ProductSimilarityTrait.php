<?php

namespace App\Traits;

trait ProductSimilarityTrait
{
    /**
     * Tokenize a product name into lowercase words (2+ chars).
     */
    protected function tokenizeName(string $name): array
    {
        $name   = mb_strtolower(trim($name));
        $tokens = preg_split('/[\s\/,;.()\[\]{}]+/', $name, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter($tokens, fn($t) => mb_strlen($t) >= 2));
    }

    /**
     * Jaccard similarity between two token arrays.
     */
    protected function jaccardSimilarity(array $a, array $b): float
    {
        $setA = array_unique($a);
        $setB = array_unique($b);
        $intersection = count(array_intersect($setA, $setB));
        $union        = count(array_unique(array_merge($setA, $setB)));
        return $union > 0 ? $intersection / $union : 0.0;
    }

    /**
     * Jaccard similarity between two sets of filter IDs.
     */
    protected function filterJaccardSimilarity(array $filterIdsA, array $filterIdsB): float
    {
        if (empty($filterIdsA) || empty($filterIdsB)) return 0.0;
        $setA = array_unique($filterIdsA);
        $setB = array_unique($filterIdsB);
        $intersection = count(array_intersect($setA, $setB));
        $union        = count(array_unique(array_merge($setA, $setB)));
        return $union > 0 ? $intersection / $union : 0.0;
    }
}
