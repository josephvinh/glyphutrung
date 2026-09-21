// Gọi trực tiếp gateway Key4U tới model vision thật để đọc ảnh chụp màn hình.
// Lý do: model vision khai báo trong settings.yaml không tồn tại trên gateway,
// nên công cụ read_image của harness bị từ chối.
import { readFileSync, writeFileSync, existsSync } from 'node:fs'
import { join } from 'node:path'

const KEY = process.env.KEY4U_KEY
const BASE = 'https://api.key4u.vn/v1'
const MODEL = process.env.VISION_MODEL || 'gemini-2.5-flash'
const SHOTS = 'G:\\xampp\\htdocs\\tntt\\scratch\\review\\shots'
const OUT = process.env.OUT_FILE || 'G:\\xampp\\htdocs\\tntt\\scratch\\review\\vision-results.json'

const SYSTEM = `Bạn là chuyên gia QA giao diện (UI/UX) đang soi ảnh chụp màn hình của một web app
quản lý đoàn Thiếu Nhi Thánh Thể tên "GIA ĐÌNH GIÁO LÝ PHÚ TRUNG".

NGUYÊN TẮC: chỉ báo cáo những gì THỰC SỰ NHÌN THẤY trong ảnh. Tuyệt đối không suy đoán, không bịa,
không bổ sung thứ không có trong ảnh. Nếu ảnh trống/trắng/lỗi/không đọc được, phải nói rõ.

Hãy quan sát KỸ và ghi lại:
1. Bố cục tổng thể: có sidebar không, thanh trên, vùng nội dung chính, các thẻ/card, bảng, biểu đồ.
2. Toàn bộ chữ đọc được: tiêu đề trang, nhãn menu, nhãn nút, tiêu đề cột bảng, các con số thống kê.
3. Lỗi giao diện (UI) — tìm và chỉ rõ VỊ TRÍ:
   - phần tử lệch hàng, đè lên nhau, chồng chéo
   - nội dung tràn ra ngoài màn hình hoặc bị cắt cụt (chữ, nút, bảng)
   - chữ bị cắt mất chữ, xuống dòng lỗi, chồng lên viền
   - ảnh/avatar/biểu tượng bị vỡ, méo, hiển thị sai hoặc ô trống hình vuông
   - màu sắc bất thường, chữ quá nhạt/khó đọc trên nền (tương phản kém)
   - khoảng trống lạ, khối rỗng, viền khung không khớp
4. Vấn đề trải nghiệm (UX):
   - vùng nội dung trống hoặc bảng không có dữ liệu nhưng vẫn hiện khung
   - thông tin bị lặp lại
   - bố cục chật/khó nhìn, mật độ chữ dày
   - nút hoặc hành động mơ hồ, khó hiểu
   - nếu là bản mobile (rộng ~390px): kiểm tra tràn ngang, chữ quá nhỏ, phần tử chồng nhau,
     menu bị che, bảng bị bóp méo

Chỉ liệt kê vấn đề bạn CHẮC CHẮN thấy. Nếu không thấy vấn đề nào, để mảng "van_de" rỗng và
đặt "khong_thay_van_de": true. Đừng bịa ra vấn đề cho đủ.

Trả về DUY NHẤT một khối JSON hợp lệ, không thêm chữ nào ngoài JSON:
{
  "doc_duoc_anh": true,
  "kich_thuoc_uoc_luong": "desktop hay mobile",
  "mo_ta_tong_quan": "3-5 câu mô tả bố cục và nội dung chính",
  "chu_de_trang": "tiêu đề trang đọc được, hoặc rỗng",
  "thanh_menu": ["các mục menu đọc được"],
  "cac_nut_hanh_dong": ["các nhãn nút đọc được"],
  "so_lieu_thong_ke": ["các con số thống kê kèm nhãn, ví dụ '128 thiếu nhi'"],
  "van_de": [
    {"loai": "UI" hoặc "UX",
     "mo_ta": "mô tả cụ thể vấn đề",
     "vi_tri": "vị trí trên màn hình, ví dụ 'sidebar trái, mục cuối'",
     "muc_do": "cao" hoặc "trung binh" hoặc "thap"}
  ],
  "khong_thay_van_de": false,
  "ghi_chu_do_tin_cay": "chỗ nào trong ảnh bạn không chắc hoặc không đọc rõ"
}`

function dataUrl(file) {
  const b = readFileSync(file)
  return 'data:image/png;base64,' + b.toString('base64')
}

async function analyzeOnce(name) {
  const file = join(SHOTS, name)
  if (!existsSync(file)) return { name, error: 'khong tim thay tep' }
  const body = {
    model: MODEL,
    temperature: 0,
    max_tokens: 8000,
    messages: [
      { role: 'system', content: SYSTEM },
      {
        role: 'user',
        content: [
          { type: 'text', text: `Đây là ảnh chụp màn hình "${name}". Hãy phân tích theo đúng định dạng JSON đã yêu cầu.` },
          { type: 'image_url', image_url: { url: dataUrl(file) } },
        ],
      },
    ],
  }
  const res = await fetch(BASE + '/chat/completions', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + KEY },
    body: JSON.stringify(body),
  })
  const text = await res.text()
  if (!res.ok) return { name, error: `HTTP ${res.status}: ${text.slice(0, 500)}` }
  let parsed
  try { parsed = JSON.parse(text) } catch { return { name, error: 'phan hoi khong phai JSON: ' + text.slice(0, 300) } }
  const content = parsed?.choices?.[0]?.message?.content
  if (typeof content !== 'string') return { name, error: 'khong co noi dung: ' + JSON.stringify(parsed).slice(0, 300) }
  return { name, model: MODEL, raw: content, usage: parsed.usage }
}

async function analyze(name) {
  let last
  for (let attempt = 1; attempt <= 3; attempt++) {
    last = await analyzeOnce(name)
    if (!last.error) return last
    await new Promise((r) => setTimeout(r, 1500 * attempt))
  }
  return last
}

const names = process.argv.slice(2)
if (names.length === 0) {
  console.error('Can truyen danh sach ten tep anh.')
  process.exit(2)
}
const out = []
for (const n of names) {
  process.stdout.write('... ' + n + '\n')
  const r = await analyze(n)
  out.push(r)
  console.log(n + ' => ' + (r.error ? 'LOI: ' + r.error : 'OK (' + (r.raw || '').length + ' ky tu)'))
  writeFileSync(OUT, JSON.stringify(out, null, 2), 'utf8')
}
console.log('Da ghi: ' + OUT)
