<?php
 
namespace App\Services;

use App\Events\ReplyTextMessageCreated;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;

class ShoppingListMessageProcessor
{
    private $channelToken;

    public function __construct()
    {
        $this->channelToken = config('app.channel_token_shoppinglist');
    }

    function processMessage($replyToken, $userId, $text) 
    {
        $command = strtolower($text);
        if($command == "リスト" || $command == "りすと" || $command == "list") {
            $this->processListMessage($replyToken, $userId);
            return;
        }
        if($command == "ヘルプ" || $command == "へるぷ" || $command == "？" || $command == "help" || $command == "?") {
            $this->processHelpMessage($userId);
            return;
        }
        if($command == "クリア" || $command == "くりあ" || $command == "クリアー" || $command == "clear") {
            $this->processClearMessage($userId);
            return;
        }
        if($command == "共有解除" || $command == "解除" || $command == "unshare") {
            $this->processUnshareMessage($userId);
            return;
        }
        if($command == "共有" || $command == "シェア" || $command == "share") {
            $this->processShareMessage($userId);
            return;
        }

        $command = str_replace("　", " ", $command);
        $splitCmd = explode(" ", $command);
        $command = Util::toNarrowNumber($splitCmd[0]);
        if ($command == "リスト" || $command == "りすと" || $command == "list") {
            if(count($splitCmd) > 1) {
                $listId = Util::toNarrowNumber($splitCmd[1]);
                if(is_numeric($listId) && $listId >= 1 && $listId <= 5) {
                    $listId = (int)$listId;
                    $listName = "";
                    if(count($splitCmd) > 2) {
                        $listName = $splitCmd[2];
                    }
                    $this->processSelectListMessage($userId, $listId, $listName);
                }
            }
        }
        for ($i = 1; $i <= 5; $i++) {
            if($command == "リスト" . $i || $command == "りすと" . $i || $command == "list" . $i){
                $listName = "";
                if(count($splitCmd) > 1) {
                    $listName = $splitCmd[1];
                }
                $this->processSelectListMessage($userId, $i, $listName);
            } 
        }

        $numberList = $this->parseNumberList($text);
        if(count($numberList) > 0) {
            $this->processDeleteMessage($userId, $numberList);
        }
        
        if($this->checkPasscode($text, 12)) {
            $this->processPasscode($userId, $text);
        }
    
        $this->processAddMessage($replyToken, $userId, $text);
    }

    function processAddMessage($replyToken, $userId, $text) {
        $shoppingLists = ShoppingList::where('userid', $userId)->where('is_active', true)->get();
        if($shoppingLists->isEmpty()) {
            $shoppingList = ShoppingList::create([
                'userid' => $userId,
                'number' => 1,
                'name' => '',
                'is_active' => true,
            ]);
        } else {
            $shoppingList = $shoppingLists[0];
        }

        $itemNumber = ShoppingListItem::where('shopping_list_id', $shoppingList->id)->max('number');
        if(is_null($itemNumber)) {
            $itemNumber = 1;
        } else {
            $itemNumber += 1;
        }

        ShoppingListItem::create([
            'shopping_list_id' => $shoppingList->id,
            'number' => $itemNumber,
            'name' => $text,
        ]);

        $returnText = '「' . $text . '」を追加しました。';
        ReplyTextMessageCreated::dispatch($this->channelToken, $replyToken, $returnText);
    }

    function processListMessage($replyToken, $userId) {
        $shoppingLists = ShoppingList::where('userid', $userId)->where('is_active', true)->get();
        if($shoppingLists->isEmpty()) {
            ReplyTextMessageCreated::dispatch($this->channelToken, $replyToken, 'リストは空です');
            return;
        }

        $shoppingList = $shoppingLists[0];
        $items = ShoppingListItem::where('shopping_list_id', $shoppingList->id)->orderBy('number')->get();
        if($items->isEmpty()) {
            ReplyTextMessageCreated::dispatch($this->channelToken, $replyToken, 'リストは空です');
            return;
        }

        $returnText = "";
        foreach($items as $item) {
            if($returnText != "") {
                $returnText .= "\n";
            }
            $returnText .= "#" . $item->number . " " . $item->name;
        }

        ReplyTextMessageCreated::dispatch($this->channelToken, $replyToken, $returnText);
    }
    
    function processHelpMessage($userId) {

    }

    function processClearMessage($userId) {

    }

    function processUnshareMessage($userId) {

    }

    function processShareMessage($userId) {

    }

    function processSelectListMessage($userId, $listId, $listName) {

    }

    function processDeleteMessage($userId, $numberList) {

    }

    function processPasscode($userId, $text) {

    }

    function parseNumberList($input) {
        $input = str_replace("　", " ", $input);
        $input = str_replace("、", " ", $input);
        $input = str_replace(",", " ", $input);
        $len = -1;
        while($len != strlen($input)) {
            $input = str_replace("  ", " ", $input);
            $len = strlen($input);
        }
        $input = Util::toNarrowNumber($input);
        $splitInput = explode(" ", $input);
        $ret = [];
        foreach($splitInput as $val) {
            if(is_numeric($val)) {
                $ret[] = intval($val);
            } else {
                return [];
            }
        }
        return array_unique($ret);
    }

    function checkPasscode($code, $len) {
        if(strlen($code) != $len) {
            return 0;
        }
        return preg_match('/[A-Z0-9]{' . $len . '}/', $code);
    }
}