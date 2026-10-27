<?php

use App\Enums\AccountHistoryField;
use App\Filament\Client\Pages\Auth\EditProfile;
use App\Models\AccountHistory;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('client');
});

it('RF-1: la cuenta cliente abre su perfil propio desde el menú de usuario con su nombre y su email', function () {
    $account = User::factory()->client()->create(['name' => 'Luis Gómez', 'email' => 'luis@example.com']);

    $this->actingAs($account)->get('/portal')->assertOk()->assertSee('/portal/profile');

    $this->get('/portal/profile')->assertOk();

    Livewire::test(EditProfile::class)
        ->assertFormSet(['name' => 'Luis Gómez', 'email' => 'luis@example.com']);
});

it('RF-2, RF-3: la cuenta cliente cambia su nombre y el email queda como está', function () {
    $account = User::factory()->client()->create(['name' => 'Luis', 'email' => 'luis@example.com']);
    $this->actingAs($account);

    Livewire::test(EditProfile::class)
        ->assertFormFieldIsDisabled('email')
        ->fillForm(['name' => 'Luis Gómez', 'email' => 'otro@example.com'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified(__('accounts.notifications.profile_saved'));

    expect($account->refresh()->name)->toBe('Luis Gómez')
        ->and($account->email)->toBe('luis@example.com')
        ->and(AccountHistory::query()->where('user_id', $account->id)->sole()->field)->toBe(AccountHistoryField::NameChanged);
});

it('RF-5, RF-10: la cuenta cliente cambia su contraseña con la actual y sigue con la sesión abierta', function () {
    $account = User::factory()->client()->create(['password' => 'clave-actual-1']);
    $this->actingAs($account);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'currentPassword' => 'clave-actual-1',
            'password' => 'clave-nueva-2',
            'passwordConfirmation' => 'clave-nueva-2',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $account->refresh();

    expect(Hash::check('clave-nueva-2', $account->password))->toBeTrue()
        ->and(session('password_hash_web'))->toBe(Auth::guard('web')->hashPasswordForCookie($account->password));
});

it('RF-9: una cuenta cliente sin contraseña no ve el cambio y se le indica la recuperación por email', function () {
    $account = User::factory()->client()->create(['password' => null]);
    $this->actingAs($account);

    Livewire::test(EditProfile::class)
        ->assertFormFieldIsHidden('currentPassword')
        ->assertSee(__('accounts.profile.no_password'));
});

it('RF-2: guardar el perfil de la cuenta no modifica la ficha del cliente vinculado', function () {
    $client = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'street' => 'Av. Colón']);
    ClientContact::factory()->for($client)->primary()->create(['name' => 'Marta Ruiz']);
    $account = User::factory()->client()->create(['name' => 'Luis', 'client_id' => $client->id]);
    $this->actingAs($account);

    $before = $client->refresh()->getAttributes();

    Livewire::test(EditProfile::class)
        ->fillForm(['name' => 'Luis Gómez'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($client->refresh()->getAttributes())->toBe($before)
        ->and($client->histories()->count())->toBe(0)
        ->and($client->contacts()->count())->toBe(1);
});
