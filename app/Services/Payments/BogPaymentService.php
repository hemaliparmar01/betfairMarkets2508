<?php

namespace App\Services\Payments;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class BogPaymentService
{
    private string $baseUrl;

    private string $authUrl;

    private string $clientId;

    private string $clientSecret;

    public function __construct()
    {
        $this->baseUrl = (string) config('services.bog.base_url');
        $this->authUrl = (string) config('services.bog.auth_url');
        $this->clientId = (string) config('services.bog.client_id');
        $this->clientSecret = (string) config('services.bog.secret');

        if (
            $this->baseUrl === ''
            || $this->authUrl === ''
            || $this->clientId === ''
            || $this->clientSecret === ''
        ) {
            throw new RuntimeException(
                'BOG credentials are not configured.'
            );
        }
    }

    public function accessToken(): string
    {
        $cacheKey = 'bog:payment:access-token:'.hash('sha256', $this->baseUrl.'|'.$this->clientId);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }
        $response = Http::asForm()->connectTimeout(5)->timeout(20)
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->post($this->authUrl, ['grant_type' => 'client_credentials'])
            ->throw()->json();
        $token = (string) ($response['access_token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('BOG access token was not returned.');
        }
        $cacheSeconds = max(0, (int) ($response['expires_in'] ?? 0) - 30);
        if ($cacheSeconds > 0) {
            Cache::put($cacheKey, $token, now()->addSeconds($cacheSeconds));
        }

        return $token;
    }

    public function createOrder(
        array $payload
    ): array {
        return $this->sendJsonRequest(
            method: 'post',
            path: '/payments/v1/ecommerce/orders',
            payload: $payload
        )->json();
    }

    public function enableAutomaticPayments(
        string $orderId
    ): void {
        $this->sendJsonRequest(
            method: 'put',
            path: '/payments/v1/orders/'
                .rawurlencode($orderId)
                .'/subscriptions'
        );
    }

    public function chargeAutomaticPayment(
        string $parentOrderId,
        string $callbackUrl,
        string $externalOrderId
    ): array {
        return $this->sendJsonRequest(
            method: 'post',
            path: '/payments/v1/ecommerce/orders/'
                .rawurlencode($parentOrderId)
                .'/subscribe',
            payload: [
                'callback_url' => $callbackUrl,

                'external_order_id' => $externalOrderId,
            ]
        )->json();
    }

    public function deleteSavedCard(
        string $parentOrderId,
        ?string $idempotencyKey = null
    ): void {
        $this->sendJsonRequest(
            method: 'delete',
            path: '/payments/v1/charges/card/'
                .rawurlencode($parentOrderId)
        );
    }

    public function getOrderDetails(
        string $orderId
    ): array {
        return $this->sendJsonRequest(
            method: 'get',
            path: '/payments/v1/receipt/'
                .rawurlencode($orderId)
        )->json();
    }

    public function verifyCallbackSignature(
        string $rawBody,
        ?string $signature
    ): bool {
        if (! $signature) {
            return false;
        }

        $publicKey = (string) config('services.bog.public_key');
        if ($publicKey === '') {
            return false;
        }

        $decodedSignature = base64_decode(
            $signature,
            true
        );

        if ($decodedSignature === false) {
            return false;
        }

        return openssl_verify(
            $rawBody,
            $decodedSignature,
            $publicKey,
            OPENSSL_ALGO_SHA256
        ) === 1;
    }

    private function sendJsonRequest(
        string $method,
        string $path,
        array $payload = []
    ): Response {
        $url = $this->baseUrl.$path;

        $idempotencyKey = (string) ($payload['external_order_id'] ?? Str::uuid());
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $request = Http::connectTimeout(5)->timeout(20)->withToken(
                $this->accessToken()
            )
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'Idempotency-Key' => $idempotencyKey,

                    'Accept-Language' => 'en',
                ]);

            $response = match (strtolower($method)) {
                'get' => $request->get(
                    $url
                ),

                'post' => $request->post(
                    $url,
                    $payload
                ),

                'put' => $request->put(
                    $url,
                    $payload
                ),

                'delete' => $request->delete(
                    $url,
                    $payload
                ),

                default => throw new RuntimeException(
                    'Unsupported BOG HTTP method.'
                ),
            };

            if ($response->status() !== 401) {
                return $response->throw();
            }
            Cache::forget('bog:payment:access-token:'.hash('sha256', $this->baseUrl.'|'.$this->clientId));
        }

        return $response->throw();
    }
}
