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
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
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

        .header { 
            text-align: center; 
            margin-bottom: 25px; 
            border-bottom: 3px double #000; 
            padding-bottom: 10px; 
        }
        .header h1 { font-size: 16pt; font-weight: bold; margin: 0; text-transform: uppercase; }
        .header h2 { font-size: 12pt; font-weight: bold; margin: 5px 0 0 0; text-transform: uppercase; }

        .content { margin-top: 15px; }
        
        /* Table Form Layout */
        .form-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .form-table td { padding: 6px 0; vertical-align: bottom; }
        
        .col-label { width: 220px; font-weight: normal; }
        .col-sep { width: 20px; text-align: center; }
        .col-input { border-bottom: 1px solid #000; height: 22px; }
        
        .amount-box {
            border: 2px solid #000;
            padding: 15px;
            margin: 15px 0;
        }

        /* Signature Table Layout */
        .sig-table {
            width: 100%;
            margin-top: 40px;
            text-align: center;
            border-collapse: collapse;
        }
        .sig-table td {
            vertical-align: top;
        }
        .sig-spacer {
            height: 110px; 
        }
        .sig-name {
            border-top: 1px solid #000;
            display: inline-block;
            padding-top: 5px;
            width: 80%;
            font-weight: bold;
        }
        
        ol li {
            margin-bottom: 5px;
            padding-left: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>FORMULIR PENGAJUAN PINJAMAN</h1>
        <h2>KOPERASI KARYAWAN PT ADIPUTRO WIRASEJATI MALANG</h2>
    </div>

    <div class="content">
        <p style="margin-bottom: 15px;">Yang bertanda tangan di bawah ini, saya:</p>
        
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

        <p style="margin-bottom: 10px;"><strong>Mengajukan Pinjaman</strong> dari Koperasi Karyawan dengan rincian sebagai berikut:</p>

        <div class="amount-box">
            <table class="form-table" style="margin-bottom: 0;">
                <tr>
                    <td class="col-label">Nilai / Jumlah Pinjaman</td>
                    <td class="col-sep">:</td>
                    <td style="border-bottom: 1px solid #000; font-weight: bold; font-size: 1.1em;">Rp </td>
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
                <tr><td colspan="3" style="height: 10px;"></td></tr>
                
                <tr>
                    <td class="col-label" style="font-weight: bold;">Uang yang Diterima</td>
                    <td class="col-sep">:</td>
                    <td style="border-bottom: 2px solid #000; font-weight: bold; font-size: 1.1em;">Rp </td>
                </tr>
                <tr>
                    <td class="col-label">Cicilan per Bulan</td>
                    <td class="col-sep">:</td>
                    <td style="border-bottom: 1px solid #000; font-weight: bold; font-size: 1.1em;">Rp </td>
                </tr>
            </table>
        </div>

        <p>Selanjutnya saya menyatakan <strong>BERSEDIA</strong> untuk:</p>
        <ol style="margin-top: 5px; padding-left: 25px;">
            <li>Bersedia dikurangi/dipotong sebagian dari gaji untuk membayar angsuran setiap bulannya sesuai jangka waktu yang disetujui. </li>
            <li>Bersedia dipotong dari gaji untuk membayar seluruh sisa pinjaman bila status keanggotaanya sudah tidak aktif lagi. </li>
            <li>Menerima jumlah pinjaman yang disetujui oleh Bendahara Koperasi.</li>
        </ol>
    </div>

    <table class="sig-table">
        <tr>
            <td style="width: 33%;"></td>
            <td style="width: 33%;"></td>
            <td style="width: 33%; padding-bottom: 5px;">Malang, ........................ 20...</td>
        </tr>
        <tr>
            <td>Disetujui Oleh,</td>
            <td>Mengetahui,</td>
            <td>Diterima Oleh,</td>
        </tr>
        <tr>
            <td class="sig-spacer"></td>
            <td class="sig-spacer"></td>
            <td class="sig-spacer"></td>
        </tr>
        <tr>
            <td>
                <span class="sig-name">Bendahara / Admin</span>
            </td>
            <td>
                <span class="sig-name">Kepala Departemen</span>
            </td>
            <td>
                <span class="sig-name">Peminjam</span>
            </td>
        </tr>
    </table>

</body>
</html>