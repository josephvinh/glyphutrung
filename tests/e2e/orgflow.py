"""Luồng xoá khối/lớp (phát hiện lỗi students.class_id).
Chạy trên DB THỬ NGHIỆM (không phải DB thật). Xem BAO_CAO_KIEM_THU.md mục 9 để biết cách dựng môi trường.
Yêu cầu: php -S 127.0.0.1:8088 -t public (đã trỏ TNTT_DB_* vào DB thử), mysql client chạy được bằng root không mật khẩu, DB tên trong biến DB bên dưới.
"""
import os
HERE = os.path.dirname(os.path.abspath(__file__))
exec(open(os.path.join(HERE,'e2e2.py'), encoding='utf-8').read().split('for role,ph in [("glv","0911000004"),("glv_chu_nhiem"')[0])
results.clear()
adm=logged("0901000001","tntt@2026")
def show(r): return f"HTTP {r[0]} {r[2][:140]!r}"
def ok(r): return r[0]==200 and isinstance(r[1],dict) and r[1].get('ok') is True
sql("delete from classes where name in ('Lớp Thử','Lớp Thử 2')"); sql("delete from blocks where name='Khối Thử'")
r=adm.req("/api/org.php?action=saveBlock","POST",{"name":"Khối Thử"}); print("saveBlock",show(r))
r=adm.req("/api/org.php?action=saveClass","POST",{"name":"Lớp Thử","block":"Khối Thử"}); print("saveClass",show(r))
r=adm.req("/api/org.php?action=deleteBlock","POST",{"name":"Khối Thử"}); rec("ORG-01","Không xoá được khối còn lớp con", not ok(r), show(r))
# class with student
cid=sql("select id from classes where name='Lớp Thử'")
sid=sql("select id from students limit 1 offset 5")
old=sql(f"select class_id from enrollments where student_id={sid}")
sql(f"update enrollments set class_id={cid} where student_id={sid}")
r=adm.req("/api/org.php?action=deleteClass","POST",{"name":"Lớp Thử"}); rec("ORG-02","Không xoá được lớp còn thiếu nhi ghi danh (từ chối có kiểm soát 4xx)", r[0] in (400,403,409) and sql("select count(*) from classes where name='Lớp Thử'")=='1', show(r))
sql(f"update enrollments set class_id={old} where student_id={sid}")
r=adm.req("/api/org.php?action=deleteClass","POST",{"name":"Lớp Thử"}); rec("ORG-03","Xoá lớp rỗng", ok(r) and sql("select count(*) from classes where name='Lớp Thử'")=='0', show(r))
r=adm.req("/api/org.php?action=deleteBlock","POST",{"name":"Khối Thử"}); rec("ORG-04","Xoá khối rỗng", ok(r), show(r))
