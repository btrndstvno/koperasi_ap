@extends('layouts.app')

@section('title', 'Penarikan Saldo')

@section('content')
<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="fw-bold text-dark">Penarikan Saldo</h2>
        <p class="text-muted">Kelola pengajuan penarikan saldo simpanan anggota</p>
    </div>
    <div class="col-md-6 text-end">
        @if(Auth::user()->isMember())
            @php
                $member = Auth::user()->member;
                $hasPending = $member ? $member->withdrawals()->where('status', 'pending')->exists() : false;
            @endphp
            @if(!$hasPending)
            <a href="{{ route('withdrawals.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Ajukan Penarikan
            </a>
            @endif
        @endif
    </div>
</div>

<!-- Statistik -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-warning text-dark">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Pengajuan Pending</h6>
                        <h3 class="fw-bold mb-0">{{ $pendingCount ?? 0 }}</h3>
                    </div>
                    <div class="fs-1 opacity-50">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0 text-white-50">Total Disetujui</h6>
                        <h3 class="fw-bold mb-0">Rp {{ number_format($totalApproved ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <div class="fs-1 text-white-50">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <form action="{{ route('withdrawals.index') }}" method="GET" class="row g-3">
            @if(Auth::user()->isAdmin())
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari NIK atau Nama..." value="{{ request('search') }}">
                </div>
            </div>
            @endif
            <div class="col-md-3">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Menunggu</option>
                    <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Disetujui</option>
                    <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Ditolak</option>
                </select>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">ID / Tanggal</th>
                        <th>Anggota</th>
                        <th class="text-end">Jumlah Penarikan</th>
                        <th class="text-center">Status</th>
                        <th>Catatan</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($withdrawals as $withdrawal)
                    <tr>
                        <td class="ps-4">
                            <span class="fw-bold text-primary">#WD{{ str_pad($withdrawal->id, 4, '0', STR_PAD_LEFT) }}</span>
                            <div class="small text-muted">{{ $withdrawal->request_date->format('d M Y') }}</div>
                        </td>
                        <td>
                            <div class="fw-bold">{{ $withdrawal->member->name }}</div>
                            <small class="text-muted">{{ $withdrawal->member->nik }} - {{ $withdrawal->member->dept }}</small>
                        </td>
                        <td class="text-end fw-bold text-danger">
                            Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}
                        </td>
                        <td class="text-center">
                            <span class="badge {{ $withdrawal->status_badge_class }}">
                                {{ $withdrawal->status_label }}
                            </span>
                        </td>
                        <td>
                            @if($withdrawal->notes)
                                <small class="text-muted">{{ Str::limit($withdrawal->notes, 30) }}</small>
                            @else
                                <small class="text-muted fst-italic">-</small>
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="{{ route('withdrawals.show', $withdrawal) }}" class="btn btn-sm btn-outline-primary" title="Detail">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if(Auth::user()->isAdmin() && $withdrawal->status === 'pending')
                                    <button type="button" class="btn btn-sm btn-success" title="Setujui" 
                                        onclick="confirmApprove({{ $withdrawal->id }}, '{{ $withdrawal->member->name }}', {{ $withdrawal->amount }})">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" title="Tolak"
                                        data-bs-toggle="modal" data-bs-target="#rejectModal{{ $withdrawal->id }}">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                @endif
                                @if($withdrawal->status === 'approved')
                                    <a href="{{ route('withdrawals.print', $withdrawal) }}?modal=1" class="btn btn-sm btn-outline-info" title="Cetak" target="_blank">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>

                    @if(Auth::user()->isAdmin() && $withdrawal->status === 'pending')
                    <!-- Reject Modal -->
                    <div class="modal fade" id="rejectModal{{ $withdrawal->id }}" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form action="{{ route('withdrawals.reject', $withdrawal) }}" method="POST">
                                    @csrf
                                    <div class="modal-header">
                                        <h5 class="modal-title">Tolak Penarikan #WD{{ str_pad($withdrawal->id, 4, '0', STR_PAD_LEFT) }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Tolak pengajuan penarikan dari <strong>{{ $withdrawal->member->name }}</strong> sebesar <strong>Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</strong>?</p>
                                        <div class="mb-3">
                                            <label class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                                            <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="Masukkan alasan penolakan..."></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-danger">Tolak Pengajuan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @endif
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            Belum ada pengajuan penarikan
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($withdrawals->hasPages())
    <div class="card-footer bg-white">
        {{ $withdrawals->links() }}
    </div>
    @endif
</div>

<!-- Approve Forms (Hidden) -->
@foreach($withdrawals->where('status', 'pending') as $withdrawal)
<form id="approveForm{{ $withdrawal->id }}" action="{{ route('withdrawals.approve', $withdrawal) }}" method="POST" class="d-none">
    @csrf
</form>
@endforeach

@endsection

@push('scripts')
<script>
function confirmApprove(id, name, amount) {
    const formattedAmount = new Intl.NumberFormat('id-ID').format(amount);
    Swal.fire({
        title: 'Setujui Penarikan?',
        html: `Penarikan saldo dari <strong>${name}</strong> sebesar <strong>Rp ${formattedAmount}</strong> akan disetujui dan saldo simpanan akan berkurang.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Setujui!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('approveForm' + id).submit();
        }
    });
}
</script>
@endpush
