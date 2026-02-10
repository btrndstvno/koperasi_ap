@extends('layouts.app')

@section('title', 'Ajukan Penarikan Saldo - Koperasi')
@section('breadcrumb')
<a href="{{ route('withdrawals.index') }}" class="text-decoration-none">Penarikan Saldo</a> / Ajukan
@endsection

@push('styles')
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    .select2-container--bootstrap-5 .select2-selection {
        min-height: 38px;
    }
</style>
@endpush

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-wallet2 me-2"></i>Ajukan Penarikan Saldo
            </div>
            <div class="card-body">
                <form action="{{ route('withdrawals.store') }}" method="POST" id="withdrawalForm">
                    @csrf
                    
                    <div class="mb-3">
                        <label class="form-label form-label-required">Anggota</label>
                        @if($member)
                            <input type="hidden" name="member_id" value="{{ $member->id }}">
                            <div class="alert alert-info mb-2">
                                <strong>{{ $member->name }}</strong> ({{ $member->nik }})
                                <br><small>Departemen: {{ $member->dept }}</small>
                            </div>
                            <div class="alert alert-success mb-0">
                                <i class="bi bi-wallet me-2"></i>
                                <strong>Saldo Simpanan Saat Ini:</strong> 
                                <span class="fs-5 fw-bold">Rp {{ number_format($member->savings_balance, 0, ',', '.') }}</span>
                            </div>
                        @else
                            <select name="member_id" id="select-member" class="form-select @error('member_id') is-invalid @enderror" required>
                                <option value="">-- Pilih Anggota --</option>
                            </select>
                            <small class="text-muted">Ketik nama atau NIK untuk mencari.</small>
                            @error('member_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        @endif
                    </div>

                    <div class="mb-3">
                        <label class="form-label form-label-required">Jumlah Penarikan</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" name="amount_display" id="amountDisplay"
                                   class="form-control input-currency @error('amount') is-invalid @enderror" 
                                   value="{{ old('amount') ? number_format(old('amount'), 0, ',', '.') : '' }}" 
                                   required placeholder="0">
                            <input type="hidden" name="amount" id="amount" value="{{ old('amount') }}">
                        </div>
                        @if($member)
                            <small class="text-muted">Maksimal penarikan: Rp {{ number_format($member->savings_balance, 0, ',', '.') }}</small>
                        @endif
                        @error('amount')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Keterangan (Opsional)</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Alasan atau keterangan penarikan...">{{ old('notes') }}</textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="{{ route('withdrawals.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-send me-1"></i> Ajukan Penarikan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Select2 for member search (Admin only)
    @if(!$member)
    $('#select-member').select2({
        theme: 'bootstrap-5',
        placeholder: 'Ketik nama atau NIK...',
        minimumInputLength: 2,
        ajax: {
            url: '{{ route("members.search") }}',
            dataType: 'json',
            delay: 300,
            data: function(params) {
                return { search: params.term };
            },
            processResults: function(data) {
                return {
                    results: data.map(function(member) {
                        return {
                            id: member.id,
                            text: member.name + ' (' + member.nik + ') - Saldo: Rp ' + new Intl.NumberFormat('id-ID').format(member.savings_balance)
                        };
                    })
                };
            }
        }
    });
    @endif

    document.getElementById('withdrawalForm').addEventListener('submit', function(e) {
        const displayVal = document.getElementById('amountDisplay').value;
        // Hapus semua karakter non-digit (titik, koma, spasi, dll)
        const amount = parseInt(displayVal.replace(/\D/g, '')) || 0;
        
        if (amount < 10000) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Jumlah Tidak Valid',
                text: 'Jumlah penarikan minimal Rp 10.000'
            });
            return false;
        }
        
        // Isi input hidden dengan angka murni
        document.getElementById('amount').value = amount;
    });
});
</script>
@endpush
