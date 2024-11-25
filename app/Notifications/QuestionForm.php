<?php

namespace App\Notifications;

use App\Models\Question;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

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
        return [
            'question_id' => $this->question->id,
            'question' => $this->question->question,
            'owner_id' => $this->question->owner_id,
            'owner_name' => $this->question->user->name,
            'message' => "A new question has been created by {$this->question->user->name}.",

        ];
    }
}
