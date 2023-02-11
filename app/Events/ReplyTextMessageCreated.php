<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReplyTextMessageCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $channelToken;
    public $replyToken;
    public $text;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($channelToken, $replyToken, $text)
    {
        $this->channelToken = $channelToken;
        $this->replyToken = $replyToken;
        $this->text = $text;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
