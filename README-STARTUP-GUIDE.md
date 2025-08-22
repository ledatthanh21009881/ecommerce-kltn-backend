# 🚀 Hướng Dẫn Sử Dụng E-commerce System

## 📋 Mục Lục
- [Khởi động dự án](#khởi-động-dự-án)
- [Tắt dự án](#tắt-dự-án)
- [Trước khi tắt máy](#trước-khi-tắt-máy)
- [Troubleshooting](#troubleshooting)
- [Cấu trúc dự án](#cấu-trúc-dự-án)

---

## 🎯 Khởi động dự án

### **Cách 1: Khởi động nhanh (Khuyến nghị)**
```bash
# Double-click file này
start-system.bat
```

### **Cách 2: Khởi động thủ công**
```bash
# Terminal 1 - Backend (PHP)
cd ecommerce
php -S localhost:8000 -t public

# Terminal 2 - Frontend (Next.js)
cd web
npm run dev
```

### **Cách 3: Sử dụng script riêng lẻ**
```bash
# Chỉ khởi động Backend
start-backend.bat

# Chỉ khởi động Frontend
web/start-frontend.bat
```

---

## 🛑 Tắt dự án

### **Cách 1: Tắt nhanh**
```bash
# Trong mỗi terminal, nhấn:
Ctrl + C
```

### **Cách 2: Tắt tất cả process**
```bash
# Windows Command Prompt
taskkill /f /im php.exe
taskkill /f /im node.exe
```

### **Cách 3: Tắt theo port**
```bash
# Tìm process đang sử dụng port
netstat -ano | findstr :3000
netstat -ano | findstr :8000

# Tắt process theo PID
taskkill /f /pid [PID]
```

---

## 💾 Trước khi tắt máy

### **1. Lưu dữ liệu quan trọng**
```bash
# Backup database (nếu cần)
mysqldump -u root -p shopswiftv2 > backup_$(date +%Y%m%d_%H%M%S).sql
```

### **2. Commit code changes**
```bash
# Kiểm tra thay đổi
git status

# Thêm files mới
git add .

# Commit với message mô tả
git commit -m "feat: update API routes and fix CORS issues"

# Push lên GitHub (nếu cần)
git push origin main
```

### **3. Tắt servers đúng cách**
```bash
# 1. Tắt Frontend (Ctrl+C trong terminal Next.js)
# 2. Tắt Backend (Ctrl+C trong terminal PHP)
# 3. Đóng tất cả terminals
```

### **4. Kiểm tra không còn process chạy**
```bash
# Kiểm tra PHP processes
tasklist | findstr php

# Kiểm tra Node processes  
tasklist | findstr node

# Nếu còn, tắt bằng:
taskkill /f /im php.exe
taskkill /f /im node.exe
```

---

## 🔧 Troubleshooting

### **Lỗi thường gặp khi khởi động**

#### **1. Port đã được sử dụng**
```bash
# Lỗi: "Port 3000 is already in use"
# Giải pháp:
taskkill /f /im node.exe
# Hoặc restart máy
```

#### **2. Database connection failed**
```bash
# Lỗi: "Connection refused"
# Giải pháp:
# 1. Kiểm tra MySQL service đang chạy
services.msc
# 2. Start MySQL nếu chưa chạy
```

#### **3. Module not found**
```bash
# Lỗi: "Cannot find module 'xxx'"
# Giải pháp:
cd web
npm install
```

#### **4. CORS Error**
```bash
# Lỗi: "Access to fetch has been blocked by CORS policy"
# Giải pháp:
# 1. Restart Backend server
# 2. Kiểm tra CORS headers trong ecommerce/public/index.php
```

### **Lỗi thường gặp khi tắt**

#### **1. Process không tắt được**
```bash
# Lỗi: "The process cannot be terminated"
# Giải pháp:
taskkill /f /im php.exe /t
taskkill /f /im node.exe /t
```

#### **2. Port vẫn bị chiếm**
```bash
# Kiểm tra:
netstat -ano | findstr :3000
netstat -ano | findstr :8000

# Tắt process theo PID:
taskkill /f /pid [PID]
```

---

## 📁 Cấu trúc dự án

```
ecommerce/
├── app/                    # Backend PHP code
│   ├── Controllers/       # API Controllers
│   ├── Domain/           # Business logic
│   ├── Middlewares/      # CORS, Auth middlewares
│   └── config/           # Database config
├── public/               # PHP entry point
├── routes/               # API routes
├── start-system.bat      # Khởi động toàn bộ
├── start-backend.bat     # Khởi động Backend
└── TROUBLESHOOTING.md    # Hướng dẫn xử lý lỗi

web/
├── app/                  # Next.js pages
│   ├── admin/           # Admin pages
│   └── api/             # Next.js API routes
├── components/          # React components
├── lib/                 # Utilities
├── start-frontend.bat   # Khởi động Frontend
└── package.json         # Dependencies
```

---

## 🌐 URLs quan trọng

| Service | URL | Mô tả |
|---------|-----|-------|
| **Frontend** | http://localhost:3000 | Giao diện chính |
| **Admin Panel** | http://localhost:3000/admin | Quản trị hệ thống |
| **Backend API** | http://localhost:8000 | API endpoints |
| **Database** | localhost:3306 | MySQL database |

---

## 📝 Checklist trước khi tắt máy

- [ ] **Lưu tất cả thay đổi code**
- [ ] **Commit và push code lên Git**
- [ ] **Tắt Frontend server (Ctrl+C)**
- [ ] **Tắt Backend server (Ctrl+C)**
- [ ] **Kiểm tra không còn process chạy**
- [ ] **Backup database (nếu cần)**
- [ ] **Đóng tất cả terminals**

---

## 🚨 Lưu ý quan trọng

### **Khi khởi động:**
1. **Luôn chạy Backend trước, Frontend sau**
2. **Đợi Backend khởi động xong (5-10 giây)**
3. **Kiểm tra không có lỗi trong console**

### **Khi tắt:**
1. **Tắt Frontend trước, Backend sau**
2. **Đợi process tắt hoàn toàn**
3. **Kiểm tra port đã được giải phóng**

### **Trước khi tắt máy:**
1. **Commit code để không mất thay đổi**
2. **Tắt servers đúng cách**
3. **Backup dữ liệu quan trọng**

## ✅ **Các API Routes đã được tạo:**

### **Orders API Routes:**
- `GET /api/orders/statistics` - Thống kê orders ✅
- `GET /api/orders/{id}` - Chi tiết order ✅
- `PUT /api/orders/{id}/status` - Cập nhật trạng thái ✅
- `POST /api/orders/{id}/assign-shipper` - Gán shipper ✅
- `POST /api/orders/{id}/send-invoice` - Gửi hóa đơn ✅
- `DELETE /api/orders/{id}` - Xóa order ✅

### **Shippers API Routes:**
- `GET /api/orders/available-shippers` - Danh sách shipper có sẵn ✅

### **Backend API Routes:**
- `GET/POST /api/backend/v1/products` - Quản lý sản phẩm ✅
- `POST /api/backend/v1/products/{id}/images` - Upload ảnh sản phẩm ✅
- `GET /api/backend/v1/categories` - Danh mục sản phẩm ✅
- `POST /api/backend/v1/auth/admin/login` - Đăng nhập admin ✅

## 🔧 **Các lỗi đã sửa:**

### **1. Backend khởi động:**
- ✅ Sửa script để sử dụng `public/router.php`
- ✅ Backend hoạt động tại `http://localhost:8000`

### **2. API Statistics:**
- ✅ Tạo Next.js API Route `/api/orders/statistics`
- ✅ Sửa component orders để sử dụng `ordersApi.getStatistics()`
- ✅ Sửa component dashboard để sử dụng `ordersApi.getStatistics()`
- ✅ API trả về dữ liệu đúng: `total_orders: 5, total_revenue: 3312000.00`

### **3. Token Authentication:**
- ✅ API cần token để hoạt động
- ✅ Next.js API Route forward token đúng cách
- ✅ `apiFetch` tự động thêm token từ `localStorage`

---

## 📞 Hỗ trợ

Nếu gặp lỗi không giải quyết được:
1. Kiểm tra `TROUBLESHOOTING.md`
2. Xem console logs
3. Chụp màn hình lỗi
4. Mô tả chi tiết các bước đã thực hiện

**Happy coding! 🎉**
