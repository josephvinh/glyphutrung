<?php
/**
 * XLSX EXPORT — tự viết zip+xml, KHÔNG composer.
 *
 * Tạo file .xlsx từ headers + rows. Cấu trúc OOXML tối thiểu:
 *  - [Content_Types].xml
 *  - _rels/.rels
 *  - xl/workbook.xml
 *  - xl/styles.xml
 *  - xl/sharedStrings.xml
 *  - xl/_rels/workbook.xml.rels
 *  - xl/worksheets/sheet1.xml
 *
 * Hỗ trợ:
 *  - Tiêu đề in đậm (style 1).
 *  - Cột ngày dd/mm/yyyy khi $opts['date_columns'] chứa index cột.
 *  - UTF-8 (đã escape cho XML).
 *
 * Giới hạn: 1 sheet, không chart/ảnh/conditional formatting.
 * Đủ cho báo cáo nhật ký / điểm danh.
 *
 * Cách dùng:
 *   xlsx_export('/tmp/baocao.xlsx', ['Tên', 'Điểm'], [['An', 8], ['Bình', 9]]);
 *   xlsx_export($path, $headers, $rows, ['date_columns' => [0]]);
 */

declare(strict_types=1);

namespace TNTT;

use ZipArchive;

class XlsxExport
{
    /**
     * Xuất headers + rows ra file XLSX.
     *
     * @param string $path     Đường dẫn file .xlsx (sẽ ghi đè nếu có).
     * @param array<int,string> $headers Tiêu đề cột.
     * @param array<int,array<int,mixed>> $rows Mỗi dòng = 1 mảng giá trị.
     * @param array{
     *     sheet_name?: string,
     *     date_columns?: array<int,int>,
     *     bold_header?: bool,
     * } $opts
     *
     * @throws \RuntimeException Nếu không tạo được file.
     */
    public static function write(
        string $path,
        array $headers,
        array $rows,
        array $opts = []
    ): void {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException(
                'PHP ZipArchive extension chưa bật — cần bật extension=zip trong php.ini.'
            );
        }

        $sheetName = (string) ($opts['sheet_name'] ?? 'Sheet1');
        $dateCols  = (array)  ($opts['date_columns'] ?? []);
        $boldHead  = (bool)   ($opts['bold_header'] ?? true);

        // 1. Tạo thư mục tạm, ghi từng file XML.
        $tmp = sys_get_temp_dir() . '/tntt_xlsx_' . bin2hex(random_bytes(8));
        if (!mkdir($tmp, 0700, true) && !is_dir($tmp)) {
            throw new \RuntimeException("Không tạo được thư mục tạm: $tmp");
        }
        try {
            // Tạo sẵn các thư mục con — file_put_contents không tự tạo.
            @mkdir($tmp . '/_rels', 0700, true);
            @mkdir($tmp . '/xl/_rels', 0700, true);
            @mkdir($tmp . '/xl/worksheets', 0700, true);

            self::writeContentTypes($tmp);
            self::writeRootRels($tmp);
            self::writeWorkbookRels($tmp);
            self::writeWorkbook($tmp, $sheetName);
            self::writeStyles($tmp, $boldHead);
            [$sharedStrings, $rowXml] = self::buildSheetXml(
                $headers, $rows, $dateCols, $boldHead
            );
            self::writeSharedStrings($tmp, $sharedStrings);
            self::writeSheet($tmp, $rowXml);

            // 2. Nén ZIP.
            $zip = new ZipArchive();
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException("Không mở được file để ghi: $path");
            }
            $files = self::collectFiles($tmp);
            foreach ($files as $name) {
                // ZIP chuẩn OOXML dùng '/' làm path separator, bất kể OS.
                $zipName = str_replace('\\', '/', $name);
                $zip->addFile($tmp . '/' . $name, $zipName);
            }
            $zip->close();
        } finally {
            self::rmdirRecursive($tmp);
        }
    }

    // ----- Ghi từng file XML tĩnh -----------------------------------------

    private static function writeContentTypes(string $dir): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '</Types>';
        file_put_contents($dir . '/[Content_Types].xml', $xml);
    }

    private static function writeRootRels(string $dir): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
        file_put_contents($dir . '/_rels/.rels', $xml);
    }

    private static function writeWorkbookRels(string $dir): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '</Relationships>';
        file_put_contents($dir . '/xl/_rels/workbook.xml.rels', $xml);
    }

    private static function writeWorkbook(string $dir, string $sheetName): void
    {
        $name = htmlspecialchars($sheetName, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>'
            . '<sheet name="' . $name . '" sheetId="1" r:id="rId1"/>'
            . '</sheets>'
            . '</workbook>';
        file_put_contents($dir . '/xl/workbook.xml', $xml);
    }

    private static function writeStyles(string $dir, bool $boldHeader): void
    {
        // Styles đơn giản: style 1 = tiêu đề in đậm, style 2 = mặc định, style 3 = ngày.
        $headerCellXfs = $boldHeader
            ? '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            : '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyFont="0"/>';
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3">'
            . $headerCellXfs
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyFont="0"/>'
            . '<xf numFmtId="14" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
        file_put_contents($dir . '/xl/styles.xml', $xml);
    }

    // ----- Sheet + sharedStrings -----------------------------------------

    /**
     * Xây XML cho sheet1.xml + mảng sharedStrings.
     *
     * @param array<int,string> $headers
     * @param array<int,array<int,mixed>> $rows
     * @param array<int,int> $dateCols
     * @return array{0:array<int,string>,1:string} [sharedStrings, sheetXml]
     */
    private static function buildSheetXml(
        array $headers,
        array $rows,
        array $dateCols,
        bool $boldHeader
    ): array {
        $shared = [];
        $ssIndex = []; // map giá trị-string → index trong $shared

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>';

        // Header row
        $xml .= '<row r="1">';
        $colLetter = 0;
        foreach ($headers as $h) {
            $colLetter++;
            $ref = self::cellRef($colLetter, 1);
            $val = (string) $h;
            $idx = self::ssAdd($shared, $ssIndex, $val);
            $styleAttr = $boldHeader ? ' s="1"' : '';
            $xml .= '<c r="' . $ref . '"' . $styleAttr . ' t="s"><v>' . $idx . '</v></c>';
        }
        $xml .= '</row>';

        // Data rows
        $rowNum = 1;
        foreach ($rows as $row) {
            $rowNum++;
            $xml .= '<row r="' . $rowNum . '">';
            $colLetter = 0;
            foreach ($row as $colIdx => $val) {
                $colLetter++;
                $ref = self::cellRef($colLetter, $rowNum);
                if (in_array($colIdx, $dateCols, true) && self::looksLikeDate($val)) {
                    // ISO date (YYYY-MM-DD) → Excel serial
                    $serial = self::dateToSerial((string) $val);
                    $xml .= '<c r="' . $ref . '" s="2"><v>' . $serial . '</v></c>';
                } elseif (is_int($val) || (is_string($val) && ctype_digit(ltrim($val, '-')))) {
                    $xml .= '<c r="' . $ref . '"><v>' . (int) $val . '</v></c>';
                } elseif (is_float($val)) {
                    $xml .= '<c r="' . $ref . '"><v>' . $val . '</v></c>';
                } else {
                    $sval = (string) $val;
                    $idx = self::ssAdd($shared, $ssIndex, $sval);
                    $xml .= '<c r="' . $ref . '" t="s"><v>' . $idx . '</v></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';

        return [$shared, $xml];
    }

    private static function writeSharedStrings(string $dir, array $strings): void
    {
        $count = count($strings);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $count . '" uniqueCount="' . $count . '">';
        foreach ($strings as $s) {
            $xml .= '<si><t xml:space="preserve">' . self::xmlEscape($s) . '</t></si>';
        }
        $xml .= '</sst>';
        file_put_contents($dir . '/xl/sharedStrings.xml', $xml);
    }

    private static function writeSheet(string $dir, string $xml): void
    {
        file_put_contents($dir . '/xl/worksheets/sheet1.xml', $xml);
    }

    // ----- Helpers --------------------------------------------------------

    private static function cellRef(int $col, int $row): string
    {
        // 1=A, 2=B, ..., 26=Z, 27=AA
        $letters = '';
        while ($col > 0) {
            $col--;
            $letters = chr(65 + ($col % 26)) . $letters;
            $col = intdiv($col, 26);
        }
        return $letters . $row;
    }

    private static function ssAdd(array &$shared, array &$index, string $value): int
    {
        if (isset($index[$value])) return $index[$value];
        $idx = count($shared);
        $shared[] = $value;
        $index[$value] = $idx;
        return $idx;
    }

    private static function xmlEscape(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function looksLikeDate(mixed $v): bool
    {
        return is_string($v) && (bool) preg_match('/^\d{4}-\d{2}-\d{2}/', $v);
    }

    /**
     * Convert YYYY-MM-DD (or full ISO) sang Excel serial (days since 1899-12-30).
     * Excel "bug" 1900-02-29 được bỏ qua để đơn giản (không ảnh hưởng sau 1900-03-01).
     */
    private static function dateToSerial(string $iso): float
    {
        $ts = strtotime(substr($iso, 0, 10));
        if ($ts === false) return 0.0;
        // 25569 = days from 1899-12-30 to 1970-01-01
        return round(($ts / 86400) + 25569, 6);
    }

    /** @return string[] */
    private static function collectFiles(string $dir): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        $baseLen = strlen($dir) + 1;
        foreach ($it as $f) {
            $out[] = substr($f->getPathname(), $baseLen);
        }
        // ZIP yêu cầu [Content_Types].xml ở đầu.
        usort($out, fn($a, $b) => ($a === '[Content_Types].xml') ? -1 : (($b === '[Content_Types].xml') ? 1 : strcmp($a, $b)));
        return $out;
    }

    private static function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
        $it2 = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it2 as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }
}
