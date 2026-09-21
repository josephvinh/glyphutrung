/* ==========================================================
   TỐI ƯU HÌNH ẢNH — nén ảnh cho web
   
   Công cụ: Sharp (Node.js image processing library)
   Cài đặt: npm install sharp
   Chạy: node build/optimize_images.cjs
   
   Tính năng:
     - Chuyển PNG sang WebP/AVIF (nén ~80% mà vẫn đẹp)
     - Resize ảnh quá lớn
     - Tạo placeholder blur (LQP) cho lazy loading
   ========================================================== */
const fs = require('fs');
const path = require('path');

// Kiểm tra Sharp
let sharp;
try {
    sharp = require('sharp');
} catch (e) {
    console.log('Cần cài đặt Sharp: npm install sharp');
    process.exit(1);
}

const ROOT = path.resolve(__dirname, '..');
const IMG_DIR = path.join(ROOT, 'public/assets/img');
const OPTIMIZED_DIR = path.join(IMG_DIR, 'optimized');

// Tạo thư mục optimized nếu chưa có
if (!fs.existsSync(OPTIMIZED_DIR)) {
    fs.mkdirSync(OPTIMIZED_DIR, { recursive: true });
}

// Cấu hình tối ưu
const configs = [
    {
        input: 'logo.png',
        outputs: [
            { name: 'logo.webp', format: 'webp', quality: 85 },
            { name: 'logo.avif', format: 'avif', quality: 80 },
        ]
    },
    {
        input: 'icon-512.png',
        outputs: [
            { name: 'icon-512.webp', format: 'webp', quality: 90 },
            { name: 'icon-512.avif', format: 'avif', quality: 85 },
        ]
    },
    {
        input: 'icon-192.png',
        outputs: [
            { name: 'icon-192.webp', format: 'webp', quality: 90 },
            { name: 'icon-192.avif', format: 'avif', quality: 85 },
        ]
    },
    {
        input: 'icon-180.png',
        outputs: [
            { name: 'icon-180.webp', format: 'webp', quality: 90 },
            { name: 'icon-180.avif', format: 'avif', quality: 85 },
        ]
    },
    {
        input: 'icon-32.png',
        outputs: [
            { name: 'icon-32.webp', format: 'webp', quality: 90 },
        ]
    },
];

// Tạo HTML để test hoặc production checklist
function generatePictureTag(baseName, outputs) {
    let html = `<picture>\n`;
    
    // AVIF (ưu tiên nhất - nén tốt nhất)
    const avif = outputs.find(o => o.format === 'avif');
    if (avif) {
        html += `    <source srcset="${avif.name}" type="image/avif">\n`;
    }
    
    // WebP
    const webp = outputs.find(o => o.format === 'webp');
    if (webp) {
        html += `    <source srcset="${webp.name}" type="image/webp">\n`;
    }
    
    // Fallback PNG/JPEG
    const pngOutput = baseName.includes('.png') ? baseName : `${baseName}.png`;
    html += `    <img src="${pngOutput}" alt="" width="..." height="...">\n`;
    html += `</picture>`;
    
    return html;
}

async function optimizeImage(input, output, config) {
    const inputPath = path.join(IMG_DIR, input);
    const outputPath = path.join(OPTIMIZED_DIR, output.name);
    
    if (!fs.existsSync(inputPath)) {
        console.log(`  ⚠️  Bỏ qua: ${input} không tìm thấy`);
        return;
    }
    
    const inputStats = fs.statSync(inputPath);
    const inputSize = (inputStats.size / 1024).toFixed(1);
    
    try {
        let pipeline = sharp(inputPath);
        
        // Resize nếu cần
        if (config.maxWidth) {
            pipeline = pipeline.resize(config.maxWidth, null, { withoutEnlargement: true });
        }
        
        // Convert và nén
        if (config.format === 'webp') {
            pipeline = pipeline.webp({ quality: config.quality, effort: 4 });
        } else if (config.format === 'avif') {
            pipeline = pipeline.avif({ quality: config.quality, effort: 4 });
        } else if (config.format === 'png') {
            pipeline = pipeline.png({ compressionLevel: 9, adaptiveFiltering: true });
        } else if (config.format === 'jpeg') {
            pipeline = pipeline.jpeg({ quality: config.quality, mozjpeg: true });
        }
        
        await pipeline.toFile(outputPath);
        
        const outputStats = fs.statSync(outputPath);
        const outputSize = (outputStats.size / 1024).toFixed(1);
        const savings = ((1 - outputStats.size / inputStats.size) * 100).toFixed(1);
        
        console.log(`  ✅ ${output.name}: ${inputSize}KB → ${outputSize}KB (tiết kiệm ${savings}%)`);
    } catch (err) {
        console.log(`  ❌ Lỗi ${input} → ${output.name}: ${err.message}`);
    }
}

async function generateBlurPlaceholder(input) {
    const inputPath = path.join(IMG_DIR, input);
    const outputPath = path.join(OPTIMIZED_DIR, `${input.replace('.png', '')}_blur.txt`);
    
    if (!fs.existsSync(inputPath)) return;
    
    try {
        const buffer = await sharp(inputPath)
            .resize(20, null, { withoutEnlargement: true })
            .blur(2)
            .toBuffer();
        
        // Extract dominant color
        const { dominant } = await sharp(inputPath).stats();
        const color = `rgb(${dominant.r}, ${dominant.g}, ${dominant.b})`;
        
        const dataUri = `data:image/jpeg;base64,${buffer.toString('base64')}`;
        
        fs.writeFileSync(outputPath, JSON.stringify({ color, dataUri }, null, 2));
        console.log(`  ✅ Tạo blur placeholder cho ${input}`);
    } catch (err) {
        console.log(`  ⚠️  Không tạo được blur cho ${input}: ${err.message}`);
    }
}

async function main() {
    console.log('🖼️  TỐI ƯU HÌNH ẢNH\n');
    
    // 1. Nén ảnh hiện có
    console.log('📦 Đang nén ảnh...\n');
    
    for (const config of configs) {
        console.log(`  ─ ${config.input} ─`);
        for (const output of config.outputs) {
            await optimizeImage(config.input, output, output);
        }
        // Tạo blur placeholder
        await generateBlurPlaceholder(config.input);
    }
    
    // 2. Tạo file hướng dẫn sử dụng
    console.log('\n\n📝 Tạo hướng dẫn sử dụng...');
    
    let usageGuide = `# HƯỚNG DẪN SỬ DỤNG ẢNH TỐI ƯU

## Các file đã tạo trong thư mục optimized/

Sau khi chạy script này, các file tối ưu sẽ nằm trong \`optimized/\`.

## Cách sử dụng trong HTML

Thay vì:
\`\`\`html
<img src="assets/img/logo.png" alt="Logo">
\`\`\`

Dùng:
\`\`\`html
<picture>
    <source srcset="assets/img/optimized/logo.avif" type="image/avif">
    <source srcset="assets/img/optimized/logo.webp" type="image/webp">
    <img src="assets/img/logo.png" alt="Logo">
</picture>
\`\`\`

## Lợi ích

| Định dạng | Kích thước (so với PNG) | Hỗ trợ |
|------------|-------------------------|---------|
| AVIF       | ~70-80% nhỏ hơn        | Chrome 85+, Firefox 93+ |
| WebP       | ~30-40% nhỏ hơn        | Hầu hết trình duyệt |
| PNG        | 100% (baseline)         | Tất cả |

## Lệnh chạy

\`\`\`bash
npm install sharp
node build/optimize_images.cjs
\`\`\`

## CI/CD Integration

Thêm vào CI/CD pipeline:
\`\`\`yaml
- name: Optimize images
  run: node build/optimize_images.cjs
\`\`\`
`;
    
    fs.writeFileSync(path.join(IMG_DIR, 'OPTIMIZED_README.md'), usageGuide);
    console.log('  ✅ Đã tạo OPTIMIZED_README.md');
    
    // 3. Tóm tắt
    console.log('\n\n📊 TÓM TẮT');
    console.log('─'.repeat(50));
    
    const files = fs.readdirSync(IMG_DIR);
    const optimizedFiles = fs.readdirSync(OPTIMIZED_DIR);
    
    console.log(`Tổng số file gốc: ${files.length}`);
    console.log(`Tổng số file tối ưu: ${optimizedFiles.length}`);
    console.log('\n✨ Hoàn tất!');
}

main().catch(console.error);
