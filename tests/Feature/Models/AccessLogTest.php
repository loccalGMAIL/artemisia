<?php

use App\Enums\AccessMethod;
use App\Enums\AccessOutcome;
use App\Enums\AccessPortal;
use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('RF-36, RF-37: resuelve la cuenta asociada a un registro de acceso', function () {
    $user = User::factory()->create();

    $log = AccessLog::factory()->create([
        'user_id' => $user->id,
        'portal' => AccessPortal::Staff,
        'method' => AccessMethod::Password,
        'outcome' => AccessOutcome::Success,
    ]);

    expect($log->user)->toBeInstanceOf(User::class)
        ->and($log->user->is($user))->toBeTrue()
        ->and($log->portal)->toBe(AccessPortal::Staff)
        ->and($log->method)->toBe(AccessMethod::Password)
        ->and($log->outcome)->toBe(AccessOutcome::Success);
});

it('RF-37: admite un registro de acceso sin cuenta asociada', function () {
    $log = AccessLog::factory()->create([
        'user_id' => null,
        'outcome' => AccessOutcome::Rejected,
        'rejection_reason' => 'Credenciales inválidas',
    ]);

    expect($log->user)->toBeNull()
        ->and($log->outcome)->toBe(AccessOutcome::Rejected);
});
