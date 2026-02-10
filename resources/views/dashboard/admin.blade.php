@extends('layouts.app')

@section('title', 'Dashboard Admin - Koperasi')
@section('breadcrumb', 'Dashboard')

@section('content')
<!-- Pending Loans Alert -->
@if($pendingLoansCount > 0)
<div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
    <div>
        <strong>Perhatian!</strong> Ada <strong>{{ $pendingLoansCount }}</strong> permintaan pinjaman menunggu persetujuan.
        <a href="{{ route('loans.index', ['status' => 'pending']) }}" class="alert-link ms-2">Lihat Sekarang →</a>
    </div>
</div>
@endif

<!-- Pending Withdrawals Alert -->
@if(isset($pendingWithdrawalsCount) && $pendingWithdrawalsCount > 0)
<div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
    <div>
        <strong>Perhatian!</strong> Ada <strong>{{ $pendingWithdrawalsCount }}</strong> permintaan penarikan saldo menunggu persetujuan.
        <a href="{{ route('withdrawals.index', ['status' => 'pending']) }}" class="alert-link ms-2">Lihat Sekarang →</a>
    </div>
</div>
@endif

<div class="row g-4 mb-4">
    <!-- Total Anggota -->
    <div class="col-md-6 col-xl-3">
        <div class="stat-card bg-primary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value">{{ number_format($totalMembers) }}</div>
                    <div class="stat-label">Total Anggota</div>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-people"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Total Simpanan -->
    <div class="col-md-6 col-xl-3">
        <div class="stat-card bg-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value">{{ number_format($totalSavings, 0, ',', '.') }}</div>
                    <div class="stat-label">Total Simpanan</div>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-wallet2"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Total Hutang -->
    <div class="col-md-6 col-xl-3">
        <div class="stat-card bg-danger">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value">{{ number_format($totalLoans, 0, ',', '.') }}</div>
                    <div class="stat-label">Total Piutang</div>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pinjaman Aktif -->
    <div class="col-md-6 col-xl-3">
        <div class="stat-card bg-info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value">{{ number_format($activeLoans) }}</div>
                    <div class="stat-label">Pinjaman Aktif</div>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-credit-card"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Transaksi Terakhir -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-2"></i>Transaksi Terakhir</span>
                <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Anggota</th>
                                <th>Jenis</th>
                                <th class="text-end">Nominal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentTransactions as $trx)
                            <tr>
                                <td>{{ $trx->transaction_date->format('d/m/Y') }}</td>
                                <td>
                                    <a href="{{ route('members.show', $trx->member_id) }}" class="text-decoration-none">
                                        {{ $trx->member->name }}
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $trx->type_badge_color }}">
                                        {{ $trx->type_label }}
                                    </span>
                                </td>
                                <td class="text-end fw-medium">
                                    Rp {{ number_format($trx->total_amount, 0, ',', '.') }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    Belum ada transaksi
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-lightning me-2"></i>Aksi Cepat
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="{{ route('transactions.bulk.create') }}" class="btn btn-primary">
                        <i class="bi bi-receipt me-2"></i>Input Transaksi Gajian
                    </a>
                    <a href="{{ route('members.create') }}" class="btn btn-outline-success">
                        <i class="bi bi-person-plus me-2"></i>Tambah Anggota Baru
                    </a>
                    <a href="{{ route('imports.index') }}" class="btn btn-outline-info">
                        <i class="bi bi-file-earmark-excel me-2"></i>Import dari Excel
                    </a>
                    <a href="{{ route('exports.members') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-download me-2"></i>Export Data Anggota
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Simpanan per Dept -->
        <div class="card mt-4">
            <div class="card-header">
                <i class="bi bi-pie-chart me-2"></i>Simpanan per Departemen
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @foreach($savingsByDept as $dept)
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span>{{ $dept->dept }}</span>
                        <span class="badge bg-success rounded-pill">
                            {{ number_format($dept->total / 1000000, 1) }}jt
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    @if($pendingLoansCount > 0)
    // SweetAlert untuk notifikasi pinjaman pending
    Swal.fire({
        title: 'Permintaan Pinjaman Baru!',
        html: 'Ada <strong>{{ $pendingLoansCount }}</strong> pengajuan pinjaman yang menunggu persetujuan.',
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-eye me-1"></i> Lihat Sekarang',
        cancelButtonText: 'Nanti Saja',
        showClass: {
            popup: 'animate__animated animate__fadeInDown'
        },
        hideClass: {
            popup: 'animate__animated animate__fadeOutUp'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '{{ route("loans.index", ["status" => "pending"]) }}';
        }
    });
    @endif

    @if($pendingWithdrawalsCount > 0)
    // SweetAlert untuk notifikasi penarikan pending
    Swal.fire({
        title: 'Permintaan Penarikan Saldo!',
        html: 'Ada <strong>{{ $pendingWithdrawalsCount }}</strong> pengajuan penarikan saldo yang menunggu persetujuan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-eye me-1"></i> Lihat Sekarang',
        cancelButtonText: 'Nanti Saja',
        confirmButtonTextColor: '#000',
        showClass: {
            popup: 'animate__animated animate__fadeInDown'
        },
        hideClass: {
            popup: 'animate__animated animate__fadeOutUp'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '{{ route("withdrawals.index", ["status" => "pending"]) }}';
        }
    });
    @endif
});
</script>
@endpush
