# API Quản Lý Đơn Hàng - Tổng Hợp

## 1. API Chính (Có Authentication)

### 1.1. Lấy Danh Sách Đơn Hàng
```
GET /api/v1/orders
```
**Query Parameters:**
- `page` (int): Trang hiện tại (mặc định: 1)
- `limit` (int): Số đơn hàng mỗi trang (mặc định: 20)
- `status` (string): Lọc theo trạng thái
- `customer_id` (int): Lọc theo khách hàng
- `search` (string): Tìm kiếm theo số hóa đơn, tên, email
- `date_from` (string): Từ ngày (YYYY-MM-DD)
- `date_to` (string): Đến ngày (YYYY-MM-DD)

**Response:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "order_id": 1,
        "invoice_number": "INV-2024-001",
        "total_amount": 1500000,
        "status": "pending",
        "created_at": "2024-01-15 10:30:00",
        "customer_name": "Nguyễn Văn A",
        "customer_email": "nguyenvana@email.com",
        "item_count": 3
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 50,
      "total_pages": 3
    }
  }
}
```

### 1.2. Thống Kê Đơn Hàng
```
GET /api/v1/orders/statistics
```
**Response:**
```json
{
  "success": true,
  "data": {
    "total_orders": 150,
    "pending_orders": 25,
    "processing_orders": 30,
    "shipping_orders": 20,
    "completed_orders": 65,
    "cancelled_orders": 8,
    "returned_orders": 2,
    "total_revenue": 45000000
  }
}
```

### 1.3. Chi Tiết Đơn Hàng
```
GET /api/v1/orders/{id}
```
**Response:**
```json
{
  "success": true,
  "data": {
    "order_id": 1,
    "invoice_number": "INV-2024-001",
    "customer": {
      "user_id": 1,
      "first_name": "Nguyễn",
      "last_name": "Văn A",
      "email": "nguyenvana@email.com",
      "phone": "0123456789"
    },
    "shipping_address": {
      "address_id": 1,
      "address_line1": "123 Đường ABC",
      "city": "TP.HCM",
      "postal_code": "70000"
    },
    "items": [
      {
        "item_id": 1,
        "product_name": "Áo thun nam",
        "variant_name": "M - Đỏ",
        "quantity": 2,
        "unit_price": 250000,
        "total_price": 500000
      }
    ],
    "total_amount": 1500000,
    "shipping_fee": 30000,
    "discount_amount": 0,
    "status": "pending",
    "created_at": "2024-01-15 10:30:00",
    "updated_at": "2024-01-15 10:30:00"
  }
}
```

### 1.4. Tạo Đơn Hàng Mới
```
POST /api/v1/orders
```
**Request Body:**
```json
{
  "customer_id": 1,
  "address_id": 1,
  "shipping_method_id": 1,
  "voucher_id": null,
  "note": "Ghi chú đơn hàng",
  "items": [
    {
      "variant_id": 1,
      "quantity": 2
    }
  ]
}
```

### 1.5. Cập Nhật Trạng Thái Đơn Hàng
```
PUT /api/v1/orders/{id}/status
```
**Request Body:**
```json
{
  "status": "processing",
  "note": "Đơn hàng đang được xử lý"
}
```

### 1.6. Gán Shipper
```
POST /api/v1/orders/{id}/assign-shipper
```
**Request Body:**
```json
{
  "shipper_id": 1
}
```

### 1.7. Danh Sách Shipper Có Sẵn
```
GET /api/v1/orders/available-shippers
```
**Response:**
```json
{
  "success": true,
  "data": [
    {
      "user_id": 1,
      "first_name": "Trần",
      "last_name": "Văn B",
      "phone": "0987654321",
      "rating": 4.5,
      "on_time_delivery_pct": 95,
      "total_delivered": 150,
      "is_available": true
    }
  ]
}
```

### 1.8. Xóa Đơn Hàng
```
DELETE /api/v1/orders/{id}
```

### 1.9. Xuất Đơn Hàng
```
GET /api/v1/orders/export
```
**Query Parameters:**
- `format` (string): "excel" hoặc "csv"
- `date_from` (string): Từ ngày
- `date_to` (string): Đến ngày
- `status` (string): Trạng thái

### 1.10. Tạo Hóa Đơn
```
GET /api/v1/orders/{id}/invoice
```

## 2. API Backend (Cho Frontend Admin)

### 2.1. Danh Sách Đơn Hàng (Backend)
```
GET /api/backend/v1/orders
```

### 2.2. Thống Kê (Backend)
```
GET /api/backend/v1/orders/statistics
```

### 2.3. Chi Tiết Đơn Hàng (Backend)
```
GET /api/backend/v1/orders/{id}
```

### 2.4. Cập Nhật Trạng Thái (Backend)
```
PUT /api/backend/v1/orders/{id}/status
POST /api/backend/v1/orders/{id}/status
```

### 2.5. Gán Shipper (Backend)
```
POST /api/backend/v1/orders/{id}/assign-shipper
```

### 2.6. Shipper Có Sẵn (Backend)
```
GET /api/backend/v1/orders/available-shippers
```

### 2.7. Xóa Đơn Hàng (Backend)
```
DELETE /api/backend/v1/orders/{id}
```

### 2.8. Xuất Đơn Hàng (Backend)
```
GET /api/backend/v1/orders/export
```

## 3. API Test (Không Cần Authentication)

### 3.1. Debug Đơn Hàng
```
GET /api/v1/orders/debug
```

### 3.2. Test Đơn Hàng
```
GET /api/v1/orders-test
GET /api/v1/orders-test/statistics
```

### 3.3. Test Chi Tiết
```
GET /api/v1/orders/test/{id}
GET /api/v1/orders/test/statistics
```

## 4. API Shipper

### 4.1. Đơn Hàng Của Shipper
```
GET /api/v1/shipper/orders
```

### 4.2. Nhận Đơn Hàng
```
POST /api/v1/shipper/orders/{id}/accept
```

### 4.3. Lấy Hàng
```
POST /api/v1/shipper/orders/{id}/pickup
```

### 4.4. Giao Hàng
```
POST /api/v1/shipper/orders/{id}/deliver
```

## 5. API Tracking

### 5.1. Theo Dõi Đơn Hàng
```
GET /api/v1/orders/{id}/tracking
```

### 5.2. Cập Nhật Tracking
```
POST /api/v1/orders/{id}/tracking
```

## 6. API Hóa Đơn (Invoice)

### 6.1. Tạo Hóa Đơn
```
GET /api/v1/invoice/generate?order_id={id}&format=pdf
```

### 6.2. Gửi Hóa Đơn Email
```
POST /api/v1/invoice/generate?order_id={id}&format=email
```

### 6.3. Test Hóa Đơn
```
GET /api/v1/invoice/debug/{orderId}
GET /api/v1/invoice/test/{orderId}
GET /api/v1/invoice/test-pdf/{orderId}
GET /api/v1/invoice/test-email/{orderId}
```

## 7. Trạng Thái Đơn Hàng

Các trạng thái đơn hàng được hỗ trợ:
- `pending`: Chờ xử lý
- `processing`: Đang xử lý
- `shipping`: Đang giao hàng
- `completed`: Hoàn thành
- `cancelled`: Đã hủy
- `returned`: Đã trả hàng

## 8. Authentication

Hầu hết API cần JWT token trong header:
```
Authorization: Bearer {token}
```

## 9. Error Response Format

```json
{
  "success": false,
  "message": "Error message",
  "status_code": 400
}
```

## 10. Success Response Format

```json
{
  "success": true,
  "data": {...},
  "message": "Success message"
}
```
