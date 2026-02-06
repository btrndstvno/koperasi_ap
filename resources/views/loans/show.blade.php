@extends('layouts.app')

@section('title', 'Detail Pinjaman - Koperasi')
@section('breadcrumb')
<a href="{{ route('loans.index') }}" class="text-decoration-none">Pinjaman</a> / {{ $loan->member->name }}
@endsection

@section('content')
<!-- Loan Header -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-8">
                <h5 class="mb-1">
                    <a href="{{ route('members.show', $loan->member) }}" class="text-decoration-none">
                        {{ $loan->member->name }}
                    </a>
                </h5>
                <p class="text-muted mb-3">
                    <code>{{ $loan->member->nik }}</code> - {{ $loan->member->dept }}
                </p>
                
                <div class="row">
                    <div class="col-md-3">
                        <small class="text-muted">Pokok Pinjaman</small>
                        <h5>Rp {{ number_format($loan->amount, 0, ',', '.') }}</h5>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted">Durasi</small>
                        <h5>{{ $loan->duration }} Bulan</h5>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted">Bunga</small>
                        <h5>{{ $loan->interest_rate }}%</h5>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">Sisa Pokok</small>
                        <h5 class="text-danger">Rp {{ number_format($loan->remaining_principal, 0, ',', '.') }}</h5>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted">Status</small>
                        <h5>
                            <span class="badge bg-{{ $loan->status_badge }} {{ $loan->status === 'pending' ? 'text-dark' : '' }}">
                                {{ $loan->status_label }}
                            </span>
                        </h5>
                    </div>
                </div>
                
                @if($loan->isPending() && Auth::user()->isAdmin())
                <div class="mt-3 pt-3 border-top">
                    <a href="{{ route('loans.print', $loan) }}" target="_blank" class="btn btn-outline-primary me-2">
                        <i class="bi bi-printer me-1"></i> Cetak SPJ
                    </a>
                    <form action="{{ route('loans.approve', $loan) }}" method="POST" class="d-inline approve-form">
                        @csrf
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle me-1"></i> ACC / Cairkan
                        </button>
                    </form>
                    <form action="{{ route('loans.reject', $loan) }}" method="POST" class="d-inline reject-form ms-2">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="bi bi-x-circle me-1"></i> Tolak
                        </button>
                    </form>
                </div>
                @endif
            </div>
            @if(!$loan->isPending())
            <div class="col-md-4">
                <div class="text-center">
                    <small class="text-muted">Progress Pembayaran</small>
                    <div class="progress mt-2" style="height: 30px;">
                        <div class="progress-bar bg-success" style="width: {{ $loan->progress_percentage }}%">
                            {{ number_format($loan->progress_percentage, 1) }}%
                        </div>
                    </div>
                    <small class="text-muted">
                        Terbayar: Rp {{ number_format($loan->total_principal_paid, 0, ',', '.') }}
                    </small>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

@if($loan->isPending())
<!-- Info Pending -->
<div class="alert alert-info mb-4">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h5 class="mb-2"><i class="bi bi-hourglass-split me-2"></i>Menunggu Persetujuan</h5>
            <p class="mb-0">Pinjaman ini belum dicairkan. Cetak SPJ untuk ditandatangani, lalu klik ACC untuk mencairkan dana.</p>
        </div>
        <div class="col-md-4 text-end">
            <small class="text-muted">Uang yang akan cair:</small>
            <h4 class="text-primary mb-0">Rp {{ number_format($loan->disbursed_amount, 0, ',', '.') }}</h4>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <small class="text-muted">Pokok Pinjaman</small>
                <h5>Rp {{ number_format($loan->amount, 0, ',', '.') }}</h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <small class="text-muted">Total Bunga</small>
                <h5 class="text-info">Rp {{ number_format($loan->total_interest, 0, ',', '.') }}</h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <small class="text-muted">Biaya Admin</small>
                <h5 class="text-warning">Rp {{ number_format($loan->admin_fee ?? 0, 0, ',', '.') }}</h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-primary">
            <div class="card-body">
                <small class="text-muted">Uang Cair Bersih</small>
                <h5 class="text-primary">Rp {{ number_format($loan->disbursed_amount, 0, ',', '.') }}</h5>
            </div>
        </div>
    </div>
</div>

@elseif($loan->isRejected())
<!-- Info Rejected -->
<div class="alert alert-danger mb-4">
    <h5 class="mb-2"><i class="bi bi-x-circle me-2"></i>Pinjaman Ditolak</h5>
    <p class="mb-0">Pengajuan pinjaman ini telah ditolak dan tidak akan diproses lebih lanjut.</p>
</div>

@else
<!-- Loan Info - Dengan Sistem Bunga Potong di Awal -->
@if($loan->isUpfrontInterest())
<div class="alert alert-success mb-4">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h5 class="mb-2"><i class="bi bi-check-circle me-2"></i>Sistem Bunga Potong di Awal</h5>
            <p class="mb-0">Bunga sudah lunas saat pencairan. Cicilan bulanan hanya berupa pokok pinjaman (fixed).</p>
        </div>
        <div class="col-md-4 text-end">
            <small class="text-muted">Total Bunga Dibayar:</small>
            <h4 class="text-success mb-0">Rp {{ number_format($loan->total_interest, 0, ',', '.') }}</h4>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-center border-primary">
            <div class="card-body">
                <small class="text-muted">Cicilan Bulanan Tetap</small>
                <h4 class="text-primary">Rp {{ number_format($loan->monthly_installment, 0, ',', '.') }}</h4>
                <small class="text-success"><i class="bi bi-check"></i> Hanya Pokok</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <small class="text-muted">Uang Cair Bersih</small>
                <h4 class="text-success">Rp {{ number_format($loan->disbursed_amount, 0, ',', '.') }}</h4>
                <small class="text-muted">Pokok - Bunga</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <small class="text-muted">Total Bunga</small>
                <h4 class="text-info">Rp {{ number_format($loan->total_interest, 0, ',', '.') }}</h4>
                <small class="text-success"><i class="bi bi-check-circle"></i> Sudah Lunas</small>
            </div>
        </div>
    </div>
</div>
@else
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <small class="text-muted">Angsuran Pokok/Bulan</small>
                <h4 class="text-primary">Rp {{ number_format($loan->monthly_principal, 0, ',', '.') }}</h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <small class="text-muted">Bunga Bulan Ini</small>
                <h4 class="text-info">Rp {{ number_format($loan->monthly_interest, 0, ',', '.') }}</h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <small class="text-muted">Total Angsuran</small>
                <h4 class="text-success">Rp {{ number_format($loan->monthly_payment, 0, ',', '.') }}</h4>
            </div>
        </div>
    </div>
</div>
@endif
@endif

@if(!$loan->isPending() && !$loan->isRejected())
<!-- Payment History -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history me-2"></i>Riwayat Pembayaran</span>
        @if($loan->status === 'active' && Auth::user()->isAdmin())
        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#repayModal">
            <i class="bi bi-cash me-1"></i> Bayar Angsuran
        </button>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal</th>
                        <th class="text-end">Pokok</th>
                        <th class="text-end">Bunga</th>
                        <th class="text-end">Total</th>
                        <th>Catatan</th>
                        @if(Auth::user()->isAdmin())
                        <th class="text-center">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($loan->transactions as $trx)
                    <tr>
                        <td>{{ $trx->transaction_date->format('d/m/Y') }}</td>
                        <td class="text-end">Rp {{ number_format($trx->amount_principal, 0, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format($trx->amount_interest, 0, ',', '.') }}</td>
                        <td class="text-end fw-medium">Rp {{ number_format($trx->total_amount, 0, ',', '.') }}</td>
                        <td><small class="text-muted">{{ $trx->notes }}</small></td>
                        @if(Auth::user()->isAdmin())
                        <td class="text-center">
                            @if($trx->type === 'loan_repayment')
                            <form action="{{ route('transactions.destroy', $trx) }}" method="POST" class="d-inline delete-repayment-form">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus & Rollback Hutang">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            @else
                            <span class="text-muted" title="Transaksi sistem">-</span>
                            @endif
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            Belum ada pembayaran
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if($loan->transactions->count() > 0)
                <tfoot class="table-light">
                    <tr class="fw-bold">
                        <td>TOTAL</td>
                        <td class="text-end">Rp {{ number_format($loan->transactions->sum('amount_principal'), 0, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format($loan->transactions->sum('amount_interest'), 0, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format($loan->transactions->sum('total_amount'), 0, ',', '.') }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endif

<!-- Modal Bayar -->
@if($loan->status === 'active')
<div class="modal fade" id="repayModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('loans.repay', $loan) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-cash me-2"></i>Bayar Angsuran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <small>
                            Sisa Pokok: <strong>Rp {{ number_format($loan->remaining_principal, 0, ',', '.') }}</strong>
                        </small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-required">Pokok</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" name="amount_principal" class="form-control" required min="0" max="{{ $loan->remaining_principal }}" value="{{ round($loan->monthly_principal) }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-required">Bunga</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" name="amount_interest" class="form-control" required min="0" value="{{ round($loan->monthly_interest) }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-required">Tanggal</label>
                        <input type="date" name="transaction_date" class="form-control" required value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Catatan</label>
                        <input type="text" name="notes" class="form-control" placeholder="Opsional">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-check me-1"></i> Bayar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Konfirmasi hapus transaksi dengan SweetAlert custom
    document.querySelectorAll('.delete-repayment-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            Swal.fire({
                title: 'Hapus Pembayaran?',
                text: 'Sisa hutang akan ditambah kembali sesuai nominal ini!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
    
    // Konfirmasi ACC / Cairkan pinjaman
    document.querySelectorAll('.approve-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            Swal.fire({
                title: 'Cairkan Pinjaman?',
                html: 'Dana akan dicairkan ke anggota.<br><strong>Pastikan SPJ sudah ditandatangani!</strong>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-check-circle me-1"></i> Ya, Cairkan!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Memproses...',
                        html: 'Sedang mencairkan dana pinjaman',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    form.submit();
                }
            });
        });
    });
    
    // Konfirmasi Tolak pinjaman
    document.querySelectorAll('.reject-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            Swal.fire({
                title: 'Tolak Pinjaman?',
                text: 'Pengajuan pinjaman ini akan ditolak dan tidak bisa dikembalikan.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-x-circle me-1"></i> Ya, Tolak!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
});
</script>
@endpush
