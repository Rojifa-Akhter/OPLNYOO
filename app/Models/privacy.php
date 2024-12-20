<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class privacy extends Model
{
    protected $guarded = ['id'];
    // In Privacy model
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

}
