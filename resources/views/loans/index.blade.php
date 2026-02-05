@extends('layouts.app')

@section('title', 'Daftar Pinjaman - Koperasi')
@section('breadcrumb', 'Pinjaman')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-cash-stack me-2"></i>Daftar Pinjaman</h4>
    <a href="{{ route('loans.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> Pinjaman Baru
    </a>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="stat-card bg-danger">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value">Rp {{ number_format($totalActive, 0, ',', '.') }}</div>
                    <div class="stat-label">Total Piutang Aktif</div>
                </div>
                <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card bg-info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value">{{ number_format($countActive) }}</div>
                    <div class="stat-label">Pinjaman Aktif</div>
                </div>
                <div class="stat-icon"><i class="bi bi-people"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form action="{{ route('loans.index') }}" method="GET" class="row g-3">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari NIK atau Nama anggota..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending (Menunggu)</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Lunas</option>
                    <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Ditolak</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-secondary w-100">
                    <i class="bi bi-filter me-1"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Loans Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Anggota</th>
                        <th class="text-end">Pokok Pinjaman</th>
                        <th class="text-center">Durasi</th>
                        <th class="text-center">Bunga</th>
                        <th class="text-end">Sisa Pokok</th>
                        <th class="text-center">Progress</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($loans as $loan)
                    <tr>
                        <td>
                            <a href="{{ route('members.show', $loan->member) }}" class="text-decoration-none">
                                <strong>{{ $loan->member->name }}</strong>
                            </a>
                            <br>
                            <small class="text-muted">{{ $loan->member->nik }} - {{ $loan->member->dept }}</small>
                        </td>
                        <td class="text-end">Rp {{ number_format($loan->amount, 0, ',', '.') }}</td>
                        <td class="text-center">{{ $loan->duration }} bln</td>
                        <td class="text-center">{{ $loan->interest_rate }}%</td>
                        <td class="text-end text-danger fw-medium">
                            Rp {{ number_format($loan->remaining_principal, 0, ',', '.') }}
                        </td>
                        <td style="width: 120px;">
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-success" style="width: {{ $loan->progress_percentage }}%">
                                    {{ number_format($loan->progress_percentage, 0) }}%
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-{{ $loan->status_badge }} {{ $loan->status === 'pending' ? 'text-dark' : '' }}">
                                {{ $loan->status_label }}
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('loans.show', $loan) }}" class="btn btn-outline-primary btn-action" title="Detail">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if($loan->status === 'pending')
                                    <a href="{{ route('loans.print', $loan) }}" class="btn btn-outline-secondary btn-action" title="Cetak SPJ" target="_blank">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <form action="{{ route('loans.approve', $loan) }}" method="POST" class="d-inline approve-form">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-success btn-action" title="ACC / Cairkan">
                                            <i class="bi bi-check-circle"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            Tidak ada data pinjaman
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($loans->hasPages())
    <div class="card-footer">
        {{ $loans->links() }}
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
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
                    // Show loading
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
});
</script>
@endpush
