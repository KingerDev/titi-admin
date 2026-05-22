<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignPush;
use App\Models\Tester;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OneSignalService
{
    private string $appId;
    private string $restApiKey;
    private string $baseUrl = 'https://onesignal.com/api/v1';

    public function __construct()
    {
        $this->appId      = config('services.onesignal.app_id');
        $this->restApiKey = config('services.onesignal.rest_api_key');
    }

    public function send(Campaign $campaign, CampaignPush $push, ?\Carbon\Carbon $sendAfter = null): ?string
    {
        $payload = $this->buildPayload($campaign, $push);

        if ($sendAfter) {
            $payload['send_after'] = $sendAfter->toRfc2822String();
        }

        $response = Http::withHeaders([
            'Authorization' => 'Key ' . $this->restApiKey,
            'Content-Type'  => 'application/json',
        ])->post("{$this->baseUrl}/notifications", $payload);

        if ($response->successful()) {
            return $response->json('id');
        }

        Log::error('OneSignal send failed', [
            'status'   => $response->status(),
            'body'     => $response->body(),
            'campaign' => $campaign->id,
        ]);

        return null;
    }

    public function cancel(string $onesignalId): void
    {
        Http::withHeaders([
            'Authorization' => 'Key ' . $this->restApiKey,
        ])->delete("{$this->baseUrl}/notifications/{$onesignalId}", [
            'app_id' => $this->appId,
        ]);
    }

    public function getStats(string $onesignalId): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Key ' . $this->restApiKey,
        ])->get("{$this->baseUrl}/notifications/{$onesignalId}", [
            'app_id' => $this->appId,
        ]);

        if (!$response->successful()) {
            return [];
        }

        $data = $response->json();

        return [
            'status'      => $data['completed_at'] ? 'sent' : ($data['canceled'] ? 'cancelled' : 'pending'),
            'send_after'  => $data['send_after'] ?? null,
            'delivered'   => $data['successful'] ?? 0,
            'failed'      => $data['failed'] ?? 0,
            'converted'   => $data['converted'] ?? 0,
        ];
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    private function buildPayload(Campaign $campaign, CampaignPush $push): array
    {
        $payload = [
            'app_id'   => $this->appId,
            'headings' => ['en' => $campaign->title],
            'contents' => ['en' => $campaign->short_description],
            'data'     => ['deep_link' => $push->push_url ?? $campaign->action_url],
        ];

        if ($campaign->image) {
            $payload['big_picture']     = $campaign->image;
            $payload['ios_attachments'] = ['image' => $campaign->image];
        }

        $payload = array_merge($payload, $this->buildAudience($campaign, $push));

        return $payload;
    }

    private function buildAudience(Campaign $campaign, CampaignPush $push): array
    {
        $audience = match ($push->target_type) {
            'testers' => $this->testerAudience(),
            'tester'  => $this->singleTesterAudience($push->target_tester_id),
            'store'   => $this->storeAudience($push->target_store_id),
            default   => ['included_segments' => ['All']],
        };

        if ($push->condition === 'unread_only') {
            $audience = $this->applyUnreadFilter($campaign->id, $audience);
        }

        return $audience;
    }

    private function testerAudience(): array
    {
        $ids = Tester::pluck('customer_id')->map(fn($id) => (string) $id)->toArray();
        return empty($ids)
            ? ['included_segments' => ['All']]
            : ['include_external_user_ids' => $ids];
    }

    private function singleTesterAudience(int $customerId): array
    {
        return ['include_external_user_ids' => [(string) $customerId]];
    }

    private function storeAudience(int $storeId): array
    {
        return [
            'filters' => [
                ['field' => 'tag', 'key' => 'store_id', 'relation' => '=', 'value' => (string) $storeId],
            ],
        ];
    }

    private function applyUnreadFilter(int $notificationId, array $audience): array
    {
        // Fetch customer_ids who already read this notification
        $readIds = DB::connection('titi')
            ->table('titi_notification_reads')
            ->where('notification_id', $notificationId)
            ->pluck('customer_id')
            ->map(fn($id) => (string) $id)
            ->toArray();

        if (empty($readIds)) {
            return $audience;
        }

        // Add exclusion — works alongside both segment and user-list targeting
        $audience['excluded_external_user_ids'] = $readIds;

        return $audience;
    }
}
