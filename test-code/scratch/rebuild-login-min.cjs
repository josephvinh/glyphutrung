/* ==========================================================
   Rebuild CHỈ login.min.js — dùng đúng đoạn mã trong build/minify.cjs
   (dòng 66-69) để bản production khớp với login.js vừa sửa.

   Cố ý KHÔNG chạy `npm run build` đầy đủ: lệnh đó còn ghi đè
   bundle.min.js + bundle.min.css, mà hai tệp ấy không có gì thay đổi —
   chỉ cần upload 1 tệp login.min.js lên host.

     node scratch/rebuild-login-min.cjs
   ========================================================== */
const esbuild = require('esbuild');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const jsBase = path.join(ROOT, 'public/assets/js');
const read = (f) => (fs.existsSync(f) ? fs.readFileSync(f, 'utf8') : '');
const kb = (n) => Math.round(n / 1024) + 'KB';

(async () => {
    // Y hệt minify.cjs: passkey.js (window.Passkey) + login.js (loginScreen)
    const loginFiles = [path.join(jsBase, 'modules/passkey.js'), path.join(jsBase, 'login.js')];
    const loginRaw = loginFiles.map(read).join('\n;\n');

    const out = path.join(jsBase, 'login.min.js');
    const before = fs.existsSync(out) ? fs.readFileSync(out, 'utf8') : '';

    const loginMin = await esbuild.transform(loginRaw, { loader: 'js', minify: true, legalComments: 'none' });
    fs.writeFileSync(out, loginMin.code);

    console.log('LOGIN:', kb(loginRaw.length), '->', kb(loginMin.code.length));
    console.log('changed:', before !== loginMin.code);
    console.log('contains resetRegisterForm:', loginMin.code.includes('resetRegisterForm'));
    console.log('bundle.min.js untouched:', fs.statSync(path.join(jsBase, 'bundle.min.js')).mtime.toISOString());
})();
