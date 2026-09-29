<?php
require_once __DIR__ . '/../bootstrap.php';

use PHPUnit\Framework\TestCase;

/**
 * app.js gộp các mảnh bằng window.TNTT[<tên trong manifest>]. Nếu tệp module
 * gán sai tên (vd. xlsxIo thay vì xlsx_io) thì app báo "thiếu phần" lúc chạy.
 * Test này bắt lỗi lệch tên ngay ở CI.
 */
class AssetManifestTest extends TestCase {
    private array $manifest;
    private string $modulesDir;

    protected function setUp(): void {
        $this->manifest = require __DIR__ . '/../../public/assets/asset_manifest.php';
        $this->modulesDir = __DIR__ . '/../../public/assets/js/modules/';
    }

    public function testEveryManifestModuleFileExists(): void {
        foreach ($this->manifest['js_modules'] as $name) {
            $this->assertFileExists($this->modulesDir . $name . '.js', "Thiếu tệp module $name.js");
        }
    }

    public function testEveryManifestModuleRegistersItsOwnName(): void {
        foreach ($this->manifest['js_modules'] as $name) {
            $src = file_get_contents($this->modulesDir . $name . '.js');
            $this->assertMatchesRegularExpression(
                '/(?:window\.)?TNTT\.' . preg_quote($name, '/') . '\s*=/',
                $src,
                "$name.js phải gán window.TNTT.$name (đúng tên trong asset_manifest.php)"
            );
        }
    }
}
