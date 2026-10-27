<?php

namespace App\Support;

use RuntimeException;

class StudentCredentialPdf
{
    /**
     * @param array<int, array{name: string, student_id: string, email: string, password: string}> $credentials
     */
    public function render(array $credentials): string
    {
        $pages = [];
        $pageLines = [];

        $addLine = function (string $text, int $size = 11, bool $bold = false) use (&$pageLines): void {
            $pageLines[] = [$text, $size, $bold];
        };

        foreach ($credentials as $index => $credential) {
            if ($pageLines === []) {
                $addLine('Student Attendance Monitoring System', 16, true);
                $addLine('Student account credentials', 12, true);
                $addLine('');
            }

            $addLine(($index + 1).'. '.$credential['name'], 12, true);
            $addLine('Student ID: '.($credential['student_id'] ?: 'Unassigned'));
            $addLine('Email: '.$credential['email']);
            $addLine('Temporary password: '.$credential['password']);
            $addLine('Change this password immediately after signing in.', 9);
            $addLine('');

            if (count($pageLines) >= 29 || $index === array_key_last($credentials)) {
                $pages[] = $pageLines;
                $pageLines = [];
            }
        }

        if ($pages === []) {
            throw new RuntimeException('No student credentials were available for the PDF.');
        }

        return $this->buildDocument($pages);
    }

    /**
     * @param array<int, array<int, array{0: string, 1: int, 2: bool}>> $pages
     */
    private function buildDocument(array $pages): string
    {
        $objects = [];
        $addObject = static function (string $body) use (&$objects): int {
            $objects[] = $body;

            return count($objects);
        };

        $font = $addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
        $boldFont = $addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>');
        $pageIds = [];

        foreach ($pages as $lines) {
            $content = "BT\n";
            $y = 780;
            foreach ($lines as [$text, $size, $bold]) {
                if ($text === '') {
                    $y -= 12;
                    continue;
                }

                $fontName = $bold ? 'F2' : 'F1';
                $content .= sprintf("/%s %d Tf 1 0 0 1 54 %d Tm (%s) Tj\n", $fontName, $size, $y, $this->escape($text));
                $y -= $size + 9;
            }
            $content .= "ET\n";

            $stream = $addObject("<< /Length ".strlen($content)." >>\nstream\n{$content}endstream");
            $pageIds[] = $addObject("<< /Type /Page /Parent PAGES_ID 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 {$font} 0 R /F2 {$boldFont} 0 R >> >> /Contents {$stream} 0 R >>");
        }

        $pagesId = $addObject('<< /Type /Pages /Kids ['.implode(' ', array_map(fn (int $id): string => "{$id} 0 R", $pageIds)).'] /Count '.count($pageIds).' >>');
        $catalog = $addObject("<< /Type /Catalog /Pages {$pagesId} 0 R >>");

        foreach ($pageIds as $pageId) {
            $objects[$pageId - 1] = str_replace('PAGES_ID', (string) $pagesId, $objects[$pageId - 1]);
        }

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[$index + 1] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        for ($index = 1; $index <= count($objects); $index++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$index]);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root {$catalog} 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function escape(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
