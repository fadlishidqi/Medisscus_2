<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends BaseModel
{
    public function questionBank()
    {
        return $this->belongsTo(QuestionBank::class);
    }

    public function answers()
    {
        return $this->hasMany(Answer::class);
    }
}
