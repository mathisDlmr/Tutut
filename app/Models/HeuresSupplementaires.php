<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HeuresSupplementaires extends Model
{
    use HasFactory;

    protected $table = 'heures_supplementaires';

    protected $fillable = ['user_id', 'semaine_id', 'nb_heures', 'commentaire'];   

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function semaine()
    {
        return $this->belongsTo(Semaine::class, 'semaine_id', 'numero'); 
    }
}
