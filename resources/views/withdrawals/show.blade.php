@extends('layouts.app')

@section('title', 'Detail Penarikan Saldo - Koperasi')
@section('breadcrumb')
<a href="{{ route('withdrawals.index') }}" class="text-decoration-none">Penarikan Saldo</a> / Detail
@endsection

@section('content')
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card h-100 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <span class="badge {{ $withdrawal->status_badge_class }} fs-6 me-3">{{ $withdrawal->status_label }}</span>
                    <h4 class="mb-0 text-gray-800">#WD{{ str_pad($withdrawal->id, 4, '0', STR_PAD_LEFT) }}</h4>
                </div>
                
                <h5 class="mb-1">
                    @if(Auth::user()->isAdmin())
                    <a href="{{ route('members.show', $withdrawal->member) }}" class="text-decoration-none fw-bold text-primary">
                        {{ $withdrawal->member->name }}
                    </a>
                    @else
                    <span class="fw-bold">{{ $withdrawal->member->name }}</span>
                    @endif
                </h5>
                <p class="text-muted mb-4 border-bottom pb-3">
                    <i class="bi bi-person-badge me-1"></i> {{ $withdrawal->member->nik }} &nbsp;|&nbsp; 
                    <i class="bi bi-building me-1"></i> {{ $withdrawal->member->dept }}
                </p>
                
                <div class="row g-3">
                    <div class="col-md-4">
                        <small class="text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Jumlah Penarikan</small>
                        <h4 class="text-danger fw-bold mt-1">Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</h4>
                    </div>
                    <div class="col-md-4">
                        <small class="text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Tanggal Pengajuan</small>
                        <h5 class="mt-1 text-dark">{{ $withdrawal->request_date->translatedFormat('d F Y') }}</h5>
                    </div>
                    @if($withdrawal->approved_date)
                    <div class="col-md-4">
                        <small class="text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Tanggal Disetujui</small>
                        <h5 class="mt-1 text-success">{{ $withdrawal->approved_date->translatedFormat('d F Y') }}</h5>
                    </div>
                    @endif
                </div>

                @if($withdrawal->notes)
                <div class="mt-4 pt-3 border-top bg-light p-3 rounded">
                    <small class="text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Keterangan / Catatan</small>
                    <p class="mb-0 mt-1 text-dark fst-italic">"{{ $withdrawal->notes }}"</p>
                </div>
                @endif

                @if($withdrawal->rejection_reason)
                <div class="alert alert-danger mt-3 mb-0 d-flex align-items-start">
                    <i class="bi bi-x-circle-fill fs-4 me-3"></i>
                    <div>
                        <strong class="d-block mb-1">Alasan Penolakan:</strong>
                        <span>{{ $withdrawal->rejection_reason }}</span>
                    </div>
                </div>
                @endif
                
                @if(Auth::user()->isAdmin() && $withdrawal->status === 'pending')
                <div class="mt-4 pt-3 border-top d-flex gap-2">
                    <button type="button" class="btn btn-success" onclick="confirmApprove()">
                        <i class="bi bi-check-circle me-1"></i> Setujui Penarikan
                    </button>
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                        <i class="bi bi-x-circle me-1"></i> Tolak
                    </button>
                    <button type="button" class="btn btn-outline-secondary ms-auto" data-bs-toggle="modal" data-bs-target="#printPreviewModal" onclick="loadPrintPreview()">
                        <i class="bi bi-printer me-1"></i> Cetak
                    </button>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm border-primary border-top border-4">
            <div class="card-body text-center py-4">
                <div class="mb-2">
                    <span class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle d-inline-block mb-2">
                        <i class="bi bi-wallet2 fs-2"></i>
                    </span>
                </div>
                <small class="text-muted text-uppercase fw-bold letter-spacing-1">Saldo Simpanan Saat Ini</small>
                <h2 class="text-primary fw-bold mt-2 mb-0">Rp {{ number_format($withdrawal->member->savings_balance, 0, ',', '.') }}</h2>
                <small class="text-muted d-block mt-2">Update Terakhir: {{ now()->translatedFormat('d M Y') }}</small>
            </div>
        </div>

        <div class="d-grid gap-2 mt-3">
             <a href="{{ route('withdrawals.index') }}" class="btn btn-secondary btn-lg">
                <i class="bi bi-arrow-left me-2"></i> Kembali ke Daftar
            </a>
        </div>
    </div>
</div>

@if(Auth::user()->isAdmin() && $withdrawal->status === 'pending')
<form id="approveForm" action="{{ route('withdrawals.approve', $withdrawal) }}" method="POST" class="d-none">
    @csrf
</form>

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
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Anda akan menolak pengajuan penarikan sebesar <strong>Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</strong>.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Alasan Penolakan <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="Contoh: Saldo tidak mencukupi, Tanda tangan tidak cocok..."></textarea>
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
        html: `Penarikan saldo dari <strong>{{ $withdrawal->member->name }}</strong> sebesar <strong>Rp ${formattedAmount}</strong> akan disetujui.<br><small class='text-muted'>Saldo simpanan anggota akan otomatis berkurang.</small>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754', // Bootstrap success color
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-check-lg"></i> Ya, Setujui!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Memproses...',
                text: 'Mohon tunggu sebentar.',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            document.getElementById('approveForm').submit();
        }
    });
}
</script>
@endpush