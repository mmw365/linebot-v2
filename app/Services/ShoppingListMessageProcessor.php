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
    const LIST_COMMANDS = ['リスト', 'りすと', 'list'];
    const HELP_COMMANDS = ['ヘルプ', 'へるぷ', '？', 'help', '?'];
    const CLEAR_COMMANDS = ['クリア', 'くりあ', 'クリアー', 'clear'];
    const SHARE_COMMANDS = ['共有', 'シェア', 'share'];
    const UNSHARE_COMMANDS = ['共有解除', '解除', 'unshare'];

    private $channelToken;
    private $replyToken;
    private $userId;

    public function __construct()
    {
        $this->channelToken = config('app.channel_token_shoppinglist');
    }

    function processMessage($replyToken, $userId, $text) 
    {
        $this->replyToken = $replyToken;
        $this->userId = $userId;

        $command = strtolower($text);
        if(in_array($command, self::LIST_COMMANDS)) {
            $this->processListMessage();
            return;
        }
        if(in_array($command, self::HELP_COMMANDS)) {
            $this->processHelpMessage();
            return;
        }
        if(in_array($command, self::CLEAR_COMMANDS)) {
            $this->processClearMessage();
            return;
        }
        if(in_array($command, self::UNSHARE_COMMANDS)) {
            $this->processUnshareMessage();
            return;
        }
        if(in_array($command, self::SHARE_COMMANDS)) {
            $this->processShareMessage();
            return;
        }

        $command = $this->parseSelectListCommand($text);
        if(!is_null($command)) {
            $this->processSelectListMessage($command['listNumber'], $command['listName']);
            return;
        }

        $numberList = $this->parseNumberList($text);
        if(count($numberList) > 0) {
            $this->processDeleteMessage($numberList);
            return;
        }
        
        if($this->checkPasscode($text, 12)) {
            $this->processPasscode($text);
            return;
        }
    
        $this->processAddMessage($text);
    }

    function parseSelectListCommand($text)
    {
        $command = str_replace("　", " ", $text);
        $splitCmd = explode(" ", $command);
        $command = Util::toNarrowNumber($splitCmd[0]);
        $command = strtolower($command);
        if(in_array($command, self::LIST_COMMANDS)) {
            if(count($splitCmd) > 1) {
                $listNumber = Util::toNarrowNumber($splitCmd[1]);
                if(is_numeric($listNumber) && $listNumber >= 1 && $listNumber <= 5) {
                    $listNumber = (int)$listNumber;
                    $listName = "";
                    if(count($splitCmd) > 2) {
                        $listName = $splitCmd[2];
                    }
                    return [
                        'listNumber' => $listNumber,
                        'listName' => $listName
                    ];
                }
            }
        }
        for ($i = 1; $i <= 5; $i++) {
            foreach(self::LIST_COMMANDS as $listCommand) {
                if($command == $listCommand . $i) {
                    $listName = "";
                    if(count($splitCmd) > 1) {
                        $listName = $splitCmd[1];
                    }
                    return [
                        'listNumber' => $i,
                        'listName' => $listName
                    ];
                } 
            }
        }
        return null;
    }

    function processAddMessage($text) {
        $shoppingList = $this->getActiveShoppingListOrCreate($this->userId);

        $shareInfo = $shoppingList->shareInfo;
        if(!is_null($shareInfo)) {
            $shoppingListToUpdate = $shareInfo->refShoppingList;
        } else {
            $shoppingListToUpdate = $shoppingList;
        }        

        $itemNumber = $this->getNextShoppingListItemNumber($shoppingListToUpdate);

        ShoppingListItem::create([
            'shopping_list_id' => $shoppingListToUpdate->id,
            'number' => $itemNumber,
            'name' => $text,
        ]);

        $returnText = '「' . $text . '」を追加しました。';
        $this->sendReplyMessage($returnText);
        $this->sendUpdateNotification($shoppingList);
    }

    function processListMessage() {
        $shoppingList = $this->getActiveShoppingListOrNull($this->userId);
        if(is_null($shoppingList)) {
            $this->sendReplyMessage('リストは空です');
            return;
        }

        $shareInfo = $shoppingList->shareInfo;
        if(!is_null($shareInfo)) {
            $shoppingList = $shareInfo->refShoppingList;
        }

        $returnText = $this->formatListItems($shoppingList);
        $this->sendReplyMessage($returnText);
    }
    
    function processHelpMessage() {
        $returnText = "メッセージの送信でリストを作成します。コマンド以外は全てリストに追加されます。\n"
            . "コマンド一覧：\n"
            . "「リスト(list)」リストを表示します。\n"
            . "「リスト番号」リストから指定番号のアイテムを削除します。\n"
            . "（※コンマ／スペース区切りで複数指定できます。"
            . "※削除されると番号がふりなおされます。）\n"
            . "「クリア(clear)」リストを全削除します。\n"
            . "「リスト１〜５」リストを切替えます。\n"
            . "（※「リスト１　＜リスト名＞」でリスト名の設定ができます。）";
        $this->sendReplyMessage($returnText);
    }

    function processClearMessage() {
        $shoppingList = $this->getActiveShoppingListOrNull($this->userId);
        if(is_null($shoppingList)) {
            $this->sendReplyMessage('リストを空にしました');
            return;
        }

        $shareInfo = $shoppingList->shareInfo;
        if(!is_null($shareInfo)) {
            $this->sendReplyMessage('共有リストのクリアはできません');
            return;
        }

        ShoppingListItem::where('shopping_list_id', $shoppingList->id)->delete();
        $this->sendReplyMessage('リストを空にしました');
        $this->sendUpdateNotification($shoppingList);
    }

    function processUnshareMessage() {
        $shoppingList = $this->getActiveShoppingListOrNull($this->userId);
        if(is_null($shoppingList)) {
            $this->sendReplyMessage('共有リストではありません');
            return;
        }

        $shareInfo = $shoppingList->shareInfo;
        if(!is_null($shareInfo)) {
            $refShoppingList = $shoppingList = $shareInfo->refShoppingList;
            $this->sendPushMessage($refShoppingList->userid, 'リスト' . $refShoppingList->number . '（公開中）の共有が解除されました');
            $shareInfo->delete();
            $this->sendReplyMessage('共有を解除しました');
            return;
        }

        
        $shareInfos = $shoppingList->refShareInfos;
        if(!$shareInfos->isEmpty()) {
            foreach($shareInfos as $shareInfo) {
                $refByShoppingList = $shoppingList = $shareInfo->shoppingList;
                $this->sendPushMessage($refByShoppingList->userid, 'リスト' . $refByShoppingList->number . '（参照中）の共有が解除されました');
                $shareInfo->delete();
            }
            $this->sendReplyMessage('共有を解除しました');
            return;
        }

        $this->sendReplyMessage('共有リストではありません');
    }

    function processShareMessage() {
        $shoppingList = $this->getActiveShoppingListOrCreate();
        $shareInfo = $shoppingList->shareInfo;
        if(!is_null($shareInfo)) {
            $this->sendReplyMessage('共有リストは共有できません');
            return;
        }
        $code = Util::createPasscode(12);
        ShoppingListShareCode::create([
            'code' => $code,
            'shopping_list_id' => $shoppingList->id,
            'expires_at' => Carbon::now()->addMinutes(30),
        ]);
        $this->sendReplyMessage("リストを共有したい友達に、下記のコードを渡してください\n" . $code);
    }

    function processSelectListMessage($listNumber, $listName) {
        $shoppingList = $this->getActiveShoppingListOrNull();
        if(!is_null($shoppingList)) {
            $shoppingList->is_active = false;
            $shoppingList->save();
        }

        $shoppingList = $this->getShoppingListByNumberOrCreate($listNumber);
        if($listName != '') {
            $shoppingList->name = $listName;
        }
        $shoppingList->is_active = true;
        $shoppingList->save();

        $returnText = '「リスト' . $listNumber . ($shoppingList->name == '' ? '' : '（' . $shoppingList->name . '）') . '」に切替えました';
        $this->sendReplyMessage($returnText);
        $this->processListMessage();
    }

    function processDeleteMessage($numberList) {
        $shoppingList = $this->getActiveShoppingListOrNull();
        if(is_null($shoppingList)) {
            return;
        }

        $shareInfo = $shoppingList->shareInfo;
        if(!is_null($shareInfo)) {
            $shoppingListToUpdate = $shareInfo->refShoppingList;
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

        $this->sendReplyMessage($returnText);
        $this->resetShoppingListItemNumber($shoppingListToUpdate);
        $this->processListMessage();
        $this->sendUpdateNotification($shoppingList);
    }

    function processPasscode($text) {
        $shoppingList = $this->getActiveShoppingListOrCreate();
        $shareInfo = $shoppingList->shareInfo;
        if(!is_null($shareInfo)) {
            $this->sendReplyMessage("共有リストを使用（参照）中です\n解除するか他のリストを選択してください");
            return;
        }
        $shareInfos = $shoppingList->refShareInfos;
        if(!$shareInfos->isEmpty()) {
            $this->sendReplyMessage("リストが共有（公開）されているため設定できません\n解除するか他のリストを選択してください");
            return;
        }

        ShoppingListShareCode::where('expires_at', '<', Carbon::now())->delete();
        $shareCode = ShoppingListShareCode::where('code', $text)->first();
        if(is_null($shareCode)) {
            $this->sendReplyMessage('有効でないコードです');
            return;
        }
        ShoppingListShareInfo::create([
            'shopping_list_id' => $shoppingList->id,
            'ref_shopping_list_id' => $shareCode->shopping_list_id,
        ]);

        $this->sendReplyMessage('共有を設定しました');
        $this->processListMessage();
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

    private function getActiveShoppingListOrNull() {
        $shoppingLists = ShoppingList::where('userid', $this->userId)->where('is_active', true)->get();
        if($shoppingLists->isEmpty()) {
            return null;
        } else {
            return $shoppingLists[0];
        }
    }

    private function getActiveShoppingListOrCreate() {
        $shoppingLists = ShoppingList::where('userid', $this->userId)->where('is_active', true)->get();
        if($shoppingLists->isEmpty()) {
            $shoppingList = ShoppingList::create([
                'userid' => $this->userId,
                'number' => 1,
                'name' => '',
                'is_active' => true,
            ]);
        } else {
            $shoppingList = $shoppingLists[0];
        }
        return $shoppingList;
    }

    private function getNextShoppingListItemNumber($shoppingList) {
        $itemNumber = ShoppingListItem::where('shopping_list_id', $shoppingList->id)->max('number');
        if(is_null($itemNumber)) {
            $itemNumber = 1;
        } else {
            $itemNumber += 1;
        }
        return $itemNumber;
    }

    private function getShoppingListByNumberOrCreate($listNumber)
    {
        $shoppingLists = ShoppingList::where('userid', $this->userId)->where('number', $listNumber)->get();
        if($shoppingLists->isEmpty()) {
            $shoppingList = ShoppingList::create([
                'userid' => $this->userId,
                'number' => $listNumber,
                'name' => '',
                'is_active' => true,
            ]);
        } else {
            $shoppingList = $shoppingLists[0];
        }
        return $shoppingList;
    }

    private function resetShoppingListItemNumber($shoppingList)
    {
        $items = ShoppingListItem::where('shopping_list_id', $shoppingList->id)->orderBy('number')->get();
        $itemNumber = 1;
        foreach($items as $item) {
            $item->number = $itemNumber;
            $item->save();
            $itemNumber += 1;
        }
    }

    private function sendUpdateNotification($shoppingList)
    {
        if(is_null($shoppingList)) {
            return;
        }
        $pushTest = "リストが更新されました\n";

        // when this is refering to shared list
        $shareInfo = $shoppingList->shareInfo;
        if(!is_null($shareInfo)) {
            $refShoppingList =  $shareInfo->refShoppingList;
            if($refShoppingList->is_active) {
                $pushTest .= $this->formatListItems($refShoppingList);
                $this->sendPushMessage($refShoppingList->userid, $pushTest);
            }
            return;
        }

        // when this list is referred by other users
        $pushTest .= $this->formatListItems($shoppingList);
        $shareInfos = $shoppingList->refShareInfos;
        foreach($shareInfos as $shareInfo) {
            $refByShoppingList =  $shareInfo->shoppingList;
            if($refByShoppingList->is_active) {
                $this->sendPushMessage($refByShoppingList->userid, $pushTest);
            }
        }
    }

    private function sendReplyMessage($text)
    {
        ReplyTextMessageCreated::dispatch($this->channelToken, $this->replyToken, $text);
    }

    private function sendPushMessage($userid, $text)
    {
        PushTextMessageCreated::dispatch($this->channelToken, $userid, $text);
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