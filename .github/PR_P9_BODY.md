## Nội dung

### Phạm vi sửa đổi (#124)

1. **Ghim ESLint**: Thêm `eslint@^9.15.0` vào `devDependencies` để bảo đảm phiên bản nhất quán
2. **ESLint config**: Tạo `eslint.config.js` cho ES2020 JS với globals phù hợp
3. **CI - npm ci**: Dùng `npm ci` thay `npm install` để cài đặc biệt deterministic
4. **CI - npm cache**: Bật caching cho Node.js dependencies
5. **CI - unmanaged tests**: Kiểm tra và báo lỗi nếu có file `*Test.php` ngoài `tests/unit/`
6. **CI - inline JS warning**: Log cảnh báo nếu có inline JS trong PHP (để developer biết)

### Chưa làm (cần thiết bị/quyền)
- Branch protection (cần admin repo)
- Chạy e2e tests trong CI (cần thiết bị thật)
- Inline JS linting (cần extract ra file riêng)

🤖 Generated with [Claude Code](https://claude.com/claude-code)
