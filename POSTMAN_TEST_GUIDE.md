# 🚀 HƯỚNG DẪN TEST API VỚI POSTMAN

## ✅ **Backend đã chạy thành công!**
- **URL**: http://127.0.0.1:8000
- **API Base**: http://127.0.0.1:8000/api/v1

## 📋 **Các API để test trong Postman:**

### 1. **Test API cơ bản (không cần auth)**
```
GET http://127.0.0.1:8000/api/v1/test
```

### 2. **Login Admin (để lấy token)**
```
POST http://127.0.0.1:8000/api/v1/auth/login
Content-Type: application/json

{
    "email": "admin@example.com",
    "password": "admin123"
}
```

### 3. **Lấy danh sách đơn hàng (cần token)**
```
GET http://127.0.0.1:8000/api/v1/orders
Authorization: Bearer YOUR_TOKEN_HERE
```

### 4. **Lấy chi tiết đơn hàng**
```
GET http://127.0.0.1:8000/api/v1/orders/1
Authorization: Bearer YOUR_TOKEN_HERE
```

### 5. **Test Invoice Generation**
```
GET http://127.0.0.1:8000/api/v1/invoice/generate?order_id=1
Authorization: Bearer YOUR_TOKEN_HERE
```

### 6. **Test Email Invoice**
```
POST http://127.0.0.1:8000/api/v1/invoice/generate
Content-Type: application/json
Authorization: Bearer YOUR_TOKEN_HERE

{
    "order_id": 1,
    "email": "test@example.com"
}
```

## 🔧 **Cách test trong Postman:**

### **Bước 1: Test API cơ bản**
1. Mở Postman
2. Tạo request GET: `http://127.0.0.1:8000/api/v1/test`
3. Send → Kết quả: `{"success":true,"message":"API router is working!"}`

### **Bước 2: Login để lấy token**
1. Tạo request POST: `http://127.0.0.1:8000/api/v1/auth/login`
2. Headers: `Content-Type: application/json`
3. Body (raw JSON):
```json
{
    "email": "admin@example.com",
    "password": "admin123"
}
```
4. Send → Copy token từ response

### **Bước 3: Test API cần authentication**
1. Tạo request GET: `http://127.0.0.1:8000/api/v1/orders`
2. Headers: `Authorization: Bearer YOUR_TOKEN_HERE`
3. Send → Xem danh sách đơn hàng

## 🎯 **Các API quan trọng khác:**

### **Quản lý đơn hàng:**
- `GET /api/v1/orders` - Lấy danh sách đơn hàng
- `GET /api/v1/orders/{id}` - Lấy chi tiết đơn hàng
- `PUT /api/v1/orders/{id}/status` - Cập nhật trạng thái
- `POST /api/v1/orders/{id}/assign-shipper` - Gán shipper

### **Invoice & Email:**
- `GET /api/v1/invoice/generate?order_id={id}` - Tạo PDF invoice
- `POST /api/v1/invoice/generate` - Gửi invoice qua email

### **Authentication:**
- `POST /api/v1/auth/login` - Đăng nhập
- `POST /api/v1/auth/refresh` - Refresh token
- `POST /api/v1/auth/forgot-password` - Quên mật khẩu

## ⚠️ **Lưu ý:**
- Backend chạy trên port 8000
- Cần token cho hầu hết API (trừ login và test)
- Token có thời hạn, cần refresh khi hết hạn
- Đảm bảo MySQL database đang chạy

## 🚀 **Khởi động nhanh:**
```bash
# Chạy file batch để khởi động backend
start-backend-absolute.bat

# Hoặc chạy cả frontend và backend
start-all-absolute.bat
```
