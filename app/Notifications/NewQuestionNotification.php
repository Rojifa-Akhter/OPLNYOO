<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewQuestionNotification extends Notification
{
    use Queueable;

    public $question;
    public $ownerName;

    public function __construct($question, $ownerName)
    {
        $this->question = $question;
        $this->ownerName = $ownerName;  // Store the owner's name
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'question_id' => $this->question->id,
            'question' => $this->question->question,
            'owner_id' => $this->question->owner_id,
            'owner_name' => $this->ownerName,  // Include the owner's name in the notification data
            'message' => "A new question has been created by {$this->ownerName}.",  // Message includes owner's name
        ];
    }
}
