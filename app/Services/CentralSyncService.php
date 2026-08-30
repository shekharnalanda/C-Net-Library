<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CentralSyncService
{
    public function enquiry(array $payload): bool
    {
        return $this->send('/api/v1/enquiries', $payload);
    }

    public function admission(array $payload): bool
    {
        return $this->send('/api/v1/admissions', $payload);
    }

    private function send(string $endpoint, array $payload): bool
    {
        if (! config('services.mci_central.enabled')) {
            return false;
        }

        $url = config('services.mci_central.url');
        $token = config('services.mci_central.token');

        if (! $url || ! $token) {
            Log::warning('MCI central sync skipped: configuration incomplete.');
            return false;
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders(['X-MCI-Token' => $token])
                ->timeout(config('services.mci_central.timeout', 10))
                ->retry(2, 300, throw: false)
                ->post($url.$endpoint, $payload);

            if ($response->successful() && $response->json('success') === true) {
                return true;
            }

            Log::warning('MCI central sync failed.', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('MCI central sync exception: '.$e->getMessage(), ['endpoint' => $endpoint]);
        }

        return false;
    }
}
