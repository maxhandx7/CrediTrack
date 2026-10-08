<?php

namespace App\Services\Waha;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class WahaClient
{
    public function enabled(): bool
    {
        return (bool) config('services.waha.enabled') && filled(config('services.waha.url'));
    }

    public function sendText(string $phone, string $text): void
    {
        if (! $this->enabled()) {
            return;
        }

        Http::withHeaders(array_filter(['X-Api-Key' => config('services.waha.key')]))
            ->acceptJson()
            ->timeout(20)
            ->post(config('services.waha.url').'/api/sendText', [
                'session' => config('services.waha.session'),
                'chatId' => static::chatId($phone),
                'text' => $text,
            ])
            ->throw();
    }

    /** "300 123 4567" → "573001234567@c.us". Acepta números con o sin indicativo. */
    public static function chatId(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        $cc = (string) config('services.waha.country_code', '57');

        if (strlen($digits) === 10 && ! str_starts_with($digits, $cc)) {
            $digits = $cc.$digits;
        }

        if (strlen($digits) < 11) {
            throw new RuntimeException("Número de WhatsApp inválido: {$phone}");
        }

        return $digits.'@c.us';
    }
}
