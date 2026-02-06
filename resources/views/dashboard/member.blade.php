@extends('layouts.app')

@section('title', 'Dashboard Member - Koperasi')
@section('breadcrumb', 'Dashboard Anggota')

@section('content')
@if(!$member)
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-2"></i>
    Akun Anda belum terhubung dengan data anggota koperasi. Silakan hubungi Admin.
</div>
@else

<!-- Member Info Header -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-auto">
                <div class="member-avatar">
                    {{ strtoupper(substr($member->name, 0, 2)) }}
                </div>
            </div>
            <div class="col">
                <h4 class="mb-1">{{ $member->name }}</h4>
                <p class="text-muted mb-0">
                    <code>{{ $member->nik }}</code> &bull; {{ $member->dept }}
                    @if($member->group_tag)
                    <span class="badge bg-primary ms-2">{{ $member->group_tag }}</span>
                    @endif
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-4 mb-4">
    <!-- Total Simpanan -->
    <div class="col-md-6">
        <div class="stat-card bg-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Total Simpanan Anda</div>
                    <div class="stat-value">Rp {{ number_format($member->savings_balance, 0, ',', '.') }}</div>
                    <small class="opacity-75">tes</small>
                </div>
                <i class="bi bi-wallet2 stat-icon"></i>
            </div>
        </div>
    </div>
    
    <!-- Status Pinjaman -->
    <div class="col-md-6">
        @if($activeLoan)
        <div class="stat-card bg-danger">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Sisa Pinjaman</div>
                    <div class="stat-value">Rp {{ number_format($activeLoan->remaining_principal, 0, ',', '.') }}</div>
                    <small class="opacity-75">Sisa {{ $activeLoan->remaining_installments }} bulan cicilan</small>
                </div>
                <i class="bi bi-credit-card stat-icon"></i>
            </div>
        </div>
        @elseif($pendingLoan)
        <div class="stat-card bg-warning">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Status Pengajuan</div>
                    <div class="stat-value">Menunggu ACC</div>
                    <small class="opacity-75">Rp {{ number_format($pendingLoan->amount, 0, ',', '.') }}</small>
                </div>
                <i class="bi bi-hourglass-split stat-icon"></i>
            </div>
        </div>
        @else
        <div class="stat-card bg-secondary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Status Pinjaman</div>
                    <div class="stat-value">Tidak Ada</div>
                    <small class="opacity-75">Anda bisa mengajukan pinjaman baru</small>
                </div>
                <i class="bi bi-check-circle stat-icon"></i>
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Active Loan Details -->
@if($activeLoan)
<div class="card mb-4 border-danger">
    <div class="card-header bg-danger text-white">
        <i class="bi bi-credit-card me-2"></i>Detail Pinjaman Aktif
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <small class="text-muted">Pokok Pinjaman</small>
                <h5>Rp {{ number_format($activeLoan->amount, 0, ',', '.') }}</h5>
            </div>
            <div class="col-md-2">
                <small class="text-muted">Tenor</small>
                <h5>{{ $activeLoan->duration }} Bulan</h5>
            </div>
            <div class="col-md-2">
                <small class="text-muted">Bunga</small>
                <h5>{{ $activeLoan->interest_rate }}%</h5>
            </div>
            <div class="col-md-3">
                <small class="text-muted">Cicilan/Bulan</small>
                <h5 class="text-primary">Rp {{ number_format($activeLoan->monthly_installment, 0, ',', '.') }}</h5>
            </div>
            <div class="col-md-2">
                <small class="text-muted">Progress</small>
                <div class="progress mt-1" style="height: 20px;">
                    <div class="progress-bar bg-success" style="width: {{ $activeLoan->progress_percentage }}%">
                        {{ number_format($activeLoan->progress_percentage, 0) }}%
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Pending Loan Info -->
@if($pendingLoan)
<div class="card mb-4 border-warning">
    <div class="card-header bg-warning text-dark">
        <i class="bi bi-hourglass-split me-2"></i>Pengajuan Pinjaman Menunggu Persetujuan
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <small class="text-muted">Nominal Pengajuan</small>
                <h5>Rp {{ number_format($pendingLoan->amount, 0, ',', '.') }}</h5>
            </div>
            <div class="col-md-4">
                <small class="text-muted">Tenor</small>
                <h5>{{ $pendingLoan->duration }} Bulan</h5>
            </div>
            <div class="col-md-4">
                <small class="text-muted">Tanggal Pengajuan</small>
                <h5>{{ $pendingLoan->created_at->format('d/m/Y H:i') }}</h5>
            </div>
        </div>
        <div class="alert alert-info mt-3 mb-0">
            <i class="bi bi-info-circle me-2"></i>
            Pengajuan Anda sedang dalam proses review oleh Admin. Mohon tunggu konfirmasi.
        </div>
    </div>
</div>
@endif

<!-- Loan Application Form (Only if no active or pending loan) -->
@if(!$activeLoan && !$pendingLoan)
<div class="card">
    <div class="card-header">
        <i class="bi bi-cash me-2"></i>Ajukan Pinjaman Baru
    </div>
    <div class="card-body">
        <form action="{{ route('loans.store') }}" method="POST" id="loanApplicationForm">
            @csrf
            <input type="hidden" name="member_id" value="{{ $member->id }}">
            <input type="hidden" name="duration" value="10">
            <input type="hidden" name="interest_rate" value="10">
            
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label form-label-required">Nominal Pinjaman</label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="number" name="amount" class="form-control" 
                               required min="100000" step="50000" 
                               placeholder="Contoh: 5000000"
                               value="{{ old('amount') }}">
                    </div>
                    <small class="text-muted">Minimal Rp 100.000</small>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tenor</label>
                    <input type="text" class="form-control" value="10 Bulan" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Bunga</label>
                    <input type="text" class="form-control" value="10%" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Estimasi Cicilan/Bulan</label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="text" class="form-control" id="estimasiCicilan" value="-" readonly>
                    </div>
                </div>
            </div>

            <div class="alert alert-info mt-4">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Sistem Bunga Potong di Awal:</strong> Bunga 10% akan dipotong langsung dari pokok pinjaman saat pencairan. Cicilan bulanan hanya berupa pokok saja.
            </div>

            <div class="text-end mt-4">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-send me-2"></i>Ajukan Pinjaman
                </button>
            </div>
        </form>
    </div>
</div>
@endif

@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const amountInput = document.querySelector('input[name="amount"]');
    const estimasiCicilan = document.getElementById('estimasiCicilan');
    
    if (amountInput && estimasiCicilan) {
        amountInput.addEventListener('input', function() {
            const amount = parseFloat(this.value) || 0;
            const tenor = 10;
            const cicilan = Math.ceil(amount / tenor);
            
            if (cicilan > 0) {
                estimasiCicilan.value = new Intl.NumberFormat('id-ID').format(cicilan);
            } else {
                estimasiCicilan.value = '-';
            }
        });
    }

    // Form submission confirmation
    const form = document.getElementById('loanApplicationForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const amount = parseFloat(document.querySelector('input[name="amount"]').value) || 0;
            
            Swal.fire({
                title: 'Konfirmasi Pengajuan',
                html: `Anda akan mengajukan pinjaman sebesar:<br><strong>Rp ${new Intl.NumberFormat('id-ID').format(amount)}</strong><br><small class="text-muted">Tenor: 10 Bulan | Bunga: 10%</small>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Ajukan!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    }
});
</script>
@endpush
