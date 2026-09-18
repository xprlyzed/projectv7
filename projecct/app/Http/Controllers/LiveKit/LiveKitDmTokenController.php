<?php

namespace App\Http\Controllers\LiveKit;

use App\Http\Controllers\Controller;
use Agence104\LiveKit\AccessToken;
use Agence104\LiveKit\AccessTokenOptions;
use Agence104\LiveKit\VideoGrant;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Özel mesaj (DM) odası için LiveKit katılım token'ı üretir.
 * Yalnızca konuşmanın iki katılımcısı 'dm-{conversationId}' odasına katılabilir.
 * İstemciler yalnızca DİNLER (canPublishData=false); mesajlar backend'den yayınlanır.
 */
class LiveKitDmTokenController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $apiKey    = config('services.livekit.api_key');
        $apiSecret = config('services.livekit.api_secret');
        $serverUrl = config('services.livekit.url');

        if (! $apiKey || ! $apiSecret || ! $serverUrl) {
            return response()->json([
                'message'    => 'Canlı altyapı (LiveKit) henüz yapılandırılmadı.',
                'configured' => false,
            ], 503);
        }

        $data = $request->validate([
            'conversation' => ['required'],
        ]);

        $conversation = Conversation::findOrFail($data['conversation']);
        $user = $request->user();

        abort_unless($user && $conversation->hasParticipant($user), 403);

        $room     = 'dm-'.$conversation->id;
        $identity = 'user-'.$user->id;

        $options = (new AccessTokenOptions())
            ->setIdentity($identity)
            ->setName($user->name)
            ->setTtl(3600);

        $grant = (new VideoGrant())
            ->setRoomJoin()
            ->setRoomName($room)
            ->setCanPublish(false)
            ->setCanSubscribe(true)
            ->setCanPublishData(false);

        $token = (new AccessToken($apiKey, $apiSecret))
            ->init($options)
            ->setGrant($grant)
            ->toJwt();

        return response()->json([
            'configured'        => true,
            'server_url'        => $serverUrl,
            'participant_token' => $token,
            'room'              => $room,
            'identity'          => $identity,
        ]);
    }
}
