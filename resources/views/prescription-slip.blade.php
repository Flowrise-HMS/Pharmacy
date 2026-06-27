<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Rx') }} {{ $patient?->mrn ?? '' }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            width: 80mm;
            max-width: 80mm;
            margin: 0 auto;
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            line-height: 1.35;
            color: #000;
            background: #fff;
        }
        .toolbar {
            width: 80mm;
            max-width: 80mm;
            margin: 0 auto 8px;
            padding: 8px 4px 0;
            text-align: center;
        }
        .toolbar button {
            font-family: inherit;
            font-size: 11px;
            padding: 6px 12px;
            border: 1px solid #000;
            background: #fff;
            cursor: pointer;
        }
        .receipt {
            width: 80mm;
            max-width: 80mm;
            padding: 3mm 2mm;
        }
        .center { text-align: center; }
        .bold { font-weight: 700; }
        .line { margin: 2px 0; word-wrap: break-word; overflow-wrap: anywhere; }
        .sep {
            border: none;
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        .rx-title { font-size: 16px; font-weight: 700; letter-spacing: 1px; }
        .med-block { margin: 4px 0; }
        .med-num { font-size: 10px; }
        .med-name { font-size: 12px; font-weight: 700; margin: 2px 0; }
        .sig { font-size: 11px; margin: 2px 0; white-space: pre-wrap; }
        .notice {
            font-size: 10px;
            text-align: center;
            margin-top: 6px;
        }
        .meta { font-size: 10px; }
        @media print {
            html, body { width: 80mm; }
            .toolbar { display: none !important; }
            .receipt { padding: 0 1mm; }
            @page {
                size: 80mm auto;
                margin: 2mm;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">{{ __('Print') }}</button>
    </div>

    <div class="receipt">
        <div class="center">
            <div class="rx-title">℞ {{ __('PRESCRIPTION') }}</div>
            <div class="line bold">{{ config('app.name', 'FlowRise HMS') }}</div>
            @if($branch?->name)
                <div class="line meta">{{ $branch->name }}</div>
            @endif
            @if($branch?->formatAddress(', '))
                <div class="line meta">{{ $branch->formatAddress(', ') }}</div>
            @endif
        </div>

        <hr class="sep">

        <div class="line meta">{{ __('Date') }}: {{ $issuedAt?->format('Y-m-d H:i') ?? '—' }}</div>
        @if($lines->count() > 1)
            <div class="line meta">{{ __('Medications') }}: {{ $lines->count() }}</div>
        @endif

        <hr class="sep">

        <div class="line"><span class="bold">{{ __('Client') }}:</span> {{ $client->name }}</div>
        @if($client->identifier)
            <div class="line meta">{{ $client->identifierLabel ?? __('Identifier') }}: {{ $client->identifier }}</div>
        @endif
        @if($client->phone && $client->isGuest())
            <div class="line meta">{{ __('Phone') }}: {{ $client->phone }}</div>
        @endif
        @if($client->email && $client->isGuest())
            <div class="line meta">{{ __('Email') }}: {{ $client->email }}</div>
        @endif
        @if($client->isPatient() && $patient?->date_of_birth)
            <div class="line meta">
                {{ __('DOB') }}: {{ $patient->date_of_birth->format('Y-m-d') }}
                @if($patient->age !== null)
                    · {{ $patient->age }}y
                @endif
            </div>
        @endif

        <hr class="sep">

        @foreach($lines as $index => $line)
            <div class="med-block">
                @if($lines->count() > 1)
                    <div class="med-num bold">{{ $index + 1 }}. {{ __('Medication') }}</div>
                @else
                    <div class="line bold">{{ __('Medication') }}</div>
                @endif
                <div class="med-name">{{ $line->item->service?->name ?? '—' }}</div>
                @if($line->item->quantity > 1)
                    <div class="line meta">{{ __('Qty') }}: {{ $line->item->quantity }}</div>
                @endif
                @if($line->item->serviceRequest?->request_number && $lines->count() > 1)
                    <div class="line meta">{{ __('Order') }}: {{ $line->item->serviceRequest->request_number }}</div>
                @endif
                <div class="line meta bold">{{ __('Directions') }}</div>
                <div class="sig">{{ $line->sigLine }}</div>
                @foreach($line->sigRows as $row)
                    <div class="line meta">{{ $row['label'] }}: {{ $row['value'] }}</div>
                @endforeach
                @if(filled($line->outsideDispense?->notes) && $line->outsideDispense->notes !== ($sharedNotes ?? null))
                    <div class="line meta bold">{{ __('Notes') }}</div>
                    <div class="sig">{{ $line->outsideDispense->notes }}</div>
                @endif
                @if($line->prescriber)
                    <div class="line meta">{{ __('Prescriber') }}: {{ $line->prescriber->name }}</div>
                @endif
            </div>
            @if(! $loop->last)
                <hr class="sep">
            @endif
        @endforeach

        @if(filled($sharedNotes))
            <hr class="sep">
            <div class="line bold">{{ __('Notes') }}</div>
            <div class="sig">{{ $sharedNotes }}</div>
        @endif

        @if($lines->count() === 1 && $lines->first()->item->serviceRequest?->request_number)
            <hr class="sep">
            <div class="line meta">{{ __('Order') }}: {{ $lines->first()->item->serviceRequest->request_number }}</div>
        @endif

        <hr class="sep">

        @if($pharmacist)
            <div class="line meta">{{ __('Pharmacy') }}: {{ $pharmacist->name }}</div>
        @endif

        <hr class="sep">

        <div class="notice">
            {{ __('Obtain at external pharmacy.') }}<br>
            {{ __('Not dispensed from this facility.') }}
        </div>

        <div class="center meta" style="margin-top:8px;">
            {{ now()->format('Y-m-d H:i') }}
        </div>
    </div>

    @if(request()->boolean('autoprint'))
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif
</body>
</html>
