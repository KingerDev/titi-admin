<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CategoryController extends Controller
{
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
            ->where('titi_product.titi_eshop', 1)
            ->where('titi_product.mopcena', '>', 0)
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
                    'product_id'  => $p->product_id,
                    'name'        => $this->safeUtf8($p->desc_name ?? ''),
                    'description' => $descText ? mb_substr(strip_tags($descText), 0, 200) : '',
                    'image'       => $image ? "https://titi.shopweb.sk/{$image}" : null,
                ];
            });

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

        return response()->json(['success' => true, 'count' => count($filterIds)]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function safeUtf8(string $text): string
    {
        $clean = iconv('UTF-8', 'UTF-8//IGNORE', $text);
        return $clean !== false ? $clean : '';
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
