<?php
// views/partial_report_card.php

/**
 * Expected variables:
 * $holyName, $fullName, $code, $className, $birthDate
 * $termName, $termFrom, $termTo
 * $attTotal, $attPresent, $attLate, $attExcused, $attUnexcused, $attRate
 * $scoreDisplay, $conductDisplay, $rankDisplay, $remarkDisplay
 * $createdBy, $status
 */
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Phiếu Liên Lạc - <?= $fullName ?></title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Times New Roman', serif; padding: 20px; max-width: 800px; margin: 0 auto; }
.card { border: 2px solid #333; border-radius: 8px; padding: 24px; }
.header { text-align: center; border-bottom: 1px solid #ccc; padding-bottom: 16px; margin-bottom: 20px; }
.header .org { font-size: 14px; font-weight: bold; color: #666; letter-spacing: 2px; }
.header h1 { font-size: 22px; margin: 8px 0; }
.header .term { font-size: 12px; color: #666; }
.info { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 20px; font-size: 14px; }
.info span { color: #666; }
.section { margin-bottom: 16px; }
.section h3 { font-size: 14px; border-bottom: 1px solid #eee; padding-bottom: 4px; margin-bottom: 8px; }
.stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; text-align: center; margin-bottom: 12px; }
.stats div { padding: 8px; background: #f5f5f5; border-radius: 4px; }
.stats .val { font-size: 20px; font-weight: bold; }
.stats .lbl { font-size: 10px; color: #666; }
.grades { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; text-align: center; margin-bottom: 16px; }
.grades div { padding: 12px; border: 1px solid #ddd; border-radius: 4px; }
.grades .val { font-size: 18px; font-weight: bold; }
.grades .lbl { font-size: 10px; color: #666; margin-top: 4px; }
.rank { background: #e3f2fd; border-color: #2196f3 !important; }
.rank .val { color: #1565c0; }
.remark { background: #fafafa; padding: 12px; border-radius: 4px; margin-bottom: 16px; font-style: italic; }
.signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; text-align: center; margin-top: 24px; padding-top: 16px; border-top: 1px solid #eee; }
.signatures p { font-size: 11px; color: #666; margin-bottom: 40px; }
.status { text-align: right; font-size: 12px; color: #888; margin-top: 8px; }
@media print { body { padding: 0; } .card { border: 1px solid #000; } }
</style>
</head>
<body>
<div class="card">
    <div class="header">
        <p class="org">ĐOÀN THIẾU NHI THÁNH THỂ</p>
        <h1>PHIẾU LIÊN LẠC</h1>
        <p class="term"><?= $termName ?> (<?= $termFrom ?> – <?= $termTo ?>)</p>
    </div>

    <div class="info">
        <div><span>Họ và tên:</span> <strong><?= $holyName ?> <?= $fullName ?></strong></div>
        <div><span>Mã số:</span> <strong><?= $code ?></strong></div>
        <div><span>Lớp:</span> <strong><?= $className ?></strong></div>
        <div><span>Ngày sinh:</span> <strong><?= $birthDate ?></strong></div>
    </div>

    <div class="section">
        <h3>CHUYÊN CẦN</h3>
        <div class="stats">
            <div><div class="val"><?= $attTotal ?></div><div class="lbl">Tổng số buổi</div></div>
            <div><div class="val" style="color:#2e7d32"><?= $attPresent ?></div><div class="lbl">Có mặt</div></div>
            <div><div class="val" style="color:#f57c00"><?= $attLate ?></div><div class="lbl">Đi trễ</div></div>
            <div><div class="val" style="color:#1976d2"><?= $attExcused ?></div><div class="lbl">Có phép</div></div>
            <div><div class="val" style="color:#c62828"><?= $attUnexcused ?></div><div class="lbl">Không phép</div></div>
        </div>
        <div style="text-align:center; font-weight:bold;">Tỷ lệ có mặt: <span style="font-size:18px"><?= $attRate ?>%</span></div>
    </div>

    <div class="section">
        <h3>HỌC TẬP & HẠNH KIỂM</h3>
        <div class="grades">
            <div>
                <div class="val"><?= $scoreDisplay ?></div>
                <div class="lbl">Điểm học lực</div>
            </div>
            <div>
                <div class="val" style="font-size:14px; text-transform:capitalize"><?= $conductDisplay ?></div>
                <div class="lbl">Hạnh kiểm</div>
            </div>
            <div class="rank">
                <div class="val"><?= $rankDisplay ?></div>
                <div class="lbl">Xếp loại</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h3>NHẬN XÉT CỦA GIÁO LÝ VIÊN</h3>
        <div class="remark"><?= $remarkDisplay ?></div>
    </div>

    <div class="signatures">
        <div>
            <p>GLV CHỦ NHIỆM</p>
            <p><?= $createdBy ?></p>
        </div>
        <div>
            <p>PHỤ HUYNH KÝ TÊN</p>
            <p>.....................</p>
        </div>
    </div>

    <div class="status">Trạng thái: <?= $status ?></div>
</div>
</body>
</html>
