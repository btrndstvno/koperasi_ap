<?php

namespace App\Http\Controllers;

use App\Imports\MonthlyTransactionImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportController extends Controller
{
    /**
     * Regex pattern untuk validasi nama sheet format "MM YYYY" atau "M YYYY"
     * Contoh valid: "05 2025", "12 2024", "1 2025"
     */
    const SHEET_NAME_PATTERN = '/^\d{1,2}\s\d{4}$/';

    /**
     * Tampilkan halaman import
     */
    public function index()
    {
        return view('imports.index');
    }

    /**
     * Preview sheet yang akan diimport
     */
    public function preview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $file = $request->file('file');
        
        try {
            // Baca hanya nama sheet tanpa load seluruh file
            $spreadsheet = IOFactory::load($file->getPathname());
            $allSheets = $spreadsheet->getSheetNames();
            
            // Filter sheet berdasarkan regex
            $validSheets = [];
            $skippedSheets = [];
            
            foreach ($allSheets as $sheetName) {
                if (preg_match(self::SHEET_NAME_PATTERN, trim($sheetName))) {
                    $validSheets[] = [
                        'name' => $sheetName,
                        'parsed_date' => $this->parseSheetNameToDate($sheetName),
                    ];
                } else {
                    $skippedSheets[] = $sheetName;
                }
            }

            // Simpan file sementara untuk proses import
            $tempPath = $file->storeAs('imports/temp', 'import_' . time() . '.' . $file->getClientOriginalExtension());

            return view('imports.preview', [
                'validSheets' => $validSheets,
                'skippedSheets' => $skippedSheets,
                'tempPath' => $tempPath,
                'originalName' => $file->getClientOriginalName(),
            ]);

        } catch (\Exception $e) {
            Log::error('Import Preview Error: ' . $e->getMessage());
            return back()->with('error', 'Gagal membaca file Excel: ' . $e->getMessage());
        }
    }

    /**
     * Proses import untuk sheet yang dipilih
     */
    public function process(Request $request)
    {
        // Fix timeout & memory untuk file Excel besar
        set_time_limit(300); // 5 menit
        ini_set('memory_limit', '512M');
        
        $request->validate([
            'temp_path' => 'required|string',
            'sheets' => 'required|array|min:1',
            'sheets.*' => 'string',
        ]);

        $tempPath = storage_path('app/' . $request->temp_path);
        
        if (!file_exists($tempPath)) {
            return back()->with('error', 'File tidak ditemukan. Silakan upload ulang.');
        }

        $results = [
            'success' => [],
            'errors' => [],
            'summary' => [
                'total_rows' => 0,
                'imported' => 0,
                'skipped' => 0,
            ]
        ];

        try {
            foreach ($request->sheets as $sheetName) {
                // Validasi ulang nama sheet dengan regex
                if (!preg_match(self::SHEET_NAME_PATTERN, trim($sheetName))) {
                    $results['errors'][] = "Sheet '{$sheetName}' tidak sesuai format dan dilewati.";
                    continue;
                }

                try {
                    $import = new MonthlyTransactionImport($sheetName);
                    
                    Excel::import($import, $tempPath, null, \Maatwebsite\Excel\Excel::XLSX, [
                        'sheetName' => $sheetName
                    ]);

                    $sheetResult = $import->getImportResult();
                    
                    $results['success'][] = [
                        'sheet' => $sheetName,
                        'imported' => $sheetResult['imported'],
                        'skipped' => $sheetResult['skipped'],
                        'errors' => $sheetResult['errors'],
                    ];

                    $results['summary']['total_rows'] += $sheetResult['imported'] + $sheetResult['skipped'];
                    $results['summary']['imported'] += $sheetResult['imported'];
                    $results['summary']['skipped'] += $sheetResult['skipped'];

                } catch (\Exception $e) {
                    $results['errors'][] = "Error pada sheet '{$sheetName}': " . $e->getMessage();
                    Log::error("Import Sheet Error [{$sheetName}]: " . $e->getMessage());
                }
            }

            // Hapus file temp
            @unlink($tempPath);

            return view('imports.result', [
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('Import Process Error: ' . $e->getMessage());
            return back()->with('error', 'Gagal memproses import: ' . $e->getMessage());
        }
    }

    /**
     * Parse nama sheet menjadi tanggal
     * "05 2025" -> 2025-05-01
     */
    private function parseSheetNameToDate(string $sheetName): string
    {
        $parts = explode(' ', trim($sheetName));
        $month = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
        $year = $parts[1];
        
        return "{$year}-{$month}-01";
    }
}
