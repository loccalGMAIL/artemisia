<?php

namespace App\Filament\Staff\Resources\Clients;

use App\Models\Client;
use Closure;
use Filament\Schemas\Components\Component;

/**
 * Extension points of the client card for modules that do not exist yet (RF-46 to RF-48).
 *
 * The budgets, contracts and payments modules register their section here, usually from a
 * service provider, when they are implemented:
 *
 *     ClientCardExtensions::register('budgets', fn (Client $client) => Section::make(...));
 *
 * Until then nothing is registered, so the card queries no table that does not exist yet.
 */
final class ClientCardExtensions
{
    /** @var array<string, Closure(Client): Component> */
    private static array $sections = [];

    /**
     * @param  Closure(Client): Component  $section
     */
    public static function register(string $key, Closure $section): void
    {
        self::$sections[$key] = $section;
    }

    /**
     * @return array<int, string>
     */
    public static function registered(): array
    {
        return array_keys(self::$sections);
    }

    /**
     * The registered sections, built for the client being shown.
     *
     * @return array<int, Component>
     */
    public static function componentsFor(Client $client): array
    {
        return array_values(array_map(fn (Closure $section): Component => $section($client), self::$sections));
    }

    /** Forgets every registered section; meant for tests. */
    public static function flush(): void
    {
        self::$sections = [];
    }
}
