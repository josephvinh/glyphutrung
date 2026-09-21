// Workflow: Điều phối công việc cho nhiều subagent
// Mục tiêu: Phân tích → Phát triển → Kiểm thử → Tối ưu

const workflow = async () => {
  phase("GIAI ĐOẠN 1: PHÂN TÍCH CODEBASE");
  
  // Bước 1: Phân tích cấu trúc dự án
  const analysis = await agent(
    "Phân tích toàn bộ cấu trúc project này. Liệt kê: 1) Các module chính, 2) Các API endpoints, 3) Cấu trúc database, 4) Các file quan trọng cần chú ý. Trả lời bằng tiếng Việt.",
    { 
      label: "Analysis",
      provider: "claude",
      model: "claude-sonnet-5"
    }
  );
  
  log(`✅ Hoàn thành phân tích: ${analysis.substring(0, 200)}...`);

  phase("GIAI ĐOẠN 2: PHÁT TRIỂN TÍNH NĂNG");
  
  // Bước 2: Lập trình (song song với các task khác nếu cần)
  const coding = await agent(
    "Dựa trên phân tích trước đó, hãy đề xuất 3 tính năng cần ưu tiên phát triển cho dự án PHP này. Giải thích lý do.",
    { 
      label: "Coding",
      provider: "claude",
      model: "claude-sonnet-5"
    }
  );
  
  log(`✅ Hoàn thành đề xuất tính năng: ${coding.substring(0, 200)}...`);

  phase("GIAI ĐOẠN 3: KIỂM THỬ");
  
  // Bước 3: Kiểm thử tự động
  const testing = await agent(
    "Liệt kê các test cases cần thiết cho một hệ thống điểm danh (attendance) PHP. Bao gồm: 1) Test cases cho API điểm danh, 2) Test cases cho validation, 3) Test cases cho permission.",
    { 
      label: "Testing",
      provider: "claude",
      model: "claude-haiku-4-5"
    }
  );
  
  log(`✅ Hoàn thành lập kế hoạch test: ${testing.substring(0, 200)}...`);

  phase("GIAI ĐOẠN 4: RÀ SOÁT BẢO MẬT");
  
  // Bước 4: Kiểm thử bảo mật
  const security = await agent(
    "Đưa ra 5 điểm bảo mật quan trọng cần kiểm tra trong một ứng dụng PHP có login/authentication. Giải thích ngắn gọn mỗi điểm.",
    { 
      label: "Security",
      provider: "claude",
      model: "claude-sonnet-5"
    }
  );
  
  log(`✅ Hoàn thành rà soát bảo mật: ${security.substring(0, 200)}...`);

  // Tổng hợp kết quả
  phase("TỔNG HỢP KẾT QUẢ");
  
  return {
    analysis,
    coding,
    testing,
    security,
    summary: "Đã hoàn thành 4 giai đoạn: Phân tích → Phát triển → Kiểm thử → Bảo mật"
  };
};

// ========== CÁCH SỬ DỤNG ==========
// 1. Chạy workflow: gọi hàm workflow() trong DSH
// 2. Hoặc chạy từng subagent riêng lẻ:
//    - await agent("prompt", {label: "Tên"})
// 3. Điều chỉnh model tùy theo độ phức tạp:
//    - claude-opus-4-8: Phân tích sâu, bảo mật
//    - claude-sonnet-5: Công việc thông thường  
//    - claude-haiku-4-5: Task đơn giản, nhanh
