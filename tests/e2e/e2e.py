"""Quét bảo mật API: endpoint chưa đăng nhập, file debug, throttle đăng nhập, CSRF, ma trận quyền, tiêm nhiễm, upload.
Chạy trên DB THỬ NGHIỆM (không phải DB thật). Xem BAO_CAO_KIEM_THU.md mục 9 để biết cách dựng môi trường.
Yêu cầu: php -S 127.0.0.1:8088 -t public (đã trỏ TNTT_DB_* vào DB thử), mysql client chạy được bằng root không mật khẩu, DB tên trong biến DB bên dưới.
"""
import os
HERE = os.path.dirname(os.path.abspath(__file__))
#!/usr/bin/env python3
"""API / security e2e checks against a local php -S server on a throwaway DB."""
import json, re, subprocess, sys, urllib.request, urllib.parse, http.cookiejar

import os as _os
BASE = _os.environ.get("E2E_BASE", "http://127.0.0.1:8088")
DB = _os.environ.get("E2E_DB", "tntt_e2e")
results = []  # (id, title, ok, detail)


def sql(q):
    return subprocess.run(["mysql", "-uroot", DB, "-N", "-B", "-e", q], capture_output=True, text=True).stdout.strip()


class Client:
    def __init__(self):
        self.cj = http.cookiejar.CookieJar()
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cj))
        self.csrf = None

    def req(self, path, method="GET", data=None, raw=None, headers=None, csrf=True):
        url = BASE + path
        h = dict(headers or {})
        body = None
        if raw is not None:
            body = raw if isinstance(raw, bytes) else raw.encode()
        elif data is not None:
            body = json.dumps(data).encode()
            h.setdefault("Content-Type", "application/json")
        if method == "POST" and csrf and self.csrf:
            h["X-CSRF-Token"] = self.csrf
        r = urllib.request.Request(url, data=body, method=method, headers=h)
        try:
            resp = self.op.open(r, timeout=30)
            code, text, hd = resp.status, resp.read().decode("utf-8", "replace"), resp.headers
        except urllib.error.HTTPError as e:
            code, text, hd = e.code, e.read().decode("utf-8", "replace"), e.headers
        try:
            js = json.loads(text)
        except Exception:
            js = None
        return code, js, text, hd

    def login(self, phone, pw):
        c, js, t, _ = self.req("/api/auth.php?action=login", "POST", {"phone": phone, "password": pw}, csrf=False)
        return c, js


def rec(i, title, ok, detail=""):
    results.append((i, title, bool(ok), detail))
    print(("PASS " if ok else "FAIL ") + i + " " + title + ("  -> " + detail if detail else ""))


# ------------------------------------------------------------------ helpers to build users
HASH = subprocess.run(["php", "-r", "echo password_hash('Test@1234', PASSWORD_DEFAULT);"], capture_output=True, text=True).stdout


def mk_member(code, phone, role, block=None, cls=None, must=0):
    sql(f"DELETE FROM member_assignments WHERE member_id IN (SELECT id FROM members WHERE phone='{phone}')")
    sql(f"DELETE FROM members WHERE phone='{phone}'")
    b = block or "NULL"
    c = cls or "NULL"
    sql(f"INSERT INTO members (code,full_name,phone,password_hash,role_code,block_id,class_id,status,must_change_pw) "
        f"VALUES ('{code}','User {code}','{phone}','{HASH}','{role}',{b},{c},'đang phục vụ',{must})")
    mid = sql(f"SELECT id FROM members WHERE phone='{phone}'")
    admin = sql("SELECT id FROM members WHERE role_code='admin' ORDER BY id LIMIT 1")
    sql(f"INSERT INTO member_assignments (member_id,role_code,block_id,class_id,is_primary,from_date,assigned_by) "
        f"VALUES ({mid},'{role}',{b},{c},1,CURDATE(),{admin})")
    return mid


classes = sql("SELECT id,block_id FROM classes ORDER BY id LIMIT 30").splitlines()
cA, bA = classes[0].split("\t")
# a class in a different block
cB = bB = None
for l in classes:
    cid, bid = l.split("\t")
    if bid != bA:
        cB, bB = cid, bid
        break

users = {
    "admin": ("0901000001", "tntt@2026"),
    "bdh": ("0911000001", "Test@1234"),
    "truong_khoi": ("0911000002", "Test@1234"),
    "glv_chu_nhiem": ("0911000003", "Test@1234"),
    "glv": ("0911000004", "Test@1234"),
    "du_bi": ("0911000005", "Test@1234"),
    "thu_thu": ("0911000006", "Test@1234"),
}
mk_member("T_BDH", "0911000001", "bdh")
mk_member("T_TK", "0911000002", "truong_khoi", block=bA)
mk_member("T_GVCN", "0911000003", "glv_chu_nhiem", block=bA, cls=cA)
mk_member("T_GLV", "0911000004", "glv", block=bA, cls=cA)
mk_member("T_DB", "0911000005", "du_bi", block=bA, cls=cA)
mk_member("T_TT", "0911000006", "thu_thu")

# ------------------------------------------------------------------ 1. unauthenticated sweep
anon = Client()
public_ok = {"auth.php", "sync.php", "csrf.php", "cache.php", "test_timezone.php", "test_webauthn.php"}
import glob, os
api_dir = "/home/user/glyphutrung/public/api"
leaks = []
for f in sorted(glob.glob(api_dir + "/*.php")):
    n = os.path.basename(f)
    if n.startswith("_") or n in ("OrgService.php", "StaffService.php", "StampService.php"):
        continue
    src = open(f, encoding="utf-8").read()
    acts = re.findall(r"case '([a-zA-Z_.-]+)'", src) or ["list", ""]
    for a in acts[:14]:
        for m in ("GET", "POST"):
            c, js, t, _ = anon.req(f"/api/{n}?action={a}", m, {} if m == "POST" else None, csrf=False)
            authed_reject = c in (401, 403, 405) or (isinstance(js, dict) and js.get("ok") is False)
            if not authed_reject and n not in public_ok:
                leaks.append(f"{m} {n}?action={a} -> {c} {t[:80]!r}")
rec("SEC-01", "Mọi endpoint api/* từ chối request chưa đăng nhập", not leaks, "; ".join(leaks[:6]))

# unauth exposures worth flagging
for path in ["/api/test_timezone.php", "/api/test_webauthn.php", "/check_times.php", "/rebuild_login.php",
             "/error_log", "/api/error_log", "/api/openapi.yaml", "/read_step.php"]:
    c, js, t, _ = anon.req(path)
    exposed = c == 200
    rec("SEC-02", f"File dev/debug không truy cập được công khai: {path}", not exposed,
        f"HTTP {c}, {len(t)} bytes" if exposed else f"HTTP {c}")

# ------------------------------------------------------------------ 2. login + throttle
c, js = anon.login("0901000001", "wrong")
rec("AUTH-01", "Sai mật khẩu -> 401", c == 401, str(c))
for i in range(6):
    c, js = anon.login("0901000001", "wrong")
rec("AUTH-02", "Sai >5 lần -> bị khóa 429", c == 429, str(c))
sql("DELETE FROM login_attempts")
c, js = Client().login("0901000001", "tntt@2026")
rec("AUTH-03", "Đăng nhập đúng admin", c == 200 and isinstance(js, dict) and js.get("ok"), str(js)[:120])
mustpw = js["user"]["mustChangePw"] if isinstance(js, dict) and js.get("user") else None
rec("AUTH-04", "Tài khoản admin mới cài yêu cầu đổi mật khẩu (must_change_pw)", mustpw is True, f"mustChangePw={mustpw}")

# must_change_pw bypass on API
cl = Client()
cl.login("0901000001", "tntt@2026")
c, js, t, _ = cl.req("/api/data.php")
rec("AUTH-05", "API data.php bị chặn khi must_change_pw=1 (chưa đổi mật khẩu mặc định)", c in (403, 401) or (js and not js.get("ok", True)),
    f"HTTP {c} keys={list(js)[:5] if isinstance(js, dict) else None}")

# session fixation / cookie flags
c, js, t, hd = Client().req("/api/auth.php?action=me")
sc = hd.get("Set-Cookie", "")
rec("AUTH-06", "Cookie phiên HttpOnly + SameSite", "HttpOnly" in sc and "SameSite" in sc, sc)

# logout via GET (CSRF logout)
cl2 = Client(); cl2.login("0911000001", "Test@1234")
c, js, t, _ = cl2.req("/api/auth.php?action=logout", "GET")
rec("AUTH-07", "Logout không cho phép GET (chống CSRF-logout)", c == 405, f"HTTP {c}")

# ------------------------------------------------------------------ 3. CSRF
def logged(role):
    ph, pw = users[role]
    cl = Client()
    c, js = cl.login(ph, pw)
    if not (isinstance(js, dict) and js.get("ok")):
        return None
    # fetch csrf token from boot data on index
    c, _, t, _ = cl.req("/")
    m = re.search(r'"csrf[A-Za-z_]*"\s*:\s*"([0-9a-f]{64})"', t)
    if not m:
        m = re.search(r'([0-9a-f]{64})', t)
    cl.csrf = m.group(1) if m else None
    return cl

bdh = logged("bdh")
rec("CSRF-00", "Lấy được CSRF token từ trang chính sau đăng nhập", bdh and bdh.csrf, str(bool(bdh and bdh.csrf)))
if bdh:
    c, js, t, _ = bdh.req("/api/notes.php?action=save", "POST", {"title": "x", "body": "y"}, csrf=False)
    rec("CSRF-01", "POST không có token bị từ chối (403)", c == 403, f"HTTP {c} {t[:80]}")
    c, js, t, _ = bdh.req("/api/notes.php?action=save", "POST", {"title": "x"}, headers={"X-CSRF-Token": "0" * 64}, csrf=False)
    rec("CSRF-02", "POST token sai bị từ chối (403)", c == 403, f"HTTP {c}")
    c, js, t, _ = bdh.req("/api/programs.php?action=delete&id=1", "GET")
    rec("CSRF-03", "Hành động ghi qua GET bị chặn (405)", c == 405, f"HTTP {c}")

# ------------------------------------------------------------------ 4. permission matrix on module APIs
perm_rows = sql("SELECT module_key,role_code,level FROM permissions").splitlines()
perm = {}
for r in perm_rows:
    k, rc, lv = r.split("\t")
    perm[(k, rc)] = lv

reads = {  # module -> a read URL
    "students": "/api/students.php?action=next_code",
    "years": "/api/years.php?action=list",
    "gifts": "/api/gifts.php?action=list",
    "org": "/api/org.php?action=list",
    "library": "/api/library.php?action=list",
    "notes": "/api/notes.php?action=list",
    "announcements": "/api/announcements.php?action=list",
    "leave": "/api/leave.php?action=list",
    "logs": "/api/logs.php",
    "settings": "/api/settings.php?action=permission",
    "assignments": "/api/assignments.php?action=list",
}
mods = {"students": "students", "years": "years", "gifts": "gifts", "library": "thu_vien", "logs": "settings", "settings": "settings", "assignments": "staff"}
for role in users:
    cl = logged(role)
    if not cl:
        rec("PERM-LOGIN", f"Đăng nhập được vai {role}", False)
        continue
    for name, url in reads.items():
        c, js, t, _ = cl.req(url)
        mk = mods.get(name, name)
        expect = perm.get((mk, role), "none")
        denied = c in (401, 403, 405) or (isinstance(js, dict) and js.get("ok") is False and "quyền" in json.dumps(js, ensure_ascii=False))
        if role == "admin":
            continue
        if expect == "none":
            rec("PERM-" + role, f"{role} không có quyền '{mk}' -> bị chặn ({name})", denied, f"HTTP {c} {t[:70]!r}")

# admin-only settings actions from non-admins
for role in ("bdh", "truong_khoi", "glv_chu_nhiem", "glv", "du_bi", "thu_thu"):
    cl = logged(role)
    c, js, t, _ = cl.req("/api/settings.php?action=clearLogs", "POST", {})
    rec("PERM-ADM", f"{role} không xoá được nhật ký / đổi phân quyền (settings)", c in (401, 403), f"HTTP {c} {t[:80]!r}")
    c, js, t, _ = cl.req("/api/org.php?action=resetPassword", "POST", {"id": 1})
    rec("PERM-ADM2", f"{role} không reset mật khẩu admin (org.resetPassword)", c in (401, 403) or (isinstance(js, dict) and js.get("ok") is False), f"HTTP {c} {t[:80]!r}")

# ------------------------------------------------------------------ 5. IDOR: student of another class
sidB = sql(f"SELECT student_id FROM enrollments WHERE class_id={cB} LIMIT 1") if cB else None
sidA = sql(f"SELECT student_id FROM enrollments WHERE class_id={cA} LIMIT 1")
glv = logged("glv")
gvcn = logged("glv_chu_nhiem")
for role, cl in (("glv", glv), ("glv_chu_nhiem", gvcn)):
    c, js, t, _ = cl.req(f"/api/data.php")
    names = re.findall(r'"fullName"', t)
    sB = sql(f"SELECT full_name FROM students WHERE id={sidB}") if sidB else ""
    leaked = bool(sB) and (sB in t)
    rec("IDOR-01", f"{role} lớp A không thấy hồ sơ em lớp khối khác trong data.php", not leaked, f"len={len(t)} leaked={leaked}")
    c, js, t, _ = cl.req(f"/api/export.php?action=report&studentId={sidB}&termId=1")
    rec("IDOR-02", f"{role} không xuất báo cáo em lớp khác (export.php)", c in (401, 403) or (isinstance(js, dict) and js.get("ok") is False) or "quyền" in t or "phụ trách" in t, f"HTTP {c} {t[:80]!r}")
    c, js, t, _ = cl.req(f"/print.php?type=report&studentId={sidB}&termId=1")
    rec("IDOR-03", f"{role} không in phiếu em lớp khác (print.php)", "phụ trách" in t or "Không" in t, f"HTTP {c} {t[:100]!r}")
    c, js, t, _ = cl.req("/api/students.php?action=save", "POST", {"id": int(sidB), "fullName": "HACKED"})
    rec("IDOR-04", f"{role} không sửa được em lớp khác (students.save)", c in (401, 403) or (isinstance(js, dict) and js.get("ok") is False), f"HTTP {c} {t[:100]!r}")
    c, js, t, _ = cl.req("/api/students.php?action=bulk_delete", "POST", {"ids": [int(sidB)]})
    rec("IDOR-05", f"{role} không xoá được em lớp khác (bulk_delete)", c in (401, 403) or (isinstance(js, dict) and js.get("ok") is False), f"HTTP {c} {t[:100]!r}")
print("STUDENT B still exists:", sql(f"SELECT COUNT(*) FROM students WHERE id={sidB}"), sql(f"SELECT full_name FROM students WHERE id={sidB}"))

# ------------------------------------------------------------------ 6. injection / validation
admin_cl = Client()
sql("UPDATE members SET must_change_pw=0 WHERE role_code='admin'")
sql("DELETE FROM login_attempts")
admin_cl.login("0901000001", "tntt@2026")
c, _, t, _ = admin_cl.req("/")
m = re.search(r'([0-9a-f]{64})', t)
admin_cl.csrf = m.group(1) if m else None
rec("INJ-00", "Admin đăng nhập & lấy CSRF (sau khi bỏ must_change_pw)", bool(admin_cl.csrf), "")

payloads = ["1' OR '1'='1", "1;DROP TABLE students", "1 UNION SELECT 1,2,3--", "-1", "9999999999999999999", "abc", "%00", "' OR SLEEP(3)-- "]
bad = []
for p in payloads:
    q = urllib.parse.quote(p)
    for u in [f"/api/years.php?action=list&id={q}", f"/api/export.php?action=attendance&classId={q}&programId={q}&from={q}&to={q}",
              f"/api/library_file.php?id={q}&mode=view", f"/api/scores.php?classId={q}&termId={q}",
              f"/api/students.php?action=next_code&year={q}", f"/print.php?type=report&studentId={q}&termId={q}"]:
        c, js, t, _ = admin_cl.req(u)
        if c >= 500 or "SQLSTATE" in t or "syntax" in t.lower() or "PDOException" in t:
            bad.append(f"{c} {u[:70]} {t[:60]!r}")
rec("INJ-01", "Tham số GET độc hại không gây 500 / lộ SQL", not bad, "; ".join(bad[:5]))
print("students table intact:", sql("SELECT COUNT(*) FROM students"))

# malformed body
c, js, t, _ = admin_cl.req("/api/notes.php?action=save", "POST", raw="{not json", headers={"Content-Type": "application/json"})
rec("INJ-02", "Body JSON hỏng trả lỗi có kiểm soát (không 500)", c < 500, f"HTTP {c} {t[:80]!r}")
c, js, t, _ = admin_cl.req("/api/notes.php?action=save", "POST", raw="A" * (1024 * 1024 + 10))
rec("INJ-03", "Body >1MB bị từ chối 413", c == 413, f"HTTP {c}")

# ------------------------------------------------------------------ 7. stored XSS
xss = "<img src=x onerror=alert(1)>"
c, js, t, _ = admin_cl.req("/api/students.php?action=save", "POST",
                           {"fullName": xss, "holyName": "Maria", "birthDate": "2015-01-01", "classId": int(cA), "gender": "nữ"})
print("create xss student:", c, t[:160])
sid = sql(f"SELECT id FROM students WHERE full_name LIKE '%onerror%' LIMIT 1")
stored_raw = bool(sid)
rec("XSS-01", "Server lưu / trả tên chứa HTML dạng dữ liệu thô (front-end phải escape)", True, f"stored={stored_raw} (thông tin)")
if sid:
    c, _, t, _ = admin_cl.req(f"/print.php?type=report&studentId={sid}&termId=1")
    unescaped = xss in t
    rec("XSS-02", "print.php escape tên thiếu nhi (không in nguyên thẻ <img onerror>)", not unescaped, f"HTTP {c} raw_in_output={unescaped}")
    c, _, t, _ = admin_cl.req(f"/api/export.php?action=attendance&classId={cA}")
    rec("XSS-03", "export CSV: không chèn công thức (=,+,-,@) ", True, "xem ExportTest")
    sql(f"DELETE FROM enrollments WHERE student_id={sid}; DELETE FROM students WHERE id={sid}")

# csv injection
c, js, t, _ = admin_cl.req("/api/students.php?action=save", "POST",
                           {"fullName": "=cmd|' /C calc'!A0", "holyName": "x", "birthDate": "2015-01-01", "classId": int(cA), "gender": "nam"})
sid2 = sql("SELECT id FROM students WHERE full_name LIKE '=cmd%' LIMIT 1")
if sid2:
    c, _, t, hd = admin_cl.req(f"/api/export.php?action=attendance&classId={cA}")
    rec("XSS-04", "CSV export vô hiệu hoá công thức (prefix ') với tên bắt đầu bằng '='", "'=cmd" in t or "\t=cmd" in t or "=cmd" not in t,
        f"HTTP {c}; contains_raw={'=cmd' in t}")
    sql(f"DELETE FROM enrollments WHERE student_id={sid2}; DELETE FROM students WHERE id={sid2}")

# ------------------------------------------------------------------ 8. upload
boundary = "----x"
def upload(name, content, ctype="application/octet-stream"):
    body = (f"--{boundary}\r\nContent-Disposition: form-data; name=\"title\"\r\n\r\nT\r\n"
            f"--{boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"{name}\"\r\nContent-Type: {ctype}\r\n\r\n").encode() + content + f"\r\n--{boundary}--\r\n".encode()
    return admin_cl.req("/api/library.php?action=upload", "POST", raw=body, headers={"Content-Type": f"multipart/form-data; boundary={boundary}"})
for nm, content in [("shell.php", b"<?php echo 1;"), ("a.php.pdf", b"<?php echo 1;"), ("x.pdf", b"<?php echo 1;"), ("x.html", b"<script>1</script>"), ("x.svg", b"<svg onload=alert(1)>")]:
    c, js, t, _ = upload(nm, content)
    ok = c >= 400 or (isinstance(js, dict) and js.get("ok") is False)
    rec("UPL-" + nm, f"Upload {nm} (nội dung không khớp/nguy hiểm) bị từ chối", ok, f"HTTP {c} {t[:90]!r}")

# ------------------------------------------------------------------ 9. public endpoints
c, js, t, _ = anon.req("/api/auth.php?action=register", "POST", {"fullName": "Reg Test", "phone": "0988111222", "password": "abcdef", "role": "admin"}, csrf=False)
print("register:", c, t[:200])
role_after = sql("SELECT role_code,status FROM members WHERE phone='0988111222'")
rec("PUB-01", "Đăng ký công khai không thể tự đặt vai admin", "admin" not in role_after.split("\t")[:1], f"created: {role_after!r}")
sql("DELETE FROM member_assignments WHERE member_id IN (SELECT id FROM members WHERE phone='0988111222')")
sql("DELETE FROM members WHERE phone='0988111222'")

c, js, t, _ = anon.req("/tracuu.php")
print("tracuu:", c, len(t))
c, js, t, _ = anon.req("/somoc.php")
print("somoc:", c, len(t))
c, js, t, _ = anon.req("/bxh.php")
print("bxh:", c, len(t))

# ------------------------------------------------------------------ summary
p = sum(1 for r in results if r[2]); f = len(results) - p
print(f"\nTOTAL {len(results)}  PASS {p}  FAIL {f}")
json.dump(results, open(os.path.join(HERE,"out","e2e_results.json"), "w"), ensure_ascii=False, indent=1)
