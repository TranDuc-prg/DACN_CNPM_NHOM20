<?php
/**
 * VIET DUC PHARMACY - Export PDF
 * Dependency: composer require dompdf/dompdf
 * Keep the existing authorization checks from your project before allowing data exports.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    exit('Chua cai Dompdf. Trong thu muc BANTHUOC_chot, chay: composer require dompdf/dompdf');
}
require_once $autoload;

if (!class_exists(\Dompdf\Dompdf::class)) {
    http_response_code(500);
    exit('Khong tim thay thu vien Dompdf. Hay chay: composer require dompdf/dompdf');
}

// IMPORTANT: Add the same admin/staff permission guard used by your existing reports.
// Example: require_once __DIR__ . '/config/admin_auth.php'; (only if it exists in your project).

$views = [
    'products'   => ['title' => 'DANH SÁCH THUỐC', 'filename' => 'danh_sach_thuoc.pdf'],
    'orders'     => ['title' => 'DANH SÁCH ĐƠN HÀNG', 'filename' => 'danh_sach_don_hang.pdf'],
    'customers'  => ['title' => 'THỐNG KÊ KHÁCH HÀNG', 'filename' => 'thong_ke_khach_hang.pdf'],
    'newsletter' => ['title' => 'DANH SÁCH EMAIL ĐĂNG KÝ', 'filename' => 'danh_sach_email.pdf'],
];

$view = (string)($_GET['view'] ?? 'products');
if (!isset($views[$view])) {
    http_response_code(400);
    exit('Loai bao cao khong hop le.');
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    exit('Khong ket noi duoc co so du lieu.');
}
$conn->set_charset('utf8mb4');

function pdfEscape($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money($value): string
{
    return number_format((float)($value ?? 0), 0, ',', '.') . ' đ';
}

function rowCells(array $values): string
{
    $out = '<tr>';
    foreach ($values as $cell) {
        $out .= '<td>' . pdfEscape($cell) . '</td>';
    }
    return $out . '</tr>';
}

$headings = [];
$sql = '';
switch ($view) {
    case 'orders':
        $headings = ['ID', 'Khách hàng', 'SĐT', 'Tổng tiền', 'Trạng thái'];
        $sql = 'SELECT id, fullname, phone, total_amount, status FROM orders ORDER BY id DESC';
        break;
    case 'customers':
        $headings = ['Tên khách hàng', 'SĐT', 'Địa chỉ', 'Số đơn', 'Tổng chi'];
        // Select customer details from the most recent order per phone to avoid invalid GROUP BY.
        $sql = 'SELECT o.fullname, o.phone, o.address, s.total_orders, s.total_spent
                FROM orders o
                JOIN (
                  SELECT phone, MAX(id) AS latest_id,
                         COUNT(*) AS total_orders, SUM(total_amount) AS total_spent
                  FROM orders GROUP BY phone
                ) s ON o.id = s.latest_id
                ORDER BY s.total_spent DESC';
        break;
    case 'newsletter':
        $headings = ['Email', 'Ngày tạo'];
        $sql = 'SELECT email, created_at FROM newsletter ORDER BY created_at DESC';
        break;
    default:
        $headings = ['ID', 'Tên thuốc', 'Danh mục', 'Giá'];
        $sql = 'SELECT id, name, category, price_new FROM products ORDER BY id DESC';
}

try {
    $result = $conn->query($sql);
    if (!$result) {
        throw new RuntimeException($conn->error);
    }

    $rows = '';
    $count = 0;
    while ($r = $result->fetch_assoc()) {
        switch ($view) {
            case 'orders':
                $cells = [$r['id'], $r['fullname'], $r['phone'], money($r['total_amount']), $r['status']];
                break;
            case 'customers':
                $cells = [$r['fullname'], $r['phone'], $r['address'], $r['total_orders'], money($r['total_spent'])];
                break;
            case 'newsletter':
                $cells = [$r['email'], $r['created_at']];
                break;
            default:
                $cells = [$r['id'], $r['name'], $r['category'], money($r['price_new'])];
        }
        $rows .= rowCells($cells);
        $count++;
    }
    $result->free();

    if ($count === 0) {
        $rows = '<tr><td colspan="' . count($headings) . '">Không có dữ liệu.</td></tr>';
    }

    $header = '<tr>';
    foreach ($headings as $heading) {
        $header .= '<th>' . pdfEscape($heading) . '</th>';
    }
    $header .= '</tr>';

    $title = pdfEscape($views[$view]['title']);
    $date = date('d/m/Y H:i');
    $html = '<!doctype html><html lang="vi"><head><meta charset="UTF-8">
    <style>
      @page { margin: 24px 22px; }
      body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #222; }
      h2 { text-align: center; font-size: 16px; margin: 0 0 12px; }
      .meta { text-align: right; color: #555; font-size: 9px; margin-bottom: 12px; }
      table { width: 100%; border-collapse: collapse; table-layout: auto; }
      th, td { border: 1px solid #999; padding: 6px 5px; text-align: left; overflow-wrap: anywhere; }
      th { background-color: #eeeeee; }
      tr { page-break-inside: avoid; }
      thead { display: table-header-group; }
    </style></head><body><h2>' . $title . '</h2><div class="meta">Ngày xuất: ' . pdfEscape($date) . ' | Tổng dòng: ' . $count . '</div>
    <table><thead>' . $header . '</thead><tbody>' . $rows . '</tbody></table></body></html>';

    $options = new \Dompdf\Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isRemoteEnabled', false);
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    $output = $dompdf->output();
    if (substr($output, 0, 5) !== '%PDF-') {
        throw new RuntimeException('Ket qua tao ra khong phai PDF hop le.');
    }

    // Do not send HTML or other output before these headers.
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $views[$view]['filename'] . '"');
    header('Content-Length: ' . strlen($output));
    header('Cache-Control: private, no-store');
    echo $output;
    exit;
} catch (Throwable $e) {
    error_log('PDF export failed: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Xuat PDF that bai. Vui long xem PHP error log va kiem tra cau truc bang du lieu.');
}
