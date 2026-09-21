# HƯỚNG DẪN SỬ DỤNG ẢNH TỐI ƯU

## Các file đã tạo trong thư mục optimized/

Sau khi chạy script này, các file tối ưu sẽ nằm trong `optimized/`.

## Cách sử dụng trong HTML

Thay vì:
```html
<img src="assets/img/logo.png" alt="Logo">
```

Dùng:
```html
<picture>
    <source srcset="assets/img/optimized/logo.avif" type="image/avif">
    <source srcset="assets/img/optimized/logo.webp" type="image/webp">
    <img src="assets/img/logo.png" alt="Logo">
</picture>
```

## Lợi ích

| Định dạng | Kích thước (so với PNG) | Hỗ trợ |
|------------|-------------------------|---------|
| AVIF       | ~70-80% nhỏ hơn        | Chrome 85+, Firefox 93+ |
| WebP       | ~30-40% nhỏ hơn        | Hầu hết trình duyệt |
| PNG        | 100% (baseline)         | Tất cả |

## Lệnh chạy

```bash
npm install sharp
node build/optimize_images.cjs
```

## CI/CD Integration

Thêm vào CI/CD pipeline:
```yaml
- name: Optimize images
  run: node build/optimize_images.cjs
```
