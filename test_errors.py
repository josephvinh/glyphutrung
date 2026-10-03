import sys
import os

def test_errors():
    print("Bắt đầu kiểm tra các lỗi phổ biến trong Python...\n")
    print("-" * 50)
    
    # 1. ZeroDivisionError
    try:
        print("1. Kiểm tra ZeroDivisionError (Lỗi chia cho 0)...")
        result = 1 / 0
    except ZeroDivisionError as e:
        print(f"   -> Đã bắt được lỗi: {type(e).__name__} - {e}\n")

    # 2. TypeError
    try:
        print("2. Kiểm tra TypeError (Lỗi kiểu dữ liệu)...")
        result = "hello" + 5
    except TypeError as e:
        print(f"   -> Đã bắt được lỗi: {type(e).__name__} - {e}\n")

    # 3. ValueError
    try:
        print("3. Kiểm tra ValueError (Lỗi giá trị không hợp lệ)...")
        result = int("abc")
    except ValueError as e:
        print(f"   -> Đã bắt được lỗi: {type(e).__name__} - {e}\n")

    # 4. IndexError
    try:
        print("4. Kiểm tra IndexError (Lỗi chỉ mục ngoài khoảng của List)...")
        lst = [1, 2, 3]
        result = lst[5]
    except IndexError as e:
        print(f"   -> Đã bắt được lỗi: {type(e).__name__} - {e}\n")

    # 5. KeyError
    try:
        print("5. Kiểm tra KeyError (Lỗi khóa không tồn tại trong Dictionary)...")
        d = {"a": 1}
        result = d["b"]
    except KeyError as e:
        print(f"   -> Đã bắt được lỗi: {type(e).__name__} - {e}\n")

    # 6. FileNotFoundError
    try:
        print("6. Kiểm tra FileNotFoundError (Lỗi không tìm thấy file)...")
        with open("file_khong_ton_tai.txt", "r") as f:
            pass
    except FileNotFoundError as e:
        print(f"   -> Đã bắt được lỗi: {type(e).__name__} - {e}\n")

    # 7. AttributeError
    try:
        print("7. Kiểm tra AttributeError (Lỗi thuộc tính/phương thức không tồn tại)...")
        num = 10
        num.append(5)
    except AttributeError as e:
        print(f"   -> Đã bắt được lỗi: {type(e).__name__} - {e}\n")

    # 8. NameError
    try:
        print("8. Kiểm tra NameError (Lỗi sử dụng biến chưa được định nghĩa)...")
        print(bien_chua_khai_bao)
    except NameError as e:
        print(f"   -> Đã bắt được lỗi: {type(e).__name__} - {e}\n")

    # 9. ModuleNotFoundError
    try:
        print("9. Kiểm tra ModuleNotFoundError (Lỗi không tìm thấy thư viện/module)...")
        import module_khong_ton_tai_xyz
    except ModuleNotFoundError as e:
        print(f"   -> Đã bắt được lỗi: {type(e).__name__} - {e}\n")

    # 10. SyntaxError
    try:
        print("10. Kiểm tra SyntaxError (Lỗi cú pháp)...")
        # Phải dùng eval để bắt lỗi cú pháp động
        eval("x === 1")
    except SyntaxError as e:
        print(f"   -> Đã bắt được lỗi: {type(e).__name__} - {e}\n")

    # 11. AssertionError
    try:
        print("11. Kiểm tra AssertionError (Lỗi kiểm tra điều kiện assert)...")
        assert 1 == 2, "Một không thể bằng hai"
    except AssertionError as e:
        print(f"   -> Đã bắt được lỗi: {type(e).__name__} - {e}\n")

    print("-" * 50)
    print("Hoàn tất kiểm tra! Tất cả các lỗi đã được bắt và xử lý an toàn.")

if __name__ == "__main__":
    test_errors()
