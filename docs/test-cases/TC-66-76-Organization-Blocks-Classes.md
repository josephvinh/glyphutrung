# TC-66 to TC-76: Organization - Blocks & Classes

## Test Scope

Testing blocks, classes management, and assignment assignments.

## Test Cases

| ID | Description |
|----|-------------|
| TC-66 | Create new block |
| TC-67 | Rename block with cascade update |
| TC-68 | Delete block with existing classes fails |
| TC-69 | Assign Trưởng Khối (Block Head) |
| TC-70 | Create new class |
| TC-71 | Rename class with cascade update |
| TC-72 | Delete class with existing students fails |
| TC-73 | Assign GLV Chủ Nhiệm (Class Head) |
| TC-74 | Add GLV Phụ Tá (Assistant) to class |
| TC-75 | Remove GLV assignment from class |
| TC-76 | Display class student count |

## Expected Results

### Block Operations

- Create: INSERT INTO blocks
- Rename: Cascade updates classes, students, members, announcements
- Delete: Only if no classes exist

### Class Operations

- Create: INSERT INTO classes (requires block)
- Rename: Cascade updates students, members, announcements
- Delete: Only if no students and no GLV assignments

### Assignments

- Trưởng Khối: One per block, role = truong_khoi
- GLV Chủ Nhiệm: One per class, role = glv_chu_nhiem
- GLV Phụ Tá: Multiple per class, role = glv
- Assignment stored in member_assignments table

### Permission

- responsible_blocks() determines which blocks user can manage
- Only BĐH and Admin can manage all blocks/classes
- Trưởng Khối can only manage their own block

## Backend APIs

- POST /api/org.php?action=saveBlock
- POST /api/org.php?action=deleteBlock
- POST /api/org.php?action=saveClass
- POST /api/org.php?action=deleteClass
- POST /api/assignments.php?action=create
- POST /api/assignments.php?action=end
