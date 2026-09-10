<?php

use App\Enums\AccountHistoryField;
use App\Models\AccountHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('RF-35: resuelve la cuenta y el autor de un asiento de historial', function () {
    $user = User::factory()->create();
    $author = User::factory()->create();

    $history = AccountHistory::factory()->create([
        'user_id' => $user->id,
        'field' => AccountHistoryField::Activated,
        'author_id' => $author->id,
    ]);

    expect($history->user)->toBeInstanceOf(User::class)
        ->and($history->user->is($user))->toBeTrue()
        ->and($history->author)->toBeInstanceOf(User::class)
        ->and($history->author->is($author))->toBeTrue()
        ->and($history->field)->toBe(AccountHistoryField::Activated);
});
