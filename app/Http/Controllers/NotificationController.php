<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignPush;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class NotificationController extends Controller
{
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
                'condition'        => $p->condition,
                'send_at'          => $p->send_at?->toIso8601String(),
                'status'           => $p->status,
                'created_at'       => $p->created_at->toIso8601String(),
            ]);

        return Inertia::render('Notifications/Index', [
            'campaigns' => $campaigns,
            'pushes'    => $pushes,
            'stores'    => $this->getStores(),
            'testers'   => $this->getTesters(),
        ]);
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
