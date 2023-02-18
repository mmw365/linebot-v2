<?php
 
namespace App\Services;

use Illuminate\Support\Facades\Http;

class MessageApiClient
{
    function sendReplyTextMessage($channelToken, $replyToken, $text) {
        $messages = [];

        if(is_array($text)) {
            foreach($text as $t) {
                $messages[] = [
                    'type' => 'text',
                    'text' => $t
                ];
            }
        } else {
            $messages[] = [
                'type' => 'text',
                'text' => $text
            ];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $channelToken,
        ])->post(config('app.line_endpoint_url_reply'), [
            'replyToken' => $replyToken,
            'messages' => $messages,
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