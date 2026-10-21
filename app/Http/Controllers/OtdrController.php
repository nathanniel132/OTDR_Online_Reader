<?php

namespace App\Http\Controllers;

use App\Services\SorParserService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OtdrController extends Controller
{
    protected SorParserService $parser;

    public function __construct(SorParserService $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Display OTDR Dashboard.
     */
    public function index(): View
    {
        return view('otdr.index');
    }

    /**
     * Handle stateless file upload & parse .sor files.
     */
    public function parse(Request $request): JsonResponse
    {
        try {
            $files = [];
            if ($request->hasFile('files')) {
                $files = $request->file('files');
            } elseif ($request->hasFile('file')) {
                $files = [$request->file('file')];
            }

            if (empty($files)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada file .sor yang diunggah.',
                ], 422);
            }

            $traces = [];
            $errors = [];

            foreach ($files as $file) {
                $filename = $file->getClientOriginalName();
                $ext = strtolower($file->getClientOriginalExtension());

                if ($ext !== 'sor') {
                    $errors[] = "File '{$filename}' diabaikan: ekstensi harus .sor.";

                    continue;
                }

                try {
                    $parsed = $this->parser->parse($file->getRealPath(), true);
                    $parsed['fileName'] = $filename;
                    $parsed['fileSize'] = $file->getSize();
                    $traces[] = $parsed;
                } catch (Exception $e) {
                    $errors[] = "Gagal memproses '{$filename}': ".$e->getMessage();
                }
            }

            if (empty($traces) && ! empty($errors)) {
                return response()->json([
                    'success' => false,
                    'message' => implode(' | ', $errors),
                    'errors' => $errors,
                ], 400);
            }

            return response()->json([
                'success' => true,
                'count' => count($traces),
                'traces' => $traces,
                'warnings' => $errors,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Load built-in sample file (Doc/kabel1 cbd c1.sor).
     */
    public function sample(): JsonResponse
    {
        try {
            $path = base_path('Doc/kabel1 cbd c1.sor');
            if (! file_exists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File sampel Doc/kabel1 cbd c1.sor tidak ditemukan.',
                ], 404);
            }

            $parsed = $this->parser->parse($path, true);
            $parsed['fileName'] = 'kabel1 cbd c1.sor';
            $parsed['fileSize'] = filesize($path);

            return response()->json([
                'success' => true,
                'trace' => $parsed,
                'message' => 'Sampel OTDR berhasil dimuat.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat sampel: '.$e->getMessage(),
            ], 500);
        }
    }
}
