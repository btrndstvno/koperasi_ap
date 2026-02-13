<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulir Penarikan Saldo</title>
    <style>
        @page {
            size: 215mm 330mm; /* F4 */
            margin: 15mm 20mm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            color: #000;
            line-height: 1.4; /* [REVISI] Teks lebih rapat */
            margin: 0; padding: 0;
        }
        .no-print {
            text-align: center;
            margin-bottom: 15px;
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

        .header { margin-bottom: 15px; margin-top: 0; } /* [REVISI] Jarak bawah judul dikurangi */
        .header h1 { font-size: 14pt; font-weight: bold; margin: 0; background-color: #e2eeb8; display: inline-block; padding: 2px 5px; text-transform: uppercase; }
        .header h2 { font-size: 14pt; font-weight: bold; margin: 5px 0 0 0; background-color: #e2eeb8; display: inline-block; padding: 2px 5px; text-transform: uppercase; }

        .form-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .form-table td { padding: 4px 0; vertical-align: bottom; } /* [REVISI] Jarak antar baris (NAMA ke NIK) sangat dirapatkan */
        
        .col-label { width: 180px; }
        .col-sep { width: 20px; text-align: center; }
        .col-input { border-bottom: 1px solid #000; height: 18px; } /* [REVISI] Tinggi garis dirapatkan */

        .signature-section {
            display: flex; justify-content: space-between; margin-top: 30px; /* [REVISI] Jarak ke TTD didekatkan */
        }
        .sig-box { width: 300px; text-align: center; }
        .sig-line { border-top: 1px solid #000; margin-top: 100px; } /* [REVISI] Jarak tinggi TTD dikurangi */
    </style>
</head>
<body>
    <div class="header">
        <h1>FORMULIR PENGAMBILAN SALDO KOPERASI</h1><br>
        <h2>KARYAWAN PT. ADIPUTRO WIRASEJATI MALANG</h2>
    </div>

    <div class="content">
        <p style="margin-bottom: 10px;">Yang bertandatangan dibawah ini,</p>
        
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
            <tr>
                <td class="col-label">Ambil saldo sebanyak</td>
                <td class="col-sep">:</td>
                <td class="col-input" style="font-weight: bold;">Rp. </td>
            </tr>
        </table>
        
        <div style="margin-left: 200px; margin-bottom: 15px;">
            ( <span style="border-bottom: 1px solid #000; display: inline-block; width: 350px;">&nbsp;</span> )
        </div>

        <p style="margin-bottom: 10px;">Dengan catatan,</p>
        
        <table class="form-table">
            <tr>
                <td class="col-label">Saldo akhir bulan</td>
                <td class="col-sep"></td>
                <td>
                    <span style="border-bottom: 1px solid #000; padding: 0 40px;"></span>
                    <span style="border-bottom: 1px solid #000; padding: 0 15px;">20</span>
                </td>
            </tr>
            <tr>
                <td class="col-label">Tercatat sebanyak</td>
                <td class="col-sep">:</td>
                <td class="col-input" style="font-weight: bold;">Rp. </td>
            </tr>
        </table>
    </div>

    <div class="signature-section">
        <div class="sig-box">
            <strong>Ttd / Tgl Pengajuan</strong>
            <p style="margin: 5px 0;">( ........... , ........................ 20... )</p>
            <div class="sig-line"></div>
            <div style="margin-top: 5px;"></div>
        </div>

        <div class="sig-box">
            <strong>Tgl Menerima</strong>
            <p style="margin: 5px 0;">( ........... , ........................ 20... )</p>
            <div class="sig-line"></div>
            <div style="margin-top: 5px;"></div>
        </div>
    </div>
</body>
</html>