<?php

namespace App\Jobs;

use App\Models\StandalonePush;
use App\Services\OneSignalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendStandalonePushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;

    public function __construct(public readonly int $pushId) {}

    public function handle(OneSignalService $onesignal): void
    {
        $push = StandalonePush::findOrFail($this->pushId);

        try {
            $onesignalId = $onesignal->sendStandalone($push, $push->send_at);

            if ($onesignalId) {
                $push->update([
                    'onesignal_id' => $onesignalId,
                    'send_error'   => null,
                ]);
            } else {
                $push->update([
                    'status'     => 'error',
                    'send_error' => 'OneSignal nevrátil ID. Skontrolujte API kľúč a nastavenia aplikácie.',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('SendStandalonePushJob failed', ['push' => $this->pushId, 'error' => $e->getMessage()]);

            $push->update([
                'status'      => 'error',
                'send_error'  => $e->getMessage(),
                'retry_count' => $push->retry_count + 1,
            ]);
        }
    }
}
