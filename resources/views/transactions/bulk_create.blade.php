@extends('layouts.app')

@section('title', 'Input Transaksi Massal - Koperasi')
@section('breadcrumb', 'Input Massal / Gajian')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-receipt me-2"></i>Input Transaksi Massal / Gajian</h4>
    <div class="d-flex gap-2">
        <span class="badge bg-primary fs-6">Total Anggota: {{ $grandTotals->total_members }}</span>
        <span class="badge bg-warning text-dark fs-6">Punya Pinjaman: {{ $grandTotals->members_with_loan }}</span>
    </div>
</div>

<!-- Period Selector & Date Input -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-bold">Periode Transaksi</label>
                <form action="{{ route('transactions.bulk.create') }}" method="GET" class="input-group">
                    <select name="month" class="form-select">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
                            </option>
                        @endfor
                    </select>
                    <select name="year" class="form-select">
                        @for($y = now()->year - 2; $y <= now()->year + 1; $y++)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                    <button type="submit" class="btn btn-secondary">
                        <i class="bi bi-arrow-repeat"></i> Refresh
                    </button>
                </form>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Tanggal Transaksi</label>
                <input type="date" id="transactionDateInput" class="form-control" value="{{ $transactionDate }}">
                <div class="form-text text-muted">Tanggal ini digunakan untuk semua transaksi inputan.</div>
            </div>
            <div class="col-md-5">
                 <label class="form-label fw-bold">Pencarian Global (Semua Tab)</label>
                 <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="globalSearchInput" class="form-control" placeholder="Cari Nama / NIK..." onkeyup="searchMembers(this.value)">
                 </div>
                 <div class="form-text text-muted">Ketik untuk mencari. Jika hasil ada di tab lain, tab akan otomatis terbuka.</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header py-2">
        <ul class="nav nav-tabs card-header-tabs" id="groupTabs" role="tablist">
            @php $isFirst = true; @endphp
            @foreach($groupedByTag as $tagName => $group)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $isFirst ? 'active' : '' }}" 
                            id="tab-{{ Str::slug($tagName) }}" 
                            data-bs-toggle="tab" 
                            data-bs-target="#content-{{ Str::slug($tagName) }}" 
                            type="button" role="tab">
                        <i class="bi bi-{{ $tagName == 'Manager' ? 'person-badge' : ($tagName == 'Bangunan' ? 'building' : 'people') }} me-1"></i>
                        {{ $tagName }}
                        <span class="badge bg-secondary tab-badge" id="badge-{{ Str::slug($tagName) }}">{{ $group->count }}</span>
                    </button>
                </li>
                @php $isFirst = false; @endphp
            @endforeach
        </ul>
    </div>
    <div class="card-body p-0">
        <div class="tab-content" id="groupTabsContent">
            @php 
                $isFirst = true; 
                $globalIndex = 0;
            @endphp
            @foreach($groupedByTag as $tagName => $group)
                <div class="tab-pane fade {{ $isFirst ? 'show active' : '' }}" 
                        id="content-{{ Str::slug($tagName) }}" 
                        role="tabpanel" 
                        data-tag="{{ $tagName }}">
                    
                    {{-- Form removed, using AJAX per-row update --}}
                
                    <div class="table-container">
                        <table class="table table-sm table-bordered bulk-table mb-0">
                        <thead class="table-light sticky-header">
                            <tr>
                                <th style="width: 50px;" class="text-center">#</th>
                                <th style="width: 80px;">NIK</th>
                                <th style="width: 150px;">NAMA</th>
                                <th style="width: 70px;" class="text-center">DEPT</th>
                                <th style="width: 100px;" class="text-end">POT KOP</th>
                                <th style="width: 100px;" class="text-end">IUR KOP</th>
                                <th style="width: 100px;" class="text-end">IUR TUNAI</th>
                                <th style="width: 100px;" class="text-end">JUMLAH</th>
                                <th style="width: 110px;" class="text-end">SISA PINJAMAN</th>
                                <th style="width: 110px;" class="text-end">SALDO KOP</th>
                                <th style="width: 80px;">CSD</th>
                                <th style="width: 120px;">KETERANGAN</th>
                                <th style="width: 60px;" class="text-center">SISA<br>CICILAN</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $rowNum = 1; @endphp
                            @foreach($group->members as $member)
                                <tr class="{{ $member->has_loan ? 'has-loan' : '' }} member-row" 
                                    data-tag="{{ $tagName }}" 
                                    data-row="{{ $globalIndex }}"
                                    data-member-id="{{ $member->id }}"
                                    data-loan-id="{{ $member->loan_id }}"
                                    data-search="{{ strtolower($member->name . ' ' . $member->nik) }}">
                                    
                                    <td class="text-center">{{ $rowNum }}</td>
                                    <td><code>{{ $member->nik }}</code></td>
                                    <td><strong>{{ $member->name }}</strong></td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary badge-dept">{{ $member->dept }}</span>
                                    </td>
                                    <td>
                                        {{-- POT KOP: Now Editable --}}
                                        <input type="text" 
                                                class="form-control form-control-sm text-end input-pot auto-save"
                                                value="{{ round($member->pot_kop) }}"
                                                data-field="pot_kop"
                                                data-tag="{{ $tagName }}"
                                                data-row="{{ $globalIndex }}">
                                    </td>
                                    <td>
                                        {{-- IUR KOP: Editable --}}
                                        <input type="text" 
                                                class="form-control form-control-sm text-end input-iur auto-save"
                                                value="{{ round($member->iur_kop) }}"
                                                min="0" step="1"
                                                data-field="iur_kop"
                                                data-tag="{{ $tagName }}"
                                                data-row="{{ $globalIndex }}">
                                    </td>
                                    <td>
                                        {{-- IUR TUNAI: Editable --}}
                                        <input type="text" 
                                                class="form-control form-control-sm text-end input-iur-tunai auto-save"
                                                value="{{ round($member->iur_tunai) }}"
                                                min="0" step="1"
                                                data-field="iur_tunai"
                                                data-tag="{{ $tagName }}"
                                                data-row="{{ $globalIndex }}">
                                    </td>
                                    <td class="text-end">
                                        {{-- JUMLAH: Auto-calculated --}}
                                        <span class="row-jumlah fw-bold text-primary" data-row="{{ $globalIndex }}">
                                            {{ number_format($member->pot_kop + $member->iur_kop + $member->iur_tunai, 0, ',', '.') }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        {{-- SISA PINJAMAN: Updated via AJAX --}}
                                        <span class="remaining-principal" data-member-id="{{ $member->id }}">
                                        @if($member->has_loan)
                                            <span class="text-danger-bold">{{ number_format($member->remaining_principal, 0, ',', '.') }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        {{-- SALDO SIMPANAN: Updated via AJAX --}}
                                        <span class="savings-balance text-success-bold" data-member-id="{{ $member->id }}">
                                            {{ number_format($member->savings_balance, 0, ',', '.') }}
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">{{ $member->group_tag }}</small>
                                    </td>
                                    <td>
                                        <input type="text" 
                                                class="form-control form-control-sm notes-input auto-save"
                                                placeholder="Catatan..."
                                                maxlength="100"
                                                data-field="notes"
                                                data-row="{{ $globalIndex }}">
                                    </td>
                                    <td class="text-center">
                                        @if($member->has_loan && $member->remaining_installments > 0)
                                            @if($member->remaining_installments <= 1)
                                                <span class="badge bg-danger">{{ $member->remaining_installments }}x</span>
                                            @elseif($member->remaining_installments <= 3)
                                                <span class="badge bg-warning text-dark">{{ $member->remaining_installments }}x</span>
                                            @else
                                                <span class="badge bg-secondary">{{ $member->remaining_installments }}x</span>
                                            @endif
                                        @elseif($member->has_loan)
                                            {{-- User request: Jangan tampilkan "Lunas", biarkan kosong/- --}}
                                            <small class="text-muted">-</small>
                                        @else
                                            <small class="text-muted">-</small>
                                        @endif
                                    </td>
                                </tr>
                                @php $globalIndex++; $rowNum++; @endphp
                            @endforeach
                        </tbody>
                        <tfoot>
                            {{-- Subtotal Row for Tag --}}
                            <tr class="subtotal-row" data-tag-subtotal="{{ $tagName }}">
                                <td colspan="4" class="text-end">SUBTOTAL {{ strtoupper($tagName) }}:</td>
                                <td class="text-end">
                                    <span class="subtotal-pot" data-tag="{{ $tagName }}">{{ number_format($group->subtotal_pot, 0, ',', '.') }}</span>
                                </td>
                                <td class="text-end">
                                    <span class="subtotal-iur" data-tag="{{ $tagName }}">{{ number_format($group->subtotal_iur, 0, ',', '.') }}</span>
                                </td>
                                <td class="text-end">
                                    <span class="subtotal-iur-tunai" data-tag="{{ $tagName }}">{{ number_format($group->subtotal_iur_tunai, 0, ',', '.') }}</span>
                                </td>
                                <td class="text-end">
                                    <span class="subtotal-jumlah text-primary fw-bold" data-tag="{{ $tagName }}">{{ number_format($group->subtotal_jumlah, 0, ',', '.') }}</span>
                                </td>
                                <td colspan="5"></td>
                            </tr>
                        </tfoot>
                    </table>
                    </div>
                </div>
                @php $isFirst = false; @endphp
            @endforeach
        </div>
    </div>
</div>

<div class="fixed-bottom bg-white border-top p-3 shadow-lg d-flex justify-content-between align-items-center">
    <div>
        <small class="text-muted d-block">Status: <span id="saveStatus" class="fw-bold text-success">Siap</span></small>
        <small class="text-muted">Perubahan tersimpan otomatis. Tekan "Simpan Semua" untuk memproses seluruh data bulan ini.</small>
    </div>
    <form action="{{ route('transactions.bulk.store') }}" method="POST" id="bulkForm">
        @csrf
        <input type="hidden" name="transaction_date" value="{{ $transactionDate }}">
        {{-- Data will be injected via JS --}}
        <button type="submit" class="btn btn-primary" id="btnSimpanSemua">
            <i class="bi bi-save me-2"></i>Simpan Semua Transaksi
        </button>
    </form>
</div>
<div style="height: 80px;"></div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'); 
    const dateInput = document.getElementById('transactionDateInput');
    const globalSearchInput = document.getElementById('globalSearchInput');
    const statusEl = document.getElementById('saveStatus');
    const bulkForm = document.getElementById('bulkForm');
    const container = document.getElementById('groupTabsContent'); // Delegate container

    // Update Date in Form on Change
    dateInput.addEventListener('change', function() {
        document.querySelector('input[name="transaction_date"]').value = this.value;
    });

    // Format Rupiah
    function formatNumber(num) {
        return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(num);
    }

    // 1. Kalkulasi Client-Side (Subtotal & Row Total)
    function calculateTotals(rowIndex, tagName) {
        if(rowIndex !== null) {
            const pot = parseFloat(document.querySelector(`input.input-pot[data-row="${rowIndex}"]`)?.value) || 0;
            const iur = parseFloat(document.querySelector(`input.input-iur[data-row="${rowIndex}"]`)?.value) || 0;
            const tunai = parseFloat(document.querySelector(`input.input-iur-tunai[data-row="${rowIndex}"]`)?.value) || 0;
            const jumlahSpan = document.querySelector(`.row-jumlah[data-row="${rowIndex}"]`);
            if(jumlahSpan) jumlahSpan.textContent = formatNumber(pot + iur + tunai);
        }
        
        if(tagName) {
            // Optimization: Only query selector within the active/target tab if possible, but here we need tag scope
            const tabPane = document.getElementById(`content-${tagName.toLowerCase().replace(/ /g, '-')}`) || document.body;
            
            let totalPot = 0, totalIur = 0, totalIurTunai = 0;
            // Scoped query is faster
            tabPane.querySelectorAll(`input.input-pot`).forEach(i => totalPot += parseFloat(i.value) || 0);
            tabPane.querySelectorAll(`input.input-iur`).forEach(i => totalIur += parseFloat(i.value) || 0);
            tabPane.querySelectorAll(`input.input-iur-tunai`).forEach(i => totalIurTunai += parseFloat(i.value) || 0);
            
            const subPot = tabPane.querySelector(`.subtotal-pot`);
            const subIur = tabPane.querySelector(`.subtotal-iur`);
            const subTunai = tabPane.querySelector(`.subtotal-iur-tunai`);
            const subJml = tabPane.querySelector(`.subtotal-jumlah`);
            
            if(subPot) subPot.textContent = formatNumber(totalPot);
            if(subIur) subIur.textContent = formatNumber(totalIur);
            if(subTunai) subTunai.textContent = formatNumber(totalIurTunai);
            if(subJml) subJml.textContent = formatNumber(totalPot + totalIur + totalIurTunai);
        }
    }

    // 2. Auto-Save Logic (Debounced via Delegation)
    const debounceMap = new Map();
    
    function updateSingle(input) {
        const row = input.closest('tr');
        const memberId = row.dataset.memberId;
        const loanId = row.dataset.loanId;
        const field = input.dataset.field; // pot_kop, iur_kop, iur_tunai, notes
        const value = input.value;
        const key = `${memberId}-${field}`;

        // UI Feedback: Loading
        input.classList.remove('is-valid', 'is-invalid');
        input.classList.add('border-warning');
        statusEl.textContent = 'Menyimpan...';
        statusEl.className = 'fw-bold text-warning';

        // Clear previous timeout
        if (debounceMap.has(key)) clearTimeout(debounceMap.get(key));

        // Set new timeout (1000ms debounce - increased for performance)
        const timeoutId = setTimeout(() => {
            fetch("{{ route('transactions.bulk.update-single') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-CSRF-TOKEN": csrfToken
                },
                body: JSON.stringify({
                    member_id: memberId,
                    transaction_date: dateInput.value,
                    loan_id: loanId, // Optional, works for pot_kop
                    field: field,
                    value: value
                })
            })
            .then(res => res.json())
            .then(data => {
                input.classList.remove('border-warning');
                if (data.success) {
                    input.classList.add('is-valid');
                    statusEl.textContent = 'Tersimpan';
                    statusEl.className = 'fw-bold text-success';
                    
                    // Update Balance & Principal from server response
                    if (data.new_balance !== undefined) {
                        const balEl = row.querySelector('.savings-balance');
                        if(balEl) balEl.textContent = formatNumber(data.new_balance);
                    }
                    if (data.new_loan_remaining !== undefined) {
                        const remEl = row.querySelector('.remaining-principal');
                        if(remEl) {
                            if (data.new_loan_remaining > 0) {
                                remEl.innerHTML = `<span class="text-danger-bold">${formatNumber(data.new_loan_remaining)}</span>`;
                            } else {
                                // User request: Jangan tampilkan "Lunas", biarkan 0 atau - 
                                remEl.innerHTML = `<span class="text-success fw-bold">0</span>`; 
                            }
                        }
                    }

                    // Remove success class after 2s
                    setTimeout(() => input.classList.remove('is-valid'), 2000);
                } else {
                    input.classList.add('is-invalid');
                    statusEl.textContent = 'Gagal menyimpan!';
                    statusEl.className = 'fw-bold text-danger';
                }
            })
            .catch(err => {
                input.classList.remove('border-warning');
                input.classList.add('is-invalid');
                statusEl.textContent = 'Error koneksi!';
                statusEl.className = 'fw-bold text-danger';
                console.error(err);
            });
        }, 1000); // 1s debounce

        debounceMap.set(key, timeoutId);
    }

    // [OPTIMIZATION] Event Delegation for Inputs
    // Single listener for thousands of inputs
    if (container) {
        container.addEventListener('input', function(e) {
            if (e.target.classList.contains('auto-save')) {
                // Calculation is fast, do it immediately for responsiveness
                calculateTotals(e.target.dataset.row, e.target.dataset.tag);
                // Save is slow, debounce it
                updateSingle(e.target);
            }
        });
    }

    // 3. Global Search Logic (Debounced)
    let searchTimeout;
    globalSearchInput.addEventListener('keyup', function(e) {
        clearTimeout(searchTimeout);
        const keyword = this.value;
        
        searchTimeout = setTimeout(() => {
            performSearch(keyword);
        }, 500); // 500ms debounce for search
    });

    function performSearch(keyword) {
        keyword = keyword.toLowerCase();
        let foundInCurrentTab = false;
        let firstTabWithMatch = null;
        const matchesPerTab = {};

        // Reset all rows display first? No, just iterate.
        // Performance note: querying all rows is O(N). If N=1000, it's fine. 
        // Rendering/Hiding them is what's heavy.
        
        const allRows = document.querySelectorAll('.member-row');
        
        // Optimization: Use `requestAnimationFrame` or batch DOM updates if really slow.
        // For now, standard loop.
        
        allRows.forEach(row => {
            const text = row.dataset.search;
            const tabPane = row.closest('.tab-pane');
            const tabBtnId = tabPane.id.replace('content-', 'tab-'); // e.g., tab-manager

            if (text.includes(keyword)) {
                row.style.display = ''; // Show
                matchesPerTab[tabBtnId] = (matchesPerTab[tabBtnId] || 0) + 1;
                
                if (tabPane.classList.contains('active')) {
                    foundInCurrentTab = true;
                }
                if (!firstTabWithMatch) firstTabWithMatch = tabBtnId;
            } else {
                row.style.display = 'none'; // Hide
            }
        });

        // Auto-switch tab logic
        if (!foundInCurrentTab && firstTabWithMatch && keyword.length > 0) {
            const triggerBtn = document.getElementById(firstTabWithMatch);
            if(triggerBtn) bootstrap.Tab.getOrCreateInstance(triggerBtn).show();
        }
    }

    // 4. Handle Simpan Semua (Bulk Store)
    bulkForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'Simpan Semua?',
            text: "Akan memproses seluruh data transaksi bulan ini (termasuk yang belum diedit). Data yang sudah ada (auto-saved) tetap aman.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Proses Semua',
            confirmButtonColor: '#0d6efd'
        }).then((result) => {
            if (result.isConfirmed) {
                // Collect Data from Inputs
                const rows = document.querySelectorAll('.member-row');
                // Remove old hidden inputs (dynamic-input) to be clean
                bulkForm.querySelectorAll('.dynamic-input').forEach(el => el.remove());

                const transactions = [];

                rows.forEach(row => {
                    const memberId = row.dataset.memberId;
                    const loanId = row.dataset.loanId;
                    
                    // Find values safely
                    const pot = row.querySelector('.input-pot')?.value || 0;
                    const iur = row.querySelector('.input-iur')?.value || 0;
                    const tunai = row.querySelector('.input-iur-tunai')?.value || 0;
                    const notes = row.querySelector('.notes-input')?.value || '';

                    // Push to array
                    transactions.push({
                        member_id: memberId,
                        loan_id: loanId,
                        pot_kop: pot,
                        iur_kop: iur,
                        iur_tunai: tunai,
                        notes: notes
                    });
                });

                // Create SINGLE hidden input with JSON string
                // This bypasses PHP max_input_vars limit (usually 1000)
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'transactions';
                input.value = JSON.stringify(transactions);
                input.className = 'dynamic-input';
                bulkForm.appendChild(input);

                bulkForm.submit();
            }
        });
    });

    // Helper removed as we use JSON now
    /* 
    function addHidden(form, name, value) { ... } 
    */
    
    // Tab State
    const tabEls = document.querySelectorAll('button[data-bs-toggle="tab"]');
    tabEls.forEach(tabEl => {
        tabEl.addEventListener('shown.bs.tab', function (event) {
            localStorage.setItem('activeBulkTab', event.target.id);
        });
    });
    const lastActiveTab = localStorage.getItem('activeBulkTab');
    if (lastActiveTab) {
        const tabTrigger = document.querySelector(`#${lastActiveTab}`);
        if (tabTrigger) { const tab = new bootstrap.Tab(tabTrigger); tab.show(); }
    }
});
</script>
@endpush