<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Salle extends Model
{
    use HasFactory;

    protected $table = 'salles';

    protected $primaryKey = 'numero';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['numero'];

    public function disponibilites()
    {
        return $this->hasMany(DispoSalle::class, 'fk_salle', 'numero');
    }

    protected static function booted()
    {
        static::deleting(function ($salle) {
            $salle->disponibilites()->delete();
        });
    }
}
