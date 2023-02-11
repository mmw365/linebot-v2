<?php

namespace App\Listeners;

use App\Events\ReplyTextMessageCreated;
use App\Services\MessageApiClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ReplyTextMessageSender
{
    private $messageApiClient;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(MessageApiClient $messageApiClient)
    {
        $this->messageApiClient = $messageApiClient;
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\ReplyTextMessageCreated  $event
     * @return void
     */
    public function handle(ReplyTextMessageCreated $event)
    {
        $this->messageApiClient->sendReplyTextMessage($event->channelToken, $event->replyToken, $event->text);
    }
}
