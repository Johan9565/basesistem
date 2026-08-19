<?php

namespace App\Http\Controllers;

use App\Services\PdfOcrService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class OcrController extends Controller
{
    public function __construct(private PdfOcrService $ocr)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Ocr/Index', $this->pageProps([
            'result' => session('ocr_result'),
        ]));
    }

    public function extract(Request $request)
    {
        set_time_limit(0);

        $validated = $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf'],
            'language' => ['nullable', 'string', Rule::in(PdfOcrService::LANGUAGES)],
            'mode' => ['nullable', 'string', Rule::in(PdfOcrService::MODES)],
        ]);

        $file = $request->file('pdf');
        $path = $file->getRealPath();
        if ($path === false || $path === '') {
            return back()->withErrors([
                'pdf' => 'No se pudo leer el archivo subido.',
            ]);
        }

        try {
            $extracted = $this->ocr->extract(
                $path,
                $validated['language'] ?? PdfOcrService::DEFAULT_LANGUAGE,
                $validated['mode'] ?? 'auto',
            );
        } catch (RuntimeException $e) {
            return back()->withErrors([
                'pdf' => $e->getMessage(),
            ]);
        }

        return redirect()->route('ocr')->with('ocr_result', [
            'filename' => $file->getClientOriginalName(),
            'text' => $extracted['text'],
            'pages' => $extracted['pages'],
            'method' => $extracted['method'],
            'page_count' => $extracted['page_count'],
            'pages_processed' => $extracted['pages_processed'],
        ]);
    }

    private function pageProps(array $extra = []): array
    {
        return array_merge([
            'languages' => $this->ocr->languageOptions(),
            'tools' => $this->ocr->toolsStatus(),
        ], $extra);
    }
}
