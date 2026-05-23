<?php

namespace App\Http\Controllers;

use App\Jobs\SendCampaignPushJob;
use App\Models\Campaign;
use App\Models\CampaignPush;
use App\Services\OneSignalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CampaignPushController extends Controller
{
    public function __construct(private OneSignalService $onesignal) {}

    public function index(int $campaignId)
    {
        $campaign = Campaign::findOrFail($campaignId);
        $pushes   = CampaignPush::where('notification_id', $campaignId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($p) => $this->mapPush($p));

        return Inertia::render('Campaigns/Pushes', [
            'campaign' => [
                'id'     => $campaign->id,
                'name'   => $campaign->name,
                'title'  => $campaign->title,
                'status' => $campaign->status,
            ],
            'pushes'  => $pushes,
            'stores'  => $this->getStores(),
            'testers' => $this->getTesters(),
        ]);
    }

    public function store(Request $request, int $campaignId)
    {
        $campaign = Campaign::findOrFail($campaignId);

        $data = $request->validate([
            'target_type'      => 'required|in:all,testers,tester,store,segment,filtered',
            'target_store_id'  => 'nullable|integer|required_if:target_type,store',
            'target_tester_id' => 'nullable|integer|required_if:target_type,tester',
            'target_segment'   => 'nullable|string|max:255|required_if:target_type,segment',
            'target_filters'   => 'nullable|array',
            'target_filters.*.key'      => 'required_with:target_filters|string|max:100',
            'target_filters.*.relation' => 'required_with:target_filters|in:=,!=,<,<=,>,>=,exists,not_exists,time_elapsed_gt,time_elapsed_lt',
            'target_filters.*.value'    => 'nullable|string|max:255',
            'condition'        => 'required|in:none,unread_only',
            'send_mode'        => 'required|in:now,scheduled',
            'send_at'          => 'nullable|date|after:now|required_if:send_mode,scheduled',
            'push_url'         => 'nullable|string|max:500',
        ]);

        $sendAfter = $data['send_mode'] === 'scheduled'
            ? \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $data['send_at'], 'Europe/Bratislava')->utc()
            : null;

        $push = CampaignPush::create([
            'notification_id'  => $campaign->id,
            'target_type'      => $data['target_type'],
            'target_store_id'  => $data['target_store_id'] ?? null,
            'target_tester_id' => $data['target_tester_id'] ?? null,
            'target_segment'   => $data['target_segment'] ?? null,
            'target_filters'   => $data['target_filters'] ?? null,
            'condition'        => $data['condition'],
            'status'           => 'pending',
            'send_at'          => $sendAfter,
            'push_url'         => $data['push_url'] ?? null,
        ]);

        SendCampaignPushJob::dispatch($campaign->id, $push->id);

        return back()->with('success', $data['send_mode'] === 'now'
            ? 'Notifikácia bola odoslaná.'
            : 'Notifikácia bola naplánovaná.'
        );
    }

    public function retry(int $campaignId, int $pushId)
    {
        $push = CampaignPush::where('notification_id', $campaignId)->findOrFail($pushId);

        abort_if($push->status !== 'error', 422, 'Len chybné notifikácie môžu byť opakované.');

        $push->update(['status' => 'pending', 'send_error' => null]);

        SendCampaignPushJob::dispatch($campaignId, $push->id);

        return back()->with('success', 'Notifikácia bola znova zaradená do fronty.');
    }

    public function duplicate(int $campaignId, int $pushId)
    {
        $original = CampaignPush::where('notification_id', $campaignId)->findOrFail($pushId);

        return response()->json([
            'target_type'      => $original->target_type,
            'target_store_id'  => $original->target_store_id,
            'target_tester_id' => $original->target_tester_id,
            'target_segment'   => $original->target_segment,
            'target_filters'   => $original->target_filters ?? [],
            'condition'        => $original->condition,
            'push_url'         => $original->push_url,
        ]);
    }

    public function destroy(int $campaignId, int $pushId)
    {
        $push = CampaignPush::where('notification_id', $campaignId)->findOrFail($pushId);

        if ($push->onesignal_id) {
            $this->onesignal->cancel($push->onesignal_id);
        }

        $push->delete();

        return back()->with('success', 'Push notifikácia bola zrušená.');
    }

    public function stats(int $campaignId, int $pushId)
    {
        $push = CampaignPush::where('notification_id', $campaignId)->findOrFail($pushId);

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

    public function syncCampaignStats(int $campaignId)
    {
        $pushes = CampaignPush::where('notification_id', $campaignId)
            ->whereNotNull('onesignal_id')
            ->whereIn('status', ['sent', 'pending'])
            ->get();

        foreach ($pushes as $push) {
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
        }

        $refreshed = CampaignPush::where('notification_id', $campaignId)
            ->whereNotNull('stats_synced_at')
            ->get();

        return response()->json([
            'recipients' => $refreshed->sum('recipients'),
            'delivered'  => $refreshed->sum('delivered'),
            'failed'     => $refreshed->sum('failed'),
            'converted'  => $refreshed->sum('converted'),
            'push_count' => $refreshed->count(),
            'synced_at'  => now()->toIso8601String(),
        ]);
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    private function mapPush(CampaignPush $p): array
    {
        return [
            'id'               => $p->id,
            'onesignal_id'     => $p->onesignal_id,
            'notification_id'  => $p->notification_id,
            'target_type'      => $p->target_type,
            'target_store_id'  => $p->target_store_id,
            'target_tester_id' => $p->target_tester_id,
            'target_segment'   => $p->target_segment,
            'target_filters'   => $p->target_filters,
            'condition'        => $p->condition,
            'send_at'          => $p->send_at?->toIso8601String(),
            'status'           => $p->status,
            'send_error'       => $p->send_error,
            'retry_count'      => $p->retry_count,
            'push_url'         => $p->push_url,
            'recipients'       => $p->recipients,
            'delivered'        => $p->delivered,
            'failed'           => $p->failed,
            'converted'        => $p->converted,
            'stats_synced_at'  => $p->stats_synced_at?->toIso8601String(),
            'created_at'       => $p->created_at->toIso8601String(),
        ];
    }

    private function getTesters(): array
    {
        return \App\Models\Tester::orderBy('note')
            ->get(['customer_id', 'note'])
            ->toArray();
    }

    private function getStores(): array
    {
        try {
            return DB::connection('titi')
                ->table('titi_store as s')
                ->join('titi_store_description as sd', function ($join) {
                    $join->on('s.store_id', '=', 'sd.store_id')
                         ->where('sd.language_id', '=', 2);
                })
                ->select('s.store_id', 'sd.name')
                ->orderBy('sd.name')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }
}
