<?php
 
namespace App\Services;

use Illuminate\Support\Facades\Http;

class MessageApiClient
{
    function sendReplyTextMessage($channelToken, $replyToken, $text) {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $channelToken,
        ])->post(config('app.line_endpoint_url_reply'), [
            'replyToken' => $replyToken,
            'messages' => [[
                'type' => 'text',
                'text' => $text
            ]],
        ]);
    }

    function sendPushTextMessage($channelToken, $userId, $text) {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $channelToken,
        ])->post(config('app.line_endpoint_url_push'), [
            'to' => $userId,
            'messages' => [[
                'type' => 'text',
                'text' => $text
            ]],
        ]);
    }
}