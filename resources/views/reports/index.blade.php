@extends('layouts.app')

@section('title', 'Laporan Bulanan - Koperasi')
@section('breadcrumb', 'Laporan Bulanan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Laporan Bulanan</h4>
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
                    <div class="stat-label">Total Piutang Aktif</div>
                </div>
                <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="stat-card bg-info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value">{{ number_format($activeLoanCount) }}</div>
                    <div class="stat-label">Pinjaman Aktif</div>
                </div>
                <div class="stat-icon"><i class="bi bi-credit-card"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Cash Flow Summary -->
<div class="card mb-4">
    <div class="card-header">
        <i class="bi bi-cash-coin me-2"></i>Cash Flow Bulan {{ \Carbon\Carbon::create()->month((int)$month)->translatedFormat('F') }} {{ $year }}
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col-md-3">
                <div class="border rounded p-3">
                    <small class="text-muted">Simpanan Masuk</small>
                    <h4 class="text-success mb-0">Rp {{ number_format($cashFlow->total_saving ?? 0, 0, ',', '.') }}</h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3">
                    <small class="text-muted">Pembayaran Pokok</small>
                    <h4 class="text-primary mb-0">Rp {{ number_format($cashFlow->total_principal ?? 0, 0, ',', '.') }}</h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3">
                    <small class="text-muted">Pendapatan Bunga</small>
                    <h4 class="text-info mb-0">Rp {{ number_format($cashFlow->total_interest ?? 0, 0, ',', '.') }}</h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 bg-light">
                    <small class="text-muted">Total Transaksi</small>
                    <h4 class="text-dark mb-0">Rp {{ number_format($cashFlow->grand_total ?? 0, 0, ',', '.') }}</h4>
                    <small class="text-muted">{{ $cashFlow->transaction_count ?? 0 }} transaksi</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Rekap per Departemen - Simpanan -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-building me-2"></i>Simpanan per Departemen
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Departemen</th>
                                <th class="text-center">Anggota</th>
                                <th class="text-end">Total Simpanan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rekapDept as $dept)
                            <tr>
                                <td><span class="badge bg-secondary">{{ $dept->dept }}</span></td>
                                <td class="text-center">{{ $dept->member_count }}</td>
                                <td class="text-end text-success fw-medium">
                                    Rp {{ number_format($dept->total_savings, 0, ',', '.') }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr class="fw-bold">
                                <td>TOTAL</td>
                                <td class="text-center">{{ $rekapDept->sum('member_count') }}</td>
                                <td class="text-end text-success">
                                    Rp {{ number_format($rekapDept->sum('total_savings'), 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Rekap per Departemen - Hutang -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-credit-card me-2"></i>Piutang per Departemen
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Departemen</th>
                                <th class="text-center">Pinjaman</th>
                                <th class="text-end">Total Piutang</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($loansByDept as $dept)
                            <tr>
                                <td><span class="badge bg-secondary">{{ $dept->dept }}</span></td>
                                <td class="text-center">{{ $dept->loan_count }}</td>
                                <td class="text-end text-danger fw-medium">
                                    Rp {{ number_format($dept->total_debt, 0, ',', '.') }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">Tidak ada piutang aktif</td>
                            </tr>
                            @endforelse
                        </tbody>
                        @if($loansByDept->count() > 0)
                        <tfoot class="table-light">
                            <tr class="fw-bold">
                                <td>TOTAL</td>
                                <td class="text-center">{{ $loansByDept->sum('loan_count') }}</td>
                                <td class="text-end text-danger">
                                    Rp {{ number_format($loansByDept->sum('total_debt'), 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transaction List -->
<div class="card mt-4">
    <div class="card-header">
        <i class="bi bi-list-ul me-2"></i>Daftar Transaksi Bulan {{ \Carbon\Carbon::create()->month((int)$month)->translatedFormat('F') }} {{ $year }}
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>Anggota</th>
                        <th>Jenis</th>
                        <th class="text-end">Simpanan</th>
                        <th class="text-end">Pokok</th>
                        <th class="text-end">Bunga</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $trx)
                    <tr>
                        <td>{{ $trx->transaction_date->format('d/m/Y') }}</td>
                        <td>
                            <a href="{{ route('members.show', $trx->member_id) }}" class="text-decoration-none">
                                {{ $trx->member->name }}
                            </a>
                        </td>
                        <td>
                            <span class="badge bg-{{ $trx->type_badge_color }}">{{ $trx->type_label }}</span>
                        </td>
                        <td class="text-end">
                            @if($trx->amount_saving > 0)
                                {{ number_format($trx->amount_saving, 0, ',', '.') }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-end">
                            @if($trx->amount_principal > 0)
                                {{ number_format($trx->amount_principal, 0, ',', '.') }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-end">
                            @if($trx->amount_interest > 0)
                                {{ number_format($trx->amount_interest, 0, ',', '.') }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-end fw-medium">{{ number_format($trx->total_amount, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            Tidak ada transaksi pada periode ini
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($transactions->hasPages())
    <div class="card-footer">
        {{ $transactions->appends(request()->query())->links() }}
    </div>
    @endif
</div>
@endsection
