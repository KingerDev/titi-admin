<?php

namespace App\Http\Controllers;

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
            ->map(fn($p) => [
                'id'               => $p->id,
                'onesignal_id'     => $p->onesignal_id,
                'target_type'      => $p->target_type,
                'target_store_id'  => $p->target_store_id,
                'target_tester_id' => $p->target_tester_id,
                'condition'        => $p->condition,
                'send_at'          => $p->send_at?->toIso8601String(),
                'status'           => $p->status,
                'created_at'       => $p->created_at->toIso8601String(),
            ]);

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
            'target_type'      => 'required|in:all,testers,tester,store',
            'target_store_id'  => 'nullable|integer|required_if:target_type,store',
            'target_tester_id' => 'nullable|integer|required_if:target_type,tester',
            'condition'        => 'required|in:none,unread_only',
            'send_mode'        => 'required|in:now,scheduled',
            'send_at'          => 'nullable|date|after:now|required_if:send_mode,scheduled',
        ]);

        $push = new CampaignPush([
            'notification_id'  => $campaign->id,
            'target_type'      => $data['target_type'],
            'target_store_id'  => $data['target_store_id'] ?? null,
            'target_tester_id' => $data['target_tester_id'] ?? null,
            'condition'        => $data['condition'],
            'status'           => 'pending',
            'send_at'          => $data['send_mode'] === 'scheduled' ? $data['send_at'] : null,
        ]);

        $sendAfter = $data['send_mode'] === 'scheduled'
            ? \Carbon\Carbon::parse($data['send_at'])
            : null;

        $onesignalId = $this->onesignal->send($campaign, $push, $sendAfter);

        $push->onesignal_id = $onesignalId;
        $push->save();

        return back()->with('success', $data['send_mode'] === 'now'
            ? 'Notifikácia bola odoslaná.'
            : 'Notifikácia bola naplánovaná.'
        );
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

        if (($stats['status'] ?? null) === 'sent' && $push->status === 'pending') {
            $push->update(['status' => 'sent']);
        }

        return response()->json($stats);
    }

    // ── Private helpers ─────────────────────────────────────────────────────

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
