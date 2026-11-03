<?php

namespace App\Models;

use App\Models\Traits\HasDisplayAttributes;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Closure;
use Illuminate\Database\Eloquent\Model;
use WeakMap;

class ModelPermission extends Model
{
    use CrudTrait, HasDisplayAttributes;

    protected $fillable = [
        'backpack_role_id',
        'role_id',
        'model_name',
        'can_read',
        'can_create',
        'can_update',
        'can_delete',
    ];

    protected $casts = [
        'model_name' => 'array',
    ];

    /**
     * The gate both permission providers define, each on its own role column:
     * the first permission row naming the model decides the action.
     */
    public static function gateFor(string $roleColumn): Closure
    {
        // Every permission middleware on a route asks again: one query per user
        // and role for the request, not one per check.
        $memo = new WeakMap;

        return function (User $user, string $modelName, string $action) use ($roleColumn, $memo): bool {
            $roleId = $user->{$roleColumn};

            if (! $roleId) {
                return false;
            }

            if (($memo[$user][0] ?? null) !== $roleId) {
                $memo[$user] = [$roleId, self::where($roleColumn, $roleId)->get()];
            }

            foreach ($memo[$user][1] as $permission) {
                $models = $permission->model_name;

                // Both ["Model1", "Model2"] and {"Model1": "Model1"} name a model.
                if (is_array($models) && (in_array($modelName, $models) || array_key_exists($modelName, $models))) {
                    return match ($action) {
                        'read' => (bool) $permission->can_read,
                        'create' => (bool) $permission->can_create,
                        'update' => (bool) $permission->can_update,
                        'delete' => (bool) $permission->can_delete,
                        default => false,
                    };
                }
            }

            return false;
        };
    }

    public function backpackRole()
    {
        return $this->belongsTo(BackpackRole::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    // see HasDisplayAttributes for full doc
    public function getDisplayAttribute(): string
    {
        if (is_array($this->model_name)) {
            return implode(',', $this->model_name);
        }

        return $this->model_name;
    }
}
