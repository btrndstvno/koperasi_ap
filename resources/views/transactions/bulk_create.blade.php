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
            </div>
            <!-- <div class="col-md-5">
                <div class="alert alert-info mb-0 py-2">
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>Info:</strong> POT KOP = Cicilan Pinjaman | IUR KOP = Potong Gaji | IUR TUNAI = Bayar Cash
                </div>
            </div> -->
        </div>
    </div>
</div>

<!-- Bulk Form removed from here, moved inside tabs -->

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
                            <span class="badge bg-secondary tab-badge">{{ $group->count }}</span>
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
                        
                        <form action="{{ route('transactions.bulk.store') }}" method="POST" class="bulk-group-form" data-tag="{{ $tagName }}">
                            @csrf
                            <input type="hidden" name="transaction_date" class="transaction-date-hidden" value="{{ $transactionDate }}">
                        
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
                                        <tr class="{{ $member->has_loan ? 'has-loan' : '' }}" data-tag="{{ $tagName }}" data-row="{{ $globalIndex }}">
                                            <td class="text-center">
                                                <input type="hidden" name="transactions[{{ $globalIndex }}][member_id]" value="{{ $member->id }}">
                                                <input type="hidden" name="transactions[{{ $globalIndex }}][loan_id]" value="{{ $member->loan_id }}">
                                                {{ $rowNum }}
                                            </td>
                                            <td><code>{{ $member->nik }}</code></td>
                                            <td><strong>{{ $member->name }}</strong></td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary badge-dept">{{ $member->dept }}</span>
                                            </td>
                                            <td>
                                                {{-- POT KOP: Readonly --}}
                                                <input type="number" 
                                                       name="transactions[{{ $globalIndex }}][pot_kop]" 
                                                       class="form-control form-control-sm text-end input-pot"
                                                       value="{{ round($member->pot_kop) }}"
                                                       readonly
                                                       data-tag="{{ $tagName }}"
                                                       data-row="{{ $globalIndex }}">
                                            </td>
                                            <td>
                                                {{-- IUR KOP: Editable (Potong Gaji) --}}
                                                <input type="number" 
                                                       name="transactions[{{ $globalIndex }}][iur_kop]" 
                                                       class="form-control form-control-sm text-end input-iur"
                                                       value="{{ round($member->iur_kop) }}"
                                                       min="0" step="1"
                                                       data-tag="{{ $tagName }}"
                                                       data-row="{{ $globalIndex }}">
                                            </td>
                                            <td>
                                                {{-- IUR TUNAI: Editable (Cash) --}}
                                                <input type="number" 
                                                       name="transactions[{{ $globalIndex }}][iur_tunai]" 
                                                       class="form-control form-control-sm text-end input-iur-tunai"
                                                       value="{{ round($member->iur_tunai) }}"
                                                       min="0" step="1"
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
                                                @if($member->has_loan)
                                                    <span class="text-danger-bold">{{ number_format($member->remaining_principal, 0, ',', '.') }}</span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <span class="text-success-bold">{{ number_format($member->savings_balance, 0, ',', '.') }}</span>
                                            </td>
                                            <td>
                                                <small class="text-muted">{{ $member->csd }}</small>
                                            </td>
                                            <td>
                                                <input type="text" 
                                                       name="transactions[{{ $globalIndex }}][notes]" 
                                                       class="form-control form-control-sm notes-input"
                                                       placeholder="Catatan..."
                                                       maxlength="100">
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
                                                    <span class="badge bg-success">Lunas</span>
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
                        
                        <div class="row align-items-center mt-3 mb-2 px-2">
                            <div class="col-md-8">
                                <div class="d-flex gap-4">
                                    <div>
                                        <small class="text-muted">Total {{ $tagName }} POT:</small>
                                        <strong class="ms-1" id="totalPot-{{ Str::slug($tagName) }}">Rp {{ number_format($group->subtotal_pot, 0, ',', '.') }}</strong>
                                    </div>
                                    <div>
                                        <small class="text-muted">Total {{ $tagName }} IUR:</small>
                                        <strong class="ms-1 text-info" id="totalIur-{{ Str::slug($tagName) }}">Rp {{ number_format($group->subtotal_iur, 0, ',', '.') }}</strong>
                                    </div>
                                    <div>
                                        <small class="text-muted">Total {{ $tagName }} TUNAI:</small>
                                        <strong class="ms-1 text-success" id="totalIurTunai-{{ Str::slug($tagName) }}">Rp {{ number_format($group->subtotal_iur_tunai, 0, ',', '.') }}</strong>
                                    </div>
                                    <div>
                                        <small class="text-muted">TOTAL {{ $tagName }}:</small>
                                        <strong class="ms-1 text-primary fs-5" id="totalJumlah-{{ Str::slug($tagName) }}">Rp {{ number_format($group->subtotal_jumlah, 0, ',', '.') }}</strong>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-2"></i>Simpan {{ $tagName }}
                                </button>
                            </div>
                        </div>
                        </form>
                    </div>
                    @php $isFirst = false; @endphp
                @endforeach
            </div>
        <!-- Global Footer Removed -->
    </div>
<!-- Global Form Removed -->
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('.bulk-group-form');
    const dateInput = document.getElementById('transactionDateInput');
    const dateHiddens = document.querySelectorAll('.transaction-date-hidden');

    // Simpan posisi tab terakhir agar tidak reset saat refresh/save
    const tabEls = document.querySelectorAll('button[data-bs-toggle="tab"]');
    tabEls.forEach(tabEl => {
        tabEl.addEventListener('shown.bs.tab', function (event) {
            localStorage.setItem('activeBulkTab', event.target.id);
        });
    });

    // Restore posisi tab saat halaman dimuat ulang
    const lastActiveTab = localStorage.getItem('activeBulkTab');
    if (lastActiveTab) {
        const tabTrigger = document.querySelector(`#${lastActiveTab}`);
        if (tabTrigger) {
            const tab = new bootstrap.Tab(tabTrigger);
            tab.show();
        }
    }
    
    // Sync date input with hidden fields
    dateInput.addEventListener('change', function() {
        const newVal = this.value;
        dateHiddens.forEach(input => input.value = newVal);
    });

    // Format number to Indonesian
    function formatNumber(num) {
        return new Intl.NumberFormat('id-ID').format(num);
    }

    // Helper: secure slug generation (simple version to match PHP Str::slug mostly)
    function toSlug(text) {
        return text.toString().toLowerCase()
            .replace(/\s+/g, '-')           // Replace spaces with -
            .replace(/[^\w\-]+/g, '')       // Remove all non-word chars
            .replace(/\-\-+/g, '-')         // Replace multiple - with single -
            .replace(/^-+/, '')             // Trim - from start of text
            .replace(/-+$/, '');            // Trim - from end of text
    }

    // Calculate single row total
    function calculateRowTotal(rowIndex) {
        const potInput = document.querySelector(`input[name="transactions[${rowIndex}][pot_kop]"]`);
        const iurInput = document.querySelector(`input[name="transactions[${rowIndex}][iur_kop]"]`);
        const iurTunaiInput = document.querySelector(`input[name="transactions[${rowIndex}][iur_tunai]"]`);
        const jumlahSpan = document.querySelector(`.row-jumlah[data-row="${rowIndex}"]`);
        
        if (!potInput || !iurInput || !iurTunaiInput || !jumlahSpan) return { pot: 0, iur: 0, iurTunai: 0, jumlah: 0 };
        
        const pot = parseInt(potInput.value) || 0;
        const iur = parseInt(iurInput.value) || 0;
        const iurTunai = parseInt(iurTunaiInput.value) || 0;
        const jumlah = pot + iur + iurTunai;
        
        jumlahSpan.textContent = formatNumber(jumlah);
        
        return { pot, iur, iurTunai, jumlah };
    }

    // Calculate subtotal for a tag group
    function calculateTagSubtotal(tagName) {
        const potInputs = document.querySelectorAll(`input.input-pot[data-tag="${tagName}"]`);
        const iurInputs = document.querySelectorAll(`input.input-iur[data-tag="${tagName}"]`);
        const iurTunaiInputs = document.querySelectorAll(`input.input-iur-tunai[data-tag="${tagName}"]`);
        
        let totalPot = 0, totalIur = 0, totalIurTunai = 0;
        
        potInputs.forEach(input => totalPot += parseInt(input.value) || 0);
        iurInputs.forEach(input => totalIur += parseInt(input.value) || 0);
        iurTunaiInputs.forEach(input => totalIurTunai += parseInt(input.value) || 0);
        
        const totalJumlah = totalPot + totalIur + totalIurTunai;
        
        // Update subtotal display in Table Footer (tfoot)
        const subtotalPot = document.querySelector(`.subtotal-pot[data-tag="${tagName}"]`);
        const subtotalIur = document.querySelector(`.subtotal-iur[data-tag="${tagName}"]`);
        const subtotalIurTunai = document.querySelector(`.subtotal-iur-tunai[data-tag="${tagName}"]`);
        const subtotalJumlah = document.querySelector(`.subtotal-jumlah[data-tag="${tagName}"]`);
        
        if (subtotalPot) subtotalPot.textContent = formatNumber(totalPot);
        if (subtotalIur) subtotalIur.textContent = formatNumber(totalIur);
        if (subtotalIurTunai) subtotalIurTunai.textContent = formatNumber(totalIurTunai);
        if (subtotalJumlah) subtotalJumlah.textContent = formatNumber(totalJumlah);

        // Update Summary row next to Button
        const slug = toSlug(tagName);
        const summPot = document.getElementById(`totalPot-${slug}`);
        const summIur = document.getElementById(`totalIur-${slug}`);
        const summTunai = document.getElementById(`totalIurTunai-${slug}`);
        const summJml = document.getElementById(`totalJumlah-${slug}`);

        if (summPot) summPot.textContent = 'Rp ' + formatNumber(totalPot);
        if (summIur) summIur.textContent = 'Rp ' + formatNumber(totalIur);
        if (summTunai) summTunai.textContent = 'Rp ' + formatNumber(totalIurTunai);
        if (summJml) summJml.textContent = 'Rp ' + formatNumber(totalJumlah);
        
        return { pot: totalPot, iur: totalIur, iurTunai: totalIurTunai, jumlah: totalJumlah };
    }

    // Add event listeners to all editable inputs
    document.querySelectorAll('.input-iur, .input-iur-tunai').forEach(input => {
        ['input', 'change'].forEach(eventType => {
            input.addEventListener(eventType, function() {
                const rowIndex = this.dataset.row;
                const tagName = this.dataset.tag;
                calculateRowTotal(rowIndex);
                calculateTagSubtotal(tagName);
            });
        });
    });

    // Form submission with SweetAlert (Per Form/Tab)
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const tagName = this.getAttribute('data-tag');
            const slug = toSlug(tagName);
            
            // Get totals from summary elements
            const potTotal = document.getElementById(`totalPot-${slug}`)?.textContent || '0';
            const iurTotal = document.getElementById(`totalIur-${slug}`)?.textContent || '0';
            const iurTunaiTotal = document.getElementById(`totalIurTunai-${slug}`)?.textContent || '0';
            const grandTotal = document.getElementById(`totalJumlah-${slug}`)?.textContent || '0';
            
            if (grandTotal === 'Rp 0' || grandTotal === '0') {
              Swal.fire({
                  icon: 'warning',
                  title: 'Data Kosong',
                  text: 'Tidak ada nilai transaksi untuk disimpan.'
              });
              return;
            }

            Swal.fire({
                title: `Simpan Transaksi ${tagName}?`,
                html: `
                    <div class="text-start">
                        <p><strong>Tanggal:</strong> ${dateInput.value}</p>
                        <hr>
                        <p><strong>Total POT KOP:</strong> ${potTotal}</p>
                        <p><strong>Total IUR KOP:</strong> ${iurTotal}</p>
                        <p><strong>Total IUR TUNAI:</strong> ${iurTunaiTotal}</p>
                        <hr>
                        <p class="fs-5"><strong>TOTAL:</strong> <span class="text-primary">${grandTotal}</span></p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-check-circle me-1"></i> Ya, Simpan!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Mohon Tunggu...',
                        html: 'Sedang memproses...',
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
