#!/usr/bin/env bash
# ============================================================
#  Đồng bộ code -> thư mục APP trên máy chủ (dùng chung cho
#  .cpanel.yml và GitHub Actions). Chạy TỪ trong thư mục repo clone.
#
#  GIỮ NGUYÊN các thứ riêng của máy chủ (không đè khi deploy):
#    - config/config.php        (mặc định trên git — không đè)
#    - config/config.local.php  (DB thật + secret của máy chủ — KHÔNG lên git)
#    - config/backup/           (sao lưu, có tên thật)
#
#  Không tự chạy migration (việc một lần) — chạy tay khi cần.
# ============================================================
set -euo pipefail

DEPLOYPATH="${DEPLOYPATH:-$HOME/tntt-app}"
mkdir -p "$DEPLOYPATH"

rsync -a --delete \
  --exclude='.git' \
  --exclude='.github' \
  --exclude='.claude' \
  --exclude='.superpowers' \
  --exclude='docs' \
  --exclude='tests' \
  --exclude='scratch' \
  --exclude='REVIEW_RESULTS' \
  --exclude='build' \
  --exclude='node_modules' \
  --exclude='phpunit10.phar' \
  --exclude='phpunit.xml' \
  --exclude='package.json' \
  --exclude='package-lock.json' \
  --exclude='tsconfig.json' \
  --exclude='CLAUDE.md' \
  --exclude='CHANGES_SUMMARY.md' \
  --exclude='config/config.php' \
  --exclude='config/config.local.php' \
  --exclude='config/backup' \
  --exclude='"NGOC VINH"' \
  --exclude='"[working-dir] NGOC VINH"' \
  --exclude='Enter' \
  ./ "$DEPLOYPATH/"

echo "Deploy xong -> $DEPLOYPATH (docroot phải trỏ vào $DEPLOYPATH/public)"
