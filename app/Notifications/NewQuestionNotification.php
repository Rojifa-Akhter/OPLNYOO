<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewQuestionNotification extends Notification
{
    use Queueable;

    public $question;
    public function __construct($question)
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

    /**
     * Get the mail representation of the notification.
     */
    // public function toMail(object $notifiable): MailMessage
    // {
    //     return (new MailMessage)
    //                 ->line('The introduction to the notification.')
    //                 ->action('Notification Action', url('/'))
    //                 ->line('Thank you for using our application!');
    // }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $ownerName = $this->question->user ? $this->question->user->name : 'Unknown';

        return [
            'question_id' => $this->question->id,
            'question' => $this->question->question,
            'owner_id' => $this->question->owner_id,
            'owner_name' => $ownerName,
            'message' => "A new question has been created by {$ownerName}.",
        ];
    }

}
