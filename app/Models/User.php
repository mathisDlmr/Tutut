<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\Roles;

class User extends Model
{
    use HasFactory;

    protected $fillable = ['email', 'firstName', 'lastName', 'role'];

    public function isAdmin(): bool
    {
        return AdminList::hasEmail($this->email);
    }

    public function isEmployedTutor(): bool
    {
        return EmployedTutorList::hasEmail($this->email);
    }

    public function updateRole(): void
    {
        if ($this->isAdmin()) {
            $this->role = Roles::Administrator;
        } elseif ($this->isEmployedTutor()) {
            $this->role = Roles::EmployedTutor;
        } elseif ((!$this->isEmployedTutor()) && ($this->role === Roles::EmployedTutor)){
            $this->role = Roles::Tutor;  // Si le rôle est EmployedTutor mais que l'utilisateur ne l'est plus, il devient Tutor
        } elseif ($this->role !== Roles::Tutor) {
            $this->role = Roles::Tutee;  // Par défaut un user est Tutee
        }
        $this->save();
    }
}
