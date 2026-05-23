<?php

namespace App\Http\Controllers;

use App\Models\CampaignPush;
use App\Models\StandalonePush;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $event = $request->input('event');
        $id    = $request->input('id') ?? $request->input('notification', [])['id'] ?? null;

        if (!$id) {
            return response()->json(['ok' => false], 400);
        }

        Log::info('OneSignal webhook', ['event' => $event, 'id' => $id]);

        if ($event === 'notification.sent' || $event === 'notification.completed') {
            $this->markSent($id, $request->all());
        }

        return response()->json(['ok' => true]);
    }

    private function markSent(string $onesignalId, array $payload): void
    {
        $delivered = $payload['successful'] ?? $payload['notification']['successful'] ?? null;

        $push = CampaignPush::where('onesignal_id', $onesignalId)->first();
        if ($push && $push->status === 'pending') {
            $push->update(['status' => 'sent']);
            return;
        }

        $push = StandalonePush::where('onesignal_id', $onesignalId)->first();
        if ($push && $push->status === 'pending') {
            $push->update(['status' => 'sent']);
        }
    }
}
