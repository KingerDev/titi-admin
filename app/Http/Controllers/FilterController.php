<?php

namespace App\Http\Controllers;

use App\Models\Filter;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FilterController extends Controller
{
    public function show(Filter $filter)
    {
        $filter->load(['description', 'group.description']);

        $assignedProducts = $filter->products()
            ->where('titi_eshop', 1)
            ->where('mopcena', '>', 0)
            ->with([
                'description' => fn($q) => $q->where('language_id', 2),
                'mainImage',
            ])
            ->get()
            ->map(fn($p) => $this->formatProduct($p));

        $assignedCategoryIds = DB::connection('titi')
            ->table('titi_category_filter')
            ->where('filter_id', $filter->filter_id)
            ->pluck('category_id')
            ->toArray();

        return Inertia::render('Filters/Show', [
            'filter' => [
                'filter_id'  => $filter->filter_id,
                'name'       => $filter->description->name ?? '',
                'group_name' => $filter->group->description->name ?? '',
            ],
            'assignedProducts'    => $assignedProducts,
            'categories'          => $this->getCategoryTree(),
            'assignedCategoryIds' => $assignedCategoryIds,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'            => 'required|string|max:64',
            'filter_group_id' => 'required|integer',
        ]);

        $groupId = (int) $request->input('filter_group_id');
        $maxSort = DB::connection('titi')->table('titi_filter')
            ->where('filter_group_id', $groupId)->max('sort_order') ?? 0;

        $filterId = DB::connection('titi')->table('titi_filter')
            ->insertGetId([
                'filter_group_id' => $groupId,
                'sort_order'      => $maxSort + 1,
            ], 'filter_id');

        DB::connection('titi')->table('titi_filter_description')->insert([
            'filter_id'       => $filterId,
            'language_id'     => 2,
            'filter_group_id' => $groupId,
            'name'            => $request->input('name'),
        ]);

        return redirect()->route('filters.show', $filterId)
            ->with('success', 'Filter „' . $request->input('name') . '" bol vytvorený.');
    }

    public function search(Filter $filter, Request $request)
    {
        $q          = trim($request->get('q', ''));
        $categoryId = (int) $request->get('category_id', 0);

        // Require at least a query OR a category to be set
        if (strlen($q) < 1 && $categoryId <= 0) {
            return response()->json([]);
        }

        $assignedIds = $filter->products()->pluck('titi_product.product_id')->toArray();

        $products = Product::join('titi_product_description', function ($join) {
                $join->on('titi_product.product_id', '=', 'titi_product_description.product_id')
                     ->where('titi_product_description.language_id', 2);
            })
            ->where('titi_product.titi_eshop', 1)
            ->where('titi_product.mopcena', '>', 0)
            ->when(strlen($q) >= 1, function ($query) use ($q) {
                $query->where(function ($q2) use ($q) {
                    $q2->where('titi_product_description.name', 'LIKE', "%{$q}%")
                       ->orWhere('titi_product_description.description', 'LIKE', "%{$q}%")
                       ->orWhere('titi_product_description.ai_search_enrichment', 'LIKE', "%{$q}%");
                });
            })
            ->when($categoryId > 0, function ($query) use ($categoryId) {
                $query->whereExists(function ($sub) use ($categoryId) {
                    $sub->select(DB::raw(1))
                        ->from('titi_product_to_category')
                        ->whereColumn('titi_product_to_category.product_id', 'titi_product.product_id')
                        ->where('titi_product_to_category.category_id', $categoryId);
                });
            })
            ->select(
                'titi_product.product_id',
                'titi_product_description.name as desc_name',
                'titi_product_description.description as desc_text',
                'titi_product_description.ai_search_enrichment as aie'
            )
            ->with('mainImage')
            ->limit(strlen($q) >= 1 ? 40 : 120)
            ->get()
            ->map(function ($p) use ($assignedIds) {
                $image    = $p->mainImage ? $p->mainImage->image : null;
                $descText = $this->safeUtf8($p->desc_text ?? '');
                return [
                    'product_id'       => $p->product_id,
                    'name'             => $this->safeUtf8($p->desc_name ?? ''),
                    'description'      => $descText ? mb_substr(strip_tags($descText), 0, 200) : '',
                    'description_html' => $descText,
                    'image'            => $image ? "https://titi.shopweb.sk/{$image}" : null,
                    'assigned'         => in_array($p->product_id, $assignedIds),
                ];
            });

        return response()->json($products);
    }

    public function sync(Filter $filter, Request $request)
    {
        $productIds = $request->input('product_ids');
        $productIds = is_array($productIds) ? array_map('intval', $productIds) : [];

        $filter->products()->sync($productIds);

        return response()->json(['success' => true, 'count' => count($productIds)]);
    }

    public function syncCategories(Filter $filter, Request $request)
    {
        $categoryIds = array_values(array_unique(
            array_map('intval', (array) $request->input('category_ids', []))
        ));

        $table = DB::connection('titi')->table('titi_category_filter');
        $table->where('filter_id', $filter->filter_id)->delete();

        if (!empty($categoryIds)) {
            $rows = array_map(fn($catId) => [
                'filter_id'   => $filter->filter_id,
                'category_id' => $catId,
            ], $categoryIds);
            $table->insert($rows);
        }

        return response()->json(['success' => true, 'count' => count($categoryIds)]);
    }

    public function categoryProductIds(Request $request)
    {
        $categoryId = (int) $request->get('category_id', 0);

        if ($categoryId <= 0) {
            return response()->json([]);
        }

        $ids = Product::join('titi_product_to_category', 'titi_product.product_id', '=', 'titi_product_to_category.product_id')
            ->where('titi_product_to_category.category_id', $categoryId)
            ->where('titi_product.titi_eshop', 1)
            ->where('titi_product.mopcena', '>', 0)
            ->pluck('titi_product.product_id');

        return response()->json($ids);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function formatProduct(Product $p): array
    {
        $image    = $p->mainImage ? $p->mainImage->image : null;
        $descText = $this->safeUtf8($p->description->description ?? '');
        return [
            'product_id'       => $p->product_id,
            'name'             => $this->safeUtf8($p->description->name ?? ''),
            'description'      => $descText ? mb_substr(strip_tags($descText), 0, 200) : '',
            'description_html' => $descText,
            'image'            => $image ? "https://titi.shopweb.sk/{$image}" : null,
        ];
    }

    /**
     * Remove/replace invalid UTF-8 byte sequences so JSON encoding never fails.
     */
    private function safeUtf8(string $text): string
    {
        // iconv with //IGNORE strips bytes that aren't valid UTF-8
        $clean = iconv('UTF-8', 'UTF-8//IGNORE', $text);
        return $clean !== false ? $clean : '';
    }

    /**
     * Build a flat ordered category list with depth info for a hierarchical select.
     */
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

        // Group by parent_id
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
