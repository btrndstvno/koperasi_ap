@extends('layouts.app')

@section('title', 'Laporan Bulanan')
@section('breadcrumb')
<a href="{{ route('reports.index') }}" class="text-decoration-none">Laporan</a> / Bulanan
@endsection

@section('content')
<style>
    @media print {
        @page { size: landscape; margin: 10mm; }
        body { background: white; font-family: Arial, sans-serif; font-size: 11px; }
        .no-print { display: none !important; }
        .card { border: none !important; box-shadow: none !important; }
        .card-header { display: none !important; }
        .tab-content { display: block !important; }
        .tab-pane { display: block !important; opacity: 1 !important; visibility: visible !important; margin-bottom: 20px; page-break-inside: avoid; }
        .tab-pane:not(.active) { display: block !important; }
        .table { width: 100% !important; border-collapse: collapse !important; border: 1px solid #000 !important; }
        .table th, .table td { border: 1px solid #000 !important; padding: 4px !important; color: #000 !important; }
        .badge { border: 1px solid #000; color: #000 !important; background: none !important; }
        .text-danger { color: #000 !important; }
        .text-success { color: #000 !important; }
        .text-primary { color: #000 !important; }
        
        /* Show tab title in print */
        .print-tab-title { display: block !important; font-size: 14px; font-weight: bold; margin-bottom: 5px; border-bottom: 1px solid #000; padding-bottom: 2px; }
    }
    .print-tab-title { display: none; }
    .table-sm td, .table-sm th { font-size: 0.85rem; vertical-align: middle; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <h4 class="mb-0"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Laporan Transaksi Bulanan</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.monthly', ['month' => $month, 'year' => $year, 'export' => 'excel']) }}" class="btn btn-success">
            <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
        </a>
        <button onclick="window.print()" class="btn btn-primary">
            <i class="bi bi-printer me-1"></i> Cetak Laporan
        </button>
    </div>
</div>

<!-- Filter -->
<div class="card mb-4 no-print">
    <div class="card-body">
        <form action="{{ route('reports.monthly') }}" method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Bulan</label>
                <select name="month" class="form-select">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
                        </option>
                    @endfor
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tahun</label>
                <select name="year" class="form-select">
                    @for($y = now()->year - 2; $y <= now()->year + 1; $y++)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-secondary w-100">
                    <i class="bi bi-search me-1"></i> Lihat
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Report Title buat Print -->
<div class="d-none d-print-block text-center mb-4">
    <h3>KOPERASI ARTHA PRIMA</h3>
    <h4>LAPORAN TRANSAKSI BULAN {{ strtoupper(\Carbon\Carbon::create()->month($month)->translatedFormat('F')) }} {{ $year }}</h4>
</div>

<div class="card">
    <div class="card-header no-print">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            @php $isFirst = true; @endphp
            @foreach($groupedData as $tag => $data)
                <li class="nav-item">
                    <button class="nav-link {{ $isFirst ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-{{ Str::slug($tag) }}" type="button">
                        {{ $tag }} <span class="badge bg-secondary ms-1">{{ $data->members->count() }}</span>
                    </button>
                </li>
                @php $isFirst = false; @endphp
            @endforeach
        </ul>
    </div>
    <div class="card-body p-0">
        <div class="tab-content">
            @php $isFirst = true; @endphp
            @foreach($groupedData as $tag => $data)
                <div class="tab-pane fade {{ $isFirst ? 'show active' : '' }}" id="tab-{{ Str::slug($tag) }}">
                    <div class="print-tab-title">{{ $tag }}</div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm mb-0 table-hover">
                            <thead class="table-light">
                                <tr class="text-center align-middle">
                                    <th width="40">NO</th>
                                    <th width="70">NIK</th>
                                    <th>NAMA/CSD</th>
                                    <th width="60">DEPT</th>
                                    <th width="90">POT KOP</th>
                                    <th width="90">IUR KOP</th>
                                    <th width="90">IUR TUNAI</th>
                                    <th width="90">JUMLAH</th>
                                    <th width="100">SISA PINJAMAN</th>
                                    <th width="100">SALDO KOP</th>
                                    <th width="80">KET</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($data->members as $index => $member)
                                <tr>
                                    <td class="text-center">{{ $index + 1 }}</td>
                                    <td><code class="small">{{ $member->nik }}</code></td>
                                    <td>
                                        {{ $member->name }}
                                        <div class="small text-muted">{{ $member->csd }}</div>
                                    </td>
                                    <td class="text-center small">{{ $member->dept }}</td>
                                    <td class="text-end text-danger fw-bold">
                                        {{ $member->pot_kop > 0 ? number_format($member->pot_kop, 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="text-end text-success">
                                        {{ $member->iur_kop > 0 ? number_format($member->iur_kop, 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="text-end text-primary">
                                        {{ $member->iur_tunai > 0 ? number_format($member->iur_tunai, 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="text-end fw-bold">
                                        {{ $member->total > 0 ? number_format($member->total, 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="text-end text-danger">
                                        {{ $member->sisa_pinjaman > 0 ? number_format($member->sisa_pinjaman, 0, ',', '.') : '-' }}
                                        @if($member->sisa_tenor > 0)
                                        <div class="small text-muted">({{ $member->sisa_tenor }}x)</div>
                                        @endif
                                    </td>
                                    <td class="text-end text-success fw-bold">
                                        {{ $member->saldo_kop > 0 ? number_format($member->saldo_kop, 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="small text-muted"></td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="11" class="text-center py-3 text-muted">Tidak ada data</td>
                                </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td colspan="4" class="text-end">SUBTOTAL {{ strtoupper($tag) }}</td>
                                    <td class="text-end text-danger">{{ number_format($data->subtotal_pot, 0, ',', '.') }}</td>
                                    <td class="text-end text-success">{{ number_format($data->subtotal_iur, 0, ',', '.') }}</td>
                                    <td class="text-end text-primary">{{ number_format($data->subtotal_iur_tunai, 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($data->subtotal_total, 0, ',', '.') }}</td>
                                    <td class="text-end text-danger">{{ number_format($data->subtotal_sisa_pinjaman, 0, ',', '.') }}</td>
                                    <td class="text-end text-success">{{ number_format($data->subtotal_saldo_kop, 0, ',', '.') }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                @php $isFirst = false; @endphp
            @endforeach
        </div>
        
        <!-- Grand Total Summary -->
        <div class="p-3 border-top bg-light">
            <div class="row text-center fw-bold">
                <div class="col">
                    <small class="d-block text-muted">TOTAL POT KOP</small>
                    <span class="text-danger h5">Rp {{ number_format($grandTotal->pot_kop, 0, ',', '.') }}</span>
                </div>
                <div class="col">
                    <small class="d-block text-muted">TOTAL IUR KOP</small>
                    <span class="text-success h5">Rp {{ number_format($grandTotal->iur_kop, 0, ',', '.') }}</span>
                </div>
                <div class="col">
                    <small class="d-block text-muted">TOTAL IUR TUNAI</small>
                    <span class="text-primary h5">Rp {{ number_format($grandTotal->iur_tunai, 0, ',', '.') }}</span>
                </div>
                <div class="col">
                    <small class="d-block text-muted">GRAND TOTAL</small>
                    <span class="text-dark h4">Rp {{ number_format($grandTotal->total, 0, ',', '.') }}</span>
                </div>
            </div>
            <hr>
            <div class="row text-center fw-bold">
                <div class="col">
                    <small class="d-block text-muted">TOTAL SISA PINJAMAN (Historis)</small>
                    <span class="text-danger h5">Rp {{ number_format($grandTotal->sisa_pinjaman, 0, ',', '.') }}</span>
                </div>
                <div class="col">
                    <small class="d-block text-muted">TOTAL SALDO SIMPANAN (Historis)</small>
                    <span class="text-success h5">Rp {{ number_format($grandTotal->saldo_kop, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
