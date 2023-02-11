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
            $this->processHelpMessage($replyToken);
            return;
        }
        if($command == "クリア" || $command == "くりあ" || $command == "クリアー" || $command == "clear") {
            $this->processClearMessage($replyToken, $userId);
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

        $command = str_replace("　", " ", $text);
        $splitCmd = explode(" ", $command);
        $command = Util::toNarrowNumber($splitCmd[0]);
        $command = strtolower($command);
        if ($command == "リスト" || $command == "りすと" || $command == "list") {
            if(count($splitCmd) > 1) {
                $listNumber = Util::toNarrowNumber($splitCmd[1]);
                if(is_numeric($listNumber) && $listNumber >= 1 && $listNumber <= 5) {
                    $listNumber = (int)$listNumber;
                    $listName = "";
                    if(count($splitCmd) > 2) {
                        $listName = $splitCmd[2];
                    }
                    $this->processSelectListMessage($replyToken, $userId, $listNumber, $listName);
                    return;
                }
            }
        }
        for ($i = 1; $i <= 5; $i++) {
            if($command == "リスト" . $i || $command == "りすと" . $i || $command == "list" . $i){
                $listName = "";
                if(count($splitCmd) > 1) {
                    $listName = $splitCmd[1];
                }
                $this->processSelectListMessage($replyToken, $userId, $i, $listName);
                return;
            } 
        }

        $numberList = $this->parseNumberList($text);
        if(count($numberList) > 0) {
            $this->processDeleteMessage($replyToken, $userId, $numberList);
            return;
        }
        
        if($this->checkPasscode($text, 12)) {
            $this->processPasscode($userId, $text);
            return;
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
    
    function processHelpMessage($replyToken) {
        $returnText = "メッセージの送信でリストを作成します。コマンド以外は全てリストに追加されます。\n"
            . "コマンド一覧：\n"
            . "「リスト(list)」リストを表示します。\n"
            . "「リスト番号」リストから指定番号のアイテムを削除します。\n"
            . "（※コンマ／スペース区切りで複数指定できます。"
            . "※削除されると番号がふりなおされます。）\n"
            . "「クリア(clear)」リストを全削除します。\n"
            . "「リスト１〜５」リストを切替えます。\n"
            . "（※「リスト１　＜リスト名＞」でリスト名の設定ができます。）";
            ReplyTextMessageCreated::dispatch($this->channelToken, $replyToken, $returnText);
        }

    function processClearMessage($replyToken, $userId) {
        $shoppingLists = ShoppingList::where('userid', $userId)->where('is_active', true)->get();
        if($shoppingLists->isEmpty()) {
            ReplyTextMessageCreated::dispatch($this->channelToken, $replyToken, 'リストを空にしました');
            return;
        }

        $shoppingList = $shoppingLists[0];
        ShoppingListItem::where('shopping_list_id', $shoppingList->id)->delete();
        ReplyTextMessageCreated::dispatch($this->channelToken, $replyToken, 'リストを空にしました');
    }

    function processUnshareMessage($userId) {

    }

    function processShareMessage($userId) {

    }

    function processSelectListMessage($replyToken, $userId, $listNumber, $listName) {
        $shoppingLists = ShoppingList::where('userid', $userId)->where('is_active', true)->get();
        if(!$shoppingLists->isEmpty()) {
            $shoppingList = $shoppingLists[0];
            $shoppingList->is_active = false;
            $shoppingList->save();
        }

        $shoppingLists = ShoppingList::where('userid', $userId)->where('number', $listNumber)->get();
        if($shoppingLists->isEmpty()) {
            $shoppingList = ShoppingList::create([
                'userid' => $userId,
                'number' => $listNumber,
                'name' => $listName,
                'is_active' => true,
            ]);
        } else {
            $shoppingList = $shoppingLists[0];
            if($listName != '') {
                $shoppingList->name = $listName;
            }
            $shoppingList->is_active = true;
            $shoppingList->save();
        }
        $returnText = '「リスト' . $listNumber . ($shoppingList->name == '' ? '' : '（' . $shoppingList->name . '）') . '」に切替えました';
        ReplyTextMessageCreated::dispatch($this->channelToken, $replyToken, $returnText);
    }

    function processDeleteMessage($replyToken, $userId, $numberList) {
        $shoppingLists = ShoppingList::where('userid', $userId)->where('is_active', true)->get();
        if($shoppingLists->isEmpty()) {
            return;
        }

        $returnText = '';
        $shoppingList = $shoppingLists[0];
        foreach($numberList as $itemNumber) {
            $items = ShoppingListItem::where('shopping_list_id', $shoppingList->id)->where('number', $itemNumber)->get();
            if($returnText != '') {
                $returnText .= "\n";
            }
            if($items->isEmpty()) {
                $returnText .= '#' . $itemNumber . ' はありません';
            } else {
                $returnText .= '「' . $items[0]->name . '」を削除しました';
                $items[0]->delete();
            }
        }

        ReplyTextMessageCreated::dispatch($this->channelToken, $replyToken, $returnText);

        $items = ShoppingListItem::where('shopping_list_id', $shoppingList->id)->orderBy('number')->get();
        $itemNumber = 1;
        foreach($items as $item) {
            $item->number = $itemNumber;
            $item->save();
            $itemNumber += 1;
        }

        $this->processListMessage($replyToken, $userId);
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