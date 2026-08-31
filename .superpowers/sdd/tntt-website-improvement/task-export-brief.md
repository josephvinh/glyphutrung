# Task: Export Reports PDF/Excel

## Task Description
Add the ability to export reports to PDF and Excel formats.

## Files to Create/Modify

### Create: `public/api/export.php`
```php
<?php
/**
 * EXPORT REPORTS
 * 
 *   POST api/export.php?action=report { termId, studentId, format }
 *   POST api/export.php?action=attendance { yearId, format }
 *   POST api/export.php?action=scores { termId, format }
 */

require __DIR__ . '/_bootstrap.php';

$me   = require_login();
$year = current_year();
if (!$year) json_fail('Chưa có niên khoá nào đang mở.', 409);

$in     = json_input();
$action = $_GET['action'] ?? '';
$format = strtolower($in['format'] ?? 'pdf');

if (!in_array($format, ['pdf', 'excel', 'csv'])) {
    json_fail('Định dạng không hỗ trợ.', 400);
}
```

### Create: `public/assets/js/modules/export.js`
```javascript
window.TNTT.export = {
    async report(termId, studentId, format = 'pdf') {
        const r = await this.api('export', 'report', {
            termId, studentId, format
        });
        if (r.ok && r.url) {
            window.open(r.url, '_blank');
        }
    },
    
    async attendance(yearId, format = 'csv') {
        const r = await this.api('export', 'attendance', {
            yearId, format
        });
        if (r.ok && r.url) {
            window.open(r.url, '_blank');
        }
    }
};
```

### Modify: `views/module_reports.php`
Add export buttons:
```html
<div class="flex gap-2 mb-4">
    <button @click="exportReport('pdf')" class="btn-secondary">
        <i data-lucide="file-text"></i> PDF
    </button>
    <button @click="exportReport('excel')" class="btn-secondary">
        <i data-lucide="table"></i> Excel
    </button>
    <button @click="exportReport('csv')" class="btn-secondary">
        <i data-lucide="file-spreadsheet"></i> CSV
    </button>
</div>
```

## Requirements
1. Export student report cards to PDF
2. Export attendance sheets to CSV/Excel
3. Export score lists to CSV/Excel
4. Download triggered from browser (no server-side file storage)

## Acceptance Criteria
1. PDF export works for report cards
2. CSV export works for attendance
3. Excel export works for scores
4. Downloads happen in browser

## Implementation Plan

### Step 1: Create export.php API endpoint
- Handle `report` action - export individual report cards as PDF data URL
- Handle `attendance` action - export attendance sheet as CSV/Excel
- Handle `scores` action - export score list as CSV/Excel
- Return data URLs (base64 encoded) for browser download

### Step 2: Create export.js JavaScript module
- Register in window.TNTT.export namespace
- Methods: report(), attendance(), scores()
- Call API and trigger browser download via data URL

### Step 3: Modify module_reports.php
- Add export buttons in the navigation bar
- Wire up exportReport(format) method to call export.js
