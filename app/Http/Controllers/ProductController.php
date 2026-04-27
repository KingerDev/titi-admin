<?php

namespace App\Http\Controllers;

use App\Traits\ProductSimilarityTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class ProductController extends Controller
{
    use ProductSimilarityTrait;

    // ═══════════════════════════════════════════════════════════════════════════
    //  PRODUCT DETAIL PAGE
    // ═══════════════════════════════════════════════════════════════════════════

    public function show(int $productId)
    {
        $product = DB::connection('titi')
            ->table('titi_product as p')
            ->join('titi_product_description as pd', function ($j) {
                $j->on('p.product_id', '=', 'pd.product_id')
                  ->where('pd.language_id', 2);
            })
            ->where('p.product_id', $productId)
            ->select('p.product_id', 'pd.name', 'pd.description')
            ->first();

        if (!$product) abort(404);

        $image = DB::connection('titi')
            ->table('titi_product_image')
            ->where('product_id', $productId)
            ->where('main', 1)
            ->value('image');

        $filters = DB::connection('titi')
            ->table('titi_product_filter as pf')
            ->join('titi_filter_description as fd', function ($j) {
                $j->on('pf.filter_id', '=', 'fd.filter_id')
                  ->where('fd.language_id', 2);
            })
            ->join('titi_filter as f', 'pf.filter_id', '=', 'f.filter_id')
            ->join('titi_filter_group_description as fgd', function ($j) {
                $j->on('f.filter_group_id', '=', 'fgd.filter_group_id')
                  ->where('fgd.language_id', 2);
            })
            ->where('pf.product_id', $productId)
            ->select('pf.filter_id', 'fd.name', 'fgd.name as group_name')
            ->orderBy('fgd.name')
            ->get()
            ->map(fn($f) => ['filter_id' => $f->filter_id, 'name' => $f->name, 'group_name' => $f->group_name])
            ->all();

        $categories = DB::connection('titi')
            ->table('titi_product_to_category as pc')
            ->join('titi_category_description as cd', function ($j) {
                $j->on('pc.category_id', '=', 'cd.category_id')
                  ->where('cd.language_id', 2);
            })
            ->where('pc.product_id', $productId)
            ->select('pc.category_id', 'cd.name')
            ->get()
            ->map(fn($c) => ['category_id' => $c->category_id, 'name' => $c->name])
            ->all();

        $variantIds = DB::connection('titi')
            ->table('titi_product_variant')
            ->where('product_id', $productId)
            ->pluck('variant_id')
            ->all();

        $relatedIds = DB::connection('titi')
            ->table('titi_product_related')
            ->where('product_id', $productId)
            ->pluck('related_id')
            ->all();

        $descRaw = $product->description ?? '';
        $descHtml = iconv('UTF-8', 'UTF-8//IGNORE', $descRaw) ?: '';

        return Inertia::render('Products/Show', [
            'product' => [
                'product_id'       => $product->product_id,
                'name'             => $product->name,
                'description'      => $descHtml ? mb_substr(strip_tags($descHtml), 0, 500) : '',
                'description_html' => $descHtml,
                'image'            => $image ? "https://titi.shopweb.sk/{$image}" : null,
                'filters'          => $filters,
                'categories'       => $categories,
            ],
            'variants' => $this->loadProductList($variantIds),
            'related'  => $this->loadProductList($relatedIds),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  VARIANTS — CRUD
    // ═══════════════════════════════════════════════════════════════════════════

    public function getVariants(int $productId)
    {
        $ids = DB::connection('titi')
            ->table('titi_product_variant')
            ->where('product_id', $productId)
            ->distinct()
            ->pluck('variant_id')
            ->all();

        return response()->json($this->loadProductList($ids));
    }

    public function getVariantGroups(int $productId)
    {
        $variantIds = DB::connection('titi')
            ->table('titi_product_variant')
            ->where('product_id', $productId)
            ->distinct()
            ->pluck('variant_id')
            ->map(fn($id) => (int) $id)
            ->all();

        $allIds = array_values(array_unique(array_merge([$productId], $variantIds)));

        if (count($allIds) < 2) {
            return response()->json(['groups' => [], 'variants' => []]);
        }

        $filterRows = DB::connection('titi')
            ->table('titi_product_filter as pf')
            ->join('titi_filter_description as fd', function ($j) {
                $j->on('pf.filter_id', '=', 'fd.filter_id')->where('fd.language_id', 2);
            })
            ->join('titi_filter_group_description as fgd', function ($j) {
                $j->on('fd.filter_group_id', '=', 'fgd.filter_group_id')->where('fgd.language_id', 2);
            })
            ->whereIn('pf.product_id', $allIds)
            ->select('pf.product_id', 'fgd.name as group_name', 'fd.name as filter_name')
            ->get();

        // product_id → [group_name → first filter_name]
        $productFilterMap = [];
        foreach ($filterRows as $row) {
            $pid = (int) $row->product_id;
            if (!isset($productFilterMap[$pid][$row->group_name])) {
                $productFilterMap[$pid][$row->group_name] = $row->filter_name;
            }
        }

        $allGroupNames = array_unique($filterRows->pluck('group_name')->all());

        $distinguishingGroups = [];
        foreach ($allGroupNames as $groupName) {
            $values = array_filter(
                array_map(fn($pid) => $productFilterMap[$pid][$groupName] ?? null, $allIds),
                fn($v) => $v !== null
            );
            if (count(array_unique($values)) > 1) {
                $distinguishingGroups[] = $groupName;
            }
        }

        $productList   = $this->loadProductList($allIds);
        $productIndex  = array_column($productList, null, 'product_id');

        $variants = array_map(function (int $pid) use ($productIndex, $productFilterMap, $distinguishingGroups, $productId) {
            $info = $productIndex[$pid] ?? ['product_id' => $pid, 'name' => "Produkt #{$pid}", 'image' => null];
            $filterValues = [];
            foreach ($distinguishingGroups as $g) {
                $filterValues[$g] = $productFilterMap[$pid][$g] ?? null;
            }
            return array_merge($info, [
                'is_current'    => $pid === $productId,
                'filter_values' => $filterValues,
            ]);
        }, $allIds);

        return response()->json(['groups' => $distinguishingGroups, 'variants' => $variants]);
    }

    public function suggestVariants(int $productId)
    {
        $target = $this->getProductWithContext($productId);
        if (!$target) return response()->json(['groups' => []]);

        $peers = $this->getCategoryPeers($productId, 150);
        $candidates = $this->filterVariantCandidates($target, $peers);

        if (empty($candidates)) return response()->json(['groups' => []]);

        $groups = $this->detectVariantsViaAI($target, $candidates);

        // Enrich each group with product data
        $enriched = [];
        foreach ($groups as $group) {
            $groupIds = array_values(array_unique(array_map('intval', $group)));
            if (count($groupIds) < 2) continue;
            $enriched[] = $this->loadProductList($groupIds);
        }

        return response()->json(['groups' => $enriched]);
    }

    public function saveVariants(Request $request, int $productId)
    {
        $variantIds = array_values(array_unique(
            array_map('intval', (array) $request->input('variant_ids', []))
        ));

        if (empty($variantIds)) return response()->json(['saved' => 0]);

        $this->saveVariantPairs($productId, $variantIds);

        return response()->json(['saved' => count($variantIds)]);
    }

    public function removeVariant(int $productId, int $variantId)
    {
        DB::connection('titi')->table('titi_product_variant')
            ->where('product_id', $productId)->where('variant_id', $variantId)
            ->delete();
        DB::connection('titi')->table('titi_product_variant')
            ->where('product_id', $variantId)->where('variant_id', $productId)
            ->delete();

        return response()->json(['success' => true]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  RELATED — CRUD
    // ═══════════════════════════════════════════════════════════════════════════

    public function getRelated(int $productId)
    {
        $ids = DB::connection('titi')
            ->table('titi_product_related')
            ->where('product_id', $productId)
            ->pluck('related_id')
            ->all();

        return response()->json($this->loadProductList($ids));
    }

    public function suggestRelated(int $productId)
    {
        $target = $this->getProductWithContext($productId);
        if (!$target) return response()->json(['related' => []]);

        $candidates = $this->buildRelatedCandidates($productId, $target);

        if (empty($candidates)) return response()->json(['related' => []]);

        $relatedIds = $this->detectRelatedViaAI($target, $candidates);

        return response()->json(['related' => $this->loadProductList($relatedIds)]);
    }

    public function saveRelated(Request $request, int $productId)
    {
        $relatedIds = array_values(array_unique(
            array_map('intval', (array) $request->input('related_ids', []))
        ));

        if (empty($relatedIds)) return response()->json(['saved' => 0]);

        $this->saveRelatedPairs($productId, $relatedIds);

        return response()->json(['saved' => count($relatedIds)]);
    }

    public function removeRelated(int $productId, int $relatedId)
    {
        DB::connection('titi')->table('titi_product_related')
            ->where('product_id', $productId)->where('related_id', $relatedId)
            ->delete();
        DB::connection('titi')->table('titi_product_related')
            ->where('product_id', $relatedId)->where('related_id', $productId)
            ->delete();

        return response()->json(['success' => true]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  BATCH (for category page — frontend calls per-product in a loop)
    // ═══════════════════════════════════════════════════════════════════════════

    public function batchSuggestVariants(Request $request)
    {
        $productId = (int) $request->input('product_id');
        if (!$productId) return response()->json(['groups' => [], 'product_id' => 0]);

        $target = $this->getProductWithContext($productId);
        if (!$target) return response()->json(['groups' => [], 'product_id' => $productId]);

        $peers = $this->getCategoryPeers($productId, 150);
        $candidates = $this->filterVariantCandidates($target, $peers);

        if (empty($candidates)) return response()->json(['groups' => [], 'product_id' => $productId]);

        $groups = $this->detectVariantsViaAI($target, $candidates);

        $enriched = [];
        foreach ($groups as $group) {
            $groupIds = array_values(array_unique(array_map('intval', $group)));
            if (count($groupIds) < 2) continue;
            $enriched[] = $this->loadProductList($groupIds);
        }

        return response()->json(['product_id' => $productId, 'groups' => $enriched]);
    }

    public function batchSuggestRelated(Request $request)
    {
        $productId = (int) $request->input('product_id');
        if (!$productId) return response()->json(['related' => [], 'product_id' => 0]);

        $target = $this->getProductWithContext($productId);
        if (!$target) return response()->json(['related' => [], 'product_id' => $productId]);

        $candidates = $this->buildRelatedCandidates($productId, $target);

        if (empty($candidates)) return response()->json(['related' => [], 'product_id' => $productId]);

        $relatedIds = $this->detectRelatedViaAI($target, $candidates);

        return response()->json([
            'product_id' => $productId,
            'related'    => $this->loadProductList($relatedIds),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════════════════════

    private function getProductWithContext(int $productId): ?array
    {
        $row = DB::connection('titi')
            ->table('titi_product_description')
            ->where('product_id', $productId)
            ->where('language_id', 2)
            ->select('name', 'description')
            ->first();

        if (!$row) return null;

        $filterIds = DB::connection('titi')
            ->table('titi_product_filter')
            ->where('product_id', $productId)
            ->pluck('filter_id')
            ->map(fn($id) => (int) $id)
            ->all();

        $filterNames = DB::connection('titi')
            ->table('titi_product_filter as pf')
            ->join('titi_filter_description as fd', function ($j) {
                $j->on('pf.filter_id', '=', 'fd.filter_id')->where('fd.language_id', 2);
            })
            ->where('pf.product_id', $productId)
            ->pluck('fd.name')
            ->all();

        $rawHtml = $row->description ?? '';
        $withBreaks = preg_replace('/<(br\s*\/?\s*|\/p|\/li|\/div|\/td|\/h[1-6])\s*>/i', "\n", $rawHtml);
        $plain = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = preg_replace('/[^\S\n]+/', ' ', $plain);

        return [
            'product_id'   => $productId,
            'name'         => $row->name ?? '',
            'description'  => mb_substr($plain, 0, 800),
            'filter_ids'   => $filterIds,
            'filter_names' => $filterNames,
        ];
    }

    private function getCategoryPeers(int $productId, int $limit = 150): array
    {
        $categoryIds = DB::connection('titi')
            ->table('titi_product_to_category')
            ->where('product_id', $productId)
            ->pluck('category_id')
            ->all();

        if (empty($categoryIds)) return [];

        $cacheKey = 'product_peers_' . implode('-', $categoryIds);

        return Cache::remember($cacheKey, 300, function () use ($categoryIds, $limit) {
            $rows = DB::connection('titi')
                ->table('titi_product_to_category as pc')
                ->join('titi_product as p', 'pc.product_id', '=', 'p.product_id')
                ->join('titi_product_description as pd', function ($j) {
                    $j->on('pc.product_id', '=', 'pd.product_id')->where('pd.language_id', 2);
                })
                ->whereIn('pc.category_id', $categoryIds)
                ->where('p.status', 1)
                ->where('p.titi_eshop', 1)
                ->where('p.mopcena', '>', 0)
                ->select('p.product_id', 'pd.name')
                ->distinct()
                ->limit($limit)
                ->get();

            $productIds = $rows->pluck('product_id')->all();

            // Load filter IDs per product
            $filterRows = DB::connection('titi')
                ->table('titi_product_filter')
                ->whereIn('product_id', $productIds)
                ->select('product_id', 'filter_id')
                ->get()
                ->groupBy('product_id');

            return $rows->map(function ($r) use ($filterRows) {
                $filterIds = isset($filterRows[$r->product_id])
                    ? $filterRows[$r->product_id]->pluck('filter_id')->map(fn($id) => (int) $id)->all()
                    : [];
                return [
                    'product_id' => $r->product_id,
                    'name'       => $r->name,
                    'filter_ids' => $filterIds,
                ];
            })->all();
        });
    }

    private function filterVariantCandidates(array $target, array $peers): array
    {
        $targetTokens = $this->tokenizeName($target['name']);
        $targetMarker = $this->extractProductTypeMarker($target['name']);
        $candidates   = [];

        foreach ($peers as $peer) {
            if ($peer['product_id'] === $target['product_id']) continue;

            // ── L/P handedness guard ──────────────────────────────────────────
            // If target has a type marker (L=ľavák, P=pravák, etc.) and peer has
            // a DIFFERENT marker → fundamentally different product type → NOT a variant.
            // These will naturally become related product candidates instead.
            $peerMarker = $this->extractProductTypeMarker($peer['name']);
            if ($targetMarker !== null && $peerMarker !== null && $targetMarker !== $peerMarker) {
                continue;
            }

            $peerTokens  = $this->tokenizeName($peer['name']);
            $nameScore   = $this->jaccardSimilarity($targetTokens, $peerTokens);
            $filterScore = $this->filterJaccardSimilarity($target['filter_ids'], $peer['filter_ids']);

            // Variant signal: similar name OR very similar filter profile
            if ($nameScore >= 0.3 || $filterScore >= 0.4) {
                $candidates[] = array_merge($peer, ['_score' => $nameScore]);
            }
        }

        usort($candidates, fn($a, $b) => $b['_score'] <=> $a['_score']);
        return array_slice($candidates, 0, 30);
    }

    /**
     * Extract a product-type marker from the product name.
     * Detects standalone single uppercase letters that indicate a product subtype,
     * most commonly L (ľavák / left-handed) vs P (pravák / right-handed).
     *
     * Examples:
     *   "Strúhadlo Stabilo EASY L so zásobníkom žlté"  → "L"
     *   "Strúhadlo Stabilo EASY P so zásobníkom modré" → "P"
     *   "Pero BIC Cristal M modré"                     → "M"  (Medium)
     *   "Ceruzka HB"                                   → null (HB is not a single-letter marker)
     *
     * A valid marker is a token that is exactly one uppercase ASCII letter AND
     * appears after the first two tokens (brand/category prefix).
     */
    private function extractProductTypeMarker(string $name): ?string
    {
        // Split on whitespace and common separators (but preserve tokens)
        $tokens = preg_split('/[\s\/\-]+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);

        // Skip first 2 tokens (usually category + brand), then look for single uppercase letter
        $tokens = array_slice($tokens, 2);

        foreach ($tokens as $token) {
            // Must be exactly 1 character, uppercase ASCII letter
            if (preg_match('/^[A-Z]$/', $token)) {
                return $token;
            }
        }

        return null;
    }

    /**
     * Build the full candidate pool for related product suggestions.
     *
     * Two-source strategy:
     *  A) Same-category peers — products in the same category but NOT name-similar
     *     (name Jaccard < 0.45 → excludes colour/size variants)
     *  B) Cross-category peers — products from OTHER categories that share at least
     *     one filter value with the target (e.g. same "Formát: A5" → notebook cover
     *     appears as candidate for a notebook)
     *
     * Both pools are merged, de-duped, and the top 28 by relevance score are returned.
     */
    private function buildRelatedCandidates(int $productId, array $target): array
    {
        // IDs to always exclude
        $variantIds = DB::connection('titi')
            ->table('titi_product_variant')
            ->where('product_id', $productId)
            ->pluck('variant_id')
            ->map(fn($id) => (int) $id)
            ->all();
        $excludeIds = array_flip(array_merge([$productId], $variantIds));

        $targetTokens = $this->tokenizeName($target['name']);
        $seen         = $excludeIds; // track already-added product IDs

        $candidates = [];

        // ── Pool A: same-category peers ─────────────────────────────────────
        $sameCatPeers = $this->getCategoryPeers($productId, 200);

        foreach ($sameCatPeers as $peer) {
            if (isset($seen[$peer['product_id']])) continue;

            // Exclude near-duplicates — if name is very similar it's likely a variant
            $nameSim = $this->jaccardSimilarity($targetTokens, $this->tokenizeName($peer['name']));
            if ($nameSim >= 0.45) continue;

            $filterScore = $this->filterJaccardSimilarity($target['filter_ids'], $peer['filter_ids']);
            if ($filterScore >= 0.05) {
                $seen[$peer['product_id']] = true;
                // Slightly downweight same-category peers so cross-category ones
                // can compete despite having lower filter overlap
                $candidates[] = array_merge($peer, ['_score' => $filterScore * 0.8, '_source' => 'same_cat']);
            }
        }

        // ── Pool B: cross-category peers ────────────────────────────────────
        if (!empty($target['filter_ids'])) {
            $crossPeers = $this->getCrossCategoryPeers($productId, $target['filter_ids'], 120);

            foreach ($crossPeers as $peer) {
                if (isset($seen[$peer['product_id']])) continue;

                $nameSim = $this->jaccardSimilarity($targetTokens, $this->tokenizeName($peer['name']));
                if ($nameSim >= 0.45) continue;

                $filterScore = $this->filterJaccardSimilarity($target['filter_ids'], $peer['filter_ids']);
                if ($filterScore >= 0.03) {
                    $seen[$peer['product_id']] = true;
                    // Cross-category peers get a bonus — being in a different category
                    // is actually a strong signal of complementary purpose
                    $candidates[] = array_merge($peer, ['_score' => $filterScore * 1.3, '_source' => 'cross_cat']);
                }
            }
        }

        usort($candidates, fn($a, $b) => $b['_score'] <=> $a['_score']);
        return array_slice($candidates, 0, 28);
    }

    /**
     * Fetch products from categories OTHER than the target product's own categories,
     * that share at least one filter value with the target.
     * This is the key for finding complementary products (pencil → sharpener, etc.)
     */
    private function getCrossCategoryPeers(int $productId, array $filterIds, int $limit = 120): array
    {
        $ownCategoryIds = DB::connection('titi')
            ->table('titi_product_to_category')
            ->where('product_id', $productId)
            ->pluck('category_id')
            ->all();

        $rows = DB::connection('titi')
            ->table('titi_product_filter as pf')
            ->join('titi_product_to_category as pc', 'pf.product_id', '=', 'pc.product_id')
            ->join('titi_product as p', 'pf.product_id', '=', 'p.product_id')
            ->join('titi_product_description as pd', function ($j) {
                $j->on('pf.product_id', '=', 'pd.product_id')->where('pd.language_id', 2);
            })
            ->whereIn('pf.filter_id', $filterIds)
            ->where('pf.product_id', '!=', $productId)
            ->where('p.status', 1)
            ->where('p.titi_eshop', 1)
            ->where('p.mopcena', '>', 0)
            ->when(!empty($ownCategoryIds), fn($q) =>
                $q->whereNotIn('pc.category_id', $ownCategoryIds)
            )
            ->select('p.product_id', 'pd.name')
            ->distinct()
            ->limit($limit)
            ->get();

        $productIds = $rows->pluck('product_id')->all();
        if (empty($productIds)) return [];

        $filterRows = DB::connection('titi')
            ->table('titi_product_filter')
            ->whereIn('product_id', $productIds)
            ->select('product_id', 'filter_id')
            ->get()
            ->groupBy('product_id');

        return $rows->map(function ($r) use ($filterRows) {
            $fIds = isset($filterRows[$r->product_id])
                ? $filterRows[$r->product_id]->pluck('filter_id')->map(fn($id) => (int) $id)->all()
                : [];
            return ['product_id' => $r->product_id, 'name' => $r->name, 'filter_ids' => $fIds];
        })->all();
    }

    private function detectVariantsViaAI(array $target, array $candidates): array
    {
        $allCandidateIds = array_column($candidates, 'product_id');

        // Load filter names for candidates
        $filterNamesByProduct = [];
        if (!empty($allCandidateIds)) {
            $fRows = DB::connection('titi')
                ->table('titi_product_filter as pf')
                ->join('titi_filter_description as fd', function ($j) {
                    $j->on('pf.filter_id', '=', 'fd.filter_id')->where('fd.language_id', 2);
                })
                ->whereIn('pf.product_id', $allCandidateIds)
                ->select('pf.product_id', 'fd.name')
                ->get()
                ->groupBy('product_id');

            foreach ($fRows as $pid => $rows) {
                $filterNamesByProduct[$pid] = $rows->pluck('name')->take(5)->implode(', ');
            }
        }

        // Load descriptions for candidates
        $descByProduct = $this->loadCandidateDescriptions($allCandidateIds);

        $candidateLines = array_map(function ($c) use ($filterNamesByProduct, $descByProduct) {
            $fStr  = $filterNamesByProduct[$c['product_id']] ?? '';
            $fPart = $fStr ? " [{$fStr}]" : '';
            $desc  = $descByProduct[$c['product_id']] ?? '';
            $dPart = $desc ? " — \"{$desc}\"" : '';
            return "ID {$c['product_id']}: \"{$c['name']}\"{$fPart}{$dPart}";
        }, $candidates);

        // Detect if target has a type marker to include in prompt context
        $targetMarker = $this->extractProductTypeMarker($target['name']);
        $markerRule   = '';
        if ($targetMarker !== null) {
            $markerRule = "\n- DÔLEŽITÉ: Označenie modelu (napr. 'L'=ľavák, 'P'=pravák, 'M'=medium, atď.) "
                . "rozlišuje typ produktu — produkt s označením '{$targetMarker}' NESMIE byť variant "
                . "produktu s iným označením (napr. '" . ($targetMarker === 'L' ? 'P' : 'L') . "'). "
                . "Produkty pre ľavákov a pravákov sú rôzne typy, nie varianty!";
        }

        $systemPrompt = implode("\n", [
            "Si asistent pre e-shop. Zisti, ktoré produkty sú variantmi toho istého produktu.",
            "Varianty = PRESNE rovnaký produkt, líšia sa iba jednou kozmetickou vlastnosťou (farba, veľkosť/formát, objem, počet kusov v balení).",
            "NIE sú varianty:",
            "- iný model/typ produktu (napr. L=ľavák vs P=pravák)",
            "- príslušenstvo alebo doplnok",
            "- úplne iná kategória produktu" . $markerRule,
            "Ak si nie si istý podľa názvu, použi popis produktu na overenie.",
            "Odpovedaj iba JSON: {\"groups\": [[product_id, product_id, ...], ...]}",
            "Každá skupina obsahuje ID produktov, ktoré sú variantmi navzájom (vrátane cieľového produktu ak je variantom).",
            "Ak žiadny kandidát nie je variant cieľového produktu, vráť {\"groups\": []}.",
        ]);

        $targetDesc = $target['description'] ?? '';
        $targetDescPart = $targetDesc ? "\nPopis: \"" . mb_substr($targetDesc, 0, 500) . "\"" : '';

        $userPrompt = "Cieľový produkt (ID: {$target['product_id']}): \"{$target['name']}\"{$targetDescPart}\n\n"
            . "Kandidáti (len tí istého typu ako cieľový produkt):\n" . implode("\n", $candidateLines) . "\n\n"
            . "Vráť skupiny product_id, ktoré patria k sebe ako farební/veľkostní varianty.";

        try {
            $response = Http::timeout(25)->withHeaders([
                'Authorization' => 'Bearer ' . config('services.openai.key'),
                'Content-Type'  => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model'           => 'gpt-4o-mini',
                'temperature'     => 0,
                'max_tokens'      => 600,
                'response_format' => ['type' => 'json_object'],
                'messages'        => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
            ]);

            if (!$response->successful()) return [];

            $data = json_decode($response->json('choices.0.message.content', '{}'), true);
            return $data['groups'] ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function detectRelatedViaAI(array $target, array $candidates): array
    {
        $filterNamesStr = implode(', ', array_slice($target['filter_names'], 0, 8));

        // Load filter names for candidates
        $allCandidateIds = array_column($candidates, 'product_id');
        $filterNamesByProduct = [];
        if (!empty($allCandidateIds)) {
            $fRows = DB::connection('titi')
                ->table('titi_product_filter as pf')
                ->join('titi_filter_description as fd', function ($j) {
                    $j->on('pf.filter_id', '=', 'fd.filter_id')->where('fd.language_id', 2);
                })
                ->whereIn('pf.product_id', $allCandidateIds)
                ->select('pf.product_id', 'fd.name')
                ->get()
                ->groupBy('product_id');

            foreach ($fRows as $pid => $rows) {
                $filterNamesByProduct[$pid] = $rows->pluck('name')->take(5)->implode(', ');
            }
        }

        // Load descriptions for candidates
        $descByProduct = $this->loadCandidateDescriptions($allCandidateIds);

        $candidateLines = array_map(function ($c) use ($filterNamesByProduct, $descByProduct) {
            $fStr  = $filterNamesByProduct[$c['product_id']] ?? '';
            $fPart = $fStr ? " [{$fStr}]" : '';
            $desc  = $descByProduct[$c['product_id']] ?? '';
            $dPart = $desc ? " — \"{$desc}\"" : '';
            return "ID {$c['product_id']}: \"{$c['name']}\"{$fPart}{$dPart}";
        }, $candidates);

        $systemPrompt = implode("\n", [
            "Si asistent pre e-shop. Vyber KOMPLEMENTÁRNE produkty — také, ktoré zákazník prirodzene kúpi SPOLU s hlavným produktom.",
            "",
            "Pravidlá výberu:",
            "- Hľadaj PRÍSLUŠENSTVO a DOPLNKY, nie podobné produkty",
            "- Príklady správnych vzťahov: ceruzka→strúhadlo/guma, zošit→obal na zošit/menovky/registre, pero→náplň do pera/puzdro, farby→štetce/paleta, lepiaca páska→dávkovač pásky",
            "- Produkty môžu byť z INEJ kategórie — to je v poriadku (dokonca žiadúce)",
            "- Zdieľané vlastnosti (rovnaký formát A5, rovnaká značka) sú dobrý signál",
            "- Použi popis produktu na lepšie pochopenie jeho funkcie a použitia",
            "",
            "NEVYBERAJ:",
            "- Rovnaký produkt v inej farbe alebo veľkosti (to sú varianty, nie súvisiace)",
            "- Produkty ktoré nie sú nijako funkčne späté s hlavným produktom",
            "",
            "Odpovedaj iba JSON: {\"related\": [product_id, ...]}",
            "Vyber ideálne 3–8 produktov. Ak žiadny kandidát nie je skutočne komplementárny, vráť {\"related\": []}.",
        ]);

        $targetDesc = $target['description'] ?? '';
        $targetDescPart = $targetDesc ? "\nPopis: \"" . mb_substr($targetDesc, 0, 500) . "\"" : '';

        $filterPart = $filterNamesStr ? " [atribúty: {$filterNamesStr}]" : '';
        $userPrompt = "Hlavný produkt (ID: {$target['product_id']}): \"{$target['name']}\"{$filterPart}{$targetDescPart}\n\n"
            . "Kandidáti na posúdenie (môžu byť z rôznych kategórií):\n" . implode("\n", $candidateLines) . "\n\n"
            . "Vyber tie, ktoré zákazník prirodzene kúpi spolu s hlavným produktom (príslušenstvo, doplnky, komplementárne produkty).";

        try {
            $response = Http::timeout(25)->withHeaders([
                'Authorization' => 'Bearer ' . config('services.openai.key'),
                'Content-Type'  => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model'           => 'gpt-4o-mini',
                'temperature'     => 0,
                'max_tokens'      => 400,
                'response_format' => ['type' => 'json_object'],
                'messages'        => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
            ]);

            if (!$response->successful()) return [];

            $data = json_decode($response->json('choices.0.message.content', '{}'), true);
            return array_map('intval', $data['related'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function loadCandidateDescriptions(array $productIds): array
    {
        if (empty($productIds)) return [];

        return DB::connection('titi')
            ->table('titi_product_description')
            ->whereIn('product_id', $productIds)
            ->where('language_id', 2)
            ->pluck('description', 'product_id')
            ->map(function ($html) {
                $withBreaks = preg_replace('/<(br\s*\/?\s*|\/p|\/li|\/div)\s*>/i', ' ', $html ?? '');
                $plain = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                return mb_substr(preg_replace('/\s+/', ' ', trim($plain)), 0, 150);
            })
            ->filter()
            ->all();
    }

    private function saveVariantPairs(int $productId, array $variantIds): void
    {
        // Build all product IDs in the group (including the target)
        $allIds = array_unique(array_merge([$productId], $variantIds));

        // Expand: include existing variants of any product already in the group,
        // so adding product 4 to group {1,2,3} merges into one group {1,2,3,4}.
        $existingVariants = DB::connection('titi')
            ->table('titi_product_variant')
            ->whereIn('product_id', $allIds)
            ->pluck('variant_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $allIds = array_values(array_unique(array_merge($allIds, $existingVariants)));

        $rows   = [];

        foreach ($allIds as $idA) {
            foreach ($allIds as $idB) {
                if ($idA === $idB) continue;
                $rows[] = [
                    'product_id' => $idA,
                    'variant_id' => $idB,
                ];
            }
        }

        if (!empty($rows)) {
            DB::connection('titi')
                ->table('titi_product_variant')
                ->insertOrIgnore($rows);
        }
    }

    private function saveRelatedPairs(int $productId, array $relatedIds): void
    {
        $rows = [];
        foreach ($relatedIds as $relId) {
            $rows[] = ['product_id' => $productId, 'related_id' => $relId];
            $rows[] = ['product_id' => $relId,     'related_id' => $productId];
        }

        if (!empty($rows)) {
            DB::connection('titi')
                ->table('titi_product_related')
                ->insertOrIgnore($rows);
        }
    }

    private function loadProductList(array $productIds): array
    {
        if (empty($productIds)) return [];

        $descriptions = DB::connection('titi')
            ->table('titi_product_description')
            ->whereIn('product_id', $productIds)
            ->where('language_id', 2)
            ->pluck('name', 'product_id');

        $images = DB::connection('titi')
            ->table('titi_product_image')
            ->whereIn('product_id', $productIds)
            ->where('main', 1)
            ->pluck('image', 'product_id');

        $result = [];
        foreach ($productIds as $id) {
            $img = $images[$id] ?? null;
            $result[] = [
                'product_id' => $id,
                'name'       => $descriptions[$id] ?? "Produkt #{$id}",
                'image'      => $img ? "https://titi.shopweb.sk/{$img}" : null,
            ];
        }

        return $result;
    }
}
