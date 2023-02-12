<?php

namespace App\Listeners;

use App\Events\PushTextMessageCreated;
use App\Services\MessageApiClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class PushTextMessageSender
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
     * @param  \App\Events\PushTextMessageCreated  $event
     * @return void
     */
    public function handle(PushTextMessageCreated $event)
    {
        $this->messageApiClient->sendPushTextMessage($event->channelToken, $event->userId, $event->text);
    }
}
