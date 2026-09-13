/* ==========================================================
   MINIFY — nén JS + CSS cho bản PRODUCTION.

     node build/minify.cjs

   Sinh ra:
     public/assets/js/bundle.min.js
     public/assets/css/bundle.min.css

   bundle.php sẽ phục vụ các tệp .min này NẾU chúng mới hơn mọi tệp nguồn;
   nếu cũ hơn (quên build sau khi sửa code) thì tự quay về nối thô -> không
   bao giờ phục vụ bản cũ/hỏng.

   CẦN chạy lại mỗi khi sửa JS/CSS rồi commit tệp .min. Cần esbuild:
     npm install
   ========================================================== */
const esbuild = require('esbuild');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const ROOT = path.resolve(__dirname, '..');
const PHP = process.env.PHP_BIN
    || (fs.existsSync('G:/xampp/php/php.exe') ? 'G:/xampp/php/php.exe' : 'php');

// Nguồn danh sách file = asset_manifest.php (một nguồn duy nhất, khớp bundle.php)
const manifestPath = path.join(ROOT, 'public/assets/asset_manifest.php').replace(/\\/g, '/');
let manifest;
try {
    const json = execSync(`"${PHP}" -r "echo json_encode(require '${manifestPath}');"`, { encoding: 'utf8' });
    manifest = JSON.parse(json);
} catch (e) {
    console.error('Không đọc được asset_manifest qua PHP. Đặt biến PHP_BIN trỏ tới php.', e.message);
    process.exit(1);
}

const jsBase  = path.join(ROOT, 'public/assets/js');
const cssBase = path.join(ROOT, 'public/assets/css');

// Thứ tự PHẢI khớp bundle.php: toast -> modules (theo manifest) -> app.js
const jsFiles = [
    path.join(jsBase, 'modules/toast.js'),
    ...manifest.js_modules.map(m => path.join(jsBase, 'modules', m + '.js')),
    path.join(jsBase, 'app.js'),
];
const cssFiles = manifest.css.map(c => path.join(cssBase, c + '.css'));

const read = f => fs.existsSync(f) ? fs.readFileSync(f, 'utf8') : '';
const kb   = n => Math.round(n / 1024) + 'KB';

(async () => {
    // JS: nối bằng ";\n" như bundle.php (phòng thiếu ; cuối tệp) rồi nén.
    const jsRaw = jsFiles.map(read).join('\n;\n');
    const jsMin = await esbuild.transform(jsRaw, { loader: 'js', minify: true, legalComments: 'none' });
    fs.writeFileSync(path.join(jsBase, 'bundle.min.js'), jsMin.code);
    console.log('JS  :', kb(jsRaw.length), '->', kb(jsMin.code.length));

    // CSS
    const cssRaw = cssFiles.map(read).join('\n');
    const cssMin = await esbuild.transform(cssRaw, { loader: 'css', minify: true, legalComments: 'none' });
    fs.writeFileSync(path.join(cssBase, 'bundle.min.css'), cssMin.code);
    console.log('CSS :', kb(cssRaw.length), '->', kb(cssMin.code.length));

    // BUNDLE MÀN ĐĂNG NHẬP: passkey (window.Passkey) + login (component
    // loginScreen). Trang đăng nhập là trang riêng, chỉ cần 2 mảnh này.
    const loginFiles = [ path.join(jsBase, 'modules/passkey.js'), path.join(jsBase, 'login.js') ];
    const loginRaw = loginFiles.map(read).join('\n;\n');
    const loginMin = await esbuild.transform(loginRaw, { loader: 'js', minify: true, legalComments: 'none' });
    fs.writeFileSync(path.join(jsBase, 'login.min.js'), loginMin.code);
    console.log('LOGIN:', kb(loginRaw.length), '->', kb(loginMin.code.length));

    console.log('✓ Xong. Nhớ commit bundle.min.js + bundle.min.css.');
})();
