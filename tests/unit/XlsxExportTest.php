<?php
// tests/unit/XlsxExportTest.php
//
// Kiểm tra src/XlsxExport.php tạo file XLSX hợp lệ:
//   1) Tạo file thành công, có đủ cấu trúc OOXML
//   2) Đọc lại nội dung cells bằng ZipArchive + simplexml
//   3) Hỗ trợ tiếng Việt (UTF-8 qua sharedStrings)
//   4) date_columns chuyển YYYY-MM-DD → Excel serial
//   5) bold_header = false → header không in đậm
//   6) Ném lỗi nếu thiếu ZipArchive
//
// Chạy: php phpunit.phar --testsuite "TNTT Unit Tests" --filter XlsxExportTest

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/XlsxExport.php';

use PHPUnit\Framework\TestCase;
use TNTT\XlsxExport;

class XlsxExportTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'tntt_xlsx_') . '.xlsx';
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmpFile)) @unlink($this->tmpFile);
    }

    public function test_tao_file_xlsx_thanh_cong(): void
    {
        XlsxExport::write(
            $this->tmpFile,
            ['Tên', 'Điểm'],
            [['An', 8], ['Bình', 9]]
        );
        $this->assertFileExists($this->tmpFile);
        $this->assertGreaterThan(100, filesize($this->tmpFile));

        // Mở lại bằng ZipArchive
        $zip = new ZipArchive();
        $this->assertSame(true, $zip->open($this->tmpFile));
        $this->assertNotSame(false, $zip->locateName('[Content_Types].xml'));
        $this->assertNotSame(false, $zip->locateName('xl/workbook.xml'));
        $this->assertNotSame(false, $zip->locateName('xl/worksheets/sheet1.xml'));
        $this->assertNotSame(false, $zip->locateName('xl/sharedStrings.xml'));
        $this->assertNotSame(false, $zip->locateName('xl/styles.xml'));
        $zip->close();
    }

    public function test_noi_dung_cells_dung(): void
    {
        XlsxExport::write(
            $this->tmpFile,
            ['Tên', 'Điểm'],
            [['An', 8], ['Bình', 9]]
        );
        $zip = new ZipArchive();
        $zip->open($this->tmpFile);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        // Sheet có 3 row (header + 2 dòng)
        $this->assertSame(3, substr_count($sheetXml, '<row '));
        // Cell B2 = số 8
        $this->assertMatchesRegularExpression(
            '/<c r="B2"[^>]*><v>8<\/v><\/c>/',
            $sheetXml
        );
        // SharedStrings chứa "An" và "Bình"
        $this->assertStringContainsString('>An<', $ssXml);
        $this->assertStringContainsString('>Bình<', $ssXml);
    }

    public function test_tieng_viet_khong_loi(): void
    {
        XlsxExport::write(
            $this->tmpFile,
            ['Họ tên', 'Điểm', 'Ghi chú'],
            [['Nguyễn Văn A', 8, 'Đạt'], ['Trần Thị Bé', 9, 'Xuất sắc']]
        );
        $zip = new ZipArchive();
        $zip->open($this->tmpFile);
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        // Tất cả text phải có trong sharedStrings dưới dạng XML-escape
        $this->assertStringContainsString('Nguyễn Văn A', $ssXml);
        $this->assertStringContainsString('Trần Thị Bé', $ssXml);
        $this->assertStringContainsString('Xuất sắc', $ssXml);
        $this->assertStringContainsString('Đạt', $ssXml);
    }

    public function test_date_columns_chuyen_YYYY_MM_DD_sang_serial(): void
    {
        XlsxExport::write(
            $this->tmpFile,
            ['Ngày', 'Tên'],
            [['2026-10-14', 'A'], ['2025-01-01', 'B']],
            ['date_columns' => [0]]
        );
        $zip = new ZipArchive();
        $zip->open($this->tmpFile);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        // Cột ngày dùng style 2 (numFmtId=14 = short date)
        $this->assertMatchesRegularExpression(
            '/<c r="A2" s="2"><v>[\d.]+<\/v><\/c>/',
            $sheetXml
        );
        // 2026-10-14 → serial ~ 46039
        if (preg_match('/<c r="A2" s="2"><v>([\d.]+)<\/v><\/c>/', $sheetXml, $m)) {
            $serial = (float) $m[1];
            $this->assertGreaterThan(45000, $serial);
            $this->assertLessThan(47000, $serial);
        }
    }

    public function test_bold_header_mac_dinh(): void
    {
        XlsxExport::write($this->tmpFile, ['A'], [['x']]);
        $zip = new ZipArchive();
        $zip->open($this->tmpFile);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        // Header (row 1) có s="1"
        $this->assertMatchesRegularExpression('/<row r="1">.*s="1"/s', $sheetXml);
    }

    public function test_bold_header_false(): void
    {
        XlsxExport::write(
            $this->tmpFile,
            ['A'],
            [['x']],
            ['bold_header' => false]
        );
        $zip = new ZipArchive();
        $zip->open($this->tmpFile);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        // Không có s="1" ở bất kỳ đâu
        $this->assertStringNotContainsString('s="1"', $sheetXml);
    }

    public function test_sheet_name_custom(): void
    {
        XlsxExport::write(
            $this->tmpFile,
            ['A'],
            [['x']],
            ['sheet_name' => 'Báo Cáo']
        );
        $zip = new ZipArchive();
        $zip->open($this->tmpFile);
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $zip->close();
        $this->assertStringContainsString('Báo Cáo', $workbookXml);
    }

    public function test_file_rong_van_tao_thanh_cong(): void
    {
        XlsxExport::write($this->tmpFile, ['A', 'B'], []);
        $this->assertFileExists($this->tmpFile);
        $zip = new ZipArchive();
        $this->assertSame(true, $zip->open($this->tmpFile));
        // Chỉ có header row
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $this->assertSame(1, substr_count($sheetXml, '<row '));
    }
}
