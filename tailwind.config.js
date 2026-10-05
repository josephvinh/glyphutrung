/** @type {import('tailwindcss').Config} */
const fs = require('fs');
const path = require('path');

function collectFiles(dir, ext) {
    const results = [];
    function walk(d) {
        if (!fs.existsSync(d)) return;
        for (const f of fs.readdirSync(d)) {
            const full = path.join(d, f);
            const stat = fs.statSync(full);
            if (stat.isDirectory()) {
                walk(full);
            } else if (f.endsWith(ext)) {
                results.push(full);
            }
        }
    }
    walk(dir);
    return results;
}

const viewsDir = path.join(__dirname, 'views');
const views = collectFiles(viewsDir, '.php');
const jsDir = path.join(__dirname, 'public', 'assets', 'js');
const jsFiles = collectFiles(jsDir, '.js');
const publicIndex = path.join(__dirname, 'public', 'index.php');

module.exports = {
  content: {
    files: [...views, ...jsFiles, publicIndex],
  },
  theme: {
    extend: {
      fontSize: {
        micro: ['11px', '1.35'],
      },
      // Token bo góc cho bottom-sheet/modal — khớp --r-sheet trong app.css.
      // Các view dùng rounded-sheet / rounded-t-sheet (popup), cần khai ở đây
      // thì Tailwind mới sinh ra lớp tương ứng.
      borderRadius: {
        sheet: '1.25rem',
      },
    },
  },
  plugins: [],
}
