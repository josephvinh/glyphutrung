/**
 * CQ-1: Kiểm tra trùng tên thuộc tính/hàm trong các module JS
 *
 * Các module gộp bằng Object.defineProperties - trùng tên sẽ bị đè âm thầm.
 * Chỉ kiểm tra các export thực sự: object literals được gán cho window.TNTT
 *
 * Chạy: node tests/e2e/check_module_merge.js
 *
 * Exit code: 0 = pass, 1 = có trùng tên
 */

const fs = require('fs');
const path = require('path');

const MODULE_DIR = path.join(__dirname, '../..', 'public/assets/js/modules');

// Các tên được phép trùng (global/shared utilities)
const ALLOWED_DUPLICATES = new Set([
    'init',        // init() phổ biến, gọi tường minh
    'destroy',     // destroy() phổ biến
    'toast',       // TNTT.toast là global
    'TNTT',        // Global object
]);

/**
 * Trích xuất các key trong object literal được gán cho window.TNTT
 * Ví dụ: window.TNTT.moduleName = { key1: value1, key2: value2 }
 */
function extractTNTTExports(content) {
    const names = new Set();

    // Tìm: window.TNTT.xxx = { ... }
    const exportPattern = /window\.TNTT\.\w+\s*=\s*\{([^}]*)\}/g;
    let match;
    while ((match = exportPattern.exec(content)) !== null) {
        const objContent = match[1];
        // Trích key từ object: key: value hoặc 'key': value hoặc "key": value
        const keyPattern = /(?:['"]?(\w+)['"]?\s*:|(\w+)\s*:)/g;
        let keyMatch;
        while ((keyMatch = keyPattern.exec(objContent)) !== null) {
            const key = keyMatch[1] || keyMatch[2];
            if (key) names.add(key);
        }
    }

    return names;
}

function main() {
    const files = fs.readdirSync(MODULE_DIR).filter(f => f.endsWith('.js'));
    const allExports = new Map(); // name -> [files]

    for (const file of files) {
        const filePath = path.join(MODULE_DIR, file);
        const content = fs.readFileSync(filePath, 'utf8');
        const exports = extractTNTTExports(content);

        for (const name of exports) {
            if (!allExports.has(name)) {
                allExports.set(name, []);
            }
            allExports.get(name).push(file);
        }
    }

    // Lọc ra các trùng tên thực sự (nhiều hơn 1 file, không phải allowed)
    const conflicts = [];
    for (const [name, fileList] of allExports) {
        if (fileList.length > 1 && !ALLOWED_DUPLICATES.has(name)) {
            conflicts.push({ name, files: fileList });
        }
    }

    if (conflicts.length === 0) {
        console.log('✅ Không có trùng tên module JS');
        console.log(`   Đã kiểm tra ${files.length} files, ${allExports.size} exports`);
        process.exit(0);
    }

    console.log('❌ Phát hiện trùng tên module JS:');
    for (const { name, files } of conflicts.sort((a, b) => a.name.localeCompare(b.name))) {
        console.log(`   "${name}" xuất hiện trong: ${files.join(', ')}`);
    }
    console.log(`\nTổng: ${conflicts.length} trùng lặp`);
    console.log('Cách sửa: Đổi tên trong module để tránh đè nhau khi gộp.');
    process.exit(1);
}

main();
