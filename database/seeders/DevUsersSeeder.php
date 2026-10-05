<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DevUsersSeeder extends Seeder
{
    /**
     * Exécute les seeds de développement (comptes local uniquement).
     */
    public function run(): void
    {
        if (!app()->environment('local')) {
            $this->command?->warn('DevUsersSeeder ignoré : non en environnement local.');
            return;
        }

        $adminPassword = env('SEED_ADMIN_PASSWORD');
        $userPassword  = env('SEED_USER_PASSWORD');

        if (empty($adminPassword) || empty($userPassword)) {
            throw new RuntimeException(
                'DevUsersSeeder impossible : SEED_ADMIN_PASSWORD et SEED_USER_PASSWORD doivent être renseignés dans .env'
            );
        }

        // Administrateur technique : amine.m
        User::updateOrCreate(
            ['identifiant' => 'amine.m'],
            [
                'prenom'   => 'Amine',
                'nom'      => 'M.',
                'role'     => 'admin',
                'password' => $adminPassword,
                'actif'    => true,
            ]
        );

        // Utilisateur métier : sonia.d
        User::updateOrCreate(
            ['identifiant' => 'sonia.d'],
            [
                'prenom'   => 'Sonia',
                'nom'      => 'D.',
                'role'     => 'user',
                'password' => $userPassword,
                'actif'    => true,
            ]
        );

        $this->command?->info('Comptes de développement (amine.m, sonia.d) créés avec succès.');
    }
}
