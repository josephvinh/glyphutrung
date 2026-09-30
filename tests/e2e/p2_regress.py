"""Hồi quy gói P2 (đổi schema): export.php (#79), xoá lớp (#80).
Chạy trên DB THỬ NGHIỆM: php -S <host:port> -t public (TNTT_DB_* trỏ vào DB thử),
mysql root không mật khẩu. Cần e2e.py + e2e2.py (nhánh origin/audit, tests/e2e) đặt cạnh file này;
biến môi trường E2E_BASE / E2E_DB chọn cổng và DB thử.
Kịch bản tự dọn dữ liệu thử (đơn xin phép 'P2-*', lớp 'Lớp P2*', niên khoá 'P2 cũ').
"""
import os
HERE = os.path.dirname(os.path.abspath(__file__))
exec(open(os.path.join(HERE, 'e2e2.py'), encoding='utf-8').read().split('for role,ph in [("glv","0911000004"),("glv_chu_nhiem"')[0])
results.clear()
def show(r): return f"HTTP {r[0]} {r[2][:140]!r}"
def okj(r): return r[0] == 200 and isinstance(r[1], dict) and r[1].get('ok') is True
def rows_of(r): return r[1]['sheet']['rows'] if okj(r) and 'sheet' in r[1] else []

def cleanup():
    sql("DELETE FROM leave_requests WHERE reason LIKE 'P2-%'")
    sql("DELETE FROM member_assignments WHERE class_id IN (SELECT id FROM classes WHERE name LIKE 'Lớp P2%')")
    sql("DELETE FROM enrollments WHERE year_id IN (SELECT id FROM school_years WHERE name='P2 cũ')")
    sql("DELETE FROM classes WHERE name LIKE 'Lớp P2%'")
    sql("DELETE FROM school_years WHERE name='P2 cũ'")
cleanup()

adm = logged("0901000001", "tntt@2026")
CUR = sql("SELECT id FROM school_years WHERE is_current=1 LIMIT 1")
term = sql(f"SELECT id FROM terms WHERE year_id={CUR} ORDER BY id LIMIT 1")
sidA = sql(f"SELECT student_id FROM enrollments WHERE year_id={CUR} AND class_id={cA} LIMIT 1")
nameA = sql(f"SELECT name FROM classes WHERE id={cA}")
nameB = sql(f"SELECT name FROM classes WHERE id={cB}")
blockA = sql(f"SELECT name FROM blocks WHERE id={bA}")
d0, d1 = sql(f"SELECT start_date, DATE_ADD(start_date, INTERVAL 364 DAY) FROM school_years WHERE id={CUR}").split("\t")
HDR_DETAIL = ['STT', 'Mã số', 'Họ tên', 'Lớp', 'Ngày', 'Buổi', 'Trạng thái', 'Ghi chú', 'Người ghi']

# ---- #79 export.php (admin)
r = adm.req("/api/export.php?action=attendance-detail")
rec("EXP-01", "attendance-detail (admin, mặc định) không 500", okj(r) and 'sheet' in r[1], show(r)[:120])

# EXP-02: giá trị thật = số bản ghi điểm danh lớp A + đơn xin phép đã duyệt chưa có điểm danh
exp_att = int(sql(f"""SELECT COUNT(*) FROM attendances a
    JOIN enrollments e ON e.student_id=a.student_id AND e.year_id=a.year_id
    WHERE a.year_id={CUR} AND a.session_date BETWEEN '{d0}' AND '{d1}' AND e.class_id={cA}"""))
exp_lv = int(sql(f"""SELECT COUNT(*) FROM leave_requests lr
    JOIN enrollments e ON e.student_id=lr.student_id AND e.year_id=lr.year_id
    WHERE lr.year_id={CUR} AND lr.status='đã duyệt' AND lr.session_date BETWEEN '{d0}' AND '{d1}' AND e.class_id={cA}
      AND NOT EXISTS (SELECT 1 FROM attendances a WHERE a.student_id=lr.student_id AND a.program_id=lr.program_id
                      AND a.session_date=lr.session_date AND a.year_id=lr.year_id)"""))
r = adm.req(f"/api/export.php?action=attendance-detail&classId={cA}&fromDate={d0}&toDate={d1}")
rw = rows_of(r)
rec("EXP-02", "attendance-detail lớp A: count = số bản ghi thật, tiêu đề đúng, mọi dòng thuộc lớp A",
    okj(r) and exp_att > 0 and r[1].get('count') == exp_att + exp_lv and len(rw) == exp_att + exp_lv + 1
    and rw[0] == HDR_DETAIL and all(x[3] == nameA for x in rw[1:]) and all(re.fullmatch(r'\d\d/\d\d/\d{4}', x[4]) for x in rw[1:]),
    f"count={r[1].get('count') if okj(r) else None} kỳ vọng {exp_att}+{exp_lv}, rows={len(rw)}")

# EXP-03: ma trận điểm danh — số dòng dữ liệu = số em đang sinh hoạt, tiêu đề, số cột buổi
nAll = int(sql(f"SELECT COUNT(*) FROM enrollments WHERE year_id={CUR} AND status='đang sinh hoạt'"))
nA = int(sql(f"SELECT COUNT(*) FROM enrollments WHERE year_id={CUR} AND class_id={cA} AND status='đang sinh hoạt'"))
nSess = int(sql(f"""SELECT COUNT(*) FROM (SELECT DISTINCT a.program_id, a.session_date FROM attendances a
    JOIN programs p ON p.id=a.program_id WHERE a.year_id={CUR} AND p.status='kích hoạt') t"""))
r = adm.req("/api/export.php?action=attendance", "POST", {})
rw = rows_of(r)
rec("EXP-03", "attendance (admin): số dòng dữ liệu = số em đang sinh hoạt, tiêu đề + số cột buổi đúng, có ô có mặt",
    okj(r) and nAll > 100 and len(rw) == nAll + 1 and rw[0][:3] == ['Mã số', 'Họ tên', 'Lớp'] and rw[0][-1] == 'Tỷ lệ'
    and nSess > 0 and len(rw[0]) == 3 + nSess + 1 and any(c in ('P', 'L') for x in rw[1:] for c in x[3:-1]),
    f"dòng={len(rw)} (kỳ vọng {nAll}+1), cột={len(rw[0]) if rw else 0} (kỳ vọng {3+nSess+1})")
r = adm.req("/api/export.php?action=attendance", "POST", {"classId": int(cA)})
rw = rows_of(r)
rec("EXP-03b", "attendance lọc lớp A: chỉ em lớp A", okj(r) and len(rw) == nA + 1 and all(x[2] == nameA for x in rw[1:]),
    f"dòng={len(rw)} (kỳ vọng {nA}+1)")

r = adm.req("/api/export.php?action=scores", "POST", {"classId": int(cA), "termId": int(term)})
rw = rows_of(r)
rec("EXP-04", "scores có classId: không HY093, đúng số em lớp A", okj(r) and len(rw) == nA + 1 and all(x[2] == nameA for x in rw[1:]),
    show(r)[:100] + f" dòng={len(rw)} kỳ vọng {nA}+1")
r = adm.req("/api/export.php?action=scores", "POST", {"termId": int(term)})
rec("EXP-05", "scores toàn đoàn (không classId)", okj(r) and len(rows_of(r)) == nAll + 1, show(r)[:100])
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
    rec("EXP-10", f"{role}: attendance-detail lớp của mình 200 và chỉ có lớp mình", r[0] == 200 and okj(r)
        and all(x[3] == nameA for x in rows_of(r)[1:]), show(r)[:100])

# ---- #79 đơn xin phép: 'đã duyệt' có, 'chờ duyệt' không, GLV lớp A không thấy lớp B
DAY = '2031-05-05'
prog = sql(f"SELECT id FROM programs WHERE year_id={CUR} AND status='kích hoạt' ORDER BY id LIMIT 1")
sA1, sA2 = sql(f"SELECT student_id FROM enrollments WHERE year_id={CUR} AND class_id={cA} AND status='đang sinh hoạt' ORDER BY student_id LIMIT 2").split()
sB1 = sql(f"SELECT student_id FROM enrollments WHERE year_id={CUR} AND class_id={cB} AND status='đang sinh hoạt' ORDER BY student_id LIMIT 1")
for sid_, st_, rs_ in [(sA1, 'đã duyệt', 'P2-DUYET-A'), (sA2, 'chờ duyệt', 'P2-CHO-A'), (sB1, 'đã duyệt', 'P2-DUYET-B')]:
    sql(f"INSERT INTO leave_requests (year_id,student_id,program_id,session_date,reason,status) VALUES ({CUR},{sid_},{prog},'{DAY}','{rs_}','{st_}')")
q = f"/api/export.php?action=attendance-detail&programId={prog}&fromDate={DAY}&toDate={DAY}"
r = adm.req(q); rw = rows_of(r); notes = [x[7] for x in rw[1:]]
rec("LV-01", "admin: đơn 'đã duyệt' xuất hiện (cả lớp A và B), có lý do + trạng thái Vắng mặt",
    okj(r) and 'P2-DUYET-A' in notes and 'P2-DUYET-B' in notes and all(x[6] == 'Vắng mặt' for x in rw[1:]), f"notes={notes}")
rec("LV-02", "admin: đơn 'chờ duyệt' KHÔNG xuất hiện", okj(r) and 'P2-CHO-A' not in notes and len(rw) == 3, f"notes={notes}")
c = logged("0911000004")
r = c.req(q); rw = rows_of(r); notes = [x[7] for x in rw[1:]]
rec("LV-03", "GLV lớp A: thấy đơn đã duyệt lớp mình, KHÔNG thấy lớp B và đơn chờ duyệt",
    okj(r) and notes == ['P2-DUYET-A'] and rw[1][3] == nameA, f"notes={notes}")
sql("DELETE FROM leave_requests WHERE reason LIKE 'P2-%'")

# ---- #80 xoá lớp (chỉ xét niên khoá hiện tại; ghi danh niên khoá cũ bị khoá ngoại RESTRICT chặn -> 400, không 500)
# (a1) còn ghi danh ở niên khoá HIỆN TẠI -> chặn, nêu số em
adm.req("/api/org.php?action=saveClass", "POST", {"name": "Lớp P2", "block": blockA})
cid = sql("SELECT id FROM classes WHERE name='Lớp P2'")
sid = sql("SELECT id FROM students LIMIT 1 OFFSET 7")
old = sql(f"SELECT class_id FROM enrollments WHERE year_id={CUR} AND student_id={sid}")
sql(f"UPDATE enrollments SET class_id={cid} WHERE year_id={CUR} AND student_id={sid}")
r = adm.req("/api/org.php?action=deleteClass", "POST", {"name": "Lớp P2"})
rec("ORG-P2-1", "Lớp còn ghi danh niên khoá hiện tại bị chặn (400, nêu 'còn 1 em'), lớp còn nguyên",
    r[0] == 400 and 'còn 1 em' in r[2] and sql("SELECT COUNT(*) FROM classes WHERE name='Lớp P2'") == '1', show(r))
sql(f"UPDATE enrollments SET class_id={old} WHERE year_id={CUR} AND student_id={sid}")
# (a2) chỉ còn ghi danh niên khoá CŨ -> DELETE vướng fk_enr_class: 400 có kiểm soát, lịch sử còn nguyên
sql("INSERT INTO school_years (name,start_date,end_date,is_current) VALUES ('P2 cũ','2020-09-01','2021-06-30',0)")
yid = sql("SELECT id FROM school_years WHERE name='P2 cũ'")
sql(f"INSERT INTO enrollments (year_id,student_id,class_id) VALUES ({yid},{sid},{cid})")
r = adm.req("/api/org.php?action=deleteClass", "POST", {"name": "Lớp P2"})
rec("ORG-P2-2", "Lớp chỉ còn ghi danh niên khoá CŨ: 400 có kiểm soát (nêu niên khoá cũ), KHÔNG 500, lớp + ghi danh cũ còn nguyên",
    r[0] == 400 and 'niên khoá cũ' in r[2] and sql("SELECT COUNT(*) FROM classes WHERE name='Lớp P2'") == '1'
    and sql(f"SELECT COUNT(*) FROM enrollments WHERE year_id={yid} AND class_id={cid}") == '1', show(r))
# (a3) hết ghi danh hẳn -> xoá được
sql(f"DELETE FROM enrollments WHERE year_id={yid}")
r = adm.req("/api/org.php?action=deleteClass", "POST", {"name": "Lớp P2"})
rec("ORG-P2-2b", "Lớp rỗng hẳn (không ghi danh nào) xoá được", okj(r) and sql("SELECT COUNT(*) FROM classes WHERE name='Lớp P2'") == '0', show(r))
sql(f"DELETE FROM school_years WHERE id={yid}")
# (b) còn người phân công đang hiệu lực vẫn bị chặn
adm.req("/api/org.php?action=saveClass", "POST", {"name": "Lớp P2b", "block": blockA})
cid = sql("SELECT id FROM classes WHERE name='Lớp P2b'")
glvid = sql("SELECT id FROM members WHERE phone='0911000004'")
admid = sql("SELECT id FROM members WHERE role_code='admin' ORDER BY id LIMIT 1")
sql(f"INSERT INTO member_assignments (member_id,role_code,block_id,class_id,is_primary,from_date,assigned_by) "
    f"VALUES ({glvid},'glv',{bA},{cid},0,CURDATE(),{admid})")
r = adm.req("/api/org.php?action=deleteClass", "POST", {"name": "Lớp P2b"})
rec("ORG-P2-3", "Lớp còn người phân công đang hiệu lực bị chặn (400), lớp còn nguyên",
    r[0] == 400 and 'phân công' in r[2] and sql("SELECT COUNT(*) FROM classes WHERE name='Lớp P2b'") == '1', show(r))
sql(f"DELETE FROM member_assignments WHERE class_id={cid}")
r = adm.req("/api/org.php?action=deleteClass", "POST", {"name": "Lớp P2b"})
rec("ORG-P2-4", "Gỡ phân công xong thì xoá được lớp rỗng", okj(r) and sql("SELECT COUNT(*) FROM classes WHERE name='Lớp P2b'") == '0', show(r))

cleanup()
p = sum(1 for x in results if x[2]); print("TOTAL", len(results), "PASS", p, "FAIL", len(results) - p)
