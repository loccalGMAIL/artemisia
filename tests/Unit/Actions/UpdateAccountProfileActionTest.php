<?php

use App\Actions\UpdateAccountProfileAction;
use App\Enums\AccountHistoryField;
use App\Exceptions\CannotChangeOwnEmailException;
use App\Exceptions\DuplicateAccountEmailException;
use App\Models\AccountHistory;
use App\Models\User;
use App\Notifications\AccountEmailChanged;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('RF-2, RF-20: guarda el nombre nuevo y deja un asiento con el nombre anterior y el nuevo, el autor y la fecha', function () {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->staff()->create(['name' => 'Ana Peres']);

    $result = app(UpdateAccountProfileAction::class)->handle($target, ['name' => 'Ana Pérez'], $actor);

    expect($result->name)->toBe('Ana Pérez')
        ->and($target->refresh()->name)->toBe('Ana Pérez');

    $history = AccountHistory::query()->where('user_id', $target->id)->sole();

    expect($history->field)->toBe(AccountHistoryField::NameChanged)
        ->and($history->old_value)->toBe(['name' => 'Ana Peres'])
        ->and($history->new_value)->toBe(['name' => 'Ana Pérez'])
        ->and($history->author_id)->toBe($actor->id)
        ->and($history->created_at)->not->toBeNull();
});

it('RF-2, RF-20: una cuenta que cambia su propio nombre es la autora del asiento', function () {
    $account = User::factory()->client()->create(['name' => 'Luis']);

    app(UpdateAccountProfileAction::class)->handle($account, ['name' => 'Luis Gómez'], $account);

    $history = AccountHistory::query()->where('user_id', $account->id)->sole();

    expect($account->refresh()->name)->toBe('Luis Gómez')
        ->and($history->author_id)->toBe($account->id);
});

it('RF-4: rechaza un nombre vacío o de más de 150 caracteres sin cambiar la cuenta', function (string $name) {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->staff()->create(['name' => 'Ana Pérez']);

    try {
        app(UpdateAccountProfileAction::class)->handle($target, ['name' => $name], $actor);
        $this->fail('Se esperaba una ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('name');
    }

    expect($target->refresh()->name)->toBe('Ana Pérez')
        ->and(AccountHistory::query()->count())->toBe(0);
})->with([
    'vacío' => [''],
    'solo espacios' => ['   '],
    'más de 150 caracteres' => [str_repeat('a', 151)],
]);

it('RF-23: guardar sin cambios no deja ningún asiento', function () {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->staff()->create(['name' => 'Ana Peres']);
    $action = app(UpdateAccountProfileAction::class);

    $action->handle($target, ['name' => 'Ana Pérez'], $actor);
    $action->handle($target->refresh(), ['name' => 'Ana Pérez'], $actor);
    $action->handle($target->refresh(), ['name' => '  Ana Pérez  '], $actor);

    expect(AccountHistory::query()->where('user_id', $target->id)->count())->toBe(1);
});

it('RF-16, RF-21: guarda el email nuevo en minúsculas y sin espacios exteriores, con su asiento', function () {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->staff()->create(['name' => 'Ana Pérez', 'email' => 'ana@example.com']);

    app(UpdateAccountProfileAction::class)->handle(
        $target,
        ['name' => 'Ana Pérez', 'email' => '  Ana.Nueva@Example.COM '],
        $actor,
    );

    expect($target->refresh()->email)->toBe('ana.nueva@example.com');

    $history = AccountHistory::query()->where('user_id', $target->id)->sole();

    expect($history->field)->toBe(AccountHistoryField::EmailChanged)
        ->and($history->old_value)->toBe(['email' => 'ana@example.com'])
        ->and($history->new_value)->toBe(['email' => 'ana.nueva@example.com'])
        ->and($history->author_id)->toBe($actor->id);
});

it('RF-20, RF-21: cambiar el nombre y el email deja un asiento por cada uno', function () {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->staff()->create(['name' => 'Ana Peres', 'email' => 'ana@example.com']);

    app(UpdateAccountProfileAction::class)->handle(
        $target,
        ['name' => 'Ana Pérez', 'email' => 'ana.nueva@example.com'],
        $actor,
    );

    expect(AccountHistory::query()->where('user_id', $target->id)->pluck('field')->all())
        ->toEqualCanonicalizing([AccountHistoryField::NameChanged, AccountHistoryField::EmailChanged]);
});

it('RF-15: rechaza un email vacío o con formato inválido sin cambiar la cuenta', function (string $email) {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->staff()->create(['name' => 'Ana Peres', 'email' => 'ana@example.com']);

    try {
        app(UpdateAccountProfileAction::class)->handle($target, ['name' => 'Ana Pérez', 'email' => $email], $actor);
        $this->fail('Se esperaba una ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('email');
    }

    expect($target->refresh()->email)->toBe('ana@example.com')
        ->and($target->name)->toBe('Ana Peres')
        ->and(AccountHistory::query()->count())->toBe(0);
})->with([
    'vacío' => [''],
    'sin arroba' => ['ana.example.com'],
    'de más de 150 caracteres' => [str_repeat('a', 150).'@example.com'],
]);

it('RF-14: rechaza un email que ya usa otra cuenta, aunque esté inactiva y sin importar mayúsculas', function (bool $inactive) {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->staff()->create(['email' => 'ana@example.com']);
    $existing = User::factory()->staff()->create(['name' => 'Luis Gómez', 'email' => 'luis@example.com', 'is_active' => ! $inactive]);

    try {
        app(UpdateAccountProfileAction::class)->handle(
            $target,
            ['name' => $target->name, 'email' => ' LUIS@example.com '],
            $actor,
        );
        $this->fail('Se esperaba una DuplicateAccountEmailException.');
    } catch (DuplicateAccountEmailException $exception) {
        expect($exception->existingAccount->is($existing))->toBeTrue()
            ->and($exception->getMessage())->toContain('luis@example.com')->toContain('Luis Gómez');
    }

    expect($target->refresh()->email)->toBe('ana@example.com')
        ->and(AccountHistory::query()->count())->toBe(0);
})->with([
    'activa' => [false],
    'inactiva' => [true],
]);

it('RF-14: el email de la propia cuenta no cuenta como conflicto', function () {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->staff()->create(['name' => 'Ana Peres', 'email' => 'ana@example.com']);

    app(UpdateAccountProfileAction::class)->handle(
        $target,
        ['name' => 'Ana Pérez', 'email' => 'ANA@example.com'],
        $actor,
    );

    expect($target->refresh()->name)->toBe('Ana Pérez')
        ->and($target->email)->toBe('ana@example.com')
        ->and(AccountHistory::query()->where('user_id', $target->id)->sole()->field)->toBe(AccountHistoryField::NameChanged);
});

it('RF-12: una cuenta no puede cambiar su propio email', function () {
    $admin = User::factory()->admin()->create(['name' => 'Ana Peres', 'email' => 'ana@example.com']);

    expect(fn () => app(UpdateAccountProfileAction::class)->handle(
        $admin,
        ['name' => 'Ana Pérez', 'email' => 'otra@example.com'],
        $admin,
    ))->toThrow(CannotChangeOwnEmailException::class);

    expect($admin->refresh()->email)->toBe('ana@example.com')
        ->and($admin->name)->toBe('Ana Peres')
        ->and(AccountHistory::query()->count())->toBe(0);
});

it('RF-12: una cuenta sí puede guardar su nombre con su mismo email', function () {
    $admin = User::factory()->admin()->create(['name' => 'Ana Peres', 'email' => 'ana@example.com']);

    app(UpdateAccountProfileAction::class)->handle(
        $admin,
        ['name' => 'Ana Pérez', 'email' => 'ana@example.com'],
        $admin,
    );

    expect($admin->refresh()->name)->toBe('Ana Pérez')
        ->and($admin->email)->toBe('ana@example.com');
});

it('RF-17: avisa del cambio de email al email anterior y al nuevo', function () {
    Notification::fake();
    $actor = User::factory()->admin()->create();
    $target = User::factory()->staff()->create(['name' => 'Ana Pérez', 'email' => 'ana@example.com']);

    app(UpdateAccountProfileAction::class)->handle(
        $target,
        ['name' => 'Ana Pérez', 'email' => 'ana.nueva@example.com'],
        $actor,
    );

    Notification::assertSentOnDemandTimes(AccountEmailChanged::class, 2);

    Notification::assertSentOnDemand(
        AccountEmailChanged::class,
        function (AccountEmailChanged $notification, array $channels, object $notifiable): bool {
            $mail = $notification->toMail($notifiable);

            return $notifiable->routes['mail'] === 'ana@example.com'
                && ! $notification->forNewAddress
                && $mail->subject !== null
                && str_contains(implode(' ', $mail->introLines), 'ana.nueva@example.com');
        },
    );

    Notification::assertSentOnDemand(
        AccountEmailChanged::class,
        function (AccountEmailChanged $notification, array $channels, object $notifiable): bool {
            $mail = $notification->toMail($notifiable);

            return $notifiable->routes['mail'] === 'ana.nueva@example.com'
                && $notification->forNewAddress
                && $mail->subject !== null
                && str_contains(implode(' ', $mail->introLines), 'ana@example.com');
        },
    );
});

it('RF-17: solo avisa si el email cambia y si el cambio se confirma', function () {
    Notification::fake();
    $actor = User::factory()->admin()->create();
    $changed = User::factory()->staff()->create(['name' => 'Ana Pérez', 'email' => 'ana@example.com']);
    $nameOnly = User::factory()->staff()->create(['name' => 'Luis', 'email' => 'luis@example.com']);
    $rolledBack = User::factory()->staff()->create(['name' => 'Eva', 'email' => 'eva@example.com']);
    $action = app(UpdateAccountProfileAction::class);

    $action->handle($changed, ['name' => 'Ana Pérez', 'email' => 'ana.nueva@example.com'], $actor);
    Notification::assertSentOnDemandTimes(AccountEmailChanged::class, 2);

    $action->handle($nameOnly, ['name' => 'Luis Gómez', 'email' => 'luis@example.com'], $actor);
    $action->handle($nameOnly->refresh(), ['name' => 'Luis Gómez'], $actor);
    Notification::assertSentOnDemandTimes(AccountEmailChanged::class, 2);

    try {
        DB::transaction(function () use ($action, $rolledBack, $actor): void {
            $action->handle($rolledBack, ['name' => 'Eva', 'email' => 'eva.nueva@example.com'], $actor);

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    Notification::assertSentOnDemandTimes(AccountEmailChanged::class, 2);
    expect($rolledBack->refresh()->email)->toBe('eva@example.com');
});

it('RF-18: invalida los enlaces de definición de contraseña pendientes del email anterior', function () {
    Notification::fake();
    $actor = User::factory()->admin()->create();
    $target = User::factory()->staff()->create(['name' => 'Ana Pérez', 'email' => 'ana@example.com']);
    $other = User::factory()->staff()->create(['email' => 'otra@example.com']);
    $nameOnly = User::factory()->staff()->create(['name' => 'Luis', 'email' => 'luis@example.com']);
    $broker = Password::broker();

    $targetToken = $broker->createToken($target);
    $otherToken = $broker->createToken($other);
    $nameOnlyToken = $broker->createToken($nameOnly);

    $action = app(UpdateAccountProfileAction::class);

    $action->handle($nameOnly, ['name' => 'Luis Gómez'], $actor);
    expect($broker->tokenExists($nameOnly, $nameOnlyToken))->toBeTrue();

    $action->handle($target, ['name' => 'Ana Pérez', 'email' => 'ana.nueva@example.com'], $actor);

    expect(DB::table('password_reset_tokens')->where('email', 'ana@example.com')->exists())->toBeFalse()
        ->and(DB::table('password_reset_tokens')->where('email', 'ana.nueva@example.com')->exists())->toBeFalse()
        ->and($broker->tokenExists($target->refresh(), $targetToken))->toBeFalse()
        ->and($broker->tokenExists($other, $otherToken))->toBeTrue();
});
