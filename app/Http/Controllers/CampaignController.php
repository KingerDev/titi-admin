<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Services\OneSignalService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CampaignController extends Controller
{
    public function index()
    {
        $campaigns = Campaign::withCount('pushes')
            ->orderByRaw("FIELD(status, 'draft', 'testing', 'active')")
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($c) => [
                'id'          => $c->id,
                'name'        => $c->name,
                'title'       => $c->title,
                'status'      => $c->status,
                'is_active'   => $c->is_active,
                'pushes_count'=> $c->pushes_count,
                'expires_at'  => $c->expires_at?->toIso8601String(),
                'created_at'  => $c->created_at->toIso8601String(),
            ]);

        return Inertia::render('Campaigns/Index', compact('campaigns'));
    }

    public function create()
    {
        return Inertia::render('Campaigns/Form', [
            'campaign' => null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateCampaign($request);
        $data = $this->convertDatesToUtc($data);

        $campaign = Campaign::create(array_merge($data, [
            'is_active'  => $data['status'] === 'active',
            'is_testing' => $data['status'] === 'testing',
        ]));

        return redirect()->route('campaigns.edit', $campaign->id)
            ->with('success', 'Kampaň bola uložená.');
    }

    public function edit(int $id)
    {
        $campaign = Campaign::withCount('pushes')->findOrFail($id);

        return Inertia::render('Campaigns/Form', [
            'campaign' => [
                'id'                => $campaign->id,
                'name'              => $campaign->name,
                'title'             => $campaign->title,
                'image'             => $campaign->image,
                'short_description' => $campaign->short_description,
                'long_description'  => $campaign->long_description,
                'action_url'        => $campaign->action_url,
                'starts_at'         => $campaign->starts_at?->setTimezone('Europe/Bratislava')->format('Y-m-d\TH:i'),
                'expires_at'        => $campaign->expires_at?->setTimezone('Europe/Bratislava')->format('Y-m-d\TH:i'),
                'status'            => $campaign->status,
                'is_active'         => $campaign->is_active,
                'pushes_count'      => $campaign->pushes_count,
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $campaign = Campaign::findOrFail($id);
        $data     = $this->validateCampaign($request);
        $data     = $this->convertDatesToUtc($data);

        $campaign->update(array_merge($data, [
            'is_active'  => $data['status'] === 'active',
            'is_testing' => $data['status'] === 'testing',
        ]));

        return back()->with('success', 'Kampaň bola uložená.');
    }

    public function destroy(int $id, OneSignalService $onesignal)
    {
        $campaign = Campaign::with('pushes')->findOrFail($id);

        foreach ($campaign->pushes as $push) {
            if ($push->onesignal_id && $push->status === 'pending') {
                $onesignal->cancel($push->onesignal_id);
            }
        }

        $campaign->delete();

        return redirect()->route('campaigns.index')
            ->with('success', 'Kampaň bola zmazaná.');
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    private function convertDatesToUtc(array $data): array
    {
        foreach (['starts_at', 'expires_at'] as $field) {
            if (!empty($data[$field])) {
                $data[$field] = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $data[$field], 'Europe/Bratislava')
                    ->utc();
            }
        }
        return $data;
    }

    private function validateCampaign(Request $request): array
    {
        return $request->validate([
            'name'              => 'required|string|max:255',
            'title'             => 'required|string|max:255',
            'image'             => 'nullable|url|max:500',
            'short_description' => 'required|string|max:1000',
            'long_description'  => 'nullable|string',
            'action_url'        => 'nullable|string|max:500',
            'status'            => 'required|in:draft,testing,active',
            'starts_at'         => 'nullable|date',
            'expires_at'        => 'nullable|date',
        ]);
    }

}
