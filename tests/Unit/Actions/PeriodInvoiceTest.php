<?php

use App\Actions\SetPeriodInvoiceAction;
use App\Enums\PeriodHistoryField;
use App\Models\ContractPeriod;
use App\Models\PeriodHistory;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app()->setLocale('es');
    $this->staff = User::factory()->create();
});

function setInvoice(ContractPeriod $period, ?string $number, mixed $date, User $actor): ContractPeriod
{
    return app(SetPeriodInvoiceAction::class)->handle($period, $number, $date, $actor);
}

function invoiceEntries(ContractPeriod $period)
{
    return PeriodHistory::query()
        ->where('contract_period_id', $period->id)
        ->where('field', PeriodHistoryField::InvoiceChanged)
        ->orderBy('id')
        ->get();
}

it('RF-53: carga el número y la fecha de la factura de un período', function () {
    $period = ContractPeriod::factory()->create();

    $updated = setInvoice($period, '0001-00000123', '2026-10-05', $this->staff);

    expect($updated->invoice_number)->toBe('0001-00000123')
        ->and($updated->invoice_date->toDateString())->toBe('2026-10-05')
        ->and($period->fresh()->invoice_number)->toBe('0001-00000123');
});

it('RF-53: modifica una referencia ya cargada', function () {
    $period = ContractPeriod::factory()->invoiced('0001-00000100', '2026-09-30')->create();

    setInvoice($period, '0001-00000101', '2026-10-02', $this->staff);

    expect($period->fresh()->invoice_number)->toBe('0001-00000101')
        ->and($period->fresh()->invoice_date->toDateString())->toBe('2026-10-02');
});

it('RF-53: recorta los espacios del número', function () {
    $period = ContractPeriod::factory()->create();

    setInvoice($period, '  0001-00000123  ', '2026-10-05', $this->staff);

    expect($period->fresh()->invoice_number)->toBe('0001-00000123');
});

it('RF-54: rechaza el número sin su fecha y dice qué falta', function (mixed $date) {
    $period = ContractPeriod::factory()->create();

    try {
        setInvoice($period, '0001-00000123', $date, $this->staff);
        $this->fail('Se esperaba un rechazo.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('invoice_date')
            ->and($e->errors()['invoice_date'][0])->toContain('fecha');
    }

    expect($period->fresh()->invoice_number)->toBeNull()
        ->and(invoiceEntries($period))->toHaveCount(0);
})->with(['nula' => null, 'vacía' => '', 'espacios' => '  ']);

it('RF-54: rechaza la fecha sin su número y dice qué falta', function (?string $number) {
    $period = ContractPeriod::factory()->create();

    try {
        setInvoice($period, $number, '2026-10-05', $this->staff);
        $this->fail('Se esperaba un rechazo.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('invoice_number')
            ->and($e->errors()['invoice_number'][0])->toContain('número');
    }

    expect($period->fresh()->invoice_date)->toBeNull()
        ->and(invoiceEntries($period))->toHaveCount(0);
})->with(['nulo' => null, 'vacío' => '', 'espacios' => '   ']);

it('RF-54: rechaza una fecha que no es válida o un número demasiado largo', function (string $number, string $date) {
    $period = ContractPeriod::factory()->create();

    expect(fn () => setInvoice($period, $number, $date, $this->staff))->toThrow(ValidationException::class)
        ->and($period->fresh()->invoice_number)->toBeNull();
})->with([
    'fecha inexistente' => ['0001-1', '2026-02-31'],
    'fecha en texto' => ['0001-1', 'ayer'],
    'número de 51 caracteres' => [str_repeat('1', 51), '2026-10-05'],
]);

it('RF-55: un período puede quedar sin referencia de factura', function () {
    $period = ContractPeriod::factory()->create();

    expect($period->invoice_number)->toBeNull()
        ->and($period->invoice_date)->toBeNull();

    $unchanged = setInvoice($period, null, null, $this->staff);

    expect($unchanged->invoice_number)->toBeNull()
        ->and(invoiceEntries($period))->toHaveCount(0);
});

it('RF-55, RF-57: dejar número y fecha en blanco quita la referencia y lo registra', function () {
    $period = ContractPeriod::factory()->invoiced('0001-00000100', '2026-09-30')->create();

    $cleared = setInvoice($period, '', '', $this->staff);

    expect($cleared->invoice_number)->toBeNull()
        ->and($cleared->invoice_date)->toBeNull()
        ->and(invoiceEntries($period)->sole()->old_value)->toBe(['number' => '0001-00000100', 'date' => '2026-09-30'])
        ->and(invoiceEntries($period)->sole()->new_value)->toBe(['number' => null, 'date' => null]);
});

it('RF-56: admite el mismo número de factura en más de un período', function () {
    $first = ContractPeriod::factory()->create();
    $second = ContractPeriod::factory()->create();

    setInvoice($first, '0001-00000555', '2026-10-05', $this->staff);
    setInvoice($second, '0001-00000555', '2026-10-05', $this->staff);

    expect(ContractPeriod::query()->where('invoice_number', '0001-00000555')->count())->toBe(2);
});

it('RF-57: la primera carga registra el valor anterior vacío, el nuevo, el autor y la fecha', function () {
    $period = ContractPeriod::factory()->create();

    setInvoice($period, '0001-00000123', '2026-10-05', $this->staff);

    $entry = invoiceEntries($period)->sole();

    expect($entry->old_value)->toBe(['number' => null, 'date' => null])
        ->and($entry->new_value)->toBe(['number' => '0001-00000123', 'date' => '2026-10-05'])
        ->and($entry->author_id)->toBe($this->staff->id)
        ->and($entry->created_at)->not->toBeNull();
});

it('RF-57: cada modificación registra el valor anterior y el nuevo', function () {
    $period = ContractPeriod::factory()->create();

    setInvoice($period, '0001-00000123', '2026-10-05', $this->staff);
    setInvoice($period->fresh(), '0001-00000124', '2026-10-06', $this->staff);

    $entries = invoiceEntries($period);

    expect($entries)->toHaveCount(2)
        ->and($entries[1]->old_value)->toBe(['number' => '0001-00000123', 'date' => '2026-10-05'])
        ->and($entries[1]->new_value)->toBe(['number' => '0001-00000124', 'date' => '2026-10-06']);
});

it('RF-57: guardar los mismos valores no deja un asiento', function () {
    $period = ContractPeriod::factory()->invoiced('0001-00000123', '2026-10-05')->create();

    setInvoice($period, '0001-00000123', '2026-10-05', $this->staff);

    expect(invoiceEntries($period))->toHaveCount(0);
});
