# TC-11 to TC-36: Student Management

## Test Scope

Testing student CRUD operations, filtering, bulk actions, and Excel import/export.

## Test Cases

| ID | Description |
|----|-------------|
| TC-11 | Display student list with pagination (20 per page) |
| TC-12 | Search students by name |
| TC-13 | Filter by class |
| TC-14 | Filter by gender (Male/Female) |
| TC-15 | Filter by age range (From/To) |
| TC-16 | Filter by status (active/inactive/transferred) |
| TC-17 | Filter by address |
| TC-18 | Add new student with all fields |
| TC-19 | Add student fails without required fields |
| TC-20 | Auto-generate student code (format: TN + YEAR + SEQ) |
| TC-21 | Edit student information |
| TC-22 | Change student class (auto-update block) |
| TC-23 | Warning when closing form with unsaved changes |
| TC-24 | Auto-save draft to localStorage |
| TC-25 | Delete single student |
| TC-26 | Bulk delete multiple students |
| TC-27 | Bulk move students to another class |
| TC-28 | Export student list to Excel |
| TC-29 | Download Excel template when no students |
| TC-30 | Import students from Excel file |
| TC-31 | Export student list to PDF (table format) |
| TC-32 | Export student cards to PDF |
| TC-33 | Keyboard shortcut: j/k to navigate |
| TC-34 | Keyboard shortcut: e to edit |
| TC-35 | Keyboard shortcut: n for new student |
| TC-36 | Copy phone number to clipboard |

## Expected Results

### List View

- Initial display: 20 students
- Load more: +20 students each time
- Search is case-insensitive, accent-insensitive
- Filters combine with AND logic

### Student Code Format

- Pattern: TN + 2-digit year + 4-digit sequence
- Example: TN260001

### Bulk Operations

- Bulk delete requires confirmation with count
- Bulk move shows target class selector
- Backend validates class ownership

### Import/Export

- Excel columns: code, holyName, name, gender, birthDate, block, className, status, fatherName, fatherPhone, motherName, motherPhone, address
- Rows starting with # are ignored
- Missing required columns show warning

## Backend APIs

- POST /api/students.php?action=save
- POST /api/students.php?action=bulk_move
- POST /api/students.php?action=bulk_delete
- POST /api/students.php?action=import
- GET /api/students.php?action=next_code
