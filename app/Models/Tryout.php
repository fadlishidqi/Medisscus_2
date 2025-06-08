<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tryout extends BaseModel
{
    public function questionBank()
    {
        return $this->belongsTo(QuestionBank::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}
