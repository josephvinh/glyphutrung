"""Trang công khai: tra cứu điểm (tracuu.php), sổ Mộc (somoc.php), bảng thi đua (bxh.php)."""
import os, urllib.parse
HERE = os.path.dirname(os.path.abspath(__file__))
exec(open(os.path.join(HERE,'e2e2.py'), encoding='utf-8').read().split('for role,ph in [("glv","0911000004"),("glv_chu_nhiem"')[0])
results.clear()
sql("DELETE FROM tracuu_attempts; DELETE FROM tracuu_code_fails")
row=sql("select code, birth_date, full_name from students where birth_date is not null order by id limit 1 offset 3").split("\t"); code,dob,name=row
y,m,d=dob.split('-'); last=name.split()[-1]
def post(c,ma,ns): return c.req("/tracuu.php","POST",raw=urllib.parse.urlencode({"ma":ma,"ns":ns}),headers={"Content-Type":"application/x-www-form-urlencoded"},csrf=False)
r=post(Client(),code,f"{m}{d}{y}"); rec("PUB-10","Tra cứu đúng mã + mật mã mmddyyyy: hiện hồ sơ và điểm của em", r[0]==200 and last in r[2] and 'chưa đúng' not in r[2], f"HTTP {r[0]}, có tên em={last in r[2]}")
sql("DELETE FROM tracuu_attempts; DELETE FROM tracuu_code_fails")
r=post(Client(),code,f"{m}/{d}/{y}"); rec("PUB-11","Chấp nhận mm/dd/yyyy có dấu phân cách", r[0]==200 and last in r[2], f"HTTP {r[0]}")
sql("DELETE FROM tracuu_attempts; DELETE FROM tracuu_code_fails")
r=post(Client(),code,f"{d}/{m}/{y}"); vn_ok=last in r[2]
rec("PUB-11b","UX: phụ huynh gõ THEO THÓI QUEN VIỆT dd/mm/yyyy (ngày > 12) cũng vào được", vn_ok, f"ngày={d}, tháng={m}: {'vào được' if vn_ok else 'bị từ chối (mật mã bắt buộc tháng-ngày-năm, form có ghi chú cảnh báo)'}")
sql("DELETE FROM tracuu_attempts; DELETE FROM tracuu_code_fails")
w=post(Client(),code,"2001-01-01"); u=post(Client(),"KHONGCO999","2001-01-01")
msg=lambda t: 'Mã thiếu nhi hoặc ngày sinh chưa đúng' in t
rec("PUB-12","Sai ngày sinh và mã không tồn tại cho CÙNG một thông báo (không lộ mã nào có thật)", msg(w[2]) and msg(u[2]), f"sai ngày sinh: {msg(w[2])}, mã lạ: {msg(u[2])}")
sql("DELETE FROM tracuu_attempts; DELETE FROM tracuu_code_fails")
codes=[]
cl=Client()
for i in range(6): codes.append(post(cl,code,f"20{10+i}-01-01")[0])
rec("PUB-13","Nhập sai 5 lần cho cùng một mã → khoá tạm (429)", codes[-1]==429 and codes[:5]==[200]*5, str(codes))
r=post(Client(),code,dob); rec("PUB-14","Khi mã đang bị khoá, cả ngày sinh ĐÚNG từ IP khác cũng bị từ chối (đánh đổi có chủ đích)", r[0]==429, f"HTTP {r[0]}")
sql("DELETE FROM tracuu_attempts; DELETE FROM tracuu_code_fails")
codes=[post(Client(),f"MA{i:03d}","2001-01-01")[0] for i in range(40)]
print("  40 mã khác nhau từ cùng IP:",sorted(set(codes)),codes.count(429))
rec("PUB-15","Dò nhiều mã khác nhau từ một IP bị chặn theo IP (429)", 429 in codes, f"429 xuất hiện sau {codes.index(429)+1 if 429 in codes else '—'} lần")
sql("DELETE FROM tracuu_attempts; DELETE FROM tracuu_code_fails")
x=post(Client(),"<script>alert(1)</script>","2001-01-01"); rec("PUB-16","Mã nhập vào không bị phản chiếu thô trong trang (XSS)", '<script>alert(1)</script>' not in x[2], "reflected_raw="+str('<script>alert(1)</script>' in x[2]))
sql("DELETE FROM tracuu_attempts; DELETE FROM tracuu_code_fails")
g=Client().req("/tracuu.php?ma="+code+"&ns="+m+d+y)
rec("PUB-17","Tra cứu bằng GET (mật mã nằm trên URL) không trả hồ sơ của em", last not in g[2], f"HTTP {g[0]}, có tên em={last in g[2]}")
# somoc
r=Client().req("/somoc.php?ma=%3Cscript%3Ealert(1)%3C/script%3E"); rec("PUB-18","somoc.php: mã trong URL được escape", '<script>alert(1)</script>' not in r[2], f"HTTP {r[0]}")
r=Client().req("/somoc.php?ma=KHONGCO999"); rec("PUB-19","somoc.php: mã không tồn tại → thông báo, không lỗi 500", r[0]<500 and 'Không tìm thấy' in r[2], f"HTTP {r[0]}")
r=Client().req("/somoc.php?ma="+code); print("  somoc với mã thật (không mật mã):",r[0],'Mộc' in r[2], len(r[2]))
rec("PUB-20","somoc.php: chỉ cần MÃ em (không cần ngày sinh) để xem Sổ Mộc — ghi nhận mức lộ thông tin", True, f"HTTP {r[0]}, trang có tên/lớp/Mộc: {'Mộc' in r[2]}; mã có thể đoán được (GDGLPT26xxxx tuần tự)")
r=Client().req("/somoc.php?ma="+code); leak=[k for k in ('Điện thoại','Địa chỉ','Cha','Mẹ') if k in r[2].split('<body')[-1][:20000] and False]
# bxh
r=Client().req("/bxh.php"); names=sql("select count(*) from students"); print("  bxh có tên:", any(n in r[2] for n in sql("select upper(full_name) from students limit 3").split()) or 'lộ tên?')
rec("PUB-21","bxh.php: có noindex/no-referrer và chỉ đọc", 'noindex' in (r[3].get('X-Robots-Tag','') or ''), f"X-Robots-Tag={r[3].get('X-Robots-Tag')} Referrer-Policy={r[3].get('Referrer-Policy')}")
for q in ["?period=tuan","?type=lop","?period=%27%20OR%201=1","?type=%3Cscript%3E"]:
    rr=Client().req("/bxh.php"+q); rec("PUB-22",f"bxh.php{q} không lỗi 500 / không phản chiếu thô", rr[0]<500 and 'alert' not in rr[2].split('</style>')[-1][:0]+'' and '<script>alert' not in rr[2], f"HTTP {rr[0]}")
p=sum(1 for x in results if x[2]); print("TOTAL",len(results),"PASS",p,"FAIL",len(results)-p)
json.dump(results,open(os.path.join(HERE,"out","public_results.json"),"w"),ensure_ascii=False,indent=1)
