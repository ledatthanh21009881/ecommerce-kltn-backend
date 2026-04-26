<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Domain\Orders\Order;
use App\Domain\Orders\OrderItem;
use App\Domain\Payments\Payment;
use App\Support\{ResponseHelper, CloudinaryService, EmailService};
use FPDF;
use Exception;

class InvoiceController extends Controller
{
    private $orderModel;
    private $orderItemModel;
    private $paymentModel;
    private $cloudinaryService;
    private $emailService;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        
        // Get database from container
        $this->orderModel = new Order($container->get('database'));
        $this->orderItemModel = new OrderItem($container->get('database'));
        $this->paymentModel = new Payment($container->get('database'));
        $this->cloudinaryService = new CloudinaryService();
        $this->emailService = new EmailService();
    }

    /**
     * GET /api/invoice/generate - Tạo hóa đơn PDF
     */
    public function generate(Request $req, Response $res)
    {
        try {
            $orderId = (int) $req->query('order_id');
            $format = $req->query('format', 'pdf'); // pdf hoặc email
            
            if (!$orderId) {
                return $res->json(ResponseHelper::badRequest('Order ID is required'));
            }

            // Kiểm tra đơn hàng tồn tại
            $order = $this->orderModel->getByIdWithDetails($orderId);
            if (!$order) {
                return $res->json(ResponseHelper::notFound('Order not found'));
            }

            // Kiểm tra trạng thái đơn hàng
            if (!in_array($order['status'], ['completed', 'confirmed'])) {
                return $res->json(ResponseHelper::forbidden('Invoice can only be generated for completed or confirmed orders'));
            }

            // Kiểm tra thanh toán
            $payment = $this->paymentModel->getByOrderId($orderId);
            if (!$payment || $payment['status'] !== 'confirmed') {
                return $res->json(ResponseHelper::forbidden('Payment must be confirmed before generating invoice'));
            }

            // Tạo hóa đơn PDF
            $pdfPath = $this->createInvoicePDF($order);
            
            if ($format === 'email') {
                // Gửi email
                $this->sendInvoiceEmail($order, $pdfPath);
                return $res->json(ResponseHelper::success(null, 'Invoice sent to customer email'));
            } else {
                // Trả về file PDF
                $this->returnPDF($res, $pdfPath, $order['invoice_number']);
            }

        } catch (Exception $e) {
            error_log("Invoice generation error: " . $e->getMessage());
            return $res->json(ResponseHelper::serverError('Failed to generate invoice: ' . $e->getMessage()));
        }
    }

    /**
     * Tạo file PDF hóa đơn
     */
    private function createInvoicePDF(array $order): string
    {
        $pdf = new FPDF();
        $pdf->AddPage();
        
        // Sử dụng font hỗ trợ tiếng Việt
        $pdf->SetFont('Arial', 'B', 16);
        
        // Set UTF-8 encoding for Vietnamese characters
        $pdf->SetAutoPageBreak(true, 10);

        // Header với màu nền
        $pdf->SetFillColor(52, 73, 94); // Màu xanh đậm
        $pdf->Rect(0, 0, 210, 35, 'F');
        
        // Tiêu đề chính
        $pdf->SetFont('Arial', 'B', 18);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(0, 12, 'HÓA ĐƠN GIÁ TRỊ GIA TĂNG', 0, 1, 'C');
        
        // Thông tin công ty
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'SHOPSWIFT E-COMMERCE', 0, 1, 'C');
        
        // Reset màu
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);
        
        // Thông tin hóa đơn
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, 'Số: ' . $order['invoice_number'], 0, 1, 'R');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, 'Ngày: ' . date('d/m/Y H:i:s'), 0, 1, 'R');
        $pdf->Ln(10);

        // Thông tin người bán và người mua (2 cột)
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetFillColor(240, 240, 240);
        
        // Header cho 2 cột
        $pdf->Cell(95, 8, 'THÔNG TIN NGƯỜI BÁN', 1, 0, 'C', true);
        $pdf->Cell(95, 8, 'THÔNG TIN NGƯỜI MUA', 1, 1, 'C', true);
        
        // Reset fill color
        $pdf->SetFillColor(255, 255, 255);
        
        // Nội dung 2 cột
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(95, 6, 'Tên: VIVIENNE E-commerce', 1, 0);
        $pdf->Cell(95, 6, 'Tên: ' . $order['first_name'] . ' ' . $order['last_name'], 1, 1);
        
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(95, 6, 'MST: ' . ($_ENV['SHOP_MST'] ?? '0123456789'), 1, 0);
        $pdf->Cell(95, 6, 'Email: ' . $order['email'], 1, 1);
        
        $pdf->Cell(95, 6, 'Địa chỉ: ' . ($_ENV['SHOP_ADDRESS'] ?? '123 Đường ABC, Quận 1, TP.HCM'), 1, 0);
        $pdf->Cell(95, 6, 'Điện thoại: ' . $order['phone'], 1, 1);
        
        // Địa chỉ giao hàng
        $shippingAddress = json_decode($order['shipping_address_snapshot'], true);
        if ($shippingAddress && isset($shippingAddress['address'])) {
            $pdf->Cell(95, 6, '', 1, 0);
            $pdf->Cell(95, 6, 'Địa chỉ: ' . $shippingAddress['address'], 1, 1);
        }
        
        $pdf->Ln(10);

        // Bảng hàng hóa
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetFillColor(52, 73, 94);
        $pdf->SetTextColor(255, 255, 255);
        
        $pdf->Cell(15, 10, 'STT', 1, 0, 'C', true);
        $pdf->Cell(75, 10, 'TÊN HÀNG HÓA', 1, 0, 'C', true);
        $pdf->Cell(20, 10, 'SL', 1, 0, 'C', true);
        $pdf->Cell(30, 10, 'ĐƠN GIÁ', 1, 0, 'C', true);
        $pdf->Cell(35, 10, 'THÀNH TIỀN', 1, 0, 'C', true);
        $pdf->Cell(25, 10, 'THUẾ', 1, 1, 'C', true);
        
        // Reset màu
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);

        $pdf->SetFont('Arial', '', 9);
        $stt = 1;
        $subtotal = 0;
        $totalVat = 0;

        foreach ($order['items'] as $item) {
            $quantity = (float)($item['quantity'] ?? 0);
            $unitPrice = (float)($item['unit_price'] ?? 0);
            $itemTotal = $quantity * $unitPrice;
            $vat = $itemTotal * 0.1; // 10% VAT
            $subtotal += $itemTotal;
            $totalVat += $vat;

            $pdf->Cell(15, 8, $stt, 1, 0, 'C');
            $pdf->Cell(75, 8, $item['product_name'] ?? 'N/A', 1, 0, 'L');
            $pdf->Cell(20, 8, $quantity, 1, 0, 'C');
            $pdf->Cell(30, 8, number_format((float)$unitPrice, 0, ',', '.') . ' ₫', 1, 0, 'R');
            $pdf->Cell(35, 8, number_format((float)$itemTotal, 0, ',', '.') . ' ₫', 1, 0, 'R');
            $pdf->Cell(25, 8, number_format((float)$vat, 0, ',', '.') . ' ₫', 1, 1, 'R');
            $stt++;
        }

        // Tổng cộng
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(140, 8, 'Tổng cộng:', 1, 0, 'L', true);
        $pdf->Cell(35, 8, number_format((float)$subtotal, 0, ',', '.') . ' ₫', 1, 0, 'R', true);
        $pdf->Cell(25, 8, number_format((float)$totalVat, 0, ',', '.') . ' ₫', 1, 1, 'R', true);

        // Phí vận chuyển
        $shippingFee = (float)($order['shipping_fee'] ?? 0);
        if ($shippingFee > 0) {
            $pdf->Cell(140, 8, 'Phí vận chuyển:', 1, 0, 'L');
            $pdf->Cell(60, 8, number_format((float)$shippingFee, 0, ',', '.') . ' ₫', 1, 1, 'R');
        }

        // Giảm giá
        $discountAmount = (float)($order['discount_amount_applied'] ?? 0);
        if ($discountAmount > 0) {
            $pdf->Cell(140, 8, 'Giảm giá:', 1, 0, 'L');
            $pdf->Cell(60, 8, '-' . number_format((float)$discountAmount, 0, ',', '.') . ' ₫', 1, 1, 'R');
        }

        // Tổng thanh toán
        $totalAmount = (float)($order['total_amount'] ?? 0);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetFillColor(52, 73, 94);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(140, 12, 'TỔNG THANH TOÁN:', 1, 0, 'L', true);
        $pdf->Cell(60, 12, number_format((float)$totalAmount, 0, ',', '.') . ' ₫', 1, 1, 'R', true);
        
        // Reset màu
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);
        
        $pdf->Ln(15);

        // Chữ ký
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(95, 8, 'NGƯỜI MUA HÀNG', 1, 0, 'C', true);
        $pdf->Cell(95, 8, 'NGƯỜI BÁN HÀNG', 1, 1, 'C', true);
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Cell(95, 25, '(Ký, ghi rõ họ tên)', 1, 0, 'C', true);
        $pdf->Cell(95, 25, '(Ký, ghi rõ họ tên)', 1, 1, 'C', true);
        
        // Footer
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'I', 9);
        $pdf->SetTextColor(128, 128, 128);
        $pdf->Cell(0, 5, 'Cảm ơn quý khách đã mua hàng tại VIVIENNE!', 0, 1, 'C');
        $pdf->Cell(0, 5, 'Hóa đơn này được tạo tự động bởi hệ thống.', 0, 1, 'C');

        // Lưu file
        $filename = 'invoice_' . $order['order_id'] . '_' . date('YmdHis') . '.pdf';
        $filepath = storage_path('uploads/' . $filename);
        
        // Tạo thư mục nếu chưa có
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pdf->Output('F', $filepath);

        // Upload lên Cloudinary (tùy chọn)
        try {
            $cloudinaryUrl = $this->cloudinaryService->uploadPDF($filepath, 'invoices');
            
            // Cập nhật URL vào database
            $this->orderModel->updateInvoiceUrl($order['order_id'], $cloudinaryUrl);
            
            // Xóa file tạm
            unlink($filepath);
            
            return $cloudinaryUrl;
        } catch (Exception $e) {
            error_log("Cloudinary upload error: " . $e->getMessage());
            return $filepath; // Trả về file local nếu upload thất bại
        }
    }

    /**
     * Gửi hóa đơn qua email
     */
    private function sendInvoiceEmail(array $order, string $pdfPath): void
    {
        try {
            $subject = 'Hóa đơn đơn hàng #' . $order['invoice_number'];
            $body = $this->getInvoiceEmailTemplate($order);
            
            // Test email sending
            $result = $this->emailService->sendWithAttachment(
                $order['email'],
                $subject,
                $body,
                $pdfPath,
                'invoice.pdf'
            );

            if (!$result) {
                error_log("Failed to send invoice email to: " . $order['email']);
                throw new Exception("Failed to send email");
            }

            // Log hoạt động
            $this->orderModel->logActivity(
                $order['order_id'],
                'generate_invoice',
                1, // Use admin user ID as default
                null,
                ['email_sent' => true, 'email' => $order['email']]
            );
        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Template email hóa đơn
     */
    private function getInvoiceEmailTemplate(array $order): string
    {
        return "
        <h2>Cảm ơn bạn đã mua hàng tại VIVIENNE!</h2>
        <p>Đơn hàng #{$order['invoice_number']} của bạn đã được xử lý thành công.</p>
        <p>Tổng thanh toán: " . number_format((float)$order['total_amount'], 0, ',', '.') . " ₫</p>
        <p>Hóa đơn được đính kèm trong email này.</p>
        <p>Trân trọng,<br>VIVIENNE Team</p>
        ";
    }

    /**
     * Trả về file PDF
     */
    private function returnPDF(Response $res, string $filepath, string $invoiceNumber): void
    {
        if (file_exists($filepath)) {
            // Clear any output buffers
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            $res->setHeader('Content-Type', 'application/pdf');
            $res->setHeader('Content-Disposition', 'attachment; filename="invoice_' . $invoiceNumber . '.pdf"');
            $res->setHeader('Content-Length', (string)filesize($filepath));
            $res->setHeader('Cache-Control', 'no-cache, must-revalidate');
            $res->setHeader('Pragma', 'no-cache');
            
            readfile($filepath);
            
            // Xóa file tạm nếu là file local
            if (strpos($filepath, storage_path()) === 0) {
                unlink($filepath);
            }
        } else {
            $res->json(ResponseHelper::serverError('PDF file not found'));
        }
    }

    /**
     * POST /api/invoice/generate - Tạo và gửi hóa đơn
     */
    public function generateAndSend(Request $req, Response $res)
    {
        return $this->generate($req, $res);
    }
}
