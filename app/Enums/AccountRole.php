<?php

namespace App\Enums;

enum AccountRole: string
{
    case Admin = 'admin';
    case Staff = 'staff';
    case Client = 'client';

    public function label(): string
    {
        return __('accounts.roles.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role): array => [$role->value => $role->label()])
            ->all();
    }
}
