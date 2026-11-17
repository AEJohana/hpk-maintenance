<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Label QR - {{ $machine->code }}</title>
    <link rel="stylesheet" href="{{ asset('maintenance.css') }}">
    <style>
        body {
            background-color: #f1f5f9;
            margin: 0;
            padding: 24px;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #0f172a;
        }
        .print-actions {
            max-width: 500px;
            margin: 0 auto 20px auto;
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
        }
        .btn-print {
            background-color: #0284c7;
            color: #ffffff;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-print:hover {
            background-color: #0369a1;
        }
        .btn-secondary {
            background-color: #e2e8f0;
            color: #334155;
            font-weight: 500;
            padding: 10px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-secondary:hover {
            background-color: #cbd5e1;
        }
        .label-container {
            max-width: 460px;
            margin: 0 auto;
            background: #ffffff;
            border: 2px solid #0f172a;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            box-sizing: border-box;
        }
        .label-header {
            text-align: center;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .label-header .company {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.5px;
            margin: 0;
            text-transform: uppercase;
        }
        .label-header .subcompany {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            margin: 2px 0 0 0;
        }
        .label-header .doc-title {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            color: #0284c7;
            margin-top: 6px;
            text-transform: uppercase;
        }
        .label-qr-section {
            text-align: center;
            margin-bottom: 16px;
        }
        .label-qr-section svg {
            display: block;
            margin: 0 auto;
            max-width: 220px;
            height: auto;
        }
        .qr-string {
            font-family: monospace;
            font-size: 11px;
            color: #475569;
            margin-top: 6px;
            word-break: break-all;
            background: #f8fafc;
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px dashed #cbd5e1;
        }
        .machine-code-badge {
            text-align: center;
            background: #0f172a;
            color: #ffffff;
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 16px;
        }
        .machine-code-badge .code {
            font-size: 26px;
            font-weight: 900;
            letter-spacing: 1px;
            margin: 0;
            line-height: 1.1;
        }
        .machine-code-badge .name {
            font-size: 13px;
            font-weight: 500;
            color: #94a3b8;
            margin: 2px 0 0 0;
        }
        .label-details {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 12px;
        }
        .label-details td {
            padding: 4px 6px;
            vertical-align: top;
            border-bottom: 1px solid #f1f5f9;
        }
        .label-details td.label {
            width: 32%;
            font-weight: 600;
            color: #475569;
        }
        .label-details td.value {
            font-weight: 700;
            color: #0f172a;
        }
        .label-footer {
            border-top: 1px dashed #94a3b8;
            padding-top: 8px;
            text-align: center;
            font-size: 10px;
            color: #64748b;
        }

        @media print {
            body {
                background: none;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            .label-container {
                box-shadow: none;
                border: 2px solid #000000;
                margin: 0 auto;
                page-break-inside: avoid;
            }
            .machine-code-badge {
                background: #000000 !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            @page {
                size: auto;
                margin: 8mm;
            }
        }
    </style>
</head>
<body>
    <div class="print-actions no-print">
        <a href="{{ route('machines.show', $machine) }}" class="btn-secondary">← Kembali</a>
        <div style="display: flex; gap: 8px;">
            <a href="{{ route('machines.qr-svg', $machine) }}" download class="btn-secondary" title="Unduh file SVG asli">Unduh SVG</a>
            <button onclick="window.print()" class="btn-print">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Cetak Label
            </button>
        </div>
    </div>

    <div class="label-container">
        <div class="label-header">
            <h1 class="company">KAROSERI HPK</h1>
            <p class="subcompany">PT HARAPAN PUTERA MANDIRI</p>
            <div class="doc-title">Label Pemeriksaan Harian Mesin</div>
        </div>

        <div class="label-qr-section">
            {!! $qrSvg !!}
            <div class="qr-string">{{ $machine->qr_value }}</div>
        </div>

        <div class="machine-code-badge">
            <h2 class="code">{{ $machine->code }}</h2>
            <div class="name">{{ $machine->name }}</div>
        </div>

        <table class="label-details">
            <tr>
                <td class="label">Merk / Tipe:</td>
                <td class="value">{{ $machine->brand ?? '—' }} {{ $machine->model ? '('.$machine->model.')' : '' }}</td>
            </tr>
            <tr>
                <td class="label">Lokasi:</td>
                <td class="value">{{ $machine->location?->area }} / {{ $machine->location?->name }}</td>
            </tr>
            <tr>
                <td class="label">Bagian:</td>
                <td class="value">{{ $machine->department->name }}</td>
            </tr>
            @if($machine->asset_number)
            <tr>
                <td class="label">Nomor Aset:</td>
                <td class="value">{{ $machine->asset_number }}</td>
            </tr>
            @endif
            @if($machine->power_kw || $machine->capacity)
            <tr>
                <td class="label">Spesifikasi:</td>
                <td class="value">
                    {{ $machine->power_kw ? $machine->power_kw.' KW' : '' }}
                    {{ $machine->power_hp ? '('.$machine->power_hp.' HP)' : '' }}
                    {{ $machine->capacity ? '· '.$machine->capacity : '' }}
                </td>
            </tr>
            @endif
        </table>

        <div class="label-footer">
            Pindai QR ini menggunakan menu <strong>Scan Daily Check</strong> untuk mengisi checklist pemeriksaan.
        </div>
    </div>
</body>
</html>
