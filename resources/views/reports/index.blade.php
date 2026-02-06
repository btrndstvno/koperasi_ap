@extends('layouts.app')

@section('title', 'Laporan Bulanan - Koperasi')
@section('breadcrumb', 'Ringkasan Laporan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-speedometer2 me-2"></i>Ringkasan Laporan</h4>
</div>

<!-- Period Selector -->
<div class="card mb-4">
    <div class="card-body">
        <form action="{{ route('reports.index') }}" method="GET" class="row g-3 align-items-end">
            <div class="col-auto">
                <label class="form-label">Periode Laporan</label>
                <div class="input-group">
                    <select name="month" class="form-select">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
                            </option>
                        @endfor
                    </select>
                    <select name="year" class="form-select">
                        @for($y = now()->year - 2; $y <= now()->year + 1; $y++)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-1"></i> Tampilkan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="stat-card bg-primary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value">{{ number_format($totalMembers) }}</div>
                    <div class="stat-label">Total Anggota</div>
                </div>
                <div class="stat-icon"><i class="bi bi-people"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="stat-card bg-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value">Rp {{ number_format($totalSavings, 0, ',', '.') }}</div>
                    <div class="stat-label">Total Simpanan</div>
                </div>
                <div class="stat-icon"><i class="bi bi-wallet2"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="stat-card bg-danger">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value">Rp {{ number_format($totalActiveLoans, 0, ',', '.') }}</div>
                    <div class="stat-label">Total Pinjaman Aktif ({{ $activeLoanCount }})</div>
                </div>
                <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="stat-card bg-info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value">Rp {{ number_format(($cashFlow->grand_total ?? 0), 0, ',', '.') }}</div>
                    <div class="stat-label">Cash Flow ({{ \Carbon\Carbon::create()->month($month)->translatedFormat('F') }})</div>
                </div>
                <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Rekap Transaksi Department -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-building me-2"></i>Rekap Simpanan per Departemen</h6>
            </div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Departemen</th>
                            <th class="text-center">Anggota</th>
                            <th class="text-end">Total Simpanan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rekapDept as $dept)
                        <tr>
                            <td>{{ $dept->dept }}</td>
                            <td class="text-center"><span class="badge bg-secondary rounded-pill">{{ $dept->member_count }}</span></td>
                            <td class="text-end fw-bold">Rp {{ number_format($dept->total_savings, 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-3">Tidak ada data</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Rekap Pinjaman Department -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-credit-card me-2"></i>Rekap Hutang per Departemen</h6>
            </div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Departemen</th>
                            <th class="text-center">Peminjam</th>
                            <th class="text-end">Sisa Hutang</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loansByDept as $dept)
                        <tr>
                            <td>{{ $dept->dept }}</td>
                            <td class="text-center"><span class="badge bg-danger rounded-pill">{{ $dept->loan_count }}</span></td>
                            <td class="text-end fw-bold text-danger">Rp {{ number_format($dept->total_debt, 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-3">Tidak ada data pinjaman aktif</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2"></i>Riwayat Transaksi Terbaru ({{ \Carbon\Carbon::create()->month($month)->translatedFormat('F') }})</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Tanggal</th>
                    <th>Anggota</th>
                    <th>Tipe</th>
                    <th>Keterangan</th>
                    <th class="text-end">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $trx)
                <tr>
                    <td>{{ $trx->transaction_date->format('d/m/Y') }}</td>
                    <td>
                        <div class="fw-bold">{{ $trx->member->name }}</div>
                        <small class="text-muted">{{ $trx->member->nik }}</small>
                    </td>
                    <td>
                        <span class="badge bg-{{ $trx->type == 'saving_withdraw' || $trx->type == 'loan_disbursement' ? 'danger' : 'success' }}">
                            {{ ucwords(str_replace('_', ' ', $trx->type)) }}
                        </span>
                    </td>
                    <td>{{ $trx->notes }}</td>
                    <td class="text-end fw-bold {{ $trx->type == 'saving_withdraw' || $trx->type == 'loan_disbursement' ? 'text-danger' : 'text-success' }}">
                        Rp {{ number_format($trx->total_amount, 0, ',', '.') }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-4 text-muted">Belum ada transaksi di bulan ini</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())
    <div class="card-footer bg-white">
        {{ $transactions->links() }}
    </div>
    @endif
</div>
@endsection
