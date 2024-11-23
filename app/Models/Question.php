<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $guarded = ['id'];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
    public function answers()
    {
        return $this->hasMany(Answer::class);
    }

    public function submittedAnswers()
    {
        return $this->hasMany(userAnswer::class);
    }

}
