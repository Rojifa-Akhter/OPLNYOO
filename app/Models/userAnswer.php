<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class userAnswer extends Model
{
    protected $guarded = ['id'];

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
