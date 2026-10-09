<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ __('budgets.pdf.title', ['id' => $budget->id]) }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 18px 0 6px; border-bottom: 1px solid #d1d5db; padding-bottom: 3px; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; background: #f3f4f6; padding: 5px; font-size: 10px; }
        td { padding: 5px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .right { text-align: right; }
        .totals { width: 45%; margin-left: 55%; margin-top: 12px; }
        .totals td { border: 0; padding: 3px 5px; }
        .grand td { border-top: 1px solid #1f2937; font-weight: bold; font-size: 13px; }
    </style>
</head>
<body>
    <h1>{{ __('budgets.pdf.title', ['id' => $budget->id]) }}</h1>
    <p class="muted">{{ $budget->title }}</p>

    <table>
        <tr>
            <td><strong>{{ __('budgets.fields.issue_date') }}:</strong> {{ $budget->issue_date->format('d/m/Y') }}</td>
            <td><strong>{{ __('budgets.fields.validity_date') }}:</strong> {{ $budget->validity_date->format('d/m/Y') }}</td>
            <td><strong>{{ __('budgets.fields.modality') }}:</strong> {{ $budget->modality->label() }}</td>
        </tr>
    </table>

    <h2>{{ __('budgets.pdf.client') }}</h2>
    <p>
        <strong>{{ $client->display_name }}</strong><br>
        {{ $client->person_type->documentLabel() }}: {{ $client->document }}<br>
        @if ($client->street)
            {{ trim($client->street.' '.$client->street_number) }}@if ($client->city), {{ $client->city }}@endif @if ($client->province), {{ $client->province->name }}@endif<br>
        @endif
        @if ($phone)
            {{ __('budgets.pdf.phone') }}: {{ $phone }}
        @endif
    </p>

    <h2>{{ __('budgets.pdf.items') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('budgets.items.name') }}</th>
                <th>{{ __('budgets.items.description') }}</th>
                <th class="right">{{ __('budgets.items.quantity') }}</th>
                <th class="right">{{ __('budgets.items.unit_price') }}</th>
                <th class="right">{{ __('budgets.items.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ $item->quantity }}</td>
                    <td class="right">{{ \App\Support\Money::display($item->unit_price) }}</td>
                    <td class="right">{{ \App\Support\Money::display($item->amount) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>{{ __('budgets.fields.subtotal') }}</td>
            <td class="right">{{ \App\Support\Money::display($budget->subtotal) }}</td>
        </tr>
        <tr>
            <td>{{ __('budgets.fields.discount') }}@if ($budget->discount_type) ({{ $budget->discount_type->label() }})@endif</td>
            <td class="right">{{ \App\Support\Money::display($budget->discount_amount) }}</td>
        </tr>
        <tr class="grand">
            <td>{{ $budget->totalLabel() }}</td>
            <td class="right">{{ \App\Support\Money::display($budget->total) }}</td>
        </tr>
    </table>
</body>
</html>
