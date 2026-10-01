"""Web Push phía máy chủ: VAPID, giao thông báo tới push service giả (TLS cục bộ), dọn subscription chết, SSRF, outbox.
Cần: config/config.local.php có khoá VAPID (tạm thời), chứng chỉ tự ký của mock được tin cậy ở mức hệ điều hành (sandbox)."""
import os, ssl, json, threading, time, base64, socket
from http.server import BaseHTTPRequestHandler, HTTPServer
HERE = os.path.dirname(os.path.abspath(__file__))
exec(open(os.path.join(HERE,'e2e2.py'), encoding='utf-8').read().split('for role,ph in [("glv","0911000004"),("glv_chu_nhiem"')[0])
import datetime
results.clear()
def ok(r): return r[0]==200 and isinstance(r[1],dict) and r[1].get('ok') is True
def show(r): return f"HTTP {r[0]} {r[2][:120]!r}"
SCR=os.environ.get('SCRATCH','.')
got=[]
class H(BaseHTTPRequestHandler):
    def do_POST(self):
        got.append((self.path,dict(self.headers)))
        if self.path.startswith('/push/slow'): time.sleep(6)
        code={'/push/ok':201,'/push/gone':410,'/push/notfound':404,'/push/err':500}.get(self.path.split('?')[0],201)
        self.send_response(code); self.send_header('Content-Length','0'); self.end_headers()
    def log_message(self,*a): pass
srv=HTTPServer(('127.0.0.1',9443),H); ctx=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER); ctx.load_cert_chain(os.path.join(SCR,'tls/c.pem'),os.path.join(SCR,'tls/k.pem')); srv.socket=ctx.wrap_socket(srv.socket,server_side=True)
threading.Thread(target=srv.serve_forever,daemon=True).start()
# raw listener để chứng minh SSRF (chỉ ghi nhận kết nối tới)
raw=socket.socket(); raw.setsockopt(socket.SOL_SOCKET,socket.SO_REUSEADDR,1); raw.bind(('127.0.0.1',9444)); raw.listen(5); raw.settimeout(1); hits=[]
def rawloop():
    end=time.time()+120
    while time.time()<end:
        try:
            c,a=raw.accept(); d=c.recv(200); hits.append(len(d)); c.close()
        except Exception: pass
threading.Thread(target=rawloop,daemon=True).start()

sql("DELETE FROM login_attempts; DELETE FROM push_subscriptions; DELETE FROM push_outbox"); sql("UPDATE members SET must_change_pw=0")
adm=logged("0901000001","tntt@2026"); glv=logged("0911000004"); tt=logged("0911000006")
pub=json.load(open(os.path.join(SCR,'vapid.json')))['public']
r=Client().req("/api/push.php?action=key"); rec("PUSH-01","Khoá VAPID công khai trả về đúng (không lộ khoá riêng)", isinstance(r[1],dict) and r[1].get('key')==pub and 'private' not in r[2], show(r))
r=adm.req("/api/push.php?action=status"); rec("PUSH-02","status: available=true khi đã cấu hình khoá", ok(r) and r[1].get('available') is True and r[1].get('devices')==0, show(r))
r=Client().req("/api/push.php?action=status"); rec("PUSH-03","status yêu cầu đăng nhập", r[0]==401, show(r))
r=adm.req("/api/push.php?action=subscribe","POST",{"endpoint":"http://insecure.example/x"}); rec("PUSH-04","subscribe từ chối endpoint không phải https", not ok(r), show(r))
r=adm.req("/api/push.php?action=subscribe","POST",{"endpoint":"https://localhost:9443/"+"a"*600}); rec("PUSH-05","subscribe từ chối endpoint > 500 ký tự", not ok(r), show(r))
r=adm.req("/api/push.php?action=subscribe","POST",{"endpoint":"https://localhost:9443/push/ok"}); rec("PUSH-06","subscribe endpoint hợp lệ", ok(r), show(r))
r=adm.req("/api/push.php?action=subscribe","POST",{"endpoint":"https://localhost:9443/push/ok"}); rec("PUSH-07","subscribe lặp cùng endpoint không tạo trùng", ok(r) and sql("select count(*) from push_subscriptions")=="1", show(r))
got.clear(); t0=time.time(); r=adm.req("/api/push.php?action=test","POST",{}); dt=time.time()-t0
rec("PUSH-08","action=test gửi 1 cú chuông tới push service, trả devices=1", ok(r) and r[1].get('devices')==1 and len(got)==1, show(r)+f" mock nhận {len(got)} request")
if got:
    h={k.lower():v for k,v in got[0][1].items()}
    auth=h.get('authorization','')
    m=None
    import re
    m=re.match(r'vapid t=([\w-]+)\.([\w-]+)\.([\w-]+), k=([\w-]+)$',auth)
    rec("PUSH-09","Header Authorization đúng chuẩn VAPID (t=<JWT>, k=<khoá công khai>)", bool(m) and m.group(4)==pub, auth[:60]+'…')
    if m:
        import sys; sys.path.insert(0, os.path.join(SCR,'pylib'))
        from cryptography.hazmat.primitives.asymmetric import ec, utils
        from cryptography.hazmat.primitives import hashes
        b64d=lambda s: base64.urlsafe_b64decode(s+'='*(-len(s)%4))
        pk=b64d(pub); key=ec.EllipticCurvePublicKey.from_encoded_point(ec.SECP256R1(),pk)
        raw_sig=b64d(m.group(3)); r_=int.from_bytes(raw_sig[:32],'big'); s_=int.from_bytes(raw_sig[32:],'big')
        try:
            key.verify(utils.encode_dss_signature(r_,s_),(m.group(1)+'.'+m.group(2)).encode(),ec.ECDSA(hashes.SHA256())); sigok=True
        except Exception as e: sigok=False
        pl=json.loads(b64d(m.group(2)))
        rec("PUSH-10","Chữ ký ES256 của JWT hợp lệ với khoá công khai",sigok,"")
        rec("PUSH-11","JWT: aud = scheme+host của endpoint (cổng mặc định 443 nên không ảnh hưởng push service thật), exp ≤ 24h (chuẩn VAPID), có sub", pl.get('aud')=='https://localhost' and 0<pl.get('exp',0)-time.time()<=86400 and pl.get('sub','').startswith('mailto:'), json.dumps(pl))
    rec("PUSH-12","Có header TTL và Content-Length: 0 (không đẩy nội dung qua push service)", h.get('ttl')=='86400' and h.get('content-length')=='0', f"TTL={h.get('ttl')} CL={h.get('content-length')}")
rec("PUSH-13","Outbox lưu thông báo cho người nhận", sql("select count(*) from push_outbox where member_id=1")=="1", "rows="+sql("select count(*) from push_outbox where member_id=1"))
# pending bằng endpoint khi chưa đăng nhập (đường của service worker)
anon=Client(); r=anon.req("/api/push.php?action=pending","POST",{"endpoint":"https://localhost:9443/push/ok"},csrf=False)
rec("PUSH-14","SW lấy nội dung việc mới bằng endpoint dù đã đăng xuất; đọc xong đánh dấu đã lấy", ok(r) and r[1].get('item') and r[1]['item'].get('title')=='Thử thông báo', show(r))
r=anon.req("/api/push.php?action=pending","POST",{"endpoint":"https://localhost:9443/push/ok"},csrf=False)
rec("PUSH-15","Gọi lần 2 không trả lại thông báo cũ", ok(r) and r[1].get('item') is None, show(r))
r=anon.req("/api/push.php?action=pending","POST",{"endpoint":"https://localhost:9443/push/khac"},csrf=False)
rec("PUSH-16","Endpoint lạ + chưa đăng nhập → 401 (không lộ dữ liệu)", r[0]==401, show(r))
# dọn subscription chết
for path,label in (("/push/gone","410"),("/push/notfound","404")):
    sql("delete from push_subscriptions"); adm.req("/api/push.php?action=subscribe","POST",{"endpoint":"https://localhost:9443"+path})
    r=adm.req("/api/push.php?action=test","POST",{}); rec("PUSH-17",f"Push service trả {label} → xoá subscription chết", sql("select count(*) from push_subscriptions")=="0", show(r))
sql("delete from push_subscriptions"); adm.req("/api/push.php?action=subscribe","POST",{"endpoint":"https://localhost:9443/push/err"})
r=adm.req("/api/push.php?action=test","POST",{}); rec("PUSH-18","Push service lỗi 500 → giữ subscription, báo không rung được (409)", r[0]==409 and sql("select count(*) from push_subscriptions")=="1", show(r))
# unsubscribe chỉ xoá của mình
sql("delete from push_subscriptions"); adm.req("/api/push.php?action=subscribe","POST",{"endpoint":"https://localhost:9443/push/ok"})
r=glv.req("/api/push.php?action=unsubscribe","POST",{"endpoint":"https://localhost:9443/push/ok"}); rec("PUSH-19","Người khác không huỷ được subscription của mình", sql("select count(*) from push_subscriptions")=="1", show(r))
# chiếm endpoint
r=glv.req("/api/push.php?action=subscribe","POST",{"endpoint":"https://localhost:9443/push/ok"}); owner=sql("select member_id from push_subscriptions")
print("  chủ sở hữu endpoint sau khi GLV đăng ký cùng endpoint:",owner)
rec("PUSH-20","Người khác đăng ký trùng endpoint KHÔNG chiếm được subscription của người trước", owner=="1", "member_id hiện tại="+owner)
# SSRF
sql("delete from push_subscriptions"); hits.clear()
r=tt.req("/api/push.php?action=subscribe","POST",{"endpoint":"https://127.0.0.1:9444/internal-admin"}); sub_ok=ok(r)
r=tt.req("/api/push.php?action=test","POST",{}); time.sleep(1.5)
rec("PUSH-21","SSRF: máy chủ KHÔNG được kết nối tới địa chỉ nội bộ do người dùng đặt làm endpoint (thủ thư, không cần quyền gì)", len(hits)==0, f"subscribe={sub_ok}, kết nối nội bộ nhận được={len(hits)} {show(r)}")
sql("delete from push_subscriptions")
# độ trễ khi push service chậm
adm.req("/api/push.php?action=subscribe","POST",{"endpoint":"https://localhost:9443/push/slow"})
t0=time.time(); r=adm.req("/api/push.php?action=test","POST",{}); dt=time.time()-t0
print(f"  action=test với push service chậm 6s: {dt:.1f}s")
rec("PUSH-22","Gửi push không chặn request của người dùng lâu (push đồng bộ trong request)", dt<2, f"request bị giữ {dt:.1f}s")
sql("delete from push_subscriptions; delete from push_outbox")
p=sum(1 for x in results if x[2]); print("TOTAL",len(results),"PASS",p,"FAIL",len(results)-p)
json.dump(results,open(os.path.join(HERE,"out","push_results.json"),"w"),ensure_ascii=False,indent=1)
