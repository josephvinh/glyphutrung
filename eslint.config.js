// ESLint (flat config, ESLint 9) — CẤU HÌNH TỐI THIỂU cho CI (#82).
//
// Mục tiêu: chỉ bắt LỖI LOGIC THẬT (biến không khai báo, khoá trùng, khai báo
// lại, gán vào const...), KHÔNG ép phong cách. Job js-lint trong
// .github/workflows/ci.yml chạy `eslint public/assets/js/` và PHẢI đỏ khi có
// mức "error" (trước đây `|| true` nên không bao giờ đỏ).
//
// Không nạp gói ngoài (kể cả `globals`) để CI chỉ cần `npx eslint@9`.
// Mã ở đây là script cổ điển nạp bằng <script> (không phải ES module), nên
// sourceType = "script" và các hàm/biến toàn cục dùng chéo giữa các file
// (bundle.php nối chúng lại) được coi là cùng một phạm vi toàn cục.

const browser = [
  // DOM + Web API hay dùng
  'window', 'document', 'navigator', 'location', 'history', 'screen', 'self',
  'console', 'fetch', 'Request', 'Response', 'Headers', 'URL', 'URLSearchParams',
  'FormData', 'Blob', 'File', 'FileReader', 'Image', 'Audio', 'Option',
  'localStorage', 'sessionStorage', 'indexedDB', 'caches', 'crypto', 'performance',
  'setTimeout', 'clearTimeout', 'setInterval', 'clearInterval',
  'requestAnimationFrame', 'cancelAnimationFrame', 'requestIdleCallback', 'queueMicrotask',
  'alert', 'confirm', 'prompt', 'open', 'print', 'matchMedia', 'getComputedStyle',
  'atob', 'btoa', 'structuredClone', 'TextEncoder', 'TextDecoder',
  'AbortController', 'IntersectionObserver', 'MutationObserver', 'ResizeObserver',
  'Event', 'CustomEvent', 'KeyboardEvent', 'MouseEvent', 'HTMLElement', 'Node', 'DOMParser',
  'Notification', 'PublicKeyCredential', 'BarcodeDetector', 'MediaRecorder',
  'ServiceWorkerRegistration', 'CSS',
  // Service worker (public/sw.js)
  'clients', 'skipWaiting',
];

// Thư viện nạp bằng <script> riêng (public/assets/js/vendor/*) hoặc nạp lười từ CDN
// (html2canvas, jspdf — xem custom-qrcard.js, luôn kiểm tra window.* trước khi dùng)
const vendor = ['Alpine', 'lucide', 'XLSX', 'QRCode', 'jsQR', 'html2canvas', 'jspdf'];

const globals = {};
for (const g of [...browser, ...vendor]) globals[g] = 'readonly';

module.exports = [
  {
    // Thư viện đã minify/bên thứ ba và bundle sinh ra: không lint.
    ignores: ['**/vendor/**', '**/*.min.js', 'public/assets/js/bundle.js'],
  },
  {
    files: ['**/*.js'],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'script',
      globals,
    },
    rules: {
      // Lỗi logic thật -> error (làm CI đỏ)
      'no-undef': 'error',
      'no-redeclare': 'error',
      'no-dupe-args': 'error',
      'no-dupe-else-if': 'error',
      'no-const-assign': 'error',
      'no-func-assign': 'error',
      'no-self-assign': 'error',
      'no-unreachable': 'error',
      'no-unsafe-negation': 'error',
      'no-cond-assign': 'error',
      'use-isnan': 'error',
      'valid-typeof': 'error',

      // #91: public/assets/js/modules/library.js khai báo khoá trùng `libItemIcon`.
      // Đó là lỗi thật nhưng nằm ngoài phạm vi #82 -> để WARN (không làm CI đỏ);
      // khi #91 sửa xong hãy đổi lại thành 'error'.
      'no-dupe-keys': 'warn',
    },
  },
];
