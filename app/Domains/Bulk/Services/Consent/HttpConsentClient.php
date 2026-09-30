<?php

namespace App\Domains\Bulk\Services\Consent;

use App\Domains\Bulk\Enums\ConsentAction;
use Illuminate\Support\Facades\Http;
use Throwable;

class HttpConsentClient implements ConsentClientInterface
{
    public function updateConsent(string $userId, string $phone, ConsentAction $action): ConsentResult
    {
        $baseUrl = rtrim((string) config('consent.base_url'), '/');

        if ($baseUrl === '') {
            return ConsentResult::failed('consent_not_configured', 'Consent service base URL is not configured.');
        }

        try {
            $request = Http::timeout((float) config('consent.timeout', 5))
                ->acceptJson()
                ->asJson();

            $token = config('consent.token');
            if (is_string($token) && $token !== '') {
                $request = $request->withToken($token);
            }

            $response = $request->post("{$baseUrl}/consents", [
                'user_id' => $userId,
                'phone_number' => $phone,
                'action' => $action->value,
            ]);

            if ($response->successful()) {
                return ConsentResult::ok();
            }

            return ConsentResult::failed(
                'consent_rejected',
                $response->json('message') ?? 'Consent service rejected the request.',
            );
        } catch (Throwable $exception) {
            return ConsentResult::failed('consent_unavailable', $exception->getMessage());
        }
    }
}
