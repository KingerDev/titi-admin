<?php

namespace App\Http\Controllers;

use App\Models\NotificationTemplate;
use Illuminate\Http\Request;

class NotificationTemplateController extends Controller
{
    public function index()
    {
        return response()->json(
            NotificationTemplate::orderBy('name')
                ->get()
                ->map(fn($t) => [
                    'id'               => $t->id,
                    'name'             => $t->name,
                    'title'            => $t->title,
                    'message'          => $t->message,
                    'subtitle'         => $t->subtitle,
                    'image'            => $t->image,
                    'push_url'         => $t->push_url,
                    'target_type'      => $t->target_type,
                    'target_store_id'  => $t->target_store_id,
                    'target_tester_id' => $t->target_tester_id,
                    'target_segment'   => $t->target_segment,
                    'target_filters'   => $t->target_filters ?? [],
                    'ttl'              => $t->ttl,
                    'priority'         => $t->priority,
                    'collapse_id'      => $t->collapse_id,
                    'ios_badge_type'   => $t->ios_badge_type,
                    'ios_badge_count'  => $t->ios_badge_count,
                ])
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'title'            => 'required|string|max:255',
            'message'          => 'required|string|max:2000',
            'subtitle'         => 'nullable|string|max:255',
            'image'            => 'nullable|url|max:500',
            'push_url'         => 'nullable|string|max:500',
            'target_type'      => 'required|in:all,testers,tester,store,segment,filtered',
            'target_store_id'  => 'nullable|integer',
            'target_tester_id' => 'nullable|integer',
            'target_segment'   => 'nullable|string|max:255',
            'target_filters'   => 'nullable|array',
            'target_filters.*.key'      => 'required_with:target_filters|string|max:100',
            'target_filters.*.relation' => 'required_with:target_filters|in:=,!=,<,<=,>,>=,exists,not_exists',
            'target_filters.*.value'    => 'nullable|string|max:255',
            'ttl'              => 'nullable|integer|min:60|max:2419200',
            'priority'         => 'nullable|integer|in:5,10',
            'collapse_id'      => 'nullable|string|max:64',
            'ios_badge_type'   => 'nullable|in:None,SetTo,Increase',
            'ios_badge_count'  => 'nullable|integer|min:0|max:9999',
        ]);

        $template = NotificationTemplate::create($data);

        return response()->json(['id' => $template->id, 'name' => $template->name], 201);
    }

    public function destroy(int $id)
    {
        NotificationTemplate::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
