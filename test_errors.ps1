Write-Host "Bắt đầu kiểm tra các lỗi phổ biến trong PowerShell..." -ForegroundColor Cyan
Write-Host ("-" * 50)

# 1. Lỗi chia cho 0 (DivideByZeroException)
try {
    Write-Host "1. Kiểm tra Lỗi chia cho 0 (DivideByZeroException)..."
    $result = 1 / 0
} catch {
    Write-Host "   -> Đã bắt được lỗi: $($_.Exception.GetType().Name) - $($_.Exception.Message)" -ForegroundColor Yellow
}

# 2. Lỗi gọi phương thức trên giá trị null (RuntimeException)
try {
    Write-Host "2. Kiểm tra lỗi NullReference..."
    $bien_null = $null
    $bien_null.ToString()
} catch {
    Write-Host "   -> Đã bắt được lỗi: $($_.Exception.GetType().Name) - $($_.Exception.Message)" -ForegroundColor Yellow
}

# 3. Lỗi không tìm thấy Command (CommandNotFoundException)
try {
    Write-Host "3. Kiểm tra CommandNotFoundException..."
    lenh_khong_ton_tai -ErrorAction Stop
} catch {
    Write-Host "   -> Đã bắt được lỗi: $($_.Exception.GetType().Name) - $($_.Exception.Message)" -ForegroundColor Yellow
}

# 4. Lỗi không tìm thấy file (ItemNotFoundException)
try {
    Write-Host "4. Kiểm tra ItemNotFoundException (Lỗi không tìm thấy file)..."
    Get-Item "C:\duong_dan_khong_ton_tai_xyz123.txt" -ErrorAction Stop
} catch {
    Write-Host "   -> Đã bắt được lỗi: $($_.Exception.GetType().Name) - $($_.Exception.Message)" -ForegroundColor Yellow
}

# 5. Lỗi chuyển đổi kiểu dữ liệu (InvalidCastException)
try {
    Write-Host "5. Kiểm tra sai định dạng kiểu dữ liệu..."
    [int]$number = "chuoi_chu_cai"
} catch {
    Write-Host "   -> Đã bắt được lỗi: $($_.Exception.GetType().Name) - $($_.Exception.Message)" -ForegroundColor Yellow
}

Write-Host ("-" * 50)
Write-Host "Hoàn tất kiểm tra! Tất cả các lỗi đã được bắt và xử lý an toàn." -ForegroundColor Green
