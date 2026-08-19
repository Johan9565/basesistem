<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class PdfOcrService
{
    public const MODES = ['auto', 'text', 'ocr'];

    public const LANGUAGES = ['spa', 'eng', 'spa+eng'];

    public const DEFAULT_LANGUAGE = 'spa+eng';

    public function toolsStatus(): array
    {
        return [
            'pdftotext' => $this->binary('pdftotext') !== null,
            'pdftoppm' => $this->binary('pdftoppm') !== null,
            'tesseract' => $this->binary('tesseract') !== null,
            'pdfinfo' => $this->binary('pdfinfo') !== null,
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function languageOptions(): array
    {
        return [
            ['value' => 'spa', 'label' => 'Español'],
            ['value' => 'eng', 'label' => 'Inglés'],
            ['value' => 'spa+eng', 'label' => 'Español + Inglés'],
        ];
    }

    /**
     * @return array{
     *     text: string,
     *     pages: array<int, array{number: int, text: string, method: string}>,
     *     method: string,
     *     page_count: int,
     *     pages_processed: int
     * }
     */
    public function extract(
        string $pdfPath,
        string $language = self::DEFAULT_LANGUAGE,
        string $mode = 'auto',
    ): array {
        if (! is_readable($pdfPath)) {
            throw new RuntimeException('No se pudo leer el archivo PDF.');
        }

        $language = in_array($language, self::LANGUAGES, true)
            ? $language
            : self::DEFAULT_LANGUAGE;
        $mode = in_array($mode, self::MODES, true) ? $mode : 'auto';

        $pageCount = $this->pageCount($pdfPath);
        $workDir = $this->makeWorkDir();

        try {
            $pages = [];
            for ($page = 1; $page <= $pageCount; $page++) {
                $pages[] = $this->extractPage($pdfPath, $page, $language, $mode, $workDir);
            }
        } finally {
            $this->deleteDirectory($workDir);
        }

        $methods = array_unique(array_column($pages, 'method'));
        $method = count($methods) === 1 ? ($methods[0] ?? 'none') : 'mixed';

        $text = collect($pages)
            ->map(function (array $page) {
                $body = trim($page['text']);
                $header = '----- Página '.$page['number'].' -----';

                return $body === '' ? $header."\n" : $header."\n".$body;
            })
            ->implode("\n\n");

        return [
            'text' => $text,
            'pages' => $pages,
            'method' => $method,
            'page_count' => $pageCount,
            'pages_processed' => $pageCount,
        ];
    }

    /**
     * @return array{number: int, text: string, method: string}
     */
    private function extractPage(
        string $pdfPath,
        int $page,
        string $language,
        string $mode,
        string $workDir,
    ): array {
        $native = '';
        if ($mode !== 'ocr') {
            $native = $this->nativeText($pdfPath, $page);
        }

        if ($mode === 'text') {
            return ['number' => $page, 'text' => $native, 'method' => 'native'];
        }

        if ($mode === 'auto' && $this->isMeaningfulText($native)) {
            return ['number' => $page, 'text' => $native, 'method' => 'native'];
        }

        $ocr = $this->ocrPage($pdfPath, $page, $language, $workDir);

        return ['number' => $page, 'text' => $ocr, 'method' => 'ocr'];
    }

    private function nativeText(string $pdfPath, int $page): string
    {
        $bin = $this->requireBinary('pdftotext');
        $result = Process::timeout(60)->run([
            $bin,
            '-layout',
            '-enc',
            'UTF-8',
            '-f',
            (string) $page,
            '-l',
            (string) $page,
            $pdfPath,
            '-',
        ]);

        if ($result->failed()) {
            return '';
        }

        return $this->normalizeText($result->output());
    }

    private function ocrPage(string $pdfPath, int $page, string $language, string $workDir): string
    {
        $pdftoppm = $this->requireBinary('pdftoppm');
        $tesseract = $this->requireBinary('tesseract');
        $prefix = $workDir.'/page-'.$page.'-img';

        $render = Process::timeout(120)->run([
            $pdftoppm,
            '-png',
            '-r',
            '200',
            '-f',
            (string) $page,
            '-l',
            (string) $page,
            $pdfPath,
            $prefix,
        ]);

        if ($render->failed()) {
            throw new RuntimeException(
                'No se pudo convertir la página '.$page.' del PDF. '.$this->processError($render)
            );
        }

        $images = glob($prefix.'*.png') ?: [];
        if ($images === []) {
            throw new RuntimeException('No se generó imagen para la página '.$page.'.');
        }

        sort($images, SORT_NATURAL);
        $chunks = [];
        foreach ($images as $image) {
            $ocr = Process::timeout(90)->run([
                $tesseract,
                $image,
                'stdout',
                '-l',
                $language,
            ]);

            if ($ocr->failed()) {
                throw new RuntimeException(
                    'Tesseract falló en la página '.$page.'. '.$this->processError($ocr)
                );
            }

            $chunks[] = $this->normalizeText($ocr->output());
        }

        return $this->normalizeText(implode("\n", $chunks));
    }

    private function pageCount(string $pdfPath): int
    {
        $bin = $this->binary('pdfinfo');
        if ($bin === null) {
            return 1;
        }

        $result = Process::timeout(30)->run([$bin, $pdfPath]);
        if ($result->failed()) {
            throw new RuntimeException('El archivo no parece un PDF válido.');
        }

        if (preg_match('/^Pages:\s+(\d+)/m', $result->output(), $m)) {
            return max(1, (int) $m[1]);
        }

        return 1;
    }

    private function isMeaningfulText(string $text): bool
    {
        $count = preg_match_all('/[\p{L}\p{N}]/u', $text) ?: 0;
        $compact = preg_replace('/\s+/u', '', $text) ?? '';
        $len = mb_strlen($compact);
        if ($count < 20 || $len === 0) {
            return false;
        }

        return ($count / $len) >= 0.55;
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\x0c"], ["\n", "\n"], $text);
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function makeWorkDir(): string
    {
        $dir = sys_get_temp_dir().'/ocr-'.bin2hex(random_bytes(8));
        if (! mkdir($dir, 0700) && ! is_dir($dir)) {
            throw new RuntimeException('No se pudo crear el directorio temporal de OCR.');
        }

        return $dir;
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    private function binary(string $name): ?string
    {
        foreach (['/usr/bin/'.$name, '/usr/local/bin/'.$name] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function requireBinary(string $name): string
    {
        $path = $this->binary($name);
        if ($path === null) {
            throw new RuntimeException(
                'Falta la herramienta "'.$name.'" en el servidor. Instálala para extraer texto de PDFs.'
            );
        }

        return $path;
    }

    private function processError(mixed $result): string
    {
        $error = trim((string) $result->errorOutput());
        $out = trim((string) $result->output());

        return $error !== '' ? $error : $out;
    }
}
