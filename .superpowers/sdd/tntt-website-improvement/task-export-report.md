# Task Report: Export Reports PDF/Excel

## Summary
Implemented export functionality for reports to PDF, Excel (XLS), and CSV formats using browser-side data URLs.

## Files Created

### 1. `public/api/export.php`
**Purpose:** Backend API endpoint for generating exportable files

**Actions supported:**
- `report` - Export individual report cards (PDF/CSV/XLS)
- `attendance` - Export attendance sheet (CSV/XLS)
- `scores` - Export score list (CSV/XLS)

**Key features:**
- Returns data URLs (base64 encoded) for browser download
- No server-side file storage required
- Helper functions for HTML report card generation and CSV building
- UTF-8 BOM for proper Vietnamese character encoding

### 2. `public/assets/js/modules/export.js`
**Purpose:** JavaScript module for triggering exports from the frontend

**Methods:**
- `report(termId, studentId, format)` - Export single report card
- `attendance(yearId, format)` - Export attendance sheet
- `scores(termId, classId, format)` - Export score list
- `classReports(className, format)` - Batch export for entire class
- `_download(dataUrl, filename)` - Helper to trigger browser download

## Files Modified

### 1. `views/module_reports.php`
**Changes:**
- Replaced single "Xuất" button with three format-specific buttons:
  - CSV button (grey)
  - Excel button (green/emerald)
  - PDF button (blue)
- Icons from Lucide library

### 2. `public/assets/js/modules/reports.js`
**Changes:**
- Added `exportReport(format)` method that calls `window.TNTT.export.report()`
- Loops through all students in current class and triggers downloads
- Shows success/error count after export

## Implementation Details

### PDF Export
- Generates print-ready HTML with inline CSS
- Returns as data URL that browser can print to PDF via "Print > Save as PDF"
- Includes: student info, attendance stats, scores, conduct, rank, remarks, signatures

### CSV/Excel Export
- Uses UTF-8 BOM (`\xEF\xBB\xBF`) for proper Vietnamese character support
- Base64 encoded data URLs for reliable download
- Attendance: Matrix format with dates as columns, P/L/E/A codes
- Scores: Student rows with score type columns, auto-calculated averages

### Download Mechanism
- Uses `<a download>` pattern with data URLs
- No intermediate server file storage
- Files download directly to user's browser

## Acceptance Criteria Status

| Criteria | Status |
|----------|--------|
| PDF export for report cards | Implemented (returns HTML for print-to-PDF) |
| CSV export for attendance | Implemented |
| Excel export for scores | Implemented (XLS format) |
| Browser-side downloads | Implemented (data URLs) |

## Notes
- Export API requires login (uses `require_login()`)
- All exports are scoped to the current school year
- Class filtering is supported via `classId` parameter
- The PDF format returns HTML that users can print to PDF using browser's print function
