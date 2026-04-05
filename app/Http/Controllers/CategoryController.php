<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class CategoryController extends Controller
{
    // Slovak color adjective forms → filter value (feminine noun form)
    private const COLOR_MAP = [
        'čierny' => 'čierna', 'čierna' => 'čierna', 'čierne' => 'čierna', 'čiernej' => 'čierna',
        'modrý' => 'modrá', 'modrá' => 'modrá', 'modré' => 'modrá', 'modrej' => 'modrá',
        'červený' => 'červená', 'červená' => 'červená', 'červené' => 'červená',
        'zelený' => 'zelená', 'zelená' => 'zelená', 'zelené' => 'zelená',
        'žltý' => 'žltá', 'žltá' => 'žltá', 'žlté' => 'žltá',
        'biely' => 'biela', 'biela' => 'biela', 'biele' => 'biela',
        'ružový' => 'ružová', 'ružová' => 'ružová', 'ružové' => 'ružová',
        'oranžový' => 'oranžová', 'oranžová' => 'oranžová', 'oranžové' => 'oranžová',
        'fialový' => 'fialová', 'fialová' => 'fialová', 'fialové' => 'fialová',
        'hnedý' => 'hnedá', 'hnedá' => 'hnedá', 'hnedé' => 'hnedá',
        'strieborný' => 'strieborná', 'strieborná' => 'strieborná', 'strieborné' => 'strieborná',
        'zlatý' => 'zlatá', 'zlatá' => 'zlatá', 'zlaté' => 'zlatá',
        'sivý' => 'sivá', 'sivá' => 'sivá', 'sivé' => 'sivá',
        'béžový' => 'béžová', 'béžová' => 'béžová',
        'transparentný' => 'transparentná', 'transparentná' => 'transparentná', 'transparentné' => 'transparentná',
        'bordový' => 'bordová', 'bordová' => 'bordová',
        'tyrkysový' => 'tyrkysová', 'tyrkysová' => 'tyrkysová',
        'svetlomodrý' => 'svetlomodrá', 'svetlomodrá' => 'svetlomodrá',
        'tmavomodrý' => 'tmavomodrá', 'tmavomodrá' => 'tmavomodrá',
        'svetlozelený' => 'svetlozelená', 'svetlozelená' => 'svetlozelená',
        'tmavozelený' => 'tmavozelená', 'tmavozelená' => 'tmavozelená',
        'neónový' => 'neónová', 'neónová' => 'neónová',
        'antracitový' => 'antracitová', 'antracitová' => 'antracitová',
        'dymový' => 'dymová', 'dymová' => 'dymová',
        'medený' => 'medená', 'medená' => 'medená',
    ];

    // Keyword-presence rules: if keyword found in text → assign value to group
    private const KEYWORD_VALUE_MAP = [
        'Permanentnosť' => [
            ['keywords' => ['permanentný', 'permanentná', 'permanentné', 'permanentne', 'permanent'], 'value' => 'áno'],
            ['keywords' => ['nepermanentný', 'nepermanentná', 'nepermanentné', 'nepermanent'], 'value' => 'nie'],
        ],
    ];

    // Color-related filter groups (need special adjective→noun mapping)
    private const COLOR_GROUPS = ['Farba', 'Farba náplne', 'Farba tela'];
    public function index()
    {
        $categories = $this->getCategoryTree();

        // Count products per category
        $counts = DB::connection('titi')
            ->table('titi_product_to_category as pc')
            ->join('titi_product as p', 'pc.product_id', '=', 'p.product_id')
            ->where('p.titi_eshop', 1)
            ->where('p.mopcena', '>', 0)
            ->select('pc.category_id', DB::raw('COUNT(*) as product_count'))
            ->groupBy('pc.category_id')
            ->pluck('product_count', 'category_id');

        foreach ($categories as &$cat) {
            $cat['product_count'] = $counts[$cat['category_id']] ?? 0;
        }

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function show(int $categoryId)
    {
        $category = DB::connection('titi')
            ->table('titi_category as c')
            ->join('titi_category_description as cd', function ($j) {
                $j->on('c.category_id', '=', 'cd.category_id')
                  ->where('cd.language_id', 2);
            })
            ->where('c.category_id', $categoryId)
            ->select('c.category_id', 'cd.name')
            ->first();

        if (!$category) {
            abort(404);
        }

        $products = Product::join('titi_product_to_category as pc', 'titi_product.product_id', '=', 'pc.product_id')
            ->join('titi_product_description as pd', function ($j) {
                $j->on('titi_product.product_id', '=', 'pd.product_id')
                  ->where('pd.language_id', 2);
            })
            ->where('pc.category_id', $categoryId)
            ->where('titi_product.status', 1)
            ->where('titi_product.titi_eshop', 1)
            ->where('titi_product.mopcena', '>', 0)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('titi_stavskladu')
                    ->whereColumn('titi_stavskladu.product_id', 'titi_product.product_id')
                    ->where('titi_stavskladu.celkom', '>', 1);
            })
            ->select(
                'titi_product.product_id',
                'pd.name as desc_name',
                'pd.description as desc_text'
            )
            ->with('mainImage')
            ->orderBy('pd.name')
            ->get()
            ->map(function ($p) {
                $image    = $p->mainImage ? $p->mainImage->image : null;
                $descText = $this->safeUtf8($p->desc_text ?? '');
                return [
                    'product_id'       => $p->product_id,
                    'name'             => $this->safeUtf8($p->desc_name ?? ''),
                    'description'      => $descText ? mb_substr(strip_tags($descText), 0, 300) : '',
                    'description_html' => $descText,
                    'image'            => $image ? "https://titi.shopweb.sk/{$image}" : null,
                ];
            });

        // Mark which products already have at least one filter assigned
        $productIds     = $products->pluck('product_id')->all();
        $withFiltersIds = DB::connection('titi')
            ->table('titi_product_filter')
            ->whereIn('product_id', $productIds)
            ->distinct()
            ->pluck('product_id')
            ->flip()           // convert to [id => index] for O(1) lookup
            ->all();

        $products = $products->map(fn($p) => array_merge($p, [
            'has_filters' => array_key_exists($p['product_id'], $withFiltersIds),
        ]));

        return Inertia::render('Categories/Show', [
            'category'   => ['category_id' => $category->category_id, 'name' => $category->name],
            'products'   => $products,
            'categories' => $this->getCategoryTree(),
            'filters'    => $this->getFilterList(),
        ]);
    }

    public function getProductCategories(int $productId)
    {
        $categoryIds = DB::connection('titi')
            ->table('titi_product_to_category')
            ->where('product_id', $productId)
            ->pluck('category_id');

        return response()->json($categoryIds);
    }

    public function syncProductCategories(Request $request, int $productId)
    {
        $categoryIds = array_values(array_unique(
            array_map('intval', (array) $request->input('category_ids', []))
        ));

        $table = DB::connection('titi')->table('titi_product_to_category');
        $table->where('product_id', $productId)->delete();

        if (!empty($categoryIds)) {
            $rows = array_map(fn($catId) => [
                'product_id'  => $productId,
                'category_id' => $catId,
            ], $categoryIds);
            $table->insert($rows);
        }

        return response()->json(['success' => true, 'count' => count($categoryIds)]);
    }

    public function getProductFilters(int $productId)
    {
        $filterIds = DB::connection('titi')
            ->table('titi_product_filter')
            ->where('product_id', $productId)
            ->pluck('filter_id');

        return response()->json($filterIds);
    }

    public function syncProductFilters(Request $request, int $productId)
    {
        $filterIds = array_values(array_unique(
            array_map('intval', (array) $request->input('filter_ids', []))
        ));

        $table = DB::connection('titi')->table('titi_product_filter');
        $table->where('product_id', $productId)->delete();

        if (!empty($filterIds)) {
            $rows = array_map(fn($fId) => [
                'product_id' => $productId,
                'filter_id'  => $fId,
            ], $filterIds);
            $table->insert($rows);
        }

        $this->invalidateCategoryCache($productId);

        return response()->json(['success' => true, 'count' => count($filterIds)]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function safeUtf8(string $text): string
    {
        $clean = iconv('UTF-8', 'UTF-8//IGNORE', $text);
        return $clean !== false ? $clean : '';
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  FILTER SUGGESTION v2 — Smart Matcher with Sibling Transfer
    // ═══════════════════════════════════════════════════════════════════════════

    public function suggestFilters(int $productId)
    {
        $product = DB::connection('titi')
            ->table('titi_product_description')
            ->where('product_id', $productId)
            ->where('language_id', 2)
            ->select('name', 'description')
            ->first();

        if (!$product) {
            return response()->json(['suggestions' => []]);
        }

        $filters = $this->getFilterList();
        $grouped = collect($filters)->groupBy('group_name');

        // ── HTML → plain text ─────────────────────────────────────────────────
        $rawHtml    = $this->safeUtf8($product->description ?? '');
        $withBreaks = preg_replace('/<(br\s*\/?\s*|\/p|\/li|\/div|\/td|\/h[1-6])\s*>/i', "\n", $rawHtml);
        $plain      = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain      = preg_replace('/[^\S\n]+/', ' ', $plain);
        $productName = $this->safeUtf8($product->name ?? '');
        $fullText    = $productName . "\n" . $plain;

        $profile      = $this->getCategoryFilterProfile($productId);
        $usedGroups   = [];
        $levelsUsed   = [];
        $allSuggestions = [];

        // ── STEP 0: Category Defaults (confidence: high) ────────────────────
        // Values that appear in 85%+ of products in this category/group
        // These are "almost always correct" (e.g. Permanentnosť: áno)
        // Learned automatically from manual assignments
        $defaultResults = $this->applyCategoryDefaults($grouped, $usedGroups, $profile);
        if (!empty($defaultResults)) {
            $levelsUsed[] = 'category_defaults';
            foreach ($defaultResults as $d) {
                $usedGroups[] = $d['group_name'];
                $allSuggestions[] = $d;
            }
        }

        // ── STEP 1: Sibling Transfer (confidence: high) ─────────────────────
        // Find the most similar product in the same category that already has
        // filters → clone its filters, adjusting color from product name
        $siblingResults = $this->transferFromSibling($productId, $productName, $grouped);
        if (!empty($siblingResults)) {
            $levelsUsed[] = 'sibling_transfer';
            foreach ($siblingResults as $s) {
                if (!in_array($s['group_name'], $usedGroups)) {
                    $usedGroups[] = $s['group_name'];
                    $allSuggestions[] = $s;
                }
            }
        }

        // ── STEP 2: Rule-based extraction (confidence: high) ────────────────
        // Color from product name, brand detection + learned supplier mapping,
        // keyword rules, structured Key:Value patterns in description
        $ruleResults = $this->extractByRules($productName, $fullText, $grouped, $usedGroups, $profile);
        if (!empty($ruleResults)) {
            $levelsUsed[] = 'rules';
            foreach ($ruleResults as $r) {
                $usedGroups[] = $r['group_name'];
                $allSuggestions[] = $r;
            }
        }

        // ── STEP 3: Smart value scan (confidence: medium) ───────────────────
        // For remaining expected groups, scan text for known filter values
        $scanResults = $this->smartValueScan($fullText, $grouped, $usedGroups, $profile);
        if (!empty($scanResults)) {
            $levelsUsed[] = 'value_scan';
            foreach ($scanResults as $r) {
                $usedGroups[] = $r['group_name'];
                $allSuggestions[] = $r;
            }
        }

        // ── STEP 4: AI fallback (confidence: low) ───────────────────────────
        $aiUsed = false;
        if (count($allSuggestions) < 2 && !empty($profile['expected_groups'])) {
            $aiResults = $this->extractViaAI($productName, $plain, $grouped, $usedGroups, $profile);
            if (!empty($aiResults)) {
                $levelsUsed[] = 'ai';
                $aiUsed       = true;
                foreach ($aiResults as $r) {
                    $allSuggestions[] = $r;
                }
            }
        }

        // Sort: high → medium → low
        $order = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($allSuggestions, fn($a, $b) =>
            ($order[$a['confidence']] ?? 2) - ($order[$b['confidence']] ?? 2)
        );

        return response()->json([
            'suggestions'    => $allSuggestions,
            '_debug'         => [
                'levels_used'           => $levelsUsed,
                'ai_used'               => $aiUsed,
                'products_with_filters' => $profile['products_with_filters'],
                'dominant_groups'       => array_keys($profile['dominant_values']),
                'known_brands'          => array_keys($profile['brand_supplier']),
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  STEP 0: Category Defaults (learned from manual assignments)
    // ═══════════════════════════════════════════════════════════════════════════

    private function applyCategoryDefaults($grouped, array &$usedGroups, array $profile): array
    {
        $suggestions = [];

        foreach ($profile['dominant_values'] as $groupName => $info) {
            if (in_array($groupName, $usedGroups)) continue;
            if (!$grouped->has($groupName)) continue;
            // Skip color groups — color varies per product, defaults are misleading
            if (in_array($groupName, self::COLOR_GROUPS)) continue;

            $suggestions[] = [
                'filter_id'  => $info['filter_id'],
                'group_name' => $groupName,
                'name'       => $info['name'],
                'confidence' => 'high',
                'is_new'     => false,
            ];
        }

        return $suggestions;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  STEP 1: Sibling Transfer
    // ═══════════════════════════════════════════════════════════════════════════

    private function transferFromSibling(int $productId, string $productName, $grouped): array
    {
        $categoryIds = DB::connection('titi')
            ->table('titi_product_to_category')
            ->where('product_id', $productId)
            ->pluck('category_id');

        if ($categoryIds->isEmpty()) return [];

        // Cached list of siblings with filters in these categories
        $cacheKey = 'cat_siblings_' . $categoryIds->sort()->implode('-');
        $siblings = Cache::remember($cacheKey, 300, function () use ($categoryIds) {
            return DB::connection('titi')
                ->table('titi_product_to_category as pc')
                ->join('titi_product_description as pd', function ($j) {
                    $j->on('pc.product_id', '=', 'pd.product_id')
                      ->where('pd.language_id', 2);
                })
                ->whereIn('pc.category_id', $categoryIds)
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('titi_product_filter')
                        ->whereColumn('titi_product_filter.product_id', 'pc.product_id');
                })
                ->select('pc.product_id', 'pd.name')
                ->distinct()
                ->limit(200)
                ->get()
                ->map(fn($r) => ['product_id' => $r->product_id, 'name' => $r->name])
                ->all(); // Store as plain array for safe cache serialization
        });

        if (empty($siblings)) return [];

        // Tokenize current product name
        $myTokens = $this->tokenizeName($productName);
        if (empty($myTokens)) return [];

        // Find the best-matching sibling by Jaccard token similarity
        $bestSibling = null;
        $bestScore   = 0;

        foreach ($siblings as $sibling) {
            if ($sibling['product_id'] == $productId) continue;
            $sibTokens = $this->tokenizeName($sibling['name'] ?? '');
            $score     = $this->jaccardSimilarity($myTokens, $sibTokens);
            if ($score > $bestScore) {
                $bestScore   = $score;
                $bestSibling = $sibling;
            }
        }

        // Need at least 50% token overlap
        if (!$bestSibling || $bestScore < 0.5) return [];

        // Get the sibling's assigned filters
        $siblingFilters = DB::connection('titi')
            ->table('titi_product_filter as pf')
            ->join('titi_filter as f', 'pf.filter_id', '=', 'f.filter_id')
            ->join('titi_filter_description as fd', function ($j) {
                $j->on('pf.filter_id', '=', 'fd.filter_id')
                  ->where('fd.language_id', 2);
            })
            ->join('titi_filter_group_description as fgd', function ($j) {
                $j->on('f.filter_group_id', '=', 'fgd.filter_group_id')
                  ->where('fgd.language_id', 2);
            })
            ->where('pf.product_id', $bestSibling['product_id'])
            ->select('pf.filter_id', 'fd.name', 'fgd.name as group_name')
            ->get();

        if ($siblingFilters->isEmpty()) return [];

        // Detect color difference between current product and sibling
        $myColor  = $this->extractColorFromName($productName);
        $sibColor = $this->extractColorFromName($bestSibling['name'] ?? '');
        $colorChanged = $myColor && $sibColor
            && mb_strtolower($myColor) !== mb_strtolower($sibColor);
        $isSet = (bool) preg_match('/sada|set|mix\s+\d/iu', $productName);

        $suggestions = [];

        foreach ($siblingFilters as $f) {
            // For set products: skip color groups entirely (let rules/scan handle "mix N farieb")
            if (in_array($f->group_name, self::COLOR_GROUPS) && $isSet) {
                continue;
            }

            // For color groups: if the product name has a different color, adjust
            if (in_array($f->group_name, self::COLOR_GROUPS) && $colorChanged) {
                $normalizedColor = self::COLOR_MAP[mb_strtolower($myColor)] ?? null;
                if ($normalizedColor && $grouped->has($f->group_name)) {
                    $colorMatch = $grouped[$f->group_name]->first(
                        fn($filter) => mb_strtolower($filter['name']) === mb_strtolower($normalizedColor)
                    );
                    if ($colorMatch) {
                        $suggestions[] = [
                            'filter_id'  => $colorMatch['filter_id'],
                            'group_name' => $f->group_name,
                            'name'       => $colorMatch['name'],
                            'confidence' => 'high',
                            'is_new'     => false,
                        ];
                        continue;
                    }
                    // Color exists in map but not in DB → suggest as new
                    $suggestions[] = [
                        'filter_id'  => null,
                        'group_name' => $f->group_name,
                        'name'       => $normalizedColor,
                        'confidence' => 'high',
                        'is_new'     => true,
                    ];
                    continue;
                }
            }

            // For non-color groups (or no color change): clone as-is
            $suggestions[] = [
                'filter_id'  => (int) $f->filter_id,
                'group_name' => $f->group_name,
                'name'       => $f->name,
                'confidence' => 'high',
                'is_new'     => false,
            ];
        }

        return $suggestions;
    }

    // ─── Tokenize product name for similarity comparison ────────────────────

    private function tokenizeName(string $name): array
    {
        $name   = mb_strtolower(trim($name));
        $tokens = preg_split('/[\s\/,;.()\[\]{}]+/', $name, -1, PREG_SPLIT_NO_EMPTY);
        // Keep tokens with 2+ characters (drop "a", "s", "v", etc.)
        return array_values(array_filter($tokens, fn($t) => mb_strlen($t) >= 2));
    }

    // ─── Jaccard similarity between two token arrays ────────────────────────

    private function jaccardSimilarity(array $a, array $b): float
    {
        $setA = array_unique($a);
        $setB = array_unique($b);
        $intersection = count(array_intersect($setA, $setB));
        $union        = count(array_unique(array_merge($setA, $setB)));
        return $union > 0 ? $intersection / $union : 0.0;
    }

    // ─── Extract color adjective from product name (rightmost match) ────────

    private function extractColorFromName(string $name): ?string
    {
        $tokens = preg_split('/[\s\/,;.()\[\]{}]+/', mb_strtolower(trim($name)), -1, PREG_SPLIT_NO_EMPTY);
        // Color is typically the last word in the product name
        foreach (array_reverse($tokens) as $token) {
            if (isset(self::COLOR_MAP[$token])) {
                return $token;
            }
        }
        return null;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  STEP 2: Rule-based extraction
    // ═══════════════════════════════════════════════════════════════════════════

    private function extractByRules(string $productName, string $fullText, $grouped, array &$usedGroups, array $profile): array
    {
        $suggestions = [];
        $textLower   = mb_strtolower($fullText);

        // ── Rule A: Color from product name using Slovak adjective map ───────
        // Color in name refers to body/appearance, NOT ink color (Farba náplne)
        $nameColorGroups = array_diff(self::COLOR_GROUPS, ['Farba náplne']);
        foreach ($nameColorGroups as $colorGroup) {
            if (in_array($colorGroup, $usedGroups)) continue;
            if (!$grouped->has($colorGroup)) continue;
            if (!isset($profile['expected_groups'][$colorGroup])) continue;

            $colorAdj = $this->extractColorFromName($productName);
            if (!$colorAdj) continue;

            $normalizedColor = self::COLOR_MAP[mb_strtolower($colorAdj)] ?? null;
            if (!$normalizedColor) continue;

            $match = $grouped[$colorGroup]->first(
                fn($f) => mb_strtolower($f['name']) === mb_strtolower($normalizedColor)
            );
            if ($match) {
                $suggestions[] = [
                    'filter_id'  => $match['filter_id'],
                    'group_name' => $colorGroup,
                    'name'       => $match['name'],
                    'confidence' => 'high',
                    'is_new'     => false,
                ];
                $usedGroups[] = $colorGroup;
            }
        }

        // ── Rule B: Brand detection from product name → Značka + Dodávateľ ────
        if (!in_array('Značka', $usedGroups) && $grouped->has('Značka')) {
            $nameTokens = preg_split('/[\s\/,;.()\[\]{}]+/', $productName, -1, PREG_SPLIT_NO_EMPTY);
            $brandMap = $profile['brand_supplier'] ?? [];

            // Check each token against known brand filter values
            foreach ($nameTokens as $token) {
                if (mb_strlen($token) < 2) continue;
                $tokenLower = mb_strtolower($token);

                // First check against learned brand→supplier map
                foreach ($brandMap as $brand => $info) {
                    if (mb_strtolower($brand) === $tokenLower) {
                        // Found brand
                        $suggestions[] = [
                            'filter_id'  => $info['brand_filter_id'],
                            'group_name' => 'Značka',
                            'name'       => $brand,
                            'confidence' => 'high',
                            'is_new'     => false,
                        ];
                        $usedGroups[] = 'Značka';

                        // Also assign linked Dodávateľ if not already used
                        if (!in_array('Dodávateľ', $usedGroups) && $grouped->has('Dodávateľ')) {
                            $suggestions[] = [
                                'filter_id'  => $info['supplier_filter_id'],
                                'group_name' => 'Dodávateľ',
                                'name'       => $info['supplier'],
                                'confidence' => 'high',
                                'is_new'     => false,
                            ];
                            $usedGroups[] = 'Dodávateľ';
                        }
                        break 2; // found brand, stop searching
                    }
                }

                // Fallback: check against all Značka filter values (no supplier link)
                if (!in_array('Značka', $usedGroups)) {
                    $brandMatch = $grouped['Značka']->first(
                        fn($f) => mb_strtolower($f['name']) === $tokenLower
                    );
                    if ($brandMatch) {
                        $suggestions[] = [
                            'filter_id'  => $brandMatch['filter_id'],
                            'group_name' => 'Značka',
                            'name'       => $brandMatch['name'],
                            'confidence' => 'high',
                            'is_new'     => false,
                        ];
                        $usedGroups[] = 'Značka';
                        break;
                    }
                }
            }
        }

        // ── Rule C: Keyword presence rules (e.g. "permanentný" → áno) ────────
        foreach (self::KEYWORD_VALUE_MAP as $groupName => $rules) {
            if (in_array($groupName, $usedGroups)) continue;
            if (!$grouped->has($groupName)) continue;

            foreach ($rules as $rule) {
                $found = false;
                foreach ($rule['keywords'] as $keyword) {
                    if (str_contains($textLower, mb_strtolower($keyword))) {
                        $found = true;
                        break;
                    }
                }
                if ($found) {
                    $match = $grouped[$groupName]->first(
                        fn($f) => mb_strtolower($f['name']) === mb_strtolower($rule['value'])
                    );
                    if ($match) {
                        $suggestions[] = [
                            'filter_id'  => $match['filter_id'],
                            'group_name' => $groupName,
                            'name'       => $match['name'],
                            'confidence' => 'high',
                            'is_new'     => false,
                        ];
                        $usedGroups[] = $groupName;
                        break; // first matching rule wins
                    }
                }
            }
        }

        // ── Rule D: Structured Key:Value patterns in description ─────────────
        preg_match_all('/^([^:\n]{2,60}):\s*(.+)$/mu', $fullText, $matches, PREG_SET_ORDER);
        $groupNames = $grouped->keys()->all();

        foreach ($matches as $m) {
            $key = trim($m[1]);
            $val = trim($m[2]);
            if ($key === '' || $val === '') continue;

            $matchedGroup = $this->fuzzyMatchGroup($key, $groupNames);
            if (!$matchedGroup || in_array($matchedGroup, $usedGroups)) continue;

            $filterMatch = $this->matchValue($val, $grouped[$matchedGroup]);
            if ($filterMatch) {
                $suggestions[] = [
                    'filter_id'  => $filterMatch['filter_id'],
                    'group_name' => $matchedGroup,
                    'name'       => $filterMatch['name'],
                    'confidence' => 'high',
                    'is_new'     => false,
                ];
            } else {
                $suggestions[] = [
                    'filter_id'  => null,
                    'group_name' => $matchedGroup,
                    'name'       => $val,
                    'confidence' => 'medium',
                    'is_new'     => true,
                ];
            }
            $usedGroups[] = $matchedGroup;
        }

        return $suggestions;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  STEP 3: Smart value scan (improved)
    // ═══════════════════════════════════════════════════════════════════════════

    private function smartValueScan(string $text, $grouped, array $usedGroups, array $profile): array
    {
        $results   = [];
        $textLower = mb_strtolower($text);

        foreach ($profile['expected_groups'] as $groupName => $count) {
            if (in_array($groupName, $usedGroups)) continue;
            if (!$grouped->has($groupName)) continue;
            // Color groups handled by rules/sibling → skip to avoid false positives
            if (in_array($groupName, self::COLOR_GROUPS)) continue;

            foreach ($grouped[$groupName] as $filter) {
                $valueLower = mb_strtolower($filter['name']);
                // Skip short values (e.g. "nie", "áno") — too many false positives
                if (mb_strlen($valueLower) < 4) continue;

                // Standard word-boundary match
                if ($this->containsWord($textLower, $valueLower)) {
                    $results[] = [
                        'filter_id'  => $filter['filter_id'],
                        'group_name' => $groupName,
                        'name'       => $filter['name'],
                        'confidence' => 'medium',
                        'is_new'     => false,
                    ];
                    break; // one value per group
                }

                // Normalized match: strip spaces around numbers/units
                // e.g. "0,7 mm" matches "0,7mm" and vice versa
                $valNorm  = preg_replace('/\s+/', '', $valueLower);
                $textNorm = preg_replace('/\s+/', '', $textLower);
                if (mb_strlen($valNorm) >= 3 && $valNorm !== $valueLower && str_contains($textNorm, $valNorm)) {
                    $results[] = [
                        'filter_id'  => $filter['filter_id'],
                        'group_name' => $groupName,
                        'name'       => $filter['name'],
                        'confidence' => 'medium',
                        'is_new'     => false,
                    ];
                    break;
                }
            }
        }
        return $results;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  STEP 4: AI extraction (last resort)
    // ═══════════════════════════════════════════════════════════════════════════

    private function extractViaAI(string $productName, string $plain, $grouped, array $usedGroups, array $profile): array
    {
        if (!config('services.openai.key')) {
            return [];
        }

        $missingGroups = array_diff(array_keys($profile['expected_groups']), $usedGroups);
        if (empty($missingGroups)) {
            return [];
        }

        // Build a list of valid values per group so AI can pick from them
        $groupOptions = [];
        foreach ($missingGroups as $gn) {
            if (!$grouped->has($gn)) continue;
            $values = $grouped[$gn]->pluck('name')->take(30)->all();
            $groupOptions[$gn] = $values;
        }

        if (empty($groupOptions)) return [];

        $groupLines = [];
        foreach ($groupOptions as $gn => $values) {
            $groupLines[] = "  {$gn}: [" . implode(', ', $values) . "]";
        }
        $groupBlock = implode("\n", $groupLines);

        $examplesBlock = '';
        if (!empty($profile['examples'])) {
            $lines = array_map(
                fn($ex) => "  - \"{$ex['name']}\": {$ex['filters']}",
                $profile['examples']
            );
            $examplesBlock = "\n\nPríklady produktov z tejto kategórie:\n"
                . implode("\n", $lines);
        }

        $systemPrompt = implode("\n", [
            "Si extractor atribútov produktov pre e-shop.",
            "Z popisu produktu extrahuj hodnoty PRE TIETO SKUPINY (vyber z povolených hodnôt):",
            $groupBlock,
            $examplesBlock,
            "",
            "Pravidlá:",
            "- Iba hodnoty EXPLICITNE uvedené alebo jednoznačne vyplývajúce z textu",
            "- Pre každú skupinu max 1 hodnota",
            "- PREDNOSTNE vyber hodnotu z povolených hodnôt (v zátvorkách vyššie)",
            "- Ak pre skupinu nie je hodnota v texte, vynechaj ju",
            "- key = presný názov skupiny zo zoznamu",
            "- value = presná hodnota (z povolených hodnôt ak sa zhoduje)",
        ]);

        $userPrompt = "Názov: {$productName}\n"
            . "Popis: " . mb_substr($plain, 0, 2000) . "\n\n"
            . "Odpovedz JSON: {\"attributes\": [{\"key\": \"...\", \"value\": \"...\"}]}";

        try {
            $response = Http::timeout(15)->withHeaders([
                'Authorization' => 'Bearer ' . config('services.openai.key'),
                'Content-Type'  => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model'           => 'gpt-4o-mini',
                'temperature'     => 0,
                'max_tokens'      => 256,
                'response_format' => ['type' => 'json_object'],
                'messages'        => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
            ]);

            if (!$response->successful()) return [];

            $data = json_decode($response->json('choices.0.message.content', '{}'), true);
        } catch (\Exception $e) {
            return [];
        }

        $result = [];
        foreach ($data['attributes'] ?? [] as $attr) {
            $key = trim($attr['key'] ?? '');
            $val = trim($attr['value'] ?? '');
            if ($key === '' || $val === '') continue;
            if (in_array($key, $usedGroups)) continue;

            // Try to match AI's value to an existing filter
            if ($grouped->has($key)) {
                $filterMatch = $this->matchValue($val, $grouped[$key]);
                if ($filterMatch) {
                    $result[] = [
                        'filter_id'  => $filterMatch['filter_id'],
                        'group_name' => $key,
                        'name'       => $filterMatch['name'],
                        'confidence' => 'low',
                        'is_new'     => false,
                    ];
                    continue;
                }
            }

            $result[] = [
                'filter_id'  => null,
                'group_name' => $key,
                'name'       => $val,
                'confidence' => 'low',
                'is_new'     => true,
            ];
        }

        return $result;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  Shared helpers
    // ═══════════════════════════════════════════════════════════════════════════

    private function matchValue(string $rawValue, $groupFilters): ?array
    {
        $valLower = mb_strtolower(trim($rawValue));

        // 1. Exact case-insensitive
        $match = $groupFilters->first(fn($f) => mb_strtolower($f['name']) === $valLower);
        if ($match) return $match;

        // 2. Normalized: strip/normalize units and spaces around numbers
        $normalized = preg_replace('/\s*(mm|cm|ks|ml|db|kg|m|g|l)\s*$/iu', '', $valLower);
        $normalized = trim($normalized);
        if ($normalized !== $valLower) {
            $match = $groupFilters->first(function ($f) use ($normalized) {
                $fn = preg_replace('/\s*(mm|cm|ks|ml|db|kg|m|g|l)\s*$/iu', '', mb_strtolower($f['name']));
                return trim($fn) === $normalized;
            });
            if ($match) return $match;
        }

        // 3. Space-insensitive comparison (e.g. "0,7mm" vs "0,7 mm")
        $valNoSpace = preg_replace('/\s+/', '', $valLower);
        if ($valNoSpace !== $valLower) {
            $match = $groupFilters->first(fn($f) =>
                preg_replace('/\s+/', '', mb_strtolower($f['name'])) === $valNoSpace
            );
            if ($match) return $match;
        }

        // 4. Partial containment (min 4 chars)
        if (mb_strlen($valLower) >= 4) {
            $match = $groupFilters->first(fn($f) =>
                str_contains(mb_strtolower($f['name']), $valLower) ||
                str_contains($valLower, mb_strtolower($f['name']))
            );
            if ($match) return $match;
        }

        return null;
    }

    private function fuzzyMatchGroup(string $key, array $groupNames): ?string
    {
        $keyLower = mb_strtolower(trim($key));

        // 1. Exact match
        foreach ($groupNames as $gn) {
            if (mb_strtolower($gn) === $keyLower) return $gn;
        }

        // 2. Key contains group name (longest match wins, min 5 chars)
        $best    = null;
        $bestLen = 0;
        foreach ($groupNames as $gn) {
            $gnLower = mb_strtolower($gn);
            if (mb_strlen($gnLower) >= 5 && str_contains($keyLower, $gnLower) && mb_strlen($gnLower) > $bestLen) {
                $best    = $gn;
                $bestLen = mb_strlen($gnLower);
            }
        }
        if ($best) return $best;

        // 3. Group contains key (min 5 chars for key)
        if (mb_strlen($keyLower) >= 5) {
            foreach ($groupNames as $gn) {
                if (str_contains(mb_strtolower($gn), $keyLower)) return $gn;
            }
        }

        return null;
    }

    private function containsWord(string $haystack, string $needle): bool
    {
        $escaped = preg_quote($needle, '/');
        return (bool) preg_match('/(?<=\s|^|[,;.:()\-])' . $escaped . '(?=\s|$|[,;.:()\-])/iu', $haystack);
    }

    // ─── Category filter profile with cache (self-learning) ────────────────

    private function getCategoryFilterProfile(int $productId): array
    {
        $categoryIds = DB::connection('titi')
            ->table('titi_product_to_category')
            ->where('product_id', $productId)
            ->pluck('category_id');

        if ($categoryIds->isEmpty()) {
            return [
                'expected_groups'  => [],
                'dominant_values'  => [],
                'brand_supplier'   => [],
                'total_products'   => 0,
                'products_with_filters' => 0,
                'examples'         => [],
            ];
        }

        $cacheKey = 'cat_profile_' . $categoryIds->sort()->implode('-');

        return Cache::remember($cacheKey, 300, function () use ($categoryIds) {
            $totalProducts = DB::connection('titi')
                ->table('titi_product_to_category')
                ->whereIn('category_id', $categoryIds)
                ->distinct()
                ->count('product_id');

            $productsWithFilters = DB::connection('titi')
                ->table('titi_product_to_category as pc')
                ->join('titi_product_filter as pf', 'pc.product_id', '=', 'pf.product_id')
                ->whereIn('pc.category_id', $categoryIds)
                ->distinct()
                ->count('pc.product_id');

            // ── Per-group stats: which groups + which values are most common ──
            $valueStats = DB::connection('titi')
                ->table('titi_product_to_category as pc')
                ->join('titi_product_filter as pf', 'pc.product_id', '=', 'pf.product_id')
                ->join('titi_filter as f', 'pf.filter_id', '=', 'f.filter_id')
                ->join('titi_filter_description as fd', function ($j) {
                    $j->on('pf.filter_id', '=', 'fd.filter_id')
                      ->where('fd.language_id', 2);
                })
                ->join('titi_filter_group_description as fgd', function ($j) {
                    $j->on('f.filter_group_id', '=', 'fgd.filter_group_id')
                      ->where('fgd.language_id', 2);
                })
                ->whereIn('pc.category_id', $categoryIds)
                ->select(
                    'fgd.name as group_name',
                    'fd.name as value_name',
                    'pf.filter_id',
                    DB::raw('COUNT(DISTINCT pc.product_id) as cnt')
                )
                ->groupBy('fgd.name', 'fd.name', 'pf.filter_id')
                ->orderByDesc('cnt')
                ->get()
                ->groupBy('group_name');

            // Batch: count distinct products per group (single query, no N+1)
            $groupProductCounts = DB::connection('titi')
                ->table('titi_product_to_category as pc')
                ->join('titi_product_filter as pf', 'pc.product_id', '=', 'pf.product_id')
                ->join('titi_filter as f', 'pf.filter_id', '=', 'f.filter_id')
                ->join('titi_filter_group_description as fgd', function ($j) {
                    $j->on('f.filter_group_id', '=', 'fgd.filter_group_id')
                      ->where('fgd.language_id', 2);
                })
                ->whereIn('pc.category_id', $categoryIds)
                ->select('fgd.name as group_name', DB::raw('COUNT(DISTINCT pc.product_id) as cnt'))
                ->groupBy('fgd.name')
                ->pluck('cnt', 'group_name')
                ->all();

            $expectedGroups = [];
            $dominantValues = [];
            $threshold = max(2, $productsWithFilters * 0.3);

            foreach ($valueStats as $groupName => $values) {
                $groupProductCount = $groupProductCounts[$groupName] ?? 0;

                if ($groupProductCount >= $threshold) {
                    $expectedGroups[$groupName] = $groupProductCount;
                }

                // Dominant value: one value in 85%+ of products with this group
                $topValue = $values->first();
                if ($groupProductCount >= 5 && $topValue->cnt / $groupProductCount >= 0.85) {
                    $dominantValues[$groupName] = [
                        'filter_id' => (int) $topValue->filter_id,
                        'name'      => $topValue->value_name,
                        'count'     => (int) $topValue->cnt,
                        'total'     => $groupProductCount,
                        'pct'       => round($topValue->cnt / $groupProductCount * 100),
                    ];
                }
            }

            arsort($expectedGroups);

            // ── Brand → Supplier mapping (learned from existing assignments) ──
            // Optimized: use subqueries with filter_group_id directly
            $brandGroupId = DB::connection('titi')
                ->table('titi_filter_group_description')
                ->where('language_id', 2)
                ->where('name', 'Značka')
                ->value('filter_group_id');

            $supplierGroupId = DB::connection('titi')
                ->table('titi_filter_group_description')
                ->where('language_id', 2)
                ->where('name', 'Dodávateľ')
                ->value('filter_group_id');

            $brandSupplier = [];
            if ($brandGroupId && $supplierGroupId) {
                // Get product IDs in these categories that have both brand and supplier
                $catProductIds = DB::connection('titi')
                    ->table('titi_product_to_category')
                    ->whereIn('category_id', $categoryIds)
                    ->distinct()
                    ->pluck('product_id');

                if ($catProductIds->isNotEmpty()) {
                    $brandSupplier = DB::connection('titi')
                        ->table('titi_product_filter as pf1')
                        ->join('titi_filter as f1', function ($j) use ($brandGroupId) {
                            $j->on('pf1.filter_id', '=', 'f1.filter_id')
                              ->where('f1.filter_group_id', $brandGroupId);
                        })
                        ->join('titi_filter_description as fd1', function ($j) {
                            $j->on('pf1.filter_id', '=', 'fd1.filter_id')
                              ->where('fd1.language_id', 2);
                        })
                        ->join('titi_product_filter as pf2', 'pf1.product_id', '=', 'pf2.product_id')
                        ->join('titi_filter as f2', function ($j) use ($supplierGroupId) {
                            $j->on('pf2.filter_id', '=', 'f2.filter_id')
                              ->where('f2.filter_group_id', $supplierGroupId);
                        })
                        ->join('titi_filter_description as fd2', function ($j) {
                            $j->on('pf2.filter_id', '=', 'fd2.filter_id')
                              ->where('fd2.language_id', 2);
                        })
                        ->whereIn('pf1.product_id', $catProductIds->take(500))
                        ->select(
                            'fd1.name as brand',
                            'pf1.filter_id as brand_filter_id',
                            'fd2.name as supplier',
                            'pf2.filter_id as supplier_filter_id',
                            DB::raw('COUNT(*) as cnt')
                        )
                        ->groupBy('fd1.name', 'pf1.filter_id', 'fd2.name', 'pf2.filter_id')
                        ->having('cnt', '>=', 2)
                        ->orderByDesc('cnt')
                        ->get()
                        ->groupBy('brand')
                        ->map(function ($rows) {
                            $top = $rows->sortByDesc('cnt')->first();
                            return [
                                'brand_filter_id'    => (int) $top->brand_filter_id,
                                'supplier'           => $top->supplier,
                                'supplier_filter_id' => (int) $top->supplier_filter_id,
                                'count'              => (int) $top->cnt,
                            ];
                        })
                        ->all();
                }
            }

            // ── AI few-shot examples ─────────────────────────────────────────
            $siblingIds = DB::connection('titi')
                ->table('titi_product_to_category as pc')
                ->join('titi_product_filter as pf', 'pc.product_id', '=', 'pf.product_id')
                ->whereIn('pc.category_id', $categoryIds)
                ->select('pc.product_id')
                ->distinct()
                ->limit(15)
                ->pluck('product_id');

            $examples = [];
            if ($siblingIds->isNotEmpty()) {
                $names = DB::connection('titi')
                    ->table('titi_product_description')
                    ->whereIn('product_id', $siblingIds)
                    ->where('language_id', 2)
                    ->pluck('name', 'product_id');

                $filterRows = DB::connection('titi')
                    ->table('titi_product_filter as pf')
                    ->join('titi_filter_description as fd', function ($j) {
                        $j->on('pf.filter_id', '=', 'fd.filter_id')
                          ->where('fd.language_id', 2);
                    })
                    ->join('titi_filter_group_description as fgd', function ($j) {
                        $j->on('fd.filter_group_id', '=', 'fgd.filter_group_id')
                          ->where('fgd.language_id', 2);
                    })
                    ->whereIn('pf.product_id', $siblingIds)
                    ->select('pf.product_id', 'fgd.name as group_name', 'fd.name as filter_name')
                    ->get()
                    ->groupBy('product_id');

                foreach ($siblingIds as $sid) {
                    $name    = $names[$sid] ?? null;
                    $filters = $filterRows->get($sid, collect());
                    if (!$name || $filters->isEmpty()) continue;
                    $examples[] = [
                        'name'    => $name,
                        'filters' => $filters->map(fn($f) => "{$f->group_name}: {$f->filter_name}")->implode(', '),
                        '_count'  => $filters->count(),
                    ];
                }
                usort($examples, fn($a, $b) => $b['_count'] - $a['_count']);
                $examples = array_slice($examples, 0, 3);
            }

            return [
                'expected_groups'       => $expectedGroups,
                'dominant_values'       => $dominantValues,
                'brand_supplier'        => $brandSupplier,
                'total_products'        => $totalProducts,
                'products_with_filters' => $productsWithFilters,
                'examples'              => $examples,
            ];
        });
    }

    // ─── Invalidate category caches for a product ───────────────────────────

    private function invalidateCategoryCache(int $productId): void
    {
        $categoryIds = DB::connection('titi')
            ->table('titi_product_to_category')
            ->where('product_id', $productId)
            ->pluck('category_id');

        if ($categoryIds->isEmpty()) return;

        $key = $categoryIds->sort()->implode('-');
        Cache::forget('cat_profile_' . $key);
        Cache::forget('cat_siblings_' . $key);
    }

    public function createAndAssignFilter(Request $request, int $productId)
    {
        $groupName  = trim($request->input('group_name', ''));
        $filterName = trim($request->input('filter_name', ''));

        if (!$groupName || !$filterName) {
            return response()->json(['error' => 'Missing group_name or filter_name'], 422);
        }

        // Find existing group by name (case-insensitive)
        $existingGroup = DB::connection('titi')
            ->table('titi_filter_group_description')
            ->where('language_id', 2)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($groupName)])
            ->first();

        if ($existingGroup) {
            $groupId = $existingGroup->filter_group_id;
        } else {
            $maxSort = DB::connection('titi')->table('titi_filter_group')->max('sort_order') ?? 0;
            $groupId = DB::connection('titi')->table('titi_filter_group')
                ->insertGetId(['sort_order' => $maxSort + 1], 'filter_group_id');
            DB::connection('titi')->table('titi_filter_group_description')->insert([
                'filter_group_id' => $groupId,
                'language_id'     => 2,
                'name'            => $groupName,
            ]);
        }

        // Create filter
        $maxFilterSort = DB::connection('titi')->table('titi_filter')
            ->where('filter_group_id', $groupId)->max('sort_order') ?? 0;
        $filterId = DB::connection('titi')->table('titi_filter')
            ->insertGetId([
                'filter_group_id' => $groupId,
                'sort_order'      => $maxFilterSort + 1,
            ], 'filter_id');
        DB::connection('titi')->table('titi_filter_description')->insert([
            'filter_id'       => $filterId,
            'language_id'     => 2,
            'filter_group_id' => $groupId,
            'name'            => $filterName,
        ]);

        // Assign to product
        DB::connection('titi')->table('titi_product_filter')->insertOrIgnore([
            'product_id' => $productId,
            'filter_id'  => $filterId,
        ]);

        $this->invalidateCategoryCache($productId);

        return response()->json([
            'filter_id'  => $filterId,
            'group_id'   => $groupId,
            'group_name' => $groupName,
            'name'       => $filterName,
        ]);
    }

    private function getFilterList(): array
    {
        return DB::connection('titi')
            ->table('titi_filter as f')
            ->join('titi_filter_description as fd', function ($j) {
                $j->on('f.filter_id', '=', 'fd.filter_id')
                  ->where('fd.language_id', 2);
            })
            ->join('titi_filter_group as fg', 'f.filter_group_id', '=', 'fg.filter_group_id')
            ->join('titi_filter_group_description as fgd', function ($j) {
                $j->on('fg.filter_group_id', '=', 'fgd.filter_group_id')
                  ->where('fgd.language_id', 2);
            })
            ->select('f.filter_id', 'fd.name', 'fg.filter_group_id', 'fgd.name as group_name')
            ->orderBy('fgd.name')
            ->orderBy('fd.name')
            ->get()
            ->map(fn($r) => [
                'filter_id'   => $r->filter_id,
                'name'        => $r->name,
                'group_id'    => $r->filter_group_id,
                'group_name'  => $r->group_name,
            ])
            ->toArray();
    }

    private function getCategoryTree(): array
    {
        $rows = DB::connection('titi')
            ->table('titi_category as c')
            ->join('titi_category_description as cd', function ($join) {
                $join->on('c.category_id', '=', 'cd.category_id')
                     ->where('cd.language_id', 2);
            })
            ->where('c.status', 1)
            ->select('c.category_id', 'c.parent_id', 'c.sort_order', 'cd.name')
            ->orderBy('c.sort_order')
            ->get();

        $byParent = $rows->groupBy('parent_id');

        $result = [];
        $this->walkTree($byParent, 0, 0, $result);

        return $result;
    }

    private function walkTree($byParent, int $parentId, int $depth, array &$result): void
    {
        $children = $byParent->get($parentId, collect());
        foreach ($children as $cat) {
            $result[] = [
                'category_id' => $cat->category_id,
                'name'        => $cat->name,
                'depth'       => $depth,
            ];
            $this->walkTree($byParent, $cat->category_id, $depth + 1, $result);
        }
    }
}
