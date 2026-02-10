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
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Import Excel Koperasi
 */
class MonthlyTransactionImport implements ToCollection, WithMultipleSheets, WithCalculatedFormulas
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

    const HEADER_KEYWORDS = [
        'nik' => ['NIK', 'NO INDUK', 'NIP'],
        'name' => ['NAMA', 'NAMA ANGGOTA', 'NAMA KARYAWAN'],
        'dept' => ['DEPT', 'DEPARTMENT', 'BAGIAN'],
        'pot_kop' => ['POT KOP', 'POT_KOP', 'POTONGAN', 'POT. KOP', 'POT'],
        'iur_kop' => ['IUR KOP', 'IUR_KOP', 'IURAN', 'IUR. KOP'],
        'iur_tunai' => ['IUR TUNAI', 'IUR_TUNAI', 'TUNAI'],
        'jumlah' => ['JUMLAH', 'TOTAL'],
        'saldo' => ['SALDO', 'SALDO KOPRASI', 'SALDO KOPERASI', 'SALDO KOP', 'KOPRASI'],
        'sisa_cicilan' => ['SISA CICILAN', 'SISA_CICILAN', 'CICILAN', 'SISA CIC'],
        'sisa_pinjaman' => ['SISA PINJAMAN', 'SISA_PINJAMAN', 'SISA HUTANG'],
        'adiputro_csd' => ['ADIPUTRO CSD', 'ADIPUTRO', 'GRUP', 'GROUP TAG', 'TAG', 'KET', 'KETERANGAN'],
    ];

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
                    // STEP 1: Detect Date
                    if ($this->transactionDate === null && $rowNumber <= 5) {
                        $this->detectTransactionDate($rowArray);
                    }
                    
                    // STEP 2: Detect Section
                    $detectedSection = $this->detectSectionHeader($rowArray);
                    if ($detectedSection) {
                        $this->currentSection = $detectedSection;
                        $this->columnMap = []; 
                        $this->inDataArea = false;
                        Log::info("Section detected: {$detectedSection} at row {$rowNumber}");
                        continue;
                    }
                    
                    // STEP 3: Detect Header
                    if ($this->isHeaderRow($rowArray)) {
                        $this->detectColumnMapping($rowArray);
                        $this->inDataArea = true;
                        if (!$this->currentSection) {
                            $this->currentSection = 'Office';
                        }
                        Log::info("Header detected at row {$rowNumber}", $this->columnMap);
                        continue;
                    }
                    
                    // STEP 4: Process Data
                    if ($this->inDataArea && $this->currentSection) {
                        $this->processDataRow($rowArray, $rowNumber);
                    }
                    
                } catch (\Exception $e) {
                    $this->importResult['errors'][] = "Baris {$rowNumber}: " . $e->getMessage();
                    $this->importResult['skipped']++;
                    Log::warning("Import error row {$rowNumber}: " . $e->getMessage());
                }
            }

            if ($this->transactionDate === null) {
                $this->transactionDate = $this->parseSheetNameToDate($this->sheetName);
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function detectTransactionDate(array $row): void
    {
        foreach ($row as $cell) {
            if (empty($cell)) continue;
            $cellText = strtoupper(trim((string) $cell));
            
            $monthMap = [
                'JANUARI' => 1, 'FEBRUARI' => 2, 'MARET' => 3, 'APRIL' => 4,
                'MEI' => 5, 'JUNI' => 6, 'JULI' => 7, 'AGUSTUS' => 8,
                'SEPTEMBER' => 9, 'OKTOBER' => 10, 'NOVEMBER' => 11, 'DESEMBER' => 12,
                'JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5,
                'JUN' => 6, 'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10,
                'NOV' => 11, 'DEC' => 12,
            ];
            
            if (preg_match('/(\d{1,2})\s+([A-Z]+)\s+(\d{4})/', $cellText, $matches)) {
                $monthName = $matches[2];
                if (isset($monthMap[$monthName])) {
                    $this->transactionDate = sprintf('%04d-%02d-%02d', $matches[3], $monthMap[$monthName], $matches[1]);
                    return;
                }
            }
        }
    }

    protected function detectSectionHeader(array $row): ?string
    {
        $firstCells = array_slice($row, 0, 3);
        $firstCellsText = strtoupper(implode(' ', array_map('strval', $firstCells)));
        
        if (str_contains($firstCellsText, 'NIK')) return null;
        
        $nonEmptyCells = count(array_filter($row, fn($v) => !empty(trim((string)$v))));
        if ($nonEmptyCells > 3) return null;
        
        $firstCell = trim((string) ($row[0] ?? ''));
        if (!empty($firstCell) && preg_match('/^\d+$/', $firstCell)) return null;
        
        foreach (self::SECTION_KEYWORDS as $section => $keywords) {
            foreach ($keywords as $keyword) {
                if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/', $firstCellsText)) {
                    return $section;
                }
            }
        }
        return null;
    }

    protected function isHeaderRow(array $row): bool
    {
        $rowText = strtoupper(implode(' ', array_map('strval', $row)));
        return (str_contains($rowText, 'NIK') || str_contains($rowText, 'NO INDUK')) && 
               (str_contains($rowText, 'POT') || str_contains($rowText, 'JUMLAH') || str_contains($rowText, 'SALDO'));
    }

    protected function detectColumnMapping(array $headerRow): void
    {
        $this->columnMap = [];
        
        // 1. Standard Mapping
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
        
        // 2. Split Headers (Adjacent Cells)
        $splitKeywords = [
            'iur_kop' => [['IUR', 'KOP']],
            'pot_kop' => [['POT', 'KOP']],
            'iur_tunai' => [['IUR', 'TUNAI']],
            'sisa_pinjaman' => [['SISA', 'PINJAMAN']],
            'sisa_cicilan' => [['SISA', 'CICILAN']],
        ];
        
        foreach ($splitKeywords as $fieldKey => $patterns) {
            if (isset($this->columnMap[$fieldKey])) continue;
            foreach ($patterns as $pattern) {
                for ($i = 0; $i < count($headerRow) - 1; $i++) {
                    $cell1 = strtoupper(trim((string) ($headerRow[$i] ?? '')));
                    $cell2 = strtoupper(trim((string) ($headerRow[$i + 1] ?? '')));
                    if ($cell1 === $pattern[0] && $cell2 === $pattern[1]) {
                        $this->columnMap[$fieldKey] = $i;
                        break 2;
                    }
                }
            }
        }
        
        // 3. Inferred IUR KOP (Strict Mode)
        // Hanya tebak jika belum ketemu, DAN ada pot_kop
        if (!isset($this->columnMap['iur_kop']) && isset($this->columnMap['pot_kop'])) {
            $startCol = $this->columnMap['pot_kop'] + 1;
            // Batasi pencarian: jangan lewat IUR TUNAI, JUMLAH, atau SALDO
            $limitCols = array_filter([
                $this->columnMap['iur_tunai'] ?? 999,
                $this->columnMap['jumlah'] ?? 999,
                $this->columnMap['saldo'] ?? 999, // Proteksi agar tidak bablas ke SALDO
                $this->columnMap['sisa_pinjaman'] ?? 999
            ]);
            $endCol = min($limitCols) !== 999 ? min($limitCols) : count($headerRow);
            
            for ($i = $startCol; $i < $endCol; $i++) {
                $cell = strtoupper(trim((string) ($headerRow[$i] ?? '')));
                if (empty($cell) || $cell === 'KOP') continue;
                
                if ($cell === 'IUR' || (str_contains($cell, 'IUR') && !str_contains($cell, 'TUNAI'))) {
                    $this->columnMap['iur_kop'] = $i;
                    Log::info("Inferred iur_kop at {$i}");
                    break;
                }
            }
        }
        
        // Proteksi Tambahan: Pastikan IUR KOP tidak sama dengan SALDO atau SISA PINJAMAN
        if (isset($this->columnMap['iur_kop'])) {
            $badIndices = [
                $this->columnMap['saldo'] ?? -1,
                $this->columnMap['sisa_pinjaman'] ?? -1,
                $this->columnMap['jumlah'] ?? -1
            ];
            if (in_array($this->columnMap['iur_kop'], $badIndices)) {
                Log::warning("IUR KOP detected overlap with Saldo/Jumlah. Removing IUR KOP mapping.");
                unset($this->columnMap['iur_kop']);
            }
        }

        // 4. Inferred Sisa Pinjaman & Cicilan (Standard)
        if (!isset($this->columnMap['sisa_pinjaman']) && isset($this->columnMap['saldo'])) {
            $startCol = $this->columnMap['jumlah'] ?? $this->columnMap['iur_tunai'] ?? 0;
            $endCol = $this->columnMap['saldo'];
            for ($i = $startCol + 1; $i < $endCol; $i++) {
                $cell = strtoupper(trim((string) ($headerRow[$i] ?? '')));
                if (str_contains($cell, 'SISA') && !str_contains($cell, 'CICILAN')) {
                    $this->columnMap['sisa_pinjaman'] = $i;
                    break;
                }
            }
        }

        if (!isset($this->columnMap['sisa_cicilan'])) {
            $startCol = max($this->columnMap['saldo'] ?? 0, $this->columnMap['adiputro_csd'] ?? 0);
            for ($i = $startCol + 1; $i < count($headerRow); $i++) {
                $cell = strtoupper(trim((string) ($headerRow[$i] ?? '')));
                if (str_contains($cell, 'CICILAN') || $cell === 'SISA') {
                    $this->columnMap['sisa_cicilan'] = $i;
                    break;
                }
            }
        }
        
        Log::info("Mapped: " . json_encode($this->columnMap));
    }

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
        
        // Group & CSD
        $groupTag = $this->currentSection ?? 'Office';
        $employeeStatus = 'monthly';
        $csdValue = $adiputroCsd;
        
        if (empty($csdValue)) {
            $csdValue = $this->currentSection ?? '-';
        }

        if ($this->currentSection === 'Bangunan') {
            if (strtoupper($adiputroCsd) === 'MINGGUAN') $employeeStatus = 'weekly';
        } else {
            $tagMapping = ['CSD' => 'CSD', 'MANAGER' => 'Manager', 'MGR' => 'Manager', 'OFFICE' => 'Office', 'OFC' => 'Office', 'KARYAWAN' => 'Office'];
            $upperCsd = strtoupper(trim($adiputroCsd));
            if (isset($tagMapping[$upperCsd])) $groupTag = $tagMapping[$upperCsd];
        }
        
        // Member Create/Update
        $member = Member::where('nik', $nik)->first();
        $isNewMember = false;
        
        if (!$member) {
            $member = Member::create([
                'nik' => $nik,
                'name' => $name ?: "Member {$nik}",
                'dept' => $dept ?: '-',
                'group_tag' => $groupTag,
                'csd' => $csdValue, 
                'employee_status' => $employeeStatus,
                'savings_balance' => $saldo,
            ]);
            $this->importResult['members_created']++;
            $isNewMember = true;
        } else {
            $updateData = [
                'name' => $name ?: $member->name,
                'dept' => $dept ?: $member->dept,
                'group_tag' => $groupTag,
                'employee_status' => $employeeStatus,
                'savings_balance' => $saldo,
            ];
            if (!empty($csdValue)) $updateData['csd'] = $csdValue;
            $member->update($updateData);
            $this->importResult['members_updated']++;
        }
        
        // Loan Processing
        // [FIX ISSUE 2] Kita menghapus pengecekan $loanJustCreated nanti di blok transaksi
        // Tapi kita perlu tahu apakah loan baru dibuat untuk penyesuaian saldo awal
        $loanWasCreated = false;
        
        if ($potKop > 0) {
            $calculatedSisaCicilan = -1;
            $forcePayment = false;

            if ($sisaCicilan === 0) {
                $calculatedSisaCicilan = 0;
                $forcePayment = true;
            } else {
                $calculatedSisaCicilan = $sisaCicilan > 0 ? $sisaCicilan : -1;
            }
            
            // Fallback: hanya jika sisa cicilan tidak diketahui (< 0), bukan 0 (lunas)
            if ($calculatedSisaCicilan < 0) {
                if ($sisaPinjaman > 0) {
                    $calculatedSisaCicilan = (int) ceil($sisaPinjaman / $potKop);
                } elseif ($sisaPinjaman == 0) {
                    $calculatedSisaCicilan = 0; 
                    $forcePayment = true;
                }
            }
            
            // Final fallback: jika masih tidak diketahui
            if ($calculatedSisaCicilan < 0) {
                $calculatedSisaCicilan = 1; // Default
                $forcePayment = true;
            }
            
            // [FIX ISSUE 2] createLoanFromInstallments sekarang akan mengembalikan Loan object atau null
            $loanWasCreated = $this->createLoanFromInstallments($member, $potKop, $calculatedSisaCicilan);
        }
        
        // Transactions
        $hasTransaction = false;
        $transactionDate = $this->transactionDate ?: now()->format('Y-m-d');
        
        // Initial Balance Logic
        $needsInitialSaldo = $isNewMember;
        if (!$isNewMember && $saldo > 0) {
            if ($member->transactions()->where('type', Transaction::TYPE_SAVING_DEPOSIT)->count() === 0) {
                $needsInitialSaldo = true;
            }
        }
        
        if ($needsInitialSaldo && $saldo > 0) {
            $previousSaldo = $saldo - $iurKop - $iurTunai;
            if ($previousSaldo > 0) {
                $initialDate = Carbon::parse($transactionDate)->subMonth()->startOfMonth()->format('Y-m-d');
                Transaction::create([
                    'member_id' => $member->id,
                    'transaction_date' => $initialDate,
                    'type' => Transaction::TYPE_SAVING_DEPOSIT,
                    'amount_saving' => $previousSaldo,
                    'total_amount' => $previousSaldo,
                    'payment_method' => 'other',
                    'notes' => "Saldo Awal Import: {$this->sheetName}",
                ]);
                $hasTransaction = true;
            }
        }
        
        if ($iurKop > 0) {
            Transaction::create([
                'member_id' => $member->id,
                'transaction_date' => $transactionDate,
                'type' => Transaction::TYPE_SAVING_DEPOSIT,
                'amount_saving' => $iurKop,
                'total_amount' => $iurKop,
                'payment_method' => 'deduction',
                'notes' => "Import: {$this->sheetName}",
            ]);
            $hasTransaction = true;
        }
        
        if ($iurTunai > 0) {
            Transaction::create([
                'member_id' => $member->id,
                'transaction_date' => $transactionDate,
                'type' => Transaction::TYPE_SAVING_DEPOSIT,
                'amount_saving' => $iurTunai,
                'total_amount' => $iurTunai,
                'payment_method' => 'cash',
                'notes' => "Import Tunai: {$this->sheetName}",
            ]);
            $hasTransaction = true;
        }
        
        // [FIX ISSUE 2]: SELALU buat transaksi POT KOP meskipun loan baru dibuat
        // Ini agar Total Uang (Transaction Sum) sama dengan Total Excel
        if ($potKop > 0) {
            $activeLoan = $member->activeLoans()->first();
            
            if ($activeLoan) {
                // Kurangi sisa pinjaman di sistem agar sinkron
                $activeLoan->reduceRemainingPrincipal($potKop);
                
                Transaction::create([
                    'member_id' => $member->id,
                    'loan_id' => $activeLoan->id,
                    'transaction_date' => $transactionDate,
                    'type' => Transaction::TYPE_LOAN_REPAYMENT,
                    'amount_principal' => $potKop,
                    'total_amount' => $potKop,
                    'payment_method' => 'deduction',
                    'notes' => "Import: {$this->sheetName}",
                ]);
                $hasTransaction = true;
            }
        }
        
        if ($hasTransaction) $this->importResult['imported']++;
        else $this->importResult['skipped']++;
    }

    protected function parseSisaCicilan(array $row): int
    {
        $colIndex = $this->columnMap['sisa_cicilan'] ?? null;
        if ($colIndex === null) return -1;
        
        $value = $row[$colIndex] ?? null;
        if ($value === null || $value === '') return -1;
        
        $strValue = trim((string) $value);
        if (in_array($strValue, ['-', '–', '—', '−'])) return 0;
        if (str_starts_with(strtoupper($strValue), '#')) return -1;
        if (is_numeric($value)) return max(0, (int) $value);
        return -1;
    }

    protected function createLoanFromInstallments(Member $member, float $monthlyInstallment, int $remainingInstallments): bool
    {
        if ($member->activeLoans()->exists()) return false;
        
        $duration = 10;
        $remainingInstallments = max(0, min(10, $remainingInstallments));
        $paidInstallments = $duration - $remainingInstallments;
        
        $loanAmount = $monthlyInstallment * $duration;
        
        // [FIX] LOGIKA SALDO:
        // Sisa Cicilan di Excel adalah kondisi SETELAH bayar bulan ini.
        // Karena kita akan membuat Transaksi Pembayaran (reduceRemainingPrincipal),
        // Maka saldo awal Loan harus kita naikkan dulu sebesar 1x cicilan.
        // Saldo Awal = (Sisa Cicilan * Cicilan) + Pembayaran Bulan Ini
        
        $remainingPrincipalInExcel = $monthlyInstallment * $remainingInstallments;
        $initialPrincipal = $remainingPrincipalInExcel + $monthlyInstallment;
        
        // [FIX] Tanggal approve: monthsBack = paidInstallments (tanpa -1)
        // approved_date adalah bulan dimana pinjaman di-approve (sebelum pembayaran pertama)
        // Contoh: sisa 5x, paid 5x → approved 5 bulan lalu dari bulan transaksi
        $transactionDate = Carbon::parse($this->transactionDate ?: now());
        $monthsBack = max(0, $paidInstallments);
        $approvedDate = $transactionDate->copy()->subMonths($monthsBack)->startOfMonth();
        
        // [FIX] Jika sisa cicilan = 0 (lunas setelah bayar bulan ini),
        // buat loan dengan status langsung paid setelah reduceRemainingPrincipal nanti
        $loanStatus = Loan::STATUS_ACTIVE;
        
        Loan::create([
            'member_id' => $member->id,
            'amount' => $loanAmount,
            'interest_rate' => 10,
            'duration' => $duration,
            'remaining_principal' => $initialPrincipal, // Set lebih tinggi agar nanti dikurangi transaksi
            'monthly_installment' => $monthlyInstallment,
            'status' => $loanStatus,
            'application_date' => $approvedDate,
            'approved_date' => $approvedDate,
        ]);
        
        $this->importResult['loans_created']++;
        return true;
    }

    protected function isValidNik(mixed $value): bool
    {
        if (empty($value)) return false;
        $strValue = strtoupper(trim((string) $value));
        $invalidKeywords = ['TOTAL', 'JUMLAH', 'SUBTOTAL', 'GRAND', 'SUM', 'RATA', 'AVERAGE', 'KETERANGAN'];
        foreach ($invalidKeywords as $keyword) {
            if (str_contains($strValue, $keyword)) return false;
        }
        return preg_match('/\d/', $strValue);
    }

    protected function cleanNik(mixed $value): string
    {
        $nik = trim((string) $value);
        if (preg_match('/^(\d+)\.0+$/', $nik, $matches)) $nik = $matches[1];
        if (is_numeric($nik) && strlen($nik) < 6) $nik = str_pad($nik, 6, '0', STR_PAD_LEFT);
        return $nik;
    }

    protected function getStringValue(array $row, string $fieldKey): string
    {
        $colIndex = $this->columnMap[$fieldKey] ?? null;
        if ($colIndex === null) return '';
        $value = trim((string) ($row[$colIndex] ?? ''));
        // Skip formula strings (e.g. =VLOOKUP(...)) — treat as empty
        if (str_starts_with($value, '=')) return '';
        return $value;
    }

    protected function getNumericValue(array $row, string $fieldKey): float
    {
        $colIndex = $this->columnMap[$fieldKey] ?? null;
        if ($colIndex === null) return 0;
        $value = $row[$colIndex] ?? null;
        // Skip formula strings
        if (is_string($value) && str_starts_with(trim($value), '=')) return 0;
        return $this->parseAmount($value);
    }

    protected function parseAmount(mixed $value): float
    {
        if (empty($value)) return 0;
        if (is_numeric($value)) return max(0, (float) $value);
        
        $cleaned = preg_replace('/[^\d.,\-]/', '', (string) $value);
        
        if (preg_match('/^\d{1,3}(\.\d{3})+,\d{2}$/', $cleaned)) { // ID
            $cleaned = str_replace(['.', ','], ['', '.'], $cleaned);
        } elseif (preg_match('/^\d{1,3}(,\d{3})+\.\d{2}$/', $cleaned)) { // US
            $cleaned = str_replace(',', '', $cleaned);
        } elseif (strpos($cleaned, ',') !== false && strpos($cleaned, '.') === false) { // Simple comma
            $cleaned = str_replace(',', '.', $cleaned);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $cleaned)) { // ID no decimal
            $cleaned = str_replace('.', '', $cleaned);
        }
        
        return max(0, (float) $cleaned);
    }

    protected function parseSheetNameToDate(string $sheetName): string
    {
        $parts = preg_split('/[\s\-_]+/', trim($sheetName));
        if (count($parts) < 2) return now()->format('Y-m-01');
        
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

    public function getImportResult(): array
    {
        return $this->importResult;
    }
}