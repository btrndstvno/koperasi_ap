<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulir Pengajuan Pinjaman</title>
    <style>
        @page {
            size: 215mm 330mm; /* F4 */
            margin: 10mm 15mm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            color: #000;
            line-height: 1.3;
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

        /* =============== SECTION 1: PENGAJUAN =============== */
        .section-pengajuan {
            border: none;
            padding-bottom: 5px;
            margin-bottom: 0;
        }

        .header-pengajuan {
            margin-bottom: 10px;
        }
        .header-pengajuan .title-box {
            background-color: #e74c3c;
            color: #fff;
            display: inline-block;
            padding: 3px 10px;
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .header-pengajuan .subtitle-box {
            background-color: #27ae60;
            color: #fff;
            display: inline-block;
            padding: 3px 10px;
            font-size: 11pt;
            font-weight: bold;
        }

        .photo-box {
            float: right;
            width: 70px;
            height: 90px;
            border: 1px solid #000;
            margin-top: 0;
        }

        .content { margin-top: 10px; }

        /* Table Form Layout */
        .form-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .form-table td { padding: 6px 0; vertical-align: bottom; }
        
        .col-label { width: 180px; font-weight: normal; }
        .col-sep { width: 15px; text-align: center; }
        .col-input { border-bottom: 1px solid #000; height: 20px; }

        .amount-line {
            border-bottom: 1px solid #000;
            display: inline-block;
            width: 100%;
            min-height: 20px;
            margin-top: 5px;
        }

        .amount-box-pengajuan {
            border: 1px solid #000;
            padding: 5px 10px;
            margin: 5px 0;
            text-align: center;
        }

        ol { margin: 3px 0; padding-left: 25px; }
        ol li { margin-bottom: 3px; padding-left: 3px; font-size: 9.5pt; }

        /* Signature areas */
        .sig-area {
            width: 100%;
            margin-top: 10px;
        }
        .sig-area td {
            vertical-align: top;
        }
        .sig-spacer-small {
            height: 80px;
        }

        /* Horizontal divider between sections */
        .section-divider {
            border-top: 1px solid #000;
            margin: 8px 0;
        }

        /* =============== SECTION 2: PERSETUJUAN =============== */
        .section-persetujuan {
            padding-top: 5px;
        }

        .header-persetujuan {
            margin-bottom: 10px;
        }
        .header-persetujuan .title-box {
            background-color: #27ae60;
            color: #fff;
            display: inline-block;
            padding: 3px 10px;
            font-size: 12pt;
            font-weight: bold;
        }

        .persetujuan-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 8px; 
        }
        .persetujuan-table td { 
            padding: 8px 0; 
            vertical-align: bottom; 
        }
        .persetujuan-label { 
            width: 250px; 
        }
        .persetujuan-sep { 
            width: 30px; 
            text-align: left; 
        }
        .persetujuan-value { 
            border-bottom: 1px solid #000; 
        }
        .persetujuan-minus {
            width: 30px;
            text-align: right;
            padding-right: 8px !important;
        }

        .sig-name-line {
            border-top: none;
            display: inline-block;
            padding-top: 3px;
            font-size: 9.5pt;
        }
    </style>
</head>
<body>

    <!-- ==================== SECTION 1: FORMULIR PENGAJUAN PINJAMAN ==================== -->
    <div class="section-pengajuan">
        <div class="header-pengajuan">
            <div class="photo-box"></div>
            <div class="title-box">FORMULIR PENGAJUAN PINJAMAN</div><br>
            <div class="subtitle-box">KOPERASI KARYAWAN PT ADIPUTRO WIRASEJATI<br>MALANG</div>
        </div>

        <div style="clear: both;"></div>

        <div class="content">
            <p style="margin: 5px 0;">Yang bertandatangan dibawah ini,</p>

            <table class="form-table">
                <tr>
                    <td class="col-label">NAMA</td>
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

            <table class="form-table" style="margin-bottom: 5px;">
                <tr>
                    <td style="font-weight: bold; white-space: nowrap;">Mengajukan Pinjaman sebesar : Rp.</td>
                    <td class="col-input" style="width: 100%;"></td>
                </tr>
            </table>

            <div class="amount-box-pengajuan">
                <span class="amount-line"></span>
            </div>

            <p style="margin: 5px 0;">Dan, menyetujui SYARAT Pengajuan PINJAMAN yaitu :</p>

            <ol>
                <li>Bersedia dikurangi/dipotong sebagian dari gaji untuk membayar angsuran setiap bulannya sebanyak 10x dari Jumlah Pengajuan Pinjaman yang disetujui.</li>
                <li>Bersedia dipotong dari gaji untuk membayar seluruh sisa pinjaman bila status keanggotaannya sudah tidak aktif lagi.</li>
                <li>Menerima jumlah pinjaman yang di setujui oleh Bendahara Koperasi.</li>
                <li>Tidak memiliki tanggungan pinjaman yang masih sedang berjalan.</li>
            </ol>

            <table class="sig-area" style="margin-top: 15px;">
                <tr>
                    <td style="width: 50%; text-align: center;">
                        <p style="margin: 3px 0;">Malang, _____, _____, 20 ___</p>
                    </td>
                    <td style="width: 50%; text-align: center;">
                        <p style="margin: 3px 0;">Mengetahui,</p>
                    </td>
                </tr>
                <tr>
                    <td class="sig-spacer-small"></td>
                    <td class="sig-spacer-small"></td>
                </tr>
                <tr>
                    <td style="text-align: center;">
                        <p style="margin: 0;">(Peminjam)</p>
                    </td>
                    <td style="text-align: center;">
                        <p style="margin: 0;">(Kepala Departemen)</p>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- ==================== DIVIDER ==================== -->
    <div class="section-divider" style="margin-top: 60px;"></div>

    <!-- ==================== SECTION 2: FORMULIR PERSETUJUAN PINJAMAN ==================== -->
    <div class="section-persetujuan">
        <div class="header-persetujuan">
            <div class="title-box">FORMULIR PERSETUJUAN PINJAMAN</div>
        </div>

        <table class="persetujuan-table">
            <tr>
                <td class="persetujuan-label">Nilai / Jumlah Pinjaman</td>
                <td class="persetujuan-sep">: Rp.</td>
                <td class="persetujuan-value"></td>
            </tr>
            <tr>
                <td class="persetujuan-label">Bunga ( 1% x 10 bulan )</td>
                <td class="persetujuan-sep">: Rp.</td>
                <td class="persetujuan-value"></td>
            </tr>
            <tr>
                <td class="persetujuan-label">Administrasi ( 1% )</td>
                <td class="persetujuan-sep">: Rp.</td>
                <td class="persetujuan-value"></td>
                <!-- <td style="width: 30px; text-align: center;">(-)</td> -->
            </tr>
            <tr>
                <td class="persetujuan-label"><strong>JUMLAH YANG DISERAHTERIMAKAN</strong></td>
                <td class="persetujuan-sep">: Rp.</td>
                <td class="persetujuan-value"></td>
            </tr>
        </table>

        <table class="persetujuan-table" style="margin-top: 5px;">
            <tr>
                <td class="persetujuan-label">ANGSURAN TIAP BULAN ( 10 X )</td>
                <td class="persetujuan-sep">: Rp.</td>
                <td class="persetujuan-value"></td>
            </tr>
        </table>

        <table class="sig-area" style="margin-top: 30px;">
            <tr>
                <td style="width: 50%; text-align: center;">
                    <p style="margin: 3px 0;">Disetujui Oleh,</p>
                </td>
                <td style="width: 50%; text-align: center;">
                    <p style="margin: 3px 0;">Diterima Oleh,</p>
                </td>
            </tr>
            <tr>
                <td></td>
                <td style="text-align: center;">
                    <p style="margin: 3px 0;">Tgl _____, _____, 20 ___</p>
                </td>
            </tr>
            <tr>
                <td class="sig-spacer-small"></td>
                <td class="sig-spacer-small"></td>
            </tr>
            <tr>
                <td style="text-align: center;">
                    <span class="sig-name-line">(Bendahara / Admin Koperasi)</span>
                </td>
                <td style="text-align: center;">
                    <span class="sig-name-line">(Peminjam)</span>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>