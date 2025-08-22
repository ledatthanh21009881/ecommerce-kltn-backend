# Hướng Dẫn Xử Lý Lỗi E-commerce System

## 🚀 Khởi động nhanh
Double-click `start-system.bat` để khởi động toàn bộ hệ thống.

## 🔧 Các lỗi thường gặp và cách xử lý

### 1. **Lỗi CORS (Cross-Origin Resource Sharing)**
**Triệu chứng:** 
- Browser console hiển thị: "Access to fetch at 'http://localhost:8000' from origin 'http://localhost:3000' has been blocked by CORS policy"
- API calls bị lỗi 405 Method Not Allowed

**Nguyên nhân:**
- Frontend (port 3000) gọi API đến Backend (port 8000) bị browser chặn
- Backend chưa được cấu hình CORS đúng cách

**Cách xử lý:**
1. **Restart Backend server:**
   ```bash
   # Trong terminal Backend
   Ctrl+C để dừng
   php -S localhost:8000 -t public
   ```

2. **Kiểm tra CORS headers trong `ecommerce/public/index.php`:**
   ```php
   header('Access-Control-Allow-Origin: *');
   header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, Accept, Origin');
   header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
   ```

3. **Sử dụng Next.js API Routes thay vì gọi trực tiếp:**
   - ✅ `/api/orders/1` (Next.js route)
   - ❌ `http://localhost:8000/api/orders/1` (direct call)

### 2. **Lỗi Port đã được sử dụng**
**Triệu chứng:**
- "Port 3000 is already in use"
- "Port 8000 is already in use"

**Cách xử lý:**
1. **Tắt tất cả process cũ:**
   ```bash
   # Windows
   taskkill /f /im php.exe
   taskkill /f /im node.exe
   
   # Hoặc tắt theo port
   netstat -ano | findstr :3000
   taskkill /f /pid [PID]
   ```

2. **Sử dụng script `start-system.bat`** - nó sẽ tự động tắt process cũ

### 3. **Lỗi Database Connection**
**Triệu chứng:**
- "Unknown database 'shopswift'"
- "Connection refused"

**Cách xử lý:**
1. **Kiểm tra MySQL service:**
   ```bash
   # Windows
   services.msc
   # Tìm "MySQL" và Start nếu chưa chạy
   ```

2. **Kiểm tra database name trong `ecommerce/app/config/database.php`:**
   ```php
   'database' => $_ENV['DB_DATABASE'] ?? 'shopswiftv2',
   ```

3. **Kiểm tra file .env:**
   ```env
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=shopswiftv2
   DB_USERNAME=root
   DB_PASSWORD=your_password
   ```

### 4. **Lỗi Module không tìm thấy**
**Triệu chứng:**
- "Cannot find module 'xxx'"
- "Module not found"

**Cách xử lý:**
1. **Cài đặt dependencies:**
   ```bash
   cd web
   npm install
   ```

2. **Kiểm tra package.json có đầy đủ dependencies không**

### 5. **Lỗi Authentication**
**Triệu chứng:**
- "Authorization token required"
- "401 Unauthorized"

**Cách xử lý:**
1. **Đăng nhập lại admin:**
   - Truy cập http://localhost:3000/admin/login
   - Đăng nhập với tài khoản admin

2. **Kiểm tra token trong localStorage:**
   ```javascript
   // Browser console
   localStorage.getItem('adminToken')
   ```

## 📋 Checklist khi khởi động

### Trước khi chạy:
- [ ] PHP đã được cài đặt và thêm vào PATH
- [ ] Node.js đã được cài đặt và thêm vào PATH
- [ ] MySQL service đang chạy
- [ ] Database `shopswiftv2` đã được tạo
- [ ] File `.env` đã được cấu hình đúng

### Khi chạy:
- [ ] Backend chạy trên port 8000
- [ ] Frontend chạy trên port 3000
- [ ] Không có lỗi CORS trong browser console
- [ ] API calls thành công
- [ ] Admin panel có thể truy cập

## 🛠️ Các script hữu ích

### `start-system.bat`
- Khởi động toàn bộ hệ thống
- Tự động tắt process cũ
- Kiểm tra dependencies

### `start-backend.bat`
- Chỉ khởi động backend

### `start-frontend.bat`
- Chỉ khởi động frontend

## 📞 Liên hệ hỗ trợ
Nếu vẫn gặp lỗi, hãy:
1. Kiểm tra console browser (F12)
2. Kiểm tra terminal logs
3. Chụp màn hình lỗi
4. Mô tả chi tiết các bước đã thực hiện
