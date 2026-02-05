@extends('layouts.app')

@section('title', 'Detail Anggota - ' . $member->name)
@section('breadcrumb')
<a href="{{ route('members.index') }}" class="text-decoration-none">Anggota</a> / {{ $member->name }}
@endsection

@section('content')
<!-- Member Header -->
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
                <p class="text-muted mb-2">
                    <code class="me-2">{{ $member->nik }}</code>
                    <span class="badge bg-secondary">{{ $member->dept }}</span>
                    @if($member->employee_status === 'monthly')
                        <span class="badge bg-primary">Bulanan</span>
                    @else
                        <span class="badge bg-warning text-dark">Mingguan</span>
                    @endif
                </p>
                <div class="d-flex gap-4">
                    <div>
                        <small class="text-muted d-block">Total Simpanan</small>
                        <span class="fs-5 fw-bold text-success">
                            Rp {{ number_format($member->savings_balance, 0, ',', '.') }}
                        </span>
                    </div>
                    <div>
                        <small class="text-muted d-block">Sisa Hutang</small>
                        <span class="fs-5 fw-bold text-danger">
                            Rp {{ number_format($member->total_debt, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-auto">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addSavingModal">
                        <i class="bi bi-plus-circle me-1"></i> Tambah Simpanan
                    </button>
                    @if(!$member->hasActiveLoan())
                    <a href="{{ route('loans.create', ['member_id' => $member->id]) }}" class="btn btn-primary">
                        <i class="bi bi-cash me-1"></i> Buat Pinjaman Baru
                    </a>
                    @endif
                    <a href="{{ route('members.edit', $member) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-pencil me-1"></i> Edit
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Active Loan Summary -->
@if($activeLoan)
<div class="card mb-4 border-danger">
    <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
        <span><i class="bi bi-credit-card me-2"></i>Pinjaman Aktif</span>
        @if($activeLoan->isUpfrontInterest())
        <span class="badge bg-warning text-dark">
            <i class="bi bi-check-circle me-1"></i>Bunga Sudah Lunas di Awal
        </span>
        @endif
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-2">
                <small class="text-muted">Pokok Pinjaman</small>
                <h5>Rp {{ number_format($activeLoan->amount, 0, ',', '.') }}</h5>
            </div>
            <div class="col-md-2">
                <small class="text-muted">Durasi</small>
                <h5>{{ $activeLoan->duration }} Bulan</h5>
            </div>
            <div class="col-md-2">
                <small class="text-muted">Bunga</small>
                <h5>{{ $activeLoan->interest_rate }}%</h5>
            </div>
            @if($activeLoan->isUpfrontInterest())
            <div class="col-md-2">
                <small class="text-muted">Total Bunga</small>
                <h5 class="text-info">Rp {{ number_format($activeLoan->total_interest, 0, ',', '.') }}</h5>
                <small class="text-success"><i class="bi bi-check-circle"></i> Sudah lunas</small>
            </div>
            <div class="col-md-2">
                <small class="text-muted">Uang Cair Bersih</small>
                <h5 class="text-success">Rp {{ number_format($activeLoan->disbursed_amount, 0, ',', '.') }}</h5>
            </div>
            @endif
            <div class="col-md-2">
                <small class="text-muted">Sisa Pokok</small>
                <h5 class="text-danger">Rp {{ number_format($activeLoan->remaining_principal, 0, ',', '.') }}</h5>
                <div class="progress mt-1" style="height: 8px;">
                    <div class="progress-bar bg-success" style="width: {{ $activeLoan->progress_percentage }}%"></div>
                </div>
                <small class="text-muted">{{ number_format($activeLoan->progress_percentage, 0) }}% lunas</small>
            </div>
        </div>
        <hr>
        <div class="d-flex justify-content-between align-items-center">
            <div>
                @if($activeLoan->isUpfrontInterest())
                <div class="alert alert-success mb-0 py-2 d-inline-block">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Cicilan Bulanan Tetap:</strong> 
                    Rp {{ number_format($activeLoan->monthly_installment, 0, ',', '.') }}
                    <span class="text-muted">(Hanya Pokok - Bunga sudah dipotong di awal)</span>
                </div>
                @else
                <small class="text-muted">Angsuran per Bulan: </small>
                <strong>Rp {{ number_format($activeLoan->monthly_principal, 0, ',', '.') }}</strong> (Pokok) + 
                <strong>Rp {{ number_format($activeLoan->monthly_interest, 0, ',', '.') }}</strong> (Bunga) = 
                <strong class="text-primary">Rp {{ number_format($activeLoan->monthly_payment, 0, ',', '.') }}</strong>
                @endif
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#repayLoanModal">
                <i class="bi bi-cash me-1"></i> Bayar Angsuran
            </button>
        </div>
    </div>
</div>
@endif

<!-- Tabs -->
<ul class="nav nav-tabs" id="memberTabs" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#loans-tab" type="button">
            <i class="bi bi-credit-card me-1"></i> Riwayat Pinjaman ({{ $member->loans->count() }})
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#savings-tab" type="button">
            <i class="bi bi-wallet2 me-1"></i> Mutasi Simpanan ({{ $savingTransactions->count() }})
        </button>
    </li>
</ul>

<div class="tab-content mt-3">
    <!-- Tab Riwayat Pinjaman -->
    <div class="tab-pane fade show active" id="loans-tab">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th class="text-end">Pokok Pinjaman</th>
                                <th class="text-center">Durasi</th>
                                <th class="text-center">Bunga</th>
                                <th class="text-end">Sisa Pokok</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($member->loans as $loan)
                            <tr>
                                <td>{{ $loan->created_at->format('d/m/Y') }}</td>
                                <td class="text-end">Rp {{ number_format($loan->amount, 0, ',', '.') }}</td>
                                <td class="text-center">{{ $loan->duration }} bln</td>
                                <td class="text-center">{{ $loan->interest_rate }}%</td>
                                <td class="text-end text-danger">Rp {{ number_format($loan->remaining_principal, 0, ',', '.') }}</td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $loan->status_badge }} {{ $loan->status === 'pending' ? 'text-dark' : '' }}">
                                        {{ $loan->status_label }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('loans.show', $loan) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Belum ada riwayat pinjaman</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Mutasi Simpanan -->
    <div class="tab-pane fade" id="savings-tab">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Keterangan</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Kredit</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($savingTransactions as $trx)
                            <tr>
                                <td>{{ $trx->transaction_date->format('d/m/Y') }}</td>
                                <td>
                                    <span class="badge bg-{{ $trx->type_badge_color }} me-1">{{ $trx->type_label }}</span>
                                    {{ $trx->notes }}
                                </td>
                                <td class="text-end">
                                    @if($trx->type === 'saving_deposit')
                                        <span class="text-success">+ Rp {{ number_format($trx->amount_saving, 0, ',', '.') }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($trx->type === 'saving_withdraw')
                                        <span class="text-danger">- Rp {{ number_format($trx->amount_saving, 0, ',', '.') }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if(!in_array($trx->type, ['interest_revenue', 'admin_fee', 'loan_disbursement']))
                                    <form action="{{ route('transactions.destroy', $trx) }}" method="POST" class="d-inline delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus & Rollback Saldo">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    @else
                                    <span class="text-muted" title="Transaksi sistem tidak bisa dihapus">-</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Belum ada mutasi simpanan</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Simpanan -->
<div class="modal fade" id="addSavingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('members.add-saving', $member) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Tambah Simpanan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label form-label-required">Nominal</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" name="amount" class="form-control input-currency" required min="1000" step="1000">
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
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check me-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Bayar Angsuran -->
@if($activeLoan)
<div class="modal fade" id="repayLoanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('loans.repay', $activeLoan) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-cash me-2"></i>Bayar Angsuran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if($activeLoan->isUpfrontInterest())
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i>
                        <strong>Sistem Bunga Potong di Awal</strong><br>
                        <small>Bunga sudah lunas. Cicilan hanya berupa pokok pinjaman.</small>
                    </div>
                    @endif
                    <div class="alert alert-info">
                        <small>
                            Sisa Pokok: <strong>Rp {{ number_format($activeLoan->remaining_principal, 0, ',', '.') }}</strong><br>
                            Cicilan Pokok/Bulan: <strong>Rp {{ number_format($activeLoan->monthly_installment > 0 ? $activeLoan->monthly_installment : $activeLoan->monthly_principal, 0, ',', '.') }}</strong>
                        </small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-required">Pokok</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" name="amount_principal" class="form-control input-currency" required min="0" max="{{ $activeLoan->remaining_principal }}" value="{{ $activeLoan->monthly_installment > 0 ? round($activeLoan->monthly_installment) : round($activeLoan->monthly_principal) }}">
                        </div>
                    </div>
                    @if(!$activeLoan->isUpfrontInterest())
                    <div class="mb-3">
                        <label class="form-label form-label-required">Bunga</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" name="amount_interest" class="form-control input-currency" required min="0" value="{{ round($activeLoan->monthly_interest) }}">
                        </div>
                    </div>
                    @else
                    <input type="hidden" name="amount_interest" value="0">
                    @endif
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
{{-- Script khusus member detail dapat ditambahkan di sini jika perlu --}}
{{-- Note: Delete confirmation sudah dihandle secara global di layout app.blade.php --}}
@endpush
