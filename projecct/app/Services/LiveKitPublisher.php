<?php

namespace App\Services;

use Agence104\LiveKit\RoomServiceClient;

/**
 * Sunucudan LiveKit odasına GÜVENİLİR gerçek-zamanlı veri paketi yayınlar.
 * Kaynak doğruluğu backend'de; istemciler yalnızca dinler (spoof engellenir).
 * Hiçbir hata isteği bozmaz (sessiz raporlanır).
 */
class LiveKitPublisher
{
    public static function publish(int $auctionId, string $type, array $payload = []): void
    {
        self::publishToRoom('auction-'.$auctionId, $type, $payload, 'auction-events');
    }

    /**
     * Herhangi bir LiveKit odasına (ör. 'dm-{conversationId}') güvenilir veri paketi yayınlar.
     */
    public static function publishToRoom(string $room, string $type, array $payload = [], string $topic = 'auction-events'): void
    {
        $url    = config('services.livekit.url');
        $key    = config('services.livekit.api_key');
        $secret = config('services.livekit.api_secret');

        if (! $url || ! $key || ! $secret) {
            return;
        }

        // wss://host -> https://host (server API HTTP üzerinden konuşur)
        $host = preg_replace('#^wss?://#i', 'https://', $url);

        try {
            $client = new RoomServiceClient($host, $key, $secret);
            $data = json_encode(array_merge([
                'type'   => $type,
                'sentAt' => (int) round(microtime(true) * 1000),
            ], $payload));

            // kind 0 = RELIABLE (sıralı, garantili teslim)
            $client->sendData($room, $data, 0, [], $topic);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
