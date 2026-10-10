<?php

use App\Actions\AddPeriodDiscountAction;
use App\Actions\AddPeriodExtraAction;
use App\Actions\RegisterPaymentAction;
use App\Actions\UpdateContractAmountAction;
use App\Filament\Client\Pages\ContractsPage;
use App\Filament\Staff\Resources\ContractPeriods\Pages\ListContractPeriods;
use App\Filament\Staff\Resources\ContractPeriods\Pages\ViewContractPeriod;
use App\Filament\Staff\Resources\Contracts\Pages\ViewContract;
use App\Models\Contract;
use App\Models\ContractPeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;

/**
 * The files that make up the contracts and payments module (spec 005).
 *
 * @return list<string>
 */
function contractModuleFiles(): array
{
    $files = [];

    foreach ([
        app_path('Actions'),
        app_path('Exceptions'),
        app_path('Console/Commands'),
        app_path('Enums'),
        app_path('Models'),
        app_path('Filament/Staff/Resources/Contracts'),
        app_path('Filament/Staff/Resources/ContractPeriods'),
        app_path('Filament/Client/Pages'),
    ] as $directory) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }

    return $files;
}

it('RF-91, RNF-1: todos los textos del módulo que usan una clave de contratos existen en lang/es/contracts.php', function () {
    app()->setLocale('es');

    $missing = [];

    foreach (contractModuleFiles() as $file) {
        preg_match_all("/(?:__|trans_choice)\\('(contracts\\.[a-z_.]*[a-z_])'/", file_get_contents($file), $matches);

        foreach ($matches[1] as $key) {
            if (! Lang::has($key, 'es')) {
                $missing[$key] = basename($file);
            }
        }
    }

    expect($missing)->toBe([]);
});

it('RF-91: las claves que se arman al vuelo también existen', function () {
    app()->setLocale('es');

    foreach (['extra', 'discount'] as $type) {
        expect(Lang::has("contracts.validation.{$type}_description_required", 'es'))->toBeTrue();
        expect(Lang::has("contracts.concept_types.{$type}", 'es'))->toBeTrue();
    }

    foreach (['active', 'finished', 'cancelled'] as $status) {
        expect(Lang::has("contracts.statuses.{$status}", 'es'))->toBeTrue();
    }

    foreach (['pending', 'partial', 'paid'] as $status) {
        expect(Lang::has("contracts.payment_statuses.{$status}", 'es'))->toBeTrue();
    }

    foreach (['cash', 'transfer', 'other'] as $method) {
        expect(Lang::has("contracts.payment_methods.{$method}", 'es'))->toBeTrue();
    }

    foreach (['generated', 'concept_added', 'concept_removed', 'invoice_changed', 'payment_registered', 'payment_voided'] as $field) {
        expect(Lang::has("contracts.period_history_fields.{$field}", 'es'))->toBeTrue();
    }

    foreach (['created', 'status_changed', 'amount_changed', 'due_day_changed'] as $field) {
        expect(Lang::has("contracts.history_fields.{$field}", 'es'))->toBeTrue();
    }
});

it('RF-91: el código del módulo no lleva textos en castellano escritos a mano', function () {
    $hardcoded = [];

    foreach (contractModuleFiles() as $file) {
        foreach (token_get_all(file_get_contents($file)) as $token) {
            if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING && preg_match('/[áéíóúñÁÉÍÓÚÑ¿¡]/u', $token[1]) === 1) {
                $hardcoded[basename($file)][] = $token[1];
            }
        }
    }

    // `lang/es` is where the texts live; the only strings with accents allowed are the
    // PHPDoc-free code ones listed here, none so far.
    expect($hardcoded)->toBe([]);
});

it('RNF-1: todos los importes que el módulo guarda tienen exactamente 2 decimales', function () {
    app()->setLocale('es');
    $this->seed(RoleSeeder::class);
    Carbon::setTestNow(CarbonImmutable::parse('2026-10-15 15:00:00', 'UTC'));
    $staff = User::factory()->staff()->create();
    $contract = Contract::factory()->startingOn('2026-10-01')->indefinite()->create(['monthly_amount' => '1000']);
    $period = ContractPeriod::factory()->create(['contract_id' => $contract->id, 'fee' => '1000', 'total' => '1000']);

    app(UpdateContractAmountAction::class)->handle($contract, '1234.5', $staff);
    app(AddPeriodExtraAction::class)->handle($period, ['description' => 'Extra', 'amount' => '10.1'], $staff);
    app(AddPeriodDiscountAction::class)->handle($period->fresh(), ['description' => 'Descuento', 'amount' => '3'], $staff);
    $payment = app(RegisterPaymentAction::class)->handle($period->fresh(), ['paid_on' => '2026-10-10', 'amount' => '100.5', 'method' => 'cash'], $staff);

    $period = $period->fresh();
    $values = [
        $contract->fresh()->monthly_amount,
        $period->fee,
        $period->total,
        $period->paid_amount,
        $period->balance,
        $period->concepts()->first()->amount,
        $period->concepts()->orderByDesc('id')->first()->amount,
        $payment->amount,
    ];

    foreach ($values as $value) {
        expect($value)->toMatch('/^-?\d+\.\d{2}$/');
    }

    expect($period->total)->toBe('1007.10')
        ->and($period->paid_amount)->toBe('100.50')
        ->and($period->balance)->toBe('906.60');

    Carbon::setTestNow();
});

it('RNF-1: todos los importes que el módulo muestra llevan 2 decimales', function () {
    app()->setLocale('es');
    $this->seed(RoleSeeder::class);
    Carbon::setTestNow(CarbonImmutable::parse('2026-10-15 15:00:00', 'UTC'));
    $staff = User::factory()->staff()->create();
    $contract = Contract::factory()->create(['monthly_amount' => '1234.50']);
    $period = ContractPeriod::factory()->create(['contract_id' => $contract->id, 'fee' => '1234.50', 'total' => '1234.50']);
    app(AddPeriodExtraAction::class)->handle($period, ['description' => 'Extra', 'amount' => '10.1'], $staff);
    app(RegisterPaymentAction::class)->handle($period->fresh(), ['paid_on' => '2026-10-10', 'amount' => '100', 'method' => 'cash'], $staff);

    $this->actingAs($staff);
    Filament::setCurrentPanel('staff');

    $pages = [
        Livewire::test(ListContractPeriods::class),
        Livewire::test(ViewContractPeriod::class, ['record' => $period->getKey()]),
        Livewire::test(ViewContract::class, ['record' => $contract->getKey()]),
    ];

    $account = User::factory()->client()->create(['client_id' => $contract->client_id]);
    $this->actingAs($account);
    Filament::setCurrentPanel('client');
    $pages[] = Livewire::test(ContractsPage::class);

    foreach ($pages as $page) {
        $text = html_entity_decode(strip_tags($page->html()));

        preg_match_all('/\$\s*[\d.,]+/u', $text, $all);
        preg_match_all('/\$\s*\d{1,3}(?:\.\d{3})*,\d{2}(?!\d)/u', $text, $formatted);

        expect($all[0])->not->toBe([])
            ->and($formatted[0])->toBe($all[0]);
    }

    Carbon::setTestNow();
});
