<?php
require_once __DIR__ . '/../bootstrap.php';

use PHPUnit\Framework\TestCase;

/**
 * Bộ helper phản hồi API trong public/api/_response.php:
 * json_ok(), json_created(), json_fail(), json_validation_fail(), json_paginated().
 *
 * Các hàm json_* có kiểu trả về `never` (gửi JSON rồi exit), nên phần kiểm
 * HÀNH VI chạy chúng trong một tiến trình PHP con và đọc lại JSON + mã HTTP —
 * gọi thẳng trong PHPUnit sẽ giết luôn tiến trình test.
 */
class ResponseFormatTest extends TestCase {

    // ---- Cấu trúc meta / request id ----------------------------------------

    public function testResponseMetaStructure(): void {
        $meta = response_meta(200);
        $this->assertArrayHasKey('timestamp', $meta);
        $this->assertArrayHasKey('requestId', $meta);
        $this->assertArrayHasKey('status', $meta);
        $this->assertSame('OK', $meta['status']);
    }

    public function testResponseMetaCreatedStatus(): void {
        $this->assertSame('CREATED', response_meta(201)['status']);
    }

    public function testRequestIdFormatAndUniqueness(): void {
        $id1 = generate_request_id();
        $id2 = generate_request_id();
        $this->assertMatchesRegularExpression('/^req_[0-9a-f]{16}$/', $id1);
        $this->assertMatchesRegularExpression('/^req_[0-9a-f]{16}$/', $id2);
        $this->assertNotSame($id1, $id2);
    }

    // ---- Chữ ký hàm --------------------------------------------------------

    public function testAllResponseFunctionsReturnNever(): void {
        foreach (['json_ok', 'json_created', 'json_fail', 'json_validation_fail', 'json_paginated'] as $fn) {
            $this->assertTrue(function_exists($fn), "$fn phải tồn tại");
            $type = (new ReflectionFunction($fn))->getReturnType();
            $this->assertNotNull($type, "$fn phải khai kiểu trả về");
            $this->assertSame('never', (string) $type, "$fn phải trả về never");
        }
    }

    public function testJsonFailSignature(): void {
        $params = (new ReflectionFunction('json_fail'))->getParameters();
        $this->assertCount(4, $params);
        $this->assertSame(['code', 'message', 'details', 'status'], array_map(fn($p) => $p->getName(), $params));
        $this->assertSame('string', (string) $params[0]->getType());
        // string|int: tham số 2 là int ở kiểu gọi cũ json_fail($message, $httpStatus)
        $this->assertSame('string|int', (string) $params[1]->getType());
        $this->assertSame('array', (string) $params[2]->getType());
        $this->assertSame('int', (string) $params[3]->getType());
    }

    public function testJsonValidationFailIsDefinedInResponseFile(): void {
        $file = (new ReflectionFunction('json_validation_fail'))->getFileName();
        $this->assertStringEndsWith('_response.php', $file);
    }

    // ---- Hành vi json_fail: cả 3 kiểu gọi (chạy trong tiến trình con) ------

    public function testJsonFailOldStyleMessageOnly(): void {
        // ~110 nơi gọi cũ: json_fail('Câu báo lỗi.')
        [$status, $body] = $this->runInChild("json_fail('Mật khẩu hiện tại không đúng.');");
        $this->assertSame(400, $status);
        $this->assertFalse($body['ok']);
        $this->assertSame('BAD_REQUEST', $body['error']['code']);
        $this->assertSame('Mật khẩu hiện tại không đúng.', $body['error']['message']);
    }

    public function testJsonFailOldStyleMessageAndStatus(): void {
        // Kiểu cũ: json_fail('Câu báo lỗi.', 404)
        [$status, $body] = $this->runInChild("json_fail('Không tìm thấy chương trình.', 404);");
        $this->assertSame(404, $status);
        $this->assertSame('BAD_REQUEST', $body['error']['code']);
        $this->assertSame('Không tìm thấy chương trình.', $body['error']['message']);
    }

    public function testJsonFailNewStyleInfersStatusFromCode(): void {
        // require_login_pending_pw(): json_fail(ERR_UNAUTHORIZED, ...) không truyền status → phải là 401
        [$status, $body] = $this->runInChild("json_fail(ERR_UNAUTHORIZED, 'Chưa đăng nhập.');");
        $this->assertSame(401, $status);
        $this->assertSame('AUTH_REQUIRED', $body['error']['code']);
        $this->assertSame('Chưa đăng nhập.', $body['error']['message']);
    }

    public function testJsonFailNewStyleExplicitStatusAndDetails(): void {
        [$status, $body] = $this->runInChild(
            "json_fail(ERR_PASSWORD_EXPIRED, 'Bạn cần đổi mật khẩu.', ['field' => 'pw'], 403);"
        );
        $this->assertSame(403, $status);
        $this->assertSame('PASSWORD_EXPIRED', $body['error']['code']);
        $this->assertSame(['field' => 'pw'], $body['error']['details']);
    }

    public function testJsonValidationFailReturns422WithFields(): void {
        [$status, $body] = $this->runInChild("json_validation_fail(['name' => 'Bắt buộc']);");
        $this->assertSame(422, $status);
        $this->assertSame('VALIDATION_FAILED', $body['error']['code']);
        $this->assertSame(['name' => 'Bắt buộc'], $body['error']['details']['fields']);
    }

    /**
     * Chạy $code trong `php -r` sau khi nạp _http_util.php (kèm _response.php,
     * _errors.php). Trả về [mã HTTP, JSON đã decode].
     */
    private function runInChild(string $code): array {
        $util = var_export(realpath(__DIR__ . '/../../public/api/_http_util.php'), true);
        $script = 'register_shutdown_function(function () { fwrite(STDERR, (string) http_response_code()); });'
                . "require $util;"
                . $code;

        $proc = proc_open(
            [PHP_BINARY, '-r', $script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        $this->assertIsResource($proc, 'Không mở được tiến trình PHP con');
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        $body = json_decode($stdout, true);
        $this->assertIsArray($body, "Tiến trình con không trả JSON hợp lệ. stdout: $stdout | stderr: $stderr");
        $this->assertMatchesRegularExpression('/^\d{3}$/', trim($stderr), "Không đọc được mã HTTP. stderr: $stderr");
        return [(int) trim($stderr), $body];
    }
}
