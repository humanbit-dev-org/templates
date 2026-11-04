<?php

namespace App\Support\Backpack;

use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Whether a model method is a relation, decided without calling it.
 *
 * The Backpack helpers used to invoke every public parameterless method to see
 * what came back: on a model whose public methods run business logic (and can
 * even write records) that meant executing it against an empty model. A relation
 * either declares a Relation return type or builds one with $this->hasMany()
 * and friends, directly or by narrowing another relation of the same model.
 */
final class ModelRelations
{
	private const BUILDERS = "hasOne|hasMany|belongsTo|belongsToMany|hasOneThrough|hasManyThrough|morphTo|morphOne|morphMany|morphToMany|morphedByMany";

	/** @var array<string, bool> */
	private static array $cache = [];

	public static function isRelationMethod(ReflectionMethod $method): bool
	{
		$key = $method->class . "::" . $method->getName();

		return self::$cache[$key] ??= self::inspect($method);
	}

	private static function inspect(ReflectionMethod $method): bool
	{
		if ($method->getNumberOfParameters() > 0 || $method->isStatic()) {
			return false;
		}

		$type = $method->getReturnType();
		if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
			return is_a($type->getName(), Relation::class, true);
		}

		$file = $method->getFileName();
		if ($file === false) {
			return false;
		}

		$body = implode(
			"",
			array_slice(file($file), $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1)
		);

		if (preg_match('/\$this->(' . self::BUILDERS . ")\s*\(/", $body) === 1) {
			return true;
		}

<<<<<<< HEAD
		// return $this->tiraggi()->where(...): a narrowed relation of the same model.
		return preg_match('/return\s+\$this->(\w+)\(\)/', $body, $m) === 1 &&
			$m[1] !== $method->getName() &&
			$method->getDeclaringClass()->hasMethod($m[1]) &&
			self::isRelationMethod($method->getDeclaringClass()->getMethod($m[1]));
	}
=======
        // return $this->media()->where(...): a narrowed relation of the same model.
        return preg_match('/return\s+\$this->(\w+)\(\)/', $body, $m) === 1
            && $m[1] !== $method->getName()
            && $method->getDeclaringClass()->hasMethod($m[1])
            && self::isRelationMethod($method->getDeclaringClass()->getMethod($m[1]));
    }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb
}
