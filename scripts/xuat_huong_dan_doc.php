<?php
/**
 * XUẤT HƯỚNG DẪN RA FILE .DOC (mở được bằng Word / trình duyệt).
 *
 *   php scripts/xuat_huong_dan_doc.php [đường-dẫn-xuất.doc]
 *
 * Không cần thư viện ngoài: sinh HTML tương thích Word rồi lưu .doc.
 * Nội dung lấy từ config/huong_dan.php (cùng nguồn với module Hướng dẫn).
 */

$HD  = require __DIR__ . '/../config/huong_dan.php';
$out = $argv[1] ?? (__DIR__ . '/../docs/Huong-dan-su-dung.doc');

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

$ngay = date('d/m/Y');

ob_start();
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="utf-8">
<title><?= $h($HD['tieu_de']) ?></title>
<style>
  body   { font-family: "Segoe UI", Arial, sans-serif; font-size: 12pt; color: #1f2937; line-height: 1.5; }
  h1     { font-size: 20pt; color: #1d4ed8; margin: 0 0 4pt; }
  h2     { font-size: 15pt; color: #1d4ed8; margin: 18pt 0 4pt; border-bottom: 1pt solid #cbd5e1; padding-bottom: 3pt; }
  h3     { font-size: 12.5pt; color: #111827; margin: 10pt 0 2pt; }
  .meta  { color: #6b7280; font-size: 10pt; margin-bottom: 12pt; }
  .intro { background: #eff6ff; border: 1pt solid #bfdbfe; padding: 8pt 10pt; margin-bottom: 12pt; }
  .desc  { color: #4b5563; font-style: italic; margin: 0 0 4pt; }
  ol     { margin: 2pt 0 8pt; padding-left: 22pt; }
  li     { margin-bottom: 2pt; }
  .role  { background: #1d4ed8; color: #fff; padding: 6pt 10pt; margin: 16pt 0 6pt; }
  .role .desc { color: #dbeafe; font-style: normal; margin: 2pt 0 0; }
</style>
</head>
<body>
  <h1><?= $h($HD['tieu_de']) ?></h1>
  <p class="meta">Giáo xứ Phú Trung · Ngày xuất: <?= $h($ngay) ?></p>
  <div class="intro"><?= $h($HD['gioi_thieu']) ?></div>

  <h2>Dùng chung cho mọi vai</h2>
  <?php foreach ($HD['chung'] as $m): ?>
    <h3><?= $h($m['title']) ?></h3>
    <ol><?php foreach ($m['steps'] as $s): ?><li><?= $h($s) ?></li><?php endforeach; ?></ol>
  <?php endforeach; ?>

  <h2>Hướng dẫn theo vai</h2>
  <?php foreach ($HD['vai'] as $r): ?>
    <div class="role">
      <b><?= $h($r['label']) ?></b>
      <div class="desc"><?= $h($r['mo_ta']) ?></div>
    </div>
    <?php foreach ($r['items'] as $m): ?>
      <h3><?= $h($m['title']) ?></h3>
      <ol><?php foreach ($m['steps'] as $s): ?><li><?= $h($s) ?></li><?php endforeach; ?></ol>
    <?php endforeach; ?>
  <?php endforeach; ?>
</body>
</html>
<?php
$html = ob_get_clean();

// BOM UTF-8 để Word đọc đúng tiếng Việt
if (!is_dir(dirname($out))) mkdir(dirname($out), 0755, true);
file_put_contents($out, "\xEF\xBB\xBF" . $html);
echo "Đã xuất: " . realpath($out) . " (" . number_format(strlen($html)) . " bytes)\n";
