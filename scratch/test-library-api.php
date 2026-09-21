<?php
/**
 * Test Thư Viện: gọi API trực tiếp.
 * Chạy: php scratch/test-library-api.php
 */
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../public/api/_bootstrap.php';
require __DIR__ . '/../public/api/_library.php';

echo "=== 1. Kiểm tra CSDL ===\n";
$cats = db_all("SELECT * FROM library_categories");
echo "Chủ đề: " . count($cats) . " dòng\n";
foreach ($cats as $c) printf("  id=%d name=%s active=%s\n", $c['id'], $c['name'], $c['is_active']);

$items = db_all("SELECT id, title, item_type, status FROM library_items");
echo "Mục: " . count($items) . " dòng\n";
foreach ($items as $i) printf("  id=%d type=%s status=%s title=%s\n", $i['id'], $i['item_type'], $i['status'], $i['title']);

echo "\n=== 2. Gọi library_search_sql ===\n";
$p = [];
$sql = library_search_sql('', $p);
echo "q='' → '$sql' (params: " . count($p) . ")\n";
$p2 = [];
$sql2 = library_search_sql('abc', $p2);
echo "q='abc' → '$sql2' (params: " . count($p2) . ")\n";

echo "\n=== 3. Gọi library_page ===\n";
$p = ['page' => 1, 'perPage' => 20];
[$limit, $offset, $page] = library_page($p);
echo "page=1, perPage=20 → limit=$limit, offset=$offset, page=$page\n";
$p2 = ['page' => 2, 'perPage' => 60];
[$limit2, $offset2, $page2] = library_page($p2);
echo "page=2, perPage=60 → limit=$limit2, offset=$offset2, page=$page2\n";

echo "\n=== 4. Gọi action=list (như frontend) ===\n";
$in = ['category' => 0, 'q' => '', 'page' => 1, 'perPage' => 20];
$where = "i.status = 'da_duyet'";
$params = [];
$where .= library_search_sql($in['q'], $params);
[$limit, $offset, $page] = library_page($in);
$total = library_count($where, $params);
$rows = db_all(
    "SELECT i.*, c.name AS category_name
       FROM library_items i
       LEFT JOIN library_categories c ON c.id = i.category_id
      WHERE $where
      ORDER BY i.approved_at DESC, i.id DESC
      LIMIT $limit OFFSET $offset",
    $params
);
echo "WHERE: $where\n";
echo "total=$total, rows=" . count($rows) . "\n";
foreach (array_map('library_row_out', $rows) as $r) {
    echo "  - $r[type] | $r[title] | cat=$r[categoryName] | status=$r[status]\n";
}

echo "\n=== 5. app_config('library') ===\n";
$libCfg = app_config('library');
echo json_encode($libCfg, JSON_UNESCAPED_UNICODE) . "\n";
echo "storage_path: " . ($libCfg['storage_path'] ?? 'NULL') . "\n";
echo "storage exists: " . (is_dir($libCfg['storage_path'] ?? '') ? 'YES' : 'NO (will be created)') . "\n";

echo "\n=== 6. permission_of('thu_vien') ===\n";
// Giả lập admin
$admin = db_one("SELECT * FROM members WHERE role_code='admin' LIMIT 1");
echo "Admin perms: " . json_encode(permission_of('thu_vien')) . "\n";
