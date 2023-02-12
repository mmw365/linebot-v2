<?php
 
namespace App\Services;

use App\Events\PushTextMessageCreated;
use App\Events\ReplyTextMessageCreated;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\ShoppingListShareCode;
use App\Models\ShoppingListShareInfo;
use Carbon\Carbon;

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
            $this->processUnshareMessage($replyToken, $userId);
            return;
        }
        if($command == "共有" || $command == "シェア" || $command == "share") {
            $this->processShareMessage($replyToken, $userId);
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
            $this->processPasscode($replyToken, $userId, $text);
            return;
        }
    
        $this->processAddMessage($replyToken, $userId, $text);
    }

    function processAddMessage($replyToken, $userId, $text) {
        $shoppingList = $this->getActiveShoppingListOrCreate($userId);

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
        $shoppingList = $this->getActiveShoppingListOrNull($userId);
        if(is_null($shoppingList)) {
            ReplyTextMessageCreated::dispatch($this->channelToken, $replyToken, 'リストは空です');
            return;
        }

        $shareInfo = ShoppingListShareInfo::where('shopping_list_id', $shoppingList->id)->first();
        if(!is_null($shareInfo)) {
            $shoppingList = ShoppingList::find($shareInfo->ref_shopping_list_id);
        }

        $returnText = $this->formatListItems($shoppingList);

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
        $shoppingList = $this->getActiveShoppingListOrNull($userId);
        if(is_null($shoppingList)) {
            ReplyTextMessageCreated::dispatch($this->channelToken, $replyToken, 'リストを空にしました');
            return;
        }

        $shareInfo = ShoppingListShareInfo::where('shopping_list_id', $shoppingList->id)->first();
        if(!is_null($shareInfo)) {
            ReplyTextMessageCreated::dispatch($this->channelToken, $replyToken, '共有リストのクリアはできません');
            return;
        }

        ShoppingListItem::where('shopping_list_id', $shoppingList->id)->delete();
        ReplyTextMessageCreated::dispatch($this->channelToken, $replyToken, 'リストを空にしました');
        $this->sendUpdateNotification($shoppingList);
    }

    function processUnshareMessage($replyToken, $userId) {
        $shoppingList = $this->getActiveShoppingListOrNull($userId);
        if(is_null($shoppingList)) {
            ReplyTextMessageCreated::dispatch($this->channelToken, $replyToken, '共有リストではありません');
            return;
        }

        $shareInfo = ShoppingListShareInfo::where('shopping_list_id', $shoppingList->id)->first();
        if(!is_null($shareInfo)) {
            $refShoppingList = ShoppingList::find($shareInfo->ref_shopping_list_id);
            PushTextMessageCreated::dispatch($this->channelToken, $refShoppingList->userid, 'リスト' . $refShoppingList->number . '（公開中）の共有が解除されました');
            $shareInfo->delete();
            ReplyTextMessageCreated::dispatch($this->channelToken, $replyToken, '共有を解除しました');
            return;
        }

        $shareInfos = ShoppingListShareInfo::where('ref_shopping_list_id', $shoppingList->id)->get();
        if(!$shareInfos->isEmpty()) {
            foreach($shareInfos as $shareInfo) {
                $refByShoppingList = ShoppingList::find($shareInfo->shopping_list_id);
                PushTextMessageCreated::dispatch($this->channelToken, $refByShoppingList->userid, 'リスト' . $refByShoppingList->number . '（参照中）の共有が解除されました');
                $shareInfo->delete();
            }
            ReplyTextMessageCreated::dispatch($this->channelToken, $replyToken, '共有を解除しました');
            return;
        }

        ReplyTextMessageCreated::dispatch($this->channelToken, $replyToken, '共有リストではありません');
    }

    function processShareMessage($replyToken, $userId) {
        $shoppingList = $this->getActiveShoppingListOrCreate($userId);
        $shareInfo = ShoppingListShareInfo::where('shopping_list_id', $shoppingList->id)->first();
        if(!is_null($shareInfo)) {
            ReplyTextMessageCreated::dispatch($this->channelToken, $replyToken, '共有リストは共有できません');
            return;
        }
        $code = Util::createPasscode(12);
        ShoppingListShareCode::create([
            'code' => $code,
            'shopping_list_id' => $shoppingList->id,
            'expires_at' => Carbon::now()->addMinutes(30),
        ]);
        ReplyTextMessageCreated::dispatch($this->channelToken, $replyToken,
                "リストを共有したい友達に、下記のコードを渡してください\n" . $code);
    }

    function processSelectListMessage($replyToken, $userId, $listNumber, $listName) {
        $shoppingList = $this->getActiveShoppingListOrNull($userId);
        if(!is_null($shoppingList)) {
            $shoppingList->is_active = false;
            $shoppingList->save();
        }

        $shoppingList = $this->getShoppingListByNumberOrCreate($userId, $listNumber);
        if($listName != '') {
            $shoppingList->name = $listName;
        }
        $shoppingList->is_active = true;
        $shoppingList->save();

        $returnText = '「リスト' . $listNumber . ($shoppingList->name == '' ? '' : '（' . $shoppingList->name . '）') . '」に切替えました';
        ReplyTextMessageCreated::dispatch($this->channelToken, $replyToken, $returnText);
        $this->processListMessage($replyToken, $userId);
    }

    function processDeleteMessage($replyToken, $userId, $numberList) {
        $shoppingList = $this->getActiveShoppingListOrNull($userId);
        if(is_null($shoppingList)) {
            return;
        }

        $shareInfo = ShoppingListShareInfo::where('shopping_list_id', $shoppingList->id)->first();
        if(!is_null($shareInfo)) {
            $shoppingListToUpdate = ShoppingList::find($shareInfo->ref_shopping_list_id);
        } else {
            $shoppingListToUpdate = $shoppingList;
        }

        $returnText = '';
        foreach($numberList as $itemNumber) {
            $items = ShoppingListItem::where('shopping_list_id', $shoppingListToUpdate->id)->where('number', $itemNumber)->get();
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

        $items = ShoppingListItem::where('shopping_list_id', $shoppingListToUpdate->id)->orderBy('number')->get();
        $itemNumber = 1;
        foreach($items as $item) {
            $item->number = $itemNumber;
            $item->save();
            $itemNumber += 1;
        }

        $this->processListMessage($replyToken, $userId);
        $this->sendUpdateNotification($shoppingList);
    }

    function processPasscode($replyToken, $userId, $text) {
        $shoppingList = $this->getActiveShoppingListOrCreate($userId);
        $shareInfo = ShoppingListShareInfo::where('shopping_list_id', $shoppingList->id)->first();
        if(!is_null($shareInfo)) {
            ReplyTextMessageCreated::dispatch($this->channelToken, $replyToken, "共有リストを使用（参照）中です\n解除するか他のリストを選択してください");
            return;
        }
        $shareInfo = ShoppingListShareInfo::where('ref_shopping_list_id', $shoppingList->id)->first();
        if(!is_null($shareInfo)) {
            ReplyTextMessageCreated::dispatch($this->channelToken, $replyToken, "リストが共有（公開）されているため設定できません\n解除するか他のリストを選択してください");
            return;
        }

        ShoppingListShareCode::where('expires_at', '<', Carbon::now())->delete();
        $shareCode = ShoppingListShareCode::where('code', $text)->first();
        if(is_null($shareCode)) {
            ReplyTextMessageCreated::dispatch($this->channelToken, $replyToken, '有効でないコードです');
            return;
        }
        ShoppingListShareInfo::create([
            'shopping_list_id' => $shoppingList->id,
            'ref_shopping_list_id' => $shareCode->shopping_list_id,
        ]);

        ReplyTextMessageCreated::dispatch($this->channelToken, $replyToken, '共有を設定しました');
        $this->processListMessage($replyToken, $userId);
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

    private function getActiveShoppingListOrNull($userId) {
        $shoppingLists = ShoppingList::where('userid', $userId)->where('is_active', true)->get();
        if($shoppingLists->isEmpty()) {
            return null;
        } else {
            return $shoppingLists[0];
        }
    }

    private function getActiveShoppingListOrCreate($userId) {
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
        return $shoppingList;
    }

    private function getShoppingListByNumberOrCreate($userId, $listNumber) {
        $shoppingLists = ShoppingList::where('userid', $userId)->where('number', $listNumber)->get();
        if($shoppingLists->isEmpty()) {
            $shoppingList = ShoppingList::create([
                'userid' => $userId,
                'number' => $listNumber,
                'name' => '',
                'is_active' => true,
            ]);
        } else {
            $shoppingList = $shoppingLists[0];
        }
        return $shoppingList;
    }

    private function sendUpdateNotification($shoppingList) {
        if(is_null($shoppingList)) {
            return;
        }
        $pushTest = "リストが更新されました\n";

        // when this is refering to shared list
        $shareInfo = ShoppingListShareInfo::where('shopping_list_id', $shoppingList->id)->first();
        if(!is_null($shareInfo)) {
            $refShoppingList =  ShoppingList::find($shareInfo->ref_shopping_list_id);
            if($refShoppingList->is_active) {
                $pushTest .= $this->formatListItems($refShoppingList);
                PushTextMessageCreated::dispatch($this->channelToken, $refShoppingList->userid, $pushTest);
            }
            return;
        }

        // when this list is referred by other users
        $pushTest .= $this->formatListItems($shoppingList);
        $shareInfos = ShoppingListShareInfo::where('ref_shopping_list_id', $shoppingList->id)->get();
        foreach($shareInfos as $shareInfo) {
            $refByShoppingList =  ShoppingList::find($shareInfo->shopping_list_id);
            if($refByShoppingList->is_active) {
                PushTextMessageCreated::dispatch($this->channelToken, $refByShoppingList->userid, $pushTest);
            }
        }
    }

    private function formatListItems($shoppingList)
    {
        $items = ShoppingListItem::where('shopping_list_id', $shoppingList->id)->orderBy('number')->get();
        if($items->isEmpty()) {
            return 'リストは空です';
        }

        $returnText = "";
        foreach($items as $item) {
            if($returnText != "") {
                $returnText .= "\n";
            }
            $returnText .= "#" . $item->number . " " . $item->name;
        }

        return $returnText;
    }
}