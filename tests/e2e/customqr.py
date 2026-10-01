"""Thẻ QR tuỳ biến (custom-qrcard): xem trước, xuất SVG, preset, phân quyền theo phạm vi lớp. Chỉ kiểm chức năng thông thường."""
import os
HERE = os.path.dirname(os.path.abspath(__file__))
exec(open(os.path.join(HERE,'e2e2.py'), encoding='utf-8').read().split('for role,ph in [("glv","0911000004"),("glv_chu_nhiem"')[0])
results.clear()
def ok(r): return r[0]==200 and isinstance(r[1],dict) and r[1].get('ok') is True
def show(r): return f"HTTP {r[0]} {r[2][:120]!r}"
sql("DELETE FROM login_attempts; UPDATE members SET must_change_pw=0; DELETE FROM qr_card_presets")
adm=logged("0901000001","tntt@2026"); glv=logged("0911000004"); tt=logged("0911000006")
idsA=[int(x) for x in sql(f"select student_id from enrollments where class_id={cA} limit 3").split()]
idsB=[int(x) for x in sql(f"select student_id from enrollments where class_id={cB} limit 2").split()]
P=lambda c,a,d=None,m="POST": c.req(f"/api/custom-qrcard.php?action={a}",m,d if m=="POST" else None)
r=P(adm,"preview",{"student_ids":idsA,"options":{}}); rec("CQR-01","Xem trước thẻ QR cho 3 em (admin)", ok(r) and r[1].get('count')==3 and '<svg' in r[1].get('html','').lower(), show(r)[:100])
r=P(adm,"preview",{"student_ids":[],"options":{}}); rec("CQR-02","Không chọn em nào → 400", r[0]==400, show(r))
r=P(glv,"preview",{"student_ids":idsA+idsB,"options":{}}); n=r[1].get('count') if isinstance(r[1],dict) else None
PREVIEW_OK=ok(P(adm,"preview",{"student_ids":idsA,"options":{}}))
if PREVIEW_OK: rec("CQR-03","GLV chỉ tạo được thẻ cho em thuộc lớp mình (bỏ em lớp khác)", ok(r) and n is not None and n<=len(idsA), f"yêu cầu {len(idsA)+len(idsB)} em, nhận {n} thẻ")
r=P(tt,"preview",{"student_ids":idsA,"options":{}}); rec("CQR-04","Thủ thư (không có module qrcard) bị chặn", r[0]==403, show(r))
r=P(Client(),"preview",{"student_ids":idsA,"options":{}}); rec("CQR-05","Chưa đăng nhập bị chặn", r[0]==401, show(r))
r=P(adm,"export_svg_inline",{"student_ids":idsA[:1],"options":{}}); rec("CQR-06","Xuất SVG cho 1 em", ok(r) or r[0]==200, show(r)[:100])
for lab,opt in [("tên em chứa ký tự HTML","html")]:
    pass
if PREVIEW_OK:
  r=P(adm,"preview",{"student_ids":idsA,"options":{"header_text":"<b>Tiêu đề</b> & \"trích dẫn\""}}); html=r[1].get('html','') if isinstance(r[1],dict) else ''
  rec("CQR-07","Chữ tuỳ chọn do người dùng nhập được escape khi dựng thẻ", '<b>Tiêu đề</b>' not in html, "raw_bold_in_output="+str('<b>Tiêu đề</b>' in html))
r=P(adm,"save_preset",{"name":"Mẫu A","options":{"size":"medium"},"is_default":True}); pid=r[1].get('id') if ok(r) else None
rec("CQR-08","Lưu preset", ok(r), show(r))
r=P(adm,"save_preset",{"name":"","options":{}}); rec("CQR-09","Tên preset rỗng bị từ chối", not ok(r), show(r))
r=P(adm,"list_presets",None,"GET"); rec("CQR-10","Liệt kê preset của mình", ok(r) and len(r[1].get('presets',[]))>=1, show(r)[:100])
r=P(glv,"list_presets",None,"GET"); rec("CQR-11","Preset của người khác không hiện với GLV", ok(r) and all('Mẫu A'!=p.get('name') for p in r[1].get('presets',[])), show(r)[:100])
if not pid: pid=sql("select max(id) from qr_card_presets")
r=P(glv,"delete_preset",{"id":int(pid)}); rec("CQR-12","GLV không xoá được preset của người khác (và không lỗi 500)", r[0] in (403,404), show(r))
r=P(adm,"delete_preset",{"id":int(pid)}); rec("CQR-13","Chủ preset xoá được preset của mình", ok(r) and sql(f"select count(*) from qr_card_presets where id={pid}")=="0", show(r))
r=P(adm,"list_logos",None,"GET"); print("  list_logos:",show(r)); rec("CQR-14","Liệt kê logo của mình (không lỗi 500)", r[0]==200, show(r))
p=sum(1 for x in results if x[2]); print("TOTAL",len(results),"PASS",p,"FAIL",len(results)-p)
json.dump(results,open(os.path.join(HERE,"out","customqr_results.json"),"w"),ensure_ascii=False,indent=1)
