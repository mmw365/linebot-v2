<?php

namespace App\Http\Controllers;

use App\Services\ShoppingListMessageProcessor;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    function shoppinglist(Request $request, ShoppingListMessageProcessor $shoppingListMessageProcessor) {
        $type = $request->input('events.0.type');
        if($type != 'message') {
            return response()->json([]);
        }
        $messagType = $request->input('events.0.message.type');
        if($messagType != 'text') {
            return response()->json([]);
        }
        $replyToken = $request->input('events.0.replyToken');
        $text = $request->input('events.0.message.text');
        $userId = $request->input('events.0.source.userId');
        $shoppingListMessageProcessor->processMessage($replyToken, $userId, $text);
        return response()->json([]);
    }
}
