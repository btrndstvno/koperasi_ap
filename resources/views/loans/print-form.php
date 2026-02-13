<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulir Pengajuan Pinjaman</title>
    <style>
        @page {
            size: 215mm 330mm; /* F4 */
            margin: 15mm 20mm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            color: #000;
            line-height: 1.3;
            margin: 0;
            padding: 20px;
        }
        /* Tombol Print (Hanya tampil di Modal) */
        .no-print {
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            background: #f8f9fa;
            border-bottom: 1px solid #ddd;
        }
        .btn-print {
            background-color: #0d6efd;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-print:hover { background-color: #0b5ed7; }

        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }

        .header { text-align: center; margin-bottom: 30px; border-bottom: 3px double #000; padding-bottom: 10px; }
        .header h1 { font-size: 16pt; font-weight: bold; margin: 0; text-transform: uppercase; }
        .header h2 { font-size: 12pt; font-weight: bold; margin: 5px 0 0 0; text-transform: uppercase; }

        .content { margin-top: 10px; }
        
        /* Table Layout Presisi */
        .form-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .form-table td { padding: 5px 0; vertical-align: bottom; }
        
        .col-label { width: 200px; font-weight: normal; }
        .col-sep { width: 20px; text-align: center; }
        /* Garis Solid */
        .col-input { border-bottom: 1px solid #000; height: 20px; } 
        
        .amount-box {
            border: 2px solid #000;
            padding: 15px;
            margin: 15px 0;
        }

        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            text-align: center;
        }
        .sig-box { width: 30%; }
        .sig-space { height: 80px; }
        .sig-name { border-bottom: 1px solid #000; font-weight: bold; }
    </style>
</head>
<body>

    <div class="header">
        <h1>FORMULIR PENGAJUAN PINJAMAN</h1>
        <h2>KOPERASI KARYAWAN PT ADIPUTRO WIRASEJATI MALANG</h2>
    </div>

    <div class="content">
        <p>Yang bertanda tangan di bawah ini, saya:</p>
        
        <table class="form-table">
            <tr>
                <td class="col-label">Nama Lengkap</td>
                <td class="col-sep">:</td>
                <td class="col-input"></td>
            </tr>
            <tr>
                <td class="col-label">NIK</td>
                <td class="col-sep">:</td>
                <td class="col-input"></td>
            </tr>
            <tr>
                <td class="col-label">Departemen</td>
                <td class="col-sep">:</td>
                <td class="col-input"></td>
            </tr>
        </table>

        <p><strong>Mengajukan Pinjaman</strong> dari Koperasi Karyawan dengan rincian sebagai berikut:</p>

        <div class="amount-box">
            <table class="form-table" style="margin-bottom: 0;">
                <tr>
                    <td class="col-label">Nilai / Jumlah Pinjaman</td>
                    <td class="col-sep">:</td>
                    <td style="border-bottom: 1px solid #000; font-weight: bold;">Rp </td>
                </tr>
                <tr>
                    <td class="col-label">Jangka Waktu</td>
                    <td class="col-sep">:</td>
                    <td style="font-weight: bold;">10 Bulan</td> 
                </tr>
                <tr>
                    <td class="col-label">Bunga (10%)</td> 
                    <td class="col-sep">:</td>
                    <td style="color: red; border-bottom: 1px solid #000;">- Rp </td> 
                </tr>
                <tr>
                    <td class="col-label">Biaya Admin</td>
                    <td class="col-sep">:</td>
                    <td style="color: red; border-bottom: 1px solid #000;">- Rp </td>
                </tr>
                <tr style="height: 10px;"></tr> <tr>
                    <td class="col-label" style="font-weight: bold;">Uang yang Diterima</td>
                    <td class="col-sep">:</td>
                    <td style="border-bottom: 2px solid #000; font-weight: bold;">Rp </td>
                </tr>
                <tr>
                    <td class="col-label">Cicilan per Bulan</td>
                    <td class="col-sep">:</td>
                    <td style="border-bottom: 1px solid #000; font-weight: bold;">Rp </td>
                </tr>
            </table>
        </div>

        <p>Selanjutnya saya menyatakan <strong>BERSEDIA</strong> untuk:</p>
        <ol style="margin-top: 5px; padding-left: 20px;">
            <li>Bersedia dikurangi/dipotong sebagian dari gaji untuk membayar angsuran setiap bulannya sesuai jangka waktu yang disetujui. </li>
            <li>Bersedia dipotong dari gaji untuk membayar seluruh sisa pinjaman bila status keanggotaanya sudah tidak aktif lagi. </li>
            <li>Menerima jumlah pinjaman yang disetujui oleh Bendahara Koperasi.</li>
        </ol>
    </div>

    <div class="signature-section">
        <div class="sig-box">
            <p>Disetujui Oleh,</p>
            <div class="sig-space"></div>
            <div class="sig-name">Bendahara / Admin</div>
        </div>
        <div class="sig-box">
            <p>Mengetahui,</p>
            <div class="sig-space"></div>
            <div class="sig-name">Kepala Departemen</div>
        </div>
        <div class="sig-box">
            <p>Malang, ........................ 20...</p>
            <p>Diterima Oleh,</p>
            <div class="sig-space" style="height: 45px;"></div>
            <div class="sig-name">( Nama Jelas )</div>
        </div>
    </div>
</body>
</html>