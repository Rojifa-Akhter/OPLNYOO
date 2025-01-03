<?php

namespace App\Notifications;

use App\Models\Question;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class QuestionForm extends Notification
{
    use Queueable;

    public $question;
    public function __construct(Question $question)
    {
        $this->question = $question;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        // Check if the user exists before accessing its name
        $ownerName = $this->question->user ? $this->question->user->name : 'Unknown';

        return [
            'question_id' => $this->question->id,
            'question' => $this->question->question,
            'owner_id' => $this->question->owner_id,
            'owner_name' => $ownerName, // Handle null case here
            'message' => "A new question has been created by {$ownerName}.",
        ];
    }

}
