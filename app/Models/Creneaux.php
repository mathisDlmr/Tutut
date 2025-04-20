<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Creneaux extends Model
{
    use HasFactory;

    protected $table = 'creneaux';

    protected $fillable = ['tutor1_id','tutor2_id','fk_semaine','fk_salle','open','start','end'];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
    ];    

    public function tutor1()
    {
        return $this->belongsTo(User::class, 'tutor1_id');
    }

    public function tutor2()
    {
        return $this->belongsTo(User::class, 'tutor2_id');
    }

    public function semaine()
    {
        return $this->belongsTo(Semaine::class, 'fk_semaine');
    }   
    
    public function inscriptions()
    {
        return $this->hasMany(Inscription::class, 'creneau_id');
    }    

    public function inscriptionsCount()
    {
        return $this->inscriptions()->count();
    }    
}
