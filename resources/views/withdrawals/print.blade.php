<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulir Pengambilan Saldo - {{ $withdrawal->member->name }}</title>
    <style>
        @page {
            size: 215mm 330mm; /* F4 / Folio */
            margin: 15mm 20mm;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            color: #000;
            line-height: 1.6;
        }

        .no-print {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 1000;
        }

        .no-print button {
            padding: 8px 12px;
            background: #333;
            color: #fff;
            border: none;
            cursor: pointer;
            border-radius: 4px;
        }

        @media print {
            .no-print { display: none; }
        }

        .header {
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 14pt;
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
            display: inline-block;
            background-color: #e2eeb8; /* Match photo highlight */
            padding: 2px 5px;
        }

        .header h2 {
            font-size: 14pt;
            font-weight: bold;
            margin: 5px 0 0 0;
            text-transform: uppercase;
            display: inline-block;
            background-color: #e2eeb8; 
            padding: 2px 5px;
        }

        .content {
            margin-top: 30px;
        }

        .row {
            display: flex;
            align-items: baseline;
            margin-bottom: 15px; 
        }

        .label {
            width: 180px;
            font-weight: normal;
        }

        .separator {
            width: 20px;
            text-align: center;
        }

        .value {
            flex: 1;
            border-bottom: 1px solid #000;
            font-weight: bold;
            padding-left: 5px;
        }

        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 60px;
            width: 100%;
        }

        .sig-column {
            width: 280px; /* Fixed width to control line length (not too long) */
        }

        .sig-title {
            margin-bottom: 0px;
            font-weight: bold;
        }

        .sig-date {
            margin-bottom: 5px;
        }

        .sig-line {
            border-top: 1px solid #000;
            width: 100%;
            margin-top: 100px; /* Large vertical space for signature */
        }

    </style>
</head>
<body>
    @php
        function penyebut($nilai) {
            $nilai = abs($nilai);
            $huruf = array("", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas");
            $temp = "";
            if ($nilai < 12) {
                $temp = " ". $huruf[$nilai];
            } else if ($nilai <20) {
                $temp = penyebut($nilai - 10). " belas";
            } else if ($nilai < 100) {
                $temp = penyebut($nilai/10)." puluh". penyebut($nilai % 10);
            } else if ($nilai < 200) {
                $temp = " seratus" . penyebut($nilai - 100);
            } else if ($nilai < 1000) {
                $temp = penyebut($nilai/100) . " ratus" . penyebut($nilai % 100);
            } else if ($nilai < 2000) {
                $temp = " seribu" . penyebut($nilai - 1000);
            } else if ($nilai < 1000000) {
                $temp = penyebut($nilai/1000) . " ribu" . penyebut($nilai % 1000);
            } else if ($nilai < 1000000000) {
                $temp = penyebut($nilai/1000000) . " juta" . penyebut($nilai % 1000000);
            }
            return $temp;
        }

        function terbilang($nilai) {
            if($nilai<0) {
                $hasil = "minus ". trim(penyebut($nilai));
            } else {
                $hasil = trim(penyebut($nilai));
            }
            return $hasil . " rupiah";
        }
    @endphp

    @if(!request('modal'))
    <div class="no-print">
        <button onclick="window.print()">Print</button>
    </div>
    @endif

    <div class="header">
        <h1>FORMULIR PENGAMBILAN SALDO KOPERASI</h1>
        <br>
        <h2>KARYAWAN PT. ADIPUTRO WIRASEJATI MALANG</h2>
    </div>

    <div class="content">
        <p style="margin-bottom: 20px;">Yang bertandatangan dibawah ini,</p>
        
        <div class="row">
            <div class="label">NAMA</div>
            <div class="separator">:</div>
            <div class="value">{{ $withdrawal->member->name }}</div>
        </div>

        <div class="row">
            <div class="label">NIK</div>
            <div class="separator">:</div>
            <div class="value">{{ $withdrawal->member->nik }}</div>
        </div>

        <div class="row">
            <div class="label">Departemen</div>
            <div class="separator">:</div>
            <div class="value">{{ $withdrawal->member->dept }}</div>
        </div>

        <div class="row">
            <div class="label">Pengajuan tgl</div>
            <div class="separator">:</div>
            <div class="value" style="border-bottom: none;">
                @php
                    $d = $withdrawal->request_date->format('d');
                    $m = $withdrawal->request_date->translatedFormat('F'); 
                    $y = $withdrawal->request_date->format('Y');
                @endphp
                <span style="border-bottom: 1px solid #000; padding: 0 10px; display: inline-block; min-width: 30px; text-align: center;">{{ $d }}</span>
                -
                <span style="border-bottom: 1px solid #000; padding: 0 10px; display: inline-block; min-width: 100px; text-align: center;">{{ $m }}</span>
                -
                <span style="border-bottom: 1px solid #000; padding: 0 10px; display: inline-block; min-width: 50px; text-align: center;">{{ $y }}</span>
            </div>
        </div>

        <div class="row" style="margin-bottom: 5px;">
            <div class="label">Ambil saldo sebanyak</div>
            <div class="separator">:</div>
            <div class="value">Rp. {{ number_format($withdrawal->amount, 0, ',', '.') }}</div>
        </div>
        
        <div style="margin-left: 200px; margin-bottom: 25px;">
            ( <span style="border-bottom: 1px solid #000; padding: 0 5px;">{{ terbilang($withdrawal->amount) }}</span> )
        </div>

        <p style="margin-bottom: 15px;">Dengan catatan,</p>
        
        <div class="row">
            <div class="label">Saldo akhir bulan</div>
            <div style="width: auto; margin-right: 10px;">
                <span style="border-bottom: 1px solid #000; padding: 0 10px;">{{ $withdrawal->created_at->translatedFormat('F') }}</span>
                <span style="border-bottom: 1px solid #000; padding: 0 10px;">{{ $withdrawal->created_at->format('Y') }}</span>
            </div>
        </div>

        <div class="row">
            <div class="label">tercatat sebanyak Rp.</div>
            <div class="value" style="flex: inherit; min-width: 200px;">
                {{ number_format($withdrawal->member->savings_balance, 0, ',', '.') }}
            </div>
        </div>
    </div>

    <!-- Signatures -->
    <div class="signature-section">
        <!-- Left: Ttd / Tgl Pengajuan -->
        <div class="sig-column">
            <div class="sig-title">Ttd</div>
            <div class="sig-title">Tgl Pengajuan</div>
            
            <div class="sig-date">
                ( 
                {{ $d }}
                , 
                {{ $m }} 
                {{ $y }}
                )
            </div>
            
            <!-- Signature Line -->
            <div class="sig-line"></div>
        </div>

        <!-- Right: Tgl Menerima -->
        <div class="sig-column">
            <div class="sig-title">Tgl Menerima</div>
            
            <div class="sig-date">
                ( 
                <span style="display: inline-block; width: 30px; text-align: center;">___</span>
                , 
                <span style="display: inline-block; width: 80px; text-align: center;">________</span> 
                20
                <span style="display: inline-block; width: 30px; text-align: center;">__</span> 
                )
            </div>

            <!-- Signature Line -->
            <div class="sig-line"></div>
        </div>
    </div>

</body>
</html>
