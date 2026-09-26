// Trích JSON từ kết quả vision thành bản gọn dễ đọc.
import { readFileSync } from 'node:fs'

const files = process.argv.slice(2)
for (const f of files) {
  const arr = JSON.parse(readFileSync(f, 'utf8'))
  console.log('\n########## ' + f + ' (' + arr.length + ' anh) ##########')
  for (const item of arr) {
    console.log('\n===== ' + item.name + (item.error ? '  [LOI: ' + item.error + ']' : '') + ' =====')
    if (item.error) continue
    let raw = item.raw.trim().replace(/^```json\s*/i, '').replace(/```$/, '').trim()
    let o
    try { o = JSON.parse(raw) } catch {
      console.log('(JSON khong hop le, in raw)\n' + raw.slice(0, 1200)); continue
    }
    console.log('doc_duoc_anh: ' + o.doc_duoc_anh + ' | kich_thuoc: ' + (o.kich_thuoc_uoc_luong || '?'))
    console.log('chu_de_trang: ' + JSON.stringify(o.chu_de_trang))
    console.log('mo_ta: ' + (o.mo_ta_tong_quan || ''))
    if (o.so_lieu_thong_ke?.length) console.log('so_lieu: ' + JSON.stringify(o.so_lieu_thong_ke))
    if (o.cac_nut_hanh_dong?.length) console.log('nut: ' + JSON.stringify(o.cac_nut_hanh_dong))
    console.log('khong_thay_van_de: ' + o.khong_thay_van_de)
    const vd = o.van_de || []
    if (vd.length === 0) console.log('VAN_DE: (khong co)')
    else for (const v of vd) {
      console.log(`  - [${v.loai}/${v.muc_do}] ${v.mo_ta}  @ ${v.vi_tri || '?'}`)
    }
    if (o.ghi_chu_do_tin_cay) console.log('do_tin_cay: ' + o.ghi_chu_do_tin_cay)
  }
}
