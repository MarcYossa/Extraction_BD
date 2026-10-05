<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Les attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'identifiant',
        'prenom',
        'nom',
        'role',
        'agence_id',
        'password',
        'actif',
    ];

    /**
     * Les attributs masqués lors de la sérialisation.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Les trancatages de types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'actif'    => 'boolean',
        ];
    }

    /**
     * Nom du champ utilisé comme identifiant unique d'authentification.
     */
    public function getAuthIdentifierName(): string
    {
        return 'identifiant';
    }

    /**
     * Vérifie si l'utilisateur est un administrateur technique.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Nom affiché sous la forme "Prénom N." (ex: Amine M.).
     */
    protected function nomAffiche(): Attribute
    {
        return Attribute::make(
            get: fn () => sprintf(
                '%s %s.',
                $this->prenom,
                mb_strtoupper(mb_substr($this->nom ?? '', 0, 1))
            )
        );
    }

    /**
     * Initiales de l'utilisateur pour l'avatar (ex: AM).
     */
    protected function initiales(): Attribute
    {
        return Attribute::make(
            get: fn () => mb_strtoupper(
                mb_substr($this->prenom ?? '', 0, 1) . mb_substr($this->nom ?? '', 0, 1)
            )
        );
    }

    /**
     * Libellé lisible du rôle (ex: Administrateur / Utilisateur métier).
     */
    protected function roleLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->isAdmin() ? 'Administrateur' : 'Utilisateur métier'
        );
    }
}
