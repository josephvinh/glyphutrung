# TC-77 to TC-88: Staff Management

## Test Scope

Testing staff accounts, approvals, role management, and password reset.

## Test Cases

| ID | Description |
|----|-------------|
| TC-77 | Display staff list sorted by role level |
| TC-78 | Filter staff by role |
| TC-79 | Search staff member |
| TC-80 | Approve new registration |
| TC-81 | Reject registration (delete account) |
| TC-82 | Edit staff member info |
| TC-83 | Change staff role |
| TC-84 | Cannot edit role if has active assignment |
| TC-85 | Cannot edit admin/BDH accounts |
| TC-86 | Reset password and show temp password |
| TC-87 | Toggle Thủ Thư (Librarian) role |
| TC-88 | Delete staff member |

## Role Hierarchy

| Role | Level | Scope |
|------|-------|-------|
| admin | 5 | Toàn đoàn |
| bdh | 4 | Toàn đoàn |
| truong_khoi | 3 | Khối |
| glv_chu_nhiem | 2 | Lớp |
| glv | 1 | Lớp |

## Expected Results

### Account Status

- chờ duyệt: New registration pending approval
- đang phục vụ: Active account
- đã nghỉ: Inactive account

### Registration Approval

- Approve: Set status to đang phục vụ, assign default role (glv)
- Reject: Delete member record completely

### Password Reset

- Generate random temp password
- Set must_change_pw = true
- Show password ONCE only (log or display)

### Protected Roles

- admin and bdh accounts cannot be edited/deleted from Staff screen
- Their info can only be changed by admin

## Backend APIs

- POST /api/org.php?action=approveMember
- POST /api/org.php?action=rejectMember
- POST /api/org.php?action=saveMember
- POST /api/org.php?action=deleteMember
- POST /api/org.php?action=resetPassword

## Permission

- Only BĐH and Admin can access Staff module
