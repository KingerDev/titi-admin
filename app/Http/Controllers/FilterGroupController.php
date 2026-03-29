<?php

namespace App\Http\Controllers;

use App\Models\FilterGroup;
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
}
