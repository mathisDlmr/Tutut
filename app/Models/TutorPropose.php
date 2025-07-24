<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TutorPropose extends Model
{
    use HasFactory;

    protected $table = 'tutor_propose';

    protected $fillable = ['fk_user','fk_code'];

    public function user()
    {
        return $this->belongsTo(User::class, 'fk_user');
    }

    public function uv()
    {
        return $this->belongsTo(UV::class, 'fk_code', 'code');
    }
}
