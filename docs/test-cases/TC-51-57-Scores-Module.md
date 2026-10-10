# TC-51 to TC-57: Scores Module

## Test Scope

Testing score input, average calculation, ranking, and export.

## Test Cases

| ID | Description |
|----|-------------|
| TC-51 | Enter oral score (Miệng) |
| TC-52 | Invalid score input (0-10 range) |
| TC-53 | Delete score by clearing input |
| TC-54 | Calculate weighted average |
| TC-55 | Calculate average with missing columns |
| TC-56 | Academic ranking classification |
| TC-57 | Export score table to Excel |

## Score Types

| Type | Weight | Short |
|------|--------|-------|
| Miệng (Oral) | 1 | M |
| 15 phút (15 min) | 1 | 15 |
| Giữa kỳ (Midterm) | 2 | GK |
| Cuối kỳ (Final) | 3 | CK |

## Average Formula

ĐTB = (M×1 + 15p×1 + GK×2 + CK×3) / 7

## Ranking Thresholds

| Average | Rank | Color |
|---------|------|-------|
| >= 8.0 | Giỏi (Excellent) | Green |
| >= 6.5 | Khá (Good) | Blue |
| >= 5.0 | Trung bình (Average) | Amber |
| < 5.0 | Yếu (Poor) | Red |

## Expected Results

### Score Entry

- Real-time save on tab/blur
- Empty input = delete score
- Invalid input (letters, >10, <0) shows warning and reverts

### Average Calculation

- Only calculates on columns with values
- Example: Only M=8 → ĐTB = 8.0

### Permission Check

- Only users with edit permission on scores module
- GLV sees only assigned class students

## Backend APIs

- POST /api/scores.php?action=set
- GET /api/export.php?action=scores

## Test Data

Student: Nguyễn Văn A
Term: Học kỳ 1
Scores: M=8, 15p=7, GK=8, CK=9
Expected: ĐTB = (8+7+16+27)/7 = 60/7 = 8.6 → Giỏi
