<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Answer extends BaseModel
{
    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
