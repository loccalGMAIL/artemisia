<?php

use App\Actions\CreateAccountAction;
use App\Enums\AccountHistoryField;
use App\Models\AccountHistory;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('RF-2, RF-3, RF-5, RF-35: crea una cuenta activa con un solo rol, envía el enlace y registra el alta', function () {
    $this->seed(RoleSeeder::class);
    Notification::fake();

    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);
    $this->actingAs($admin);

    $account = app(CreateAccountAction::class)->handle([
        'name' => 'Ana Pérez',
        'email' => 'ana@example.com',
        'role' => 'staff',
    ]);

    expect($account)->toBeInstanceOf(User::class)
        ->and($account->is_active)->toBeTrue()
        ->and($account->hasRole('staff'))->toBeTrue()
        ->and($account->roles)->toHaveCount(1);

    Notification::assertSentTo($account, ResetPassword::class);

    $history = AccountHistory::where('user_id', $account->id)->first();

    expect($history)->not->toBeNull()
        ->and($history->field)->toBe(AccountHistoryField::Created)
        ->and($history->author_id)->toBe($admin->id);
});
