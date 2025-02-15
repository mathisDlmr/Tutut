<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Creneaux extends Model
{
    use HasFactory;

    protected $table = 'creneaux';

    protected $fillable = ['tutor1_id','tutor2_id','start','end'];
}
