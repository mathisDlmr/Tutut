<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BecomeTutor extends Model
{
    use HasFactory;
    
    /**
     * Nom de la table associée au modèle.
     *
     * @var string
     */
    protected $table = 'become_tutor';
    
    /**
     * Les attributs qui sont mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'fk_user',
        'semester',
        'UVs',
        'motivation',
        'status'
    ];
    
    /**
     * Les attributs qui doivent être castés.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'UVs' => 'array',
    ];
    
    /**
     * La relation avec l'utilisateur qui a fait la demande
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fk_user');
    }
}