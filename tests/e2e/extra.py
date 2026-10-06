"""Kịch bản bổ sung: chương trình, nhân sự/duyệt tài khoản, phân công, lên lớp, nhập Excel, thư viện, thông báo họp, đổi quà.
Chạy trên DB THỬ NGHIỆM. Nên sao lưu DB trước (script làm thay đổi ghi danh/niên khoá)."""
import os
HERE = os.path.dirname(os.path.abspath(__file__))
exec(open(os.path.join(HERE,'e2e2.py'), encoding='utf-8').read().split('for role,ph in [("glv","0911000004"),("glv_chu_nhiem"')[0])
import datetime, json, base64
results.clear()
def ok(r): return r[0]==200 and isinstance(r[1],dict) and r[1].get('ok') is True
def show(r): return f"HTTP {r[0]} {r[2][:130]!r}"
sql("DELETE FROM login_attempts"); sql("UPDATE members SET must_change_pw=0")
adm=logged("0901000001","tntt@2026"); bdh=logged("0911000001"); tk=logged("0911000002"); gvcn=logged("0911000003"); glv=logged("0911000004"); tt=logged("0911000006")
nameA=sql(f"select name from classes where id={cA}"); nameB=sql(f"select name from classes where id={cB}")
def P(c,path,data=None,m="POST"): return c.req(path,m,data if m=="POST" else None)

# ---------------- PROGRAMS
base={"name":"CT Thử","type":"bắt buộc","startTime":"08:00","cutoffTime":"08:15","absentTime":"09:00","dayOfWeek":0,"countForAttendance":True,"status":"kích hoạt"}
r=P(adm,"/api/programs.php?action=save",base); pid=r[1].get('id') if ok(r) else None
rec("PRG-01","Tạo chương trình lặp hàng tuần hợp lệ", ok(r), show(r))
for lab,ch in [("giờ sai định dạng",{"startTime":"8h"}),("thiếu giờ tính đi trễ",{"cutoffTime":""}),("giờ đi trễ ≤ giờ bắt đầu",{"cutoffTime":"07:00"}),("thiếu giờ khoá sổ",{"absentTime":""}),("giờ khoá sổ < giờ đi trễ",{"absentTime":"08:05"}),("thứ = 9",{"dayOfWeek":9}),("tên rỗng",{"name":" "}),("ngày áp dụng từ > đến",{"effectiveFrom":"2026-12-01","effectiveTo":"2026-11-01"}),("chiến dịch thiếu ngày",{"type":"chiến dịch"})]:
    r=P(adm,"/api/programs.php?action=save",dict(base,**ch)); rec("PRG-02",f"Từ chối chương trình: {lab}", r[0]==400, show(r))
    if ok(r): P(adm,"/api/programs.php?action=delete",{"id":r[1].get('id')})
r=P(glv,"/api/programs.php?action=save",base); rec("PRG-03","GLV không tạo được chương trình", r[0]==403, show(r))
if pid:
    r=P(adm,"/api/programs.php?action=save",dict(base,id=pid,name="CT Thử (sửa)")); rec("PRG-04","Sửa chương trình", ok(r) and sql(f"select name from programs where id={pid}")=="CT Thử (sửa)", show(r))
    r=P(adm,"/api/programs.php?action=delete",{"id":pid}); rec("PRG-05","Xoá chương trình", ok(r) and sql(f"select count(*) from programs where id={pid}")=="0", show(r))
r=P(adm,"/api/programs.php?action=delete",{"id":1})
print("  delete program có điểm danh (id=1):",show(r), "còn?",sql("select count(*) from programs where id=1"), "điểm danh còn:",sql("select count(*) from attendances where program_id=1"))
rec("PRG-06","Xoá chương trình đang có điểm danh: hoặc bị chặn hoặc cảnh báo rõ (không mất dữ liệu âm thầm)", (not ok(r)) or sql("select count(*) from attendances where program_id=1")!="0", show(r)+f" attendances_left={sql('select count(*) from attendances where program_id=1')}")

# ---------------- STAFF / duyệt tài khoản
sql("delete from member_assignments where member_id in (select id from members where phone like '0977%')"); sql("delete from members where phone like '0977%'")
mk_member("T_ST","0977000001","glv"); mid=sql("select id from members where phone='0977000001'")
r=P(adm,"/api/org.php?action=saveMember",{"fullName":"Tạo Tay","phone":"0977000009","role":"glv"})
rec("STAFF-00","Tạo thành viên thủ công bị từ chối theo thiết kế (phải tự đăng ký rồi duyệt)", not ok(r) and sql("select count(*) from members where phone='0977000009'")=="0", show(r))
r=P(adm,"/api/org.php?action=saveMember",{"id":int(mid),"fullName":"Nhân Sự Sửa","holyName":"Anna","phone":"0977000001","role":"glv"})
rec("STAFF-01","Admin sửa hồ sơ thành viên", ok(r) and "NHÂN SỰ SỬA" in sql(f"select upper(full_name) from members where id={mid}").upper(), show(r)+" tên="+sql(f"select full_name from members where id={mid}"))
r=P(adm,"/api/org.php?action=saveMember",{"id":int(mid),"fullName":"Trùng SĐT","phone":"0901000001","role":"glv"}); rec("STAFF-02","Đổi SĐT trùng người khác bị từ chối", not ok(r) and sql(f"select phone from members where id={mid}")=="0977000001", show(r))
r=P(adm,"/api/org.php?action=saveMember",{"id":int(mid),"fullName":"","phone":"0977000001","role":"glv"}); rec("STAFF-03","Thiếu tên bị từ chối", not ok(r), show(r))
r=P(adm,"/api/org.php?action=saveMember",{"id":int(mid),"fullName":"SĐT Ngắn","phone":"12","role":"glv"}); rec("STAFF-04","SĐT không hợp lệ ('12') bị từ chối", not ok(r), show(r)+" phone="+sql(f"select phone from members where id={mid}"))
sql(f"update members set phone='0977000001' where id={mid}")
r=P(bdh,"/api/org.php?action=saveMember",{"id":int(mid),"fullName":"Nâng Quyền","phone":"0977000001","role":"admin"}); role_now=sql(f"select role_code from members where id={mid}")
rec("STAFF-05","BĐH KHÔNG nâng thành viên lên admin (leo thang đặc quyền)", role_now!="admin", show(r)+" role="+repr(role_now))
sql(f"update members set role_code='glv' where id={mid}")
r=P(bdh,"/api/org.php?action=saveMember",{"id":int(sql("select id from members where role_code='admin' order by id limit 1")),"fullName":"Bị Đổi","phone":"0901000001","role":"glv"}); adm_role=sql("select role_code from members where phone='0901000001'")
rec("STAFF-06","BĐH KHÔNG hạ vai / sửa hồ sơ admin", adm_role=="admin" and not ok(r), show(r)+" role="+repr(adm_role))
sql("update members set role_code='admin' where phone='0901000001'")
r=P(tk,"/api/org.php?action=saveMember",{"id":int(mid),"fullName":"TK sửa","phone":"0977000001","role":"glv"}); rec("STAFF-07","Trưởng khối không có quyền staff không sửa được thành viên", not ok(r), show(r))
# self-register + approve/reject
anon=Client()
for ph,nm in (("0977100001","Chờ Duyệt A"),("0977100002","Chờ Duyệt B")):
    r=anon.req("/api/auth.php?action=register","POST",{"fullName":nm,"phone":ph,"password":"abcdef","birthDate":"1990-01-01","danhXung":"glv"},csrf=False)
    sql("delete from login_attempts where phone like 'reg:%'")
ida=sql("select id from members where phone='0977100001'"); idb=sql("select id from members where phone='0977100002'")
c=Client(); r0=c.login("0977100001","abcdef"); rec("STAFF-08","Tài khoản 'chờ duyệt' chưa đăng nhập được", r0[0]==403, show((r0[0],None,json.dumps(r0[1],ensure_ascii=False))))
r=P(adm,"/api/org.php?action=approveMember",{"id":int(ida),"role":"glv"}); rec("STAFF-09","Duyệt tài khoản chờ", ok(r) and sql(f"select status from members where id={ida}")=="đang phục vụ", show(r))
sql("delete from login_attempts"); c=Client(); r0=c.login("0977100001","abcdef"); rec("STAFF-10","Sau duyệt đăng nhập được", r0[0]==200 and r0[1] and r0[1].get('ok'), str(r0[0]))
r=P(adm,"/api/org.php?action=approveMember",{"id":int(ida),"role":"glv"}); rec("STAFF-11","Duyệt lần 2 bị từ chối", not ok(r), show(r))
r=P(adm,"/api/org.php?action=approveMember",{"id":int(idb),"role":"admin"}); rec("STAFF-12","Duyệt với vai 'admin' bị từ chối (chỉ glv/du_bi)", not ok(r), show(r))
r=P(adm,"/api/org.php?action=rejectMember",{"id":int(idb)}); print("  reject:",show(r), sql(f"select count(*) from members where id={idb}"))
rec("STAFF-13","Từ chối tài khoản chờ", ok(r), show(r))
r=P(glv,"/api/org.php?action=approveMember",{"id":int(idb),"role":"glv"}); rec("STAFF-14","GLV không duyệt được tài khoản", r[0]==403, show(r))
# delete
r=P(adm,"/api/org.php?action=deleteMember",{"id":int(sql("select id from members where role_code='admin' order by id limit 1"))}); print("  xoá chính admin duy nhất:",show(r))
rec("STAFF-15","Không cho xoá tài khoản admin duy nhất/chính mình", not ok(r) and sql("select count(*) from members where role_code='admin'")!="0", show(r))
r=P(adm,"/api/org.php?action=deleteMember",{"id":int(mid)}); rec("STAFF-16","Xoá thành viên thường", ok(r), show(r))

# ---------------- ASSIGNMENTS
sql("delete from member_assignments where member_id in (select id from members where phone like '0977%')"); sql("delete from members where phone like '0977%'")
mk_member("T_ASG","0977200001","glv")
mid=sql("select id from members where phone='0977200001'")
r=P(adm,"/api/assignments.php?action=create",{"memberId":int(mid),"role":"glv","classId":int(cA)}); ok1=ok(r); aid=r[1].get('id') if ok1 and isinstance(r[1],dict) else sql(f"select max(id) from member_assignments where member_id={mid}")
rec("ASG-01","Admin phân công GLV vào lớp", ok1, show(r))
r=P(adm,"/api/assignments.php?action=create",{"memberId":int(mid),"role":"glv"}); rec("ASG-02","Vai phạm vi lớp mà thiếu lớp bị từ chối", not ok(r), show(r))
r=P(adm,"/api/assignments.php?action=create",{"memberId":int(mid),"role":"khong_co","classId":int(cA)}); rec("ASG-03","Vai không tồn tại bị từ chối", not ok(r), show(r))
r=P(tk,"/api/assignments.php?action=create",{"memberId":int(mid),"role":"glv","classId":int(cB)}); rec("ASG-04","Trưởng khối KHÔNG phân công vào lớp ngoài khối mình", r[0]==403 or not ok(r), show(r)+f" (TK khối {bA}, lớp B khối {bB})")
r=P(tk,"/api/assignments.php?action=create",{"memberId":int(mid),"role":"admin"}); rec("ASG-05","Trưởng khối KHÔNG gán vai admin", not ok(r), show(r))
r=P(bdh,"/api/assignments.php?action=create",{"memberId":int(mid),"role":"admin"}); rec("ASG-06","BĐH KHÔNG gán vai admin/BĐH (chỉ admin)", not ok(r), show(r))
r=P(adm,"/api/assignments.php?action=delete",{"assignmentId":int(aid)}); rec("ASG-07","Không xoá phân công còn hiệu lực (phải kết thúc trước)", not ok(r), show(r))
r=P(adm,"/api/assignments.php?action=end",{"assignmentId":int(aid)}); rec("ASG-08","Kết thúc phân công", ok(r), show(r))
r=P(adm,"/api/assignments.php?action=end",{"assignmentId":int(aid)}); rec("ASG-09","Kết thúc lần 2 bị từ chối", not ok(r), show(r))
r=P(adm,"/api/assignments.php?action=delete",{"assignmentId":int(aid)}); rec("ASG-10","Xoá phân công đã kết thúc", ok(r), show(r))
r=P(glv,"/api/assignments.php?action=create",{"memberId":int(mid),"role":"glv","classId":int(cA)}); rec("ASG-11","GLV không tự phân công", r[0]==403, show(r))
sql("delete from member_assignments where member_id in (select id from members where phone like '0977%')"); sql("delete from members where phone like '0977%'")

# ---------------- IMPORT
codeB=sql(f"select code from students s join enrollments e on e.student_id=s.id where e.class_id={cB} limit 1")
n0=sql("select count(*) from students")
rows=[{"name":"Nhập Một","holyName":"An","className":nameA,"birthDate":"2015-03-03","gender":"nam"},
      {"name":"Nhập Hai","className":"Lớp Không Có","birthDate":"2015-03-03","gender":"nữ"},
      {"name":"","className":nameA},
      {"name":"=cmd|' /C calc'!A0","className":nameA,"birthDate":"2015-03-03","gender":"nam"},
      {"code":codeB,"name":"GHI ĐÈ EM LỚP B","className":nameA}]
r=P(gvcn,"/api/students.php?action=import",{"rows":rows}); print("  gvcn import:",show(r))
rec("IMP-01","GVCN import: dòng hợp lệ được thêm; lớp lạ / tên rỗng bị bỏ qua", ok(r) and r[1].get('added')>=1 and r[1].get('skipped')>=2, show(r))
rec("IMP-02","GVCN KHÔNG ghi đè được em lớp khác qua import (mã trùng)", sql(f"select full_name from students where code='{codeB}'")!="GHI ĐÈ EM LỚP B", "tên hiện tại="+repr(sql(f"select full_name from students where code='{codeB}'")))
r2=P(adm,"/api/students.php?action=import",{"rows":[{"name":"Nhập Ba","className":nameA,"birthDate":"2099-05-05","gender":"nam"}]}); print("  admin import ngày sinh 2099:",show(r2))
rec("IMP-03","Import từ chối ngày sinh tương lai (đồng bộ với issue #86)", not (ok(r2) and r2[1].get('added')), show(r2))
r=P(adm,"/api/students.php?action=import",{"rows":[]}); rec("IMP-04","Import rỗng bị từ chối", not ok(r), show(r))
r=P(adm,"/api/students.php?action=import",{"rows":[{"name":"X"*7,"className":nameA}]*3000}); print("  import 3000 dòng:",show(r)); rec("IMP-05","Import 3.000 dòng không lỗi 500/timeout", r[0]<500, show(r))
r=P(tt,"/api/students.php?action=import",{"rows":rows}); rec("IMP-06","Thủ thư không import được", r[0]==403, show(r))
formula=sql("select count(*) from students where full_name like '=%' or full_name like '%CMD|%'")
print("  tên chứa công thức đã lưu:",formula)
sql("delete from enrollments where student_id in (select id from students where full_name like 'NHẬP %' or full_name like 'XXXXXXX%' or full_name like '=%' or full_name like '%CMD|%')")
sql("delete from students where full_name like 'NHẬP %' or full_name like 'XXXXXXX%' or full_name like '=%' or full_name like '%CMD|%'")

# ---------------- LIBRARY approve / reject
png=base64.b64decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==")
def up(c,name,content,ct="image/png"):
    b="----y"; body=(f"--{b}\r\nContent-Disposition: form-data; name=\"title\"\r\n\r\nTL {name}\r\n--{b}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"{name}\"\r\nContent-Type: {ct}\r\n\r\n").encode()+content+f"\r\n--{b}--\r\n".encode()
    return c.req("/api/library.php?action=upload","POST",raw=body,headers={"Content-Type":f"multipart/form-data; boundary={b}"})
r=up(glv,"glv.png",png); print("  glv upload:",show(r)); 
lid=sql("select id from library_items where original_name='glv.png' order by id desc limit 1")
if ok(r) and lid:
    st=sql(f"select status from library_items where id={lid}")
    rec("LIB-04","GLV tải tài liệu lên → trạng thái chờ duyệt", st=="cho_duyet", f"status={st}")
    r=up(glv,"glv.png",png)
    r=P(glv,"/api/library.php?action=approve",{"id":int(lid)}); rec("LIB-05","GLV không tự duyệt tài liệu của mình", not ok(r), show(r))
    r=P(adm,"/api/library.php?action=approve",{"id":int(lid)}); rec("LIB-06","Admin duyệt", ok(r) and sql(f"select status from library_items where id={lid}")=="da_duyet", show(r))
    lid_p=sql("select max(id) from library_items where original_name='glv.png'")
    sql(f"update library_items set status='cho_duyet' where id={lid}")
    r=tt.req(f"/api/library_file.php?id={lid}&mode=view","GET"); rec("LIB-11","Tệp CHƯA duyệt của người khác không xem được (thủ thư)", r[0]==403, f"HTTP {r[0]}")
    r=bdh.req(f"/api/library_file.php?id={lid}&mode=view","GET"); rec("LIB-12","Tệp chưa duyệt: BĐH (không có quyền duyệt thư viện?) — ghi nhận hành vi", True, f"HTTP {r[0]} perm(thu_vien,bdh)={perm.get(('thu_vien','bdh'))}")
    sql(f"update library_items set status='da_duyet' where id={lid}")
    r=glv.req(f"/api/library_file.php?id={lid}&mode=view","GET"); rec("LIB-07","Tệp đã duyệt xem được bởi người có quyền", r[0]==200, f"HTTP {r[0]} {len(r[2])}B")
    r=tt.req(f"/api/library_file.php?id={lid}&mode=view","GET"); rec("LIB-08","Tệp ĐÃ duyệt: mọi thành viên đăng nhập xem được (theo thiết kế, dù thủ thư không có module thư viện)", r[0]==200, f"HTTP {r[0]}")
    r=Client().req(f"/api/library_file.php?id={lid}&mode=view","GET"); rec("LIB-09","Chưa đăng nhập không tải được tệp", r[0] in (401,403), f"HTTP {r[0]}")
    r=P(adm,"/api/library.php?action=reject",{"id":int(lid),"reason":"Sai chủ đề"}); print("  reject sau duyệt:",show(r))
    r=P(adm,"/api/library.php?action=delete",{"id":int(lid)}); rec("LIB-10","Xoá tài liệu + xoá tệp trên đĩa", ok(r) and sql(f"select count(*) from library_items where id={lid}")=="0", show(r))
sql("delete from library_items where original_name in ('glv.png','ok.png')")

# ---------------- ANNOUNCEMENTS meeting + RSVP
r=P(adm,"/api/announcements.php?action=save",{"title":"Họp thử","body":"Họp GLV","level":"important","audienceType":"all","status":"đã phát","isMeeting":True,"meetingAt":"2026-10-10 19:30","meetingPlace":"Phòng họp"}); aid=r[1].get('id') if ok(r) else None
rec("ANN-06","Tạo thông báo họp", ok(r), show(r))
if aid:
    r=glv.req("/api/announcements.php?action=rsvp","POST",{"id":aid,"status":"tham gia"}); print("  rsvp:",show(r)); rec("ANN-07","GLV phản hồi RSVP", ok(r), show(r))
    r=glv.req("/api/announcements.php?action=rsvp","POST",{"id":aid,"status":"<script>"}); rec("ANN-08","RSVP giá trị lạ bị từ chối/chuẩn hoá", (not ok(r)) or sql(f"select status from meeting_rsvp where announcement_id={aid} and member_id=(select id from members where phone='0911000004')") in ("tham gia","không tham gia"), show(r)+f" stored={sql(f'select status from meeting_rsvp where announcement_id={aid}')!r}")
    r=adm.req("/api/announcements.php?action=rsvpList","POST",{"id":aid}); rec("ANN-09","Admin xem danh sách RSVP", ok(r), show(r))
    r=glv.req("/api/announcements.php?action=rsvpList","POST",{"id":aid}); rec("ANN-10","GLV không xem được danh sách RSVP của người khác", not ok(r), show(r))
    r=P(adm,"/api/announcements.php?action=save",{"title":"Họp sai","body":"x","audienceType":"all","status":"published","isMeeting":True,"meetingAt":"không phải ngày"}); rec("ANN-11","Ngày họp sai định dạng bị từ chối", not ok(r) or r[0]<500, show(r)); 
    if ok(r): P(adm,"/api/announcements.php?action=delete",{"id":r[1].get('id')})
    P(adm,"/api/announcements.php?action=delete",{"id":aid})

# ---------------- REWARDS (đổi quà tại quầy)
sid=sql("select id from students limit 1 offset 40"); scode=sql(f"select code from students where id={sid}")
sql(f"delete from stamp_transactions where student_id={sid}"); sql(f"delete from student_stamps where student_id={sid}")
sql(f"insert into student_stamps (year_id,student_id,current_balance,held_balance,total_earned,current_streak,longest_streak) values (1,{sid},10,0,10,0,0)")
r=P(adm,"/api/gifts.php?action=save",{"name":"Quà Đổi Thử","stampCost":4,"stock":3,"status":"active","sortOrder":1}); gid=r[1].get('id') if ok(r) else None
tt_=tt
r=P(tt,"/api/rewards.php?action=lookup",{"code":scode}); rec("RWD-01","Thủ thư tra cứu em theo mã", ok(r), show(r))
r=P(tt,"/api/rewards.php?action=redeem",{"studentCode":scode,"items":[{"giftId":gid,"qty":2}]}); bal=sql(f"select current_balance from student_stamps where student_id={sid}")
rec("RWD-02","Đổi 2 quà × 4 Mộc: trừ đúng 8, số dư còn 2, tồn kho 1", ok(r) and bal=="2" and sql(f"select stock from gifts where id={gid}")=="1", show(r)+f" bal={bal} stock={sql(f'select stock from gifts where id={gid}')}")
r=P(tt,"/api/rewards.php?action=redeem",{"studentCode":scode,"items":[{"giftId":gid,"qty":1}]}); rec("RWD-03","Không đủ Mộc (còn 2 < 4) bị từ chối, không trừ", not ok(r) and sql(f"select current_balance from student_stamps where student_id={sid}")=="2", show(r))
sql(f"update student_stamps set current_balance=100 where student_id={sid}")
r=P(tt,"/api/rewards.php?action=redeem",{"studentCode":scode,"items":[{"giftId":gid,"qty":5}]}); rec("RWD-04","Vượt tồn kho (5 > 1) bị từ chối", not ok(r) and sql(f"select stock from gifts where id={gid}")=="1", show(r))
for lab,it in [("qty âm",[{"giftId":gid,"qty":-3}]),("qty 0",[{"giftId":gid,"qty":0}]),("quà không tồn tại",[{"giftId":99999,"qty":1}]),("qty rất lớn",[{"giftId":gid,"qty":10**12}])]:
    b0=sql(f"select current_balance from student_stamps where student_id={sid}")
    r=P(tt,"/api/rewards.php?action=redeem",{"studentCode":scode,"items":it}); rec("RWD-05",f"Giỏ quà xấu ({lab}) bị từ chối, số dư không đổi", not ok(r) and sql(f"select current_balance from student_stamps where student_id={sid}")==b0, show(r))
r=P(glv,"/api/rewards.php?action=redeem",{"studentCode":scode,"items":[{"giftId":gid,"qty":1}]}); rec("RWD-06","GLV không đổi quà được", r[0]==403, show(r))
r=P(tt,"/api/rewards.php?action=redeem",{"studentCode":"KHONGCO","items":[{"giftId":gid,"qty":1}]}); rec("RWD-07","Mã em không tồn tại → 404", r[0]==404, show(r))
# double-spend race
sql(f"update student_stamps set current_balance=4 where student_id={sid}"); sql(f"update gifts set stock=10 where id={gid}")
import threading
outs=[]
def go():
    c=logged("0911000006"); outs.append(P(c,"/api/rewards.php?action=redeem",{"studentCode":scode,"items":[{"giftId":gid,"qty":1}]}))
ths=[threading.Thread(target=go) for _ in range(4)]; [t.start() for t in ths]; [t.join() for t in ths]
okc=sum(1 for o in outs if ok(o)); balf=sql(f"select current_balance from student_stamps where student_id={sid}")
rec("RWD-08","Đổi quà đồng thời 4 lần khi chỉ đủ Mộc cho 1: chỉ 1 thành công, số dư không âm", okc==1 and int(balf)>=0, f"thành công={okc}/4, số dư cuối={balf}")
# cleanup
sql(f"delete from stamp_transactions where student_id={sid}"); sql(f"delete from student_stamps where student_id={sid}")
sql("delete from gift_order_items"); sql("delete from gift_orders"); 
if gid: sql(f"delete from gifts where id={gid}")

# ---------------- PROMOTION (lên lớp)
blockname=sql(f"select name from blocks where id={bA}")
r=P(adm,"/api/promotion.php?action=run",{"block":blockname,"targetYearId":1,"results":{}}); rec("PROMO-01","Niên khoá đích trùng niên khoá hiện tại bị từ chối", not ok(r), show(r))
r=P(adm,"/api/promotion.php?action=run",{"block":blockname,"targetYearId":999,"results":{}}); rec("PROMO-02","Niên khoá đích không tồn tại → 404", r[0]==404, show(r))
r=P(adm,"/api/promotion.php?action=run",{"block":"Khối Ma","targetYearId":2,"results":{}}); print("  khối ma:",show(r))
r=P(adm,"/api/years.php?action=create",{"name":"2030 - 2031","startDate":"2030-08-01","endDate":"2031-05-31"}); ty=r[1].get('id') if ok(r) else sql("select id from school_years where name='2030 - 2031'")
r=P(glv,"/api/promotion.php?action=run",{"block":blockname,"targetYearId":int(ty),"results":{}}); rec("PROMO-03","GLV không chạy được lên lớp", r[0]==403, show(r))
cl=sql(f"select id from classes where block_id={bA} order by id").split()
for i,cid_ in enumerate(cl):
    if i+1<len(cl): sql(f"update classes set next_class_id={cl[i+1]}, is_final=0 where id={cid_}")
    else: sql(f"update classes set next_class_id=NULL, is_final=1 where id={cid_}")
before=sql("select count(*) from enrollments where year_id=1")
r=P(adm,"/api/promotion.php?action=run",{"block":blockname,"targetYearId":int(ty),"results":{}}); print("  run (tất cả ở lại):",show(r))
n_t=sql(f"select count(*) from enrollments where year_id={ty}")
rec("PROMO-04","Lên lớp cả khối (mặc định 'ở lại'): tạo ghi danh năm đích đúng số em", ok(r) and int(n_t)==r[1].get('stay') and r[1].get('up')==0, show(r)+f" ghi danh đích={n_t}")
r=P(adm,"/api/promotion.php?action=run",{"block":blockname,"targetYearId":int(ty),"results":{}}); n_t2=sql(f"select count(*) from enrollments where year_id={ty}")
rec("PROMO-05","Chạy lại lần 2 không tạo trùng ghi danh (idempotent)", n_t2==n_t, f"trước={n_t} sau={n_t2} {show(r)}")
sids=[x for x in sql(f"select e.student_id from enrollments e join classes c on c.id=e.class_id where e.year_id=1 and c.block_id={bA} limit 2").split()]
res={sids[0]:"len"} if sids else {}
r=P(adm,"/api/promotion.php?action=run",{"block":blockname,"targetYearId":int(ty),"results":res}); print("  run có 1 em lên:",show(r))
newcls=sql(f"select class_id from enrollments where year_id={ty} and student_id={sids[0]}")
rec("PROMO-06","Em được đánh 'lên' chuyển sang lớp kế tiếp ở năm đích (kể cả khi đã ghi danh 'ở lại' ở lần chạy trước)", ok(r) and newcls!=sql(f"select class_id from enrollments where year_id=1 and student_id={sids[0]}"), f"lớp cũ={sql(f'select class_id from enrollments where year_id=1 and student_id={sids[0]}')} lớp đích={newcls}")
P(adm,"/api/years.php?action=lock",{"id":int(ty)}); r=P(adm,"/api/promotion.php?action=run",{"block":blockname,"targetYearId":int(ty),"results":{}}); rec("PROMO-07","Niên khoá đích đã khoá bị từ chối", not ok(r), show(r))
sql(f"delete from enrollments where year_id={ty}"); sql(f"delete from school_years where id={ty}")
sql("update enrollments set year_result=NULL where year_result is not null and year_id=1")

p=sum(1 for x in results if x[2]); print("TOTAL",len(results),"PASS",p,"FAIL",len(results)-p)
json.dump(results,open(os.path.join(HERE,"out","extra_results.json"),"w"),ensure_ascii=False,indent=1)
