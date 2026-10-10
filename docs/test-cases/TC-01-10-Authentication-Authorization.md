# TC-01 to TC-10: Authentication & Authorization

## Test Scope

Testing authentication, session management, role-based permissions, and security features.

## Test Cases

| ID | Description |
|----|-------------|
| TC-01 | Login with valid credentials |
| TC-02 | Login with invalid password |
| TC-03 | Account locked after multiple failed attempts |
| TC-04 | Disabled account cannot login |
| TC-05 | Force password change on first login |
| TC-06 | GLV only sees assigned class students |
| TC-07 | Truong Khoi sees all classes in their block |
| TC-08 | BDH sees entire organization |
| TC-09 | Access denied to unauthorized API |
| TC-10 | POST request without CSRF token rejected |

## Expected Results

### Login Flow

- Successful login redirects to Dashboard
- Failed login increments attempt counter
- 5 failed attempts within 15 minutes = 15-minute lockout
- Disabled accounts return HTTP 403
- First-time users must change password

### Permission Checks

- GLV: allowed_class_ids() returns only assigned class
- Truong Khoi: responsible_blocks() returns only their block
- BDH/Admin: Full organization access (null)

### Security

- CSRF token required for all POST requests
- Rate limiting: 5 attempts/15min/phone, 20/15min/IP

## Backend APIs to Test

- POST /api/auth.php - Login
- POST /api/auth.php?action=password - Change password
- All write endpoints with require_write() check
