<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Filament\Models\Contracts\HasName;

class UV extends Authenticatable implements HasName
{
    use HasFactory;

    protected $table = 'uvs';

    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['code', 'intitule'];

    public function tutors()
    {
        return $this->belongsToMany(User::class, 'tutor_propose', 'fk_code', 'fk_user');
    }    

    public function getFilamentName(): string
    {
        return $this->intitule;
    }

    public function getLabelAttribute(): string
    {
        return "{$this->code} - {$this->intitule}";
    }    
}
