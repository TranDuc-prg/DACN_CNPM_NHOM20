<?php
/**
 * VIET DUC PHARMACY - Automated Test Runner
 * Place beside index.php and config/db.php.
 * Browser: run_tests.php?key=VDP_TEST_2026_CHANGE_THIS_KEY
 *
 * Safety:
 * - Localhost only; no real order, stock mutation, payment or chat message.
 * - Gemini API is checked by configuration only; no API request/quota is consumed.
 * - Security checks are non-destructive smoke/static checks, not a penetration test.
 */
const TEST_KEY = 'VDP_TEST_2026_CHANGE_THIS_KEY';
const PERF_LIMIT_SECONDS = 3.0;

error_reporting(E_ALL);
ini_set('display_errors', '0');
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }

if (PHP_SAPI !== 'cli' && !hash_equals(TEST_KEY, (string)($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden: vui long cung cap key hop le.');
}

$results = [];
$conn = null;
$base = trim((string)($_GET['base'] ?? ''));

function addResult(string $id, string $group, string $feature, string $expected,
                   string $actual, string $status): void {
    global $results;
    $results[] = compact('id', 'group', 'feature', 'expected', 'actual', 'status');
}
function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function sourceText(string $relative): string {
    $path = __DIR__ . '/' . ltrim($relative, '/');
    return is_file($path) ? (string)file_get_contents($path) : '';
}
function containsAny(string $text, array $needles): bool {
    foreach ($needles as $needle) if ($needle !== '' && stripos($text, $needle) !== false) return true;
    return false;
}
function requestLocal(string $base, string $path, ?array $post = null): array {
    if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL chua duoc bat trong XAMPP.');
    $url = rtrim($base, '/') . '/' . ltrim($path, '/');
    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 12,
        CURLOPT_HEADER => true, CURLOPT_USERAGENT => 'VietDuc-TestRunner/2.0',
    ];
    if ($post !== null) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($post);
    }
    curl_setopt_array($ch, $options);
    $started = microtime(true);
    $raw = curl_exec($ch);
    $elapsed = microtime(true) - $started;
    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException($error);
    }
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return [
        'code' => $code, 'headers' => substr($raw, 0, $headerSize),
        'body' => substr($raw, $headerSize), 'seconds' => $elapsed,
    ];
}
function dbTableExists(mysqli $db, string $table): bool {
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    if (!$stmt) return false;
    $stmt->bind_param('s', $table); $stmt->execute();
    $row = $stmt->get_result()->fetch_row(); $stmt->close();
    return (int)($row[0] ?? 0) === 1;
}
function dbColumnsExist(mysqli $db, string $table, array $columns): bool {
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
    if (!$stmt) return false;
    foreach ($columns as $column) {
        $stmt->bind_param('ss', $table, $column); $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        if ((int)($row[0] ?? 0) !== 1) { $stmt->close(); return false; }
    }
    $stmt->close(); return true;
}
function countRows(mysqli $db, string $table): int {
    $allowed = ['products', 'orders'];
    if (!in_array($table, $allowed, true)) throw new RuntimeException('Bang khong nam trong danh sach cho phep.');
    $result = $db->query("SELECT COUNT(*) AS total FROM `$table`");
    if (!$result) throw new RuntimeException($db->error);
    return (int)$result->fetch_assoc()['total'];
}

if ($base === '' && PHP_SAPI !== 'cli') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
          . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
}
if ($base === '') $base = 'http://localhost';
$host = strtolower((string)parse_url($base, PHP_URL_HOST));
if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
    http_response_code(400); exit('Chi cho phep kiem thu tren localhost.');
}

/* 1. Database connection and schema */
try {
    require __DIR__ . '/config/db.php';
    if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
        throw new RuntimeException('Khong tao duoc ket noi MySQL.');
    }
    $dbName = (string)($conn->query('SELECT DATABASE()')->fetch_row()[0] ?? '');
    addResult('TC01', 'Cơ sở dữ liệu', 'Kết nối MySQL',
        'Kết nối thành công và sử dụng CSDL shop_db', 'CSDL hiện tại: ' . $dbName,
        $dbName === 'shop_db' ? 'PASS' : 'FAIL');
} catch (Throwable $e) {
    addResult('TC01', 'Cơ sở dữ liệu', 'Kết nối MySQL',
        'Kết nối thành công đến CSDL shop_db', $e->getMessage(), 'BLOCKED');
    $conn = null;
}

$schemaCases = [
    ['TC02','Tài khoản','users',['id','fullname','email','password','role']],
    ['TC03','Sản phẩm','products',['id','name','price_new','stock']],
    ['TC04','Chi tiết đơn hàng','order_details',['id','order_id','product_id','quantity','price']],
    ['TC05','Đơn hàng','orders',['id','user_id','fullname','phone','address','products','total_amount','payment_method','status']],
    ['TC06','Theo dõi đơn hàng','orders',['id','status','created_at']],
    ['TC07','Thống kê doanh thu','orders',['total_amount','created_at','status']],
    ['TC08','Quản lý người dùng','users',['id','fullname','email','role']],
    ['TC09','Sản phẩm bán chạy','order_details',['order_id','product_id','quantity','price']],
];
foreach ($schemaCases as [$id,$feature,$table,$columns]) {
    if (!$conn) {
        addResult($id,'Cơ sở dữ liệu',$feature,"Bảng $table có đủ các cột cần thiết",'Không có kết nối DB','BLOCKED');
        continue;
    }
    try {
        $ok = dbTableExists($conn,$table) && dbColumnsExist($conn,$table,$columns);
        addResult($id,'Cơ sở dữ liệu',$feature,"Bảng $table có đủ các cột cần thiết",
            $ok ? 'Bảng và các cột yêu cầu tồn tại' : 'Thiếu bảng hoặc cột bắt buộc', $ok?'PASS':'FAIL');
    } catch (Throwable $e) {
        addResult($id,'Cơ sở dữ liệu',$feature,"Bảng $table có đủ các cột cần thiết",$e->getMessage(),'FAIL');
    }
}

/* 2. HTTP routes */
$routes = [
    ['TC10','Trang chủ','index.php'], ['TC11','Đăng nhập','login.php'],
    ['TC12','Giỏ hàng','cart.php'], ['TC13','Đăng ký','register.php'],
    ['TC14','Tra cứu đơn hàng','track-order.php'],
];
foreach ($routes as [$id,$feature,$path]) {
    try {
        $r = requestLocal($base,$path);
        $ok = $r['code'] === 200;
        addResult($id,'Truy cập trang',$feature,'Trang trả về HTTP 200','HTTP '.$r['code'],$ok?'PASS':'FAIL');
    } catch (Throwable $e) {
        addResult($id,'Truy cập trang',$feature,'Trang trả về HTTP 200',$e->getMessage(),'BLOCKED');
    }
}

/* 3. Authentication and access control */
try {
    $r = requestLocal($base,'login.php',['login'=>'1','email'=>'','password'=>'']);
    $ok = $r['code']===200 && containsAny($r['body'],['Vui lòng nhập đầy đủ thông tin','Vui long nhap day du thong tin']);
    addResult('TC15','Xác thực','Đăng nhập bỏ trống','Hiển thị yêu cầu nhập đủ thông tin',
        'HTTP '.$r['code'].'; '.($ok?'tìm thấy thông báo':'không tìm thấy thông báo'),$ok?'PASS':'FAIL');
} catch (Throwable $e) { addResult('TC15','Xác thực','Đăng nhập bỏ trống','Hiển thị yêu cầu nhập đủ thông tin',$e->getMessage(),'BLOCKED'); }

try {
    $r = requestLocal($base,'login.php',['login'=>'1','email'=>'__vdp_test_missing__@example.invalid','password'=>'Invalid-Test-Password-2026']);
    $ok = $r['code']===200 && containsAny($r['body'],['Email không tồn tại','Email khong ton tai']);
    addResult('TC16','Xác thực','Email không tồn tại','Không đăng nhập và hiển thị thông báo phù hợp',
        'HTTP '.$r['code'].'; '.($ok?'tìm thấy thông báo':'không tìm thấy thông báo'),$ok?'PASS':'FAIL');
} catch (Throwable $e) { addResult('TC16','Xác thực','Email không tồn tại','Không đăng nhập và hiển thị thông báo phù hợp',$e->getMessage(),'BLOCKED'); }

try {
    $r = requestLocal($base,'admin_users.php');
    $ok = in_array($r['code'],[301,302,303],true) && stripos($r['headers'],'login.php')!==false;
    addResult('TC17','Phân quyền','Truy cập trang quản trị khi chưa đăng nhập',
        'Chuyển hướng đến login.php','HTTP '.$r['code'].'; '.($ok?'có redirect login.php':'không đúng redirect'),$ok?'PASS':'FAIL');
} catch (Throwable $e) { addResult('TC17','Phân quyền','Truy cập trang quản trị khi chưa đăng nhập','Chuyển hướng đến login.php',$e->getMessage(),'BLOCKED'); }

/* 4. Read-only business data integrity */
if (!$conn) {
    foreach ([['TC18','Dữ liệu sản phẩm'],['TC19','Dữ liệu đơn hàng']] as [$id,$name])
        addResult($id,'Dữ liệu nghiệp vụ',$name,'Dữ liệu hợp lệ','Không có kết nối DB','BLOCKED');
} else {
    try {
        $q=$conn->query('SELECT COUNT(*) total, SUM(price_new < 0) bad_price, SUM(stock < 0) bad_stock FROM products');
        $v=$q->fetch_assoc(); $ok=(int)$v['total']>0 && (int)$v['bad_price']===0 && (int)$v['bad_stock']===0;
        addResult('TC18','Dữ liệu nghiệp vụ','Dữ liệu sản phẩm','Có sản phẩm; giá và tồn kho không âm',
            'Sản phẩm: '.(int)$v['total'].'; giá âm: '.(int)$v['bad_price'].'; tồn kho âm: '.(int)$v['bad_stock'],$ok?'PASS':'FAIL');
    } catch(Throwable $e) { addResult('TC18','Dữ liệu nghiệp vụ','Dữ liệu sản phẩm','Giá và tồn kho không âm',$e->getMessage(),'FAIL'); }
    try {
        $q=$conn->query('SELECT COUNT(*) total, SUM(total_amount < 0) bad_total FROM orders');
        $v=$q->fetch_assoc(); $ok=(int)$v['total']>=0 && (int)$v['bad_total']===0;
        addResult('TC19','Dữ liệu nghiệp vụ','Dữ liệu đơn hàng','Tổng tiền đơn hàng không âm',
            'Đơn hàng: '.(int)$v['total'].'; tổng tiền âm: '.(int)$v['bad_total'],$ok?'PASS':'FAIL');
    } catch(Throwable $e) { addResult('TC19','Dữ liệu nghiệp vụ','Dữ liệu đơn hàng','Tổng tiền đơn hàng không âm',$e->getMessage(),'FAIL'); }
}

/* 5. Integration/configuration checks (no external transaction/API call) */
$vnpay = sourceText('config/vnpay_config.php');
$vnpayReturn = sourceText('vnpay_return.php');
$ok = $vnpay!=='' && $vnpayReturn!=='' && containsAny($vnpay,['sandbox.vnpayment.vn']);
addResult('TC20','Tích hợp','Cấu hình VNPay sandbox','Có cấu hình sandbox và trang nhận kết quả',
    $ok?'Tìm thấy cấu hình sandbox và vnpay_return.php':'Thiếu cấu hình hoặc endpoint',$ok?'PASS':'FAIL');

$aiConfig = sourceText('config/ai.php');
$aiEndpointFiles = ['ai_chat.php','chatbot_widget.php'];
$aiEndpointFound = false;
foreach($aiEndpointFiles as $f) if (is_file(__DIR__.'/'.$f)) $aiEndpointFound=true;
$aiKeyConfigured = false;
if ($aiConfig!=='') {
    // Load only the local config in an isolated scope; never print the secret.
    try {
        $aiInfo = (static function($file) {
            require $file;
            return ['key'=>defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '',
                    'model'=>defined('GEMINI_MODEL') ? GEMINI_MODEL : ''];
        })(__DIR__.'/config/ai.php');
        $aiKeyConfigured = trim((string)$aiInfo['key'])!=='';
    } catch(Throwable $e) { $aiKeyConfigured=false; }
}
$ok = $aiConfig!=='' && $aiEndpointFound && $aiKeyConfigured;
addResult('TC21','Tích hợp','Cấu hình Gemini API','Có cấu hình, khóa API và endpoint chatbot',
    $aiConfig===''?'Thiếu config/ai.php':(!$aiEndpointFound?'Không tìm thấy endpoint chatbot':($aiKeyConfigured?'Đã có khóa API (không hiển thị giá trị)':'Chưa cấu hình GEMINI_API_KEY')),
    $ok?'PASS':'FAIL');

$chatApi = sourceText('chat_live_api.php');
$adminChatApi = sourceText('admin_chat_api.php');
$chatSchema = $conn && dbTableExists($conn,'chat_conversations') && dbTableExists($conn,'chat_messages')
    && dbColumnsExist($conn,'chat_messages',['conversation_id','sender','message','created_at']);
$ok = $chatApi!=='' && $adminChatApi!=='' && $chatSchema;
addResult('TC22','Tích hợp','Chat trực tiếp','Có API khách/nhân viên và bảng chat cần thiết',
    $ok?'Tìm thấy API và cấu trúc bảng chat':'Thiếu API hoặc bảng/cột chat',$ok?'PASS':'FAIL');

$reportFiles = ['export_excel.php','export_pdf.php','get_chart_data.php'];
$missingReports = array_values(array_filter($reportFiles,fn($f)=>!is_file(__DIR__.'/'.$f)));
$ok = count($missingReports)===0;
addResult('TC23','Tích hợp','Thống kê và xuất báo cáo','Có endpoint Excel, PDF và dữ liệu biểu đồ',
    $ok?'Đã tìm thấy 3 thành phần báo cáo':'Thiếu: '.implode(', ',$missingReports),$ok?'PASS':'FAIL');

$productAdmin = is_file(__DIR__.'/themsp.php') || is_file(__DIR__.'/admin_products.php');
$ok = $productAdmin && $conn && dbTableExists($conn,'products')
    && dbColumnsExist($conn,'products',['id','name','price_new','stock']);
addResult('TC24','Quản trị','Quản lý sản phẩm','Có trang quản trị và bảng products hợp lệ',
    $ok?'Tìm thấy trang quản trị và cấu trúc products':'Thiếu trang hoặc cấu trúc bảng',$ok?'PASS':'FAIL');

/* 6. Security smoke checks: source review + harmless login payload */
$loginSource = sourceText('login.php');
$hasPrepared = stripos($loginSource,'prepare(')!==false && stripos($loginSource,'bind_param')!==false;
$hasPasswordVerify = stripos($loginSource,'password_verify(')!==false;
$ok = $hasPrepared && $hasPasswordVerify;
addResult('TC25','Bảo mật','Kiểm tra truy vấn và mật khẩu','Đăng nhập dùng truy vấn tham số và password_verify()',
    'Prepared Statement: '.($hasPrepared?'có':'không').'; password_verify: '.($hasPasswordVerify?'có':'không'),
    $ok?'PASS':'FAIL');

$adminChatSecurity = $adminChatApi!=='' && stripos($adminChatApi,'hash_equals')!==false
    && stripos($adminChatApi,'csrf')!==false;
addResult('TC26','Bảo mật','Kiểm tra cơ chế CSRF chat quản trị','Có kiểm tra token CSRF',
    $adminChatSecurity?'Tìm thấy kiểm tra CSRF và hash_equals()':'Không tìm thấy đầy đủ cơ chế CSRF',
    $adminChatSecurity?'PASS':'FAIL');

try {
    $payload = ["login"=>"1","email"=>"' OR '1'='1' -- ","password"=>"invalid-test"];
    $r = requestLocal($base,'login.php',$payload);
    $notRedirect = !in_array($r['code'],[301,302,303],true);
    $noSuccess = !containsAny($r['body'],['Đăng nhập thành công','Xin chào']);
    $ok = $r['code']===200 && $notRedirect && $noSuccess;
    addResult('TC27','Bảo mật','Đầu vào thử nghiệm SQL Injection tại đăng nhập',
        'Không đăng nhập trái phép hoặc chuyển hướng vào hệ thống',
        'HTTP '.$r['code'].'; '.($ok?'không có dấu hiệu đăng nhập thành công':'cần kiểm tra thủ công'),
        $ok?'PASS':'FAIL');
} catch(Throwable $e) { addResult('TC27','Bảo mật','Đầu vào thử nghiệm SQL Injection tại đăng nhập','Không đăng nhập trái phép',$e->getMessage(),'BLOCKED'); }

/* 7. Performance smoke checks: simple localhost response-time samples */
foreach ([['TC28','Thời gian phản hồi trang chủ','index.php'],['TC29','Thời gian phản hồi trang đăng nhập','login.php']] as [$id,$feature,$path]) {
    try {
        $r=requestLocal($base,$path);
        $ms=round($r['seconds']*1000,2);
        $ok=$r['code']===200 && $r['seconds']<=PERF_LIMIT_SECONDS;
        addResult($id,'Hiệu năng',$feature,'HTTP 200 và thời gian phản hồi không quá '.PERF_LIMIT_SECONDS.' giây',
            'HTTP '.$r['code'].'; thời gian: '.$ms.' ms',$ok?'PASS':'FAIL');
    } catch(Throwable $e) { addResult($id,'Hiệu năng',$feature,'Phản hồi trong giới hạn thời gian',$e->getMessage(),'BLOCKED'); }
}
if ($conn) {
    try {
        $start=microtime(true);
        $q=$conn->query('SELECT COUNT(*) AS total FROM products');
        $q->fetch_assoc();
        $ms=round((microtime(true)-$start)*1000,2);
        $ok=$ms<=1000;
        addResult('TC30','Hiệu năng','Thời gian truy vấn dữ liệu sản phẩm',
            'Truy vấn COUNT products không quá 1000 ms','Thời gian truy vấn: '.$ms.' ms',$ok?'PASS':'FAIL');
    } catch(Throwable $e) { addResult('TC30','Hiệu năng','Thời gian truy vấn dữ liệu sản phẩm','Truy vấn thực hiện được',$e->getMessage(),'FAIL'); }
} else {
    addResult('TC30','Hiệu năng','Thời gian truy vấn dữ liệu sản phẩm','Truy vấn thực hiện được','Không có kết nối DB','BLOCKED');
}

/* CSV export */
if (isset($_GET['export']) && $_GET['export']==='csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="VIET_DUC_PHARMACY_test_results.csv"');
    echo "\xEF\xBB\xBF";
    $out=fopen('php://output','w');
    fputcsv($out,['Mã TC','Nhóm kiểm thử','Chức năng','Kết quả mong đợi','Kết quả thực tế','Trạng thái']);
    foreach($results as $r) fputcsv($out,[$r['id'],$r['group'],$r['feature'],$r['expected'],$r['actual'],$r['status']]);
    fclose($out); exit;
}

$counts=array_fill_keys(['PASS','FAIL','BLOCKED','NOT RUN'],0);
foreach($results as $r) if(isset($counts[$r['status']])) $counts[$r['status']]++;
$total=count($results);
$rate=$total ? round($counts['PASS']/$total*100,2) : 0;

if (PHP_SAPI==='cli') {
    foreach($results as $r) echo implode(' | ',[$r['id'],$r['status'],$r['group'],$r['feature'],$r['actual']]).PHP_EOL;
    echo "TOTAL=$total PASS={$counts['PASS']} FAIL={$counts['FAIL']} BLOCKED={$counts['BLOCKED']} RATE={$rate}%".PHP_EOL;
    exit;
}
?><!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>VIET DUC PHARMACY - Báo cáo kiểm thử</title>
<style>
*{box-sizing:border-box}body{font:25px Arial,sans-serif;background:#f3f6fb;margin:0;padding:24px;color:#17243a}
main{max-width:1500px;margin:auto}.panel{background:#fff;border-radius:14px;padding:24px;box-shadow:0 3px 18px #dce3ed}
h1{font-size:25px;margin:0 0 10px}.muted{color:#66748a}.stats{display:flex;gap:12px;flex-wrap:wrap;margin:18px 0}
.stat{background:#edf3fa;padding:12px 17px;border-radius:9px}.stat strong{margin-right:5px}
table{width:100%;border-collapse:collapse;font-size:25px}th,td{border-bottom:1px solid #e5eaf1;padding:10px;text-align:left;vertical-align:top}
th{background:#edf3fa;position:sticky;top:0}b.PASS{color:#118353}b.FAIL{color:#c52e42}b.BLOCKED,b.NOT\ RUN{color:#a57919}
a{color:#145fb3}.notice{background:#fff8e8;border-left:4px solid #e0a52c;padding:12px 15px;margin:16px 0;border-radius:5px}
.table-wrap{overflow-x:auto}.rate{font-weight:bold}.small{font-size:25px}
@media(max-width:700px){body{padding:8px}.panel{padding:12px}h1{font-size:25px}}
</style></head><body><main class="panel">
<h1>VIET DUC PHARMACY — Kết quả kiểm thử tự động</h1>
<div class="stats">
<?php foreach(['PASS','FAIL','BLOCKED','NOT RUN'] as $s): ?><div class="stat"><strong><?=h($s)?>:</strong> <?=$counts[$s]?></div><?php endforeach; ?>
<div class="stat rate">Tỷ lệ PASS: <?=$rate?>%</div></div>
<p><a href="?key=<?=urlencode(TEST_KEY)?>&amp;export=csv">Tải kết quả CSV để mở bằng Excel</a></p>
<div class="table-wrap"><table><thead><tr><th>Mã TC</th><th>Nhóm</th><th>Chức năng</th><th>Kết quả mong đợi</th><th>Kết quả thực tế</th><th>Trạng thái</th></tr></thead><tbody>
<?php foreach($results as $r): ?><tr><td><?=h($r['id'])?></td><td><?=h($r['group'])?></td><td><?=h($r['feature'])?></td><td><?=h($r['expected'])?></td><td><?=h($r['actual'])?></td><td><b class="<?=h($r['status'])?>"><?=h($r['status'])?></b></td></tr><?php endforeach; ?>
</tbody></table></div>
</main></body></html>
