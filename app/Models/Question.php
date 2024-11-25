<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'options' => 'array',
    ];
    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    // Define the answers relationship
    public function answers()
    {
        return $this->hasMany(userAnswer::class, 'question_id');
    }

    // Define the submittedAnswers relationship (if it's the same as answers)
    public function submittedAnswers()
    {
        return $this->hasMany(userAnswer::class, 'question_id');
    }
}



