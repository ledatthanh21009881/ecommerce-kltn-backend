# HƯỚNG DẪN IMPORT DATABASE VÀO XAMPP

## Bước 1: Import file gốc (Nếu chưa có database)

1. Mở phpMyAdmin: http://localhost/phpmyadmin
2. Tạo database mới: `shopswiftv2` (hoặc chọn database có sẵn)
3. Chọn database `shopswiftv2`
4. Chọn tab **"Import"**
5. Chọn file: `shopswiftv2_new.sql`
6. Click **"Go"**

## Bước 2: Tối ưu database (Xóa bảng không cần + Thêm Collections)

1. Vẫn ở database `shopswiftv2`
2. Chọn tab **"SQL"**
3. Copy và paste nội dung từ file `shopswiftv2_final.sql`
4. Click **"Go"**

Hoặc chạy lệnh MySQL từ command line:

```bash
mysql -u root -p shopswiftv2 < shopswiftv2_final.sql
```

## Kết quả:

✅ Database sẽ có **25 bảng** (tối ưu từ 30 bảng)
✅ Đã xóa: Review System (4), Voucher phụ (2), Payment Confirmations (1), Analytics (2)
✅ Đã thêm: Collections (2 bảng mới)
✅ Giữ nguyên toàn bộ dữ liệu các bảng khác

## Checklist các bảng cuối cùng:

### Core (16 bảng):
- accounts, users, user_roles, roles, refresh_tokens
- customers, addresses, notifications
- products, product_variants, product_images, categories, sizes
- orders, order_items, order_status_logs
- carts, cart_items
- payments, shipping_methods
- vouchers, voucher_usages

### Messenger (4 bảng):
- conversations, messages, message_media, media

### Shipper/Delivery (4 bảng):
- shippers, shipper_performance, shipping_logs, shipping_tracking

### Inventory (3 bảng):
- suppliers, purchase_receipts, purchase_items

### System (2 bảng):
- activity_logs, content_blocks

### Collections (2 bảng mới):
- collections, collection_products

**Tổng: 25 bảng**

