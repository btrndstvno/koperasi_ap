<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPJ Pinjaman - {{ $loan->member->name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 1.6;
            padding: 20mm;
            background: #fff;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px double #000;
            padding-bottom: 15px;
        }
        
        .header h1 {
            font-size: 18pt;
            margin-bottom: 5px;
        }
        
        .header h2 {
            font-size: 14pt;
            font-weight: normal;
        }
        
        .header p {
            font-size: 10pt;
            color: #555;
        }
        
        .title {
            text-align: center;
            margin: 30px 0;
        }
        
        .title h3 {
            font-size: 14pt;
            text-decoration: underline;
            margin-bottom: 5px;
        }
        
        .title p {
            font-size: 10pt;
        }
        
        .content {
            text-align: justify;
            margin-bottom: 30px;
        }
        
        .content p {
            margin-bottom: 15px;
            text-indent: 40px;
        }
        
        .data-table {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
        }
        
        .data-table td {
            padding: 5px 10px;
            vertical-align: top;
        }
        
        .data-table td:first-child {
            width: 180px;
        }
        
        .data-table td:nth-child(2) {
            width: 20px;
            text-align: center;
        }
        
        .amount-box {
            border: 1px solid #000;
            padding: 15px;
            margin: 20px 0;
            background: #f9f9f9;
        }
        
        .amount-box table {
            width: 100%;
        }
        
        .amount-box td {
            padding: 5px 10px;
        }
        
        .amount-box .total {
            border-top: 1px solid #000;
            font-weight: bold;
        }
        
        .terms {
            margin: 30px 0;
        }
        
        .terms h4 {
            margin-bottom: 10px;
        }
        
        .terms ol {
            margin-left: 20px;
        }
        
        .terms li {
            margin-bottom: 8px;
        }
        
        .signature-area {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-box {
            width: 45%;
            text-align: center;
        }
        
        .signature-box .date {
            margin-bottom: 10px;
        }
        
        .signature-box .line {
            margin-top: 80px;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
        }
        
        .signature-box .name {
            font-weight: bold;
        }
        
        .footer {
            margin-top: 50px;
            font-size: 9pt;
            color: #666;
            text-align: center;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
        
        .no-print {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .no-print button {
            padding: 10px 20px;
            font-size: 14px;
            cursor: pointer;
            border: none;
            border-radius: 5px;
            margin-left: 10px;
        }
        
        .btn-print {
            background: #007bff;
            color: white;
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 10pt;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .status-pending {
            background: #ffc107;
            color: #000;
        }
        
        .status-active {
            background: #28a745;
            color: #fff;
        }
        
        @media print {
            body {
                padding: 10mm;
            }
            
            .no-print {
                display: none !important;
            }
            
            .status-badge {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <!-- Print/Back Buttons -->
    <div class="no-print">
        <button class="btn-back" onclick="window.history.back()">← Kembali</button>
        <button class="btn-print" onclick="window.print()">🖨️ Cetak SPJ</button>
    </div>

    <!-- Header Koperasi -->
    <div class="header">
        <h1>KOPERASI KARYAWAN</h1>
        <h2>PT. AP (Persero)</h2>
        <p>Alamat Kantor Koperasi | Telepon: (021) xxx-xxxx</p>
        
        @if($loan->status === 'pending')
            <span class="status-badge status-pending">DRAFT - BELUM DICAIRKAN</span>
        @else
            <span class="status-badge status-active">SUDAH DICAIRKAN</span>
        @endif
    </div>

    <!-- Title -->
    <div class="title">
        <h3>SURAT PERJANJIAN PINJAMAN</h3>
        <p>No: SPJ/{{ $loan->id }}/{{ now()->format('m/Y') }}</p>
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
            <tr>
                <td>Status Karyawan</td>
                <td>:</td>
                <td>{{ $loan->member->employee_status === 'monthly' ? 'Bulanan' : 'Mingguan' }}</td>
            </tr>
        </table>

        <p>
            Dengan ini menyatakan <strong>MEMINJAM UANG</strong> dari Koperasi Karyawan dengan rincian sebagai berikut:
        </p>

        <div class="amount-box">
            <table>
                <tr>
                    <td style="width: 200px;">Pokok Pinjaman</td>
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
                    <td>Biaya Admin (1%)</td>
                    <td>:</td>
                    <td style="text-align: right; color: red;">- Rp {{ number_format($loan->admin_fee, 0, ',', '.') }}</td>
                </tr>
                @endif
                <tr class="total">
                    <td><strong>Uang yang Diterima</strong></td>
                    <td>:</td>
                    <td style="text-align: right;"><strong>Rp {{ number_format($loan->disbursed_amount, 0, ',', '.') }}</strong></td>
                </tr>
                <tr>
                    <td>Cicilan per Bulan</td>
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
            <li>Membayar angsuran pinjaman sebesar <strong>Rp {{ number_format($loan->monthly_installment, 0, ',', '.') }}</strong> setiap bulan melalui pemotongan gaji.</li>
            <li>Melunasi seluruh sisa pinjaman apabila saya berhenti bekerja atau diberhentikan dari perusahaan.</li>
            <li>Memberikan kuasa penuh kepada perusahaan untuk memotong gaji saya setiap bulan untuk pembayaran angsuran.</li>
            <li>Mematuhi seluruh ketentuan dan peraturan yang berlaku di Koperasi.</li>
            <li>Tidak akan melakukan pinjaman baru sebelum pinjaman ini lunas.</li>
        </ol>
    </div>

    <p style="text-indent: 40px; text-align: justify;">
        Demikian surat perjanjian ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.
    </p>

    <!-- Signature Area -->
    <div class="signature-area">
        <div class="signature-box">
            <p class="date">{{ now()->translatedFormat('d F Y') }}</p>
            <p>Peminjam,</p>
            <p class="line"></p>
            <p class="name">{{ $loan->member->name }}</p>
            <p style="font-size: 10pt;">NIK: {{ $loan->member->nik }}</p>
        </div>
        <div class="signature-box">
            <p class="date">{{ now()->translatedFormat('d F Y') }}</p>
            <p>Mengetahui,<br>Pengurus Koperasi</p>
            <p class="line"></p>
            <p class="name">_______________________</p>
            <p style="font-size: 10pt;">Ketua / Bendahara</p>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>Dokumen ini dicetak pada {{ now()->translatedFormat('l, d F Y H:i') }} | ID Pinjaman: #{{ $loan->id }}</p>
    </div>
</body>
</html>
