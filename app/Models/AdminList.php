<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminList extends Model
{
    use HasFactory;

    protected $table = 'admin_list';

    protected $fillable = ['email'];

    public static function hasEmail(string $email): bool
    {
        return self::where('email', $email)->exists();
    }
}
