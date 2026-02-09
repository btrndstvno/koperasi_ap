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
            
            // Tampilkan semua sheet - tanggal akan dideteksi dari isi file
            $validSheets = [];
            
            foreach ($allSheets as $sheetName) {
                $validSheets[] = [
                    'name' => $sheetName,
                    'parsed_date' => 'Akan dideteksi dari isi file',
                ];
            }

            // Simpan file sementara untuk proses import
            $tempPath = $file->storeAs('imports/temp', 'import_' . time() . '.' . $file->getClientOriginalExtension());

            return view('imports.preview', [
                'validSheets' => $validSheets,
                'skippedSheets' => [],
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
                'members_created' => 0,
                'members_updated' => 0,
                'loans_created' => 0,
            ]
        ];

        try {
            foreach ($request->sheets as $sheetName) {
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
                        'members_created' => $sheetResult['members_created'] ?? 0,
                        'members_updated' => $sheetResult['members_updated'] ?? 0,
                        'loans_created' => $sheetResult['loans_created'] ?? 0,
                        'errors' => $sheetResult['errors'],
                    ];

                    $results['summary']['total_rows'] += $sheetResult['imported'] + $sheetResult['skipped'];
                    $results['summary']['imported'] += $sheetResult['imported'];
                    $results['summary']['skipped'] += $sheetResult['skipped'];
                    $results['summary']['members_created'] += $sheetResult['members_created'] ?? 0;
                    $results['summary']['members_updated'] += $sheetResult['members_updated'] ?? 0;
                    $results['summary']['loans_created'] += $sheetResult['loans_created'] ?? 0;

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
     * Import langsung tanpa preview - proses semua sheet dalam file
     */
    public function direct(Request $request)
    {
        // Fix timeout & memory untuk file Excel besar
        set_time_limit(300); // 5 menit
        ini_set('memory_limit', '512M');
        
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $file = $request->file('file');
        $filePath = $file->getPathname(); // Use uploaded file temp path directly
        
        $results = [
            'success' => [],
            'errors' => [],
            'summary' => [
                'total_rows' => 0,
                'imported' => 0,
                'skipped' => 0,
                'members_created' => 0,
                'members_updated' => 0,
                'loans_created' => 0,
            ]
        ];

        try {
            // Baca semua sheet dari file
            $spreadsheet = IOFactory::load($filePath);
            $allSheets = $spreadsheet->getSheetNames();

            foreach ($allSheets as $sheetName) {
                try {
                    $import = new MonthlyTransactionImport($sheetName);
                    
                    Excel::import($import, $filePath, null, \Maatwebsite\Excel\Excel::XLSX, [
                        'sheetName' => $sheetName
                    ]);

                    $sheetResult = $import->getImportResult();
                    
                    $results['success'][] = [
                        'sheet' => $sheetName,
                        'imported' => $sheetResult['imported'],
                        'skipped' => $sheetResult['skipped'],
                        'members_created' => $sheetResult['members_created'] ?? 0,
                        'members_updated' => $sheetResult['members_updated'] ?? 0,
                        'loans_created' => $sheetResult['loans_created'] ?? 0,
                        'errors' => $sheetResult['errors'],
                    ];

                    $results['summary']['total_rows'] += $sheetResult['imported'] + $sheetResult['skipped'];
                    $results['summary']['imported'] += $sheetResult['imported'];
                    $results['summary']['skipped'] += $sheetResult['skipped'];
                    $results['summary']['members_created'] += $sheetResult['members_created'] ?? 0;
                    $results['summary']['members_updated'] += $sheetResult['members_updated'] ?? 0;
                    $results['summary']['loans_created'] += $sheetResult['loans_created'] ?? 0;

                } catch (\Exception $e) {
                    $results['errors'][] = "Error pada sheet '{$sheetName}': " . $e->getMessage();
                    Log::error("Import Sheet Error [{$sheetName}]: " . $e->getMessage());
                }
            }

            return view('imports.result', [
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('Import Direct Error: ' . $e->getMessage());
            return back()->with('error', 'Gagal memproses import: ' . $e->getMessage());
        }
    }
}
