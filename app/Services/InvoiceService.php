<?php
declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;

class InvoiceService
{
    private $dompdf;
    
    public function __construct()
    {
        $this->dompdf = new Dompdf();
        
        // Configure DOMPDF
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');
        
        $this->dompdf->setOptions($options);
    }
    
    /**
     * Tạo hóa đơn PDF từ dữ liệu đơn hàng
     */
    public function generateInvoicePDF(array $order): string
    {
        try {
            $html = $this->generateInvoiceHTML($order);
            
            $this->dompdf->loadHtml($html);
            $this->dompdf->setPaper('A4', 'portrait');
            $this->dompdf->render();
            
            return $this->dompdf->output();
            
        } catch (Exception $e) {
            error_log("Error generating invoice PDF: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Tạo HTML cho hóa đơn
     */
    private function generateInvoiceHTML(array $order): string
    {
        $orderItems = $order['items'] ?? [];
        $customer = $order['customer'] ?? [];
        $shippingAddress = $order['shipping_address'] ?? [];
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Hóa đơn #' . $order['invoice_number'] . '</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                    line-height: 1.4;
                    color: #333;
                    margin: 0;
                    padding: 20px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                    border-bottom: 2px solid #4F46E5;
                    padding-bottom: 20px;
                }
                .company-name {
                    font-size: 24px;
                    font-weight: bold;
                    color: #4F46E5;
                    margin-bottom: 5px;
                }
                .company-info {
                    font-size: 12px;
                    color: #666;
                }
                .invoice-title {
                    font-size: 18px;
                    font-weight: bold;
                    margin: 20px 0;
                    color: #333;
                }
                .invoice-details {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 30px;
                }
                .invoice-info, .customer-info {
                    width: 45%;
                }
                .info-group {
                    margin-bottom: 10px;
                }
                .info-label {
                    font-weight: bold;
                    color: #666;
                }
                .info-value {
                    color: #333;
                }
                .items-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                .items-table th {
                    background-color: #f8f9fa;
                    border: 1px solid #ddd;
                    padding: 10px;
                    text-align: left;
                    font-weight: bold;
                }
                .items-table td {
                    border: 1px solid #ddd;
                    padding: 10px;
                }
                .items-table .text-right {
                    text-align: right;
                }
                .items-table .text-center {
                    text-align: center;
                }
                .totals {
                    width: 100%;
                    margin-top: 20px;
                }
                .totals table {
                    width: 300px;
                    margin-left: auto;
                    border-collapse: collapse;
                }
                .totals td {
                    padding: 5px 10px;
                    border-bottom: 1px solid #eee;
                }
                .totals .total-row {
                    font-weight: bold;
                    border-top: 2px solid #4F46E5;
                    border-bottom: 2px solid #4F46E5;
                }
                .footer {
                    margin-top: 40px;
                    text-align: center;
                    font-size: 10px;
                    color: #666;
                    border-top: 1px solid #eee;
                    padding-top: 20px;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="company-name">VIVIENNE</div>
                <div class="company-info">
                    Địa chỉ: 123 Đường ABC, Quận XYZ, TP.HCM<br>
                    Điện thoại: 0123 456 789 | Email: info@vivienne.com<br>
                    Website: www.vivienne.com
                </div>
            </div>
            
            <div class="invoice-title">HÓA ĐƠN BÁN HÀNG</div>
            
            <div class="invoice-details">
                <div class="invoice-info">
                    <div class="info-group">
                        <span class="info-label">Số hóa đơn:</span>
                        <span class="info-value">#' . $order['invoice_number'] . '</span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Ngày đặt:</span>
                        <span class="info-value">' . date('d/m/Y H:i', strtotime($order['created_at'])) . '</span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Trạng thái:</span>
                        <span class="info-value">' . ucfirst($order['status']) . '</span>
                    </div>
                </div>
                
                <div class="customer-info">
                    <div class="info-group">
                        <span class="info-label">Khách hàng:</span>
                        <span class="info-value">' . ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '') . '</span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Email:</span>
                        <span class="info-value">' . ($customer['email'] ?? '') . '</span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Điện thoại:</span>
                        <span class="info-value">' . ($customer['phone'] ?? '') . '</span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Địa chỉ giao hàng:</span>
                        <span class="info-value">' . ($shippingAddress['address_line'] ?? '') . '</span>
                    </div>
                </div>
            </div>
            
            <table class="items-table">
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Sản phẩm</th>
                        <th class="text-center">Số lượng</th>
                        <th class="text-right">Đơn giá</th>
                        <th class="text-right">Thành tiền</th>
                    </tr>
                </thead>
                <tbody>';
        
        $total = 0;
        foreach ($orderItems as $index => $item) {
            $itemTotal = $item['quantity'] * $item['unit_price'];
            $total += $itemTotal;
            
            $html .= '
                    <tr>
                        <td>' . ($index + 1) . '</td>
                        <td>' . ($item['product_name_snapshot'] ?? 'N/A') . '</td>
                        <td class="text-center">' . $item['quantity'] . '</td>
                        <td class="text-right">' . number_format($item['unit_price'], 0, ',', '.') . ' ₫</td>
                        <td class="text-right">' . number_format($itemTotal, 0, ',', '.') . ' ₫</td>
                    </tr>';
        }
        
        $html .= '
                </tbody>
            </table>
            
            <div class="totals">
                <table>
                    <tr>
                        <td>Tạm tính:</td>
                        <td class="text-right">' . number_format($total, 0, ',', '.') . ' ₫</td>
                    </tr>
                    <tr>
                        <td>Phí vận chuyển:</td>
                        <td class="text-right">' . number_format($order['shipping_fee'] ?? 0, 0, ',', '.') . ' ₫</td>
                    </tr>';
        
        if (($order['discount_amount_applied'] ?? 0) > 0) {
            $html .= '
                    <tr>
                        <td>Giảm giá:</td>
                        <td class="text-right">-' . number_format($order['discount_amount_applied'], 0, ',', '.') . ' ₫</td>
                    </tr>';
        }
        
        $html .= '
                    <tr class="total-row">
                        <td><strong>Tổng cộng:</strong></td>
                        <td class="text-right"><strong>' . number_format((float)$order['total_amount'], 0, ',', '.') . ' ₫</strong></td>
                    </tr>
                </table>
            </div>
            
            <div class="footer">
                <p>Cảm ơn quý khách đã mua hàng tại VIVIENNE!</p>
                <p>Hóa đơn này được tạo tự động. Vui lòng liên hệ chúng tôi nếu có thắc mắc.</p>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Lưu hóa đơn PDF vào file
     */
    public function saveInvoicePDF(array $order, string $directory = 'invoices'): string
    {
        try {
            // Tạo thư mục nếu chưa tồn tại
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            
            $filename = 'invoice_' . $order['invoice_number'] . '_' . date('Y-m-d_H-i-s') . '.pdf';
            $filepath = $directory . '/' . $filename;
            
            $pdfContent = $this->generateInvoicePDF($order);
            file_put_contents($filepath, $pdfContent);
            
            return $filepath;
            
        } catch (Exception $e) {
            error_log("Error saving invoice PDF: " . $e->getMessage());
            throw $e;
        }
    }
}
