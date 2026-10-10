# TC-58 to TC-65: Programs Module

## Test Scope

Testing program/activity management, scheduling, and class assignments.

## Test Cases

| ID | Description |
|----|-------------|
| TC-58 | Display program list |
| TC-59 | Multi-day recurring program |
| TC-60 | Campaign/single-event program |
| TC-61 | Program with effective date range |
| TC-62 | Create recurring program |
| TC-63 | Create campaign program |
| TC-64 | Toggle program active/inactive |
| TC-65 | Assign classes to program |

## Program Types

| Type | Schedule | Event Date |
|------|----------|------------|
| Bắt buộc (Required) | Repeating: dayOfWeek or daysOfWeek | No |
| Chiến dịch (Campaign) | Single event | Required |

## Expected Results

### Program Fields

- name: Program name
- type: Bắt buộc or Chiến dịch
- startTime: HH:MM format
- cutoffTime: HH:MM (optional, defaults to start + 30 min)
- dayOfWeek: 0=Sunday, 6=Saturday
- daysOfWeek: Array for multi-day programs
- eventDate: YYYY-MM-DD for campaigns
- effectiveFrom: Start date of validity
- effectiveTo: End date of validity
- status: kích hoạt or đã đóng
- countForAttendance: Boolean
- classIds: Array of assigned class IDs

### Validation

- startTime is required
- cutoffTime must be after startTime
- eventDate required for campaigns
- effectiveFrom <= effectiveTo

### Attendance Integration

- Programs with countForAttendance=true affect attendance stats
- Class assignment limits visible students in attendance

## Backend APIs

- POST /api/programs.php?action=save
- POST /api/programs.php?action=delete

## Permission

- Only BĐH and Admin can manage programs
