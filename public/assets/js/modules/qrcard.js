/* ==========================================================
   IN THẺ QR CHO THIẾU NHI

   Không có thẻ thì không có gì để quét. Màn này dựng một trang in
   gồm thẻ của từng em trong lớp đang lọc, cắt ra là dùng được.

   Mã QR chứa ĐÚNG MÃ SỐ của em, không gì khác. Cố ý:
     - Quét bằng app khác cũng chỉ ra một chuỗi vô hại như "KT1A001",
       không lộ tên, ngày sinh hay số điện thoại cha mẹ
     - Thẻ rơi ra ngoài cũng không thành rò rỉ thông tin

   Thư viện qrcode.min.js chỉ nạp khi bấm nút, không nằm trong đường
   tải của trang thường.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.qrcard = {

    qrTheDangLam: false,

    _qrTaiBoSinh() {
        if (window.qrcode) return Promise.resolve();
        return new Promise((ok, hong) => {
            const s = document.createElement('script');
            s.src = 'assets/js/vendor/qrcode.min.js';
            s.onload = ok;
            s.onerror = () => hong(new Error('Không tải được bộ sinh mã QR.'));
            document.head.appendChild(s);
        });
    },

    /** Vẽ một mã QR thành chuỗi <img src="data:..."> */
    _qrAnh(noiDung, coO) {
        // Mức sửa lỗi M: chịu được thẻ hơi bẩn hoặc mờ mà vẫn đọc ra.
        const qr = window.qrcode(0, 'M');
        qr.addData(String(noiDung));
        qr.make();
        return qr.createImgTag(coO, 0);   // 0 = không chừa lề, tự canh bằng CSS
    },

    async inTheQR() {
        const ds = this.filteredStudents;
        if (!ds.length) {
            alert('Không có em nào trong danh sách đang xem để in thẻ.');
            return;
        }
        if (ds.length > 200 && !confirm('Sẽ in thẻ cho ' + ds.length + ' em. Tiếp tục?')) return;

        this.qrTheDangLam = true;
        try {
            await this._qrTaiBoSinh();
        } catch (e) {
            this.qrTheDangLam = false;
            alert(e.message);
            return;
        }

        const lop = this.filterClass || '';
        const nien = this.year ? this.year.name : '';

        const the = ds.map(s => `
            <div class="the">
                <div class="qr">${this._qrAnh(s.code, 4)}</div>
                <div class="tt">
                    <p class="ma">${this._thoat(s.code)}</p>
                    <p class="ten">${this._thoat(s.name)}</p>
                    <p class="lop">${this._thoat(s.className || '')}</p>
                </div>
            </div>`).join('');

        const html = `<!doctype html>
<html lang="vi"><head><meta charset="utf-8">
<title>Thẻ QR${lop ? ' — ' + this._thoat(lop) : ''}</title>
<style>
  @page { size: A4; margin: 10mm; }
  * { box-sizing: border-box; }
  body { font-family: "Be Vietnam Pro", system-ui, sans-serif; margin: 0; color: #0f172a; }
  h1 { font-size: 15px; margin: 0 0 2px; }
  .phu { font-size: 11px; color: #64748b; margin: 0 0 10px; }

  /* 3 thẻ một hàng, vừa khổ A4 và cắt thẳng bằng dao rọc giấy */
  .luoi { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4mm; }
  .the {
      border: 1px dashed #94a3b8; border-radius: 3mm;
      padding: 3mm; display: flex; align-items: center; gap: 3mm;
      break-inside: avoid; page-break-inside: avoid;
  }
  .qr img { display: block; width: 22mm; height: 22mm; image-rendering: pixelated; }
  .tt { min-width: 0; }
  .ma  { font-size: 10px; font-weight: 800; letter-spacing: .3px; color: #2563eb; margin: 0 0 1px; }
  .ten { font-size: 11px; font-weight: 700; margin: 0 0 1px; line-height: 1.25;
         overflow-wrap: anywhere; }
  .lop { font-size: 9px; color: #64748b; margin: 0; }

  .huongdan { margin-top: 8mm; font-size: 10px; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 3mm; }
  @media print { .huongdan { page-break-before: avoid; } }
</style></head>
<body>
  <h1>Thẻ điểm danh${lop ? ' — ' + this._thoat(lop) : ''}</h1>
  <p class="phu">${nien ? 'Niên khoá ' + this._thoat(nien) + ' · ' : ''}${ds.length} em · in ngày ${this.formatDate(this.toDateInput(new Date()))}</p>
  <div class="luoi">${the}</div>
  <p class="huongdan">
     Cắt theo đường nét đứt. Nên ép nhựa hoặc dán lên bìa cứng —
     thẻ nhàu thì camera khó đọc. Mã QR chỉ chứa mã số của em,
     không chứa thông tin cá nhân nào.
  </p>
  <script>window.onload = function () { window.print(); };<\/script>
</body></html>`;

        const cs = window.open('', '_blank');
        if (!cs) {
            this.qrTheDangLam = false;
            alert('Trình duyệt đã chặn cửa sổ in.\nHãy cho phép mở cửa sổ mới rồi thử lại.');
            return;
        }
        cs.document.write(html);
        cs.document.close();

        this.qrTheDangLam = false;
        this.logAction('tao', 'students', 'In thẻ QR', ds.length + ' em'
                      + (lop ? ' · ' + lop : ''));
    },

    /** Tên em do người dùng nhập -> phải thoát trước khi nhét vào HTML */
    _thoat(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    },
};
