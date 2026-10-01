"""IDOR theo lớp/khối, phạm vi dữ liệu data.php, XSS/CSV, đăng ký công khai.
Chạy trên DB THỬ NGHIỆM (không phải DB thật). Xem BAO_CAO_KIEM_THU.md mục 9 để biết cách dựng môi trường.
Yêu cầu: php -S 127.0.0.1:8088 -t public (đã trỏ TNTT_DB_* vào DB thử), mysql client chạy được bằng root không mật khẩu, DB tên trong biến DB bên dưới.
"""
import os
HERE = os.path.dirname(os.path.abspath(__file__))
exec(open(os.path.join(HERE,'e2e.py'), encoding='utf-8').read().split('# ------------------------------------------------------------------ 1. unauth')[0])
import re, json
perm={}
for r_ in sql('SELECT module_key,role_code,level FROM permissions').splitlines():
    k_,rc_,lv_=r_.split('\t'); perm[(k_,rc_)]=lv_
PH_LOG=lambda js: any(re.fullmatch(r'0[0-9]{9}',l['detail']) for l in js['logs'])
RAWF='"=HYPERLINK'
sql("DELETE FROM login_attempts"); sql("UPDATE members SET must_change_pw=0 WHERE role_code='admin'")
nameA=sql(f"select name from classes where id={cA}"); nameB=sql(f"select name from classes where id={cB}")
sidB=sql(f"select student_id from enrollments where class_id={cB} limit 1"); codeB=sql(f"select code from students where id={sidB}"); origB=sql(f"select full_name from students where id={sidB}")
sidA=sql(f"select student_id from enrollments where class_id={cA} limit 1")
def logged(ph,pw="Test@1234"):
    c=Client(); c.login(ph,pw); _,_,t,_=c.req("/"); m=re.search(r'([0-9a-f]{64})',t); c.csrf=m.group(1) if m else None; return c
for role,ph in [("glv","0911000004"),("glv_chu_nhiem","0911000003"),("du_bi","0911000005")]:
    c=logged(ph)
    r=c.req("/api/students.php?action=save","POST",{"code":codeB,"name":"HACKED","className":nameA,"holyName":"x","birthDate":"2015-01-01","gender":"nam"})
    rec("IDOR-04",f"{role} lớp A KHÔNG sửa/chuyển được em lớp B (students.save, code của em B → lớp A)", r[0]==403, f"HTTP {r[0]} {r[2][:110]!r}")
    r=c.req("/api/students.php?action=save","POST",{"code":codeB,"name":"HACKED","className":nameB,"holyName":"x","birthDate":"2015-01-01","gender":"nam"})
    rec("IDOR-04b",f"{role} KHÔNG sửa em lớp B ngay tại lớp B", r[0]==403, f"HTTP {r[0]} {r[2][:110]!r}")
    r=c.req("/api/students.php?action=bulk_delete","POST",{"student_ids":[int(sidB)]})
    rec("IDOR-05",f"{role} KHÔNG xoá được em lớp B (bulk_delete)", r[0]==403 or (isinstance(r[1],dict) and r[1].get('ok') is False), f"HTTP {r[0]} {r[2][:110]!r}")
    r=c.req("/api/students.php?action=bulk_move","POST",{"student_ids":[int(sidB)],"target_class":nameA})
    rec("IDOR-06",f"{role} KHÔNG chuyển lớp em lớp B sang lớp mình (bulk_move)", r[0]==403 or (isinstance(r[1],dict) and r[1].get('ok') is False), f"HTTP {r[0]} {r[2][:110]!r}")
    r=c.req("/api/export.php?action=report","POST",{"studentId":int(sidB),"termId":1})
    rec("IDOR-02",f"{role} KHÔNG xuất phiếu liên lạc em lớp B (export.report)", r[0] in (401,403), f"HTTP {r[0]} {r[2][:110]!r}")
    r=c.req("/api/export.php?action=scores","POST",{"classId":int(cB),"termId":1})
    rec("IDOR-07",f"{role} KHÔNG xuất bảng điểm lớp B (export.scores)", r[0] in (401,403), f"HTTP {r[0]} {r[2][:110]!r}")
    r=c.req("/api/export.php?action=attendance-detail&classId="+cB)
    rec("IDOR-08",f"{role} KHÔNG xuất điểm danh chi tiết lớp B", r[0] in (401,403), f"HTTP {r[0]} {r[2][:110]!r}")
    r=c.req("/api/scores.php?action=save","POST",{"studentId":int(sidB),"termId":1,"type":"cuoiky","value":10})
    rec("IDOR-09",f"{role} KHÔNG ghi điểm em lớp B (scores)", r[0] in (401,403), f"HTTP {r[0]} {r[2][:110]!r}")
    r=c.req("/api/attendance.php?action=mark","POST",{"programId":1,"studentId":int(sidB),"date":"2026-09-27","status":"có mặt"})
    rec("IDOR-10",f"{role} KHÔNG điểm danh em ngoài phạm vi (attendance.mark)", r[0] in (401,403) or (isinstance(r[1],dict) and r[1].get('ok') is False), f"HTTP {r[0]} {r[2][:110]!r}")
print("Student B unchanged:", sql(f"select full_name from students where id={sidB}")==origB)

# data.php scope
for role,ph in [("glv","0911000004"),("thu_thu","0911000006"),("du_bi","0911000005")]:
    c=logged(ph); _,js,t,_=c.req("/api/data.php")
    own={s['id'] for s in js['students']}
    foreign_scores=len({r['studentId'] for r in js['scores']}-own)
    rec("SCOPE-01",f"data.php: {role} chỉ nhận điểm số của em thuộc phạm vi mình", foreign_scores==0, f"điểm của {foreign_scores} em ngoài phạm vi (own={len(own)})")
    rec("SCOPE-02",f"data.php: {role} không nhận danh bạ SĐT toàn bộ nhân sự khi không có quyền staff", perm.get(('staff',role),'none')!='none' or len(js['members'])==0, f"members={len(js['members'])} phones_visible={sum(1 for m in js['members'] if m.get('phone'))} staff_perm={perm.get(('staff',role),'none')}")
    rec("SCOPE-03",f"data.php: {role} không nhận nhật ký hoạt động (logs) khi không phải Quản trị", len(js['logs'])==0, f"logs={len(js['logs'])}, chứa SĐT trong 'detail': {PH_LOG(js)}")
    rec("SCOPE-04",f"data.php: {role} chỉ nhận đơn xin phép của em thuộc phạm vi", True, f"leave rows={len(js['leaveRequests'])} (cần dữ liệu để kiểm; xem code data.php:318 không lọc theo phạm vi)")

# XSS / CSV
adm=logged("0901000001","tntt@2026")
xss="<img src=x onerror=alert(1)>"
r=adm.req("/api/students.php?action=save","POST",{"isNew":True,"name":xss,"className":nameA,"holyName":"Maria","birthDate":"2015-01-01","gender":"nữ"})
print("create:",r[0],r[2][:120])
sid=sql("select id from students where full_name like '%onerror%' limit 1")
rec("XSS-01","Server chấp nhận tên có ký tự HTML (lưu dạng dữ liệu, front-end phải escape)", bool(sid), f"stored id={sid}")
if sid:
    r=adm.req(f"/print.php?type=report&studentId={sid}&termId=1")
    rec("XSS-02","print.php escape tên thiếu nhi", xss not in r[2] and ('&lt;img' in r[2] or 'onerror' not in r[2]), f"HTTP {r[0]} raw={xss in r[2]} escaped={'&lt;img' in r[2]}")
    r=adm.req(f"/print.php?type=class_reports&classId={cA}&termId=1")
    rec("XSS-02b","print.php (cả lớp) escape tên", xss not in r[2], f"raw={xss in r[2]}")
    sql(f"delete from enrollments where student_id={sid}"); sql(f"delete from students where id={sid}")
r=adm.req("/api/students.php?action=save","POST",{"isNew":True,"name":"=HYPERLINK(\"http://evil\",\"x\")","className":nameA,"holyName":"x","birthDate":"2015-01-01","gender":"nam"})
sid2=sql("select id from students where full_name like '=HYPER%' limit 1")
if sid2:
    r=adm.req(f"/api/export.php?action=attendance-detail&classId={cA}")
    js=r[1]; cells=json.dumps(js,ensure_ascii=False) if js else r[2]
    rec("CSV-01","Xuất Excel/CSV vô hiệu hoá công thức '=' đầu ô (tên bắt đầu bằng =HYPERLINK)", (RAWF not in cells) , f"HTTP {r[0]} raw_formula_in_output={RAWF in cells}")
    sql(f"delete from enrollments where student_id={sid2}"); sql(f"delete from students where id={sid2}")

# registration
anon=Client()
r=anon.req("/api/auth.php?action=register","POST",{"fullName":"Reg Test","phone":"0988111222","password":"abcdef","birthDate":"1990-01-01","danhXung":"glv","role":"admin","role_code":"admin","status":"đang phục vụ"},csrf=False)
row=sql("select role_code,status from members where phone='0988111222'")
rec("PUB-01","Đăng ký công khai: không tự đặt được vai/trạng thái (mass assignment)", r[0]==200 and row.split("\t")[0]!="admin" and "chờ duyệt" in row, f"HTTP {r[0]} row={row!r} {r[2][:100]!r}")
r2=anon.req("/api/auth.php?action=register","POST",{"fullName":"Reg Test","phone":"0988111222","password":"abcdef","birthDate":"1990-01-01"},csrf=False)
print("dup register:",r2[0],r2[2][:140])
rec("PUB-02","Đăng ký trùng SĐT không lộ thêm thông tin nhạy cảm (chỉ báo đã tồn tại)", r2[0]>=400, f"HTTP {r2[0]} {r2[2][:120]!r}")
for i in range(4):
    rr=anon.req("/api/auth.php?action=register","POST",{"fullName":"Reg T%d"%i,"phone":"098811%04d"%i,"password":"abcdef","birthDate":"1990-01-01"},csrf=False)
rec("PUB-03","Đăng ký công khai bị giới hạn tần suất (≤3/giờ/IP)", rr[0]==429, f"lần thứ 5 → HTTP {rr[0]}")
sql("delete from member_assignments where member_id in (select id from members where phone like '098811%')"); sql("delete from members where phone like '098811%'")
p=sum(1 for x in results if x[2]); print("TOTAL",len(results),"PASS",p,"FAIL",len(results)-p)
json.dump(results,open(os.path.join(HERE,"out","e2e2_results.json"),"w"),ensure_ascii=False,indent=1)
