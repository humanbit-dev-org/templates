<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * Every registered route must point at a controller class and method that
 * exist, and the route list must build: a single route to a missing
 * controller is enough to break `php artisan route:list` for everybody.
 */
class RoutesIntegrityTest extends TestCase
{
    // route:list instantiates the controllers, and Backpack's auth controllers
    // inspect the users table in their constructor: the real schema is used,
    // inside a rolled-back transaction.
    use DatabaseTransactions;

    public function test_every_route_points_at_an_existing_controller_action(): void
    {
        $broken = [];

        /** @var Route $route */
        foreach (RouteFacade::getRoutes() as $route) {
            $uses = $route->getAction('uses');

            if (! is_string($uses)) {
                continue; // closures and invokable objects
            }

            [$class, $method] = str_contains($uses, '@') ? explode('@', $uses, 2) : [$uses, '__invoke'];

            if (! class_exists($class) || ! method_exists($class, $method)) {
                $broken[] = $route->uri().' → '.$uses;
            }
        }

        $this->assertSame([], $broken, "Routes pointing at missing controllers:\n".implode("\n", $broken));
    }

    public function test_route_list_builds(): void
    {
        $this->assertSame(0, Artisan::call('route:list', ['--json' => true]));
    }

    public function test_frontend_api_routes_are_registered(): void
    {
        foreach (['seo.index', 'password.forgot', 'password.reset', 'portal.token', 'auth.sim.saml', 'api-docs.index'] as $name) {
            $this->assertTrue(RouteFacade::has($name), "Route [{$name}] is not registered.");
        }
    }
}
