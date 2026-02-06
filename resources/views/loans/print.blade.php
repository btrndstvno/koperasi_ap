<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPJ Pinjaman - {{ $loan->member->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/print.css') }}">
</head>
<body>
    <!-- Print/Back Buttons -->
    @if(!request('modal'))
    <div class="no-print">
        <button class="btn-back" onclick="window.history.back()">← Kembali</button>
        <button class="btn-print" onclick="window.print()">🖨️ Cetak SPJ</button>
    </div>
    @endif

    <!-- Header Koperasi -->
    <div class="header">
        <h1>FORMULIR PENGAJUAN PINJAMAN</h1>
        <h2>KOPERASI KARYAWAN PT ADIPUTRO WIRASEJATI MALANG</h2>
        
        @if($loan->status === 'pending')
            <span class="status-badge status-pending">DRAFT - BELUM DICAIRKAN</span>
        @else
            <span class="status-badge status-active">SUDAH DICAIRKAN</span>
        @endif
    </div>

    <!-- Content -->
    <div class="content">
        <p>
            Yang bertanda tangan di bawah ini, saya:
        </p>
        
        <table class="data-table">
            <tr>
                <td>Nama Lengkap</td>
                <td>:</td>
                <td><strong>{{ $loan->member->name }}</strong></td>
            </tr>
            <tr>
                <td>NIK</td>
                <td>:</td>
                <td>{{ $loan->member->nik }}</td>
            </tr>
            <tr>
                <td>Departemen</td>
                <td>:</td>
                <td>{{ $loan->member->dept }}</td>
            </tr>
        </table>

        <p>
            <strong>Mengajukan Pinjaman</strong> dari Koperasi Karyawan dengan rincian sebagai berikut:
        </p>

        <div class="amount-box">
            <table>
                <tr>
                    <td style="width: 200px;">Nilai / Jumlah Pinjaman</td>
                    <td style="width: 20px;">:</td>
                    <td style="text-align: right;"><strong>Rp {{ number_format($loan->amount, 0, ',', '.') }}</strong></td>
                </tr>
                <tr>
                    <td>Jangka Waktu</td>
                    <td>:</td>
                    <td style="text-align: right;">{{ $loan->duration }} Bulan</td>
                </tr>
                <tr>
                    <td>Bunga ({{ $loan->interest_rate }}%)</td>
                    <td>:</td>
                    <td style="text-align: right; color: red;">- Rp {{ number_format($loan->total_interest, 0, ',', '.') }}</td>
                </tr>
                @if($loan->admin_fee > 0)
                <tr>
                    <td>Biaya Admin</td>
                    <td>:</td>
                    <td style="text-align: right; color: red;">- Rp {{ number_format($loan->admin_fee, 0, ',', '.') }}</td>
                </tr>
                @endif
                <tr class="total">
                    <td>Uang yang Diterima</td>
                    <td>:</td>
                    <td style="text-align: right;"><strong>Rp {{ number_format($loan->disbursed_amount, 0, ',', '.') }}</strong></td>
                </tr>
                <tr class="total">
                    <td>Cicilan per Bulan (10x)</td>
                    <td>:</td>
                    <td style="text-align: right;"><strong>Rp {{ number_format($loan->monthly_installment, 0, ',', '.') }}</strong></td>
                </tr>
            </table>
        </div>

        <p>
            Selanjutnya saya menyatakan <strong>BERSEDIA</strong> untuk:
        </p>
    </div>

    <!-- Terms -->
    <div class="terms">
        <ol>
            <li>Bersedia dikurangi/dipotong sebagian dari gaji untuk membayar angsuran setiap bulannya sebanyak {{ $loan->duration }}x dari Jumlah Pengajuan Pinjaman yang disetujui. </li>
            <li>Bersedia dipotong dari gaji untuk membayar seluruh sisa pinjaman bila status keanggotaanya sudah tidak aktif lagi. </li>
            <li>Menerima jumlah pinjaman yang disetujui oleh Bendahara Koperasi.</li>
            <li>Tidak memiliki tanggungan pinjaman yang masih berjalan.</li>    
        </ol>
    </div>

    <p style="text-indent: 40px; text-align: justify; margin-bottom: 20px;">
        Demikian surat perjanjian ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.
    </p>

    <!-- Signature Area -->
    <div class="signature-area" style="display: flex; justify-content: space-between; align-items: flex-start;">
        
        {{-- Group 1: Disetujui Oleh (Bendahara & Admin) --}}
        <div style="width: 48%; text-align: center;">
            <div style="height: 50px;">
                <p>Disetujui Oleh,</p>
            </div>
            
            <div style="display: flex; justify-content: space-between;">
                {{-- Bendahara --}}
                <div style="width: 48%;">
                    <div style="height: 110px;"></div> {{-- Signature Space --}}
                    <div style="border-bottom: 1px solid #000; margin-bottom: 5px;"></div>
                    <p class="name">Bendahara Koperasi</p>
                </div>
                {{-- Admin --}}
                <div style="width: 48%;">
                    <div style="height: 110px;"></div> {{-- Signature Space --}}
                    <div style="border-bottom: 1px solid #000; margin-bottom: 5px;"></div>
                    <p class="name">Admin Koperasi</p>
                </div>
            </div>
        </div>

        {{-- Group 2: Mengetahui (Kepala Dept) --}}
        <div style="width: 24%; text-align: center;">
            <div style="height: 50px;">
                <p>Mengetahui,</p>
            </div>
            <div style="height: 110px;"></div> {{-- Signature Space --}}
            <div style="border-bottom: 1px solid #000; margin-bottom: 5px;"></div>
            <p class="name">Kepala Departemen</p>
        </div>

        {{-- Group 3: Diterima Oleh (Peminjam) --}}
        <div style="width: 24%; text-align: center;">
            <div style="height: 50px;">
                <p style="margin-bottom: 0;">Diterima Oleh,</p>
                <p class="date" style="font-size: 10pt;">{{ now()->translatedFormat('d F Y') }}</p>
            </div>
            <div style="height: 110px;"></div> {{-- Signature Space --}}
            <div style="border-bottom: 1px solid #000; margin-bottom: 5px;"></div>
            <p class="name">{{ $loan->member->name }}</p>
        </div>
    </div>

</body>
</html>
