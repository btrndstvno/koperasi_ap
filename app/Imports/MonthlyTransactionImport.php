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
     * PENTING: adiputro_csd harus di akhir untuk menghindari konflik dengan section 'CSD'
     */
    const HEADER_KEYWORDS = [
        'nik' => ['NIK', 'NO INDUK', 'NIP'],
        'name' => ['NAMA', 'NAMA ANGGOTA', 'NAMA KARYAWAN'],
        'dept' => ['DEPT', 'DEPARTMENT', 'BAGIAN'],
        'pot_kop' => ['POT KOP', 'POT_KOP', 'POTONGAN', 'POT. KOP', 'POT'],
        'iur_kop' => ['IUR KOP', 'IUR_KOP', 'IURAN', 'IUR. KOP'],
        'iur_tunai' => ['IUR TUNAI', 'IUR_TUNAI', 'TUNAI'],
        'jumlah' => ['JUMLAH', 'TOTAL'],
        'saldo' => ['SALDO', 'SALDO KOPRASI', 'SALDO KOPERASI', 'SALDO KOP', 'KOPRASI'],
        // sisa_cicilan HARUS sebelum sisa_pinjaman - tambah variasi "SISA" dan "CICILAN" terpisah
        'sisa_cicilan' => ['SISA CICILAN', 'SISA_CICILAN', 'CICILAN', 'SISA CIC'],
        'sisa_pinjaman' => ['SISA PINJAMAN', 'SISA_PINJAMAN', 'SISA HUTANG'],
        // adiputro_csd di akhir - mencakup berbagai format header
        'adiputro_csd' => ['ADIPUTRO CSD', 'ADIPUTRO', 'GRUP', 'GROUP TAG', 'TAG'],
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
        
        // Special handling: search for sisa_cicilan after saldo or adiputro_csd column
        // It typically appears at the end of the row
        if (!isset($this->columnMap['sisa_cicilan'])) {
            $startCol = max(
                $this->columnMap['saldo'] ?? 0,
                $this->columnMap['adiputro_csd'] ?? 0
            );
            
            for ($i = $startCol + 1; $i < count($headerRow); $i++) {
                $cell = strtoupper(trim((string) ($headerRow[$i] ?? '')));
                
                // Deteksi variasi header "SISA CICILAN" atau "CICILAN" saja
                if (str_contains($cell, 'CICILAN') || $cell === 'SISA') {
                    $this->columnMap['sisa_cicilan'] = $i;
                    Log::info("sisa_cicilan inferred at column {$i} (cell: {$cell})");
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
        
        // Debug logging for sisa_cicilan detection
        Log::info("Row {$rowNumber} - NIK={$nik}, potKop={$potKop}, sisaPinjaman={$sisaPinjaman}, saldo={$saldo}, sisaCicilan={$sisaCicilan}");
        Log::debug("Row {$rowNumber} raw data: " . json_encode(array_slice($row, 0, 15)));
        
        // Determine group_tag and employee_status
        $groupTag = $this->currentSection ?? 'Office';
        $employeeStatus = 'monthly';
        $csdValue = $adiputroCsd; // Simpan nilai asli untuk field csd
        
        if ($this->currentSection === 'Bangunan') {
            // Untuk Bangunan: jika Adiputro CSD = MINGGUAN, status = weekly
            if (strtoupper($adiputroCsd) === 'MINGGUAN') {
                $employeeStatus = 'weekly';
            }
        } else {
            // Untuk section lain: group_tag dari Adiputro CSD jika valid
            // Mapping berbagai format ke group_tag yang valid
            $upperCsd = strtoupper(trim($adiputroCsd));
            $tagMapping = [
                'CSD' => 'CSD',
                'MANAGER' => 'Manager', 
                'MGR' => 'Manager',
                'OFFICE' => 'Office',
                'OFC' => 'Office',
                'KARYAWAN' => 'Office',
            ];
            
            if (isset($tagMapping[$upperCsd])) {
                $groupTag = $tagMapping[$upperCsd];
            }
        }
        
        Log::info("Processing row {$rowNumber}: NIK={$nik}, adiputroCsd={$adiputroCsd}, groupTag={$groupTag}");
        
        // Find or create member
        $member = Member::where('nik', $nik)->first();
        $isNewMember = false;
        
        if (!$member) {
            // Create new member
            $member = Member::create([
                'nik' => $nik,
                'name' => $name ?: "Member {$nik}",
                'dept' => $dept ?: '-',
                'group_tag' => $groupTag,
                'csd' => $csdValue, // Simpan nilai kolom CSD/Adiputro
                'employee_status' => $employeeStatus,
                'savings_balance' => $saldo,
            ]);
            $this->importResult['members_created']++;
            $isNewMember = true;
            Log::info("Created member: {$nik} with group_tag={$groupTag}, csd={$csdValue}");
        } else {
            // Update existing member
            $member->update([
                'name' => $name ?: $member->name,
                'dept' => $dept ?: $member->dept,
                'group_tag' => $groupTag,
                'csd' => $csdValue, // Update nilai kolom CSD/Adiputro
                'employee_status' => $employeeStatus,
                'savings_balance' => $saldo,
            ]);
            $this->importResult['members_updated']++;
            Log::info("Updated member: {$nik} with group_tag={$groupTag}, csd={$csdValue}");
        }
        
        // Handle loan creation based on POT KOP (angsuran pinjaman)
        // POT KOP > 0 berarti ada cicilan pinjaman
        $loanJustCreated = false;
        
        if ($potKop > 0) {
            // sisaCicilan dari parseSisaCicilan():
            // -1 = kolom tidak ada atau tidak valid
            //  0 = "-" di Excel (cicilan selesai/lunas bulan ini - pembayaran terakhir)
            // >0 = sisa cicilan setelah pembayaran ini
            
            $forcePayment = false;
            $calculatedSisaCicilan = -1;

            if ($sisaCicilan === 0) {
                // Jika "-" (0), berarti bulan ini LUNAS.
                // Kita buat loan dengan sisa 1 cicilan, LALU paksa transaksi pembayaran dijalankan
                // sehingga status akhir di sistem menjadi LUNAS (0 sisa).
                $calculatedSisaCicilan = 1;
                $forcePayment = true;
                Log::info("Row {$rowNumber}: sisa cicilan = '-' (lunas bulan ini). Setting sisa=1 dan force payment.");
            } else {
                $calculatedSisaCicilan = $sisaCicilan > 0 ? $sisaCicilan : -1;
            }
            
            // Fallback: hitung dari sisa pinjaman sisaPinjaman
            // Jika sisaPinjaman > 0, kita hitung
            // Jika sisaPinjaman == 0, berarti Lunas (1 cicilan sisa)
            if ($calculatedSisaCicilan <= 0) {
                if ($sisaPinjaman > 0) {
                    // Hitung sisa cicilan dari sisa pinjaman / cicilan bulanan
                    $calculatedSisaCicilan = (int) ceil($sisaPinjaman / $potKop);
                    Log::info("Calculated sisa cicilan from sisa_pinjaman: {$sisaPinjaman} / {$potKop} = {$calculatedSisaCicilan}");
                } elseif ($sisaPinjaman == 0) {
                    // Jika sisa pinjaman 0 tapi ada bayar (pot_kop > 0), berarti LUNAS baris ini
                    // Jangan default ke 10, tapi default ke 1 (Lunas)
                    $calculatedSisaCicilan = 1; 
                    $forcePayment = true;
                    Log::info("Sisa Pinjaman 0 detected. Assuming Lunas (sisa=1).");
                }
            }
            
            // Jika masih tidak valid (misal sisaPinjaman missing dan sisaCicilan missing), 
            // Default ke 1 (Lunas) lebih aman daripada 10 (Hutang baru) untuk mencegah sisa hutang palsu 10jt
            if ($calculatedSisaCicilan <= 0) {
                $calculatedSisaCicilan = 1; // Default aman: anggap lunas bulan ini
                $forcePayment = true;
                Log::info("No valid sisa cicilan info (sisaCicilan=-1, sisaPinjaman=missing). Defaulting to 1 (Lunas).");
            }
            
            $loanJustCreated = $this->createLoanFromInstallments($member, $potKop, $calculatedSisaCicilan, $rowNumber);
            
            // Jika ini kasus pelunasan ("-"), kita paksa boolean $loanJustCreated jadi false
            // supaya blok transaksi di bawah tetap jalan dan melakukan pembayaran (pelunasan)
            if ($forcePayment && $loanJustCreated) {
                $loanJustCreated = false; 
            }
        }
        
        // Create transactions
        $hasTransaction = false;
        $transactionDate = $this->transactionDate ?: now()->format('Y-m-d');
        
        // Untuk member dengan saldo yang sudah ada di Excel,
        // buat transaksi saldo awal (Initial Balance) untuk mencatat saldo historis
        // Ini berlaku untuk:
        // 1. Member baru
        // 2. Member existing yang belum punya transaksi simpanan (first time import)
        // 
        // Saldo ini adalah akumulasi sampai periode ini (termasuk iur_kop dan iur_tunai bulan ini)
        // Jadi saldo awal historis = saldo - iur_kop - iur_tunai (saldo SEBELUM bulan ini)
        $needsInitialSaldo = $isNewMember;
        
        // Cek jika member existing tapi belum punya transaksi simpanan
        if (!$isNewMember && $saldo > 0) {
            $existingSavingTransactions = $member->transactions()
                ->where('type', Transaction::TYPE_SAVING_DEPOSIT)
                ->count();
            if ($existingSavingTransactions === 0) {
                $needsInitialSaldo = true;
            }
        }
        
        if ($needsInitialSaldo && $saldo > 0) {
            // Hitung saldo sebelum transaksi bulan ini
            $previousSaldo = $saldo - $iurKop - $iurTunai;
            
            if ($previousSaldo > 0) {
                // Tanggal saldo awal = hari pertama bulan sebelumnya
                $initialDate = Carbon::parse($transactionDate)->subMonth()->startOfMonth()->format('Y-m-d');
                
                Transaction::create([
                    'member_id' => $member->id,
                    'loan_id' => null,
                    'transaction_date' => $initialDate,
                    'type' => Transaction::TYPE_SAVING_DEPOSIT,
                    'amount_saving' => $previousSaldo,
                    'amount_principal' => 0,
                    'amount_interest' => 0,
                    'total_amount' => $previousSaldo,
                    'payment_method' => 'other',
                    'notes' => "Saldo Awal Import: {$this->sheetName}",
                ]);
                $hasTransaction = true;
                Log::info("Created initial balance transaction for {$nik}: amount={$previousSaldo}, date={$initialDate}");
            }
        }
        
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
        // SKIP jika loan baru saja dibuat dari import, karena sisa_cicilan di Excel
        // sudah memperhitungkan pembayaran bulan ini
        if ($potKop > 0 && !$loanJustCreated) {
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
     * "-" berarti cicilan selesai bulan ini (sisa = 0)
     * Digunakan HANYA untuk menentukan loan yang sudah lunas (POT KOP ada tapi sisa "-")
     */
    protected function parseSisaCicilan(array $row): int
    {
        $colIndex = $this->columnMap['sisa_cicilan'] ?? null;
        if ($colIndex === null) return -1; // -1 = kolom tidak ditemukan
        
        $value = $row[$colIndex] ?? null;
        
        // Jika kosong, return -1 (tidak ada data)
        if ($value === null || $value === '') return -1;
        
        $strValue = trim((string) $value);
        
        // "-" artinya cicilan selesai bulan ini = sisa 0 (LUNAS)
        // Handle berbagai karakter dash
        if ($strValue === '-' || $strValue === '–' || $strValue === '—' || $strValue === '−') {
            return 0;
        }
        
        $strValueUpper = strtoupper($strValue);
        
        // #DIV/0!, #VALUE!, #REF!, dll = tidak valid, return -1
        if (str_starts_with($strValueUpper, '#')) {
            return -1;
        }
        
        // Coba parse sebagai integer
        if (is_numeric($value)) {
            return max(0, (int) $value);
        }
        
        return -1; // Tidak bisa diparse
    }

    /**
     * Create loan from installment data
     * Peraturan koperasi: Cicilan selalu 10 bulan
     * 
     * Perhitungan tanggal approval:
     * - Jika sisa cicilan = 4, berarti sudah bayar 6x (termasuk bulan ini)
     * - Pembayaran pertama = bulan approval
     * - Jika bulan ini (Januari 2026) adalah pembayaran ke-6, maka approval = Agustus 2025
     * - Formula: tanggal approval = tanggal transaksi - (jumlah terbayar - 1) bulan
     * 
     * @return bool True jika loan berhasil dibuat, false jika skip
     */
    protected function createLoanFromInstallments(Member $member, float $monthlyInstallment, int $remainingInstallments, int $rowNumber): bool
    {
        // Cek apakah sudah punya loan aktif
        if ($member->activeLoans()->exists()) {
            Log::info("Member {$member->nik} sudah punya loan aktif, skip create");
            return false; // Skip, sudah ada loan
        }
        
        $duration = 10; // Cicilan selalu 10 bulan
        
        // Pastikan remainingInstallments valid (1-10)
        $remainingInstallments = max(1, min(10, $remainingInstallments));
        
        $paidInstallments = $duration - $remainingInstallments;
        
        // Bulan ini adalah pembayaran ke-(paidInstallments + 1) karena kita sedang proses pembayaran
        // Jadi total yang sudah dibayar termasuk bulan ini = paidInstallments
        // Tapi sisa cicilan 4 berarti SETELAH bayar bulan ini, sisanya 4
        // Berarti bulan ini adalah pembayaran ke-6 (10 - 4 = 6)
        
        // Hitung pokok pinjaman: cicilan bulanan * 10
        $loanAmount = $monthlyInstallment * $duration;
        
        // Sisa pokok SETELAH bayar bulan ini = cicilan * sisa cicilan
        // Bulan ini belum dibayar (masih dalam proses import), jadi:
        // Sisa pokok = cicilan * (sisa cicilan)
        $remainingPrincipal = $monthlyInstallment * $remainingInstallments;
        
        // Hitung tanggal approve (mundur dari tanggal transaksi file)
        // Jika paidInstallments = 6 (termasuk bulan ini), maka:
        // - Bulan ini adalah pembayaran ke-6
        // - Pembayaran ke-1 adalah 5 bulan yang lalu
        // - Loan diapprove di bulan pembayaran ke-1
        $transactionDate = Carbon::parse($this->transactionDate ?: now());
        
        // Mundur (paidInstallments - 1) bulan karena pembayaran pertama = bulan approval
        // Jika ini pembayaran ke-6, mundur 5 bulan
        $monthsBack = max(0, $paidInstallments - 1);
        $approvedDate = $transactionDate->copy()->subMonths($monthsBack)->startOfMonth();
        
        // Create loan
        Loan::create([
            'member_id' => $member->id,
            'amount' => $loanAmount,
            'interest_rate' => 10, // Default bunga 10% sesuai request user
            'duration' => $duration,
            'remaining_principal' => $remainingPrincipal,
            'monthly_installment' => $monthlyInstallment,
            'total_interest' => 0, // Hitungan total interest di-skip karena ini data migrasi/import
            'admin_fee' => 0,
            'disbursed_amount' => $loanAmount,
            'status' => Loan::STATUS_ACTIVE,
            'application_date' => $approvedDate,
            'approved_date' => $approvedDate,
        ]);
        
        $this->importResult['loans_created']++;
        Log::info("Created loan for {$member->nik}: amount={$loanAmount}, monthly={$monthlyInstallment}, remaining={$remainingInstallments}x, paid={$paidInstallments}x, approved={$approvedDate->format('Y-m-d')}");
        
        return true; // Loan berhasil dibuat
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
