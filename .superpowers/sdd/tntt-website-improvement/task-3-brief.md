# Task 3 Brief: Security - Security Headers

## Task Description
Add comprehensive security HTTP headers to protect against common web vulnerabilities.

## Files to Create/Modify

### Modify: `public/api/_bootstrap.php`
Add security headers after existing header calls:

```php
// Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none';");
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
```

### Modify: `public/.htaccess`
Add security headers for Apache:

```apache
<IfModule mod_headers.c>
    # Security Headers
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
    
    # Prevent MIME type sniffing
    Header always set X-Content-Security-Policy "default-src 'self'"
</IfModule>

# Prevent access to sensitive files
<FilesMatch "^\.">
    Order allow,deny
    Deny from all
</FilesMatch>

# Prevent directory listing
Options -Indexes
```

## Requirements
- Add X-Content-Type-Options: nosniff
- Add X-Frame-Options: SAMEORIGIN
- Add X-XSS-Protection: 1; mode=block
- Add Referrer-Policy: strict-origin-when-cross-origin
- Add Permissions-Policy headers
- Add CSP header (relaxed for inline scripts/styles which the app uses)
- Update .htaccess with security rules

## Acceptance Criteria
1. All security headers are set in PHP bootstrap
2. Apache .htaccess has corresponding security rules
3. Directory listing is disabled
4. Sensitive files are protected

## Notes
- The CSP is relaxed to allow 'unsafe-inline' and 'unsafe-eval' because the app uses Alpine.js which requires inline event handlers
- In a future task, this can be tightened with nonces
