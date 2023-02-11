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

    public function test_new_item_added_to_empty_shopping_list()
    {
        $channelToken = config('app.channel_token_shoppinglist');

        // $this->mock(ReplyTextMessageSender::class, function(MockInterface $mock) {
        //     $mock->shouldReceive('handle')->once();
        // });

        $mock = $this->mock(MessageApiClient::class);
        $mock->shouldReceive('sendReplyTextMessage')->once()->with($channelToken, 'dummy-reply-token', '「TEST」を追加しました。');

        $inMessage = [
            'destination' => '',
            'events' => [[
                'type' => 'message',
                'message' => ['type' => 'text', 'id' => '1', 'text' => 'TEST'],
                'webhookEventId' => '',
                'deliveryContext' => ['isRedelivery' => 'false'],
                'timestamp' => 0,
                'source' => ['type' => 'user', 'userId' => 'dummy-user-id'],
                'replyToken' => 'dummy-reply-token',
                'mode' => 'active'
            ]]
        ];

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

        $mock = $this->mock(MessageApiClient::class);
        $mock->shouldReceive('sendReplyTextMessage')->once()->with($channelToken, 'dummy-reply-token', '「TEST」を追加しました。');

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

        $inMessage = [
            'destination' => '',
            'events' => [[
                'type' => 'message',
                'message' => ['type' => 'text', 'id' => '1', 'text' => 'TEST'],
                'webhookEventId' => '',
                'deliveryContext' => ['isRedelivery' => 'false'],
                'timestamp' => 0,
                'source' => ['type' => 'user', 'userId' => 'dummy-user-id'],
                'replyToken' => 'dummy-reply-token',
                'mode' => 'active'
            ]]
        ];

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

        $inMessage = [
            'destination' => '',
            'events' => [[
                'type' => 'message',
                'message' => ['type' => 'text', 'id' => '1', 'text' => 'list'],
                'webhookEventId' => '',
                'deliveryContext' => ['isRedelivery' => 'false'],
                'timestamp' => 0,
                'source' => ['type' => 'user', 'userId' => 'dummy-user-id'],
                'replyToken' => 'dummy-reply-token',
                'mode' => 'active'
            ]]
        ];

        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);
    }

    public function test_show_list_to_existing_shopping_list()
    {
        $channelToken = config('app.channel_token_shoppinglist');

        $mock = $this->mock(MessageApiClient::class);
        $mock->shouldReceive('sendReplyTextMessage')->once()->with($channelToken, 'dummy-reply-token', "#1 TEST1\n#1 TEST2");

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
            'number' => 1,
            'name' => 'TEST2',
        ]);

        $inMessage = [
            'destination' => '',
            'events' => [[
                'type' => 'message',
                'message' => ['type' => 'text', 'id' => '1', 'text' => 'list'],
                'webhookEventId' => '',
                'deliveryContext' => ['isRedelivery' => 'false'],
                'timestamp' => 0,
                'source' => ['type' => 'user', 'userId' => 'dummy-user-id'],
                'replyToken' => 'dummy-reply-token',
                'mode' => 'active'
            ]]
        ];

        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);
    }
}
