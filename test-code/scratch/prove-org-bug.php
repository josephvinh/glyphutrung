<?php
/**
 * Prove the org.php defect and that the working-tree fix resolves it.
 *
 * The committed code selects only (id, role_code, full_name) but the handler
 * then reads $m['status'] / $m['phone']. With those keys missing:
 *     $m['status'] !== 'chờ duyệt'   ->  null !== 'chờ duyệt'  ->  TRUE
 * so approveMember answers "Tài khoản này đã được duyệt rồi." for an account
 * that is in fact still pending, and rejectMember refuses for the same reason.
 *
 * Creates one throwaway pending row, tests both queries, then removes it.
 */
require __DIR__ . '/../config/db.php';

$pdo = db();
$PHONE = '0999000888';

// --- set up a pending row, exactly like api/auth.php register does ----------
$pdo->prepare('DELETE FROM members WHERE phone = ?')->execute([$PHONE]);
$max = db_one("SELECT COALESCE(MAX(CAST(SUBSTRING(code,4) AS UNSIGNED)),0) n FROM members WHERE code LIKE 'GLV%'");
$code = 'GLV' . str_pad((string) ($max['n'] + 1), 3, '0', STR_PAD_LEFT);
db_insert('INSERT INTO members (code, holy_name, full_name, phone, birth_date, password_hash,
                                role_code, status, must_change_pw, register_note, registered_at)
           VALUES (?,?,?,?,?,?,?,?,0,?,NOW())',
    [$code, 'Test', 'Pending Probe', $PHONE, '1995-01-01', password_hash('x', PASSWORD_DEFAULT),
     'glv', 'chờ duyệt', 'probe']);
$id = (int) db()->lastInsertId();
echo "created pending member id=$id code=$code phone=$PHONE\n\n";

// --- OLD (committed) query --------------------------------------------------
$old = db_one('SELECT id, role_code, full_name FROM members WHERE id=?', [$id]);
$oldHasStatus = array_key_exists('status', $old ?? []);
$oldVerdict = ($old['status'] ?? null) !== 'chờ duyệt';
echo "OLD query (as committed):\n";
echo "  columns returned : " . implode(', ', array_keys($old ?? [])) . "\n";
echo "  has 'status' key : " . var_export($oldHasStatus, true) . "\n";
echo "  approveMember guard  \$m['status'] !== 'chờ duyệt'  => " . var_export($oldVerdict, true)
   . ($oldVerdict ? "  -> REJECTS a pending account: \"Tài khoản này đã được duyệt rồi.\"\n" : "  -> would pass\n");

// --- NEW (working tree) query ----------------------------------------------
$new = db_one('SELECT id, role_code, full_name, status FROM members WHERE id=?', [$id]);
$newVerdict = ($new['status'] ?? null) !== 'chờ duyệt';
echo "\nNEW query (working tree fix):\n";
echo "  columns returned : " . implode(', ', array_keys($new ?? [])) . "\n";
echo "  status value     : " . var_export($new['status'] ?? null, true) . "\n";
echo "  approveMember guard => " . var_export($newVerdict, true)
   . ($newVerdict ? "  -> still rejects (BUG)\n" : "  -> passes, approval can proceed (FIXED)\n");

// --- cleanup ---------------------------------------------------------------
$pdo->prepare('DELETE FROM members WHERE phone IN (?, ?)')->execute([$PHONE, '0999000777']);
echo "\ncleanup: removed probe rows (0999000888, 0999000777)\n";
echo "members remaining: " . $pdo->query('SELECT COUNT(*) FROM members')->fetchColumn() . "\n";
echo "pending rows left: " . $pdo->query("SELECT COUNT(*) FROM members WHERE status='chờ duyệt'")->fetchColumn() . "\n";
