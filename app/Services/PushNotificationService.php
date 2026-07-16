<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    /**
     * Send push notification to a single device.
     */
    public static function sendToDevice(DeviceToken $device, string $title, string $body, ?array $data = null): bool
    {
        $serviceAccountJson = Setting::get('firebase_service_account');
        if (! $serviceAccountJson) {
            return false;
        }

        try {
            $accessToken = self::getAccessToken($serviceAccountJson);
            if (! $accessToken) {
                return false;
            }

            $projectId = self::getProjectId($serviceAccountJson);
            $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

            $message = [
                'message' => [
                    'token' => $device->token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $data ?? [],
                    'android' => [
                        'notification' => [
                            'channel_id' => 'hello_store',
                            'sound' => 'default',
                        ],
                    ],
                ],
            ];

            $response = Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $message);

            if ($response->successful()) {
                $device->update(['last_used_at' => now()]);

                return true;
            }

            Log::warning('FCM push failed', [
                'token' => substr($device->token, 0, 20).'...',
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->status() === 404 || $response->status() === 400) {
                $device->update(['is_active' => false]);
            }

            return false;
        } catch (\Exception $e) {
            Log::error('FCM push error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Send push notification to all active devices of a user.
     */
    public static function sendToUser(int $userId, string $title, string $body, ?array $data = null): int
    {
        $devices = DeviceToken::where('user_id', $userId)->active()->get();
        $sent = 0;

        foreach ($devices as $device) {
            if (self::sendToDevice($device, $title, $body, $data)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Send push notification to multiple users.
     */
    public static function sendToUsers(array $userIds, string $title, string $body, ?array $data = null): int
    {
        $devices = DeviceToken::whereIn('user_id', $userIds)->active()->get();
        $sent = 0;

        foreach ($devices as $device) {
            if (self::sendToDevice($device, $title, $body, $data)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Generate OAuth2 access token from Firebase service account JSON.
     */
    private static function getAccessToken(string $serviceAccountJson): ?string
    {
        try {
            $serviceAccount = json_decode($serviceAccountJson, true);
            if (! $serviceAccount || ! isset($serviceAccount['private_key'], $serviceAccount['client_email'])) {
                return null;
            }

            $now = time();
            $header = self::base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $payload = self::base64url(json_encode([
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            $data = "{$header}.{$payload}";
            openssl_sign($data, $signature, $serviceAccount['private_key'], OPENSSL_ALGO_SHA256);
            $jwt = $data.'.'.self::base64url($signature);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->successful()) {
                return $response->json('access_token');
            }

            Log::error('FCM token exchange failed', ['body' => $response->body()]);

            return null;
        } catch (\Exception $e) {
            Log::error('FCM access token error: '.$e->getMessage());

            return null;
        }
    }

    private static function getProjectId(string $serviceAccountJson): ?string
    {
        try {
            $serviceAccount = json_decode($serviceAccountJson, true);

            return $serviceAccount['project_id'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
