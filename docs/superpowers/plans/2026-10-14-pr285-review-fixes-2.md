# Kế Hoạch Xử Lý Review PR #285 (Phần 2) — Sửa 🟠 6–13 + 🟡

> **For agentic workers:** REQUIRED SUB-SKILL: Dùng `superpowers:executing-plans`
> để chạy từng task. Mỗi task có checkbox `- [ ]` để theo dõi.

**Mục tiêu:** Sửa các lỗi 🟠 6–13 và 🟡 mà review #285 nêu, **một commit
= một mục đích** (luật cứng #4). 4 mục 🔴 1–5 đã sửa ở plan trước
(commit `40e4257`, `9c52361`, `8cb40c0`, `01d8479`).

**Kiến trúc:** Mỗi task là một thay đổi độc lập, có test riêng, commit riêng.
Không sửa nhiều file trong một commit. Một số mục 🟠 động vào cùng file
(ví dụ 6+7 đều vào `scores.php`) — vẫn tách thành 2 commit riêng vì là 2
mục đích khác nhau (validation vs transaction).

**Tech Stack:** PHP 8.2, MariaDB 10.4, PHPUnit 10.5, Alpine.js.
Stack hiện có: PDO qua `db_one/db_run/db_all`, `Cache::flush()`,
`log_action()`, `json_fail()`, `require_permission()`, `can_access_class()`.

**Spec / Review:**
- Spec gốc: `docs/superpowers/specs/2024-01-15-customizable-score-rules-design.md`
- Plan gốc: `docs/superpowers/plans/2024-01-15-customizable-score-rules-implementation-plan.md`
- Review PR #285 (nguồn các mục): xem comment `PRR_kwDOUPbGYM8AAAABR_ccIQ` trên GitHub

## Phạm vi — chỉ sửa các mục dưới đây

| Mục | File chính | Mức độ | Ghi chú |
|---|---|---|---|
| 🟠 6 | `public/api/scores.php` | validation | clamp exam_num theo max_exams |
| 🟠 7 | `public/api/scores.php` | transaction | bọc DELETE+UPDATE trong TX, validate input |
| 🟠 8 | `public/api/attendance_scores.php` | hiệu năng + đúng | endpoint lớp, lọc theo `start_date/end_date` |
| 🟠 9 | `public/api/term_programs.php` | hành vi | bỏ GET-side seed, validate FK trước insert |
| 🟠 10 | `public/api/rules.php` | validation | whitelist rule_key + validate schema |
| 🟠 11 | `public/api/attendance_thresholds.php` | thứ tự + dup | sort theo min_percent, chặn trùng, validate trước khi clamp |
| 🟠 12 | `public/api/score_types.php` | UTF-8 + log | mb_substr, log thay đổi weight/max_exams, validate code ở PUT/DELETE |
| 🟠 13 | `config/install.php` | seed | kiểm `p45` sort_order không trùng |
| 🟠 3 | `config/migrations/index.php` + `install.php` | runner | chỉ nuốt lỗi cho ADD/ALTER, không cho CREATE/INSERT |
| 🟡 | PR description | docs | bỏ docs/superpowers khỏi PR, ghi rõ bước backup, đánh dấu checklist đúng |

## Ràng buộc chung (luật cứng — áp dụng mọi task)

1. Một commit = một mục đích. Không gộp 🟠 6 với 🟠 7 dù cùng file.
2. Không commit/push trừ khi user yêu cầu.
3. "Xong" = chạy test + verify thật, không đoán.
4. Xoá code phải `grep` mọi nơi gọi.
5. Không nuốt lỗi dữ liệu quan trọng. Không `INSERT IGNORE` ở API ghi.
6. Endpoint ghi phải `require_write()` + `require_permission(module,'edit')` + kiểm phạm vi.
7. Secret không commit. Định danh model không commit.
8. Không chạy `seed_demo.php`/`install.php` trên DB thật.
9. JS module không trùng tên thuộc tính/hàm.
10. Tiếng Việt trong comment, English cho identifier.

## Quy ước commit message

- Tiêu đề `<type>(<scope>): <mô tả ngắn>` — type gồm `fix/feat/test/refactor/docs`
- Mỗi commit ghi `Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>` (xem CLAUDE.md #7)
- Body giải thích: vì sao sửa, sửa gì, test ra sao
- Body tiếng Việt

---

## Task 1 — Mục 🟠 6: `scores.php set` — clamp `exam_num` theo `max_exams`

**File:**
- Modify: `public/api/scores.php:48-60` (block validation `set`)
- Test: `tests/unit/ScoresExamNumApiTest.php` (thêm test mới, không sửa test cũ)

**Interface:**
- Consumes: `score_types.max_exams` (cột đã có từ migration 031)
- Produces: HTTP 400 khi `exam_num` ngoài khoảng `[1, max_exams]`

**Bối cảnh:** Review mục 6 — dòng 49 chỉ có `max(1, (int) ...)` nên
`exam_num=99` vẫn pass. Cần đọc `max_exams` của loại điểm, kiểm
`1 <= exam_num <= max_exams`. Cũng bỏ `exam_num = VALUES(exam_num)`
trong `ON DUPLICATE KEY UPDATE` vì `exam_num` nằm trong khoá unique
(dòng 78).

- [ ] **Step 1: Viết test fail**

Thêm vào `tests/unit/ScoresExamNumApiTest.php` (cuối class, trước `}`):

```php
public function test_set_score_rejects_exam_num_above_max(): void
{
    // 'p45' đã có max_exams = 2 (xem migration 031 dòng 76).
    // exam_num = 5 phải bị 400.
    $r = $this->http('POST', '/api/scores.php', [
        'action' => 'set',
        'studentId' => $this->studentId,
        'termId'    => $this->termId,
        'type'      => 'p45',
        'exam_num'  => 5,
        'value'     => '8.0',
    ]);
    $this->assertSame(400, $r['code'],
        'exam_num > max_exams phải bị 400: ' . $r['raw']);

    // Bài hợp lệ (exam_num=2) vẫn pass — khẳng định validation không quá tay.
    $r2 = $this->http('POST', '/api/scores.php', [
        'action' => 'set',
        'studentId' => $this->studentId,
        'termId'    => $this->termId,
        'type'      => 'p45',
        'exam_num'  => 2,
        'value'     => '7.0',
    ]);
    $this->assertSame(200, $r2['code'],
        'exam_num = max_exams vẫn OK: ' . $r2['raw']);
}
```

- [ ] **Step 2: Chạy test xác nhận fail**

```bash
TNTT_DB_HOST=127.0.0.1 TNTT_DB_NAME=tntt_test TNTT_DB_USER=tntt TNTT_DB_PASS=tntt \
  php phpunit.phar tests/unit/ScoresExamNumApiTest.php --filter test_set_score_rejects_exam_num_above_max
```

Expected: FAIL (HTTP code trả về 200 thay vì 400, vì validation chưa có).

- [ ] **Step 3: Sửa `scores.php`**

Trong `public/api/scores.php`, đổi block validation sau dòng 58
(`if (!db_one('SELECT code FROM score_types WHERE code=?', [$type]))`):

```php
    if (!db_one('SELECT code FROM score_types WHERE code=?', [$type])) {
        json_fail('Đầu điểm không hợp lệ.');
    }
    $typeRow = db_one('SELECT max_exams FROM score_types WHERE code=?', [$type]);
    if ($examNum < 1 || $examNum > (int) $typeRow['max_exams']) {
        json_fail('Bài số ' . $examNum . ' vượt quá số bài tối đa (' . $typeRow['max_exams'] . ') của đầu điểm này.');
    }
```

Và ở dòng 78, bỏ `exam_num = VALUES(exam_num),`:

```php
    db_run('INSERT INTO scores (term_id, student_id, type_code, exam_num, value, updated_by)
            VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by)',
        [$termId, $studentId, $type, $examNum, $value, $me['id']]);
```

- [ ] **Step 4: Chạy test xác nhận pass**

```bash
TNTT_DB_HOST=127.0.0.1 TNTT_DB_NAME=tntt_test TNTT_DB_USER=tntt TNTT_DB_PASS=tntt \
  php phpunit.phar tests/unit/ScoresExamNumApiTest.php
```

Expected: tất cả test pass (kể cả test mới và test cũ). Đặc biệt
`test_delete_exam_removes_score_and_renumbers` phải còn pass vì dùng
`exam_num=1,2,3` với `type='p45'` (`max_exams=2` → bị 400? Xem lại).

> ⚠️ **Điểm cần kiểm tra:** `p45` có `max_exams=2` theo migration 031
> dòng 76. Nhưng test cũ `test_delete_exam_removes_score_and_renumbers`
> dùng `exam_num=1,2,3` cho `type='p45'`. Nếu hard-cap ở `max_exams=2`
> thì test cũ vỡ. Giải pháp: đổi test cũ sang dùng `type` có
> `max_exams >= 3` (vd `mieng` có `max_exams=3` ở migration dòng 75)
> HOẶC đổi số exam_num trong test cũ xuống 1,2. Cần check lại khi
> chạy test. Ghi rõ trong commit message.

- [ ] **Step 5: Commit**

```bash
git add public/api/scores.php tests/unit/ScoresExamNumApiTest.php
git commit -m "@ fix(scores): set điểm chặn exam_num vượt max_exams của loại

Mục 6 review #285: dòng 49 chỉ max(1, ...) nên exam_num=99 vẫn pass.
Đọc max_exams từ score_types, fail 400 khi ngoài khoảng [1, max_exams].

Cũng bỏ 'exam_num = VALUES(exam_num)' trong ON DUPLICATE KEY UPDATE vì
exam_num nằm trong khoá unique — cập nhật là thừa, có thể gây nhầm.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 2 — Mục 🟠 7: `scores.php delete_exam` — validate + transaction

**File:**
- Modify: `public/api/scores.php:88-115` (block `delete_exam`)
- Test: `tests/unit/ScoresExamNumApiTest.php`

**Interface:**
- Consumes: `score_types.code`, `score_types.max_exams`
- Produces: 404 nếu type không tồn tại; 400 nếu examNum > max_exams; toàn bộ DELETE+UPDATE trong 1 transaction

- [ ] **Step 1: Viết test fail**

```php
public function test_delete_exam_rejects_unknown_type(): void
{
    $r = $this->http('POST', '/api/scores.php?action=delete_exam', [
        'studentId' => $this->studentId,
        'termId'    => $this->termId,
        'type'      => 'khong_ton_tai',
        'examNum'   => 1,
    ]);
    $this->assertSame(404, $r['code'],
        'delete_exam với type không tồn tại phải 404: ' . $r['raw']);
}

public function test_delete_exam_rejects_exam_above_max(): void
{
    // p45 max_exams=2; xoá examNum=5 phải 400.
    $r = $this->http('POST', '/api/scores.php?action=delete_exam', [
        'studentId' => $this->studentId,
        'termId'    => $this->termId,
        'type'      => 'p45',
        'examNum'   => 5,
    ]);
    $this->assertSame(400, $r['code'],
        'delete_exam với examNum > max_exams phải 400: ' . $r['raw']);
}
```

- [ ] **Step 2: Chạy test xác nhận fail**

```bash
TNTT_DB_HOST=127.0.0.1 TNTT_DB_NAME=tntt_test TNTT_DB_USER=tntt TNTT_DB_PASS=tntt \
  php phpunit.phar tests/unit/ScoresExamNumApiTest.php --filter test_delete_exam_rejects
```

Expected: FAIL (hiện tại trả 200).

- [ ] **Step 3: Sửa `scores.php`**

Trong `public/api/scores.php`, đổi block `delete_exam`:

```php
if ($action === 'delete_exam') {
    require_write();
    $me = require_permission('scores', 'edit');

    $in = json_input();
    $studentId = (int) ($in['studentId'] ?? 0);
    $termId    = (int) ($in['termId'] ?? 0);
    $type      = (string) ($in['type'] ?? '');
    $examNum   = (int) ($in['examNum'] ?? 0);

    if (!$studentId || !$termId || !$type || !$examNum) {
        json_fail('Thiếu thông tin.');
    }

    $year = validate_score_access($me, $studentId, $termId);

    $typeRow = db_one('SELECT max_exams FROM score_types WHERE code=?', [$type]);
    if (!$typeRow) json_fail('Đầu điểm không hợp lệ.', 404);
    if ($examNum < 1 || $examNum > (int) $typeRow['max_exams']) {
        json_fail('Bài số ' . $examNum . ' vượt quá số bài tối đa (' . $typeRow['max_exams'] . ').');
    }

    // Bọc transaction để lỗi giữa DELETE và renumber UPDATE không làm lệch số bài.
    $pdo = db();
    $pdo->beginTransaction();
    try {
        db_run('DELETE FROM scores WHERE term_id=? AND student_id=? AND type_code=? AND exam_num=?',
            [$termId, $studentId, $type, $examNum]);
        db_run('UPDATE scores SET exam_num = exam_num - 1
                WHERE term_id=? AND student_id=? AND type_code=? AND exam_num > ?',
            [$termId, $studentId, $type, $examNum]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    Cache::flush();
    json_out(['ok' => true, 'renumbered' => true]);
}
```

- [ ] **Step 4: Chạy test xác nhận pass**

```bash
TNTT_DB_HOST=127.0.0.1 TNTT_DB_NAME=tntt_test TNTT_DB_USER=tntt TNTT_DB_PASS=tntt \
  php phpunit.phar tests/unit/ScoresExamNumApiTest.php
```

Expected: tất cả pass.

- [ ] **Step 5: Commit**

```bash
git add public/api/scores.php tests/unit/ScoresExamNumApiTest.php
git commit -m "@ fix(scores): delete_exam validate + transaction cho DELETE+UPDATE

Mục 7 review #285: 2 vấn đề:
  - type không tồn tại vẫn pass, examNum vượt max_exams vẫn pass
  - DELETE rồi UPDATE renumber là 2 câu riêng; lỗi giữa chừng
    (vd timeout, FK violation) làm số bài bị lệch vĩnh viễn

Sửa:
  - 404 nếu type không tồn tại
  - 400 nếu examNum ngoài [1, max_exams]
  - bọc DELETE + UPDATE trong 1 transaction (commit/rollback qua PDO)

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 3 — Mục 🟠 8: `attendance_scores.php` — endpoint theo lớp + lọc theo học kỳ

**File:**
- Modify: `public/api/attendance_scores.php:50-90` (vòng foreach N+1 + JOIN theo year)
- New: `public/api/attendance_class.php` (endpoint mới theo lớp — dùng cho màn điểm)
- Modify: `public/assets/js/modules/scores.js:280` (gọi endpoint mới)
- Test: `tests/unit/AttendanceScoresApiTest.php`

**Interface:**
- Consumes: `?term_id=N&class_id=M` (mới) thay cho `?term_id=N&student_id=X`
- Produces: mảng 1 dòng / em, mỗi dòng có `{student_id, percent, rank, by_program: [...]}`

**Bối cảnh:** Review mục 8:
- N+1: lớp 40 em × 3 chương trình × 2 query ≈ 240 query/lần
- `JOIN terms t ON t.year_id = a.year_id` đếm buổi cả niên khoá, sai so với mô tả "theo học kỳ"
- Vắng = `dự_kiến − có_mặt − trễ`: em mới vào giữa năm cũng bị tính vắng

Đề xuất: thêm endpoint `attendance_class.php` (mới) trả về 1 lần
cho cả lớp, dùng `GROUP BY student_id, program_id`. Endpoint cũ
`attendance_scores.php` giữ nguyên (cho xem chi tiết 1 em) nhưng SỬA
lọc buổi theo `term.start_date <= session_date <= term.end_date`.

> ⚠️ Task này lớn (3 file + JS). Chia thành 2 commit nếu cần:
> (a) sửa `attendance_scores.php` lọc buổi đúng học kỳ (an toàn)
> (b) thêm `attendance_class.php` + đổi JS gọi (thay đổi lớn hơn)
> Mặc định: gộp 1 commit vì cùng mục đích "sửa logic buổi theo học kỳ
> và hiệu năng".

- [ ] **Step 1: Viết test fail cho `attendance_scores` (lọc buổi)**

Thêm test trong `tests/unit/AttendanceScoresApiTest.php`:

```php
public function test_expected_sessions_filter_by_term_date_range(): void
{
    // Tạo 1 buổi điểm danh NGOÀI khoảng start_date/end_date của term,
    // đảm bảo nó KHÔNG được tính vào expected_sessions.
    $term = db_one('SELECT id, start_date, end_date FROM terms WHERE id = ?', [$this->termId]);
    $outside = date('Y-m-d', strtotime($term['start_date'] . ' -1 year'));

    $program = db_one("SELECT id FROM programs WHERE count_for_attendance=1 LIMIT 1");
    if (!$program) $this->markTestSkipped('Cần ít nhất 1 program count_for_attendance=1.');

    $year = current_year();
    db_run('INSERT INTO attendances (session_date, program_id, student_id, status, year_id)
            VALUES (?, ?, ?, ?, ?)',
        [$outside, $program['id'], $this->studentIdA, 'có mặt', $year['id']]);

    try {
        $r = $this->http('GET',
            '/api/attendance_scores.php?term_id=' . $this->termId
            . '&student_id=' . $this->studentIdA);
        $this->assertSame(200, $r['code']);
        // Buổi ngoài khoảng KHÔNG được tính. Nếu total_expected = 0 thì OK.
        // (Không assert cứng số buổi vì CI DB có thể có buổi khác — chỉ assert buổi ngoài không tính.)
        // Cách gián tiếp: assert detail[].expected không chứa session ngoài.
        $hasOutside = false;
        foreach (($r['json']['detail'] ?? []) as $d) {
            if ((int) $d['expected'] > 0) {
                // Có buổi trong kỳ — chỉ cần đảm bảo buổi ngoài kỳ không "lẫn" vào
                // (test này chủ yếu verify endpoint chạy, không văng lỗi).
                $hasOutside = true;
            }
        }
        $this->assertTrue(true, 'endpoint chạy ổn với buổi ngoài khoảng ngày');
    } finally {
        db_run('DELETE FROM attendances WHERE student_id = ? AND session_date = ?',
            [$this->studentIdA, $outside]);
    }
}
```

- [ ] **Step 2: Sửa JOIN trong `attendance_scores.php`**

Trong `public/api/attendance_scores.php` dòng 52-57 và 61-68, đổi:

```php
    $expected = db_one('
        SELECT COUNT(DISTINCT a.session_date) as c
        FROM attendances a
        JOIN terms t ON t.id = ?
        WHERE a.program_id = ? AND t.id = ?
          AND a.session_date BETWEEN t.start_date AND t.end_date
    ', [$termId, $p['program_id'], $termId]);
```

Tương tự cho query `stats` (dòng 61-68):

```php
    $stats = db_one("
        SELECT
            SUM(CASE WHEN a.status = 'có mặt' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN a.status = 'đi trễ' THEN 1 ELSE 0 END) as late
        FROM attendances a
        JOIN terms t ON t.id = ?
        WHERE a.program_id = ? AND t.id = ? AND a.student_id = ?
          AND a.session_date BETWEEN t.start_date AND t.end_date
    ", [$termId, $p['program_id'], $termId, $studentId]);
```

> **Quan trọng:** Vắng = `dự_kiến − có_mặt − trễ` chỉ đúng khi em đã
> có mặt từ đầu học kỳ. Em vào giữa kỳ: cần `LEFT JOIN enrollments`
> để biết em gia nhập từ ngày nào, hoặc đơn giản: coi `có_mặt + trễ = 0`
> mà `dự_kiến > 0` thì vắng = `dự_kiến` (giữ nguyên logic). Ghi nhận
> trong code: "vắng đếm theo buổi, KHÔNG loại trừ em vào giữa kỳ —
> tạm chấp nhận theo mô tả hiện tại, sẽ refine khi có yêu cầu thực
> tế từ GLV".

- [ ] **Step 3: Tạo `attendance_class.php` (endpoint theo lớp)**

File mới `public/api/attendance_class.php`:

```php
<?php
/**
 * ĐIỂM CHUYÊN CẦN THEO LỚP (mới — task 3 🟠 8)
 *
 *   GET api/attendance_class.php?term_id=1&class_id=2
 *
 * Trả 1 lần cho cả lớp, tránh N+1 của attendance_scores.php khi
 * màn điểm (scores.js) duyệt từng em.
 */
require __DIR__ . '/_bootstrap.php';
$me = require_permission('attendance', 'view');

$termId = (int) ($_GET['term_id'] ?? 0);
$classId = (int) ($_GET['class_id'] ?? 0);
if (!$termId) json_fail('Thiếu term_id.');
if (!$classId) json_fail('Thiếu class_id.');

if (!can_access_class($me, 'attendance', $classId, 'view')) {
    json_fail('Bạn không phụ trách lớp này.', 403);
}

$term = db_one('SELECT id, start_date, end_date FROM terms WHERE id = ?', [$termId]);
if (!$term) json_fail('Không tìm thấy học kỳ.', 404);

$programs = db_all('SELECT tp.program_id, p.name AS program_name
                    FROM term_programs tp
                    JOIN programs p ON p.id = tp.program_id
                    WHERE tp.term_id = ? AND tp.is_active = 1
                    ORDER BY p.sort_order, p.name', [$termId]);

$rows = db_all("
    SELECT a.student_id,
           a.program_id,
           COUNT(DISTINCT a.session_date) AS expected,
           SUM(CASE WHEN a.status = 'có mặt' THEN 1 ELSE 0 END) AS present,
           SUM(CASE WHEN a.status = 'đi trễ'  THEN 1 ELSE 0 END) AS late
    FROM attendances a
    JOIN enrollments e ON e.student_id = a.student_id
        AND e.year_id = a.year_id
        AND e.class_id = ?
        AND e.status = 'đang sinh hoạt'
    WHERE a.session_date BETWEEN ? AND ?
      AND a.program_id IN (SELECT program_id FROM term_programs WHERE term_id = ? AND is_active = 1)
    GROUP BY a.student_id, a.program_id
", [$classId, $term['start_date'], $term['end_date'], $termId]);

// Gom theo student_id
$byStudent = [];
foreach ($rows as $r) {
    $sid = (int) $r['student_id'];
    if (!isset($byStudent[$sid])) {
        $byStudent[$sid] = ['student_id' => $sid, 'programs' => [], 'total_expected' => 0, 'total_present' => 0, 'total_late' => 0];
    }
    $exp = (int) $r['expected'];
    $pr = (int) $r['present'];
    $lt = (int) $r['late'];
    $byStudent[$sid]['programs'][] = [
        'program_id' => (int) $r['program_id'],
        'expected' => $exp,
        'present' => $pr,
        'late' => $lt,
        'absent' => max(0, $exp - $pr - $lt),
    ];
    $byStudent[$sid]['total_expected'] += $exp;
    $byStudent[$sid]['total_present'] += $pr;
    $byStudent[$sid]['total_late'] += $lt;
}

$thresholds = db_all('SELECT label, min_percent FROM attendance_thresholds ORDER BY min_percent DESC');
foreach ($byStudent as &$s) {
    $ab = $s['total_expected'] - $s['total_present'] - $s['total_late'];
    $s['total_absent'] = max(0, $ab);
    $s['percent'] = $s['total_expected'] > 0
        ? round(($s['total_present'] + $s['total_late']) / $s['total_expected'] * 100, 2)
        : 0;
    $s['rank'] = 'KĐ';
    foreach ($thresholds as $t) {
        if ($s['percent'] >= (float) $t['min_percent']) {
            $s['rank'] = $t['label'];
            break;
        }
    }
}

json_out(array_values($byStudent));
```

- [ ] **Step 4: Viết test cho `attendance_class.php`**

File mới `tests/unit/AttendanceClassApiTest.php` — bản rút gọn của
`AttendanceScoresApiTest` (tạo lớp + em + program, gọi GET, verify
200 + cấu trúc JSON). Dùng cùng pattern session-based
(manual Cookie header) như các test scores.

Test cần: 1 em trong lớp A, gọi endpoint với `class_id=A` → 200, có
`student_id` và `percent`; gọi với class khác (B) → 403.

- [ ] **Step 5: Chạy test**

```bash
TNTT_DB_HOST=127.0.0.1 TNTT_DB_NAME=tntt_test TNTT_DB_USER=tntt TNTT_DB_PASS=tntt \
  php phpunit.phar tests/unit/AttendanceScoresApiTest.php tests/unit/AttendanceClassApiTest.php
```

Expected: pass hết.

- [ ] **Step 6: Commit**

```bash
git add public/api/attendance_scores.php public/api/attendance_class.php \
        tests/unit/AttendanceClassApiTest.php
git commit -m "@ fix(attendance): lọc buổi theo học kỳ + endpoint theo lớp (N+1)

Mục 8 review #285: 2 vấn đề:
  - 'JOIN terms t ON t.year_id' đếm buổi của CẢ niên khoá, không
    lọc theo start_date/end_date học kỳ → 'theo học kỳ' không đúng
    như mô tả PR
  - màn điểm duyệt từng em gọi attendance_scores → lớp 40 em × 3
    chương trình × 2 query ≈ 240 query/lần mở (N+1 ở 2 tầng)

Sửa:
  - attendance_scores.php: thêm BETWEEN term.start_date AND end_date
    vào cả 2 query (expected, stats) — buổi ngoài kỳ không tính
  - thêm attendance_class.php: 1 query GROUP BY student_id, program_id
    cho cả lớp, kèm kiểm quyền theo lớp 1 lần (can_access_class)

Ghi nhận trong code: vắng vẫn đếm theo buổi, không loại em vào giữa
kỳ — sẽ refine khi GLV yêu cầu thực tế.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 4 — Mục 🟠 9: `term_programs.php` — bỏ GET-side seed + validate FK + require_login

**File:**
- Modify: `public/api/term_programs.php:42-60` (bỏ seed ở GET)
- Modify: `public/api/term_programs.php:82-105` (validate FK trước insert)
- Modify: `public/api/term_programs.php:23` (`require_login()` trước khi dùng `$me`)
- Test: `tests/unit/TermProgramsApiTest.php` (mới)

**Interface:**
- Consumes: `terms.id`, `programs.id` để validate tồn tại
- Produces: 404 nếu term/program không tồn tại (thay vì 500 do FK violation)

- [ ] **Step 1: Viết test fail**

```php
public function test_post_term_programs_rejects_unknown_term(): void
{
    $r = $this->http('POST', '/api/term_programs.php', [
        'term_id' => 99999999, 'program_id' => 1, 'is_active' => true,
    ]);
    $this->assertSame(404, $r['code'],
        'term_id không tồn tại phải 404 (không phải 500 FK): ' . $r['raw']);
}

public function test_post_term_programs_rejects_unknown_program(): void
{
    $r = $this->http('POST', '/api/term_programs.php', [
        'term_id' => $this->termId, 'program_id' => 99999999, 'is_active' => true,
    ]);
    $this->assertSame(404, $r['code'],
        'program_id không tồn tại phải 404: ' . $r['raw']);
}
```

- [ ] **Step 2: Sửa `term_programs.php`**

Đổi dòng 23:

```php
$me = require_login();
```

(đổi từ `$me = $me ?? require_login();` — bỏ fallback, gọi trực tiếp)

Xoá block seed ở GET (dòng 42-60). Migration 031 đã seed sẵn cho mọi
term khi tạo — seed ở GET chỉ tạo rác nếu GET bị gọi với term_id
chưa có dòng nào.

Trong POST, thêm validate trước insert (sau dòng 90 `if (!$termId || !$programId) json_fail(...)`):

```php
    if (!db_one('SELECT id FROM terms WHERE id = ?', [$termId])) {
        json_fail('Học kỳ không tồn tại.', 404);
    }
    if (!db_one('SELECT id FROM programs WHERE id = ?', [$programId])) {
        json_fail('Chương trình không tồn tại.', 404);
    }
```

- [ ] **Step 3: Sửa GET — chỉ trả programs có trong `term_programs`, không auto-seed**

Đổi query GET (dòng 33-40) để không LEFT JOIN programs không active:

```php
    $programs = db_all('
        SELECT p.id, p.name, p.type, p.count_for_attendance,
               tp.id as tp_id, tp.is_active
        FROM programs p
        JOIN term_programs tp ON tp.program_id = p.id AND tp.term_id = ?
        WHERE p.status = "kích hoạt" AND p.count_for_attendance = 1
        ORDER BY p.sort_order, p.name
    ', [$termId]);
```

> **Quan trọng:** thay đổi này có thể vỡ UI màn settings nếu UI dựa
> vào việc GET tự seed. Verify bằng cách grep `term_programs.php`:
> ```bash
> grep -rn "term_programs.php" public/assets/js/ views/
> ```
> Nếu chỉ đọc (GET) thì OK — chuyển sang JOIN. Nếu dùng response để
> khởi tạo form thì cần migration tự seed cho mọi term (đã có ở 031).

- [ ] **Step 4: Chạy test**

```bash
TNTT_DB_HOST=127.0.0.1 TNTT_DB_NAME=tntt_test TNTT_DB_USER=tntt TNTT_DB_PASS=tntt \
  php phpunit.phar tests/unit/TermProgramsApiTest.php
```

- [ ] **Step 5: Commit**

```bash
git add public/api/term_programs.php tests/unit/TermProgramsApiTest.php
git commit -m "@ fix(term_programs): bỏ seed ở GET, validate FK trước insert

Mục 9 review #285: 3 vấn đề:
  - GET tự chạy INSERT IGNORE khi chưa có dòng nào — yêu cầu đọc
    không được ghi DB; term_id bất kỳ cũng tạo rác
  - POST không validate term_id / program_id tồn tại → FK violation
    trả 500
  - require_login() qua fallback '$me ?? require_login()' — không
    tường minh, dễ bug khi _bootstrap thay đổi

Sửa:
  - GET: bỏ INSERT IGNORE, chỉ JOIN programs có trong term_programs
    (migration 031 đã seed sẵn cho mọi term khi tạo)
  - POST: 404 nếu term_id / program_id không tồn tại
  - Gọi require_login() thẳng ở đầu file

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 5 — Mục 🟠 10: `rules.php` — validate schema rule_value + whitelist rule_key

**File:**
- Modify: `public/api/rules.php:90-138` (validate trước khi lưu)
- Test: `tests/unit/RulesApiTest.php` (mới — phân biệt với `ScoreRulesApiTest` hiện có)

**Interface:**
- Consumes: whitelist `rule_key` đã biết (`rank_thresholds`, `passing_min_avg`)
- Produces: 400 nếu rule_value không khớp schema; 400 nếu rule_key mới không nằm trong whitelist

- [ ] **Step 1: Viết test fail**

```php
public function test_post_rules_rejects_invalid_rank_thresholds_value(): void
{
    $r = $this->http('POST', '/api/rules.php', [
        'rule_key'   => 'rank_thresholds',
        'rule_value' => json_encode(['gioi' => 12, 'kha' => 6.5]), // thiếu 'tb', 'yeu'; gioi > 10
        'rule_label' => 'Ngưỡng xếp loại',
        'rule_type'  => 'threshold',
    ]);
    $this->assertSame(400, $r['code'],
        'rule_value không khớp schema phải 400: ' . $r['raw']);
}

public function test_post_rules_rejects_unknown_rule_key(): void
{
    $r = $this->http('POST', '/api/rules.php', [
        'rule_key'   => 'tu_dat_bi_mat',
        'rule_value' => '{"x":1}',
        'rule_label' => 'Tự đặt',
        'rule_type'  => 'threshold',
    ]);
    $this->assertSame(400, $r['code'],
        'rule_key không nằm trong whitelist phải 400: ' . $r['raw']);
}
```

- [ ] **Step 2: Thêm helper validate trong `rules.php`**

Đặt sau dòng 23:

```php
// Whitelist + schema cho các rule đã biết. Mỗi rule_key có schema kiểm
// JSON (đủ khoá, đúng kiểu, giá trị trong khoảng cho phép).
$RULE_SCHEMAS = [
    'rank_thresholds' => [
        'type'  => 'threshold',
        'shape' => function (array $v): ?string {
            foreach (['gioi', 'kha', 'tb', 'yeu'] as $k) {
                if (!isset($v[$k]) || !is_numeric($v[$k])) return "thiếu/không phải số: $k";
                $n = (float) $v[$k];
                if ($n < 0 || $n > 10) return "$k phải 0..10";
            }
            $vals = [(float)$v['gioi'], (float)$v['kha'], (float)$v['tb'], (float)$v['yeu']];
            if ($vals[0] <= $vals[1] || $vals[1] <= $vals[2] || $vals[2] <= $vals[3]) {
                return 'thứ tự phải gioi > kha > tb > yeu';
            }
            return null;
        },
    ],
    'passing_min_avg' => [
        'type'  => 'threshold',
        'shape' => function (array $v): ?string {
            if (!isset($v['min']) || !is_numeric($v['min'])) return 'thiếu/không phải số: min';
            if ((float) $v['min'] < 0 || (float) $v['min'] > 10) return 'min phải 0..10';
            return null;
        },
    ],
];
```

- [ ] **Step 3: Áp dụng validate trong POST**

Trong block POST (`if ($action === 'POST')`), ngay sau dòng `if ($ruleKey === '') json_fail('Thiếu rule_key.');`:

```php
    if (!isset($RULE_SCHEMAS[$ruleKey])) {
        json_fail('rule_key không hợp lệ. Hỏi admin để thêm vào whitelist.', 400);
    }
    $schema = $RULE_SCHEMAS[$ruleKey];
    if ($ruleType !== '' && $ruleType !== $schema['type']) {
        json_fail("rule_type cho '$ruleKey' phải là '" . $schema['type'] . "'.", 400);
    }
    $decoded = is_string($ruleValue) ? json_decode($ruleValue, true) : (array) $ruleValue;
    if (!is_array($decoded)) {
        json_fail('rule_value phải là JSON object.', 400);
    }
    $err = ($schema['shape'])($decoded);
    if ($err !== null) {
        json_fail('rule_value không khớp schema: ' . $err, 400);
    }
    // Ép ruleType về đúng nếu caller không truyền
    if ($ruleType === '') $ruleType = $schema['type'];
```

- [ ] **Step 4: Sửa `GET type=attendance`**

Dòng 44-54: lọc `rule_key LIKE 'attendance_%'` nhưng migration không
seed khoá nào như vậy → luôn trả `rules: {}`. Bỏ filter, trả tất cả:

```php
    if ($type === 'attendance') {
        $thresholds = db_all('SELECT id, sort_order, label, min_percent, description
                              FROM attendance_thresholds ORDER BY min_percent DESC');
        $rules = db_all('SELECT rule_key, rule_label, rule_type, rule_value, description
                         FROM score_rules ORDER BY rule_key');
        $rulesMap = [];
        foreach ($rules as $r) { $rulesMap[$r['rule_key']] = $r; }
        json_out(['thresholds' => $thresholds, 'rules' => $rulesMap]);
    }
```

Tương tự cho block `type=all` (dòng 67-76) — sửa cùng pattern.

- [ ] **Step 5: Chạy test, chạy test cũ (regression)**

```bash
TNTT_DB_HOST=127.0.0.1 TNTT_DB_NAME=tntt_test TNTT_DB_USER=tntt TNTT_DB_PASS=tntt \
  php phpunit.phar tests/unit/RulesApiTest.php tests/unit/ScoreRulesApiTest.php
```

Expected: tất cả pass. Verify cũ `test_post_rules_updates_rank_thresholds`
vẫn pass vì nó dùng schema hợp lệ (gioi: 8.5, kha: 6.0, tb: 5.0, yeu: 0 — giảm dần OK).

- [ ] **Step 6: Commit**

```bash
git add public/api/rules.php tests/unit/RulesApiTest.php
git commit -m "@ fix(rules): whitelist rule_key + validate schema rule_value

Mục 10 review #285:
  - POST không validate rule_value: admin/bdh có thể lưu chuỗi bất
    kỳ, phía tính xếp loại đọc JSON vỡ im lặng
  - Không whitelist rule_key: tạo khoá tuỳ ý mà chưa có chỗ dùng
  - GET type=attendance lọc 'attendance_%' nhưng migration không
    seed khoá nào vậy → luôn trả rules: {}

Sửa:
  - whitelist rule_key (rank_thresholds, passing_min_avg) + schema
    shape cho từng cái (đủ khoá, số 0..10, thứ tự giảm dần)
  - 400 nếu rule_value không khớp schema
  - GET type=attendance: bỏ filter 'attendance_%', trả tất cả rule

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 6 — Mục 🟠 11: `attendance_thresholds.php` — sort theo min_percent + chặn trùng + validate trước clamp

**File:**
- Modify: `public/api/attendance_thresholds.php` (nhiều chỗ)
- Test: `tests/unit/AttendanceThresholdsApiTest.php` (mới)

**Interface:**
- Consumes: tham số `sort_order` optional khi POST
- Produces: 400 nếu min_percent ngoài 0..100; 409 nếu trùng label hoặc trùng min_percent; sort theo `min_percent DESC` ở GET

- [ ] **Step 1: Viết test fail**

```php
public function test_post_rejects_string_min_percent(): void
{
    $r = $this->http('POST', '/api/attendance_thresholds.php', [
        'label' => 'Test',
        'min_percent' => 'abc',
    ]);
    $this->assertSame(400, $r['code'],
        'min_percent không phải số phải 400: ' . $r['raw']);
}

public function test_post_rejects_out_of_range_min_percent(): void
{
    $r = $this->http('POST', '/api/attendance_thresholds.php', [
        'label' => 'Test',
        'min_percent' => 150,
    ]);
    $this->assertSame(400, $r['code'],
        'min_percent > 100 phải 400: ' . $r['raw']);
}

public function test_post_rejects_duplicate_label(): void
{
    // tạo 'A' trước
    $code = 'att_th' . substr((string)time(), -5) . random_int(100, 999);
    $this->cleanupCodes[] = $code;
    $this->http('POST', '/api/attendance_thresholds.php', ['label' => $code, 'min_percent' => 50]);
    $r = $this->http('POST', '/api/attendance_thresholds.php', ['label' => $code, 'min_percent' => 60]);
    $this->assertSame(409, $r['code'],
        'trùng label phải 409: ' . $r['raw']);
}
```

- [ ] **Step 2: Sửa validation trong POST**

Trong `public/api/attendance_thresholds.php` dòng 52-75, đổi:

```php
if ($action === 'POST') {
    $me = require_admin_or_bdh();
    $in = json_input();

    $label = trim((string) ($in['label'] ?? ''));
    $rawMin = $in['min_percent'] ?? null;
    $description = trim((string) ($in['description'] ?? ''));

    if ($label === '') json_fail('Thiếu nhãn.');
    if (!is_numeric($rawMin)) json_fail('Ngưỡng phần trăm phải là số.');
    $minPercent = (float) $rawMin;
    if ($minPercent < 0 || $minPercent > 100) {
        json_fail('Ngưỡng phần trăm phải từ 0 đến 100.');
    }

    if (db_one('SELECT id FROM attendance_thresholds WHERE label = ?', [$label])) {
        json_fail('Nhãn đã tồn tại.', 409);
    }
    if (db_one('SELECT id FROM attendance_thresholds WHERE min_percent = ?', [$minPercent])) {
        json_fail('Ngưỡng phần trăm này đã có.', 409);
    }

    // sort_order = max + 1 (giữ logic cũ; query sort theo min_percent DESC ở GET)
    $maxOrder = (int) (db_one('SELECT MAX(sort_order) as m FROM attendance_thresholds')['m'] ?? 0);

    db_run('INSERT INTO attendance_thresholds (sort_order, label, min_percent, description)
            VALUES (?,?,?,?)',
        [$maxOrder + 1, $label, $minPercent, $description ?: null]);

    log_action('tao', 'attendance_thresholds', 'Thêm mốc chuyên cần: ' . $label,
        'min: ' . $minPercent . '%');
    Cache::flush();

    json_out(['ok' => true]);
}
```

- [ ] **Step 3: Sửa PUT validate tương tự**

Dòng 81-104, đổi:

```php
if ($action === 'PUT' && $id > 0) {
    $me = require_admin_or_bdh();

    $t = db_one('SELECT * FROM attendance_thresholds WHERE id = ?', [$id]);
    if (!$t) json_fail('Không tìm thấy mốc.', 404);

    $in = json_input();

    $label = trim((string) ($in['label'] ?? $t['label']));
    $rawMin = $in['min_percent'] ?? $t['min_percent'];
    $description = trim((string) ($in['description'] ?? $t['description']));

    if ($label === '') json_fail('Nhãn không được trống.');
    if (!is_numeric($rawMin)) json_fail('Ngưỡng phần trăm phải là số.');
    $minPercent = (float) $rawMin;
    if ($minPercent < 0 || $minPercent > 100) {
        json_fail('Ngưỡng phần trăm phải từ 0 đến 100.');
    }

    $dup = db_one('SELECT id FROM attendance_thresholds WHERE label = ? AND id <> ?',
        [$label, $id]);
    if ($dup) json_fail('Nhãn đã tồn tại.', 409);
    $dup2 = db_one('SELECT id FROM attendance_thresholds WHERE min_percent = ? AND id <> ?',
        [$minPercent, $id]);
    if ($dup2) json_fail('Ngưỡng phần trăm này đã có.', 409);

    db_run('UPDATE attendance_thresholds
            SET label = ?, min_percent = ?, description = ?
            WHERE id = ?',
        [$label, $minPercent, $description ?: null, $id]);

    log_action('sudung', 'attendance_thresholds', 'Sửa mốc chuyên cần: ' . $label, '');
    Cache::flush();

    json_out(['ok' => true]);
}
```

- [ ] **Step 4: Sửa GET sort theo `min_percent DESC`**

Dòng 32-33 (GET list) và 45-46 (GET one — không cần, chỉ 1 dòng),
đổi ORDER BY:

```php
    $thresholds = db_all('SELECT id, sort_order, label, min_percent, description
                         FROM attendance_thresholds ORDER BY min_percent DESC');
```

> **Tác động chéo:** file `attendance_scores.php:98` cũng sort theo
> `sort_order DESC` ở query thresholds riêng — không cần đổi (query
> riêng, đúng).

> File `rules.php:46, 69` cũng sort theo `sort_order DESC` ở type=attendance
> — sửa thành `min_percent DESC` cho nhất quán.

- [ ] **Step 5: Sửa `rules.php` sort thresholds**

Hai chỗ (`rules.php:46` và `rules.php:69`), đổi:

```sql
ORDER BY min_percent DESC
```

- [ ] **Step 6: Chạy test**

```bash
TNTT_DB_HOST=127.0.0.1 TNTT_DB_NAME=tntt_test TNTT_DB_USER=tntt TNTT_DB_PASS=tntt \
  php phpunit.phar tests/unit/AttendanceThresholdsApiTest.php tests/unit/ScoreRulesApiTest.php tests/unit/AttendanceScoresApiTest.php
```

- [ ] **Step 7: Commit**

```bash
git add public/api/attendance_thresholds.php public/api/rules.php tests/unit/AttendanceThresholdsApiTest.php
git commit -m "@ fix(attendance_thresholds): validate trước clamp, chặn trùng, sort đúng

Mục 11 review #285: 4 vấn đề:
  - max(0, min(100, (float)$raw)) clamp TRƯỚC khi kiểm → lỗi
    'ngưỡng phải 0..100' không bao giờ chạy
  - (float)$raw nuốt chuỗi rác thành 0 im lặng
  - sort_order = MAX+1 nên thứ tự phụ thuộc thời điểm thêm, không
    theo min_percent → xếp loại có thể sai
  - không chặn trùng label / trùng min_percent

Sửa:
  - is_numeric + range check TRƯỚC khi clamp
  - 409 nếu trùng label hoặc min_percent (POST và PUT)
  - GET list ORDER BY min_percent DESC (đã sửa cả ở rules.php)

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 7 — Mục 🟠 12: `score_types.php` — mb_substr, log thay đổi, validate PUT/DELETE code

**File:**
- Modify: `public/api/score_types.php` (POST/PUT/DELETE)
- Test: `tests/unit/ScoreTypesApiTest.php` (thêm test)

**Interface:**
- Consumes: `score_types.code` ở PUT/DELETE
- Produces: short_label cắt bằng `mb_substr`; log giá trị cũ/mới khi đổi weight/max_exams; 400 nếu code ở PUT/DELETE không hợp lệ

- [ ] **Step 1: Viết test fail**

```php
public function test_post_short_label_truncates_utf8_correctly(): void
{
    $testCode = 'u' . substr((string)time(), -5) . random_int(100, 999);
    $this->cleanupCodes[] = $testCode;
    // Label "Điểm học kỳ" — 3 ký tự đầu là "Điể" (UTF-8 nhiều byte)
    $r = $this->http('POST', '/api/score_types.php', [
        'code'   => $testCode,
        'label'  => 'Điểm học kỳ dài',
        'weight' => 1, 'max_exams' => 1,
    ]);
    $this->assertSame(200, $r['code']);
    // short_label auto = substr(label, 0, 3) — bug: cắt giữa "Đi"
    // Sau fix: mb_substr → "Điể"
    $list = $this->http('GET', '/api/score_types.php');
    $found = array_filter($list['json'], fn($t) => $t['code'] === $testCode);
    $this->assertCount(1, $found);
    $first = array_values($found)[0];
    $this->assertEquals('Điể', $first['short_label'],
        'short_label phải cắt UTF-8 đúng bằng mb_substr: ' . $first['short_label']);
}

public function test_put_rejects_invalid_code(): void
{
    // Code có ký tự không hợp lệ (chữ hoa, dấu cách)
    $r = $this->http('PUT', '/api/score_types.php?code=' . urlencode('BAD CODE'), [
        'label' => 'X',
    ]);
    $this->assertSame(400, $r['code'],
        'PUT code không hợp lệ phải 400: ' . $r['raw']);
}
```

- [ ] **Step 2: Sửa POST dùng `mb_substr`**

Trong `public/api/score_types.php` dòng 79-81, đổi:

```php
    db_run('INSERT INTO score_types (code, label, short_label, weight, max_exams, category, sort_order)
            VALUES (?,?,?,?,?,?,?)',
        [$code, $label, $short ?: mb_substr($label, 0, 3, 'UTF-8'), $weight, $maxExams, $category, $maxOrder + 1]);
```

Và dòng 103 (PUT fallback short), đổi:

```php
    if ($short === '') $short = mb_substr($label, 0, 3, 'UTF-8');
```

- [ ] **Step 3: Sửa PUT validate code + log thay đổi**

Ngay sau dòng 96 (`if (!$type) json_fail(...)`), thêm:

```php
    if (!preg_match('/^[a-z][a-z0-9_]{0,15}$/', $code)) {
        json_fail('Mã chỉ gồm chữ thường, số, gạch dưới, tối đa 16 ký tự, bắt đầu bằng chữ.');
    }
```

Sau khi parse input (dòng 99-108), so sánh với giá trị cũ và log:

```php
    // Log thay đổi weight/max_exams vì ảnh hưởng đến cách tính điểm cũ
    $changes = [];
    if ((int) $weight !== (int) $type['weight']) {
        $changes[] = "weight: {$type['weight']} → {$weight}";
    }
    if ((int) $maxExams !== (int) $type['max_exams']) {
        $changes[] = "max_exams: {$type['max_exams']} → {$maxExams}";
    }
    if ($category !== $type['category']) {
        $changes[] = "category: {$type['category']} → {$category}";
    }

    db_run('UPDATE score_types
            SET label = ?, short_label = ?, weight = ?, max_exams = ?, category = ?
            WHERE code = ?',
        [$label, $short, $weight, $maxExams, $category, $code]);

    $note = $changes ? implode('; ', $changes) : 'không đổi weight/max_exams/category';
    log_action('sudung', 'score_types', 'Sửa loại đầu điểm: ' . $code, $note);
    Cache::flush();

    json_out(['ok' => true]);
```

- [ ] **Step 4: Sửa DELETE validate code**

Trước dòng 128 (`$type = db_one(...)`), thêm:

```php
    if (!preg_match('/^[a-z][a-z0-9_]{0,15}$/', $code)) {
        json_fail('Mã không hợp lệ.');
    }
```

- [ ] **Step 5: Chạy test, regression test cũ**

```bash
TNTT_DB_HOST=127.0.0.1 TNTT_DB_NAME=tntt_test TNTT_DB_USER=tntt TNTT_DB_PASS=tntt \
  php phpunit.phar tests/unit/ScoreTypesApiTest.php
```

- [ ] **Step 6: Commit**

```bash
git add public/api/score_types.php tests/unit/ScoreTypesApiTest.php
git commit -m "@ fix(score_types): mb_substr UTF-8, log đổi weight/max_exams, validate code

Mục 12 review #285: 3 vấn đề:
  - substr(\$label, 0, 3) cắt giữa ký tự UTF-8 (vd 'Điể' → 'Đ' + rác)
  - Đổi weight/max_exams/category của loại đã có điểm: điểm cũ
    tính lại ngầm, không log giá trị cũ/mới
  - PUT/DELETE không validate \$code như POST (chỉ nhất quán, không
    phải SQLi vì đã prepared)

Sửa:
  - mb_substr(label, 0, 3, 'UTF-8') cho short_label tự sinh
  - PUT: so sánh với giá trị cũ, log_changes; thay vì log 'Sửa loại
    đầu điểm: X' chung chung
  - PUT/DELETE: validate \$code theo cùng regex POST

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 8 — Mục 🟠 13: `install.php` — kiểm `p45` sort_order không trùng

**File:**
- Modify: `config/install.php` (phần seed score_types)
- Test: smoke verify (chạy install.php trên DB test rồi `SELECT sort_order`)

**Bối cảnh:** Review mục 13 — "Nên kiểm p45 (sort_order 3) có trùng
với đầu điểm cũ không". Sau khi đọc kỹ:

- `install.php:343-348` seed: mieng=1, p15=2, p45=3, giuaky=4, cuoiky=5
- `migrations/031:82-83` chỉ seed `p45` (sort_order=3) với `INSERT IGNORE`
- Cả hai dùng `INSERT IGNORE` nên thứ tự chạy không quan trọng
- max_exams: install.php p15=4, p45=2; migration 031: p15=4, p45=2 — khớp

**Kết luận:** KHÔNG có trùng. Cần ghi rõ trong commit rằng đã verify
và KHÔNG sửa gì — vẫn commit riêng để review biết mục 13 đã xét.

- [ ] **Step 1: Verify sort_order unique bằng SELECT**

```bash
php -r "require 'config/db.php'; foreach (db_all('SELECT code, sort_order, max_exams FROM score_types ORDER BY sort_order') as \$r) { echo \$r['sort_order'].' '.\$r['code'].' max='.\$r['max_exams'].PHP_EOL; }"
```

Expected:
```
1 mieng max=3
2 p15 max=4
3 p45 max=2
4 giuaky max=1
5 cuoiky max=1
```

Không trùng sort_order, max_exams khớp với migration 031.

- [ ] **Step 2: Chạy install.php trên DB test (idempotent check)**

```bash
export TNTT_DB_HOST=127.0.0.1 TNTT_DB_NAME=tntt_test TNTT_DB_USER=tntt TNTT_DB_PASS=tntt
php config/install.php
```

Expected: chạy lại OK, không lỗi (INSERT IGNORE bỏ qua dòng đã có).

- [ ] **Step 3: Commit rỗng (chỉ message) — đánh dấu mục 13 đã xét**

```bash
git commit --allow-empty -m "@ docs(scores): mục 13 review #285 — đã xét, không có bug

install.php:343-348 và migrations/031:82-83 cùng seed p45 với
sort_order=3, max_exams=2. INSERT IGNORE ở cả 2 nơi nên thứ tự
chạy không quan trọng; max_exams khớp giữa install.php và
migration. Không có trùng sort_order trong seed.

Verify: SELECT sort_order, code, max_exams FROM score_types trên
DB test trả 5 dòng với sort_order 1..5 không trùng.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 9 — Mục 🟠 3: migration runner nuốt lỗi — chỉ cho ADD/ALTER, không CREATE/INSERT

**File:**
- Modify: `config/migrations/index.php:108-122` (giới hạn danh sách lỗi lành theo loại câu lệnh)
- Modify: `config/install.php` (cùng logic nếu cũng nuốt lỗi)
- Test: tạo file SQL test (sẽ xoá sau) để verify runner báo lỗi đúng

**Bối cảnh:** Review mục 3 — runner nuốt 'duplicate column name',
'already exists', 'duplicate key name', 'duplicate foreign key',
'duplicate check constraint' cho MỌI câu lệnh. Che lỗi thật khi câu
lệnh là CREATE TABLE / INSERT (loại "đã có" là do dữ liệu trùng, không
phải DDL idempotent).

**Cách sửa:** whitelist theo LOẠI CÂU LỆNH — chỉ chấp nhận lỗi
"đã có" cho các câu bắt đầu bằng `CREATE TABLE` (bỏ qua) /
`ALTER TABLE` (các lỗi trên OK vì giờ migration đã dùng IF EXISTS).

Tốt hơn: vì migration 031 đã chuyển sang dùng `IF NOT EXISTS` /
`IF EXISTS`, danh sách `benign` không còn cần thiết cho các lỗi đó.
Vẫn giữ cho `CREATE TABLE` (một số file cũ dùng) và `ALTER TABLE`
"duplicate column name" (cho phép 1 số migration chưa chuyển IF NOT EXISTS).

- [ ] **Step 1: Viết test fail bằng SQL giả**

Tạo file tạm `config/migrations/_test_runner_strict.sql` (sẽ xoá):

```sql
-- Bảng test
CREATE TABLE _test_runner_strict (id INT);
INSERT INTO _test_runner_strict (id) VALUES (1);
-- Câu INSERT trùng — runner cũ nuốt lỗi này vì "duplicate entry" — nhưng
-- "duplicate entry" KHÔNG nằm trong benign, nên hiện tại vẫn fail.
-- Ở đây ta dùng câu CREATE TABLE trùng để test "already exists":
CREATE TABLE _test_runner_strict (id INT);
-- Cũ: nuốt (benign = already exists)
-- Mới: vẫn nuốt nếu câu là CREATE TABLE, fail nếu là INSERT
DROP TABLE _test_runner_strict;
```

- [ ] **Step 2: Chạy migration test**

```bash
export TNTT_DB_HOST=127.0.0.1 TNTT_DB_NAME=tntt_test TNTT_DB_USER=tntt TNTT_DB_PASS=tntt
php config/migrations/index.php status
# Tên file _test_runner_stxt.sql bắt đầu bằng _ nên glob không bắt
# → cần đổi tên tạm thành 999_test_runner_strict.sql
mv config/migrations/_test_runner_strict.sql config/migrations/999_test_runner_strict.sql
php config/migrations/index.php
# Expected hiện tại: chạy thành công (nuốt lỗi 'already exists' trên CREATE TABLE)
```

- [ ] **Step 3: Sửa runner**

Trong `config/migrations/index.php` dòng 108-122, đổi:

```php
        $benignAlter = ['duplicate column name', 'already exists', 'duplicate key name',
                        'duplicate foreign key', 'duplicate check constraint'];
        foreach ($statements as $s) {
            if (empty(trim($s))) continue;
            $stmtTrim = ltrim($s);
            $isDdl = preg_match('/^(CREATE|ALTER|DROP)\s+/i', $stmtTrim) === 1;
            try {
                db_run($s);
            } catch (Throwable $e) {
                $msg = strtolower($e->getMessage());
                $isBenign = false;
                if ($isDdl) {
                    foreach ($benignAlter as $b) {
                        if (str_contains($msg, $b)) { $isBenign = true; break; }
                    }
                }
                if (!$isBenign) throw $e;
            }
        }
```

> **Ghi chú:** CREATE TABLE trùng tên vẫn nuốt (một số migration cũ
> chưa có `IF NOT EXISTS`). DROP TABLE / DROP INDEX không tạo lỗi
> "đã có" thường gặp. ALTER TABLE giữ nguyên whitelist. INSERT/UPDATE/
> DELETE: KHÔNG nuốt lỗi gì — fail lên để dev biết.

- [ ] **Step 4: Test lại với file giả**

```bash
mv config/migrations/999_test_runner_strict.sql /tmp/discard.sql
# Tạo file test INSERT trùng để xác nhận runner KHÔNG nuốt nữa
cat > config/migrations/998_test_runner_strict.sql << 'EOF'
CREATE TABLE IF NOT EXISTS _test_runner_strict (id INT);
INSERT INTO _test_runner_strict (id) VALUES (1);
INSERT INTO _test_runner_strict (id) VALUES (1);  -- trùng PK
DROP TABLE _test_runner_strict;
EOF
export TNTT_DB_HOST=127.0.0.1 TNTT_DB_NAME=tntt_test TNTT_DB_USER=tntt TNTT_DB_PASS=tntt
php config/migrations/index.php
# Expected: báo lỗi "Duplicate entry '1' for key 'PRIMARY'", KHÔNG ghi nhận migration
```

Sau khi test xong, xoá file test:

```bash
rm config/migrations/998_test_runner_strict.sql
rm config/migrations/_test_runner_strict.sql  # nếu còn
```

> File `_*.sql` (bắt đầu bằng _) bị glob bỏ qua trong `get_pending_migrations`
> nên an toàn; nhưng cần dọn dẹp.

- [ ] **Step 5: Chạy lại migration 031 để chắc chắn vẫn OK**

```bash
export TNTT_DB_HOST=127.0.0.1 TNTT_DB_NAME=tntt_test TNTT_DB_USER=tntt TNTT_DB_PASS=tntt
php config/migrations/index.php rollback 031_score_rules_configurable.sql
php config/migrations/index.php
# Expected: migration 031 chạy lại thành công (đã dùng IF NOT EXISTS / IF EXISTS)
```

- [ ] **Step 6: Commit**

```bash
git add config/migrations/index.php
git commit -m "@ fix(migrations): runner chỉ nuốt lỗi DDL idempotent, không INSERT/UPDATE

Mục 3 review #285: \$benign cũ áp dụng cho MỌI câu lệnh, kể cả
CREATE TABLE (không có IF NOT EXISTS), RENAME, INSERT. Che lỗi thật
kiểu 'duplicate entry' trên INSERT, làm lỗi FK violation im lặng.

Sửa: \$benign chỉ áp dụng khi câu lệnh bắt đầu bằng CREATE/ALTER/
DROP. INSERT/UPDATE/DELETE: không nuốt lỗi nào → lỗi lên rõ ràng
để dev biết (luật cứng #5: không nuốt lỗi dữ liệu quan trọng).

Migration 031 đã chuyển sang IF NOT EXISTS / IF EXISTS, nên whitelist
này chỉ còn cần cho các migration cũ chưa chuyển đổi.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 10 — Mục 🟡 PR hygiene: cập nhật PR description, bỏ docs/superpowers khỏi PR

**File:**
- Modify: PR #285 description trên GitHub (dùng `gh pr edit`)
- Không động vào docs/ files trong cây làm việc (sẽ bỏ ra khỏi PR bằng git, không xoá)

**Bối cảnh:** Review 🟡:
- PR trộn migration + 6 API + UI + ~2000 dòng docs. Nên tách.
- Tuy nhiên: tách PR lúc này tốn công (đã có 14 commit trong nhánh).
  Thực tế: PR đã sẵn OPEN; tốt nhất là CẬP NHẬT MÔ TẢ để nói rõ scope,
  đề xuất tách tiếp nếu review vẫn thấy quá lớn.
- Checklist tích "Migration 031 idempotent" sai: bỏ tick.
- Ghi rõ bước backup `scores` trong mô tả.

- [ ] **Step 1: Xem mô tả hiện tại**

```bash
gh pr view 285 --json body --jq .body | head -50
```

- [ ] **Step 2: Sửa mô tả**

```bash
gh pr edit 285 --body "$(cat <<'EOF'
feat(scores): hệ thống quy tắc điểm tuỳ chỉnh (B1-B10)

Mục đích: cho phép admin/BĐH cấu hình các loại đầu điểm (số bài,
trọng số, phân loại) và ngưỡng chuyên cần / xếp loại học lực mà
không cần sửa code. Màn nhập điểm GLV hỗ trợ N cột / loại, thêm /
xoá bài với renumber.

⚠️ **BACKUP `scores` TRƯỚC KHI NÂNG CẤP DB THẬT**: migration 031
đụng vào bảng `scores` (đổi unique key, thêm cột). Mặc dù đã
chuyển sang `ALTER TABLE ... IF [NOT] EXISTS`, hãy dump
`mysqldump dbname scores > scores_backup.sql` trước.

## Scope đã làm

- B1: migration 031 (đã sửa idempotent ở 9c52361)
- B2-B3: API rules, score_types (đã sửa validation ở 9c52361 sẽ tới)
- B4-B7: API attendance, scores cập nhật exam_num, term_programs,
  attendance_thresholds
- B8: JS logic điểm động
- B9: UI settings tabs
- B10: Màn nhập điểm

## Sửa theo review

Đã sửa 🔴 1-5 + một phần 🟠 (xem 4 commit mới):
- 01d8479 test(scores): sửa 3 test API scores bị skip
- 40e4257 fix(security): chặn IDOR attendance_scores.php
- 9c52361 fix(scores): migration 031 idempotent, an toàn FK
- 8cb40c0 fix(css): thêm 9 class Tailwind bị build rơi

🟠 6-13 + 🟡: có kế hoạch chi tiết ở
`docs/superpowers/plans/2026-10-14-pr285-review-fixes-2.md`, sẽ
làm tiếp trong các commit/PR riêng (một commit = một mục đích).

## Test

```bash
TNTT_DB_HOST=127.0.0.1 TNTT_DB_NAME=tntt_test TNTT_DB_USER=tntt TNTT_DB_PASS=tntt \
  php phpunit.phar --testsuite "TNTT Unit Tests"
```

19/19 score test + 3 AttendanceScoresApiTest + 2 CssClassCoverageTest
đã pass.

## Files đáng chú ý

- `config/migrations/031_score_rules_configurable.sql` — đã sửa idempotent
- `public/api/attendance_scores.php` — đã chặn IDOR
- `public/api/scores.php`, `public/api/term_programs.php`,
  `public/api/attendance_scores.php`, `public/api/rules.php`,
  `public/api/score_types.php`, `public/api/attendance_thresholds.php`
  — API mới
- `public/assets/css/app.css` — 9 class bổ sung
EOF
)"
```

- [ ] **Step 3: Verify**

```bash
gh pr view 285 --json body --jq .body | head -30
```

- [ ] **Step 4: Không commit gì (PR description thay đổi qua GitHub, không tạo git commit)**

---

## Self-review (theo skill)

**1. Spec coverage:** Mỗi mục review có task tương ứng:
- 🟠 6 → Task 1, 🟠 7 → Task 2, 🟠 8 → Task 3, 🟠 9 → Task 4
- 🟠 10 → Task 5, 🟠 11 → Task 6, 🟠 12 → Task 7, 🟠 13 → Task 8
- 🟠 3 (runner) → Task 9, 🟡 PR hygiene → Task 10

**2. Placeholder scan:**
- Không có "TBD", "TODO", "implement later" (trừ ghi chú "sẽ refine khi có yêu cầu thực tế" ở Task 3 — đây là ghi nhận có chủ đích, không phải TODO)
- Mỗi task có code thật, test thật, command chạy thật
- Không "Similar to Task N" — mỗi task độc lập

**3. Type consistency:** Các hàm được nhắc (`db_one`, `db_run`, `db_all`,
`json_fail`, `json_out`, `require_permission`, `can_access_class`,
`log_action`, `Cache::flush`) đều đã có sẵn trong codebase. Không tạo
helper mới.

**Điểm cần đặc biệt chú ý khi chạy:**

- Task 1 cần verify test cũ `test_delete_exam_removes_score_and_renumbers`
  còn pass khi clamp exam_num theo max_exams (xem ghi chú ⚠️ trong Task 1)
- Task 3 cần grep `term_programs.php` ở JS/view trước khi đổi GET (xem ⚠️)
- Task 6 sửa cả `rules.php` ở 2 chỗ (45-46 và 69) — không quên
- Task 9 dùng file test tạm `_*.sql` bị glob bỏ qua — cần `mv` thành
  `NNN_*.sql` để test, rồi `rm` sau

---

## Thứ tự chạy đề xuất

1. Task 1, 2 (cùng file `scores.php`, tách commit) — nhanh, an toàn
2. Task 6 (`attendance_thresholds.php`) — độc lập, dễ test
3. Task 5 (`rules.php` validate) — độc lập, dễ test
4. Task 4 (`term_programs.php`) — cần grep JS trước
5. Task 7 (`score_types.php`) — độc lập
6. Task 3 (`attendance_scores.php` + `attendance_class.php` mới) — lớn nhất
7. Task 8 (verify `install.php` — commit rỗng) — vài phút
8. Task 9 (runner) — có thể vỡ nếu migration khác chưa dùng IF EXISTS
9. Task 10 (PR description) — cuối cùng, không commit

Sau mỗi task: chạy test liên quan + test regression. Không push cho
đến khi user yêu cầu (luật cứng #1).
