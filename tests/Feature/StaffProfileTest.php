<?php

use App\Enums\AccountHistoryField;
use App\Filament\Staff\Pages\Auth\EditProfile;
use App\Models\AccountHistory;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('staff');
});

it('RF-1: la cuenta abre su perfil propio desde el menú de usuario con su nombre y su email', function (string $role) {
    $account = User::factory()->{$role}()->create(['name' => 'Ana Pérez', 'email' => 'ana@example.com']);

    $this->actingAs($account)->get('/staff')->assertOk()->assertSee('/staff/profile');

    $this->get('/staff/profile')->assertOk();

    Livewire::test(EditProfile::class)
        ->assertFormSet(['name' => 'Ana Pérez', 'email' => 'ana@example.com']);
})->with(['admin', 'staff']);

it('RF-3: el email se muestra pero no se puede modificar', function () {
    $account = User::factory()->staff()->create(['name' => 'Ana Peres', 'email' => 'ana@example.com']);
    $this->actingAs($account);

    Livewire::test(EditProfile::class)
        ->assertFormFieldIsDisabled('email')
        ->fillForm(['name' => 'Ana Pérez', 'email' => 'otra@example.com'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($account->refresh()->email)->toBe('ana@example.com')
        ->and($account->name)->toBe('Ana Pérez');
});

it('RF-2, RF-20: la cuenta guarda su nombre nuevo y queda un asiento a su nombre', function (string $role) {
    $account = User::factory()->{$role}()->create(['name' => 'Ana Peres']);
    $this->actingAs($account);

    Livewire::test(EditProfile::class)
        ->fillForm(['name' => 'Ana Pérez'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified(__('accounts.notifications.profile_saved'));

    expect($account->refresh()->name)->toBe('Ana Pérez');

    $history = AccountHistory::query()->where('user_id', $account->id)->sole();

    expect($history->field)->toBe(AccountHistoryField::NameChanged)
        ->and($history->old_value)->toBe(['name' => 'Ana Peres'])
        ->and($history->new_value)->toBe(['name' => 'Ana Pérez'])
        ->and($history->author_id)->toBe($account->id);
})->with(['admin', 'staff']);

it('RF-4: un nombre vacío se rechaza en el formulario y no cambia la cuenta', function () {
    $account = User::factory()->staff()->create(['name' => 'Ana Peres']);
    $this->actingAs($account);

    Livewire::test(EditProfile::class)
        ->fillForm(['name' => ''])
        ->call('save')
        ->assertHasFormErrors(['name']);

    expect($account->refresh()->name)->toBe('Ana Peres')
        ->and(AccountHistory::query()->count())->toBe(0);
});

it('RF-5, RF-6, RF-10: la cuenta cambia su contraseña con la actual y sigue con la sesión abierta', function () {
    $account = User::factory()->staff()->create(['password' => 'clave-actual-1']);
    $this->actingAs($account);

    Livewire::test(EditProfile::class)
        ->assertFormFieldIsVisible('currentPassword')
        ->fillForm([
            'currentPassword' => 'clave-actual-1',
            'password' => 'clave-nueva-2',
            'passwordConfirmation' => 'clave-nueva-2',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $account->refresh();

    expect(Hash::check('clave-nueva-2', $account->password))->toBeTrue()
        ->and(AccountHistory::query()->where('user_id', $account->id)->sole()->field)->toBe(AccountHistoryField::PasswordChanged)
        ->and(session('password_hash_web'))->toBe(Auth::guard('web')->hashPasswordForCookie($account->password));
});

it('RF-7, RF-8: la contraseña actual incorrecta y la nueva inválida se rechazan en sus campos', function (array $data, string $field) {
    $account = User::factory()->staff()->create(['password' => 'clave-actual-1']);
    $this->actingAs($account);

    Livewire::test(EditProfile::class)
        ->fillForm($data)
        ->call('save')
        ->assertHasFormErrors([$field]);

    expect(Hash::check('clave-actual-1', $account->refresh()->password))->toBeTrue()
        ->and(AccountHistory::query()->count())->toBe(0);
})->with([
    'actual incorrecta' => [['currentPassword' => 'otra-clave-9', 'password' => 'clave-nueva-2', 'passwordConfirmation' => 'clave-nueva-2'], 'currentPassword'],
    'nueva de menos de 8 caracteres' => [['currentPassword' => 'clave-actual-1', 'password' => 'corta', 'passwordConfirmation' => 'corta'], 'password'],
]);

it('RF-9: una cuenta sin contraseña no ve el cambio de contraseña y se le indica la recuperación por email', function () {
    $account = User::factory()->staff()->create(['password' => null]);
    $this->actingAs($account);

    Livewire::test(EditProfile::class)
        ->assertFormFieldIsHidden('currentPassword')
        ->assertFormFieldIsHidden('password')
        ->assertSee(__('accounts.profile.no_password'));
});
