"""Hồi quy gói P3 (kiểm đầu vào): hồ sơ thiếu nhi (#86, cả save lẫn import) và điểm danh buổi tương lai (#85).
Chạy trên DB THỬ NGHIỆM: php -S 127.0.0.1:8088 -t public (TNTT_DB_* trỏ vào DB thử),
mysql root không mật khẩu, DB tntt_e2e. Cần e2e.py + e2e2.py (nhánh origin/audit, tests/e2e) đặt cạnh file này.
Kịch bản tự dọn dữ liệu thử (em 'P3 *', chương trình 'P3 all days', điểm danh của chương trình đó).
Không đưa chữ có dấu vào câu SQL (client mysql mặc định latin1) — chỉ so sánh chữ có dấu ở phía Python.
"""
import os, sys, datetime
HERE = os.path.dirname(os.path.abspath(__file__))
exec(open(os.path.join(HERE, 'e2e2.py'), encoding='utf-8').read().split('for role,ph in [("glv","0911000004"),("glv_chu_nhiem"')[0])
results.clear()
def show(r): return f"HTTP {r[0]} {r[2][:150]!r}"
def okj(r): return r[0] == 200 and isinstance(r[1], dict) and r[1].get('ok') is True
def err(r): return (r[1] or {}).get('error', '') if isinstance(r[1], dict) else ''

# "Hôm nay" theo giờ Việt Nam (máy chủ set Asia/Ho_Chi_Minh), độc lập múi giờ của máy chạy test
NOW = datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=7)))
TODAY = NOW.date()
iso = lambda d: d.isoformat()

def cleanup():
    sql("DELETE FROM attendances WHERE program_id IN (SELECT id FROM programs WHERE name='P3 all days')")
    sql("DELETE FROM programs WHERE name='P3 all days'")
    sql("DELETE FROM enrollments WHERE student_id IN (SELECT id FROM students WHERE full_name LIKE 'P3 %')")
    sql("DELETE FROM students WHERE full_name LIKE 'P3 %'")
cleanup()

adm = logged("0901000001", "tntt@2026")
glv = logged("0911000004")          # GLV lớp A (chỉ được điểm danh; không có quyền sửa hồ sơ)
gvcn = logged("0911000003")         # GV chủ nhiệm lớp A (được sửa/nhập hồ sơ lớp mình)
anon = Client(); _, _, _t, _ = anon.req("/"); _m = re.search(r'([0-9a-f]{64})', _t); anon.csrf = _m.group(1) if _m else None  # có CSRF nhưng chưa đăng nhập
CUR = sql("SELECT id FROM school_years WHERE is_current=1 LIMIT 1")
SAVE = "/api/students.php?action=save"
def save(c, extra, name="P3 SAVE", cls=None):
    body = {"isNew": True, "name": name, "className": cls or nameA, "gender": "nam"}
    body.update(extra)
    return c.req(SAVE, "POST", body)
def n_stu(like): return int(sql(f"SELECT COUNT(*) FROM students WHERE full_name LIKE '{like}'"))

# ================================================================ #86 save: ngày sinh
def bad_date(tag, bd):
    n0 = n_stu('P3 %')
    r = save(adm, {"birthDate": bd}, name="P3 BD " + tag)
    rec("STU86-01", f"save ngày sinh {bd!r} -> 400, thông báo nêu 'Ngày sinh', không lưu",
        r[0] == 400 and 'Ngày sinh' in err(r) and n_stu('P3 %') == n0, show(r))
bad_date("2099", "2099-01-01")
bad_date("1800", "1800-01-01")
bad_date("ABC", "abc")
bad_date("FEB30", "2015-02-30")
bad_date("MONTH13", "2015-13-01")
bad_date("SLASH", "20/05/2015")
bad_date("TOMORROW", iso(TODAY + datetime.timedelta(days=1)))
bad_date("OVER100", iso(TODAY.replace(year=TODAY.year - 100) - datetime.timedelta(days=1)))
r = save(adm, {"birthDate": ["2015-01-01"]}, name="P3 BD ARRAY")
rec("STU86-01b", "save ngày sinh là mảng -> 400 (không 500)", r[0] == 400 and n_stu('P3 BD ARRAY') == 0, show(r))

r = save(adm, {"birthDate": ""}, name="P3 BD EMPTY")
rec("STU86-02", "save ngày sinh RỖNG vẫn OK, lưu NULL",
    okj(r) and sql("SELECT birth_date IS NULL FROM students WHERE full_name='P3 BD EMPTY'") == '1', show(r))
r = save(adm, {}, name="P3 BD MISSING")
rec("STU86-02b", "save không gửi ngày sinh vẫn OK", okj(r), show(r))
r = save(adm, {"birthDate": "2015-05-20"}, name="P3 BD GOOD")
rec("STU86-03", "save ngày sinh hợp lệ 2015-05-20 OK", okj(r) and sql("SELECT birth_date FROM students WHERE full_name='P3 BD GOOD'") == '2015-05-20', show(r))
r = save(adm, {"birthDate": iso(TODAY)}, name="P3 BD TODAY")
rec("STU86-03b", "ranh giới: sinh HÔM NAY vẫn OK", okj(r), show(r))
b100 = iso(TODAY.replace(year=TODAY.year - 100))
r = save(adm, {"birthDate": b100}, name="P3 BD EXACT100")
rec("STU86-03c", f"ranh giới: đúng 100 năm trước ({b100}) vẫn OK", okj(r), show(r))

# sửa hồ sơ đã có: cũng bị kiểm, dữ liệu cũ không đổi
code = sql("SELECT code FROM students WHERE full_name='P3 BD GOOD'")
r = adm.req(SAVE, "POST", {"code": code, "name": "P3 BD GOOD", "className": nameA, "birthDate": "2099-01-01", "gender": "nam"})
rec("STU86-04", "sửa hồ sơ có sẵn với ngày sinh 2099 -> 400, ngày cũ giữ nguyên",
    r[0] == 400 and sql("SELECT birth_date FROM students WHERE full_name='P3 BD GOOD'") == '2015-05-20', show(r))
r = adm.req(SAVE, "POST", {"code": code, "name": "P3 BD GOOD", "className": nameA, "birthDate": "2016-06-06", "gender": "nam"})
rec("STU86-04b", "sửa hồ sơ có sẵn với ngày hợp lệ -> OK", okj(r) and sql("SELECT birth_date FROM students WHERE full_name='P3 BD GOOD'") == '2016-06-06', show(r))

# ================================================================ #86 save: độ dài
def too_long(tag, field, val, label):
    n0 = int(sql("SELECT COUNT(*) FROM students"))
    body = {field: val}
    nm = "P3 LEN " + tag
    if field == 'name': nm = val
    else: body["name"] = nm
    r = adm.req(SAVE, "POST", {"isNew": True, "className": nameA, "gender": "nam", **body})
    rec("STU86-05", f"save {field} quá dài ({len(val)} ký tự) -> 400, nêu '{label}', không 500, không lưu",
        r[0] == 400 and label in err(r) and int(sql("SELECT COUNT(*) FROM students")) == n0, show(r))
too_long("N", "name", "A" * 300, "Họ tên")
too_long("N129", "name", "Đ" * 129, "Họ tên")
too_long("ADDR", "address", "P" * 256, "Địa chỉ")
too_long("HOLY", "holyName", "H" * 65, "Tên thánh")
too_long("FATHER", "fatherName", "F" * 129, "Tên cha")
too_long("MOTHER", "motherName", "M" * 129, "Tên mẹ")
too_long("FPHONE", "fatherPhone", "0" + "9" * 24, "SĐT cha")
too_long("MPHONE", "motherPhone", "0" + "9" * 24, "SĐT mẹ")
# ranh giới: đúng bằng giới hạn (tính theo KÝ TỰ, không phải byte: 'Đ' là 2 byte) vẫn lưu được
r = adm.req(SAVE, "POST", {"isNew": True, "className": nameA, "gender": "nam", "name": "P3 " + "Đ" * 125, "address": "Ð" * 255, "holyName": "Â" * 64})
rec("STU86-06", "ranh giới: họ tên 128 ký tự, địa chỉ 255, tên thánh 64 (nhiều byte UTF-8) vẫn lưu OK", okj(r), show(r))

# quyền không bị nới lỏng
r = anon.req(SAVE, "POST", {"isNew": True, "name": "P3 ANON", "className": nameA, "birthDate": "2015-01-01"})
rec("STU86-07", "chưa đăng nhập vẫn bị chặn (401)", r[0] == 401 and n_stu('P3 ANON') == 0, show(r))
r = save(glv, {"birthDate": "2015-01-01"}, name="P3 GLV A")
rec("STU86-08", "GLV (chỉ xem hồ sơ) save vẫn 403, không lưu", r[0] == 403 and n_stu('P3 GLV A') == 0, show(r))
r = save(gvcn, {"birthDate": "2015-01-01"}, name="P3 GVCN B", cls=nameB)
rec("STU86-08b", "GVCN lớp A save vào lớp B vẫn 403, không lưu", r[0] == 403 and n_stu('P3 GVCN B') == 0, show(r))
r = save(gvcn, {"birthDate": "2099-01-01"}, name="P3 GVCN A BAD")
rec("STU86-08c", "GVCN lớp A save lớp mình với ngày 2099 -> 400", r[0] == 400 and n_stu('P3 GVCN A BAD') == 0, show(r))
r = save(gvcn, {"birthDate": "2015-01-01"}, name="P3 GVCN A")
rec("STU86-08d", "GVCN lớp A save lớp mình với dữ liệu hợp lệ vẫn OK", okj(r) and n_stu('P3 GVCN A') == 1, show(r))

# ================================================================ #86 import
rows = [
    {"name": "P3 IMP OK",      "className": nameA, "birthDate": "2015-03-03", "gender": "nam"},   # dòng 2
    {"name": "P3 IMP FUTURE",  "className": nameA, "birthDate": "2099-05-05", "gender": "nam"},   # dòng 3
    {"name": "P3 IMP OLD",     "className": nameA, "birthDate": "1800-01-01", "gender": "nam"},   # dòng 4
    {"name": "P3 IMP ABC",     "className": nameA, "birthDate": "abc",        "gender": "nam"},   # dòng 5
    {"name": "P3 IMP FEB30",   "className": nameA, "birthDate": "2015-02-30", "gender": "nam"},   # dòng 6
    {"name": "P3 IMP EMPTY",   "className": nameA, "birthDate": "",           "gender": "nữ"},    # dòng 7
    {"name": "P3 " + "N" * 300, "className": nameA, "birthDate": "2015-03-03"},                    # dòng 8
    {"name": "P3 IMP ADDR",    "className": nameA, "birthDate": "2015-03-03", "address": "x" * 300},  # dòng 9
]
r = adm.req("/api/students.php?action=import", "POST", {"rows": rows})
js = r[1] if isinstance(r[1], dict) else {}
errs = js.get('errors', [])
rec("IMP86-01", "import: chỉ 2 dòng hợp lệ (đúng + ngày rỗng) được thêm, 6 dòng lỗi bị bỏ qua, HTTP 200",
    okj(r) and js.get('added') == 2 and js.get('skipped') == 6, show(r))
for dong, tag in [(3, 'FUTURE'), (4, 'OLD'), (5, 'ABC'), (6, 'FEB30')]:
    rec("IMP86-02", f"import: dòng {dong} ({tag}) có trong errors 'Dòng {dong}: ngày sinh không hợp lệ' và KHÔNG lưu",
        any(e.startswith(f'Dòng {dong}: ') and 'ngày sinh không hợp lệ' in e.lower() for e in errs) and n_stu('P3 IMP ' + tag) == 0, f"errors={errs}")
rec("IMP86-03", "import: dòng 8 (họ tên 300 ký tự) và dòng 9 (địa chỉ 300) báo lỗi độ dài, không lưu",
    any(e.startswith('Dòng 8: ') and 'Họ tên quá dài' in e for e in errs) and any(e.startswith('Dòng 9: ') and 'Địa chỉ quá dài' in e for e in errs)
    and n_stu('P3 IMP ADDR') == 0 and n_stu('P3 NNNN%') == 0, f"errors={errs}")
rec("IMP86-04", "import: dòng hợp lệ lưu đúng ngày; dòng ngày RỖNG lưu NULL (vẫn được phép)",
    sql("SELECT birth_date FROM students WHERE full_name='P3 IMP OK'") == '2015-03-03'
    and sql("SELECT birth_date IS NULL FROM students WHERE full_name='P3 IMP EMPTY'") == '1', "")
r = adm.req("/api/students.php?action=import", "POST", {"rows": [{"name": "P3 IMP TMR", "className": nameA, "birthDate": iso(TODAY + datetime.timedelta(days=1))}]})
rec("IMP86-05", "import: ngày sinh là NGÀY MAI bị bỏ qua (added 0)", okj(r) and r[1].get('added') == 0 and r[1].get('skipped') == 1 and n_stu('P3 IMP TMR') == 0, show(r))
# cập nhật em có sẵn qua import với ngày xấu: không đổi dữ liệu cũ
codeok = sql("SELECT code FROM students WHERE full_name='P3 IMP OK'")
r = adm.req("/api/students.php?action=import", "POST", {"rows": [{"code": codeok, "name": "P3 IMP OK", "className": nameA, "birthDate": "2099-01-01"}]})
rec("IMP86-06", "import: cập nhật em có sẵn với ngày 2099 bị bỏ qua, ngày cũ giữ nguyên",
    okj(r) and r[1].get('updated') == 0 and sql("SELECT birth_date FROM students WHERE full_name='P3 IMP OK'") == '2015-03-03', show(r))
# quyền
r = anon.req("/api/students.php?action=import", "POST", {"rows": rows})
rec("IMP86-07", "import: chưa đăng nhập vẫn 401", r[0] == 401, show(r))
r = glv.req("/api/students.php?action=import", "POST", {"rows": [{"name": "P3 IMP GLVA", "className": nameA, "birthDate": "2015-01-01"}]})
rec("IMP86-08", "import: GLV (chỉ xem) vẫn 403, không thêm", r[0] == 403 and n_stu('P3 IMP GLVA') == 0, show(r))
r = gvcn.req("/api/students.php?action=import", "POST", {"rows": [{"name": "P3 IMP GVCNB", "className": nameB, "birthDate": "2015-01-01"}]})
rec("IMP86-09", "import: GVCN lớp A nhập vào lớp B vẫn bị bỏ qua (added 0)", n_stu('P3 IMP GVCNB') == 0 and not (okj(r) and r[1].get('added')), show(r))
r = gvcn.req("/api/students.php?action=import", "POST", {"rows": [{"name": "P3 IMP GVCNA", "className": nameA, "birthDate": "2015-01-01"}, {"name": "P3 IMP GVCNA BAD", "className": nameA, "birthDate": "2099-01-01"}]})
rec("IMP86-10", "import: GVCN lớp A nhập lớp mình: dòng hợp lệ thêm, dòng ngày 2099 bỏ qua", okj(r) and r[1].get('added') == 1 and r[1].get('skipped') == 1 and n_stu('P3 IMP GVCNA BAD') == 0, show(r))

# ================================================================ #85 điểm danh buổi tương lai
sql(f"INSERT INTO programs (year_id,name,start_time,days_of_week) VALUES ({CUR},'P3 all days','00:00:00','0,1,2,3,4,5,6')")
PID = sql("SELECT id FROM programs WHERE name='P3 all days'")
stA = sql(f"SELECT student_id FROM enrollments WHERE year_id={CUR} AND class_id={cA} ORDER BY student_id LIMIT 1")
stA2 = sql(f"SELECT student_id FROM enrollments WHERE year_id={CUR} AND class_id={cA} ORDER BY student_id LIMIT 1 OFFSET 1")
stB = sql(f"SELECT student_id FROM enrollments WHERE year_id={CUR} AND class_id={cB} ORDER BY student_id LIMIT 1")
codeA = sql(f"SELECT code FROM students WHERE id={stA}")
codeA2 = sql(f"SELECT code FROM students WHERE id={stA2}")
codeB = sql(f"SELECT code FROM students WHERE id={stB}")
TG = lambda c, d, sid: c.req("/api/attendance.php?action=toggle", "POST", {"programId": int(PID), "date": d, "studentId": int(sid)})
SC = lambda c, d, codes: c.req("/api/attendance.php?action=scan", "POST", {"programId": int(PID), "date": d, "codes": codes, "method": "qr"})
cnt = lambda sid, d: int(sql(f"SELECT COUNT(*) FROM attendances WHERE program_id={PID} AND student_id={sid} AND session_date='{d}'"))
tmr = iso(TODAY + datetime.timedelta(days=1)); nxt = iso(TODAY + datetime.timedelta(days=7)); yst = iso(TODAY - datetime.timedelta(days=1))
far = iso(TODAY + datetime.timedelta(days=400))
tmr_odd = f"{(TODAY + datetime.timedelta(days=1)).year}-{(TODAY + datetime.timedelta(days=1)).month}-{(TODAY + datetime.timedelta(days=1)).day}"  # không đệm 0

for lab, d in [("ngày mai", tmr), ("tuần sau", nxt), ("400 ngày nữa", far)]:
    r = TG(adm, d, stA)
    rec("ATT85-01", f"toggle buổi tương lai ({lab}: {d}) -> 400 + thông báo tiếng Việt, không ghi",
        r[0] == 400 and 'chưa diễn ra' in err(r) and cnt(stA, d) == 0, show(r))
    r = SC(adm, d, [codeA])
    rec("ATT85-02", f"scan buổi tương lai ({lab}: {d}) -> 400 + thông báo tiếng Việt, không ghi",
        r[0] == 400 and 'chưa diễn ra' in err(r) and cnt(stA, d) == 0, show(r))

# Chuỗi ngày xấu (rác phía sau, offset, xuống dòng, không đệm số 0, khoảng trắng, ngày không có thật, 0000-00-00, rỗng, mảng):
# PHẢI 400 cho cả toggle, scan một mã và scan lô nhiều mã, và KHÔNG được sinh dòng nào trong attendances
# (trước đây scan + '2026-10-01abc' bị MariaDB cắt còn '2026-10-01' rồi lưu thành bản ghi tương lai).
BAD_DATES = [
    ("rác phía sau (tương lai)", tmr + "abc"), ("offset +14:00", tmr + "T00:00:00+14:00"), ("xuống dòng cuối", tmr + "\n"),
    ("không đệm số 0", tmr_odd), ("khoảng trắng đầu", " " + tmr), ("ngày không có thật", f"{TODAY.year}-02-30"),
    ("0000-00-00", "0000-00-00"), ("chuỗi rỗng", ""), ("mảng", [tmr]), ("mảng rỗng", []),
    ("rác phía sau (quá khứ)", yst + "abc"), ("dạng dd/mm/yyyy", TODAY.strftime("%d/%m/%Y")),
]
tot = lambda: int(sql("SELECT COUNT(*) FROM attendances"))
for lab, d in BAD_DATES:
    t0 = tot()
    r = TG(adm, d, stA)
    rec("ATT85-12", f"toggle ngày xấu [{lab}] {d!r} -> 400 (không 500), không ghi", r[0] == 400 and tot() == t0, show(r))
    r = SC(adm, d, [codeA])
    rec("ATT85-13", f"scan 1 mã ngày xấu [{lab}] {d!r} -> 400, không ghi", r[0] == 400 and tot() == t0, show(r))
    r = SC(adm, d, [codeA, codeA2])
    rec("ATT85-14", f"scan lô 2 mã ngày xấu [{lab}] {d!r} -> 400, không ghi", r[0] == 400 and tot() == t0, show(r))
rec("ATT85-15", "không có dòng điểm danh nào ở tương lai sau toàn bộ ca ngày xấu",
    int(sql(f"SELECT COUNT(*) FROM attendances WHERE program_id={PID} AND session_date > '{iso(TODAY)}'")) == 0, "")

r = TG(adm, iso(TODAY), stA)
rec("ATT85-03", "toggle HÔM NAY vẫn OK (ghi)", okj(r) and r[1].get('removed') is False and cnt(stA, iso(TODAY)) == 1, show(r))
r = TG(adm, iso(TODAY), stA)
rec("ATT85-03b", "toggle HÔM NAY lần 2 vẫn gỡ được", okj(r) and r[1].get('removed') is True and cnt(stA, iso(TODAY)) == 0, show(r))
r = TG(adm, yst, stA)
rec("ATT85-04", "toggle hôm qua (điểm danh bù) vẫn OK", okj(r) and cnt(stA, yst) == 1, show(r))
r = TG(adm, yst, stA)
rec("ATT85-04b", "toggle hôm qua lần 2 vẫn gỡ được", okj(r) and r[1].get('removed') is True and cnt(stA, yst) == 0, show(r))
r = SC(adm, iso(TODAY), [codeA])
rec("ATT85-05", "scan HÔM NAY vẫn OK (added 1)", okj(r) and r[1].get('added') == 1 and cnt(stA, iso(TODAY)) == 1, show(r))
r = SC(adm, yst, [codeA2])
rec("ATT85-06", "scan hôm qua (bù) vẫn OK (added 1)", okj(r) and r[1].get('added') == 1 and cnt(stA2, yst) == 1, show(r))

# dữ liệu tương lai lỡ có sẵn: bấm lần nữa vẫn GỠ được (không kẹt)
# (chép trạng thái từ một bản ghi hợp lệ có sẵn để khỏi đưa chữ có dấu vào SQL)
sql(f"INSERT INTO attendances (year_id,program_id,session_date,student_id,status,method) SELECT {CUR},{PID},'{nxt}',{stA},status,'tay' FROM attendances WHERE program_id={PID} LIMIT 1")
r = TG(adm, nxt, stA)
rec("ATT85-07", "điểm danh tương lai lỡ có sẵn: bấm lại vẫn GỠ được (removed:true), không kẹt",
    okj(r) and r[1].get('removed') is True and cnt(stA, nxt) == 0, show(r))

# phân quyền không bị nới lỏng
r = anon.req("/api/attendance.php?action=toggle", "POST", {"programId": int(PID), "date": tmr, "studentId": int(stA)})
# attendance.php gọi require_write() (CSRF) TRƯỚC khi xét đăng nhập nên khách vãng lai nhận 403-CSRF thay vì 401 (đã như vậy từ trước);
# điều cần giữ: bị chặn (401 hoặc 403) và không ghi được gì.
rec("ATT85-08", "toggle chưa đăng nhập vẫn bị chặn (401/403), không ghi", r[0] in (401, 403) and cnt(stA, tmr) == 0, show(r))
r = anon.req("/api/attendance.php?action=scan", "POST", {"programId": int(PID), "date": tmr, "codes": [codeA]})
rec("ATT85-08b", "scan chưa đăng nhập vẫn bị chặn (401/403), không ghi", r[0] in (401, 403) and cnt(stA, tmr) == 0, show(r))
for lab, d in [("hôm nay", iso(TODAY)), ("tương lai", tmr)]:
    r = TG(glv, d, stB)
    rec("ATT85-09", f"GLV lớp A toggle em lớp B ({lab}) vẫn 403, không ghi", r[0] == 403 and cnt(stB, d) == 0, show(r))
r = SC(glv, iso(TODAY), [codeB])
rec("ATT85-09b", "GLV lớp A scan em khối khác (hôm nay) vẫn không ghi được", not (okj(r) and r[1].get('added')) and cnt(stB, iso(TODAY)) == 0, show(r))
r = TG(glv, iso(TODAY), stA2)
rec("ATT85-10", "GLV lớp A toggle em lớp mình HÔM NAY vẫn OK", okj(r) and cnt(stA2, iso(TODAY)) == 1, show(r))
r = TG(glv, tmr, stA2)
rec("ATT85-10b", "GLV lớp A toggle em lớp mình NGÀY MAI -> 400", r[0] == 400 and cnt(stA2, tmr) == 0, show(r))
r = glv.req("/api/attendance.php?action=lookup", "POST", {"programId": int(PID), "date": tmr})
rec("ATT85-11", "lookup (bảng tra máy quét) không bị ảnh hưởng, vẫn 200", okj(r), show(r))

cleanup()
p = sum(1 for x in results if x[2]); print("TOTAL", len(results), "PASS", p, "FAIL", len(results) - p)
