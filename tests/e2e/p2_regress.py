"""Hồi quy gói P2 (đổi schema): export.php (#79), xoá lớp/khối (#80), xoá preset QR (#104).
Chạy trên DB THỬ NGHIỆM: php -S 127.0.0.1:8088 -t public (TNTT_DB_* trỏ vào DB thử),
mysql root không mật khẩu, DB tntt_e2e. Cần e2e.py + e2e2.py (nhánh origin/audit, tests/e2e) đặt cạnh file này; cần migration 002 (qr_card_presets) nếu chạy thêm customqr.py.
"""
import os
HERE = os.path.dirname(os.path.abspath(__file__))
exec(open(os.path.join(HERE, 'e2e2.py'), encoding='utf-8').read().split('for role,ph in [("glv","0911000004"),("glv_chu_nhiem"')[0])
results.clear()
def show(r): return f"HTTP {r[0]} {r[2][:140]!r}"
def okj(r): return r[0] == 200 and isinstance(r[1], dict) and r[1].get('ok') is True

adm = logged("0901000001", "tntt@2026")
term = sql("SELECT t.id FROM terms t JOIN school_years y ON y.id=t.year_id WHERE y.is_current=1 ORDER BY t.id LIMIT 1")
sidA = sql(f"SELECT student_id FROM enrollments e JOIN school_years y ON y.id=e.year_id WHERE y.is_current=1 AND e.class_id={cA} LIMIT 1")

# ---- #79 export.php (admin)
r = adm.req("/api/export.php?action=attendance-detail")
rec("EXP-01", "attendance-detail (admin, mặc định) không 500", okj(r) and 'sheet' in r[1], show(r)[:120])
r = adm.req(f"/api/export.php?action=attendance-detail&classId={cA}&fromDate=2026-01-01&toDate=2026-12-31")
rec("EXP-02", "attendance-detail có lọc lớp/ngày", okj(r) and 'count' in r[1], show(r)[:120])
r = adm.req("/api/export.php?action=attendance", "POST", {})
rec("EXP-03", "attendance (ma trận) không ném 42S02/503", okj(r) and len(r[1]['sheet']['rows']) >= 1, show(r)[:120])
r = adm.req("/api/export.php?action=scores", "POST", {"classId": int(cA), "termId": int(term)})
rec("EXP-04", "scores có classId không HY093", okj(r) and len(r[1]['sheet']['rows']) > 1, show(r)[:120])
r = adm.req("/api/export.php?action=scores", "POST", {"termId": int(term)})
rec("EXP-05", "scores toàn đoàn (không classId)", okj(r), show(r)[:120])
r = adm.req("/api/export.php?action=report", "POST", {"studentId": int(sidA), "termId": int(term)})
rec("EXP-06", "report vẫn chạy", okj(r), show(r)[:120])

# ---- #79 phân quyền không bị nới lỏng
for role, ph in [("glv", "0911000004"), ("glv_chu_nhiem", "0911000003"), ("du_bi", "0911000005")]:
    c = logged(ph)
    r = c.req("/api/export.php?action=attendance-detail&classId=" + cB)
    rec("EXP-07", f"{role}: attendance-detail lớp ngoài phạm vi vẫn 403", r[0] == 403, show(r)[:100])
    r = c.req("/api/export.php?action=scores", "POST", {"classId": int(cB), "termId": int(term)})
    rec("EXP-08", f"{role}: scores lớp ngoài phạm vi vẫn 403", r[0] == 403, show(r)[:100])
    r = c.req("/api/export.php?action=attendance", "POST", {"classId": int(cB)})
    rec("EXP-09", f"{role}: attendance lớp ngoài phạm vi vẫn 403", r[0] == 403, show(r)[:100])
    r = c.req("/api/export.php?action=attendance-detail&classId=" + cA)
    rec("EXP-10", f"{role}: attendance-detail lớp của mình 200", r[0] == 200, show(r)[:100])

# ---- #80 xoá lớp còn ghi danh ở niên khoá CŨ vẫn bị chặn (không xoá dây chuyền lịch sử)
sql("DELETE FROM classes WHERE name='Lớp P2'"); sql("DELETE FROM school_years WHERE name='P2 cũ'")
adm.req("/api/org.php?action=saveClass", "POST", {"name": "Lớp P2", "block": sql(f"SELECT name FROM blocks WHERE id={bA}")})
cid = sql("SELECT id FROM classes WHERE name='Lớp P2'")
sql("INSERT INTO school_years (name,start_date,end_date,is_current) VALUES ('P2 cũ','2020-09-01','2021-06-30',0)")
yid = sql("SELECT id FROM school_years WHERE name='P2 cũ'")
sid = sql("SELECT id FROM students LIMIT 1 OFFSET 7")
sql(f"INSERT INTO enrollments (year_id,student_id,class_id) VALUES ({yid},{sid},{cid})")
r = adm.req("/api/org.php?action=deleteClass", "POST", {"name": "Lớp P2"})
rec("ORG-P2-1", "Xoá lớp chỉ còn ghi danh niên khoá cũ bị chặn có kiểm soát (4xx, nêu số em)",
    r[0] in (400, 403, 409) and 'còn 1 em' in r[2] and sql("SELECT COUNT(*) FROM classes WHERE name='Lớp P2'") == '1', show(r))
sql(f"DELETE FROM enrollments WHERE year_id={yid}")
r = adm.req("/api/org.php?action=deleteClass", "POST", {"name": "Lớp P2"})
rec("ORG-P2-2", "Hết ghi danh thì xoá được lớp", okj(r) and sql("SELECT COUNT(*) FROM classes WHERE name='Lớp P2'") == '0', show(r))
sql(f"DELETE FROM school_years WHERE id={yid}")

p = sum(1 for x in results if x[2]); print("TOTAL", len(results), "PASS", p, "FAIL", len(results) - p)
