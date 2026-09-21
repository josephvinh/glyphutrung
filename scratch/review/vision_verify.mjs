// Pass kiểm chứng: hỏi thẳng câu hỏi cụ thể cho từng ảnh, trả lời CÓ/KHÔNG kèm bằng chứng.
import { readFileSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'

const KEY = process.env.KEY4U_KEY
const BASE = 'https://api.key4u.vn/v1'
const MODEL = process.env.VISION_MODEL
const SHOTS = 'G:\\xampp\\htdocs\\tntt\\scratch\\review\\shots'
const OUT = process.env.OUT_FILE

const JOBS = [
  {
    file: 'staff-mobile.png',
    q: `Nhìn kỹ thanh điều hướng ở ĐÁY màn hình. Hỏi: (a) Nền của thanh điều hướng đó có phải màu trắng ĐỤC hoàn toàn không, hay có chữ/hình của nội dung trang phía sau LỘ XUYÊN QUA nhìn thấy được? (b) Nếu có chữ lộ qua, hãy đọc CHÍNH XÁC chữ đó. (c) Nội dung trang phía trên thanh điều hướng có bị thanh này che mất không?`,
  },
  {
    file: 'students-mobile.png',
    q: `Kiểm tra ô TÌM KIẾM ở giữa màn hình. Hỏi: (a) Biểu tượng kính lúp có bị ĐÈ LÊN chữ placeholder không, hay nằm tách riêng bên trái chữ? (b) Đọc CHÍNH XÁC chữ placeholder trong ô tìm kiếm, từng ký tự. (c) Trong thẻ thiếu nhi phía dưới: có hàng nào chỉ có biểu tượng (ghim vị trí, điện thoại) mà KHÔNG có chữ đi kèm không? Nhãn 'TÊN CHA' và 'TÊN MẸ' có tên người đi kèm không?`,
  },
  {
    file: 'students-desktop.png',
    q: `Kiểm tra ô TÌM KIẾM. Hỏi: (a) Biểu tượng kính lúp có bị ĐÈ LÊN chữ placeholder không? (b) Đọc CHÍNH XÁC chữ placeholder. (c) Nhãn 'TÊN CHA' và 'TÊN MẸ' trong thẻ thiếu nhi có tên người đi kèm không, hay để trống?`,
  },
  {
    file: 'attendance-desktop.png',
    q: `Đọc CHÍNH XÁC, từng ký tự, hai chuỗi sau: (a) chuỗi nằm TRONG ô nhập ngày (ô có thể bấm để chọn ngày), (b) chuỗi ngày tháng nằm ở dòng chữ mô tả NGAY BÊN DƯỚI ô đó. Cho biết hai chuỗi này có cùng thứ tự ngày/tháng không.`,
  },
  {
    file: 'leave-mobile.png',
    q: `Đọc CHÍNH XÁC, từng ký tự: (a) chuỗi nằm TRONG ô nhập ngày 'NGÀY XIN PHÉP', (b) chuỗi ngày tháng ở dòng chữ mô tả ngay bên dưới ô đó. Hai chuỗi này có cùng thứ tự ngày/tháng không?`,
  },
  {
    file: 'guide-mobile.png',
    q: `Nhìn kỹ vùng ĐÁY màn hình, nơi thanh điều hướng nằm. Hỏi: (a) Có chữ nào của nội dung trang bị thanh điều hướng ĐÈ LÊN hoặc lộ xuyên qua không? (b) Nếu có, đọc chính xác chữ đó. (c) Thanh điều hướng có nền trắng đục hay trong suốt?`,
  },
  {
    file: 'dashboard-mobile.png',
    q: `Nhìn hàng thẻ thống kê nằm ngang ở phần trên (dưới header đỏ). Hỏi: (a) Thẻ nằm ngoài cùng bên PHẢI có bị CẮT CỤT ở mép phải màn hình không? (b) Nếu có, đọc chính xác phần chữ còn nhìn thấy được trên thẻ đó. (c) Hàng thẻ này có phải dạng cuộn ngang (còn thẻ khác bị ẩn) không?`,
  },
  {
    file: 'promotion-mobile.png',
    q: `Trong khối viền nét đứt ở giữa màn hình có đoạn văn hướng dẫn. Hỏi: (a) Có biểu tượng hình PHỄU LỌC nhỏ nằm lẫn trong đoạn văn bản đó không? (b) Nó nằm ở dòng nào, căn lề ra sao so với các dòng chữ xung quanh (có bị rớt xuống dòng riêng, lệch lề trái không)? Mô tả chính xác vị trí.`,
  },
  {
    file: 'notes-mobile.png',
    q: `Kiểm tra vùng tiêu đề 'Lịch của tôi' và nút 'Thêm việc' màu đỏ. Hỏi: (a) Nút 'Thêm việc' có bị ĐÈ LÊN dòng chữ phụ đề nào không? (b) Nếu có, đọc chính xác dòng chữ bị đè. (c) Nút đó có bị tràn ra ngoài mép phải màn hình không?`,
  },
  {
    file: 'org-mobile.png',
    q: `Tìm ô chọn (dropdown) 'TRƯỞNG KHỐI' trong thẻ khối 'Khai Tâm'. Hỏi: (a) Đọc CHÍNH XÁC toàn bộ chữ hiển thị trong ô đó, kể cả phần bị cắt. (b) Chữ đó có bị CẮT CỤT ở mép phải ô không, hay hiển thị đầy đủ?`,
  },
]

function dataUrl(file) {
  return 'data:image/png;base64,' + readFileSync(join(SHOTS, file)).toString('base64')
}

const out = []
for (const job of JOBS) {
  const body = {
    model: MODEL,
    temperature: 0,
    max_tokens: 2000,
    messages: [
      {
        role: 'system',
        content: `Bạn là chuyên gia QA giao diện đang kiểm chứng một phát hiện cụ thể trên ảnh chụp màn hình.
Chỉ trả lời dựa trên những gì THỰC SỰ NHÌN THẤY. Nếu không chắc hoặc không nhìn rõ, phải nói thẳng là
"KHÔNG CHẮC" hoặc "KHÔNG NHÌN RÕ". Tuyệt đối không suy đoán, không bịa. Trả lời ngắn gọn, trực tiếp
vào từng câu hỏi (a), (b), (c).`,
      },
      {
        role: 'user',
        content: [
          { type: 'text', text: `Ảnh: "${job.file}".\n\n${job.q}` },
          { type: 'image_url', image_url: { url: dataUrl(job.file) } },
        ],
      },
    ],
  }
  let answer = null
  for (let i = 0; i < 3 && answer === null; i++) {
    try {
      const res = await fetch(BASE + '/chat/completions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + KEY },
        body: JSON.stringify(body),
      })
      const t = await res.text()
      if (res.ok) {
        const p = JSON.parse(t)
        answer = p?.choices?.[0]?.message?.content ?? null
      } else {
        answer = 'LOI HTTP ' + res.status + ': ' + t.slice(0, 200)
      }
    } catch (e) {
      answer = 'LOI: ' + e.message
    }
  }
  console.log('\n########## ' + job.file + ' ##########')
  console.log(answer)
  out.push({ file: job.file, model: MODEL, question: job.q, answer })
  writeFileSync(OUT, JSON.stringify(out, null, 2), 'utf8')
}
console.log('\nDa ghi: ' + OUT)
