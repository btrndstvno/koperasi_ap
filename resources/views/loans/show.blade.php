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
                
                @if(Auth::user()->isAdmin())
                <div class="mt-3 pt-3 border-top">
                    <!-- Changed to Modal Trigger -->
                    <button onclick="showPrintModal('{{ route('loans.print', $loan) }}?modal=1')" type="button" class="btn btn-outline-primary me-2">
                        <i class="bi bi-printer me-1"></i> Cetak SPJ
                    </button>
                    
                    @if($loan->isPending())
                    <button type="button" class="btn btn-warning me-2 fw-medium" data-bs-toggle="modal" data-bs-target="#editModal">
                        <i class="bi bi-pencil me-1"></i> Ubah Nominal
                    </button>
                    <button type="button" class="btn btn-success fw-bold" data-bs-toggle="modal" data-bs-target="#approveModal">
                        <i class="bi bi-check-circle me-1"></i> ACC / Cairkan
                    </button>
                    <form action="{{ route('loans.reject', $loan) }}" method="POST" class="d-inline reject-form ms-2">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="bi bi-x-circle me-1"></i> Tolak
                        </button>
                    </form>
                    @endif
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

<!-- Modal Edit Nominal -->
@if($loan->status === 'pending')
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('loans.update-amount', $loan) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Ubah Nominal Pencairan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <small class="text-muted d-block mb-1">Plafond Awal</small>
                        <strong class="fs-5">Rp {{ number_format($loan->amount, 0, ',', '.') }}</strong>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label form-label-required">Nominal Baru (Rp)</label>
                        <input type="text" id="edit_input" class="form-control form-control-lg text-end" value="{{ number_format($loan->amount, 0, ',', '.') }}" required>
                        <input type="hidden" name="amount" id="edit_amount_hidden" value="{{ $loan->amount }}">
                        <small class="text-muted">Masukkan angka saja, titik otomatis.</small>
                    </div>

                    <!-- Live Calculation Preview -->
                    <div class="p-3 bg-light rounded">
                        <table class="table table-sm table-borderless mb-0 small">
                            <tr>
                                <td>Bunga ({{ $loan->interest_rate }}%)</td>
                                <td class="text-end text-danger fw-bold" id="edit_calc_interest">0</td>
                            </tr>
                            <tr>
                                <td>Admin (1%)</td>
                                <td class="text-end text-danger fw-bold" id="edit_calc_admin">0</td>
                            </tr>
                            <tr class="border-top">
                                <td><strong>Terima Bersih</strong></td>
                                <td class="text-end fw-bold text-success fs-6" id="edit_calc_disburse">0</td>
                            </tr>
                            <tr><td colspan="2" class="pt-2"></td></tr>
                            <tr class="table-info rounded">
                                <td class="ps-2"><strong>Angsuran/Bln</strong></td>
                                <td class="text-end fw-bold pe-2" id="edit_calc_installment">0</td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
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

<!-- Modal Approve -->
@if($loan->isPending())
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('loans.approve', $loan) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i>Persetujuan Pencairan Dana</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Pastikan SPJ sudah ditandatangani dan nominal sudah sesuai perjanjian.
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted">Uang yang akan dicairkan</label>
                        <h3 class="text-success">Rp {{ number_format($loan->disbursed_amount, 0, ',', '.') }}</h3>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-required">Tanggal Pencairan</label>
                        <input type="date" name="approved_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        <small class="text-muted">Tanggal ini akan dicatat sebagai tanggal mulai pinjaman.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg me-1"></i> Cairkan Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<!-- Print Modal -->
<div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-printer me-2"></i>Pratinjau Cetak SPJ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="height: 80vh; background: #525659;"> <!-- Dark background like PDF viewer -->
                <iframe id="printFrame" src="" style="width: 100%; height: 100%; border: none;"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" onclick="printFrameContent()">
                    <i class="bi bi-printer me-1"></i> Cetak Sekarang
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function showPrintModal(url) {
    const modal = new bootstrap.Modal(document.getElementById('printModal'));
    const frame = document.getElementById('printFrame');
    frame.src = url;
    modal.show();
}

function printFrameContent() {
    const frame = document.getElementById('printFrame');
    // Call print on the iframe's window object
    frame.contentWindow.print();
}

// Format Number Function
const formatNumber = (num) => {
    return num.toString().replace(/\D/g, "").replace(/\B(?=(\d{3})+(?!\d))/g, ".");
};

const unformatNumber = (str) => {
    return parseInt(str.replace(/\./g, '')) || 0;
};

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
    
    // Live Calculation for Edit Modal
    const editInput = document.getElementById('edit_input');
    if(editInput) {
        const editHidden = document.getElementById('edit_amount_hidden');
        const rate = {{ $loan->interest_rate ?? 0 }};
        const duration = {{ $loan->duration ?? 0 }};
        
        const calculateEdit = () => {
            let val = unformatNumber(editInput.value);
            
            // Perhitungan
            let interest = val * (rate / 100);
            let admin = val * 0.01;
            let disburse = val - interest - admin;
            let installment = val / duration;

            document.getElementById('edit_calc_interest').innerText = '- Rp ' + formatNumber(Math.round(interest));
            document.getElementById('edit_calc_admin').innerText = '- Rp ' + formatNumber(Math.round(admin));
            document.getElementById('edit_calc_disburse').innerText = 'Rp ' + formatNumber(Math.round(disburse));
            document.getElementById('edit_calc_installment').innerText = 'Rp ' + formatNumber(Math.round(installment));
            
            // Update hidden input
            editHidden.value = val;
        };

        editInput.addEventListener('input', (e) => {
            let formatted = formatNumber(unformatNumber(e.target.value));
            if(e.target.value === '') formatted = '';
            editInput.value = formatted;
            calculateEdit();
        });
        
        // Initial Calculation
        calculateEdit();
        
        // Focus handler
        const editModal = document.getElementById('editModal');
        if(editModal) {
            editModal.addEventListener('shown.bs.modal', function () {
                editInput.focus();
            });
        }
    }

    // Konfirmasi ACC / Cairkan pinjaman (Simplified)
    document.querySelectorAll('.approve-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            Swal.fire({
                title: 'Cairkan Pinjaman?',
                html: 'Pastikan status SPJ sudah ditandatangani dan nominal sudah sesuai perjanjian.',
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
