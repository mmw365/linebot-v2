<?php

namespace Tests\Feature;

use App\Listeners\ReplyTextMessageSender;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Services\MessageApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Mockery\MockInterface;
use Tests\TestCase;

class ShoppingListApiTest extends TestCase
{
    use RefreshDatabase;

    private function createTextMessageByDummyUser($text) {
        return [
            'destination' => '',
            'events' => [[
                'type' => 'message',
                'message' => ['type' => 'text', 'id' => '1', 'text' => $text],
                'webhookEventId' => '',
                'deliveryContext' => ['isRedelivery' => 'false'],
                'timestamp' => 0,
                'source' => ['type' => 'user', 'userId' => 'dummy-user-id'],
                'replyToken' => 'dummy-reply-token',
                'mode' => 'active'
            ]]
        ];
    }

    public function test_help_message_is_displayed()
    {
        $channelToken = config('app.channel_token_shoppinglist');
        $helpText = "メッセージの送信でリストを作成します。コマンド以外は全てリストに追加されます。\n"
            . "コマンド一覧：\n"
            . "「リスト(list)」リストを表示します。\n"
            . "「リスト番号」リストから指定番号のアイテムを削除します。\n"
            . "（※コンマ／スペース区切りで複数指定できます。"
            . "※削除されると番号がふりなおされます。）\n"
            . "「クリア(clear)」リストを全削除します。\n"
            . "「リスト１〜５」リストを切替えます。\n"
            . "（※「リスト１　＜リスト名＞」でリスト名の設定ができます。）";

        $mock = $this->mock(MessageApiClient::class);
        $mock->shouldReceive('sendReplyTextMessage')->times(3)->with($channelToken, 'dummy-reply-token', $helpText);

        $inMessage = $this->createTextMessageByDummyUser('ヘルプ');
        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);

        $inMessage = $this->createTextMessageByDummyUser('HELP');
        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);

        $inMessage = $this->createTextMessageByDummyUser('?');
        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);
    }

    public function test_new_item_added_to_empty_shopping_list()
    {
        $channelToken = config('app.channel_token_shoppinglist');

        $mock = $this->mock(MessageApiClient::class);
        $mock->shouldReceive('sendReplyTextMessage')->once()->with($channelToken, 'dummy-reply-token', '「TEST」を追加しました。');

        $inMessage = $this->createTextMessageByDummyUser('TEST');

        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);

        //ShoppingList::count()
        $this->assertDatabaseCount('shopping_lists', 1);
        $this->assertDatabaseCount('shopping_list_items', 1);
    }

    public function test_new_item_added_to_existing_shopping_list()
    {
        $channelToken = config('app.channel_token_shoppinglist');

        $shoppingList = ShoppingList::create([
            'userid' => 'dummy-user-id',
            'number' => 1,
            'name' => '',
            'is_active' => true,
        ]);

        ShoppingListItem::create([
            'shopping_list_id' => $shoppingList->id,
            'number' => 1,
            'name' => 'TEST',
        ]);

        $mock = $this->mock(MessageApiClient::class);
        $mock->shouldReceive('sendReplyTextMessage')->once()->with($channelToken, 'dummy-reply-token', '「TEST」を追加しました。');

        $inMessage = $this->createTextMessageByDummyUser('TEST');

        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);

        //ShoppingList::count()
        $this->assertDatabaseCount('shopping_lists', 1);
        $this->assertDatabaseCount('shopping_list_items', 2);
    }

    public function test_show_list_to_empty_shopping_list()
    {
        $channelToken = config('app.channel_token_shoppinglist');

        $mock = $this->mock(MessageApiClient::class);
        $mock->shouldReceive('sendReplyTextMessage')->once()->with($channelToken, 'dummy-reply-token', 'リストは空です');

        $inMessage = $this->createTextMessageByDummyUser('list');

        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);
    }

    public function test_show_list_to_existing_shopping_list()
    {
        $channelToken = config('app.channel_token_shoppinglist');

        $mock = $this->mock(MessageApiClient::class);
        $mock->shouldReceive('sendReplyTextMessage')->once()->with($channelToken, 'dummy-reply-token', "#1 TEST1\n#2 TEST2");

        $shoppingList = ShoppingList::create([
            'userid' => 'dummy-user-id',
            'number' => 1,
            'name' => '',
            'is_active' => true,
        ]);

        ShoppingListItem::create([
            'shopping_list_id' => $shoppingList->id,
            'number' => 1,
            'name' => 'TEST1',
        ]);

        ShoppingListItem::create([
            'shopping_list_id' => $shoppingList->id,
            'number' => 2,
            'name' => 'TEST2',
        ]);

        $inMessage = $this->createTextMessageByDummyUser('list');

        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);
    }

    public function test_select_existing_shopping_list_without_name()
    {
        $channelToken = config('app.channel_token_shoppinglist');

        ShoppingList::create([
            'userid' => 'dummy-user-id',
            'number' => 1,
            'name' => '',
            'is_active' => true,
        ]);

        ShoppingList::create([
            'userid' => 'dummy-user-id',
            'number' => 2,
            'name' => '',
            'is_active' => false,
        ]);

        $mock = $this->mock(MessageApiClient::class);
        $mock->shouldReceive('sendReplyTextMessage')->once()->with($channelToken, 'dummy-reply-token','「リスト1」に切替えました');
        $mock->shouldReceive('sendReplyTextMessage')->once()->with($channelToken, 'dummy-reply-token','「リスト2」に切替えました');

        $inMessage = $this->createTextMessageByDummyUser('list1');

        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);

        $inMessage = $this->createTextMessageByDummyUser('list 2');

        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);
    }

    public function test_select_existing_shopping_list_with_name()
    {
        $channelToken = config('app.channel_token_shoppinglist');

        ShoppingList::create([
            'userid' => 'dummy-user-id',
            'number' => 1,
            'name' => 'TEST1',
            'is_active' => true,
        ]);

        ShoppingList::create([
            'userid' => 'dummy-user-id',
            'number' => 2,
            'name' => 'TEST2',
            'is_active' => false,
        ]);

        $mock = $this->mock(MessageApiClient::class);
        $mock->shouldReceive('sendReplyTextMessage')->once()->with($channelToken, 'dummy-reply-token','「リスト1（TEST1）」に切替えました');
        $mock->shouldReceive('sendReplyTextMessage')->once()->with($channelToken, 'dummy-reply-token','「リスト2（TEST2）」に切替えました');

        $inMessage = $this->createTextMessageByDummyUser('list1');
        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);

        $inMessage = $this->createTextMessageByDummyUser('list 2');
        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);
    }

    public function test_select_existing_shopping_list_and_update_name()
    {
        $channelToken = config('app.channel_token_shoppinglist');

        ShoppingList::create([
            'userid' => 'dummy-user-id',
            'number' => 1,
            'name' => '',
            'is_active' => true,
        ]);

        ShoppingList::create([
            'userid' => 'dummy-user-id',
            'number' => 2,
            'name' => '',
            'is_active' => false,
        ]);

        $mock = $this->mock(MessageApiClient::class);
        $mock->shouldReceive('sendReplyTextMessage')->once()->with($channelToken, 'dummy-reply-token','「リスト1（TEST1）」に切替えました');
        $mock->shouldReceive('sendReplyTextMessage')->once()->with($channelToken, 'dummy-reply-token','「リスト2（TEST2）」に切替えました');

        $inMessage = $this->createTextMessageByDummyUser('list1 TEST1');
        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);

        $inMessage = $this->createTextMessageByDummyUser('list 2 TEST2');
        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);
    }

    public function test_select_non_existing_shopping_list()
    {
        $channelToken = config('app.channel_token_shoppinglist');

        $mock = $this->mock(MessageApiClient::class);
        $mock->shouldReceive('sendReplyTextMessage')->once()->with($channelToken, 'dummy-reply-token','「リスト1」に切替えました');
        $mock->shouldReceive('sendReplyTextMessage')->once()->with($channelToken, 'dummy-reply-token','「リスト2」に切替えました');

        $this->assertDatabaseCount('shopping_lists', 0);

        $inMessage = $this->createTextMessageByDummyUser('list1');
        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);
        $this->assertDatabaseCount('shopping_lists', 1);

        $inMessage = $this->createTextMessageByDummyUser('list 2');
        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);
        $this->assertDatabaseCount('shopping_lists', 2);
    }

    public function test_select_non_existing_shopping_list_and_update_name()
    {
        $channelToken = config('app.channel_token_shoppinglist');

        $mock = $this->mock(MessageApiClient::class);
        $mock->shouldReceive('sendReplyTextMessage')->once()->with($channelToken, 'dummy-reply-token','「リスト1（TEST1）」に切替えました');
        $mock->shouldReceive('sendReplyTextMessage')->once()->with($channelToken, 'dummy-reply-token','「リスト2（TEST2）」に切替えました');

        $this->assertDatabaseCount('shopping_lists', 0);

        $inMessage = $this->createTextMessageByDummyUser('list1 TEST1');
        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);
        $this->assertDatabaseCount('shopping_lists', 1);

        $inMessage = $this->createTextMessageByDummyUser('list 2 TEST2');
        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);
        $this->assertDatabaseCount('shopping_lists', 2);
    }

    public function test_clear_list_to_non_existing_shopping_list()
    {
        $channelToken = config('app.channel_token_shoppinglist');

        $mock = $this->mock(MessageApiClient::class);
        $mock->shouldReceive('sendReplyTextMessage')->once()->with($channelToken, 'dummy-reply-token','リストを空にしました');
        $this->assertDatabaseCount('shopping_lists', 0);

        $inMessage = $this->createTextMessageByDummyUser('clear');
        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);
        $this->assertDatabaseCount('shopping_lists', 0);
    }


    public function test_clear_list_to_existing_shopping_list()
    {
        $channelToken = config('app.channel_token_shoppinglist');

        $shoppingList = ShoppingList::create([
            'userid' => 'dummy-user-id',
            'number' => 1,
            'name' => '',
            'is_active' => true,
        ]);

        ShoppingListItem::create([
            'shopping_list_id' => $shoppingList->id,
            'number' => 1,
            'name' => 'TEST1',
        ]);

        $mock = $this->mock(MessageApiClient::class);
        $mock->shouldReceive('sendReplyTextMessage')->once()->with($channelToken, 'dummy-reply-token','リストを空にしました');

        $this->assertDatabaseCount('shopping_list_items', 1);

        $inMessage = $this->createTextMessageByDummyUser('clear');
        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);
        $this->assertDatabaseCount('shopping_list_items', 0);
    }

    public function test_delete_item_from_existing_shopping_list()
    {
        $channelToken = config('app.channel_token_shoppinglist');

        $shoppingList = ShoppingList::create([
            'userid' => 'dummy-user-id',
            'number' => 1,
            'name' => '',
            'is_active' => true,
        ]);

        ShoppingListItem::create([
            'shopping_list_id' => $shoppingList->id,
            'number' => 1,
            'name' => 'TEST1',
        ]);

        ShoppingListItem::create([
            'shopping_list_id' => $shoppingList->id,
            'number' => 2,
            'name' => 'TEST2',
        ]);

        ShoppingListItem::create([
            'shopping_list_id' => $shoppingList->id,
            'number' => 3,
            'name' => 'TEST3',
        ]);

        $mock = $this->mock(MessageApiClient::class);
        $mock->shouldReceive('sendReplyTextMessage')->once()->with($channelToken, 'dummy-reply-token', "#1 TEST1\n#2 TEST2\n#3 TEST3");
        $mock->shouldReceive('sendReplyTextMessage')->once()->with($channelToken, 'dummy-reply-token', '「TEST2」を削除しました');
        $mock->shouldReceive('sendReplyTextMessage')->twice()->with($channelToken, 'dummy-reply-token', "#1 TEST1\n#2 TEST3");
        $mock->shouldReceive('sendReplyTextMessage')->once()->with($channelToken, 'dummy-reply-token', '#3 はありません');
        $mock->shouldReceive('sendReplyTextMessage')->once()->with($channelToken, 'dummy-reply-token', "「TEST1」を削除しました\n「TEST3」を削除しました");
        $mock->shouldReceive('sendReplyTextMessage')->once()->with($channelToken, 'dummy-reply-token', 'リストは空です');

        $this->assertDatabaseCount('shopping_list_items', 3);

        $inMessage = $this->createTextMessageByDummyUser('list');
        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);

        $inMessage = $this->createTextMessageByDummyUser('2');
        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);

        $this->assertDatabaseCount('shopping_list_items', 2);

        $inMessage = $this->createTextMessageByDummyUser('3');
        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);

        $this->assertDatabaseCount('shopping_list_items', 2);

        $inMessage = $this->createTextMessageByDummyUser('1 2');
        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);

        $this->assertDatabaseCount('shopping_list_items', 0);
    }


}
