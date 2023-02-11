<?php

namespace Tests\Feature;

use App\Models\InboundMessageLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ApiWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_returns_404()
    {
        $response = $this->get('/');
        $response->assertStatus(404);
    }

    public function test_inbound_message_is_logged()
    {
        $logCount = InboundMessageLog::count();

        $inMessage = ['id' => 123];
        $response = $this->postJson('/api/shoppinglist', $inMessage);
        $response->assertStatus(200);
        $response->assertExactJson([]);

        $this->assertDatabaseCount('inbound_message_logs', $logCount + 1);
    }
}
