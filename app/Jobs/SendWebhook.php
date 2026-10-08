<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;

/**
 * Envía un evento firmado al sistema del prestamista (ej. afdeveloper.com).
 * Firma: X-CrediTrack-Signature: t=<timestamp>,v1=<hmac_sha256(secret, "t.body")>
 */
class SendWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 6;

    public function __construct(public int $userId, public array $payload) {}

    public function backoff(): array
    {
        return [10, 60, 300, 1800, 7200];
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user?->webhook_url || ! $user->webhook_secret) {
            return;
        }

        $body = json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $user->webhook_secret);

        Http::withHeaders([
            'X-CrediTrack-Event' => $this->payload['event'],
            'X-CrediTrack-Signature' => "t={$timestamp},v1={$signature}",
        ])
            ->withBody($body, 'application/json')
            ->timeout(15)
            ->post($user->webhook_url)
            ->throw();
    }
}
