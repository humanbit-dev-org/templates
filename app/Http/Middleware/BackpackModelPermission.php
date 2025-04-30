<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class BackpackModelPermission
{
    public function handle(Request $request, Closure $next, string $modelName, string $action): Response
    {
        $user = Auth::guard(backpack_guard_name())->user();
        $allowed = Gate::forUser($user)->allows('backpack-access-model', [$modelName, $action]);
    
        if (!$allowed) {
            abort(403, 'Non sei autorizzato ad accedere a questa risorsa.');
        }

        return $next($request);
    }
} 