@extends('layouts.app')

@section('title', 'Buat Pinjaman Baru - Koperasi')
@section('breadcrumb')
<a href="{{ route('loans.index') }}" class="text-decoration-none">Pinjaman</a> / Buat Baru
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
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-cash me-2"></i>Buat Pinjaman Baru
            </div>
            <div class="card-body">
                <form action="{{ route('loans.store') }}" method="POST" id="loanForm">
                    @csrf
                    
                    <div class="mb-3">
                        <label class="form-label form-label-required">Pilih Anggota</label>
                        @if($member)
                            <input type="hidden" name="member_id" value="{{ $member->id }}">
                            <div class="alert alert-info">
                                <strong>{{ $member->name }}</strong> ({{ $member->nik }})
                                <br><small>Departemen: {{ $member->dept }}</small>
                            </div>
                        @else
                            <select name="member_id" id="select-member" class="form-select @error('member_id') is-invalid @enderror" required>
                                <option value="">-- Pilih Anggota --</option>
                            </select>
                            <small class="text-muted">Ketik nama atau NIK untuk mencari. Hanya menampilkan anggota tanpa pinjaman aktif.</small>
                            @error('member_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        @endif
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label form-label-required">Tanggal Pengajuan</label>
                            <input type="date" name="application_date" class="form-control @error('application_date') is-invalid @enderror" 
                                   value="{{ old('application_date', date('Y-m-d')) }}" required>
                            @error('application_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label form-label-required">Pokok Pinjaman</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" name="amount" id="amount" 
                                       class="form-control input-currency @error('amount') is-invalid @enderror" 
                                       value="{{ old('amount', 1000000) }}" required min="100000" step="100000">
                            </div>
                            @error('amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-required">Durasi (Bulan)</label>
                            <select name="duration" id="duration" class="form-select @error('duration') is-invalid @enderror" required>
                                @foreach([6, 10, 12, 18, 24, 36] as $dur)
                                    <option value="{{ $dur }}" {{ old('duration', 12) == $dur ? 'selected' : '' }}>{{ $dur }} Bulan</option>
                                @endforeach
                            </select>
                            @error('duration')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label form-label-required">Total Bunga</label>
                            <div class="input-group">
                                <input type="number" name="interest_rate" id="interest_rate" 
                                       class="form-control @error('interest_rate') is-invalid @enderror" 
                                       value="{{ old('interest_rate', $defaultInterestRate ?? 1) }}" required min="0" step="0.1">
                                <span class="input-group-text">%</span>
                            </div>
                            @error('interest_rate')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Loan Simulation - Sistem Bunga Potong di Awal -->
                    <div class="card bg-light mb-4">
                        <div class="card-header bg-success text-white">
                            <i class="bi bi-calculator me-2"></i>Simulasi Pinjaman - Sistem Bunga Potong di Awal + Admin Fee
                        </div>
                        <div class="card-body">
                            <div class="row text-center mb-3">
                                <div class="col-md-2">
                                    <small class="text-muted">Pokok Pinjaman</small>
                                    <h5 class="text-primary" id="simPokok">Rp 0</h5>
                                </div>
                                <div class="col-md-2">
                                    <small class="text-muted">Total Bunga</small>
                                    <h5 class="text-danger" id="simTotalBunga">Rp 0</h5>
                                    <small class="text-muted">(Pokok × Bunga%)</small>
                                </div>
                                <div class="col-md-2">
                                    <small class="text-muted">Admin Fee (1%)</small>
                                    <h5 class="text-warning" id="simAdminFee">Rp 0</h5>
                                    <small class="text-muted">(Pokok × 1%)</small>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Uang Cair Bersih</small>
                                    <h5 class="text-success fw-bold" id="simUangCair">Rp 0</h5>
                                    <small class="text-muted">(Pokok - Bunga - Admin)</small>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Cicilan/Bulan</small>
                                    <h5 class="text-info" id="simCicilan">Rp 0</h5>
                                    <small class="text-muted">(Fixed, tanpa bunga)</small>
                                </div>
                            </div>
                            <div class="alert alert-warning mb-0 py-2">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Sistem Bunga Potong di Awal + Admin Fee 1%:</strong>
                                Bunga dan biaya admin dipotong langsung saat pencairan. Cicilan bulanan hanya berupa pokok pinjaman.
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between">
                        <a href="{{ $member ? route('members.show', $member) : route('loans.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check me-1"></i> Buat Pinjaman
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<!-- jQuery (diperlukan untuk Select2) -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const amountInput = document.getElementById('amount');
    const durationSelect = document.getElementById('duration');
    const interestInput = document.getElementById('interest_rate');
    
    function formatCurrency(value) {
        return new Intl.NumberFormat('id-ID').format(value);
    }
    
    function calculateSimulation() {
        const pokok = parseInt(amountInput.value) || 0;
        const tenor = parseInt(durationSelect.value) || 1;
        const bungaPersen = parseFloat(interestInput.value) || 0;
        
        // Sistem Bunga Potong di Awal + Admin Fee 1%
        // Total Bunga = Pokok × (Bunga% / 100) × Tenor
        const totalBunga = Math.round(pokok * (bungaPersen / 100));
        
        // Admin Fee = Pokok × 1% (Fixed)
        const adminFee = Math.round(pokok * 0.01);
        
        // Uang Cair = Pokok - Total Bunga - Admin Fee
        const uangCair = pokok - totalBunga - adminFee;
        
        // Cicilan Bulanan = Pokok / Tenor (fixed, tanpa bunga)
        const cicilanBulanan = Math.round(pokok / tenor);
        
        document.getElementById('simPokok').textContent = 'Rp ' + formatCurrency(pokok);
        document.getElementById('simTotalBunga').textContent = 'Rp ' + formatCurrency(totalBunga);
        document.getElementById('simAdminFee').textContent = 'Rp ' + formatCurrency(adminFee);
        document.getElementById('simUangCair').textContent = 'Rp ' + formatCurrency(uangCair);
        document.getElementById('simCicilan').textContent = 'Rp ' + formatCurrency(cicilanBulanan);
    }
    
    amountInput.addEventListener('input', calculateSimulation);
    durationSelect.addEventListener('change', calculateSimulation);
    interestInput.addEventListener('input', calculateSimulation);
    
    // Initial calculation
    calculateSimulation();

    // Select2 AJAX untuk Pilih Anggota
    @if(!$member)
    $('#select-member').select2({
        theme: 'bootstrap-5',
        placeholder: 'Ketik Nama atau NIK...',
        allowClear: true,
        minimumInputLength: 1,
        ajax: {
            url: '{{ route("members.search") }}',
            dataType: 'json',
            delay: 300,
            data: function(params) {
                return {
                    term: params.term
                };
            },
            processResults: function(data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        language: {
            inputTooShort: function() {
                return 'Ketik minimal 1 karakter...';
            },
            noResults: function() {
                return 'Anggota tidak ditemukan';
            },
            searching: function() {
                return 'Mencari...';
            }
        }
    });
    @endif
});
</script>
@endpush
