<?php

namespace App\Notifications;

use App\Models\userAnswer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AnswerSubmittedNotification extends Notification
{
    use Queueable;

    public $user;
    public $userAnswers;

    public function __construct($user, $userAnswers)
    {
        $this->user = $user;
        $this->userAnswers = $userAnswers;
    }

    public function via($notifiable)
    {
        return ['database']; // You can also add 'database' for saving the notification in the database
    }

    public function toArray(object $notifiable): array
    {
        return [
            'user_name' => $this->user->name,
            'user_email' => $this->user->email,
            'message' => 'A user has submitted answers to your questions.',

        ];
    }
}
