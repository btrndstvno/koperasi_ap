@extends('layouts.app')

@section('title', 'Tambah Anggota Baru - Koperasi')
@section('breadcrumb')
<a href="{{ route('members.index') }}" class="text-decoration-none">Anggota</a> / Tambah Baru
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
                <i class="bi bi-person-plus me-2"></i>Tambah Anggota Baru
            </div>
            <div class="card-body">
                <form action="{{ route('members.store') }}" method="POST" id="memberForm">
                    @csrf
                    
                    {{-- Row 1: NIK & Nama --}}
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label form-label-required">NIK</label>
                            <input type="text" name="nik" class="form-control @error('nik') is-invalid @enderror" 
                                   value="{{ old('nik') }}" required placeholder="Contoh: 1234">
                            @error('nik')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-required">Nama Lengkap</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Row 2: Group Tag & Status --}}
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label form-label-required">Kategori / Group Tag</label>
                            <select name="group_tag" id="groupTagSelect" class="form-select @error('group_tag') is-invalid @enderror" required>
                                @foreach($group_tags as $tag)
                                    <option value="{{ $tag }}" {{ old('group_tag', 'Office') == $tag ? 'selected' : '' }}>{{ $tag }}</option>
                                @endforeach
                            </select>
                            @error('group_tag')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-required">Status Karyawan</label>
                            <select name="employee_status" class="form-select @error('employee_status') is-invalid @enderror" required>
                                <option value="monthly" {{ old('employee_status') == 'monthly' ? 'selected' : '' }}>Bulanan</option>
                                <option value="weekly" {{ old('employee_status') == 'weekly' ? 'selected' : '' }}>Mingguan</option>
                            </select>
                            @error('employee_status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Row 3: Departemen & CSD --}}
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label form-label-required">Departemen (DEPT)</label>
                            <select name="dept" id="deptSelect" class="form-select @error('dept') is-invalid @enderror" required>
                                <option value="">-- Pilih atau ketik baru --</option>
                                @foreach($existing_depts as $dept)
                                    <option value="{{ $dept }}" {{ old('dept') == $dept ? 'selected' : '' }}>{{ $dept }}</option>
                                @endforeach
                            </select>
                            @error('dept')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Posisi / CSD</label>
                            <select name="csd" id="csdSelect" class="form-select @error('csd') is-invalid @enderror">
                                <option value="">-- Pilih atau ketik baru --</option>
                                @foreach($existing_csds as $csd)
                                    <option value="{{ $csd }}" {{ old('csd') == $csd ? 'selected' : '' }}>{{ $csd }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted" id="csdHint">Opsional - posisi/jabatan anggota</small>
                            @error('csd')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Row 4: Saldo Awal --}}
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Saldo Simpanan Awal</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" name="savings_balance" class="form-control input-currency @error('savings_balance') is-invalid @enderror" 
                                       value="{{ old('savings_balance', 0) }}" min="0" step="1000">
                            </div>
                            <small class="text-muted">Opsional, default 0</small>
                            @error('savings_balance')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('members.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="bi bi-check me-1"></i> Simpan
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
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Select2 with tags
    const deptSelect = $('#deptSelect').select2({
        theme: 'bootstrap-5',
        tags: true,
        placeholder: '-- Pilih atau ketik baru --',
        allowClear: true,
        width: '100%'
    });

    const csdSelect = $('#csdSelect').select2({
        theme: 'bootstrap-5',
        tags: true,
        placeholder: '-- Pilih atau ketik baru --',
        allowClear: true,
        width: '100%'
    });

    const groupTagSelect = document.getElementById('groupTagSelect');
    const csdHint = document.getElementById('csdHint');

    // Function to handle group tag change
    function handleGroupTagChange() {
        const selectedTag = groupTagSelect.value;
        
        if (selectedTag === 'Manager') {
            // Set CSD to "Manager" and make it readonly
            csdSelect.val(null).trigger('change');
            
            // Create new option if not exists
            if ($('#csdSelect option[value="Manager"]').length === 0) {
                const newOption = new Option('Manager', 'Manager', true, true);
                csdSelect.append(newOption).trigger('change');
            } else {
                csdSelect.val('Manager').trigger('change');
            }
            
            // Disable Select2
            csdSelect.prop('disabled', true);
            csdHint.innerHTML = '<span class="text-info"><i class="bi bi-info-circle me-1"></i>Otomatis diisi "Manager"</span>';
        } else {
            // Enable CSD selection
            csdSelect.prop('disabled', false);
            csdHint.innerHTML = 'Opsional - posisi/jabatan anggota';
        }
    }

    // Listen to group tag changes
    groupTagSelect.addEventListener('change', handleGroupTagChange);

    // Initial check
    handleGroupTagChange();

    // Form submission with loading state
    const form = document.getElementById('memberForm');
    const submitBtn = document.getElementById('submitBtn');

    form.addEventListener('submit', function(e) {
        // Show loading
        Swal.fire({
            title: 'Mohon Tunggu...',
            html: 'Sedang memproses data anggota',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    });
});
</script>
@endpush
