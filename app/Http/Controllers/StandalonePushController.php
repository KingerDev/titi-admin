<?php

namespace App\Http\Controllers;

use App\Jobs\SendStandalonePushJob;
use App\Models\StandalonePush;
use App\Services\OneSignalService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StandalonePushController extends Controller
{
    public function __construct(private OneSignalService $onesignal) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'message'          => 'required|string|max:2000',
            'subtitle'         => 'nullable|string|max:255',
            'image'            => 'nullable|url|max:500',
            'push_url'         => 'nullable|string|max:500',
            'target_type'      => 'required|in:all,testers,tester,store,segment,filtered',
            'target_store_id'  => 'nullable|integer|required_if:target_type,store',
            'target_tester_id' => 'nullable|integer|required_if:target_type,tester',
            'target_segment'   => 'nullable|string|max:255|required_if:target_type,segment',
            'target_filters'   => 'nullable|array',
            'target_filters.*.key'      => 'required_with:target_filters|string|max:100',
            'target_filters.*.relation' => 'required_with:target_filters|in:=,!=,<,<=,>,>=,exists,not_exists',
            'target_filters.*.value'    => 'nullable|string|max:255',
            'send_mode'        => 'required|in:now,scheduled',
            'send_at'          => 'nullable|date|after:now|required_if:send_mode,scheduled',
            'ttl'              => 'nullable|integer|min:60|max:2419200',
            'priority'         => 'nullable|integer|in:5,10',
            'collapse_id'      => 'nullable|string|max:64',
            'ios_badge_type'   => 'nullable|in:None,SetTo,Increase',
            'ios_badge_count'  => 'nullable|integer|min:0|max:9999|required_if:ios_badge_type,SetTo,Increase',
        ]);

        $sendAfter = $data['send_mode'] === 'scheduled'
            ? Carbon::createFromFormat('Y-m-d\TH:i', $data['send_at'], 'Europe/Bratislava')->utc()
            : null;

        $push = StandalonePush::create([
            'title'            => $data['title'],
            'message'          => $data['message'],
            'subtitle'         => $data['subtitle'] ?? null,
            'image'            => $data['image'] ?? null,
            'push_url'         => $data['push_url'] ?? null,
            'target_type'      => $data['target_type'],
            'target_store_id'  => $data['target_store_id'] ?? null,
            'target_tester_id' => $data['target_tester_id'] ?? null,
            'target_segment'   => $data['target_segment'] ?? null,
            'target_filters'   => $data['target_filters'] ?? null,
            'send_at'          => $sendAfter,
            'ttl'              => $data['ttl'] ?? null,
            'priority'         => $data['priority'] ?? 10,
            'collapse_id'      => $data['collapse_id'] ?? null,
            'ios_badge_type'   => $data['ios_badge_type'] ?? null,
            'ios_badge_count'  => $data['ios_badge_count'] ?? null,
            'status'           => 'pending',
        ]);

        SendStandalonePushJob::dispatch($push->id);

        return back()->with('success', $data['send_mode'] === 'now'
            ? 'Notifikácia bola odoslaná.'
            : 'Notifikácia bola naplánovaná.'
        );
    }

    public function retry(int $id)
    {
        $push = StandalonePush::findOrFail($id);

        abort_if($push->status !== 'error', 422, 'Len chybné notifikácie môžu byť opakované.');

        $push->update(['status' => 'pending', 'send_error' => null]);

        SendStandalonePushJob::dispatch($push->id);

        return back()->with('success', 'Notifikácia bola znova zaradená do fronty.');
    }

    public function duplicate(int $id)
    {
        $original = StandalonePush::findOrFail($id);

        return response()->json([
            'title'            => $original->title,
            'message'          => $original->message,
            'subtitle'         => $original->subtitle,
            'image'            => $original->image,
            'push_url'         => $original->push_url,
            'target_type'      => $original->target_type,
            'target_store_id'  => $original->target_store_id,
            'target_tester_id' => $original->target_tester_id,
            'target_segment'   => $original->target_segment,
            'target_filters'   => $original->target_filters ?? [],
            'ttl'              => $original->ttl,
            'priority'         => $original->priority,
            'collapse_id'      => $original->collapse_id,
            'ios_badge_type'   => $original->ios_badge_type,
            'ios_badge_count'  => $original->ios_badge_count,
        ]);
    }

    public function destroy(int $id)
    {
        $push = StandalonePush::findOrFail($id);

        if ($push->onesignal_id) {
            $this->onesignal->cancel($push->onesignal_id);
        }

        $push->delete();

        return back()->with('success', 'Push notifikácia bola zrušená.');
    }

    public function stats(int $id)
    {
        $push = StandalonePush::findOrFail($id);

        if (!$push->onesignal_id) {
            return response()->json(['error' => 'Žiadne OneSignal ID'], 404);
        }

        $stats = $this->onesignal->getStats($push->onesignal_id);

        $updates = [
            'recipients'      => $stats['recipients'] ?? 0,
            'delivered'       => $stats['delivered'] ?? 0,
            'failed'          => $stats['failed'] ?? 0,
            'converted'       => $stats['converted'] ?? 0,
            'stats_synced_at' => now(),
        ];

        if (($stats['status'] ?? null) === 'sent' && $push->status !== 'sent') {
            $updates['status'] = 'sent';
        }

        $push->update($updates);

        return response()->json($stats);
    }
}
