<?php

use App\Enums\AccountHistoryField;
use App\Models\AccountHistory;
use App\Models\User;

it('RF-35: el asiento resuelve la cuenta, el autor y el campo como enum', function () {
    $user = User::factory()->create();
    $author = User::factory()->create();

    $history = AccountHistory::factory()->for($user)->create([
        'author_id' => $author->id,
        'field' => AccountHistoryField::RoleChanged,
        'old_value' => ['staff'],
        'new_value' => ['admin'],
    ]);

    expect($history->user->is($user))->toBeTrue()
        ->and($history->author->is($author))->toBeTrue()
        ->and($history->field)->toBe(AccountHistoryField::RoleChanged)
        ->and($history->old_value)->toBe(['staff'])
        ->and($history->new_value)->toBe(['admin']);
});

it('RF-35: el asiento no lleva updated_at', function () {
    expect((new AccountHistory)->getUpdatedAtColumn())->toBeNull();
});
