/**
 * CQ-1: Kiểm tra trùng tên thuộc tính/hàm trong các module JS
 *
 * Các module gộp bằng Object.defineProperties - trùng tên sẽ bị đè âm thầm.
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

function extractExports(filePath) {
    const content = fs.readFileSync(filePath, 'utf8');
    const names = new Set();

    // Tìm function declarations: function name() {}
    const funcMatches = content.matchAll(/\bfunction\s+(\w+)\s*\(/g);
    for (const m of funcMatches) {
        names.add(m[1]);
    }

    // Tìm arrow functions gán: const name = () => {} hoặc name: function() {}
    const constMatches = content.matchAll(/\bconst\s+(\w+)\s*=/g);
    for (const m of constMatches) {
        names.add(m[1]);
    }

    // Tìm object properties: name: value hoặc 'name': value
    const propMatches = content.matchAll(/\b(\w+)\s*:/g);
    for (const m of propMatches) {
        // Loại trừ labels, CSS properties
        const line = content.substring(0, m.index).split('\n').pop();
        if (!line.includes('case ') && !line.includes('default:')) {
            names.add(m[1]);
        }
    }

    return names;
}

function main() {
    const files = fs.readdirSync(MODULE_DIR).filter(f => f.endsWith('.js'));
    const allExports = new Map(); // name -> [files]

    for (const file of files) {
        const filePath = path.join(MODULE_DIR, file);
        const exports = extractExports(filePath);

        for (const name of exports) {
            if (!allExports.has(name)) {
                allExports.set(name, []);
            }
            allExports.get(name).push(file);
        }
    }

    // Lọc ra các trùng tên thực sự (nhiều hơn 1 file, không phải allowed)
    const conflicts = [];
    for (const [name, files] of allExports) {
        if (files.length > 1 && !ALLOWED_DUPLICATES.has(name)) {
            conflicts.push({ name, files });
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
