<?php
// tests/unit/DiffArraysTest.php
//
// Helper diff_arrays() trong public/api/_common.php:
//   1) Trả về CHỈ trường đổi (bỏ trường giống nhau).
//   2) Trước/sau đều có cùng key; giá trị null được giữ nguyên.
//   3) Tham số $keys giới hạn cột theo dõi (chống lộ password_hash/token).
//   4) So sánh bằng JSON nên int/string/float khác kiểu → vẫn tính là đổi.
//   5) Trường chỉ có ở "after" (thêm mới) và chỉ có ở "before" (xoá) đều lọt.
//   6) Mảng lồng được so sánh theo nội dung, không phải tham chiếu.

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../public/api/_common.php';

use PHPUnit\Framework\TestCase;

class DiffArraysTest extends TestCase
{
    public function test_chi_tra_ve_truong_doi(): void
    {
        $before = ['name' => 'A', 'class_id' => 1, 'note' => 'old'];
        $after  = ['name' => 'A', 'class_id' => 2, 'note' => 'old'];
        $r = diff_arrays($before, $after);
        $this->assertSame(['class_id' => 2], $r['after']);
        $this->assertSame(['class_id' => 1], $r['before']);
    }

    public function test_khong_doi_khi_giong_nhau(): void
    {
        $before = ['a' => 1, 'b' => 'x', 'c' => null];
        $after  = ['a' => 1, 'b' => 'x', 'c' => null];
        $r = diff_arrays($before, $after);
        $this->assertSame([], $r['before']);
        $this->assertSame([], $r['after']);
    }

    public function test_giu_cac_truong_cung_key_khac_gia_tri(): void
    {
        $before = ['name' => 'A', 'phone' => '0901'];
        $after  = ['name' => 'B', 'phone' => '0902'];
        $r = diff_arrays($before, $after);
        $this->assertSame(['name' => 'B', 'phone' => '0902'], $r['after']);
        $this->assertSame(['name' => 'A', 'phone' => '0901'], $r['before']);
    }

    public function test_tham_so_keys_gioi_han_cot_theo_doi(): void
    {
        $before = ['name' => 'A', 'password_hash' => 'SECRET-BEFORE'];
        $after  = ['name' => 'B', 'password_hash' => 'SECRET-AFTER'];
        // Chỉ theo dõi 'name' — password_hash KHÔNG lọt vào diff.
        $r = diff_arrays($before, $after, ['name']);
        $this->assertSame(['name' => 'B'], $r['after']);
        $this->assertSame(['name' => 'A'], $r['before']);
        $this->assertArrayNotHasKey('password_hash', $r['after']);
        $this->assertArrayNotHasKey('password_hash', $r['before']);
    }

    public function test_so_sanh_int_va_string_khac_kieu_tinh_la_doi(): void
    {
        $before = ['score' => 1];
        $after  = ['score' => '1'];
        $r = diff_arrays($before, $after);
        $this->assertNotEmpty($r['after'], 'int 1 vs string "1" phải tính là đổi (khác kiểu)');
    }

    public function test_so_sanh_int_va_int_giong_nhau_khong_doi(): void
    {
        $before = ['score' => 1];
        $after  = ['score' => 1];
        $r = diff_arrays($before, $after);
        $this->assertSame([], $r['after']);
    }

    public function test_truong_chi_co_o_after_la_them_moi(): void
    {
        $before = [];
        $after  = ['status' => 'moi'];
        $r = diff_arrays($before, $after);
        $this->assertSame(['status' => 'moi'], $r['after']);
        $this->assertSame(['status' => null], $r['before']);
    }

    public function test_truong_chi_co_o_before_la_xoa(): void
    {
        $before = ['status' => 'cu'];
        $after  = [];
        $r = diff_arrays($before, $after);
        $this->assertSame([], $r['after']);
        $this->assertSame(['status' => 'cu'], $r['before']);
    }

    public function test_mang_long_so_sanh_theo_noi_dung(): void
    {
        $before = ['meta' => ['a' => 1, 'b' => 2]];
        $after  = ['meta' => ['a' => 1, 'b' => 3]];
        $r = diff_arrays($before, $after);
        $this->assertNotEmpty($r['after'], 'mảng lồng khác giá trị phải tính là đổi');
    }

    public function test_mang_long_giong_nhau_khong_doi(): void
    {
        $before = ['meta' => ['a' => 1, 'b' => 2]];
        $after  = ['meta' => ['a' => 1, 'b' => 2]];
        $r = diff_arrays($before, $after);
        $this->assertSame([], $r['after']);
    }

    public function test_keys_mac_dinh_lay_hop_cua_hai_mang(): void
    {
        // Truờng hợp 'after' có key mới mà 'before' không có, vẫn lọt vào diff.
        $before = ['a' => 1];
        $after  = ['a' => 1, 'b' => 2];
        $r = diff_arrays($before, $after);
        $this->assertSame(['b' => 2], $r['after']);
        $this->assertSame(['b' => null], $r['before']);
    }
}
