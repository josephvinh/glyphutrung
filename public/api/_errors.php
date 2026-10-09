<?php
/**
 * ERROR CODE REGISTRY
 *
 * Centralized error codes for API responses.
 * All API endpoints MUST use these constants instead of string literals.
 *
 * Format: ERR_<CATEGORY>_<SUBCATEGORY>
 * Examples:
 *   - ERR_UNAUTHORIZED (HTTP 401)
 *   - ERR_PERMISSION_DENIED (HTTP 403)
 *   - ERR_NOT_FOUND_STUDENT (HTTP 404)
 */

// HTTP Status Codes
define('ERR_OK',                    'OK');                    // 200
define('ERR_CREATED',               'CREATED');               // 201
define('ERR_BAD_REQUEST',           'BAD_REQUEST');           // 400
define('ERR_UNAUTHORIZED',         'AUTH_REQUIRED');         // 401
define('ERR_FORBIDDEN',            'FORBIDDEN');             // 403
define('ERR_NOT_FOUND',             'NOT_FOUND');             // 404
define('ERR_METHOD_NOT_ALLOWED',    'METHOD_NOT_ALLOWED');   // 405
define('ERR_CONFLICT',              'CONFLICT');              // 409
define('ERR_VALIDATION_FAILED',     'VALIDATION_FAILED');     // 422
define('ERR_RATE_LIMITED',          'RATE_LIMITED');         // 429
define('ERR_INTERNAL',              'INTERNAL_ERROR');        // 500
define('ERR_SERVICE_UNAVAILABLE',   'SERVICE_UNAVAILABLE');   // 503

// Authentication & Authorization
define('ERR_INVALID_SESSION',       'INVALID_SESSION');       // 401
define('ERR_PERMISSION_DENIED',    'PERMISSION_DENIED');    // 403
define('ERR_CLASS_ACCESS_DENIED',   'CLASS_ACCESS_DENIED'); // 403
define('ERR_CSRF_INVALID',         'CSRF_INVALID');         // 403
define('ERR_ACCOUNT_DISABLED',      'ACCOUNT_DISABLED');     // 403
define('ERR_PASSWORD_EXPIRED',      'PASSWORD_EXPIRED');     // 403

// Resource Not Found
define('ERR_NOT_FOUND_CLASS',       'CLASS_NOT_FOUND');      // 404
define('ERR_NOT_FOUND_STUDENT',     'STUDENT_NOT_FOUND');    // 404
define('ERR_NOT_FOUND_PROGRAM',     'PROGRAM_NOT_FOUND');    // 404
define('ERR_NOT_FOUND_YEAR',        'YEAR_NOT_FOUND');       // 404

// Data Conflicts
define('ERR_DUPLICATE_ENTRY',      'DUPLICATE_ENTRY');      // 409
define('ERR_DATA_CONFLICT',         'DATA_CONFLICT');        // 409
define('ERR_YEAR_LOCKED',          'YEAR_LOCKED');          // 409

// Validation Errors
define('ERR_INVALID_INPUT',         'INVALID_INPUT');         // 422
define('ERR_REQUIRED_FIELD',        'REQUIRED_FIELD');        // 422
define('ERR_VALUE_OUT_OF_RANGE',    'VALUE_OUT_OF_RANGE');    // 422

// Rate Limiting
define('ERR_RATE_LIMIT_EXCEEDED',   'RATE_LIMIT_EXCEEDED');  // 429
define('ERR_LOGIN_ATTEMPTS_EXCEEDED', 'LOGIN_ATTEMPTS_EXCEEDED'); // 429
