"""Hồi quy gói P5 (Web Push): chống SSRF (#99), gửi sau phản hồi không giữ request (#100),
token + cấm chiếm endpoint (#107).

Chạy trên DB THỬ NGHIỆM đã có dữ liệu mẫu (php config/install.php + php config/seed_demo.php):
  PHP_CLI_SERVER_WORKERS=4 php -d opcache.enable=0 -S 127.0.0.1:8088 -t public docs/audit/router.php
  (opcache TẮT: kịch bản đổi config/config.local.php giữa các request; WORKERS>=2: có ca chạy song song)
  E2E_BASE=http://127.0.0.1:8088 E2E_DB=tntt_e2e python3 tests/e2e/p5_regress.py
mysql root không mật khẩu. Cần e2e.py + e2e2.py (nhánh origin/audit, tests/e2e) đặt cạnh file này.

Kịch bản tự dựng:
  - push service GIẢ chạy HTTPS ở localhost:9443 (+ ổ nghe thô 127.0.0.1:9444/9445 để chứng minh
    "không có kết nối nội bộ nào"). Chứng chỉ tự ký CN=localhost/SAN localhost,127.0.0.1 lấy ở
    E2E_TLS_DIR (c.pem + k.pem; mặc định $SCRATCH/tls) và PHP/curl phải tin nó ở mức hệ điều hành.
  - config/config.local.php TẠM (khoá VAPID sinh mới, production=false, test_hosts=localhost:9443);
    xoá ở cuối (nếu máy đã có config.local.php thì được giữ lại nguyên vẹn).
  - dữ liệu: dòng push_subscriptions / push_outbox bị XOÁ SẠCH ở đầu và cuối; mọi thành viên được trả
    về 'đang phục vụ'. Chạy trên DB thử, không phải DB thật.

Các nhóm ca: SSRF (43 URL tấn công + kiểu dữ liệu lạ → 422, không tạo dòng, không kết nối nội bộ),
dòng cũ độc hại bị xoá lười, redirect 302 không theo, 409 chiếm endpoint (kể cả đua), token (băm, xoay,
401), trạng thái thành viên (chờ duyệt/đã nghỉ → 401, tạm nghỉ nhận), thiết bị cũ trong hạn chuyển tiếp,
chậm 6 s không giữ request, song song, không gửi trùng, thử lại/xoá dòng chết.
Ghi chú: ca 'push.py' cũ trên nhánh audit đỏ 6 ca vì hành vi đổi có chủ đích (gửi bất đồng bộ, pending
theo token, trả 200 queued thay vì 409); kịch bản này là bản thay thế đúng hành vi mới.
"""
import os, ssl, json, threading, time, socket, re, subprocess, hashlib, sys, datetime, tempfile
from http.server import BaseHTTPRequestHandler, HTTPServer
from socketserver import ThreadingMixIn
HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.environ.get('P5_ROOT') or os.path.dirname(os.path.dirname(HERE))
exec(open(os.path.join(HERE, 'e2e2.py'), encoding='utf-8').read().split('for role,ph in [("glv","0911000004"),("glv_chu_nhiem"')[0])
results.clear()

# ----------------------------------------------------------------- môi trường
CFG = os.path.join(ROOT, 'config', 'config.local.php')
CFG_BAK = CFG + '.p5e2e.bak'
if os.path.exists(CFG): os.replace(CFG, CFG_BAK)
_vap = subprocess.run(['php', '-r', 'require "' + ROOT + '/config/push.php"; echo json_encode(push_tao_khoa());'],
                      capture_output=True, text=True, cwd=ROOT).stdout
VAP = json.loads(_vap)
def write_cfg(production=False, test_hosts=("localhost:9443",), legacy_until=None):
    th = ",".join(f"'{h}'" for h in test_hosts)
    lu = f", 'legacy_until' => '{legacy_until}'" if legacy_until else ""
    base = f"$g = file_exists('{CFG_BAK}') ? require '{CFG_BAK}' : [];\n" if os.path.exists(CFG_BAK) else "$g = [];\n"
    open(CFG, 'w').write("<?php\n" + base + f"return array_replace_recursive(is_array($g) ? $g : [], ['production' => {'true' if production else 'false'}, "
                         f"'push' => ['public' => '{VAP['public']}', 'private' => '{VAP['private']}', 'subject' => 'mailto:test@example.com', "
                         f"'test_hosts' => [{th}]{lu}]]);\n")
def restore_cfg():
    try: os.remove(CFG)
    except OSError: pass
    if os.path.exists(CFG_BAK): os.replace(CFG_BAK, CFG)
import atexit; atexit.register(restore_cfg)
write_cfg()

TLS = os.environ.get('E2E_TLS_DIR') or os.path.join(os.environ.get('SCRATCH', '.'), 'tls')
def ok(r): return r[0] == 200 and isinstance(r[1], dict) and r[1].get('ok') is True
def show(r): return f"HTTP {r[0]} {r[2][:110]!r}"
def cho(f, giay=8):
    t0 = time.time()
    while time.time() - t0 < giay:
        try:
            if f(): return True
        except Exception: pass
        time.sleep(0.2)
    return bool(f())

# ----------------------------------------------------------------- push service giả + ổ nghe thô
got = []
class TS(ThreadingMixIn, HTTPServer): daemon_threads = True; allow_reuse_address = True
class H(BaseHTTPRequestHandler):
    protocol_version = 'HTTP/1.0'
    def do_POST(self):
        n = int(self.headers.get('Content-Length') or 0); body = self.rfile.read(n) if n else b''
        got.append((self.path, dict(self.headers), body, time.time()))
        p = self.path.split('?')[0]
        if p.startswith('/push/s3'): time.sleep(3)
        elif p.startswith('/push/slow'): time.sleep(6)
        if p == '/push/redirect':
            self.send_response(302); self.send_header('Location', 'https://127.0.0.1:9444/redirected'); self.send_header('Content-Length', '0'); self.end_headers(); return
        code = {'/push/gone': 410, '/push/notfound': 404, '/push/err': 500, '/push/bad': 400}.get(p, 201)
        self.send_response(code); self.send_header('Content-Length', '0'); self.end_headers()
    def log_message(self, *a): pass
srv = TS(('127.0.0.1', 9443), H); ctx = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
ctx.load_cert_chain(os.path.join(TLS, 'c.pem'), os.path.join(TLS, 'k.pem')); srv.socket = ctx.wrap_socket(srv.socket, server_side=True)
threading.Thread(target=srv.serve_forever, daemon=True).start()
hits = {}
def raw_listen(host, port):
    s = socket.socket(); s.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    try: s.bind((host, port))
    except OSError as e: print('KHÔNG bind được', host, port, e); return
    s.listen(16); s.settimeout(0.5); hits[(host, port)] = []
    def loop():
        while True:
            try:
                c, a = s.accept(); c.settimeout(1)
                try: d = c.recv(300)
                except Exception: d = b''
                hits[(host, port)].append(d[:60]); c.close()
            except Exception: pass
    threading.Thread(target=loop, daemon=True).start()
for hp in [('127.0.0.1', 9444), ('127.0.0.1', 9445), ('127.0.0.1', 443)]: raw_listen(*hp)
def nhits(): return sum(len(v) for v in hits.values())
def clear_hits():
    for k in hits: hits[k].clear()
def sub_row(ep): return sql(f"select concat_ws('|',id,member_id,ifnull(token_hash,'NULL'),ring_seq,ring_done,ifnull(ring_lock_until,'NULL'),ring_tries,ifnull(last_fail_code,'NULL'),ifnull(last_ok_at,'NULL')) from push_subscriptions where endpoint='{ep}'")
def n_sub(): return sql("select count(*) from push_subscriptions")

sql("DELETE FROM login_attempts; DELETE FROM push_subscriptions; DELETE FROM push_outbox"); sql("UPDATE members SET must_change_pw=0")
adm = logged("0901000001", "tntt@2026"); glv = logged("0911000004"); tt = logged("0911000006")
ADM = sql("select id from members where phone='0901000001'"); GLV = sql("select id from members where phone='0911000004'"); TT = sql("select id from members where phone='0911000006'")
M = 'https://localhost:9443'
an = Client()
PEND = "/api/push.php?action=pending"
def run(fn, ten):
    """Một nhóm ca lỗi kịch bản (ngoại lệ) thì ghi FAIL rồi chạy tiếp nhóm sau, để thấy hết chỗ đỏ"""
    try: fn()
    except Exception as e:
        import traceback; traceback.print_exc()
        rec("SCRIPT", f"nhóm {ten} dừng giữa chừng: {type(e).__name__}: {str(e)[:80]}", False, "")

SUB = "/api/push.php?action=subscribe"

def sec_1():
    """1. SSRF (#99)"""
    bad = ["https://fcm.googleapis.com.evil.com/x", "https://evil.com/#fcm.googleapis.com", "https://fcm.googleapis.com@127.0.0.1/x",
           "https://127.0.0.1:9444/x", "https://[::1]/x", "https://[::ffff:127.0.0.1]/x", "https://2130706433/x", "https://0x7f.1/x",
           "https://FCM.GOOGLEAPIS.COM/x", "https://Fcm.googleapis.com/x", "https://fcm.googleapis.com./x", "https://fcm.googleapis.com:9444/x",
           "https://fcm.googleapis.com:8443/x", "http://fcm.googleapis.com/x", "https://user:pass@fcm.googleapis.com/x", "https://u@fcm.googleapis.com/x",
           "https://evilfcm.googleapis.com/x", "https://notfcm.googleapis.com.evil/x", "https://fcm.googleapis.com.evil/x",
           "https://ｆｃｍ.googleapis.com/x", "https://fcm.googleapis.com/" + "a" * 500, "https://localhost:9444/x", "https://127.0.0.1:9443/x",
           "https://localhost:09443/x", "https://localhost/x", "https://fcm.googleapis.com/x\r\nX: y", " https://fcm.googleapis.com/x",
           "https://fcm.googleapis.com/x ", "https://fcm.googleapis.com\\@127.0.0.1/x", "https://169.254.169.254/latest", "https://push.apple.com/x",
           "https://evilpush.apple.com/x", "https://web.push.apple.com.evil.com/x", "https://notify.windows.com/x", "https://xnotify.windows.com/x",
           "https://fcm.googleapis.com", "https://fcm.googleapis.com?x", "https:/fcm.googleapis.com/x", "https://fcm.googleapis.com%2f@127.0.0.1/x",
           "HTTPS://fcm.googleapis.com/x", "https://localhost.:9443/x", "https://android.googleapis.com/gcm/send/x", "https://fcm.googleapis.com:443@127.0.0.1:9444/x"]
    rows0 = n_sub(); clear_hits()
    for u in bad:
        r = tt.req(SUB, "POST", {"endpoint": u})
        rec("PUSH-29", f"SSRF từ chối {u[:70]!r} -> 422", r[0] == 422 and not ok(r), show(r))
    rec("PUSH-29n", f"đủ {len(bad)} URL tấn công (43)", len(bad) == 43, str(len(bad)))
    for v in [["https://fcm.googleapis.com/x"], {"a": 1}, 123, True]:
        r = tt.req(SUB, "POST", {"endpoint": v})
        rec("PUSH-29t", f"endpoint kiểu {type(v).__name__} -> 422", r[0] == 422 and not ok(r), show(r))
    r = tt.req(SUB, "POST", {"endpoint": None})
    rec("PUSH-29t", "endpoint null -> 400", r[0] == 400 and not ok(r), show(r))
    rec("PUSH-29r", "không tạo dòng nào, không kết nối nội bộ", n_sub() == rows0 and nhits() == 0, f"rows={n_sub()} hits={nhits()}")
    good = ["https://fcm.googleapis.com/fcm/send/abc:APA91b", "https://fcm.googleapis.com:443/fcm/send/abc2", "https://updates.push.services.mozilla.com/wpush/v2/gAAA",
            "https://web.push.apple.com/QGxx", "https://wns2-par02p.notify.windows.com/w/?token=BQYAAAB%2f"]
    for u in good:
        r = tt.req(SUB, "POST", {"endpoint": u})
        rec("PUSH-30", f"chấp nhận {u}", ok(r), show(r))
    sql(f"delete from push_subscriptions where member_id={TT}"); clear_hits()
    r = tt.req("/api/push.php?action=test", "POST", {}); time.sleep(2)
    rec("PUSH-21", "test của thủ thư sau mọi lần thử: 409, không kết nối nội bộ", r[0] == 409 and nhits() == 0, show(r) + f" hits={nhits()}")
    # dữ liệu cũ độc hại nằm sẵn trong DB: không kết nối, bị xoá lười lúc xả
    sql(f"insert into push_subscriptions (member_id,endpoint,created_at) values ({ADM},'https://127.0.0.1:9444/legacy',NOW()),({ADM},'https://localhost:9445/legacy2',NOW())")
    clear_hits()
    r = adm.req("/api/push.php?action=test", "POST", {})
    rec("PUSH-31", "dòng cũ độc hại: không kết nối, dòng bị xoá", cho(lambda: n_sub() == "0") and nhits() == 0, show(r) + f" hits={nhits()} rows={n_sub()}")

    # redirect: curl không theo
    got.clear(); clear_hits()
    adm.req(SUB, "POST", {"endpoint": M + "/push/redirect"})
    adm.req("/api/push.php?action=test", "POST", {})
    cho(lambda: sub_row(M + "/push/redirect").split('|')[7] != 'NULL'); time.sleep(1)
    rec("PUSH-32", "302 sang 127.0.0.1:9444: không theo, last_fail_code=302", len(got) == 1 and nhits() == 0 and sub_row(M + "/push/redirect").split('|')[7] == '302', f"mock={len(got)} hits={nhits()} row={sub_row(M + '/push/redirect')}")

    # test_hosts chỉ ở máy thử
    sql("delete from push_subscriptions")
    write_cfg(test_hosts=())
    r = adm.req(SUB, "POST", {"endpoint": M + "/push/ok"})
    rec("PUSH-35", "không khai test_hosts -> localhost:9443 bị 422", r[0] == 422, show(r))
    write_cfg(production=True)
    r = adm.req(SUB, "POST", {"endpoint": M + "/push/ok"})
    rec("PUSH-36", "production=true + test_hosts -> bỏ qua, 422", r[0] == 422, show(r))
    write_cfg()

run(sec_1, '1. SSRF (#99)')

def sec_2():
    """2. 409 chiếm endpoint (#107)"""
    sql("delete from push_subscriptions; delete from push_outbox")
    r = adm.req(SUB, "POST", {"endpoint": M + "/push/ok"})
    before = sub_row(M + "/push/ok")
    r = glv.req(SUB, "POST", {"endpoint": M + "/push/ok"})
    after = sub_row(M + "/push/ok")
    rec("PUSH-20", "GLV đăng ký trùng endpoint admin -> 409 endpoint_owned, member_id & token_hash không đổi",
        r[0] == 409 and (r[1] or {}).get('code') == 'endpoint_owned' and 'token' not in (r[1] or {}) and before == after and after.split('|')[1] == ADM, f"{show(r)} before={before} after={after}")
    glv.req("/api/push.php?action=unsubscribe", "POST", {"endpoint": M + "/push/ok"})
    rec("PUSH-19", "GLV không huỷ được máy của admin", n_sub() == "1", n_sub())
    r = glv.req("/api/push.php?action=status&endpoint=" + M + "/push/ok")
    rec("OWN-status", "status của GLV với endpoint admin: onThisDevice=false, không lộ lastOk/needToken", ok(r) and r[1].get('onThisDevice') is False and r[1].get('lastOkAt') is None and r[1].get('needToken') is False, show(r))
    res = {}
    def s1(c, k): res[k] = c.req(SUB, "POST", {"endpoint": M + "/push/race"})
    for _ in range(3):
        sql("delete from push_subscriptions"); res.clear()
        a = threading.Thread(target=s1, args=(adm, 'a')); b = threading.Thread(target=s1, args=(glv, 'b')); a.start(); b.start(); a.join(); b.join()
        codes = sorted([res['a'][0], res['b'][0]])
        rec("OWN-race", "2 người đua đăng ký cùng endpoint: 1 thắng (200), 1 thua (409), không 500", codes == [200, 409] and n_sub() == "1", f"codes={codes} rows={n_sub()}")

run(sec_2, '2. 409 chiếm endpoint (#107)')

def sec_3():
    """3. token (#107)"""
    sql("delete from push_subscriptions; delete from push_outbox")
    r = adm.req(SUB, "POST", {"endpoint": M + "/push/ok"}); t1 = r[1]['token']
    th = sql(f"select token_hash from push_subscriptions where endpoint='{M}/push/ok'")
    rec("TOK-hash", "token 43 ký tự base64url; DB chỉ lưu SHA-256 hex (không lưu token thô)",
        re.fullmatch(r'[A-Za-z0-9_-]{43}', t1) and th == hashlib.sha256(t1.encode()).hexdigest() and sql(f"select count(*) from push_subscriptions where token_hash='{t1}'") == '0', f"hash={th[:12]}…")
    r = adm.req(SUB, "POST", {"endpoint": M + "/push/ok"}); t2 = r[1]['token']
    rec("TOK-rotate", "đăng ký lại cùng người: token khác, vẫn 1 dòng", t2 != t1 and n_sub() == "1", "")
    adm.req("/api/push.php?action=test", "POST", {}); time.sleep(1)
    r = an.req(PEND, "POST", {"token": t1}, csrf=False)
    rec("PUSH-24", "token cũ (đã xoay) -> 401", r[0] == 401, show(r))
    tk0 = sql("select ifnull(taken_at,'NULL') from push_outbox order by id desc limit 1")
    r = an.req(PEND, "POST", {"token": 'A' * 43}, csrf=False)
    rec("PUSH-23", "token sai -> 401, outbox không bị đánh dấu", r[0] == 401 and sql("select ifnull(taken_at,'NULL') from push_outbox order by id desc limit 1") == tk0 == 'NULL', show(r))
    r = an.req(PEND, "POST", {"endpoint": M + "/push/ok"}, csrf=False)
    rec("PUSH-25", "dòng đã có token: endpoint ẩn danh -> 401", r[0] == 401, show(r))
    r = an.req(PEND, "POST", {"token": t2}, csrf=False)
    rec("PUSH-27", "không phiên + token đúng -> nhận đúng nội dung (#71)", ok(r) and r[1].get('item') and r[1]['item']['title'] == 'Thử thông báo', show(r))
    r = an.req(PEND, "POST", {"token": t2}, csrf=False)
    rec("PUSH-15", "lần 2 không trả lại", ok(r) and r[1].get('item') is None, show(r))
    c = logged("0901000001", "tntt@2026"); c.req("/api/auth.php?action=logout", "POST", {})
    sql(f"insert into push_outbox (member_id,title,body,url,tag,created_at) values ({ADM},'sau-logout','b','/','t',NOW())")
    r = c.req(PEND, "POST", {"token": t2}, csrf=False)
    rec("LOGOUT", "sau khi đăng xuất thật + token -> vẫn nhận", ok(r) and r[1].get('item') and r[1]['item']['title'] == 'sau-logout', show(r))
    # trạng thái thành viên: chờ duyệt / đã nghỉ -> 401 giống nhau; tạm nghỉ / đang phục vụ nhận
    dem = {}
    for st, nhan in (('đã nghỉ', False), ('chờ duyệt', False), ('tạm nghỉ', True), ('đang phục vụ', True)):
        sql("delete from push_outbox"); sql(f"insert into push_outbox (member_id,title,body,url,tag,created_at) values ({ADM},'x-{ord(st[0])}','b','/','t',NOW())")
        sql(f"update members set status='{st}' where id={ADM}")
        r = an.req(PEND, "POST", {"token": t2}, csrf=False)
        dem[st] = r
        good_r = (ok(r) and bool(r[1].get('item'))) if nhan else (r[0] == 401 and 'item' not in (r[1] or {}) and 'x-' not in r[2])
        rec("PUSH-28" if st == 'đã nghỉ' else "STATUS-pending", f"thành viên '{st}' + token -> " + ("nhận nội dung" if nhan else "401, không nội dung"), good_r, show(r))
    sql(f"update members set status='đang phục vụ' where id={ADM}")
    rec("STATUS-same", "chờ duyệt và đã nghỉ cùng một phản hồi (không lộ khác biệt)", dem['chờ duyệt'][2] == dem['đã nghỉ'][2], dem['chờ duyệt'][2][:60])
    for v in [t2 + 'x', t2[:-1], t2.upper() if t2.upper() != t2 else t2.lower(), "' OR 1=1 -- " + 'a' * 31, ['x'], 12, {"a": 1}]:
        r = an.req(PEND, "POST", {"token": v}, csrf=False)
        rec("TOK-fmt", f"token lạ {str(v)[:20]!r} -> 401, không 500", r[0] == 401, show(r))
    for e in (["x"], {"a": 1}, 5):
        r = an.req(PEND, "POST", {"endpoint": e}, csrf=False)
        rec("TOK-fmt", f"endpoint lạ {str(e)[:20]!r} -> 401, không 500", r[0] == 401, show(r))

run(sec_3, '3. token (#107)')

def sec_4():
    """4. thiết bị cũ (chuyển tiếp)"""
    sql("delete from push_subscriptions; delete from push_outbox")
    sql(f"insert into push_subscriptions (member_id,endpoint,created_at) values ({ADM},'{M}/push/legacy',NOW())")
    adm.req("/api/push.php?action=test", "POST", {})
    rec("LEG-ring", "dòng cũ chưa token vẫn được rung", cho(lambda: sub_row(M + "/push/legacy").split('|')[8] != 'NULL'), sub_row(M + "/push/legacy"))
    r = an.req(PEND, "POST", {"endpoint": M + "/push/legacy"}, csrf=False)
    rec("PUSH-14b", "dòng cũ: endpoint ẩn danh nhận nội dung trong hạn chuyển tiếp", ok(r) and r[1].get('item'), show(r))
    st = adm.req("/api/push.php?action=status&endpoint=" + M + "/push/legacy")
    rec("LEG-needToken", "status báo needToken=true", ok(st) and st[1].get('needToken') is True, show(st))
    r = adm.req(SUB, "POST", {"endpoint": M + "/push/legacy"})
    st = adm.req("/api/push.php?action=status&endpoint=" + M + "/push/legacy")
    rec("LEG-upgrade", "đăng ký lại cùng endpoint -> có token, needToken=false, vẫn 1 dòng", ok(r) and r[1].get('token') and st[1].get('needToken') is False and n_sub() == '1', show(r))
    sql(f"update push_subscriptions set token_hash=NULL where endpoint='{M}/push/legacy'")
    sql(f"insert into push_outbox (member_id,title,body,url,tag,created_at) values ({ADM},'x','b','/','t',NOW())")
    for st, nhan in (('chờ duyệt', False), ('đã nghỉ', False), ('tạm nghỉ', True)):
        sql(f"update members set status='{st}' where id={ADM}")
        r = an.req(PEND, "POST", {"endpoint": M + "/push/legacy"}, csrf=False)
        rec("LEG-status", f"dòng cũ + thành viên '{st}' -> " + ("nhận" if nhan else "401"), (ok(r) and bool(r[1].get('item'))) if nhan else r[0] == 401, show(r))
        if nhan: sql(f"insert into push_outbox (member_id,title,body,url,tag,created_at) values ({ADM},'x','b','/','t',NOW())")
    sql(f"update members set status='đang phục vụ' where id={ADM}")
    write_cfg(legacy_until=(datetime.date.today() - datetime.timedelta(days=1)).isoformat())
    r = an.req(PEND, "POST", {"endpoint": M + "/push/legacy"}, csrf=False)
    rec("PUSH-26", "hết hạn chuyển tiếp (legacy_until=hôm qua) -> 401", r[0] == 401, show(r))
    write_cfg(legacy_until=datetime.date.today().isoformat())
    r = an.req(PEND, "POST", {"endpoint": M + "/push/legacy"}, csrf=False)
    rec("LEG-today", "legacy_until=hôm nay -> còn nhận", ok(r), show(r))
    write_cfg()

run(sec_4, '4. thiết bị cũ (chuyển tiếp)')

def sec_5():
    """5. gửi sau phản hồi (#100)"""
    sql("delete from push_subscriptions; delete from push_outbox"); got.clear()
    adm.req(SUB, "POST", {"endpoint": M + "/push/slow"})
    t0 = time.time(); r = adm.req("/api/push.php?action=test", "POST", {}); dt = time.time() - t0
    rec("PUSH-22", "test với push chậm 6 s trả nhanh (< 2 s), queued=true", dt < 2 and ok(r) and r[1].get('queued') is True, f"{dt:.2f}s {show(r)}")
    rec("PUSH-22b", "chuông vẫn tới push service sau đó", cho(lambda: len(got) >= 1, 10), f"got={len(got)}")
    if got:
        p, h, b, ts = got[0]; hl = {k.lower(): v for k, v in h.items()}
        rec("PAYLOAD", "thân rỗng, Content-Length 0, TTL, VAPID; không lộ nội dung", b == b'' and hl.get('content-length') == '0' and 'ttl' in hl and hl.get('authorization', '').startswith('vapid t=') and 'Thử' not in json.dumps(hl, ensure_ascii=False), f"body={len(b)} headers={sorted(hl)}")
    time.sleep(6.5)
    sql("delete from push_subscriptions; delete from push_outbox"); got.clear()
    adm.req(SUB, "POST", {"endpoint": M + "/push/s3"})
    t0 = time.time(); r = adm.req("/api/push.php?action=test", "POST", {}); dt = time.time() - t0
    rec("ASYNC-done", "máy chậm 3 s: test trả nhanh, sau đó ring_done=ring_seq, last_ok_at", dt < 2 and cho(lambda: sub_row(M + "/push/s3").split('|')[8] != 'NULL', 12) and sub_row(M + "/push/s3").split('|')[3] == sub_row(M + "/push/s3").split('|')[4], f"{dt:.2f}s " + sub_row(M + "/push/s3"))
    # gzip: Accept-Encoding gzip vẫn nhận phản hồi đúng và nhanh
    import gzip as _gz, urllib.request as _ur
    sql("delete from push_subscriptions; delete from push_outbox"); got.clear()
    adm.req(SUB, "POST", {"endpoint": M + "/push/slow-gz"})
    rq = _ur.Request(BASE + "/api/push.php?action=test", data=b"{}", method="POST", headers={"Accept-Encoding": "gzip", "Content-Type": "application/json", "X-CSRF-Token": adm.csrf})
    t0 = time.time(); resp = adm.op.open(rq, timeout=30); raw = resp.read(); dt = time.time() - t0
    ce = resp.headers.get('Content-Encoding'); txt = _gz.decompress(raw).decode() if ce == 'gzip' else raw.decode('utf-8', 'replace')
    try: jj = json.loads(txt)
    except Exception: jj = None
    rec("ASYNC-gzip", "Accept-Encoding gzip + có hàng đợi: phản hồi giải mã được, JSON đúng, nhanh", dt < 2 and isinstance(jj, dict) and jj.get('queued') is True, f"{dt:.2f}s CE={ce} {txt[:60]!r}")
    cho(lambda: len(got) >= 1, 10); time.sleep(6.5)
    # leave.create + announcements.save không bị giữ chậm bởi push service
    sql("delete from push_subscriptions; delete from push_outbox"); got.clear()
    adm.req(SUB, "POST", {"endpoint": M + "/push/slow-leave"})
    yid = sql("select id from school_years where is_current=1 limit 1") or sql("select max(id) from school_years")
    sid = sql(f"select e.student_id from enrollments e where e.class_id={cA} and e.status='đang sinh hoạt' and e.year_id={yid} limit 1")
    pid = sql(f"select id from programs where year_id={yid} limit 1")
    d = (datetime.date.today() + datetime.timedelta(days=7)).isoformat()
    sql(f"delete from leave_requests where student_id={sid}")
    t0 = time.time(); r = glv.req("/api/leave.php?action=create", "POST", {"studentId": int(sid), "programId": int(pid), "date": d, "reason": "P5-ốm"}); dt = time.time() - t0
    rec("ASYNC-leave", "leave.create (người duyệt có máy push chậm 6 s) trả nhanh", ok(r) and dt < 2, f"{dt:.2f}s {show(r)}")
    rec("ASYNC-leave-sent", "chuông tới admin sau đó", cho(lambda: len(got) >= 1, 10), f"got={len(got)}")
    sql("delete from leave_requests where reason like 'P5-%'")
    time.sleep(6.5)
    sql("delete from push_subscriptions; delete from push_outbox"); got.clear()
    glv.req(SUB, "POST", {"endpoint": M + "/push/slow-glv"})
    t0 = time.time(); r = adm.req("/api/announcements.php?action=save", "POST", {"title": "P5 TB thử", "body": "x", "level": "thường", "audienceType": "toàn đoàn", "status": "đã phát"}); dt = time.time() - t0
    rec("ASYNC-ann", "announcements.save phát toàn đoàn (GLV push chậm) trả nhanh", ok(r) and dt < 2, f"{dt:.2f}s {show(r)}")
    rec("ASYNC-ann-sent", "chuông tới GLV sau đó", cho(lambda: len(got) >= 1, 10), f"got={len(got)}")
    sql("delete from announcements where title='P5 TB thử'")
    time.sleep(6.5)
    # không gửi trùng khi nhiều request đồng thời cùng xả
    sql("delete from push_subscriptions; delete from push_outbox"); got.clear()
    adm.req(SUB, "POST", {"endpoint": M + "/push/ok-dup"})
    sql(f"update push_subscriptions set ring_seq=ring_done+1, ring_lock_until=NULL where endpoint='{M}/push/ok-dup'")
    ths = [threading.Thread(target=lambda: adm.req("/api/push.php?action=status&endpoint=" + M + "/push/ok-dup")) for _ in range(8)]
    [t.start() for t in ths]; [t.join() for t in ths]; time.sleep(3)
    rec("ASYNC-dedup", "8 status đồng thời (xả cơ hội) chỉ rung 1 lần", len(got) == 1, f"got={len(got)} row={sub_row(M + '/push/ok-dup')}")
    # nhiều máy chậm + 1 máy nhanh: song song
    sql("delete from push_subscriptions; delete from push_outbox"); got.clear()
    for i in range(5): sql(f"insert into push_subscriptions (member_id,endpoint,created_at,token_hash) values ({ADM},'{M}/push/slow{i}x',NOW(),SHA2('t{i}',256))")
    sql(f"insert into push_subscriptions (member_id,endpoint,created_at) values ({ADM},'{M}/push/ok-par',NOW())")
    t0 = time.time(); r = adm.req("/api/push.php?action=test", "POST", {}); dt = time.time() - t0
    t1 = time.time(); okp = cho(lambda: any(g[0] == '/push/ok-par' for g in got), 10); dt2 = time.time() - t1
    rec("PUSH-34", "5 máy chậm + 1 nhanh: test nhanh, máy nhanh nhận ngay (song song)", dt < 2 and okp and dt2 < 3, f"test {dt:.2f}s, ok-par sau {dt2:.2f}s, devices={r[1] and r[1].get('devices')}")
    time.sleep(7)
    # status xả cơ hội
    sql("delete from push_subscriptions; delete from push_outbox"); got.clear()
    adm.req(SUB, "POST", {"endpoint": M + "/push/ok"})
    sql("update push_subscriptions set ring_seq=ring_done+1, ring_lock_until=NULL")
    adm.req("/api/push.php?action=status&endpoint=" + M + "/push/ok")
    rec("PUSH-37", "status xả hàng cơ hội", cho(lambda: len(got) == 1) and cho(lambda: sub_row(M + "/push/ok").split('|')[3] == sub_row(M + "/push/ok").split('|')[4]), sub_row(M + "/push/ok"))

run(sec_5, '5. gửi sau phản hồi (#100)')

def sec_6():
    """6. thử lại / dòng chết"""
    for path in ("/push/gone", "/push/notfound"):
        sql("delete from push_subscriptions"); adm.req(SUB, "POST", {"endpoint": M + path})
        r = adm.req("/api/push.php?action=test", "POST", {})
        rec("PUSH-17", f"{path} -> xoá dòng", r[0] == 200 and cho(lambda: n_sub() == "0"), show(r))
    sql("delete from push_subscriptions"); adm.req(SUB, "POST", {"endpoint": M + "/push/err"})
    r = adm.req("/api/push.php?action=test", "POST", {})
    cho(lambda: sub_row(M + "/push/err").split('|')[7] == '500')
    row = sub_row(M + "/push/err").split('|')
    rec("PUSH-18", "500 -> giữ dòng, tries=1, ring chưa xong", row[7] == '500' and row[6] == '1' and int(row[4]) < int(row[3]) and row[5] != 'NULL', '|'.join(row))
    sql("delete from push_subscriptions"); adm.req(SUB, "POST", {"endpoint": M + "/push/bad"})
    adm.req("/api/push.php?action=test", "POST", {})
    rec("PERM-400", "400 -> lỗi vĩnh viễn: bỏ cú chuông, giữ dòng, last_fail_code=400", cho(lambda: sub_row(M + "/push/bad").split('|')[7] == '400') and sub_row(M + "/push/bad").split('|')[3] == sub_row(M + "/push/bad").split('|')[4], sub_row(M + "/push/bad"))

run(sec_6, '6. thử lại / dòng chết')

sql("delete from push_subscriptions; delete from push_outbox"); sql("UPDATE members SET status='đang phục vụ' WHERE status IN ('chờ duyệt','tạm nghỉ','đã nghỉ') AND phone IN ('0901000001')")
restore_cfg()
p = sum(1 for x in results if x[2]); print("TOTAL", len(results), "PASS", p, "FAIL", len(results) - p)
sys.exit(0 if p == len(results) else 1)
