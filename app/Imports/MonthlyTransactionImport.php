<?php

namespace App\Imports;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Import Excel Koperasi dengan format:
 * - Baris 1: Tanggal transaksi (e.g., "31 JANUARI 2026")
 * - Section MANAGER, BANGUNAN, CSD, OFFICE/KARYAWAN
 * - Kolom: NIK, NAMA, DEPT, POT KOP, IUR KOP, IUR TUNAI, JUMLAH, SISA PINJAMAN, SALDO, Adiputro CSD, SISA CICILAN
 */
class MonthlyTransactionImport implements ToCollection, WithMultipleSheets
{
    protected string $sheetName;
    protected ?string $transactionDate = null;
    protected ?string $currentSection = null;
    protected array $columnMap = [];
    protected bool $inDataArea = false;
    
    protected array $importResult = [
        'imported' => 0,
        'skipped' => 0,
        'errors' => [],
        'members_created' => 0,
        'members_updated' => 0,
        'loans_created' => 0,
    ];

    /**
     * Keywords untuk deteksi kolom header
     * PENTING: sisa_cicilan harus sebelum sisa_pinjaman karena "SISA CICILAN" mengandung "SISA"
     */
    const HEADER_KEYWORDS = [
        'nik' => ['NIK', 'NO INDUK', 'NIP'],
        'name' => ['NAMA', 'NAMA ANGGOTA', 'NAMA KARYAWAN'],
        'dept' => ['DEPT', 'DEPARTMENT', 'BAGIAN'],
        'pot_kop' => ['POT KOP', 'POT_KOP', 'POTONGAN', 'POT. KOP', 'POT'],
        'iur_kop' => ['IUR KOP', 'IUR_KOP', 'IURAN', 'IUR. KOP'],
        'iur_tunai' => ['IUR TUNAI', 'IUR_TUNAI', 'TUNAI'],
        'jumlah' => ['JUMLAH', 'TOTAL'],
        'saldo' => ['SALDO', 'SALDO KOPRASI', 'SALDO KOPERASI', 'SALDO KOP'],
        'adiputro_csd' => ['ADIPUTRO', 'CSD', 'ADIPUTRO CSD'],
        // sisa_cicilan HARUS sebelum sisa_pinjaman
        'sisa_cicilan' => ['SISA CICILAN', 'SISA_CICILAN', 'CICILAN'],
        'sisa_pinjaman' => ['SISA PINJAMAN', 'SISA_PINJAMAN', 'SISA HUTANG'],
        // 'SISA' alone will be handled by special logic to avoid matching 'SISA CICILAN'
    ];

    /**
     * Section/Group keywords
     */
    const SECTION_KEYWORDS = [
        'Manager' => ['MANAGER'],
        'Bangunan' => ['BANGUNAN'],
        'CSD' => ['CSD'],
        'Office' => ['OFFICE', 'KARYAWAN'],
    ];

    public function __construct(string $sheetName)
    {
        $this->sheetName = $sheetName;
    }

    public function sheets(): array
    {
        return [
            $this->sheetName => $this,
        ];
    }

    public function collection(Collection $rows)
    {
        DB::beginTransaction();

        try {
            foreach ($rows as $rowIndex => $row) {
                $rowNumber = $rowIndex + 1;
                $rowArray = $row->toArray();
                
                try {
                    // STEP 1: Cari tanggal transaksi di baris awal
                    if ($this->transactionDate === null && $rowNumber <= 5) {
                        $this->detectTransactionDate($rowArray);
                    }
                    
                    // STEP 2: Deteksi section header (MANAGER, BANGUNAN, dll)
                    // Cek section header SELALU - untuk multi-section dalam satu file
                    $detectedSection = $this->detectSectionHeader($rowArray);
                    if ($detectedSection) {
                        $this->currentSection = $detectedSection;
                        $this->columnMap = []; // Reset column map for new section
                        $this->inDataArea = false; // Reset data area flag for new section
                        Log::info("Section detected: {$detectedSection} at row {$rowNumber}");
                        continue;
                    }
                    
                    // STEP 3: Deteksi kolom header
                    if ($this->isHeaderRow($rowArray)) {
                        $this->detectColumnMapping($rowArray);
                        $this->inDataArea = true;
                        // Jika belum ada section, default ke Office
                        if (!$this->currentSection) {
                            $this->currentSection = 'Office';
                            Log::info("No section detected, defaulting to Office");
                        }
                        Log::info("Header detected at row {$rowNumber}", $this->columnMap);
                        continue;
                    }
                    
                    // STEP 4: Proses data row
                    if ($this->inDataArea && $this->currentSection) {
                        $this->processDataRow($rowArray, $rowNumber);
                    }
                    
                } catch (\Exception $e) {
                    $this->importResult['errors'][] = "Baris {$rowNumber}: " . $e->getMessage();
                    $this->importResult['skipped']++;
                    Log::warning("Import row error [{$rowNumber}]: " . $e->getMessage());
                }
            }

            // Fallback jika tanggal tidak terdeteksi
            if ($this->transactionDate === null) {
                $this->transactionDate = $this->parseSheetNameToDate($this->sheetName);
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Deteksi tanggal transaksi dari baris awal (e.g., "31 JANUARI 2026")
     */
    protected function detectTransactionDate(array $row): void
    {
        foreach ($row as $cell) {
            if (empty($cell)) continue;
            
            $cellText = strtoupper(trim((string) $cell));
            
            // Pattern: DD BULAN YYYY atau DD/MM/YYYY
            $monthMap = [
                'JANUARI' => 1, 'FEBRUARI' => 2, 'MARET' => 3, 'APRIL' => 4,
                'MEI' => 5, 'JUNI' => 6, 'JULI' => 7, 'AGUSTUS' => 8,
                'SEPTEMBER' => 9, 'OKTOBER' => 10, 'NOVEMBER' => 11, 'DESEMBER' => 12,
                'JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5,
                'JUN' => 6, 'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10,
                'NOV' => 11, 'DEC' => 12,
            ];
            
            // Cari pattern "DD BULAN YYYY"
            if (preg_match('/(\d{1,2})\s+([A-Z]+)\s+(\d{4})/', $cellText, $matches)) {
                $day = (int) $matches[1];
                $monthName = $matches[2];
                $year = (int) $matches[3];
                
                if (isset($monthMap[$monthName])) {
                    $month = $monthMap[$monthName];
                    $this->transactionDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    Log::info("Transaction date detected: {$this->transactionDate}");
                    return;
                }
            }
        }
    }

    /**
     * Deteksi section header (MANAGER, BANGUNAN, CSD, OFFICE)
     * Section header adalah baris yang HANYA berisi nama section (bukan data row)
     */
    protected function detectSectionHeader(array $row): ?string
    {
        // Ambil 3 sel pertama saja untuk dicek (section header biasanya di kolom A/B)
        $firstCells = array_slice($row, 0, 3);
        $firstCellsText = strtoupper(implode(' ', array_map('strval', $firstCells)));
        
        // Skip jika ada NIK (ini baris header kolom)
        if (str_contains($firstCellsText, 'NIK')) {
            return null;
        }
        
        // Hitung sel yang tidak kosong di seluruh row
        $nonEmptyCells = count(array_filter($row, fn($v) => !empty(trim((string)$v))));
        
        // Section header biasanya hanya punya 1-3 sel (nama section, mungkin merged)
        // Data row punya banyak sel (NIK, Nama, Dept, dll)
        if ($nonEmptyCells > 3) {
            return null; // Terlalu banyak sel, kemungkinan data row
        }
        
        // Cek apakah sel pertama adalah angka (kemungkinan NIK atau nomor urut)
        $firstCell = trim((string) ($row[0] ?? ''));
        if (!empty($firstCell) && preg_match('/^\d+$/', $firstCell)) {
            return null; // Sel pertama adalah angka, kemungkinan NIK atau No.
        }
        
        foreach (self::SECTION_KEYWORDS as $section => $keywords) {
            foreach ($keywords as $keyword) {
                // Cari keyword HANYA di 3 sel pertama
                if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/', $firstCellsText)) {
                    return $section;
                }
            }
        }
        
        return null;
    }

    /**
     * Deteksi apakah baris ini adalah header kolom
     */
    protected function isHeaderRow(array $row): bool
    {
        $rowText = strtoupper(implode(' ', array_map('strval', $row)));
        
        $hasNik = str_contains($rowText, 'NIK') || str_contains($rowText, 'NO INDUK');
        $hasFinancialCol = str_contains($rowText, 'POT') 
            || str_contains($rowText, 'IUR') 
            || str_contains($rowText, 'JUMLAH')
            || str_contains($rowText, 'SALDO');
        
        $result = $hasNik && $hasFinancialCol;
        
        // Log untuk debugging
        if ($hasNik || $hasFinancialCol) {
            Log::info("Header check: NIK=" . ($hasNik ? 'Y' : 'N') . ", FIN=" . ($hasFinancialCol ? 'Y' : 'N') . " | " . substr($rowText, 0, 200));
        }
        
        return $result;
    }

    /**
     * Deteksi mapping kolom dari baris header
     * Handle split cells: "IUR KOP" bisa jadi 2 sel: "IUR" dan "KOP"
     */
    protected function detectColumnMapping(array $headerRow): void
    {
        $this->columnMap = [];
        
        // First pass: standard single-cell matching
        foreach ($headerRow as $colIndex => $cellValue) {
            $cellText = strtoupper(trim((string) $cellValue));
            
            if (empty($cellText)) continue;
            
            foreach (self::HEADER_KEYWORDS as $fieldKey => $keywords) {
                foreach ($keywords as $keyword) {
                    if (str_contains($cellText, $keyword) || $cellText === $keyword) {
                        if (!isset($this->columnMap[$fieldKey])) {
                            $this->columnMap[$fieldKey] = $colIndex;
                        }
                        break 2;
                    }
                }
            }
        }
        
        // Second pass: handle split cells for multi-word headers
        // Check adjacent pairs of cells for combined keywords
        $splitKeywords = [
            'iur_kop' => [['IUR', 'KOP']], // "IUR" in one cell, "KOP" in next
            'pot_kop' => [['POT', 'KOP']],
            'iur_tunai' => [['IUR', 'TUNAI']],
            'sisa_pinjaman' => [['SISA', 'PINJAMAN']],
            'sisa_cicilan' => [['SISA', 'CICILAN']],
        ];
        
        foreach ($splitKeywords as $fieldKey => $patterns) {
            if (isset($this->columnMap[$fieldKey])) continue; // Already mapped
            
            foreach ($patterns as $pattern) {
                for ($i = 0; $i < count($headerRow) - 1; $i++) {
                    $cell1 = strtoupper(trim((string) ($headerRow[$i] ?? '')));
                    $cell2 = strtoupper(trim((string) ($headerRow[$i + 1] ?? '')));
                    
                    // Check if two adjacent cells match the pattern
                    if ($cell1 === $pattern[0] && $cell2 === $pattern[1]) {
                        $this->columnMap[$fieldKey] = $i; // Use first cell's position
                        Log::info("Split header detected: {$fieldKey} at columns {$i}-" . ($i+1) . " ({$cell1} {$cell2})");
                        break 2;
                    }
                }
            }
        }
        
        // Special handling: search for iur_kop between pot_kop and iur_tunai/jumlah
        if (!isset($this->columnMap['iur_kop']) && isset($this->columnMap['pot_kop'])) {
            $startCol = $this->columnMap['pot_kop'] + 1;
            $endCol = $this->columnMap['iur_tunai'] ?? ($this->columnMap['jumlah'] ?? count($headerRow));
            
            for ($i = $startCol; $i < $endCol; $i++) {
                $cell = strtoupper(trim((string) ($headerRow[$i] ?? '')));
                
                // Skip empty cells and cells that are just "KOP" (second half of POT KOP)
                if (empty($cell) || $cell === 'KOP') continue;
                
                // If cell contains "IUR" but not "TUNAI", it's iur_kop
                if ($cell === 'IUR' || (str_contains($cell, 'IUR') && !str_contains($cell, 'TUNAI'))) {
                    $this->columnMap['iur_kop'] = $i;
                    Log::info("iur_kop inferred at column {$i} (cell: {$cell})");
                    break;
                }
            }
        }
        
        // Special handling: search for sisa_pinjaman between jumlah and saldo
        if (!isset($this->columnMap['sisa_pinjaman']) && isset($this->columnMap['saldo'])) {
            $startCol = $this->columnMap['jumlah'] ?? $this->columnMap['iur_tunai'] ?? 0;
            $endCol = $this->columnMap['saldo'];
            
            for ($i = $startCol + 1; $i < $endCol; $i++) {
                $cell = strtoupper(trim((string) ($headerRow[$i] ?? '')));
                
                // If cell contains "SISA" but not "CICILAN", it's sisa_pinjaman
                if (str_contains($cell, 'SISA') && !str_contains($cell, 'CICILAN')) {
                    $this->columnMap['sisa_pinjaman'] = $i;
                    Log::info("sisa_pinjaman inferred at column {$i} (cell: {$cell})");
                    break;
                }
            }
        }
        
        Log::info("Column mapping result: " . json_encode($this->columnMap));
    }

    /**
     * Proses baris data
     */
    protected function processDataRow(array $row, int $rowNumber): void
    {
        $nikColIndex = $this->columnMap['nik'] ?? null;
        if ($nikColIndex === null) return;
        
        $nikValue = $row[$nikColIndex] ?? null;
        if (!$this->isValidNik($nikValue)) {
            $this->importResult['skipped']++;
            return;
        }
        
        $nik = $this->cleanNik($nikValue);
        $name = $this->getStringValue($row, 'name');
        $dept = $this->getStringValue($row, 'dept');
        $potKop = $this->getNumericValue($row, 'pot_kop');
        $iurKop = $this->getNumericValue($row, 'iur_kop');
        $iurTunai = $this->getNumericValue($row, 'iur_tunai');
        $sisaPinjaman = $this->getNumericValue($row, 'sisa_pinjaman');
        $saldo = $this->getNumericValue($row, 'saldo');
        $adiputroCsd = $this->getStringValue($row, 'adiputro_csd');
        $sisaCicilan = $this->parseSisaCicilan($row);
        
        // Determine group_tag and employee_status
        $groupTag = $this->currentSection;
        $employeeStatus = 'monthly';
        
        if ($this->currentSection === 'Bangunan') {
            // Untuk Bangunan: jika Adiputro CSD = MINGGUAN, status = weekly
            if (strtoupper($adiputroCsd) === 'MINGGUAN') {
                $employeeStatus = 'weekly';
            }
        } else {
            // Untuk section lain: group_tag dari Adiputro CSD jika valid
            $validTags = ['MANAGER', 'OFFICE', 'CSD'];
            $upperCsd = strtoupper(trim($adiputroCsd));
            if (in_array($upperCsd, $validTags)) {
                $groupTag = ucfirst(strtolower($upperCsd));
                if ($groupTag === 'Csd') $groupTag = 'CSD';
            }
        }
        
        // Find or create member
        $member = Member::where('nik', $nik)->first();
        
        if (!$member) {
            // Create new member
            $member = Member::create([
                'nik' => $nik,
                'name' => $name ?: "Member {$nik}",
                'dept' => $dept ?: '-',
                'group_tag' => $groupTag,
                'employee_status' => $employeeStatus,
                'savings_balance' => $saldo,
            ]);
            $this->importResult['members_created']++;
            Log::info("Created member: {$nik}");
        } else {
            // Update existing member
            $member->update([
                'name' => $name ?: $member->name,
                'dept' => $dept ?: $member->dept,
                'group_tag' => $groupTag,
                'employee_status' => $employeeStatus,
                'savings_balance' => $saldo,
            ]);
            $this->importResult['members_updated']++;
        }
        
        // Handle loan creation based on SISA CICILAN
        if ($sisaCicilan > 0 && $potKop > 0) {
            $this->createLoanFromInstallments($member, $potKop, $sisaCicilan, $rowNumber);
        }
        
        // Create transactions
        $hasTransaction = false;
        $transactionDate = $this->transactionDate ?: now()->format('Y-m-d');
        
        // IUR KOP (Simpanan potong gaji)
        if ($iurKop > 0) {
            Transaction::create([
                'member_id' => $member->id,
                'loan_id' => null,
                'transaction_date' => $transactionDate,
                'type' => Transaction::TYPE_SAVING_DEPOSIT,
                'amount_saving' => $iurKop,
                'amount_principal' => 0,
                'amount_interest' => 0,
                'total_amount' => $iurKop,
                'payment_method' => 'deduction',
                'notes' => "Import: {$this->sheetName}",
            ]);
            $hasTransaction = true;
        }
        
        // IUR TUNAI (Simpanan tunai)
        if ($iurTunai > 0) {
            Transaction::create([
                'member_id' => $member->id,
                'loan_id' => null,
                'transaction_date' => $transactionDate,
                'type' => Transaction::TYPE_SAVING_DEPOSIT,
                'amount_saving' => $iurTunai,
                'amount_principal' => 0,
                'amount_interest' => 0,
                'total_amount' => $iurTunai,
                'payment_method' => 'cash',
                'notes' => "Import Tunai: {$this->sheetName}",
            ]);
            $hasTransaction = true;
        }
        
        // POT KOP (Angsuran pinjaman)
        if ($potKop > 0) {
            $activeLoan = $member->activeLoans()->first();
            
            if ($activeLoan) {
                $activeLoan->reduceRemainingPrincipal($potKop);
                
                Transaction::create([
                    'member_id' => $member->id,
                    'loan_id' => $activeLoan->id,
                    'transaction_date' => $transactionDate,
                    'type' => Transaction::TYPE_LOAN_REPAYMENT,
                    'amount_saving' => 0,
                    'amount_principal' => $potKop,
                    'amount_interest' => 0,
                    'total_amount' => $potKop,
                    'payment_method' => 'deduction',
                    'notes' => "Import: {$this->sheetName}",
                ]);
                $hasTransaction = true;
            }
        }
        
        if ($hasTransaction) {
            $this->importResult['imported']++;
        } else {
            $this->importResult['skipped']++;
        }
    }

    /**
     * Parse SISA CICILAN column (handle #DIV/0! as 0)
     */
    protected function parseSisaCicilan(array $row): int
    {
        $colIndex = $this->columnMap['sisa_cicilan'] ?? null;
        if ($colIndex === null) return 0;
        
        $value = $row[$colIndex] ?? null;
        if (empty($value)) return 0;
        
        $strValue = strtoupper(trim((string) $value));
        
        // #DIV/0!, #VALUE!, #REF!, dll = 0
        if (str_starts_with($strValue, '#')) {
            return 0;
        }
        
        // Coba parse sebagai integer
        if (is_numeric($value)) {
            return max(0, (int) $value);
        }
        
        return 0;
    }

    /**
     * Create loan from installment data
     * Peraturan koperasi: Cicilan selalu 10 bulan
     */
    protected function createLoanFromInstallments(Member $member, float $monthlyInstallment, int $remainingInstallments, int $rowNumber): void
    {
        // Cek apakah sudah punya loan aktif
        if ($member->activeLoans()->exists()) {
            return; // Skip, sudah ada loan
        }
        
        $duration = 10; // Cicilan selalu 10 bulan
        $paidInstallments = $duration - $remainingInstallments;
        
        // Hitung pokok pinjaman: cicilan bulanan * 10
        $loanAmount = $monthlyInstallment * $duration;
        
        // Hitung pokok yang sudah dibayar
        $principalPaid = $monthlyInstallment * $paidInstallments;
        
        // Sisa pokok
        $remainingPrincipal = $loanAmount - $principalPaid;
        
        // Hitung tanggal approve (mundur dari tanggal transaksi file)
        $transactionDate = Carbon::parse($this->transactionDate ?: now());
        $approvedDate = $transactionDate->copy()->subMonths($paidInstallments)->startOfMonth();
        
        // Create loan
        Loan::create([
            'member_id' => $member->id,
            'amount' => $loanAmount,
            'interest_rate' => 0, // Tidak diketahui, asumsikan 0 atau bunga sudah dipotong di awal
            'duration' => $duration,
            'remaining_principal' => $remainingPrincipal,
            'monthly_installment' => $monthlyInstallment,
            'total_interest' => 0,
            'admin_fee' => 0,
            'disbursed_amount' => $loanAmount,
            'status' => Loan::STATUS_ACTIVE,
            'application_date' => $approvedDate,
            'approved_date' => $approvedDate,
        ]);
        
        $this->importResult['loans_created']++;
        Log::info("Created loan for {$member->nik}: amount={$loanAmount}, approved={$approvedDate->format('Y-m-d')}");
    }

    /**
     * Validasi NIK
     */
    protected function isValidNik(mixed $value): bool
    {
        if (empty($value)) return false;
        
        $strValue = strtoupper(trim((string) $value));
        
        $invalidKeywords = ['TOTAL', 'JUMLAH', 'SUBTOTAL', 'GRAND', 'SUM', 'RATA', 'AVERAGE', 'KETERANGAN'];
        
        foreach ($invalidKeywords as $keyword) {
            if (str_contains($strValue, $keyword)) {
                return false;
            }
        }
        
        if (!preg_match('/\d/', $strValue)) {
            return false;
        }
        
        return true;
    }

    /**
     * Bersihkan NIK
     */
    protected function cleanNik(mixed $value): string
    {
        $nik = trim((string) $value);
        
        if (preg_match('/^(\d+)\.0+$/', $nik, $matches)) {
            $nik = $matches[1];
        }
        
        // Pad dengan leading zeros jika perlu (NIK biasanya 6 digit)
        if (is_numeric($nik) && strlen($nik) < 6) {
            $nik = str_pad($nik, 6, '0', STR_PAD_LEFT);
        }
        
        return $nik;
    }

    /**
     * Ambil nilai string dari row
     */
    protected function getStringValue(array $row, string $fieldKey): string
    {
        $colIndex = $this->columnMap[$fieldKey] ?? null;
        if ($colIndex === null) return '';
        
        $value = $row[$colIndex] ?? '';
        return trim((string) $value);
    }

    /**
     * Ambil nilai numerik dari row
     */
    protected function getNumericValue(array $row, string $fieldKey): float
    {
        $colIndex = $this->columnMap[$fieldKey] ?? null;
        if ($colIndex === null) return 0;
        
        $value = $row[$colIndex] ?? null;
        return $this->parseAmount($value);
    }

    /**
     * Parse amount dari berbagai format
     */
    protected function parseAmount(mixed $value): float
    {
        if (empty($value)) return 0;
        if (is_numeric($value)) return max(0, (float) $value);
        
        $cleaned = preg_replace('/[^\d.,\-]/', '', (string) $value);
        
        // Indonesian format (1.000.000,50)
        if (preg_match('/^\d{1,3}(\.\d{3})+,\d{2}$/', $cleaned)) {
            $cleaned = str_replace('.', '', $cleaned);
            $cleaned = str_replace(',', '.', $cleaned);
        }
        // US format (1,000,000.50)
        elseif (preg_match('/^\d{1,3}(,\d{3})+\.\d{2}$/', $cleaned)) {
            $cleaned = str_replace(',', '', $cleaned);
        }
        // Simple comma decimal (1000,50)
        elseif (strpos($cleaned, ',') !== false && strpos($cleaned, '.') === false) {
            $cleaned = str_replace(',', '.', $cleaned);
        }
        // Indonesian without decimal (1.000.000)
        elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $cleaned)) {
            $cleaned = str_replace('.', '', $cleaned);
        }
        
        return max(0, (float) $cleaned);
    }

    /**
     * Parse sheet name ke tanggal (fallback)
     */
    protected function parseSheetNameToDate(string $sheetName): string
    {
        $parts = preg_split('/[\s\-_]+/', trim($sheetName));
        
        if (count($parts) < 2) {
            return now()->format('Y-m-01');
        }
        
        $monthPart = strtoupper($parts[0]);
        $yearPart = $parts[1];
        
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
