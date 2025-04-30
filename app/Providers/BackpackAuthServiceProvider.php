<?php

namespace App\Providers;

use App\Models\ModelPermission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class BackpackAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Log::info('BackpackAuthServiceProvider boot chiamato');
        
        // Registriamo un gate generale per verificare se un utente
        // ha accesso a un determinato modello e azione
        Gate::define('backpack-access-model', function (User $user, string $modelName, string $action) {
            // Se l'utente non ha un ruolo backpack, non ha accesso
            if (!$user->backpack_role_id) {
                return false;
            }

            // Cerca permessi per questo ruolo e modello
            $permissions = ModelPermission::where('backpack_role_id', $user->backpack_role_id)
                ->get();

            foreach ($permissions as $permission) {
                // Verifica se il modello è incluso nei permessi
                if (is_array($permission->model_name) && in_array($modelName, $permission->model_name)) {
                    // Controlla il permesso specifico
                    $result = false;
                    switch ($action) {
                        case 'read':
                            $result = (bool) $permission->can_read;
                            break;
                        case 'create':
                            $result = (bool) $permission->can_create;
                            break;
                        case 'update':
                            $result = (bool) $permission->can_update;
                            break;
                        case 'delete':
                            $result = (bool) $permission->can_delete;
                            break;
                    }
                    
                    return $result;
                }
            }

            return false;
        });
    }
} 