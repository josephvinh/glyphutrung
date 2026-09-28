#!/bin/bash
# ============================================================================
# capnhat.sh — CẬP NHẬT website từ GitHub một cách AN TOÀN.
#
#   Cách dùng trên máy chủ (cPanel Terminal):
#       bash ~/tntt/capnhat.sh
#   Hoặc tạo lệnh tắt một lần (xem README ở cuối file), rồi chỉ cần gõ:
#       capnhat
#
# ĐẶC ĐIỂM AN TOÀN:
#   - CHỈ TẢI XUỐNG code mới từ GitHub — KHÔNG bao giờ đẩy (push) gì lên.
#   - KHÔNG đụng tới config/config.local.php (DB + khoá thật của máy chủ).
#   - KHÔNG xoá file người dùng đã upload (storage, cache…): chỉ đồng bộ
#     đúng các file mã nguồn mà git quản lý, phần còn lại giữ nguyên.
#   - Tự xử lý lỗi "Your local changes would be overwritten by merge".
# ============================================================================

set -u

# Về đúng thư mục chứa script (gốc dự án), dù gọi từ đâu.
cd "$(dirname "$0")" || { echo "❌ Không tìm thấy thư mục dự án."; exit 1; }

# Phải là một repo git.
if [ ! -d .git ]; then
    echo "❌ Thư mục này không phải dự án git. Hãy chạy trong ~/tntt"
    exit 1
fi

# Nhánh chính trên GitHub.
BRANCH="master"

echo "→ Đang tải bản mới nhất từ GitHub…"
if ! git fetch origin "$BRANCH"; then
    echo "❌ Không tải được từ GitHub (kiểm tra mạng). Thử lại sau."
    exit 1
fi

echo "→ Đồng bộ code về đúng bản trên GitHub (ghi đè mọi sửa tay trên máy chủ)…"
# reset --hard chỉ tác động tới các file git QUẢN LÝ. File bị .gitignore
# (như config.local.php) và file không nằm trong git được GIỮ NGUYÊN.
git reset --hard "origin/$BRANCH"

echo ""
echo "✅ XONG! Website đã cập nhật tới:"
git log --oneline -1
echo ""
echo "   (config.local.php và dữ liệu người dùng KHÔNG bị đụng tới.)"

# ============================================================================
# TẠO LỆNH TẮT "capnhat" (chỉ làm MỘT LẦN):
#
#   echo "alias capnhat='bash ~/tntt/capnhat.sh'" >> ~/.bashrc && source ~/.bashrc
#
# Từ đó về sau, mỗi lần muốn cập nhật website chỉ cần gõ:
#
#   capnhat
# ============================================================================
