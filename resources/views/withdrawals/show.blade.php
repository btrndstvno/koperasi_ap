@extends('layouts.app')

@section('title', 'Detail Penarikan Saldo - Koperasi')
@section('breadcrumb')
<a href="{{ route('withdrawals.index') }}" class="text-decoration-none">Penarikan Saldo</a> / Detail
@endsection

@section('content')
<!-- Withdrawal Header -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-8">
                <div class="d-flex align-items-center mb-3">
                    <span class="badge {{ $withdrawal->status_badge_class }} fs-6 me-3">{{ $withdrawal->status_label }}</span>
                    <h4 class="mb-0">#WD{{ str_pad($withdrawal->id, 4, '0', STR_PAD_LEFT) }}</h4>
                </div>
                
                <h5 class="mb-1">
                    @if(Auth::user()->isAdmin())
                    <a href="{{ route('members.show', $withdrawal->member) }}" class="text-decoration-none">
                        {{ $withdrawal->member->name }}
                    </a>
                    @else
                    {{ $withdrawal->member->name }}
                    @endif
                </h5>
                <p class="text-muted mb-3">
                    <code>{{ $withdrawal->member->nik }}</code> - {{ $withdrawal->member->dept }}
                </p>
                
                <div class="row">
                    <div class="col-md-4">
                        <small class="text-muted">Jumlah Penarikan</small>
                        <h4 class="text-danger">Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</h4>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Tanggal Pengajuan</small>
                        <h5>{{ $withdrawal->request_date->translatedFormat('d F Y') }}</h5>
                    </div>
                    @if($withdrawal->approved_date)
                    <div class="col-md-4">
                        <small class="text-muted">Tanggal Disetujui</small>
                        <h5>{{ $withdrawal->approved_date->translatedFormat('d F Y') }}</h5>
                    </div>
                    @endif
                </div>

                @if($withdrawal->notes)
                <div class="mt-3 pt-3 border-top">
                    <small class="text-muted">Keterangan</small>
                    <p class="mb-0">{{ $withdrawal->notes }}</p>
                </div>
                @endif

                @if($withdrawal->rejection_reason)
                <div class="alert alert-danger mt-3 mb-0">
                    <strong><i class="bi bi-x-circle me-1"></i> Alasan Penolakan:</strong>
                    <p class="mb-0">{{ $withdrawal->rejection_reason }}</p>
                </div>
                @endif
                
                @if(Auth::user()->isAdmin())
                <div class="mt-3 pt-3 border-top">
                    {{-- Print Preview Button --}}
                    <button type="button" class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#printPreviewModal" onclick="loadPrintPreview()">
                        <i class="bi bi-printer me-1"></i> Cetak Formulir
                    </button>
                    
                    @if($withdrawal->status === 'pending')
                    <button type="button" class="btn btn-success me-2" onclick="confirmApprove()">
                        <i class="bi bi-check-circle me-1"></i> Setujui
                    </button>
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                        <i class="bi bi-x-circle me-1"></i> Tolak
                    </button>
                    @endif
                </div>
                @endif
            </div>
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <small class="text-muted">Saldo Simpanan Saat Ini</small>
                        <h3 class="text-primary mb-0">Rp {{ number_format($withdrawal->member->savings_balance, 0, ',', '.') }}</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mb-3">
    <a href="{{ route('withdrawals.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
    </a>
</div>

@if(Auth::user()->isAdmin() && $withdrawal->status === 'pending')
<!-- Approve Form (Hidden) -->
<form id="approveForm" action="{{ route('withdrawals.approve', $withdrawal) }}" method="POST" class="d-none">
    @csrf
</form>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('withdrawals.reject', $withdrawal) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tolak Penarikan</h5>
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

<!-- Print Preview Modal -->
<div class="modal fade" id="printPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="height: 90vh;">
            <div class="modal-header">
                <h5 class="modal-title">Pratinjau Cetak Formulir</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="printFrame" src="" style="width: 100%; height: 100%; border: none;"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('printFrame').contentWindow.print()">
                    <i class="bi bi-printer me-1"></i> Cetak Sekarang
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function loadPrintPreview() {
    const frame = document.getElementById('printFrame');
    // Always reload to ensure fresh content
    frame.src = "{{ route('withdrawals.print', $withdrawal) }}?modal=1";
}
function confirmApprove() {
    const amount = {{ $withdrawal->amount }};
    const formattedAmount = new Intl.NumberFormat('id-ID').format(amount);
    Swal.fire({
        title: 'Setujui Penarikan?',
        html: `Penarikan saldo dari <strong>{{ $withdrawal->member->name }}</strong> sebesar <strong>Rp ${formattedAmount}</strong> akan disetujui dan saldo simpanan akan berkurang.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Setujui!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('approveForm').submit();
        }
    });
}
</script>
@endpush
