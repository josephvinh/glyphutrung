<?php
/**
 * VALIDATION LAYER
 *
 * Centralized input validation for API endpoints.
 * Provides fluent interface for chaining validation rules.
 */
class Validator
{
    private array $data;
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function required(string $field): self
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            $this->errors[$field] = [
                'code' => ERR_REQUIRED_FIELD,
                'message' => "Trường '{$field}' là bắt buộc."
            ];
        }
        return $this;
    }

    public function integer(string $field): self
    {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            if (!is_numeric($this->data[$field]) || (int) $this->data[$field] != $this->data[$field]) {
                $this->errors[$field] = [
                    'code' => ERR_INVALID_INPUT,
                    'message' => "Trường '{$field}' phải là số nguyên."
                ];
            }
        }
        return $this;
    }

    public function string(string $field): self
    {
        if (isset($this->data[$field]) && !is_string($this->data[$field])) {
            $this->errors[$field] = [
                'code' => ERR_INVALID_INPUT,
                'message' => "Trường '{$field}' phải là chuỗi."
            ];
        }
        return $this;
    }

    public function maxLength(string $field, int $max): self
    {
        if (isset($this->data[$field]) && strlen((string) $this->data[$field]) > $max) {
            $this->errors[$field] = [
                'code' => ERR_VALUE_OUT_OF_RANGE,
                'message' => "Trường '{$field}' không được dài quá {$max} ký tự."
            ];
        }
        return $this;
    }

    public function minLength(string $field, int $min): self
    {
        if (isset($this->data[$field]) && strlen((string) $this->data[$field]) < $min) {
            $this->errors[$field] = [
                'code' => ERR_VALUE_OUT_OF_RANGE,
                'message' => "Trường '{$field}' phải có ít nhất {$min} ký tự."
            ];
        }
        return $this;
    }

    public function inArray(string $field, array $allowed): self
    {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $allowed, true)) {
            $this->errors[$field] = [
                'code' => ERR_INVALID_INPUT,
                'message' => "Trường '{$field}' có giá trị không hợp lệ."
            ];
        }
        return $this;
    }

    public function email(string $field): self
    {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = [
                'code' => ERR_INVALID_INPUT,
                'message' => "Trường '{$field}' phải là email hợp lệ."
            ];
        }
        return $this;
    }

    public function validate(): bool
    {
        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstError(): ?array
    {
        return !empty($this->errors) ? reset($this->errors) : null;
    }
}

/**
 * CSRF VALIDATOR
 *
 * Centralized CSRF token validation with Origin/Referer checking.
 */
class CSRFValidator
{
    public static function validate(): void
    {
        // Only check for POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        // Check if session has CSRF token
        if (empty($_SESSION['csrf_token'])) {
            json_fail(ERR_CSRF_INVALID, 'CSRF token not found. Please reload the page.', 403);
        }

        // Get token from POST or header
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (!verify_csrf($token)) {
            json_fail(ERR_CSRF_INVALID, 'Invalid CSRF token.', 403);
        }

        // Optional: Check Origin header for additional security
        $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
        if ($origin) {
            $allowedOrigins = app_config('allowed_origins', []);
            if (!empty($allowedOrigins)) {
                $parsedOrigin = parse_url($origin, PHP_URL_HOST);
                $parsedSelf = parse_url(app_config('app_url'), PHP_URL_HOST);
                if ($parsedOrigin !== $parsedSelf) {
                    json_fail(ERR_CSRF_INVALID, 'Invalid request origin.', 403);
                }
            }
        }
    }
}
