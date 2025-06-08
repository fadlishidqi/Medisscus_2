<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionBank extends BaseModel
{
    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function tryouts()
    {
        return $this->hasMany(Tryout::class);
    }
}
