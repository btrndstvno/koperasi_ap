<?php

namespace App\Imports;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Advanced Import dengan Dynamic Row Scanning
 * 
 * Logic:
 * 1. Scan setiap baris untuk mendeteksi header (mengandung "NIK" dan "POT" atau "JUMLAH")
 * 2. Simpan column mapping secara dinamis
 * 3. Proses data sampai menemukan baris kosong atau header baru
 * 
 * Cocok untuk Excel dengan multiple "blok" data (header di berbagai posisi)
 */
class MonthlyTransactionImport implements ToCollection, WithMultipleSheets
{
    protected string $sheetName;
    protected string $transactionDate;
    
    // Dynamic column mapping (akan diisi saat scanning header)
    protected array $columnMap = [];
    
    // Flag untuk menandakan kita sedang di area data
    protected bool $inDataArea = false;
    
    // Counter untuk skip baris setelah header (jika ada merged cells)
    protected int $skipRowsAfterHeader = 0;
    
    protected array $importResult = [
        'imported' => 0,
        'skipped' => 0,
        'errors' => [],
        'blocks_found' => 0, // Jumlah blok data yang ditemukan
    ];

    /**
     * Keywords untuk mendeteksi header
     */
    const HEADER_KEYWORDS = [
        'nik' => ['NIK', 'NO INDUK', 'NIP', 'NO_INDUK'],
        'name' => ['NAMA', 'NAME', 'NAMA KARYAWAN'],
        'dept' => ['DEPT', 'DEPARTMENT', 'BAGIAN', 'DIVISI'],
        'pot_kop' => ['POT KOP', 'POT_KOP', 'POTONGAN', 'ANGSURAN', 'CICILAN', 'POT. KOP'],
        'iur_kop' => ['IUR KOP', 'IUR_KOP', 'IURAN', 'SIMPANAN', 'IUR. KOP'],
        'saldo' => ['SALDO', 'SALDO KOPRASI', 'SALDO KOPERASI', 'BALANCE'],
        'jumlah' => ['JUMLAH', 'TOTAL', 'AMOUNT'],
    ];

    public function __construct(string $sheetName)
    {
        $this->sheetName = $sheetName;
        $this->transactionDate = $this->parseSheetNameToDate($sheetName);
    }

    /**
     * Specify which sheets to import
     */
    public function sheets(): array
    {
        return [
            $this->sheetName => $this,
        ];
    }

    /**
     * Main collection processing dengan Row Scanning
     */
    public function collection(Collection $rows)
    {
        DB::beginTransaction();

        try {
            foreach ($rows as $rowIndex => $row) {
                $rowNumber = $rowIndex + 1; // 1-based untuk logging
                
                try {
                    // Convert row to array for easier manipulation
                    $rowArray = $row->toArray();
                    
                    // Skip baris setelah header (untuk handle merged cells)
                    if ($this->skipRowsAfterHeader > 0) {
                        $this->skipRowsAfterHeader--;
                        continue;
                    }
                    
                    // STEP 1: Cek apakah ini baris header
                    if ($this->isHeaderRow($rowArray)) {
                        $this->detectColumnMapping($rowArray);
                        $this->inDataArea = true;
                        $this->skipRowsAfterHeader = 1; // Skip 1 baris setelah header (jika ada sub-header)
                        $this->importResult['blocks_found']++;
                        
                        Log::info("Header detected at row {$rowNumber}", $this->columnMap);
                        continue;
                    }
                    
                    // STEP 2: Proses data jika dalam area data
                    if ($this->inDataArea) {
                        $this->processDataRow($rowArray, $rowNumber);
                    }
                    
                } catch (\Exception $e) {
                    $this->importResult['errors'][] = "Baris {$rowNumber}: " . $e->getMessage();
                    $this->importResult['skipped']++;
                    Log::warning("Import row error [{$rowNumber}]: " . $e->getMessage());
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Deteksi apakah baris ini adalah header
     * Harus mengandung "NIK" DAN ("POT" atau "JUMLAH" atau "IUR")
     */
    protected function isHeaderRow(array $row): bool
    {
        $rowText = strtoupper(implode(' ', array_map('strval', $row)));
        
        // Harus ada NIK
        $hasNik = str_contains($rowText, 'NIK') || str_contains($rowText, 'NO INDUK');
        
        // Harus ada salah satu: POT, IUR, JUMLAH
        $hasFinancialCol = str_contains($rowText, 'POT') 
            || str_contains($rowText, 'IUR') 
            || str_contains($rowText, 'JUMLAH')
            || str_contains($rowText, 'POTONGAN')
            || str_contains($rowText, 'IURAN');
        
        return $hasNik && $hasFinancialCol;
    }

    /**
     * Deteksi mapping kolom dari baris header
     */
    protected function detectColumnMapping(array $headerRow): void
    {
        // Reset column map untuk blok baru
        $this->columnMap = [];
        
        foreach ($headerRow as $colIndex => $cellValue) {
            $cellText = strtoupper(trim((string) $cellValue));
            
            if (empty($cellText)) {
                continue;
            }
            
            // Cek setiap keyword
            foreach (self::HEADER_KEYWORDS as $fieldKey => $keywords) {
                foreach ($keywords as $keyword) {
                    if (str_contains($cellText, $keyword) || $cellText === $keyword) {
                        // Jangan override jika sudah ada (ambil yang pertama)
                        if (!isset($this->columnMap[$fieldKey])) {
                            $this->columnMap[$fieldKey] = $colIndex;
                        }
                        break 2;
                    }
                }
            }
        }
        
        Log::info("Column mapping detected", $this->columnMap);
    }

    /**
     * Proses baris data
     */
    protected function processDataRow(array $row, int $rowNumber): void
    {
        // Ambil NIK dari column map
        $nikColIndex = $this->columnMap['nik'] ?? null;
        
        if ($nikColIndex === null) {
            return; // Tidak ada mapping NIK
        }
        
        $nikValue = $row[$nikColIndex] ?? null;
        
        // Validasi NIK: harus ada dan bukan text "Total", "Jumlah", dll
        if (!$this->isValidNik($nikValue)) {
            // Baris kosong atau footer, skip saja
            $this->importResult['skipped']++;
            return;
        }
        
        // Bersihkan NIK (hapus spasi, format sebagai string)
        $nik = $this->cleanNik($nikValue);
        
        // Cari atau buat member
        $member = Member::where('nik', $nik)->first();
        
        if (!$member) {
            // Log warning, tapi jangan buat member baru (untuk keamanan)
            $this->importResult['errors'][] = "Baris {$rowNumber}: NIK '{$nik}' tidak ditemukan di database.";
            $this->importResult['skipped']++;
            return;
        }
        
        // Ambil nilai POT_KOP dan IUR_KOP
        $potKop = $this->getNumericValue($row, 'pot_kop');
        $iurKop = $this->getNumericValue($row, 'iur_kop');
        
        // Jika keduanya 0 atau tidak ada, coba cari kolom "jumlah" sebagai fallback
        if ($potKop <= 0 && $iurKop <= 0) {
            $jumlah = $this->getNumericValue($row, 'jumlah');
            if ($jumlah > 0) {
                // Asumsikan "jumlah" adalah total potongan (POT_KOP)
                $potKop = $jumlah;
            }
        }
        
        // Skip jika tidak ada transaksi
        if ($potKop <= 0 && $iurKop <= 0) {
            $this->importResult['skipped']++;
            return;
        }
        
        $hasTransaction = false;
        
        // Process IUR_KOP (Simpanan)
        if ($iurKop > 0) {
            $this->processSavingDeposit($member, $iurKop);
            $hasTransaction = true;
        }
        
        // Process POT_KOP (Angsuran Pinjaman)
        if ($potKop > 0) {
            $this->processLoanRepayment($member, $potKop, $rowNumber);
            $hasTransaction = true;
        }
        
        if ($hasTransaction) {
            $this->importResult['imported']++;
        }
    }

    /**
     * Validasi apakah nilai NIK valid (bukan kosong, bukan text footer)
     */
    protected function isValidNik(mixed $value): bool
    {
        if (empty($value)) {
            return false;
        }
        
        $strValue = strtoupper(trim((string) $value));
        
        // Skip jika berisi kata-kata footer/summary
        $invalidKeywords = ['TOTAL', 'JUMLAH', 'SUBTOTAL', 'GRAND', 'SUM', 'RATA', 'AVERAGE', 'KETERANGAN'];
        
        foreach ($invalidKeywords as $keyword) {
            if (str_contains($strValue, $keyword)) {
                return false;
            }
        }
        
        // NIK harus mengandung angka
        if (!preg_match('/\d/', $strValue)) {
            return false;
        }
        
        return true;
    }

    /**
     * Bersihkan dan format NIK
     */
    protected function cleanNik(mixed $value): string
    {
        $nik = trim((string) $value);
        
        // Hapus .0 jika Excel membaca sebagai float
        if (preg_match('/^(\d+)\.0+$/', $nik, $matches)) {
            $nik = $matches[1];
        }
        
        return $nik;
    }

    /**
     * Ambil nilai numerik dari row berdasarkan field key
     */
    protected function getNumericValue(array $row, string $fieldKey): float
    {
        $colIndex = $this->columnMap[$fieldKey] ?? null;
        
        if ($colIndex === null) {
            return 0;
        }
        
        $value = $row[$colIndex] ?? null;
        
        return $this->parseAmount($value);
    }

    /**
     * Process simpanan (IUR_KOP)
     */
    protected function processSavingDeposit(Member $member, float $amount): void
    {
        // Tambah saldo simpanan
        $member->increment('savings_balance', $amount);

        // Buat transaksi
        Transaction::create([
            'member_id' => $member->id,
            'loan_id' => null,
            'transaction_date' => $this->transactionDate,
            'type' => Transaction::TYPE_SAVING_DEPOSIT,
            'amount_saving' => $amount,
            'amount_principal' => 0,
            'amount_interest' => 0,
            'total_amount' => $amount,
            'notes' => "Import: {$this->sheetName}",
        ]);
    }

    /**
     * Process angsuran pinjaman (POT_KOP)
     */
    protected function processLoanRepayment(Member $member, float $amount, int $rowNumber): void
    {
        // Cari pinjaman aktif
        $activeLoan = $member->activeLoans()->first();

        if (!$activeLoan) {
            // Jika tidak ada pinjaman aktif, buat pinjaman baru dengan asumsi
            // Ini untuk handle data legacy dimana pinjaman belum tercatat
            $this->importResult['errors'][] = "Baris {$rowNumber}: NIK '{$member->nik}' tidak punya pinjaman aktif. Potongan {$amount} diabaikan.";
            return;
        }

        // Kurangi sisa pokok pinjaman
        $activeLoan->reduceRemainingPrincipal($amount);

        // Buat transaksi pembayaran
        Transaction::create([
            'member_id' => $member->id,
            'loan_id' => $activeLoan->id,
            'transaction_date' => $this->transactionDate,
            'type' => Transaction::TYPE_LOAN_REPAYMENT,
            'amount_saving' => 0,
            'amount_principal' => $amount,
            'amount_interest' => 0,
            'total_amount' => $amount,
            'notes' => "Import: {$this->sheetName}",
        ]);
    }

    /**
     * Parse amount dari berbagai format Excel
     */
    protected function parseAmount(mixed $value): float
    {
        if (empty($value)) {
            return 0;
        }

        // Jika sudah numeric, langsung return
        if (is_numeric($value)) {
            return max(0, (float) $value);
        }

        // Remove currency symbols, spaces, and handle various formats
        $cleaned = preg_replace('/[^\d.,\-]/', '', (string) $value);
        
        // Handle Indonesian format (1.000.000,50)
        if (preg_match('/^\d{1,3}(\.\d{3})+,\d{2}$/', $cleaned)) {
            $cleaned = str_replace('.', '', $cleaned);
            $cleaned = str_replace(',', '.', $cleaned);
        }
        // Handle format with comma as thousand separator (1,000,000.50)
        elseif (preg_match('/^\d{1,3}(,\d{3})+\.\d{2}$/', $cleaned)) {
            $cleaned = str_replace(',', '', $cleaned);
        }
        // Handle simple comma decimal (1000,50)
        elseif (strpos($cleaned, ',') !== false && strpos($cleaned, '.') === false) {
            $cleaned = str_replace(',', '.', $cleaned);
        }
        // Handle Indonesian without decimal (1.000.000)
        elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $cleaned)) {
            $cleaned = str_replace('.', '', $cleaned);
        }

        return max(0, (float) $cleaned);
    }

    /**
     * Parse sheet name ke tanggal transaksi
     * Format: "01 2026" atau "JAN 2026" atau "JANUARI 2026"
     */
    protected function parseSheetNameToDate(string $sheetName): string
    {
        $parts = preg_split('/[\s\-_]+/', trim($sheetName));
        
        if (count($parts) < 2) {
            // Fallback ke hari ini jika format tidak dikenali
            return now()->format('Y-m-01');
        }
        
        $monthPart = strtoupper($parts[0]);
        $yearPart = $parts[1];
        
        // Mapping nama bulan Indonesia
        $monthMap = [
            'JAN' => '01', 'JANUARI' => '01', '01' => '01', '1' => '01',
            'FEB' => '02', 'FEBRUARI' => '02', '02' => '02', '2' => '02',
            'MAR' => '03', 'MARET' => '03', '03' => '03', '3' => '03',
            'APR' => '04', 'APRIL' => '04', '04' => '04', '4' => '04',
            'MEI' => '05', 'MAY' => '05', '05' => '05', '5' => '05',
            'JUN' => '06', 'JUNI' => '06', '06' => '06', '6' => '06',
            'JUL' => '07', 'JULI' => '07', '07' => '07', '7' => '07',
            'AGU' => '08', 'AGUSTUS' => '08', 'AUG' => '08', '08' => '08', '8' => '08',
            'SEP' => '09', 'SEPTEMBER' => '09', '09' => '09', '9' => '09',
            'OKT' => '10', 'OKTOBER' => '10', 'OCT' => '10', '10' => '10',
            'NOV' => '11', 'NOVEMBER' => '11', '11' => '11',
            'DES' => '12', 'DESEMBER' => '12', 'DEC' => '12', '12' => '12',
        ];
        
        $month = $monthMap[$monthPart] ?? '01';
        $year = is_numeric($yearPart) ? $yearPart : date('Y');
        
        return "{$year}-{$month}-01";
    }

    /**
     * Get import result summary
     */
    public function getImportResult(): array
    {
        return $this->importResult;
    }
}
