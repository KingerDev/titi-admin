<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignPush;
use App\Models\StandalonePush;
use App\Models\Tester;
use App\Services\OneSignalService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function __construct(private OneSignalService $onesignal) {}

    public function index()
    {
        $campaigns = Campaign::withCount('pushes')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($c) => [
                'id'           => $c->id,
                'name'         => $c->name,
                'title'        => $c->title,
                'status'       => $c->status,
                'pushes_count' => $c->pushes_count,
            ]);

        $pushes = CampaignPush::orderBy('created_at', 'desc')
            ->get()
            ->map(fn($p) => [
                'id'               => $p->id,
                'notification_id'  => $p->notification_id,
                'onesignal_id'     => $p->onesignal_id,
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
                'created_at'       => $p->created_at->toIso8601String(),
            ]);

        $standalone = StandalonePush::orderBy('created_at', 'desc')
            ->get()
            ->map(fn($p) => [
                'id'               => $p->id,
                'onesignal_id'     => $p->onesignal_id,
                'title'            => $p->title,
                'message'          => $p->message,
                'subtitle'         => $p->subtitle,
                'image'            => $p->image,
                'push_url'         => $p->push_url,
                'target_type'      => $p->target_type,
                'target_store_id'  => $p->target_store_id,
                'target_tester_id' => $p->target_tester_id,
                'target_segment'   => $p->target_segment,
                'target_filters'   => $p->target_filters,
                'ttl'              => $p->ttl,
                'priority'         => $p->priority,
                'collapse_id'      => $p->collapse_id,
                'ios_badge_type'   => $p->ios_badge_type,
                'ios_badge_count'  => $p->ios_badge_count,
                'send_at'          => $p->send_at?->toIso8601String(),
                'status'           => $p->status,
                'send_error'       => $p->send_error,
                'retry_count'      => $p->retry_count,
                'recipients'       => $p->recipients,
                'delivered'        => $p->delivered,
                'failed'           => $p->failed,
                'converted'        => $p->converted,
                'stats_synced_at'  => $p->stats_synced_at?->toIso8601String(),
                'created_at'       => $p->created_at->toIso8601String(),
            ]);

        return Inertia::render('Notifications/Index', [
            'campaigns'  => $campaigns,
            'pushes'     => $pushes,
            'standalone' => $standalone,
            'stores'     => $this->getStores(),
            'testers'    => $this->getTesters(),
        ]);
    }

    public function appStats()
    {
        $stats    = $this->onesignal->getAppStats();
        $testers  = Tester::count();

        return response()->json([
            'total'       => $stats['total'] ?? 0,
            'messageable' => $stats['messageable'] ?? 0,
            'testers'     => $testers,
        ]);
    }

    public function segments()
    {
        return response()->json($this->onesignal->getSegments());
    }

    public function scheduled()
    {
        $now = Carbon::now();

        $campaignPushes = CampaignPush::with('campaign')
            ->where('status', 'pending')
            ->whereNotNull('send_at')
            ->where('send_at', '>', $now)
            ->orderBy('send_at')
            ->get()
            ->map(fn($p) => [
                'type'             => 'campaign',
                'id'               => $p->id,
                'campaign_id'      => $p->notification_id,
                'campaign_name'    => $p->campaign?->name,
                'onesignal_id'     => $p->onesignal_id,
                'target_type'      => $p->target_type,
                'target_store_id'  => $p->target_store_id,
                'target_tester_id' => $p->target_tester_id,
                'target_segment'   => $p->target_segment,
                'send_at'          => $p->send_at->toIso8601String(),
                'status'           => $p->status,
            ]);

        $standalonePushes = StandalonePush::where('status', 'pending')
            ->whereNotNull('send_at')
            ->where('send_at', '>', $now)
            ->orderBy('send_at')
            ->get()
            ->map(fn($p) => [
                'type'           => 'standalone',
                'id'             => $p->id,
                'title'          => $p->title,
                'onesignal_id'   => $p->onesignal_id,
                'target_type'    => $p->target_type,
                'target_store_id'  => $p->target_store_id,
                'target_tester_id' => $p->target_tester_id,
                'target_segment' => $p->target_segment,
                'send_at'        => $p->send_at->toIso8601String(),
                'status'         => $p->status,
            ]);

        return response()->json(
            $campaignPushes->concat($standalonePushes)->sortBy('send_at')->values()
        );
    }

    public function statsOverview()
    {
        $campaigns = Campaign::with(['pushes' => fn($q) => $q->whereNotNull('stats_synced_at')])
            ->withCount('pushes as total_pushes')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($c) {
                $synced = $c->pushes;
                $totalR = $synced->sum('recipients');
                $totalD = $synced->sum('delivered');
                $totalF = $synced->sum('failed');
                $totalC = $synced->sum('converted');
                $lastSync = $synced->max('stats_synced_at');

                return [
                    'id'            => $c->id,
                    'name'          => $c->name,
                    'title'         => $c->title,
                    'status'        => $c->status,
                    'total_pushes'  => $c->total_pushes,
                    'synced_pushes' => $synced->count(),
                    'recipients'    => $totalR,
                    'delivered'     => $totalD,
                    'failed'        => $totalF,
                    'converted'     => $totalC,
                    'delivery_rate' => $totalR > 0 ? round($totalD / $totalR * 100, 1) : null,
                    'ctr'           => $totalD > 0 ? round($totalC / $totalD * 100, 1) : null,
                    'stats_synced_at' => $lastSync ? \Carbon\Carbon::parse($lastSync)->toIso8601String() : null,
                ];
            })
            ->filter(fn($c) => $c['total_pushes'] > 0)
            ->values();

        $standalone = StandalonePush::orderBy('created_at', 'desc')
            ->get()
            ->map(fn($p) => [
                'id'            => $p->id,
                'onesignal_id'  => $p->onesignal_id,
                'title'         => $p->title,
                'target_type'   => $p->target_type,
                'status'        => $p->status,
                'recipients'    => $p->recipients,
                'delivered'     => $p->delivered,
                'failed'        => $p->failed,
                'converted'     => $p->converted,
                'delivery_rate' => ($p->recipients ?? 0) > 0 ? round($p->delivered / $p->recipients * 100, 1) : null,
                'ctr'           => ($p->delivered ?? 0) > 0 ? round($p->converted / $p->delivered * 100, 1) : null,
                'stats_synced_at' => $p->stats_synced_at?->toIso8601String(),
                'created_at'    => $p->created_at->toIso8601String(),
            ]);

        $allDelivered  = $campaigns->sum('delivered')  + $standalone->sum('delivered');
        $allRecipients = $campaigns->sum('recipients') + $standalone->sum('recipients');
        $allConverted  = $campaigns->sum('converted')  + $standalone->sum('converted');
        $allFailed     = $campaigns->sum('failed')     + $standalone->sum('failed');

        $summary = [
            'total_recipients' => $allRecipients,
            'total_delivered'  => $allDelivered,
            'total_failed'     => $allFailed,
            'total_converted'  => $allConverted,
            'delivery_rate'    => $allRecipients > 0 ? round($allDelivered / $allRecipients * 100, 1) : null,
            'ctr'              => $allDelivered > 0 ? round($allConverted / $allDelivered * 100, 1) : null,
        ];

        return Inertia::render('Notifications/Stats', [
            'campaigns'  => $campaigns,
            'standalone' => $standalone,
            'summary'    => $summary,
        ]);
    }

    public function history()
    {
        $offset = (int) request('offset', 0);
        return response()->json($this->onesignal->getNotificationHistory(50, $offset));
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    private function getTesters(): array
    {
        return Tester::orderBy('note')
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
