# TC-110 to TC-119: Reports & Public Pages

## Test Scope

Testing reports, analytics, leaderboard, and public pages.

## Test Cases

| ID | Description |
|----|-------------|
| TC-110 | View monthly attendance report |
| TC-111 | View student leaderboard (bxh.php) |
| TC-112 | View dashboard statistics |
| TC-113 | Public student lookup (tracuu.php) |
| TC-114 | Public stamp book view (somoc.php) |
| TC-115 | Online pre-order gift |
| TC-116 | View/edit personal profile |
| TC-117 | Change password |
| TC-118 | Forced password change |
| TC-119 | Switch school year |

## Report Types

| Report | URL | Access |
|--------|-----|--------|
| Attendance Report | #/reports | BĐH+ |
| Score Report | #/scores | GLV+ |
| Monthly Stats | #/stats | BĐH+ |
| Analytics | #/analytics | BĐH+ |

## Public Pages

| Page | URL | Access | Data Shown |
|------|-----|--------|------------|
| Tra cứu | tracuu.php | None | Name, class, status only |
| Sổ Mộc | somoc.php | Code + password | Stamp balance, history |
| BXH | bxh.php | None | Name, rank, points |

## Expected Results

### Public Page Security

- tracuu.php: No sensitive data (address, phone)
- somoc.php: Requires student code + password
- bxh.php: Leaderboard only, no personal info

### School Year

- Current year filter applies to all data
- Switching year updates all modules
- Past years read-only

## Backend APIs

- GET /api/export.php?action=attendance-detail
- GET /api/export.php?action=scores
- GET /api/bxh.php
- GET /api/somoc_order.php
- POST /api/settings.php?action=changePassword

## Permission Summary

| Module | admin | bdh | truong_khoi | glv | du_bi | thu_thu |
|--------|-------|-----|-------------|-----|-------|---------|
| reports | edit | view | view | - | - | - |
| tracuu | - | - | - | - | - | - |
| somoc | - | - | - | - | - | - |
| settings | edit | edit | edit | edit | view | view |
