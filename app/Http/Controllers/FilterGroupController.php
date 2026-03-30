<?php

namespace App\Http\Controllers;

use App\Models\FilterGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FilterGroupController extends Controller
{
    public function index()
    {
        $filterGroups = FilterGroup::with([
            'description',
            'filters' => function ($query) {
                $query->with('description');
            },
        ])->get();

        return Inertia::render('Filters/Index', [
            'filterGroups' => $filterGroups,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:64']);

        $maxSort = DB::connection('titi')->table('titi_filter_group')->max('sort_order') ?? 0;

        $groupId = DB::connection('titi')->table('titi_filter_group')
            ->insertGetId(['sort_order' => $maxSort + 1], 'filter_group_id');

        DB::connection('titi')->table('titi_filter_group_description')->insert([
            'filter_group_id' => $groupId,
            'language_id'     => 2,
            'name'            => $request->input('name'),
        ]);

        return back()->with('success', 'Filter skupina „' . $request->input('name') . '" bola vytvorená.');
    }
}
