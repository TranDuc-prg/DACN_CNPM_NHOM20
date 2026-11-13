<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

$root = realpath(__DIR__ . '/..');
if (!$root || !is_file($root . '/index.php')) {
    fwrite(STDERR, "Hay dat thu muc kiem_thu trong BANTHUOC_chot (cung cap voi index.php).\n");
    exit(2);
}
$base = $argv[1] ?? 'http://localhost/VIET_DUC_PHARMACY_500_VISITS_OPTIMIZED/VIET_DUC_PHARMACY_CHAT_TRUC_TIEP/BANTHUOC_CHATBOT_AI/BANTHUOC_chot/';
$urlInfo = parse_url($base);
if (!$urlInfo || !in_array(strtolower($urlInfo['host'] ?? ''), ['localhost','127.0.0.1'], true)) {
    fwrite(STDERR, "Chi kiem thu localhost/127.0.0.1.\n"); exit(2);
}
$base = rtrim($base, '/') . '/';
$outputDir = __DIR__ . '/ket_qua';
if (!is_dir($outputDir)) mkdir($outputDir, 0775, true);
$results = [];
function addResult(string $id,string $group,string $tech,string $name,string $input,string $expected,string $actual,string $status,string $evidence=''): void {
    global $results;
    $results[] = compact('id','group','tech','name','input','expected','actual','status','evidence');
    echo sprintf("%-6s %-8s %s\n", $id, $status, $name);
}
function request(string $base,string $route, ?array $post=null): array {
    if (!function_exists('curl_init')) throw new RuntimeException('Chua bat extension curl trong php.ini');
    $ch = curl_init($base . $route);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>15,CURLOPT_USERAGENT=>'VDP-LocalTest/2.0']);
    if ($post!==null) curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);
    $raw = curl_exec($ch);
    if ($raw === false) { $error = curl_error($ch); curl_close($ch); throw new RuntimeException($error); }
    $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
    $headerSize=(int)curl_getinfo($ch,CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return ['http'=>$code,'headers'=>substr($raw,0,$headerSize),'body'=>substr($raw,$headerSize)];
}
function isLoginForm(string $html): bool {
    return preg_match('/<input\b[^>]*name\s*=\s*["\']email["\']/i', $html)===1
        && preg_match('/<input\b[^>]*name\s*=\s*["\']password["\']/i',$html)===1;
}
function runHttpCase(string $id,string $name,string $path, callable $validator,string $expected): void {
    global $base;
    try {
        $response=request($base,$path);
        $pass=$validator($response);
        $actual='HTTP '.$response['http'].'; body_len='.strlen($response['body']);
        addResult($id,'Chuc nang','Hop den',$name,'GET '.$path,$expected,$actual,$pass?'PASS':'FAIL');
    } catch(Throwable $e) { addResult($id,'Chuc nang','Hop den',$name,'GET '.$path,$expected,$e->getMessage(),'BLOCKED'); }
}
runHttpCase('BB01','Trang chu co noi dung','index.php',
    fn($r)=>$r['http']===200 && preg_match('/<html\b/i',$r['body'])===1 && strlen(strip_tags($r['body']))>40,
    'HTTP 200; HTML va noi dung trang chu khong rong');
runHttpCase('BB02','Form dang nhap','login.php',
    fn($r)=>$r['http']===200 && isLoginForm($r['body']),
    'Trang co input email va password');
runHttpCase('BB03','Form dang ky','register.php',
    fn($r)=>$r['http']===200 && preg_match('/name=["\']fullname["\']/i',$r['body'])===1 && isLoginForm($r['body']),
    'Trang co fullname, email va password');
runHttpCase('BB04','Trang tra cuu don','track-order.php',
    fn($r)=>$r['http']===200 && strlen(strip_tags($r['body']))>30,
    'Trang tra cuu co noi dung; CHUA xac nhan tra cuu dung ma don');

foreach([
    ['BB05','Dang nhap bo trong',['login'=>'1','email'=>'','password'=>''],'Vui lòng nhập đầy đủ thông tin!'],
    ['BB06','Dang nhap email khong ton tai',['login'=>'1','email'=>'__test_not_found__@example.invalid','password'=>'not-a-real-password'],'Email không tồn tại!'],
] as [$id,$name,$post,$message]) {
    try {
        $r=request($base,'login.php',$post);
        $pass=$r['http']===200 && str_contains($r['body'],$message);
        addResult($id,'Chuc nang','Hop den',$name,json_encode($post,JSON_UNESCAPED_UNICODE),
            'HTTP 200; thong bao '.$message,'HTTP '.$r['http'].'; message_found='.($pass?'yes':'no'),$pass?'PASS':'FAIL');
    } catch(Throwable $e) {addResult($id,'Chuc nang','Hop den',$name,'POST login.php','Khong tao phien dang nhap',$e->getMessage(),'BLOCKED');}
}
try {
    $r=request($base,'admin_users.php');
    $location=(string)(preg_match('/^Location:\s*([^\r\n]+)/im',$r['headers'],$m) ? $m[1] : '');
    $pass=in_array($r['http'],[301,302,303,307,308],true) && stripos($location,'login.php')!==false;
    addResult('BB07','Bao mat','Hop den','Chua dang nhap vao admin','GET admin_users.php khong cookie',
        'Chuyen huong den login.php','HTTP '.$r['http'].'; Location='.$location,$pass?'PASS':'FAIL');
} catch(Throwable $e) {addResult('BB07','Bao mat','Hop den','Chua dang nhap vao admin','GET admin_users.php','Chuyen huong den login.php',$e->getMessage(),'BLOCKED');}

// White-box tests target *real* pure functions from config/chat_helpers.php.
$helper = $root.'/config/chat_helpers.php';
if (!is_file($helper)) {
    addResult('WB01','Don vi','Hop trang','Ham chat_text_length','"abc"','3','Missing chat_helpers.php','BLOCKED');
    addResult('WB02','Don vi','Hop trang','Ham chat_text_substr','"abcdef",1,3','"bcd"','Missing chat_helpers.php','BLOCKED');
} else {
    require_once $helper;
    $val=chat_text_length('abc');
    addResult('WB01','Don vi','Hop trang','Nhanh dem do dai chat','chat_text_length("abc")','3',(string)$val,$val===3?'PASS':'FAIL','config/chat_helpers.php');
    $val=chat_text_substr('abcdef',1,3);
    addResult('WB02','Don vi','Hop trang','Nhanh cat chuoi chat','chat_text_substr("abcdef",1,3)','bcd',(string)$val,$val==='bcd'?'PASS':'FAIL','config/chat_helpers.php');
    if(function_exists('mb_strlen') && function_exists('mb_substr')) {
        $val=chat_text_length('Xin chào');
        addResult('WB03','Don vi','Hop trang','Nhanh Unicode mb_strlen','chat_text_length("Xin chào")','8',(string)$val,$val===8?'PASS':'FAIL','config/chat_helpers.php');
    } else {
        addResult('WB03','Don vi','Hop trang','Nhanh Unicode mb_strlen','chat_text_length("Xin chào")','8','Chua co extension mbstring','BLOCKED');
    }
}

$files=['login.php','register.php','cart.php','invoice.php','track-order.php','ai_chat.php','config/chat_helpers.php'];
foreach($files as $i=>$file) {
    $src=$root.'/'.$file;
    $id='ST'.sprintf('%02d',$i+1);
    if (!is_file($src)) { addResult($id,'Tinh','Hop trang','Lint '.$file,$file,'No syntax errors','Missing file','FAIL'); continue; }
    $command=escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($src).' 2>&1';
    $output=[];$exit=0;exec($command,$output,$exit);
    addResult($id,'Tinh','Hop trang','Lint '.$file,'php -l '.$file,'No syntax errors',implode(' ',$output),$exit===0?'PASS':'FAIL',$file);
}

// No test data is silently written to the production DB.
foreach([
 ['BB08','Gio hang them/sua/xoa'],['BB09','Dat hang COD'],['BB10','Thanh toan chuyen khoan'],
 ['BB11','VNPay sandbox callback'],['BB12','Tra cuu ma don hop le'],['BB13','Chatbot API tra loi'],
 ['BB14','Chat truc tiep'],['BB15','Xuat Excel/PDF'],['WB04','Nhanh xu ly gia/tong tien'],
 ['NF01','JMeter throughput / P95'],['NF02','Kiem thu tai 500 luot'],['NF03','OWASP ZAP'],
] as [$id,$name]) addResult($id,str_starts_with($id,'NF')?'Phi chuc nang':'Chuc nang',str_starts_with($id,'WB')?'Hop trang':'Hop den',
    $name,'Can du lieu tai khoan/DB test hoac cong cu rieng','Doi chieu voi yeu cau nghiep vu','Khong thuc thi trong bo read-only','NOT RUN');

$stamp=date('Ymd_His');
$csv=$outputDir.'/ket_qua_'.$stamp.'.csv';
$f=fopen($csv,'wb'); fwrite($f,"\xEF\xBB\xBF");
fputcsv($f,['Ma TC','Nhom','Ky thuat','Ten kiem thu','Du lieu dau vao','Ket qua mong doi','Ket qua thuc te','Trang thai','Bang chung']);
foreach($results as $r) fputcsv($f,array_values($r));
fclose($f);
$json=$outputDir.'/ket_qua_'.$stamp.'.json';
file_put_contents($json,json_encode(['base'=>$base,'time'=>date('c'),'results'=>$results],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
$counts=array_count_values(array_column($results,'status'));
echo "\nTONG KET: ".json_encode($counts,JSON_UNESCAPED_UNICODE)."\nCSV: $csv\nJSON: $json\n";
