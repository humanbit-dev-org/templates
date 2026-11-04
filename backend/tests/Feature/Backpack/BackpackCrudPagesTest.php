<?php

namespace Tests\Feature\Backpack;

use App\Models\Article;
use App\Models\Attachment;
use App\Models\BackpackRole;
use App\Models\Contatto;
use App\Models\Institutional;
use App\Models\Media;
use App\Models\Metadata;
use App\Models\ModelPermission;
use App\Models\Notification;
use App\Models\Page;
use App\Models\Role;
use App\Models\Translate;
use App\Models\User;
use App\Models\UserTwoFactor;
use Database\Seeders\BackpackRoleSeeder;
use Database\Seeders\ModelPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every Backpack CRUD must render its list, the AJAX search behind the list
 * table, the create form, the edit form and the show page for a Developer,
 * against the schema described by the migrations.
 *
 * This is the regression net for the models/migrations alignment: a column
 * read by a model, a request or a Backpack helper that the migrations do not
 * create makes one of these pages answer 500.
 */
class BackpackCrudPagesTest extends TestCase
{
    use DatabaseTransactions;

    private User $developer;

    protected function setUp(): void
    {
        parent::setUp();

        // Same reference data as DatabaseSeeder: roles, Backpack roles and the
        // model permissions that open every CRUD to the Developer role. The
        // database is the real one: everything is rolled back after the test.
        $this->seed([RoleSeeder::class, BackpackRoleSeeder::class, ModelPermissionSeeder::class]);

        $this->developer = User::query()->create([
            'username' => 'crud-test-developer',
            'email' => 'crud-test-developer@example.test',
            'name' => 'Dev',
            'surname' => 'Eloper',
            'role_id' => Role::query()->where('name', 'User')->value('id'),
            'backpack_role_id' => BackpackRole::query()->where('name', 'Developer')->value('id'),
            'password' => 'secret-password',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($this->developer, backpack_guard_name());
    }

    /**
     * CRUD route => factory of one record of that CRUD, so the edit and show
     * pages have something to open. Null for CRUDs whose records the setUp
     * already creates (users, roles, permissions).
     *
     * @return array<string, array{0: string, 1: (callable(): Model)|null}>
     */
    public static function cruds(): array
    {
        return [
            'user' => ['user', null],
            'user-two-factor' => ['user-two-factor', fn () => UserTwoFactor::query()->create([
                'username' => 'crud-test-developer',
                'device_id' => (string) Str::uuid(),
                'token_verified' => false,
            ])],
            'role' => ['role', null],
            'backpack-role' => ['backpack-role', null],
            'model-permission' => ['model-permission', null],
            'page' => ['page', fn () => Page::query()->create(['name' => 'home', 'title' => 'Home', 'order' => 1])],
            'contatto' => ['contatto', fn () => Contatto::query()->create([
                'email' => 'visitor@example.com',
                'oggetto' => Contatto::OGGETTI['altro'],
                'messaggio' => 'Hello',
            ])],
            'article' => ['article', fn () => Article::factory()->create()],
            'institutional' => ['institutional', fn () => Institutional::factory()->create()],
            'metadata' => ['metadata', fn () => Metadata::query()->create([
                'it' => 'Titolo',
                'code' => 'title',
                'page_id' => Page::query()->create(['name' => 'about', 'order' => 1])->id,
            ])],
            'media' => ['media', fn () => Media::query()->create(['title' => 'Hero'])],
            'attachment' => ['attachment', fn () => Attachment::query()->create(['title' => 'Brochure'])],
            'notification' => ['notification', fn () => Notification::query()->create([
                'type' => 'App\\Notifications\\PasswordUpdatedNotification',
                'notifiable_type' => User::class,
                'notifiable_id' => 'crud-test-developer',
                'data' => ['email' => 'crud-test-developer@example.test'],
            ])],
        ];
    }

    #[DataProvider('cruds')]
    public function test_crud_pages_render(string $route, ?callable $factory): void
    {
        $record = $factory !== null ? $factory() : $this->firstRecordOf($route);

        $this->get("/admin/{$route}")->assertOk();

        // The list table loads its rows through this AJAX endpoint.
        $this->post("/admin/{$route}/search", ['draw' => 1, 'start' => 0, 'length' => 10])
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->get("/admin/{$route}/create")->assertOk();
        $this->get("/admin/{$route}/{$record->getKey()}/edit")->assertOk();
        $this->get("/admin/{$route}/{$record->getKey()}/show")->assertOk();
    }

    /**
     * Translations are read-only in Backpack (they are imported from the
     * frontend JSON files): list, search and show work, create does not exist.
     */
    public function test_translate_archive_is_read_only(): void
    {
        $translate = Translate::query()->create(['code' => 'main_title', 'it' => 'Ciao', 'en' => 'Hello']);

        $this->get('/admin/translate?code=main_title')->assertOk()->assertSee('main_title');
        $this->post('/admin/translate/search', ['draw' => 1, 'start' => 0, 'length' => 10])->assertOk();
        $this->get("/admin/translate/{$translate->id}/show")->assertOk();
        $this->get('/admin/translate/create')->assertNotFound();
    }

    public function test_role_can_be_created_from_backpack(): void
    {
        $this->post('/admin/role', ['name' => 'Editor', 'description' => 'Content editor'])
            ->assertRedirect();

        $this->assertDatabaseHas('roles', ['name' => 'Editor', 'description' => 'Content editor']);
    }

    public function test_role_description_is_required_because_the_column_is_not_null(): void
    {
        $this->post('/admin/role', ['name' => 'Editor'])
            ->assertSessionHasErrors('description');

        $this->assertDatabaseMissing('roles', ['name' => 'Editor']);
    }

    public function test_user_creation_requires_the_not_null_columns(): void
    {
        $this->post('/admin/user', ['username' => 'crud-test-user'])
            ->assertSessionHasErrors(['email', 'name', 'surname', 'password', 'role_id']);

        $this->assertDatabaseMissing('users', ['username' => 'crud-test-user']);
    }

    public function test_user_can_be_updated_keeping_the_password_when_left_blank(): void
    {
        $user = User::query()->create([
            'username' => 'crud-test-user',
            'email' => 'crud-test-user@example.test',
            'name' => 'Mario',
            'surname' => 'Rossi',
            'role_id' => Role::query()->where('name', 'User')->value('id'),
            'password' => 'original-password',
        ]);
        $originalHash = $user->password;

        $this->put('/admin/user/crud-test-user', [
            'id' => 'crud-test-user',
            'username' => 'crud-test-user',
            'email' => 'crud-test-user@example.test',
            'name' => 'Marco',
            'surname' => 'Rossi',
            'role_id' => $user->role_id,
            'password' => '',
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('Marco', $user->name);
        $this->assertSame($originalHash, $user->password);
    }

    public function test_users_list_quick_filter_narrows_to_backpack_users(): void
    {
        User::query()->create([
            'username' => 'crud-test-frontend',
            'email' => 'crud-test-frontend@example.test',
            'name' => 'Front',
            'surname' => 'End',
            'role_id' => Role::query()->where('name', 'User')->value('id'),
            'password' => 'secret-password',
        ]);

        $this->get('/admin/user?preset=backpack')->assertOk()->assertSee('Filtri rapidi');

        $rows = $this->post('/admin/user/search?preset=backpack', ['draw' => 1, 'start' => 0, 'length' => 100])
            ->assertOk()
            ->json('data');

        // The database is the real one (rolled back): compare with what it holds.
        $this->assertCount(min(100, User::query()->whereNotNull('backpack_role_id')->count()), $rows);
        $this->assertStringNotContainsString('crud-test-frontend', json_encode($rows));
    }

    /**
     * First record of a CRUD whose rows come from setUp (seeders, developer).
     */
    private function firstRecordOf(string $route): Model
    {
        return match ($route) {
            'user' => $this->developer,
            'role' => Role::query()->firstOrFail(),
            'backpack-role' => BackpackRole::query()->firstOrFail(),
            'model-permission' => ModelPermission::query()->firstOrFail(),
        };
    }
}
