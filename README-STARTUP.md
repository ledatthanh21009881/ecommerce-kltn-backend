# Hướng Dẫn Khởi Động Hệ Thống E-commerce

## Cách 1: Khởi động nhanh (Khuyến nghị)
Double-click vào file `start-all.bat` để khởi động cả backend và frontend cùng lúc.

## Cách 2: Khởi động riêng lẻ

### Backend (PHP)
```bash
cd ecommerce
php -S localhost:8000 -t public
```

### Frontend (Next.js)
```bash
cd web
npm run dev
```

## Các URL quan trọng:
- **Backend API**: http://localhost:8000
- **Frontend**: http://localhost:3000
- **Admin Panel**: http://localhost:3000/admin

## Lưu ý:
1. Đảm bảo PHP và Node.js đã được cài đặt
2. Backend phải chạy trước frontend
3. Nếu gặp lỗi CORS, hãy restart cả hai server
4. Để dừng server: nhấn Ctrl+C trong terminal

## Troubleshooting:
- **Lỗi CORS**: Restart backend server
- **Port đã được sử dụng**: Tắt process cũ hoặc đổi port
- **Module không tìm thấy**: Chạy `npm install` trong thư mục web
