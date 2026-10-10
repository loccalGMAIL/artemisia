<?php

use App\Actions\BuildClientStatementAction;
use App\Enums\ContractStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractPeriod;
use App\Models\Payment;
use App\Models\PeriodConcept;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    // 12:00 del 15 de octubre de 2026 en Argentina.
    Carbon::setTestNow(CarbonImmutable::parse('2026-10-15 15:00:00', 'UTC'));
    $this->client = Client::factory()->create();
    $this->account = User::factory()->client()->create(['client_id' => $this->client->id]);
});

afterEach(fn () => Carbon::setTestNow());

function statementContract(Client $client, array $attributes = []): Contract
{
    $budget = Budget::factory()->accepted()->monthly()->create(['client_id' => $client->id]);

    return Contract::factory()->create(['budget_id' => $budget->id, ...$attributes]);
}

function statement(User $account): array
{
    return app(BuildClientStatementAction::class)->handle($account);
}

it('RF-93: devuelve únicamente los contratos del cliente de la cuenta', function () {
    $own = statementContract($this->client);
    $other = statementContract(Client::factory()->create());

    $contracts = statement($this->account);

    expect($contracts)->toHaveCount(1)
        ->and($contracts[0]['id'])->toBe($own->id)
        ->and(collect($contracts)->pluck('id')->all())->not->toContain($other->id);
});

it('RF-93: incluye los contratos vigentes, cancelados y finalizados con su estado, importe, inicio y plazo', function () {
    $active = statementContract($this->client, ['monthly_amount' => '1500.00', 'start_date' => '2026-09-01', 'term_months' => 6, 'term_end_month' => '2027-02-01']);
    $cancelled = statementContract($this->client, ['status' => ContractStatus::Cancelled, 'start_date' => '2025-05-10']);
    $finished = statementContract($this->client, ['status' => ContractStatus::Finished, 'start_date' => '2024-01-10']);

    $contracts = collect(statement($this->account))->keyBy('id');

    expect($contracts)->toHaveCount(3)
        ->and($contracts[$active->id]['status'])->toBe(ContractStatus::Active)
        ->and($contracts[$active->id]['monthly_amount'])->toBe('1500.00')
        ->and($contracts[$active->id]['start_date']->toDateString())->toBe('2026-09-01')
        ->and($contracts[$active->id]['term_months'])->toBe(6)
        ->and($contracts[$cancelled->id]['status'])->toBe(ContractStatus::Cancelled)
        ->and($contracts[$cancelled->id]['term_months'])->toBeNull()
        ->and($contracts[$finished->id]['status'])->toBe(ContractStatus::Finished);
});

it('RF-94: de cada período devuelve la cuota, los extras, los descuentos con su justificación, el total, lo pagado, el saldo, el estado de pago y el vencimiento', function () {
    $contract = statementContract($this->client);
    $period = ContractPeriod::factory()->create([
        'contract_id' => $contract->id,
        'month' => '2026-09-01',
        'due_date' => '2026-09-10',
        'fee' => '1000.00',
        'total' => '1150.00',
        'paid_amount' => '400.00',
    ]);
    PeriodConcept::factory()->create(['contract_period_id' => $period->id, 'description' => 'Banner adicional', 'amount' => '300.00']);
    PeriodConcept::factory()->discount()->create(['contract_period_id' => $period->id, 'description' => 'Bonificación por pronto pago', 'amount' => '150.00']);

    $row = statement($this->account)[0]['periods'][0];

    expect($row['id'])->toBe($period->id)
        ->and($row['month']->toDateString())->toBe('2026-09-01')
        ->and($row['due_date']->toDateString())->toBe('2026-09-10')
        ->and($row['fee'])->toBe('1000.00')
        ->and($row['extras'])->toBe([['description' => 'Banner adicional', 'amount' => '300.00']])
        ->and($row['discounts'])->toBe([['description' => 'Bonificación por pronto pago', 'amount' => '150.00']])
        ->and($row['total'])->toBe('1150.00')
        ->and($row['paid_amount'])->toBe('400.00')
        ->and($row['balance'])->toBe('750.00')
        ->and($row['payment_status'])->toBe(PaymentStatus::Partial)
        ->and($row['overdue'])->toBeTrue();
});

it('RF-94: un período al día no está vencido y uno pagado tiene saldo cero', function () {
    $contract = statementContract($this->client);
    ContractPeriod::factory()->create(['contract_id' => $contract->id, 'month' => '2026-10-01', 'due_date' => '2026-10-25', 'total' => '1000.00', 'paid_amount' => '0.00']);
    ContractPeriod::factory()->create(['contract_id' => $contract->id, 'month' => '2026-08-01', 'due_date' => '2026-08-10', 'total' => '1000.00', 'paid_amount' => '1000.00']);

    $periods = collect(statement($this->account)[0]['periods'])->keyBy(fn ($row) => $row['month']->format('Y-m'));

    expect($periods['2026-10']['overdue'])->toBeFalse()
        ->and($periods['2026-10']['payment_status'])->toBe(PaymentStatus::Pending)
        ->and($periods['2026-08']['overdue'])->toBeFalse()
        ->and($periods['2026-08']['balance'])->toBe('0.00')
        ->and($periods['2026-08']['payment_status'])->toBe(PaymentStatus::Paid);
});

it('RF-94: los períodos vienen del más reciente al más antiguo', function () {
    $contract = statementContract($this->client);
    foreach (['2026-08-01', '2026-10-01', '2026-09-01'] as $month) {
        ContractPeriod::factory()->create(['contract_id' => $contract->id, 'month' => $month]);
    }

    $months = collect(statement($this->account)[0]['periods'])->map(fn ($row) => $row['month']->format('Y-m'))->all();

    expect($months)->toBe(['2026-10', '2026-09', '2026-08']);
});

it('RF-95: devuelve los pagos vigentes de cada período con fecha, importe, forma de pago y referencia', function () {
    $contract = statementContract($this->client);
    $period = ContractPeriod::factory()->create(['contract_id' => $contract->id]);
    $payment = Payment::factory()->create([
        'contract_period_id' => $period->id,
        'paid_on' => '2026-10-02',
        'amount' => '400.00',
        'method' => PaymentMethod::Transfer,
        'reference' => 'Transf. 998877',
    ]);

    $payments = statement($this->account)[0]['periods'][0]['payments'];

    expect($payments)->toHaveCount(1)
        ->and($payments[0]['id'])->toBe($payment->id)
        ->and($payments[0]['paid_on']->toDateString())->toBe('2026-10-02')
        ->and($payments[0]['amount'])->toBe('400.00')
        ->and($payments[0]['method'])->toBe(PaymentMethod::Transfer)
        ->and($payments[0]['reference'])->toBe('Transf. 998877');
});

it('RF-96: no devuelve los pagos anulados', function () {
    $contract = statementContract($this->client);
    $period = ContractPeriod::factory()->create(['contract_id' => $contract->id]);
    $valid = Payment::factory()->create(['contract_period_id' => $period->id, 'amount' => '400.00']);
    Payment::factory()->voided('Cargado dos veces')->create(['contract_period_id' => $period->id, 'amount' => '999.00']);

    $payments = statement($this->account)[0]['periods'][0]['payments'];

    expect(collect($payments)->pluck('id')->all())->toBe([$valid->id])
        ->and(json_encode(statement($this->account)))->not->toContain('999.00')
        ->and(json_encode(statement($this->account)))->not->toContain('Cargado dos veces');
});

it('RF-93, RF-97: no devuelve nada de otro cliente: ni contratos, ni períodos, ni conceptos, ni pagos', function () {
    $other = Client::factory()->create();
    $contract = statementContract($other);
    $period = ContractPeriod::factory()->create(['contract_id' => $contract->id, 'total' => '7777.00']);
    PeriodConcept::factory()->create(['contract_period_id' => $period->id, 'description' => 'Concepto ajeno']);
    Payment::factory()->create(['contract_period_id' => $period->id, 'amount' => '4444.00']);

    expect(statement($this->account))->toBe([]);
});

it('RF-97: una cuenta sin vínculo con un cliente no recibe nada', function () {
    statementContract($this->client);
    $unlinked = User::factory()->client()->create(['client_id' => null]);

    expect(statement($unlinked))->toBe([]);
});

it('RF-97: una cuenta que no es de cliente no recibe nada, aunque tenga un cliente asignado', function () {
    statementContract($this->client);
    $staff = User::factory()->staff()->create(['client_id' => $this->client->id]);

    expect(statement($staff))->toBe([]);
});

it('RF-97: la cuenta de otro cliente no ve los contratos del primero', function () {
    statementContract($this->client);
    $otherAccount = User::factory()->client()->create(['client_id' => Client::factory()->create()->id]);

    expect(statement($otherAccount))->toBe([]);
});

it('RF-94, RNF-4: arma el estado de cuenta con una cantidad fija de consultas, sin una por fila', function () {
    $build = function (int $contracts): int {
        foreach (range(1, $contracts) as $i) {
            $contract = statementContract($this->client);
            foreach (range(1, 3) as $m) {
                $period = ContractPeriod::factory()->create(['contract_id' => $contract->id, 'month' => "2026-0{$m}-01"]);
                PeriodConcept::factory()->create(['contract_period_id' => $period->id]);
                Payment::factory()->create(['contract_period_id' => $period->id, 'amount' => '10.00']);
            }
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        statement($this->account);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $few = $build(1);
    $many = $build(4);

    expect($many)->toBe($few);
});
