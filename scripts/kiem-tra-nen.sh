#!/usr/bin/env bash
# ============================================================
#  KIỂM TRA NÉN TRÊN HOST THẬT (AZDIGI/LiteSpeed)
#
#  Chạy SAU KHI deploy, từ máy bạn (hoặc bất cứ máy nào ra Internet):
#      bash scripts/kiem-tra-nen.sh https://ten-mien-cua-ban.vn
#
#  Chỉ đụng tệp TĨNH CÔNG KHAI (bundle.php, favicon) — KHÔNG cần đăng nhập,
#  KHÔNG chạm dữ liệu thiếu nhi. An toàn với site production.
#
#  Đạt khi:
#    - bundle.php (JS lẫn CSS) trả Content-Encoding: gzip (hoặc br)
#    - Giải nén MỘT lớp ra đúng JS/CSS (không phải vẫn còn nén -> nén chồng)
#    - Kích thước nén nhỏ hơn hẳn bản thô
# ============================================================
# KHÔNG dùng `set -e`: script chẩn đoán, grep không khớp (vd thiếu header) trả 1
# là chuyện thường, không được để nó thoát giữa chừng.
set -u

BASE="${1:-}"
if [ -z "$BASE" ]; then
  echo "Dùng: bash scripts/kiem-tra-nen.sh https://ten-mien.vn"; exit 1
fi
BASE="${BASE%/}"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
ok=0; fail=0

kiem_tra() {
  local ten="$1" url="$2"
  echo "──────────── $ten : $url"

  # 1) Header nén + server
  curl -s -H "Accept-Encoding: gzip, br" -D "$TMP/h" -o /dev/null "$url"
  local enc srv; enc="$(grep -i '^content-encoding:' "$TMP/h" | tr -d '\r' | awk '{print $2}')"
  srv="$(grep -i '^server:' "$TMP/h" | tr -d '\r' | cut -d' ' -f2-)"
  echo "   Server         : ${srv:-?}"
  echo "   Content-Encoding: ${enc:-<KHÔNG NÉN>}"

  # 2) Kích thước: nén vs thô
  local sz_nen sz_tho
  sz_nen="$(curl -s -H 'Accept-Encoding: gzip' "$url" -o /dev/null -w '%{size_download}')"
  sz_tho="$(curl -s "$url" -o /dev/null -w '%{size_download}')"
  echo "   Cỡ nén / thô   : ${sz_nen} / ${sz_tho} bytes"

  # 3) Giải nén MỘT lớp (curl --compressed) -> phải ra văn bản, KHÔNG còn nén
  curl -s --compressed "$url" -o "$TMP/body" -H "Accept-Encoding: gzip, br"
  local magic; magic="$(head -c2 "$TMP/body" | xxd -p 2>/dev/null || echo '')"
  if [ "$magic" = "1f8b" ]; then
    echo "   ✗ NÉN CHỒNG: giải một lớp vẫn còn gzip (1f8b). Xem lại zlib.output_compression."
    fail=$((fail+1)); return
  fi

  if [ -n "$enc" ] && [ "${sz_nen:-0}" -lt "${sz_tho:-1}" ]; then
    echo "   ✓ ĐẠT (nén 1 lớp, cỡ giảm)"
    ok=$((ok+1))
  else
    echo "   ✗ CHƯA NÉN (Content-Encoding trống hoặc cỡ không giảm)."
    echo "     -> Kiểm bundle.min.js đã lên host chưa; PHP có zlib không."
    fail=$((fail+1))
  fi
}

echo "== Kiểm tra nén tĩnh trên: $BASE =="
kiem_tra "JS bundle " "$BASE/assets/js/bundle.php"
kiem_tra "CSS bundle" "$BASE/assets/css/bundle.php"

echo "════════════════════════════════════"
echo "Kết quả: ĐẠT=$ok  LỖI=$fail"
[ "$fail" -eq 0 ] && echo "→ Host nén tĩnh OK." || echo "→ Còn mục cần xử lý ở trên."
exit "$fail"
