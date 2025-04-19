<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inscription extends Model
{
    use HasFactory;

    protected $table = 'inscription';

    protected $fillable = ['tutee_id','creneau_id','enseignements_souhaites'];

    public function tutee()
    {
        return $this->belongsTo(User::class, 'tutee_id');
    }

    public function creneau()
    {
        return $this->belongsTo(Creneaux::class, 'creneau_id');
    }
}
