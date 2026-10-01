"""Hồi quy gói P1 (phân quyền / rò rỉ dữ liệu): data.php (#78), bảo vệ tài khoản admin/BĐH (#97),
must_change_pw chặn API (#83) — bộ ca nghiệm thu mục 5 của đặc tả P1.
Chạy trên DB THỬ NGHIỆM: php -S <host:port> -t public (TNTT_DB_* trỏ vào DB thử),
mysql root không mật khẩu. Cần e2e.py + e2e2.py (nhánh origin/audit, tests/e2e) đặt cạnh file này;
biến môi trường E2E_BASE / E2E_DB chọn cổng và DB thử; E2E_CACHE_DIR (mặc định <repo>/public/cache)
là thư mục cache file của máy chủ đang chạy (kịch bản xoá để đọc dữ liệu tươi).
Ca Passkey AUTH-09 dùng php-cli + openssl để sinh khoá/ký WebAuthn (không cần thư viện python ngoài).
Đo hiệu năng (PERF-01): P1_PERF_OUT=<file> ghi số đo; P1_PERF_BASELINE=<file> so với số đo trước đó.
Kịch bản tự dọn dữ liệu thử (đơn 'NHAYCAM-*', phiếu 'P1-REMARK-*', điểm mẫu, tài khoản 0911000007..11, 09772000xx).
"""
import os, glob, time, base64, hashlib, struct, secrets, statistics
HERE = os.path.dirname(os.path.abspath(__file__))
exec(open(os.path.join(HERE, 'e2e2.py'), encoding='utf-8').read().split('for role,ph in [("glv","0911000004"),("glv_chu_nhiem"')[0])
results.clear()


def sql(q):  # như e2e.py nhưng ép utf8mb4 (client mặc định latin1 làm hỏng chuỗi tiếng Việt / ENUM)
    return subprocess.run(["mysql", "-uroot", "--default-character-set=utf8mb4", DB, "-N", "-B", "-e", q],
                          capture_output=True, text=True).stdout.strip()


CACHE_DIR = os.environ.get('E2E_CACHE_DIR', os.path.join(HERE, '..', '..', 'public', 'cache'))
HEX64 = re.compile(r'[0-9a-f]{64}')


def show(r): return f"HTTP {r[0]} {r[2][:140]!r}"
def okj(r): return r[0] == 200 and isinstance(r[1], dict) and r[1].get('ok') is True
def code_of(r): return r[1].get('code') if isinstance(r[1], dict) else None
def must_blocked(r): return r[0] == 403 and code_of(r) == 'must_change_pw'


def flush_cache():
    for f in glob.glob(os.path.join(CACHE_DIR, '*.json')):
        try: os.remove(f)
        except OSError: pass


EXTRA_PHONES = "'0911000007','0911000008','0911000009','0911000010','0911000011','0911000099','0977200001','0977200099'"


def cleanup():
    sql("DELETE FROM leave_requests WHERE reason LIKE 'NHAYCAM-%'")
    sql("DELETE FROM reports WHERE remark LIKE 'P1-REMARK-%'")
    sql("DELETE FROM scores WHERE type_code='mieng' AND value=7.25")
    sql(f"DELETE FROM push_outbox WHERE member_id IN (SELECT id FROM members WHERE phone IN ({EXTRA_PHONES}))")
    sql(f"DELETE FROM push_subscriptions WHERE member_id IN (SELECT id FROM members WHERE phone IN ({EXTRA_PHONES}))")
    sql(f"DELETE FROM member_passkeys WHERE member_id IN (SELECT id FROM members WHERE phone IN ({EXTRA_PHONES}))")
    sql(f"DELETE FROM member_assignments WHERE member_id IN (SELECT id FROM members WHERE phone IN ({EXTRA_PHONES}))")
    sql(f"DELETE FROM members WHERE phone IN ({EXTRA_PHONES})")
    sql("DELETE FROM login_attempts")
    sql("UPDATE permissions SET level='edit' WHERE module_key='scores' AND role_code='glv'")
    sql("UPDATE permissions SET level='view' WHERE module_key='leave' AND role_code='glv'")
    flush_cache()


cleanup()

# =============================================================== dữ liệu mẫu (đặc tả 5.1)
CUR = sql("SELECT id FROM school_years WHERE is_current=1 LIMIT 1")
T1, T2 = sql(f"SELECT id FROM terms WHERE year_id={CUR} ORDER BY sort_order, id LIMIT 2").split()
ADMIN_ID = sql("SELECT id FROM members WHERE phone='0901000001'")
prog = sql(f"SELECT id FROM programs WHERE year_id={CUR} ORDER BY id LIMIT 1")


def enrolled_class(where):
    return sql(f"SELECT c.id FROM classes c WHERE {where} AND EXISTS (SELECT 1 FROM enrollments e "
               f"WHERE e.class_id=c.id AND e.year_id={CUR}) ORDER BY c.id LIMIT 1")


# Lớp C: khối KHÁC bA (và khác bB nếu có thể) — để GLV kiêm A+C khác khối
cC = enrolled_class(f"c.block_id NOT IN ({bA},{bB})") or enrolled_class(f"c.block_id={bB} AND c.id<>{cB}")
cA2 = enrolled_class(f"c.block_id={bA} AND c.id<>{cA}")  # lớp thứ hai của khối bA (cho Trưởng khối)
bC = sql(f"SELECT block_id FROM classes WHERE id={cC}")
CLS = {'A': cA, 'A2': cA2, 'B': cB, 'C': cC}
assert all(CLS.values()), f"thiếu lớp mẫu {CLS}"
SAMPLE = {}  # nhãn lớp -> [student_id, student_id]
for lab, cid in CLS.items():
    s1, s2 = sql(f"SELECT student_id FROM enrollments WHERE year_id={CUR} AND class_id={cid} ORDER BY student_id LIMIT 2").split()
    SAMPLE[lab] = [s1, s2]
    sql(f"INSERT INTO leave_requests (year_id,student_id,program_id,session_date,reason,status) VALUES "
        f"({CUR},{s1},{prog},'2031-06-01','NHAYCAM-{lab}-1','chờ duyệt'),"
        f"({CUR},{s2},{prog},'2031-06-02','NHAYCAM-{lab}-2','đã duyệt')")
    for s in (s1, s2):
        sql(f"INSERT INTO reports (term_id,student_id,remark,status) VALUES ({T1},{s},'P1-REMARK-{lab}','nháp')")
        sql(f"INSERT INTO scores (term_id,student_id,type_code,value) VALUES ({T2},{s},'mieng',7.25)")

# Người dùng bổ sung
gAC = mk_member("T_GLVAC", "0911000007", "glv", block=bA, cls=cA)
sql(f"INSERT INTO member_assignments (member_id,role_code,block_id,class_id,is_primary,from_date,assigned_by) "
    f"VALUES ({gAC},'glv',{bC},{cC},0,CURDATE(),{ADMIN_ID})")
gTT = mk_member("T_GLVTT", "0911000008", "glv", block=bA, cls=cA)
sql(f"INSERT INTO member_assignments (member_id,role_code,block_id,class_id,is_primary,from_date,assigned_by) "
    f"VALUES ({gTT},'thu_thu',NULL,NULL,0,CURDATE(),{ADMIN_ID})")
MC_ID = mk_member("T_MC9", "0911000009", "glv", block=bA, cls=cA, must=1)
BDH2_ID = mk_member("T_BDH2", "0911000010", "bdh")
# glv có vai gốc glv nhưng KIÊM phân công bdh (isProtected mở rộng)
GBDH_ID = mk_member("T_GBDH", "0911000011", "glv", block=bA, cls=cA)
sql(f"INSERT INTO member_assignments (member_id,role_code,block_id,class_id,is_primary,from_date,assigned_by) "
    f"VALUES ({GBDH_ID},'bdh',NULL,NULL,0,CURDATE(),{ADMIN_ID})")
sql(f"INSERT INTO members (code,full_name,phone,password_hash,role_code,status,must_change_pw,register_note,registered_at) "
    f"VALUES ('P1CHO','Người Chờ Duyệt','0977200001','{HASH}','glv','chờ duyệt',0,'P1-GHICHU-DANGKY',NOW())")
PENDING_ID = sql("SELECT id FROM members WHERE phone='0977200001'")
BDH_ID = sql("SELECT id FROM members WHERE phone='0911000001'")
GLV_ID = sql("SELECT id FROM members WHERE phone='0911000004'")
sql("DELETE FROM login_attempts")
flush_cache()

# =============================================================== #78 data.php — ma trận vai × khoá (5.2)
blkA = set(sql(f"SELECT id FROM classes WHERE block_id={bA}").split())
ROLES = [  # tên, sđt, mk, tập lớp kỳ vọng cho scores/leave/reports (None = toàn đoàn), staff
    ("admin", "0901000001", "tntt@2026", None, 'edit'),
    ("bdh", "0911000001", "Test@1234", None, 'edit'),
    ("truong_khoi", "0911000002", "Test@1234", blkA, 'view'),
    ("glv_chu_nhiem", "0911000003", "Test@1234", {cA}, 'view'),
    ("glv", "0911000004", "Test@1234", {cA}, 'view'),
    ("glv_A+C", "0911000007", "Test@1234", {cA, cC}, 'view'),
    ("glv+thu_thu", "0911000008", "Test@1234", {cA}, 'view'),
    ("du_bi", "0911000005", "Test@1234", {cA}, 'view'),
    ("thu_thu", "0911000006", "Test@1234", set(), 'none'),
]
OLD_KEYS = {'ok', 'notes', 'stampSummaries', 'classCounts', 'programs', 'programClasses', 'attendances',
            'leaveRequests', 'scores', 'reports', 'announcements', 'readAnnouncements', 'members', 'logs',
            'libraryPending', 'students'}
HEAVY_KEYS = {'ok', 'attendances', 'scores'}
LIST_KEYS = ['leaveRequests', 'scores', 'reports', 'members', 'logs']


def in_cls(classes, alias):
    if classes is None: return ''
    if not classes: return ' AND 1=0'
    return f" AND {alias}.student_id IN (SELECT student_id FROM enrollments WHERE year_id={CUR} AND class_id IN ({','.join(sorted(classes))}))"


def db_counts(classes):
    return {
        'scores': int(sql(f"SELECT COUNT(*) FROM scores sc JOIN terms t ON t.id=sc.term_id WHERE t.year_id={CUR}" + in_cls(classes, 'sc'))),
        'leaveRequests': int(sql(f"SELECT COUNT(*) FROM leave_requests l WHERE l.year_id={CUR}" + in_cls(classes, 'l'))),
        'reports': int(sql(f"SELECT COUNT(*) FROM reports r JOIN terms t ON t.id=r.term_id WHERE t.year_id={CUR}" + in_cls(classes, 'r'))),
    }


def exp_samples(classes):
    labs = [l for l, c in CLS.items() if classes is None or c in classes]
    return labs, {int(s) for l in labs for s in SAMPLE[l]}


TOTAL = db_counts(None)
N_MEM_ALL = int(sql("SELECT COUNT(*) FROM members"))
N_MEM_NOADMIN = int(sql("SELECT COUNT(*) FROM members WHERE role_code<>'admin'"))
N_MEM_VIEW = int(sql("SELECT COUNT(*) FROM members WHERE role_code<>'admin' AND status<>'chờ duyệt'"))
assert TOTAL['leaveRequests'] >= 8 and TOTAL['reports'] >= 8, TOTAL
CLIENTS = {}
for name, ph, pw, classes, staff in ROLES:
    c = logged(ph, pw); CLIENTS[name] = c
    flush_cache()
    got = {p: c.req(f"/api/data.php?part={p}") for p in ('core', 'heavy', 'all')}
    bad = [p for p, r in got.items() if not okj(r)]
    if bad:
        rec("KEYS-01", f"{name}: data.php trả 200 cho mọi part", False, "; ".join(f"{p}: {show(got[p])}" for p in bad))
        continue
    core, heavy, al = got['core'][1], got['heavy'][1], got['all'][1]
    # KEYS-01: không thiếu/thừa khoá, khoá mảng luôn là list
    keys_ok = set(core) == OLD_KEYS and set(al) == OLD_KEYS and set(heavy) == HEAVY_KEYS
    lists_ok = all(isinstance(d[k], list) for d in (core, al) for k in LIST_KEYS) and isinstance(heavy['scores'], list)
    rec("KEYS-01", f"{name}: đủ khoá cũ (core/heavy/all), không khoá mới; leaveRequests/scores/reports/members/logs là list",
        keys_ok and lists_ok, f"core={sorted(set(core) ^ OLD_KEYS)} all={sorted(set(al) ^ OLD_KEYS)} heavy={sorted(set(heavy) ^ HEAVY_KEYS)} lists={lists_ok}")
    stu = {s['id'] for s in al['students']}
    stu_core = {s['id'] for s in core['students']}
    exp = TOTAL if classes is None else db_counts(classes)
    labs, samp = exp_samples(classes)
    # SCOPE-01 / 01b — scores
    rec("SCOPE-01", f"{name}: studentId của scores ⊆ students (all) và heavy ⊆ students của core",
        {x['studentId'] for x in al['scores']} <= stu and {x['studentId'] for x in heavy['scores']} <= stu_core,
        f"ngoài phạm vi: all={len({x['studentId'] for x in al['scores']} - stu)} heavy={len({x['studentId'] for x in heavy['scores']} - stu_core)}")
    samp_sc = {x['studentId'] for x in al['scores'] if x['termId'] == int(T2) and x['type'] == 'mieng' and x['value'] == 7.25}
    rec("SCOPE-01b", f"{name}: scores đủ đúng phạm vi (số dòng = DB {exp['scores']}, điểm mẫu lớp {labs}), core rỗng, heavy = all",
        len(al['scores']) == exp['scores'] and samp_sc == samp and core['scores'] == [] and len(heavy['scores']) == exp['scores'],
        f"all={len(al['scores'])} heavy={len(heavy['scores'])} core={len(core['scores'])} kỳ vọng {exp['scores']}; mẫu={sorted(samp_sc)} kỳ vọng {sorted(samp)}")
    # SCOPE-04 — leaveRequests
    for p, d in (('core', core), ('all', al)):
        reasons = {x['reason'] for x in d['leaveRequests'] if x['reason'].startswith('NHAYCAM-')}
        exp_r = {f"NHAYCAM-{l}-{i}" for l in labs for i in (1, 2)}
        rec("SCOPE-04", f"{name} ({p}): leaveRequests đúng phạm vi (số dòng = DB {exp['leaveRequests']}, lý do mẫu lớp {labs}, em ⊆ students)",
            len(d['leaveRequests']) == exp['leaveRequests'] and reasons == exp_r
            and {x['studentId'] for x in d['leaveRequests']} <= {s['id'] for s in d['students']},
            f"n={len(d['leaveRequests'])}; lý do={sorted(reasons)}")
    # SCOPE-06 — reports
    for p, d in (('core', core), ('all', al)):
        remarks = {x['studentId'] for x in d['reports'] if x['remark'].startswith('P1-REMARK-')}
        rec("SCOPE-06", f"{name} ({p}): reports đúng phạm vi (số dòng = DB {exp['reports']}, phiếu mẫu lớp {labs}, em ⊆ students)",
            len(d['reports']) == exp['reports'] and remarks == samp
            and {x['studentId'] for x in d['reports']} <= {s['id'] for s in d['students']},
            f"n={len(d['reports'])}; phiếu mẫu lớp={sorted({x['remark'] for x in d['reports'] if x['remark'].startswith('P1-')})}")
    if classes is not None and cB not in classes:
        leakB = [x['reason'] for x in al['leaveRequests'] if x['reason'].startswith('NHAYCAM-B')]
        rec("SCOPE-04b", f"{name}: không có lý do xin phép NHAYCAM-B (lớp ngoài phạm vi)", not leakB, f"lộ={leakB}")
    # SCOPE-07 — admin/bdh bất biến
    if classes is None:
        rec("SCOPE-07", f"{name}: scores/leaveRequests/reports = COUNT(*) toàn năm (không đổi hành vi)",
            len(al['scores']) == TOTAL['scores'] and len(al['leaveRequests']) == TOTAL['leaveRequests'] and len(al['reports']) == TOTAL['reports'],
            f"{len(al['scores'])}/{len(al['leaveRequests'])}/{len(al['reports'])} kỳ vọng {TOTAL}")
    # SCOPE-02 — members
    mem = al['members']; mem_core = core['members']
    pend = [m for m in mem if m['status'] == 'chờ duyệt']
    has_admin = any(m['role'] == 'admin' for m in mem)
    if staff == 'none':
        rec("SCOPE-02", f"{name}: staff=none → members == [] (core và all)", mem == [] and mem_core == [], f"members={len(mem)}/{len(mem_core)}")
    elif name == 'admin':
        rec("SCOPE-02", f"{name}: members có admin, đủ {N_MEM_ALL} người (gồm chờ duyệt, registerNote)",
            has_admin and len(mem) == N_MEM_ALL and any(m['registerNote'] == 'P1-GHICHU-DANGKY' for m in pend), f"n={len(mem)} admin={has_admin}")
    elif staff == 'edit':
        rec("SCOPE-02", f"{name}: members không có admin, có hồ sơ chờ duyệt 0977200001 kèm registerNote, đủ {N_MEM_NOADMIN} người",
            not has_admin and len(mem) == N_MEM_NOADMIN and any(m['phone'] == '0977200001' and m['registerNote'] == 'P1-GHICHU-DANGKY' for m in pend),
            f"n={len(mem)} admin={has_admin} chờ duyệt={len(pend)}")
    else:
        rec("SCOPE-02", f"{name}: staff=view → danh bạ đủ {N_MEM_VIEW} người (có SĐT), không admin, không chờ duyệt, registerNote rỗng",
            len(mem) == N_MEM_VIEW and not has_admin and not pend and all(m['registerNote'] == '' for m in mem)
            and all(m['phone'] for m in mem) and len(mem_core) == N_MEM_VIEW,
            f"n={len(mem)} admin={has_admin} chờ duyệt={len(pend)} note≠''={sum(1 for m in mem if m['registerNote'])}")
    # SCOPE-03 — logs
    if name == 'admin':
        nlog = int(sql("SELECT COUNT(*) FROM activity_logs"))
        top = [int(x) for x in sql("SELECT id FROM activity_logs ORDER BY id DESC LIMIT 50").split()]
        rec("SCOPE-03", f"{name}: logs = 50 dòng mới nhất (min(50, COUNT))",
            len(al['logs']) == min(50, nlog) and [l['id'] for l in al['logs']] == top[:len(al['logs'])], f"logs={len(al['logs'])} COUNT={nlog}")
    else:
        rec("SCOPE-03", f"{name}: logs == [] (không phải Quản trị)", al['logs'] == [] and core['logs'] == [],
            f"logs={len(al['logs'])}, có SĐT trong detail: {PH_LOG(al) if al['logs'] else False}")

# SCOPE-05 — ma trận quyền chỉnh trong app được tôn trọng theo từng phân công
adm = CLIENTS['admin']
before_gvcn = len(CLIENTS['glv_chu_nhiem'].req("/api/data.php?part=heavy")[1]['scores'])
r1 = adm.req("/api/settings.php?action=permission", "POST", {"moduleKey": "scores", "roleCode": "glv", "level": "none"})
r2 = adm.req("/api/settings.php?action=permission", "POST", {"moduleKey": "leave", "roleCode": "glv", "level": "none"})
flush_cache()
g = CLIENTS['glv'].req("/api/data.php")[1] or {}
gt = CLIENTS['glv+thu_thu'].req("/api/data.php")[1] or {}
gac = CLIENTS['glv_A+C'].req("/api/data.php")[1] or {}
gv = CLIENTS['glv_chu_nhiem'].req("/api/data.php")[1] or {}
rec("SCOPE-05", "admin đặt scores/leave của glv=none → glv(A), glv+thu_thu, glv(A+C) nhận scores==[] và leaveRequests==[]; gvcn(A) không đổi",
    okj(r1) and okj(r2) and g.get('scores') == [] and gt.get('scores') == [] and gac.get('scores') == []
    and g.get('leaveRequests') == [] and gt.get('leaveRequests') == [] and gac.get('leaveRequests') == []
    and len(gv.get('scores', [])) == before_gvcn == db_counts({cA})['scores'] and len(gv.get('leaveRequests', [])) == db_counts({cA})['leaveRequests']
    and len(g.get('students', [])) > 0,
    f"glv={len(g.get('scores', []))}/{len(g.get('leaveRequests', []))} glv+tt={len(gt.get('scores', []))} glvAC={len(gac.get('scores', []))} gvcn={len(gv.get('scores', []))} (trước {before_gvcn})")
adm.req("/api/settings.php?action=permission", "POST", {"moduleKey": "scores", "roleCode": "glv", "level": "edit"})
adm.req("/api/settings.php?action=permission", "POST", {"moduleKey": "leave", "roleCode": "glv", "level": "view"})
flush_cache()

# SCOPE-08 — cache: hai lần liên tiếp giống nhau, vẫn đúng phạm vi, ETag ổn định (+ 304)
c = CLIENTS['glv']
a1 = c.req("/api/data.php"); a2 = c.req("/api/data.php")
e1, e2 = a1[3].get('ETag'), a2[3].get('ETag')
r304 = c.req("/api/data.php", headers={"If-None-Match": e1 or ''})
rec("SCOPE-08", "glv(A): data.php 2 lần (lần 2 từ cache) giống hệt, vẫn đúng phạm vi, ETag không đổi, If-None-Match → 304",
    okj(a1) and a1[2] == a2[2] and e1 and e1 == e2 and r304[0] == 304
    and len(a2[1]['scores']) == db_counts({cA})['scores'] and len(a2[1]['leaveRequests']) == db_counts({cA})['leaveRequests'],
    f"giống={a1[2] == a2[2]} etag={e1}/{e2} 304={r304[0]}")

# LOG-01 — logs.php
r = adm.req("/api/logs.php")
rec("LOG-01", "logs.php: admin → 200 có data (list, không rỗng)", okj(r) and isinstance(r[1].get('data'), list) and len(r[1]['data']) > 0, show(r)[:100])
for name in ('bdh', 'glv', 'thu_thu'):
    r = CLIENTS[name].req("/api/logs.php")
    rec("LOG-01", f"logs.php: {name} → 403", r[0] == 403, show(r)[:100])

# PERF-01 — TTFB data.php (không cache) — so với số đo trước (nếu có baseline)
def ttfb(cl, part, n=5):
    ts = []
    for _ in range(n):
        flush_cache(); t0 = time.perf_counter(); cl.req(f"/api/data.php?part={part}"); ts.append(time.perf_counter() - t0)
    return statistics.median(ts)
perf = {f"{n}:{p}": ttfb(CLIENTS[n], p) for n in ('glv', 'admin') for p in ('core', 'heavy')}
print("PERF", json.dumps({k: round(v * 1000, 1) for k, v in perf.items()}), "ms")
if os.environ.get('P1_PERF_OUT'):
    json.dump(perf, open(os.environ['P1_PERF_OUT'], 'w'))
if os.environ.get('P1_PERF_BASELINE') and os.path.exists(os.environ['P1_PERF_BASELINE']):
    base = json.load(open(os.environ['P1_PERF_BASELINE']))
    for k, v in perf.items():
        rec("PERF-01", f"TTFB data.php {k} không tăng quá 10% (+5ms nhiễu) so với trước",
            v <= base[k] * 1.10 + 0.005, f"{v * 1000:.1f}ms so với {base[k] * 1000:.1f}ms")
flush_cache()

# =============================================================== #97 — STAFF-06 mở rộng (5.3)
bdh = CLIENTS['bdh']
SNAP = "SELECT CONCAT_WS('|',holy_name,full_name,phone,IFNULL(title_id,'-'),role_code,password_hash,status) FROM members WHERE id="
def snap(i): return sql(SNAP + str(i))
def body(i, **kw):
    b = {"id": int(i), "holyName": "Giuse", "fullName": "Bị Đổi Tên", "phone": "0999000111", "role": "glv", "title": "", "block": "", "className": ""}
    b.update(kw); return b
s_adm, s_b2, s_bdh = snap(ADMIN_ID), snap(BDH2_ID), snap(BDH_ID)
# để hoàn lại hồ sơ admin nếu mã cũ (master) để lọt thay đổi
ADM_RESTORE = sql(f"SELECT CONCAT('holy_name=',QUOTE(holy_name),',full_name=',QUOTE(full_name),',phone=',QUOTE(phone),',title_id=',QUOTE(title_id)) FROM members WHERE id={ADMIN_ID}")
rA = bdh.req("/api/org.php?action=saveMember", "POST", body(ADMIN_ID, role="admin"))
unchanged = snap(ADMIN_ID) == s_adm
lc, ljs = Client().login("0901000001", "tntt@2026")
rec("STAFF-06a", "bdh saveMember id admin (đổi tên+SĐT) → 404, DB không đổi, admin vẫn đăng nhập bằng SĐT cũ",
    rA[0] == 404 and unchanged and lc == 200, f"{show(rA)} DB không đổi={unchanged} login={lc}")
s_adm = snap(ADMIN_ID)  # đăng nhập có thể nâng cấp password_hash (password_verify_upgrade)
r = bdh.req("/api/org.php?action=saveMember", "POST", body(BDH2_ID, role="bdh", phone="0911000010"))
rec("STAFF-06b", "bdh saveMember bdh khác → 403, DB không đổi", r[0] == 403 and snap(BDH2_ID) == s_b2, show(r))
TITLE_Q = f"SELECT IFNULL(title_id,'-') FROM members WHERE id={BDH_ID}"
old_title = sql(TITLE_Q)
new_title_label = sql(f"SELECT label FROM titles WHERE role_code='bdh' AND id<>{old_title if old_title != '-' else 0} ORDER BY sort_order DESC LIMIT 1")
r = bdh.req("/api/org.php?action=saveMember", "POST", body(BDH_ID, role="bdh", phone="0911000001", fullName="Bdh Tự Sửa", title=new_title_label))
new_title = sql(TITLE_Q)
rec("STAFF-06c", f"bdh saveMember chính mình (đổi họ tên, gửi chức danh '{new_title_label}') → 200, họ tên đổi, title_id KHÔNG đổi",
    okj(r) and sql(f"SELECT full_name FROM members WHERE id={BDH_ID}") == "Bdh Tự Sửa" and new_title == old_title,
    f"{show(r)} title_id={new_title} (trước {old_title})")
sql(f"UPDATE members SET full_name='User T_BDH', title_id={'NULL' if old_title == '-' else old_title} WHERE id={BDH_ID}")
n_before = sql("SELECT COUNT(*) FROM members")
rd = [bdh.req("/api/org.php?action=deleteMember", "POST", {"id": int(i)}) for i in (ADMIN_ID, BDH2_ID, BDH_ID)]
rec("STAFF-06d", "bdh deleteMember admin / bdh khác / chính mình → 404 / 403 / 403, không xoá",
    [x[0] for x in rd] == [404, 403, 403] and sql("SELECT COUNT(*) FROM members") == n_before, " | ".join(show(x)[:70] for x in rd))
re_ = [bdh.req("/api/org.php?action=resetPassword", "POST", {"id": int(i)}) for i in (ADMIN_ID, BDH2_ID)]
rec("STAFF-06e", "bdh resetPassword admin / bdh khác → 404 / 403, password_hash không đổi",
    [x[0] for x in re_] == [404, 403] and snap(ADMIN_ID) == s_adm and snap(BDH2_ID) == s_b2, " | ".join(show(x)[:70] for x in re_))
ra = bdh.req("/api/org.php?action=approveMember", "POST", {"id": int(ADMIN_ID), "role": "glv"})
rr = bdh.req("/api/org.php?action=rejectMember", "POST", {"id": int(ADMIN_ID)})
rec("STAFF-06f", "bdh approveMember / rejectMember với id admin → 404 / 404", ra[0] == 404 and rr[0] == 404 and snap(ADMIN_ID) == s_adm,
    f"{show(ra)[:70]} | {show(rr)[:70]}")
r = adm.req("/api/org.php?action=saveMember", "POST", body(BDH2_ID, role="bdh", phone="0911000010", fullName="Bdh Hai Admin Sửa"))
rec("STAFF-06g", "admin saveMember bdh (danh tính) → 200 (không hồi quy)",
    okj(r) and sql(f"SELECT full_name FROM members WHERE id={BDH2_ID}") == "Bdh Hai Admin Sửa", show(r))
s_glv = snap(GLV_ID)
r = bdh.req("/api/org.php?action=saveMember", "POST", body(GLV_ID, role="glv", phone="0911000004", fullName="Glv Bdh Sửa", className=nameA))
rec("STAFF-06h", "bdh saveMember glv → 200 (STAFF-01 với bdh)", okj(r) and sql(f"SELECT full_name FROM members WHERE id={GLV_ID}") == "Glv Bdh Sửa", show(r))
sql(f"UPDATE members SET full_name='User T_GLV', holy_name=NULL WHERE id={GLV_ID}")
r = adm.req("/api/org.php?action=resetPassword", "POST", {"id": int(BDH2_ID)})
rec("STAFF-06i", "admin resetPassword bdh → 200, must_change_pw=1",
    okj(r) and sql(f"SELECT must_change_pw FROM members WHERE id={BDH2_ID}") == '1' and snap(BDH2_ID) != s_b2, show(r)[:90])
pairs = []
for act, extra in (("saveMember", None), ("deleteMember", {}), ("resetPassword", {}), ("approveMember", {"role": "glv"}), ("rejectMember", {})):
    mk = (lambda i: body(i, role="admin")) if extra is None else (lambda i, e=extra: dict({"id": int(i)}, **e))
    ra_, rx_ = bdh.req(f"/api/org.php?action={act}", "POST", mk(ADMIN_ID)), bdh.req(f"/api/org.php?action={act}", "POST", mk(999999))
    pairs.append((act, ra_[0], rx_[0], (ra_[1] or {}).get('error'), (rx_[1] or {}).get('error')))
rec("STAFF-06j", "Phản hồi cho id admin GIỐNG HỆT id không tồn tại (404 + cùng chuỗi error) ở mọi thao tác nhân sự",
    all(a == b == 404 and e1_ == e2_ and e1_ for _, a, b, e1_, e2_ in pairs), "; ".join(f"{p[0]}:{p[1]}/{p[2]} {p[3]!r}={p[4]!r}" for p in pairs))
r = bdh.req("/api/org.php?action=deleteMember", "POST", {"id": int(GBDH_ID)})
rec("STAFF-06k", "bdh deleteMember người vai gốc glv nhưng kiêm phân công bdh → 403, không xoá (isProtected mở rộng)",
    r[0] == 403 and sql(f"SELECT COUNT(*) FROM members WHERE id={GBDH_ID}") == '1', show(r))
r = adm.req("/api/org.php?action=deleteMember", "POST", {"id": int(ADMIN_ID)})
rec("STAFF-06l", "admin tự xoá chính mình → 400, không xoá", r[0] == 400 and sql(f"SELECT COUNT(*) FROM members WHERE id={ADMIN_ID}") == '1', show(r))
r = bdh.req("/api/org.php?action=approveMember", "POST", {"id": int(PENDING_ID), "role": "glv"})
rec("STAFF-06m", "bdh vẫn duyệt được hồ sơ chờ duyệt (không hồi quy)",
    okj(r) and sql(f"SELECT status FROM members WHERE id={PENDING_ID}") == 'đang phục vụ', show(r))
sql(f"UPDATE members SET password_hash='{HASH}', must_change_pw=0 WHERE id={BDH2_ID}")
sql(f"UPDATE members SET {ADM_RESTORE} WHERE id={ADMIN_ID}")

# =============================================================== #83 — must_change_pw (5.4)
sql("DELETE FROM login_attempts")
mc = Client()
code, ljs = mc.login("0911000009", "Test@1234")
tok = (ljs or {}).get('csrfToken') if isinstance(ljs, dict) else None
mc.csrf = tok
rec("AUTH-08a", "login của tài khoản must=1 trả csrfToken 64 hex, user.mustChangePw=true",
    code == 200 and isinstance(tok, str) and bool(HEX64.fullmatch(tok)) and ljs['user']['mustChangePw'] is True, f"HTTP {code} csrfToken={tok!r}")
r = mc.req("/api/data.php")
rec("AUTH-05", "must=1: GET data.php → 403 code=must_change_pw", must_blocked(r), show(r))
sidA = SAMPLE['A'][0]
EPS = [
    ("GET", "/api/students.php?action=list", None),
    ("POST", "/api/attendance.php?action=mark", {}),
    ("POST", "/api/leave.php?action=create", {}),
    ("POST", "/api/scores.php?action=save", {}),
    ("GET", "/api/reports.php?action=list", None),
    ("GET", f"/api/export.php?action=attendance-detail&classId={cA}", None),
    ("GET", f"/print.php?type=report&studentId={sidA}&termId={T1}", None),
    ("GET", "/api/library.php?action=list", None),
    ("GET", "/api/library_file.php?id=1", None),
    ("GET", "/api/logs.php", None),
    ("GET", "/api/notes.php?action=list", None),
    ("POST", "/api/announcements.php?action=save", {}),
    ("GET", "/api/assignments.php?action=list_active", None),
    ("POST", "/api/org.php?action=saveMember", {"id": int(MC_ID)}),
    ("GET", "/api/passkey.php?action=status", None),
    ("GET", "/api/passkey.php?action=getRegisterArgs", None),
    ("POST", "/api/passkey.php?action=processRegister", {}),
    ("POST", "/api/passkey.php?action=delete", {}),
    ("GET", "/api/push.php?action=status", None),
    ("POST", "/api/push.php?action=subscribe", {"endpoint": "https://push.example/p1-must"}),
    ("POST", "/api/push.php?action=unsubscribe", {"endpoint": "https://push.example/p1-must"}),
    ("POST", "/api/push.php?action=test", {}),
    ("POST", "/api/push.php?action=pending", {}),
    ("POST", "/api/auth.php?action=profile", {"fullName": "Doi Ten Truoc", "phone": "0911000099"}),
    ("GET", "/api/rewards.php?action=lookup", None),
    ("GET", "/api/gifts.php?action=list", None),
    ("POST", "/api/promotion.php?action=preview", {}),
    ("POST", "/api/programs.php?action=save", {}),
    ("GET", "/api/years.php?action=list", None),
    ("GET", "/api/settings.php?action=permission", None),
]
for m, path, b in EPS:
    r = mc.req(path, m, b)
    rec("AUTH-05b", f"must=1: {m} {path.split('?')[0]}?{path.split('?')[1] if '?' in path else ''} → 403 must_change_pw", must_blocked(r), show(r)[:120])
rec("AUTH-05b", "must=1: profile KHÔNG đổi được SĐT/họ tên trước khi đổi mật khẩu",
    sql(f"SELECT phone FROM members WHERE id={MC_ID}") == '0911000009', sql(f"SELECT CONCAT(full_name,' ',phone) FROM members WHERE id={MC_ID}"))
rec("AUTH-05b", "must=1: không tạo được push_subscriptions", sql(f"SELECT COUNT(*) FROM push_subscriptions WHERE member_id={MC_ID}") == '0', "")
# nếu mã chưa chặn (master/giữa chừng) thì profile đã đổi SĐT — hoàn lại để các ca sau đăng nhập được
sql(f"UPDATE members SET phone='0911000009', full_name='User T_MC9' WHERE id={MC_ID}")
sql(f"DELETE FROM push_subscriptions WHERE member_id={MC_ID}")
r = mc.req("/api/auth.php?action=me")
rec("AUTH-05c", "must=1: auth.php?action=me → 200, user.mustChangePw == true",
    okj(r) and r[1]['user']['mustChangePw'] is True and r[1]['user']['phone'] == '0911000009', show(r)[:100])
r = mc.req("/")
m_ = re.search(r'data-csrf="([0-9a-f]{64})"', r[2])
has_flag = 'data-must-change="1"' in r[2]
rec("AUTH-08c", "GET / khi must=1: HTML có data-must-change=\"1\" và data-csrf 64 hex (= token của phiên)",
    r[0] == 200 and has_flag and bool(m_) and (tok is None or m_.group(1) == tok),
    f"must-change={has_flag} csrf={m_.group(1)[:8] + '…' if m_ else None}")
r = mc.req("/api/auth.php?action=password", "POST", {"current": "Test@1234", "new": "NewPw@123"}, csrf=False)
rec("AUTH-08b", "POST password KHÔNG có token → 403 CSRF (không nới lỏng), mật khẩu không đổi",
    r[0] == 403 and 'CSRF' in r[2] and sql(f"SELECT must_change_pw FROM members WHERE id={MC_ID}") == '1', show(r))
r = mc.req("/api/auth.php?action=password", "POST", {"current": "Test@1234", "new": "NewPw@123"})
d = mc.req("/api/data.php")
rec("AUTH-08", "POST password có token từ login (không mở index.php trước) → 200; sau đó data.php 200, must_change_pw=0",
    okj(r) and okj(d) and sql(f"SELECT must_change_pw FROM members WHERE id={MC_ID}") == '0', f"password: {show(r)[:80]} | data: HTTP {d[0]}")
MC_PW = "NewPw@123" if okj(r) else "Test@1234"
sql(f"UPDATE members SET must_change_pw=0 WHERE id={MC_ID}")  # tiếp tục các ca sau kể cả khi AUTH-08 hỏng (chạy trên master)

# ---- AUTH-09: Passkey (WebAuthn: khoá EC P-256 + chữ ký ECDSA sinh bằng openssl của php-cli, rpId=localhost)
def php_run(code, stdin='', env=None):
    return subprocess.run(["php", "-r", code], input=stdin, capture_output=True, text=True,
                          env=dict(os.environ, **(env or {}))).stdout


class EcKey:
    def __init__(self):
        k = json.loads(php_run('$k=openssl_pkey_new(["private_key_type"=>OPENSSL_KEYTYPE_EC,"curve_name"=>"prime256v1"]);'
                               'openssl_pkey_export($k,$pem);$d=openssl_pkey_get_details($k);'
                               'echo json_encode(["pem"=>$pem,"x"=>base64_encode($d["ec"]["x"]),"y"=>base64_encode($d["ec"]["y"])]);'))
        self.pem = k['pem']
        self.x = base64.b64decode(k['x']).rjust(32, b'\x00'); self.y = base64.b64decode(k['y']).rjust(32, b'\x00')

    def sign(self, data):
        return base64.b64decode(php_run('openssl_sign(base64_decode(stream_get_contents(STDIN)),$s,openssl_pkey_get_private(getenv("P1_PEM")),OPENSSL_ALGO_SHA256);'
                                        'echo base64_encode($s);', base64.b64encode(data).decode(), {"P1_PEM": self.pem}))


LBASE = re.sub(r'//[^/:]+', '//localhost', BASE, count=1)


def lreq(cl, *a, **k):
    global BASE
    old = BASE; BASE = LBASE
    try: return cl.req(*a, **k)
    finally: BASE = old


def cbor(x):
    def head(mj, n):
        if n < 24: return bytes([mj << 5 | n])
        if n < 256: return bytes([mj << 5 | 24, n])
        if n < 65536: return bytes([mj << 5 | 25]) + struct.pack('>H', n)
        return bytes([mj << 5 | 26]) + struct.pack('>I', n)
    if isinstance(x, int): return head(0, x) if x >= 0 else head(1, -1 - x)
    if isinstance(x, bytes): return head(2, len(x)) + x
    if isinstance(x, str): b = x.encode(); return head(3, len(b)) + b
    if isinstance(x, dict): return head(5, len(x)) + b''.join(cbor(k) + cbor(v) for k, v in x.items())
    raise TypeError(x)


def b64u(b): return base64.urlsafe_b64encode(b).rstrip(b'=').decode()
def b64(b): return base64.b64encode(b).decode()
def lbuchs_bin(v): return base64.b64decode(v[len('=?BINARY?B?'):-2]) if isinstance(v, str) and v.startswith('=?BINARY?B?') else base64.urlsafe_b64decode(v + '==')


PK = EcKey(); CRED = secrets.token_bytes(16); RPH = hashlib.sha256(b'localhost').digest()
pkc = Client(); lreq(pkc, "/api/auth.php?action=login", "POST", {"phone": "0911000009", "password": MC_PW}, csrf=False)
rr_ = lreq(pkc, "/"); mm = re.search(r'([0-9a-f]{64})', rr_[2]); pkc.csrf = mm.group(1) if mm else None
ra_ = lreq(pkc, "/api/passkey.php?action=getRegisterArgs")
reg_ok = False
if okj(ra_):
    ch = lbuchs_bin(ra_[1]['args']['publicKey']['challenge'])
    cose = cbor({1: 2, 3: -7, -1: 1, -2: PK.x, -3: PK.y})
    auth = RPH + bytes([0x45]) + struct.pack('>I', 0) + b'\x00' * 16 + struct.pack('>H', len(CRED)) + CRED + cose
    cdj = json.dumps({"type": "webauthn.create", "challenge": b64u(ch), "origin": LBASE}).encode()
    rg = lreq(pkc, "/api/passkey.php?action=processRegister", "POST",
              {"clientDataJSON": b64(cdj), "attestationObject": b64(cbor({"fmt": "none", "attStmt": {}, "authData": auth}))})
    reg_ok = okj(rg)
rec("AUTH-09a", "must=0: đăng ký Passkey (processRegister, attestation none) thành công, lưu member_passkeys",
    reg_ok and sql(f"SELECT COUNT(*) FROM member_passkeys WHERE member_id={MC_ID}") == '1', show(ra_)[:80])


def passkey_login(counter):
    cl = Client(); a = lreq(cl, "/api/passkey.php?action=getLoginArgs")
    ch = lbuchs_bin(a[1]['args']['publicKey']['challenge'])
    authd = RPH + bytes([0x05]) + struct.pack('>I', counter)
    cdj = json.dumps({"type": "webauthn.get", "challenge": b64u(ch), "origin": LBASE}).encode()
    sig = PK.sign(authd + hashlib.sha256(cdj).digest())
    r = lreq(cl, "/api/passkey.php?action=processLogin", "POST",
             {"id": b64u(CRED), "clientDataJSON": b64(cdj), "authenticatorData": b64(authd), "signature": b64(sig)}, csrf=False)
    return r, lreq(cl, "/api/auth.php?action=me")


r, me_ = passkey_login(1)
rec("AUTH-09b", "Đối chứng: must=0 đăng nhập Passkey → 200 và có phiên (chữ ký hợp lệ)", okj(r) and okj(me_), f"{show(r)[:90]} | me={me_[2][:60]!r}")
sql(f"UPDATE members SET must_change_pw=1 WHERE id={MC_ID}")
r, me_ = passkey_login(2)
rec("AUTH-09", "must=1: processLogin Passkey → 403 must_change_pw, KHÔNG tạo phiên (me → ok:false)",
    must_blocked(r) and isinstance(me_[1], dict) and me_[1].get('ok') is False, f"{show(r)[:120]} | me={me_[2][:60]!r}")

# ---- AUTH-10: push pending theo endpoint vẫn chạy cho user must=1, không phiên
EP_ = "https://push.example/p1-" + secrets.token_hex(8)
sql(f"INSERT INTO push_subscriptions (member_id,endpoint,created_at) VALUES ({MC_ID},'{EP_}',NOW())")
sql(f"INSERT INTO push_outbox (member_id,title,body,url,tag,created_at) VALUES ({MC_ID},'P1 tiêu đề','P1 nội dung','/','p1',NOW())")
r = Client().req("/api/push.php?action=pending", "POST", {"endpoint": EP_}, csrf=False)
rec("AUTH-10", "must=1: push.php?action=pending có endpoint, không phiên → 200 lấy được thông báo (hành vi thiết kế)",
    okj(r) and (r[1].get('item') or {}).get('title') == 'P1 tiêu đề', show(r)[:120])

# ---- AUTH-13: logout khi must=1
lo = Client(); c_, j_ = lo.login("0911000009", MC_PW); lo.csrf = (j_ or {}).get('csrfToken')
r = lo.req("/api/auth.php?action=logout", "POST", {})
me_ = lo.req("/api/auth.php?action=me")
rec("AUTH-13", "must=1: logout → 200, phiên huỷ (me → ok:false)", c_ == 200 and okj(r) and me_[1].get('ok') is False, f"{show(r)[:60]} | me={me_[2][:60]!r}")

# ---- AUTH-12: đang có phiên, admin cấp lại mật khẩu → request kế tiếp 403 must_change_pw
g2 = logged("0911000004")
pre = g2.req("/api/data.php?part=core")
r = adm.req("/api/org.php?action=resetPassword", "POST", {"id": int(GLV_ID)})
post = g2.req("/api/data.php?part=core")
post2 = g2.req("/api/notes.php?action=list")
rec("AUTH-12", "Đang dùng app, bị admin cấp lại mật khẩu → request API kế tiếp 403 must_change_pw (data.php, notes.php)",
    okj(pre) and okj(r) and must_blocked(post) and must_blocked(post2), f"trước={pre[0]} reset={r[0]} sau={show(post)[:80]} notes={post2[0]}")
sql(f"UPDATE members SET password_hash='{HASH}', must_change_pw=0 WHERE id={GLV_ID}")

# ---- AUTH-11: đăng ký công khai + duyệt → vào được, mustChangePw=false
sql("DELETE FROM login_attempts")
anon = Client()
r = anon.req("/api/auth.php?action=register", "POST", {"fullName": "P1 Tu Dang Ky", "phone": "0977200099", "password": "abcdef",
                                                        "birthDate": "1995-01-01"}, csrf=False)
nid = sql("SELECT id FROM members WHERE phone='0977200099'")
ra = bdh.req("/api/org.php?action=approveMember", "POST", {"id": int(nid or 0), "role": "glv"})
nc = Client(); c_, j_ = nc.login("0977200099", "abcdef")
d = nc.req("/api/data.php?part=core")
rec("AUTH-11", "Đăng ký công khai + BĐH duyệt → đăng nhập được, mustChangePw=false, data.php 200",
    okj(r) and okj(ra) and c_ == 200 and j_['user']['mustChangePw'] is False and okj(d), f"reg={r[0]} duyệt={ra[0]} login={c_} data={d[0]}")

cleanup()
sql(f"UPDATE members SET full_name='User T_BDH' WHERE id={BDH_ID}")
p = sum(1 for x in results if x[2]); print("TOTAL", len(results), "PASS", p, "FAIL", len(results) - p)
json.dump(results, open(os.path.join(HERE, "out", "p1_results.json"), "w"), ensure_ascii=False, indent=1)
