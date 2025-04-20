<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use App\Enums\Roles;

class User extends Authenticatable implements HasName
{
    use HasFactory;

    protected $fillable = ['email', 'firstName', 'lastName', 'role', 'locale'];

    public function getFilamentName(): string
    {
        return ($this->firstName." ".$this->lastName);
    }

    public function proposedUvs()
    {
        return $this->belongsToMany(UV::class, 'tutor_propose', 'fk_user', 'fk_code');
    }     
}
