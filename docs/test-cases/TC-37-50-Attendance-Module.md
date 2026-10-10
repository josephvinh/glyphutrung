# TC-37 to TC-50: Attendance Module

## Test Scope

Testing attendance marking, QR scanning, status inference, and offline support.

## Test Cases

| ID | Description |
|----|-------------|
| TC-37 | Open attendance session for today |
| TC-38 | Show only programs that have started |
| TC-39 | Show past programs for editing |
| TC-40 | Mark student as Present |
| TC-41 | Remove attendance (toggle off) |
| TC-42 | Auto-mark Late after cutoff time |
| TC-43 | Prevent double-tap within 450ms |
| TC-44 | Show Excused Absent for approved leaves |
| TC-45 | Show Unexcused Absent after cutoff |
| TC-46 | Show Not Marked before cutoff |
| TC-47 | Session statistics (Present/Late/Absent) |
| TC-48 | QR scan student card |
| TC-49 | Offline attendance queue |
| TC-50 | Export attendance report to Excel |

## Expected Results

### Attendance Status Logic

- Present: Manual tap or QR scan before cutoff
- Late: Manual tap or QR scan after cutoff (start_time + 30 min)
- Excused Absent: Has approved leave for this date
- Unexcused Absent: No record + past cutoff
- Not Marked: No record + before cutoff

### Session Statistics

```
Present: X | Late: Y | Absent: Z | Total: N
```

### Offline Support

- Store attendance actions in localStorage when offline
- Sync queue when online
- Badge shows pending sync count

### Cutoff Time

- Default: start_time + 30 minutes (configurable per program)
- Stored in program.cutoffTime

## Backend APIs

- POST /api/attendance.php?action=toggle
- POST /api/attendance.php?action=lookup
- POST /api/attendance.php?action=scan
- GET /api/export.php?action=attendance-detail

## Permission Check

- scan_class_ids() limits visible students
- GLV sees only assigned class
- Trưởng Khối sees entire block
