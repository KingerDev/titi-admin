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

    public function sendStandalone(\App\Models\StandalonePush $push, ?\Carbon\Carbon $sendAfter = null): ?string
    {
        $payload = [
            'app_id'   => $this->appId,
            'headings' => ['en' => $push->title],
            'contents' => ['en' => $push->message],
            'data'     => ['deep_link' => $push->push_url],
        ];

        if ($push->subtitle) {
            $payload['subtitle'] = ['en' => $push->subtitle];
        }

        if ($push->image) {
            $payload['big_picture']     = $push->image;
            $payload['ios_attachments'] = ['image' => $push->image];
        }

        if ($push->ttl !== null) {
            $payload['ttl'] = $push->ttl;
        }

        if ($push->priority) {
            $payload['priority'] = $push->priority;
        }

        if ($push->collapse_id) {
            $payload['collapse_id'] = $push->collapse_id;
        }

        if ($push->ios_badge_type) {
            $payload['ios_badge_type']  = $push->ios_badge_type;
            $payload['ios_badge_count'] = $push->ios_badge_count ?? 1;
        }

        if ($sendAfter) {
            $payload['send_after'] = $sendAfter->toRfc2822String();
        }

        $payload = array_merge($payload, $this->buildAudience(null, $push));

        $response = Http::withHeaders([
            'Authorization' => 'Key ' . $this->restApiKey,
            'Content-Type'  => 'application/json',
        ])->post("{$this->baseUrl}/notifications", $payload);

        if ($response->successful()) {
            return $response->json('id');
        }

        Log::error('OneSignal standalone send failed', [
            'status' => $response->status(),
            'body'   => $response->body(),
            'push'   => $push->id,
        ]);

        return null;
    }

    public function getAppStats(): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Key ' . $this->restApiKey,
        ])->get("{$this->baseUrl}/apps/{$this->appId}");

        if (!$response->successful()) {
            return [];
        }

        $data = $response->json();

        return [
            'total'       => $data['players'] ?? 0,
            'messageable' => $data['messageable_players'] ?? 0,
            'name'        => $data['name'] ?? '',
        ];
    }

    public function getSegments(): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Key ' . $this->restApiKey,
        ])->get("{$this->baseUrl}/apps/{$this->appId}/segments", [
            'limit'  => 300,
            'offset' => 0,
        ]);

        if (!$response->successful()) {
            return [];
        }

        return collect($response->json('segments') ?? [])
            ->map(fn($s) => ['id' => $s['id'], 'name' => $s['name']])
            ->values()
            ->toArray();
    }

    public function getNotificationHistory(int $limit = 50, int $offset = 0): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Key ' . $this->restApiKey,
        ])->get("{$this->baseUrl}/notifications", [
            'app_id' => $this->appId,
            'limit'  => $limit,
            'offset' => $offset,
            'kind'   => 1,
        ]);

        if (!$response->successful()) {
            return ['total' => 0, 'notifications' => []];
        }

        $data = $response->json();

        $notifications = collect($data['notifications'] ?? [])->map(function ($n) {
            $isScheduled = isset($n['send_after']) && !$n['completed_at'] && !$n['canceled'];
            $status = $n['canceled'] ? 'cancelled' : ($n['completed_at'] ? 'sent' : ($isScheduled ? 'scheduled' : 'pending'));

            return [
                'id'          => $n['id'],
                'headings'    => $n['headings']['en'] ?? $n['headings'][array_key_first($n['headings'] ?? [])] ?? '—',
                'contents'    => $n['contents']['en'] ?? $n['contents'][array_key_first($n['contents'] ?? [])] ?? '—',
                'send_after'  => $n['send_after'] ?? null,
                'completed_at'=> $n['completed_at'] ?? null,
                'status'      => $status,
                'delivered'   => $n['successful'] ?? 0,
                'failed'      => $n['failed'] ?? 0,
                'converted'   => $n['converted'] ?? 0,
                'recipients'  => $n['recipients'] ?? 0,
            ];
        })->toArray();

        return [
            'total'         => $data['total_count'] ?? count($notifications),
            'notifications' => $notifications,
        ];
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
            'recipients'  => $data['recipients'] ?? 0,
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

    private function buildAudience(?Campaign $campaign, CampaignPush|\App\Models\StandalonePush $push): array
    {
        $audience = match ($push->target_type) {
            'testers'  => $this->testerAudience(),
            'tester'   => $this->singleTesterAudience($push->target_tester_id),
            'store'    => $this->storeAudience($push->target_store_id),
            'segment'  => $this->segmentAudience($push->target_segment),
            'filtered' => $this->filteredAudience($push->target_filters ?? []),
            default    => ['included_segments' => ['All']],
        };

        // Merge extra tag filters onto existing audience (for 'all', 'store', etc.)
        $extraFilters = $push->target_filters ?? [];
        if (!empty($extraFilters) && $push->target_type !== 'filtered') {
            $audience = $this->mergeTagFilters($audience, $extraFilters);
        }

        if ($campaign && ($push->condition ?? null) === 'unread_only') {
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

    private function segmentAudience(string $segmentName): array
    {
        return ['included_segments' => [$segmentName]];
    }

    private function filteredAudience(array $filters): array
    {
        if (empty($filters)) {
            return ['included_segments' => ['All']];
        }

        return ['filters' => $this->buildFilterArray($filters)];
    }

    private function mergeTagFilters(array $audience, array $filters): array
    {
        if (empty($filters)) {
            return $audience;
        }

        $built = $this->buildFilterArray($filters);

        if (isset($audience['filters'])) {
            // Combine existing filters with AND operator
            $combined = [];
            foreach ($audience['filters'] as $f) {
                $combined[] = $f;
                $combined[] = ['operator' => 'AND'];
            }
            foreach ($built as $f) {
                $combined[] = $f;
            }
            $audience['filters'] = $combined;
        } else {
            $audience['filters'] = $built;
            // Remove segment targeting – filters take precedence
            unset($audience['included_segments']);
        }

        return $audience;
    }

    private function buildFilterArray(array $filters): array
    {
        $result = [];
        foreach ($filters as $i => $f) {
            if ($i > 0) {
                $result[] = ['operator' => 'AND'];
            }
            $result[] = [
                'field'    => 'tag',
                'key'      => $f['key'],
                'relation' => $f['relation'] ?? '=',
                'value'    => (string) $f['value'],
            ];
        }
        return $result;
    }
}
