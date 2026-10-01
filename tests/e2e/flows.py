"""Luồng nghiệp vụ theo module: thiếu nhi, điểm danh + QR, xin phép, điểm số, thông báo, niên khoá, khối/lớp, bảo trì, ghi chú, thư viện, quà.
Chạy trên DB THỬ NGHIỆM (không phải DB thật). Xem BAO_CAO_KIEM_THU.md mục 9 để biết cách dựng môi trường.
Yêu cầu: php -S 127.0.0.1:8088 -t public (đã trỏ TNTT_DB_* vào DB thử), mysql client chạy được bằng root không mật khẩu, DB tên trong biến DB bên dưới.
"""
import os
HERE = os.path.dirname(os.path.abspath(__file__))
exec(open(os.path.join(HERE,'e2e2.py'), encoding='utf-8').read().split('for role,ph in [("glv","0911000004"),("glv_chu_nhiem"')[0])
import datetime, json
results.clear()
T=datetime.date(2026,9,29)
def sunday(offset_weeks): 
    d=T+datetime.timedelta(days=(6-T.weekday())%7)+datetime.timedelta(weeks=offset_weeks); return d.isoformat()
adm=logged("0901000001","tntt@2026"); glv=logged("0911000004"); gvcn=logged("0911000003"); tk=logged("0911000002"); tt=logged("0911000006"); bdh=logged("0911000001")
def ok(r): return r[0]==200 and isinstance(r[1],dict) and r[1].get('ok') is True
def show(r): return f"HTTP {r[0]} {r[2][:100]!r}"
nameA=sql(f"select name from classes where id={cA}"); nameB=sql(f"select name from classes where id={cB}")
# ---------- STUDENTS
r=adm.req("/api/students.php?action=save","POST",{"isNew":True,"name":"Trần Văn Thử","holyName":"Phêrô","className":nameA,"birthDate":"2014-05-05","gender":"nam","address":"1 Test","fatherName":"Cha","fatherPhone":"0912345678"})
sid=r[1].get('id') if ok(r) else None
code=sql(f"select code from students where id={sid}") if sid else ''
rec("STU-01","Thêm thiếu nhi mới: máy chủ cấp mã GDGLPT26xxxx + ghi danh vào lớp", ok(r) and re.fullmatch(r'GDGLPT26\d{4}',code or '') and sql(f"select count(*) from enrollments where student_id={sid}")=='1', f"code={code} {show(r)}")
r=adm.req("/api/students.php?action=save","POST",{"code":code,"name":"Trần Văn Thử","holyName":"Phêrô","className":nameA,"birthDate":"2014-05-05","gender":"nam","address":"2 Đổi địa chỉ"})
rec("STU-02","Sửa hồ sơ giữ nguyên mã, cập nhật địa chỉ", ok(r) and sql(f"select address from students where id={sid}")=="2 Đổi địa chỉ" and sql("select count(*) from students where full_name like 'TRẦN VĂN THỬ%'")=='1', show(r))
r=adm.req("/api/students.php?action=save","POST",{"isNew":True,"name":"   ","className":nameA})
rec("STU-03","Từ chối họ tên rỗng", not ok(r), show(r))
r=adm.req("/api/students.php?action=save","POST",{"isNew":True,"name":"A"*300,"className":nameA,"birthDate":"2014-05-05","gender":"nam"})
print("  long name:",show(r)); 
rec("STU-04","Tên quá dài (300 ký tự) được xử lý có kiểm soát (không 500)", r[0]<500, show(r))
if ok(r): sql(f"delete from enrollments where student_id={r[1]['id']}"); sql(f"delete from students where id={r[1]['id']}")
for bd in ["2099-01-01","abc","1800-01-01"]:
    r=adm.req("/api/students.php?action=save","POST",{"isNew":True,"name":"Ngày Sinh Xấu","className":nameA,"birthDate":bd,"gender":"nam"})
    acc=ok(r); 
    rec("STU-05","Ngày sinh vô lý '%s' bị từ chối/chuẩn hoá"%bd, not acc, show(r)+ (f" stored={sql('select birth_date from students where id=%s'%r[1]['id'])}" if acc else ""))
    if acc: sql(f"delete from enrollments where student_id={r[1]['id']}"); sql(f"delete from students where id={r[1]['id']}")
r=adm.req("/api/students.php?action=bulk_move","POST",{"student_ids":[sid],"target_class":nameB})
rec("STU-06","Chuyển lớp hàng loạt (admin)", ok(r) and sql(f"select class_id from enrollments where student_id={sid}")==cB, show(r))
r=adm.req("/api/students.php?action=favorites_toggle","POST",{"student_id":sid,"studentId":sid}); print("  fav toggle:",show(r))
# ---------- PROGRAM + ATTENDANCE + STAMPS
past_sun=sunday(-1); fut_sun=sunday(1)
sql(f"update enrollments set class_id={cA} where student_id={sid}")
r=adm.req("/api/attendance.php?action=toggle","POST",{"programId":1,"date":past_sun,"studentId":sid})
st=sql(f"select status from attendances where student_id={sid} and program_id=1 and session_date='{past_sun}'")
rec("ATT-01","Điểm danh 1 em (Chúa Nhật đã qua quá giờ chốt → 'đi trễ')", ok(r) and st=='đi trễ', f"status={st!r} {show(r)}")
r=adm.req("/api/attendance.php?action=toggle","POST",{"programId":1,"date":past_sun,"studentId":sid})
rec("ATT-02","Bấm lần 2 = gỡ điểm danh", ok(r) and r[1].get('removed') and sql(f"select count(*) from attendances where student_id={sid} and session_date='{past_sun}'")=='0', show(r))
r=adm.req("/api/attendance.php?action=toggle","POST",{"programId":1,"date":T.isoformat(),"studentId":sid})
rec("ATT-03","Điểm danh vào ngày KHÔNG có chương trình (thứ Ba) bị từ chối", not ok(r), show(r))
r=adm.req("/api/attendance.php?action=toggle","POST",{"programId":1,"date":fut_sun,"studentId":sid})
fut_ok=ok(r)
rec("ATT-04","Không cho điểm danh trước cho buổi TƯƠNG LAI (%s)"%fut_sun, not fut_ok, show(r)+(" ← đã ghi được điểm danh tương lai" if fut_ok else ""))
if fut_ok: adm.req("/api/attendance.php?action=toggle","POST",{"programId":1,"date":fut_sun,"studentId":sid})
r=adm.req("/api/attendance.php?action=toggle","POST",{"programId":1,"date":"not-a-date","studentId":sid})
rec("ATT-05","Ngày sai định dạng bị từ chối (không 500)", r[0]<500 and not ok(r), show(r))
r=adm.req("/api/attendance.php?action=toggle","POST",{"programId":999,"date":past_sun,"studentId":sid})
rec("ATT-06","Chương trình không tồn tại → 404", r[0]==404, show(r))
r=adm.req("/api/attendance.php?action=toggle","POST",{"programId":1,"date":past_sun,"studentId":99999})
rec("ATT-07","Em không tồn tại → 404", r[0]==404, show(r))
# QR scan
r=adm.req("/api/attendance.php?action=scan","POST",{"programId":1,"date":past_sun,"codes":[code],"method":"qr"})
rec("ATT-08","Quét QR bằng mã thẻ → ghi điểm danh", ok(r) and sql(f"select count(*) from attendances where student_id={sid} and session_date='{past_sun}'")=='1', show(r))
r=adm.req("/api/attendance.php?action=scan","POST",{"programId":1,"date":past_sun,"codes":[code],"method":"qr"})
rec("ATT-09","Quét lại cùng mã → không tạo trùng (idempotent)", ok(r) and sql(f"select count(*) from attendances where student_id={sid} and session_date='{past_sun}'")=='1', show(r))
r=adm.req("/api/attendance.php?action=scan","POST",{"programId":1,"date":past_sun,"codes":["KHONGCO123","' OR 1=1--"],"method":"qr"})
rec("ATT-10","Quét mã không tồn tại / mã độc hại: không 500", r[0]<500, show(r))
# glv other-class scan
r=glv.req("/api/attendance.php?action=scan","POST",{"programId":1,"date":past_sun,"codes":[sql(f"select code from students s join enrollments e on e.student_id=s.id where e.class_id={cB} limit 1")],"method":"qr"})
print("  glv scan other block:",show(r))
rec("ATT-11","GLV quét mã em KHỐI KHÁC không ghi được", not (ok(r) and r[1].get('added',0)>0), show(r))
# ---------- LEAVE
sql(f"delete from attendances where student_id={sid}")
r=adm.req("/api/leave.php?action=create","POST",{"studentId":sid,"programId":1,"date":past_sun,"reason":"Ốm"})
rec("LEAVE-01","Xin phép cho buổi ĐÃ QUA bị từ chối", not ok(r), show(r))
r=adm.req("/api/leave.php?action=create","POST",{"studentId":sid,"programId":1,"date":fut_sun,"reason":"Về quê"})
lid=r[1].get('id') if ok(r) else None
rec("LEAVE-02","Tạo đơn xin phép cho buổi tương lai", ok(r), show(r))
r=adm.req("/api/leave.php?action=create","POST",{"studentId":sid,"programId":1,"date":fut_sun,"reason":"Về quê"})
rec("LEAVE-03","Đơn trùng (cùng em/buổi) bị từ chối", not ok(r), show(r))
r=glv.req("/api/leave.php?action=approve","POST",{"id":lid})
rec("LEAVE-04","GLV (quyền leave?) không duyệt được đơn ngoài phạm vi", not ok(r) or perm.get(('leave','glv'))=='edit' and False, show(r)+f" perm(leave,glv)={perm.get(('leave','glv'))}")
r=adm.req("/api/leave.php?action=approve","POST",{"id":lid})
rec("LEAVE-05","Admin duyệt đơn → status 'đã duyệt'", ok(r) and sql(f"select status from leave_requests where id={lid}") in ('đã duyệt','da_duyet','approved'), f"status={sql(f'select status from leave_requests where id={lid}')!r} {show(r)}")
r=adm.req("/api/leave.php?action=approve","POST",{"id":lid})
rec("LEAVE-06","Duyệt lại đơn đã duyệt bị từ chối/không đổi", not ok(r), show(r))
r=adm.req("/api/leave.php?action=reject","POST",{"id":lid,"reason":"x"})
print("  reject after approve:",show(r), sql(f"select status from leave_requests where id={lid}"))
sql(f"delete from leave_requests where student_id={sid}")
# ---------- SCORES
for val,expect in [(8,True),(10,True),(0,True),(11,False),(-1,False),("abc",False),(7.55,True)]:
    r=adm.req("/api/scores.php?action=set","POST",{"studentId":sid,"termId":1,"type":"cuoiky","value":val})
    stored=sql(f"select value from scores where student_id={sid} and term_id=1 and type_code='cuoiky'")
    good=(ok(r)==expect)
    rec("SCORE-01","Ghi điểm giá trị %r → %s"%(val,"chấp nhận" if expect else "từ chối"), good, show(r)+f" stored={stored}")
r=adm.req("/api/scores.php?action=set","POST",{"studentId":sid,"termId":1,"type":"khong_co","value":5})
rec("SCORE-02","Loại điểm không tồn tại bị từ chối", not ok(r), show(r))
r=adm.req("/api/scores.php?action=set","POST",{"studentId":sid,"termId":999,"type":"cuoiky","value":5})
rec("SCORE-03","Học kỳ không tồn tại bị từ chối", not ok(r), show(r))
# reports
r=adm.req("/api/reports.php?action=save","POST",{"studentId":sid,"termId":1,"attendance":{},"score":8,"conduct":"tốt","rank":"giỏi","remark":"<b>Ngoan</b>","send":True})
rec("REPORT-01","Lưu & gửi phiếu liên lạc", ok(r), show(r))
r=adm.req("/api/reports.php?action=delete","POST",{"studentId":sid,"termId":1}); rec("REPORT-02","Xoá phiếu liên lạc", ok(r), show(r))
# ---------- ANNOUNCEMENTS
r=adm.req("/api/announcements.php?action=save","POST",{"title":"Thông báo thử","body":"<script>alert(1)</script>","level":"normal","audienceType":"all","status":"published"})
aid=r[1].get('id') if ok(r) else None
rec("ANN-01","Tạo thông báo", ok(r), show(r))
r=glv.req("/api/announcements.php?action=save","POST",{"title":"glv đăng","body":"x","audienceType":"all"})
rec("ANN-02","GLV thường không đăng được thông báo toàn đoàn", not ok(r), show(r)+f" perm={perm.get(('announcements','glv'))}")
if aid:
    r=glv.req("/api/announcements.php?action=read","POST",{"id":aid}); rec("ANN-03","GLV đánh dấu đã đọc", ok(r), show(r))
    r=glv.req("/api/announcements.php?action=delete","POST",{"id":aid}); rec("ANN-04","GLV không xoá được thông báo của BĐH", not ok(r), show(r))
    r=adm.req("/api/announcements.php?action=delete","POST",{"id":aid}); rec("ANN-05","Admin xoá thông báo", ok(r), show(r))
# ---------- YEARS
r=adm.req("/api/years.php?action=create","POST",{"name":"2027 - 2028","startDate":"2027-08-01","endDate":"2028-05-31"}); yid=r[1].get('id') if ok(r) else None
rec("YEAR-01","Tạo niên khoá mới", ok(r), show(r))
r=adm.req("/api/years.php?action=create","POST",{"name":"2027 - 2028","startDate":"2027-08-01","endDate":"2028-05-31"}); rec("YEAR-02","Tên niên khoá trùng bị từ chối", not ok(r), show(r))
r=adm.req("/api/years.php?action=create","POST",{"name":"Sai ngày","startDate":"2028-08-01","endDate":"2027-05-31"}); rec("YEAR-03","Ngày kết thúc < ngày bắt đầu bị từ chối", not ok(r), show(r))
if not ok(r) is False: pass
if yid:
    r=glv.req("/api/years.php?action=activate","POST",{"id":yid}); rec("YEAR-04","GLV không kích hoạt được niên khoá", not ok(r), show(r))
    r=adm.req("/api/years.php?action=lock","POST",{"id":yid}); print("  lock:",show(r))
    r=adm.req("/api/years.php?action=unlock","POST",{"id":yid}); print("  unlock:",show(r))
# ---------- ORG
r=adm.req("/api/org.php?action=saveBlock","POST",{"name":"Khối Thử","id":0}); print("  saveBlock",show(r))
r=adm.req("/api/org.php?action=saveClass","POST",{"name":"Lớp Thử","block":"Khối Thử"}); print("  saveClass",show(r))
r=adm.req("/api/org.php?action=deleteBlock","POST",{"id":sql("select id from blocks where name='Khối Thử'")})
rec("ORG-01","Không xoá được khối còn lớp con", not ok(r), show(r))
cid_t=sql("select id from classes where name='Lớp Thử'")
if cid_t:
    sql(f"insert into enrollments (year_id,student_id,class_id,status) values (1,{sid},{cid_t},'đang sinh hoạt') on duplicate key update class_id={cid_t}")
    r=adm.req("/api/org.php?action=deleteClass","POST",{"id":int(cid_t)}); rec("ORG-02","Không xoá được lớp còn thiếu nhi", not ok(r), show(r))
    sql(f"update enrollments set class_id={cA} where student_id={sid}")
    r=adm.req("/api/org.php?action=deleteClass","POST",{"id":int(cid_t)}); rec("ORG-03","Xoá lớp rỗng", ok(r), show(r))
bid_t=sql("select id from blocks where name='Khối Thử'")
if bid_t: r=adm.req("/api/org.php?action=deleteBlock","POST",{"id":int(bid_t)}); rec("ORG-04","Xoá khối rỗng", ok(r), show(r))
r=adm.req("/api/org.php?action=resetPassword","POST",{"id":int(sql("select id from members where phone='0911000006'"))})
print("  resetPassword",show(r), "must_change_pw=",sql("select must_change_pw from members where phone='0911000006'"))
rec("ORG-05","Admin cấp lại mật khẩu → buộc đổi ở lần đăng nhập sau", ok(r) and sql("select must_change_pw from members where phone='0911000006'")=='1', show(r))
sql("update members set must_change_pw=0, password_hash='%s' where phone='0911000006'"%HASH.replace("'","''"))
# ---------- SETTINGS: maintenance mode + permission live change
r=adm.req("/api/settings.php?action=module","POST",{"moduleKey":"gifts","enabled":0,"isEnabled":0,"level":0}); print("  module toggle",show(r))
r2=tt.req("/api/gifts.php?action=list"); print("  gifts during maintenance:",show(r2))
rec("SET-01","Module bảo trì → người không phải admin nhận 503", r2[0]==503, show(r2))
adm.req("/api/settings.php?action=module","POST",{"moduleKey":"gifts","enabled":1,"isEnabled":1})
# ---------- NOTES privacy
r=bdh.req("/api/notes.php?action=save","POST",{"title":"Riêng tư","date":"2026-10-01","note":"x"}); nid=r[1].get('id') if ok(r) else None
if nid:
    r=glv.req("/api/notes.php?action=delete","POST",{"id":nid}); rec("NOTE-01","Ghi chú cá nhân: người khác không xoá được", sql(f"select count(*) from personal_notes where id={nid}")=='1' if sql("show tables like 'personal_notes'") else not ok(r), show(r))
    bdh.req("/api/notes.php?action=delete","POST",{"id":nid})
# ---------- LIBRARY
r=adm.req("/api/library.php?action=saveArticle","POST",{"title":"Bài thử","body":"Nội dung","category_id":1}); lid2=r[1].get('id') if ok(r) else None
rec("LIB-01","Admin đăng bài sổ tay", ok(r), show(r))
r=glv.req("/api/library.php?action=approve","POST",{"id":lid2 or 1}); rec("LIB-02","GLV không duyệt được tài liệu", not ok(r), show(r))
if lid2: r=adm.req("/api/library.php?action=delete","POST",{"id":lid2}); rec("LIB-03","Admin xoá bài", ok(r), show(r))
# ---------- gifts / rewards
r=adm.req("/api/gifts.php?action=save","POST",{"name":"Quà thử","stampCost":5,"stock":2,"status":"active","sortOrder":1}); gid=r[1].get('id') if ok(r) else None
rec("GIFT-01","Tạo quà", ok(r), show(r))
for bad in [{"name":"Quà âm","stampCost":-5,"stock":1},{"name":"Quà 0","stampCost":0,"stock":1},{"name":"","stampCost":3,"stock":1},{"name":"Tồn âm","stampCost":3,"stock":-9}]:
    r=adm.req("/api/gifts.php?action=save","POST",dict(bad,status="active")); 
    rec("GIFT-02","Quà dữ liệu xấu %s bị từ chối"%bad, not ok(r), show(r))
    if ok(r): adm.req("/api/gifts.php?action=delete","POST",{"id":r[1].get('id')})
if gid: adm.req("/api/gifts.php?action=delete","POST",{"id":gid})
# cleanup
sql(f"delete from enrollments where student_id={sid}"); sql(f"delete from students where id={sid}")
if yid: sql(f"delete from school_years where id={yid}")
p=sum(1 for x in results if x[2]); print("TOTAL",len(results),"PASS",p,"FAIL",len(results)-p)
json.dump(results,open(os.path.join(HERE,"out","flows_results.json"),"w"),ensure_ascii=False,indent=1)
