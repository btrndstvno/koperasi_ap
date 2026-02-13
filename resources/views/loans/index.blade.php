@extends('layouts.app')

@section('title', 'Daftar Pinjaman')

@section('content')
<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="fw-bold text-dark">Daftar Pinjaman</h2>
        <p class="text-muted">Kelola pengajuan dan pembayaran pinjaman anggota</p>
    </div>
    <div class="col-md-6 text-end">
        @if(Auth::user()->isMember() && !Auth::user()->member->activeLoans()->exists())
        <a href="{{ route('loans.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Ajukan Pinjaman
        </a>
        @endif
    </div>
</div>

<!-- Statistik Ringkas (Optional) -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0 text-white-50">Total Pinjaman Aktif</h6>
                        <h3 class="fw-bold mb-0">Rp {{ number_format($totalActive ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <div class="fs-1 text-white-50">
                        <i class="bi bi-wallet2"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0 text-muted">Jumlah Peminjam</h6>
                        <h3 class="fw-bold mb-0">{{ $countActive ?? 0 }}</h3>
                    </div>
                    <div class="fs-1 text-primary text-opacity-25">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <form action="{{ route('loans.index') }}" method="GET" class="row g-2 flex-grow-1">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari NIK atau Nama..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending (Menunggu)</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active (Berjalan)</option>
                        <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid (Lunas)</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected (Ditolak)</option>
                    </select>
                </div>
            </form>
            <div>
                <!-- Export Excel Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
                    </button>
                    <button type="button" class="btn btn-secondary ms-2" onclick="showPrintModal('{{ route('loans.print-form') }}?modal=1')">
                        <i class="fas fa-print"></i> Print Form Pengajuan
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('exports.loans') }}"><i class="bi bi-download me-2"></i>Semua Data</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('exports.loans', ['status' => 'pending']) }}"><i class="bi bi-hourglass-split me-2 text-warning"></i>Hanya Pending</a></li>
                        <li><a class="dropdown-item" href="{{ route('exports.loans', ['status' => 'active']) }}"><i class="bi bi-check-circle me-2 text-primary"></i>Hanya Aktif</a></li>
                        <li><a class="dropdown-item" href="{{ route('exports.loans', ['status' => 'paid']) }}"><i class="bi bi-check-all me-2 text-success"></i>Hanya Lunas</a></li>
                        <li><a class="dropdown-item" href="{{ route('exports.loans', ['status' => 'rejected']) }}"><i class="bi bi-x-circle me-2 text-danger"></i>Hanya Ditolak</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Tanggal ID</th>
                        <th>Anggota</th>
                        <th class="text-end">Pokok Pinjaman</th>
                        <th class="text-center">Tenor</th>
                        <th class="text-center">Bunga</th>
                        <th class="text-end">Sisa Pokok</th>
                        <th>Progress</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($loans as $loan)
                    <tr>
                        <td class="ps-4">
                            <span class="fw-bold text-primary">#{{ $loan->id }}</span>
                            <div class="small text-muted">{{ $loan->created_at->format('d M Y') }}</div>
                        </td>
                        <td>
                            <div class="fw-bold">{{ $loan->member->name }}</div>
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
                                    <!-- <button onclick="showPrintModal('{{ route('loans.print', $loan) }}?modal=1')" type="button" class="btn btn-outline-secondary btn-action" title="Cetak SPJ">
                                        <i class="bi bi-printer"></i>
                                    </button> -->
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
                        <td colspan="9" class="text-center py-4 text-muted">
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

<!-- Print Modal -->
<div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-printer me-2"></i>Pratinjau Cetak SPJ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="height: 80vh; background: #525659;">
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
    frame.contentWindow.print();
}

document.addEventListener('DOMContentLoaded', function() {
    // Konfirmasi ACC / Cairkan pinjaman (Simple)
    document.querySelectorAll('.approve-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            Swal.fire({
                title: 'Cairkan Pinjaman?',
                html: 'Status akan berubah menjadi <strong>Aktif</strong> dan dana dicairkan.<br><small class="text-muted">Untuk ubah nominal, silakan masuk ke detail pinjaman.</small>',
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
});
</script>
@endpush
