<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreerUtilisateur extends Command
{
    /**
     * Le nom et la signature de la commande Artisan.
     *
     * @var string
     */
    protected $signature = 'app:creer-utilisateur
                            {--identifiant= : L\'identifiant de connexion}
                            {--prenom= : Le prénom de l\'utilisateur}
                            {--nom= : Le nom de famille}
                            {--role= : Le rôle (admin ou user)}
                            {--password= : Le mot de passe}';

    /**
     * La description de la commande.
     *
     * @var string
     */
    protected $description = 'Crée un nouvel utilisateur applicatif dans la base SQLite.';

    /**
     * Exécute la commande.
     */
    public function handle(): int
    {
        $this->info('--- Création d\'un utilisateur Extraction BD ---');

        $identifiant = $this->option('identifiant') ?: $this->ask('Identifiant (ex: prenom.nom)');
        $prenom      = $this->option('prenom')      ?: $this->ask('Prénom');
        $nom         = $this->option('nom')         ?: $this->ask('Nom de famille');
        $role        = $this->option('role')        ?: $this->choice('Rôle', ['admin' => 'Administrateur', 'user' => 'Utilisateur métier'], 'user');
        $password    = $this->option('password')    ?: $this->secret('Mot de passe');

        // Normaliser le rôle si l'option est le libellé
        if ($role === 'Administrateur') $role = 'admin';
        if ($role === 'Utilisateur métier') $role = 'user';

        $validator = Validator::make([
            'identifiant' => $identifiant,
            'prenom'      => $prenom,
            'nom'         => $nom,
            'role'        => $role,
            'password'    => $password,
        ], [
            'identifiant' => ['required', 'string', 'max:50', 'unique:users,identifiant'],
            'prenom'      => ['required', 'string', 'max:50'],
            'nom'         => ['required', 'string', 'max:50'],
            'role'        => ['required', 'in:admin,user'],
            'password'    => ['required', 'string', 'min:6'],
        ]);

        if ($validator->fails()) {
            $this->error('Erreur de validation :');
            foreach ($validator->errors()->all() as $error) {
                $this->error(' - ' . $error);
            }
            return self::FAILURE;
        }

        $user = User::create([
            'identifiant' => strtolower(trim($identifiant)),
            'prenom'      => trim($prenom),
            'nom'         => trim($nom),
            'role'        => $role,
            'password'    => $password,
            'actif'       => true,
        ]);

        $this->info(sprintf(
            'Utilisateur [%s] (%s, rôle: %s) créé avec succès !',
            $user->identifiant,
            $user->nom_affiche,
            $user->role_label
        ));

        return self::SUCCESS;
    }
}
