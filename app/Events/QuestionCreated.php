<?php

namespace App\Events;

use App\Models\Question;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuestionCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $question;

    public function __construct(Question $question)
    {
        $this->question = $question;
    }

    public function broadcastOn()
    {
        return new Channel('admin-channel'); // You can create an admin-specific channel
    }

    public function broadcastAs()
    {
        return 'question.created';
    }
}
