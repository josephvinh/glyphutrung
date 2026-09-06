/* ==========================================================
   TNTT SUPER APP — điểm khởi động

   Trước đây toàn bộ ứng dụng nằm trong MỘT tệp 3.109 dòng: 105
   thuộc tính trạng thái và 272 phương thức dùng chung một không
   gian tên, nên sửa Điểm Danh có thể làm vỡ Thống Kê mà không ai
   hay. Nay mỗi màn hình là một mảnh riêng trong modules/, đặt tên
   khớp với views/module_*.php cho dễ lần.

   Tệp này chỉ làm một việc: gộp các mảnh lại thành component
   tnttApp của Alpine.
   ========================================================== */

document.addEventListener('alpine:init', () => {

    // Danh sách các mảnh cần gộp — NGUỒN DUY NHẤT ở
    // public/assets/asset_manifest.php, được index.php nhúng vào
    // window.TNTT_MODULES (cả bản dev nạp lẻ lẫn bản gộp production). Nhờ vậy
    // thêm module mới chỉ khai MỘT chỗ, không còn cảnh nạp mà quên gộp.
    // (Thứ tự nạp không quan trọng về chức năng — mỗi mảnh tự đứng độc lập.)
    const MANH = window.TNTT_MODULES || [];
    if (!MANH.length) {
        console.error('[TNTT] window.TNTT_MODULES rỗng — index.php chưa nhúng '
                    + 'danh sách module (public/assets/asset_manifest.php).');
    }

    /**
     * Gộp các mảnh thành một đối tượng.
     *
     * KHÔNG dùng Object.assign được: component có 82 getter (các
     * thuộc tính dẫn xuất như filteredStudents, todaySession...).
     * Object.assign ĐỌC getter rồi chép GIÁ TRỊ tại thời điểm gộp,
     * biến chúng thành hằng số chết — Alpine sẽ không bao giờ tính
     * lại. Phải chép mô tả thuộc tính để getter vẫn là getter.
     */
    function gopManh() {
        const dich = {};
        const thieu = [];

        for (const ten of MANH) {
            const manh = window.TNTT && window.TNTT[ten];
            if (!manh) { thieu.push(ten); continue; }
            Object.defineProperties(dich, Object.getOwnPropertyDescriptors(manh));
        }

        // Thiếu mảnh nghĩa là index.php chưa nạp tệp đó — thường do cập
        // nhật mà quên chép index.php mới. Trước đây chỉ ghi console.error,
        // nên triệu chứng duy nhất là "bấm nút không thấy gì" và rất khó
        // lần ra. Nay báo thẳng, kèm đúng tên tệp cần chép.
        if (thieu.length) {
            const tin = 'Bản cài đặt thiếu ' + thieu.length + ' phần:\n  '
                      + thieu.join(', ') + '\n\n'
                      + 'Nguyên nhân: tệp public/index.php trên máy chủ là bản cũ,\n'
                      + 'chưa nạp các tệp assets/js/modules/*.js mới.\n\n'
                      + 'Hãy chép public/index.php của bản cập nhật lên máy chủ.';
            console.error('[TNTT] ' + tin);
            setTimeout(() => alert(tin), 800);
        }
        return dich;
    }

    Alpine.data('tnttApp', gopManh);
});
