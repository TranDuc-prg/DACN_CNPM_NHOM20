<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require __DIR__ . '/chay_kiem_thu.php'; // runs original 17 checks; initializes $base, $results, $root

function vdReplace(string $id,string $status,string $actual,string $expected='',string $evidence=''): void {
    global $results;
    foreach ($results as &$r) if ($r['id']===$id) {
        $r['status']=$status; $r['actual']=$actual;
        if ($expected!=='') $r['expected']=$expected;
        if ($evidence!=='') $r['evidence']=$evidence;
        echo sprintf("%-6s %-8s %s\n",$id,$status,$r['name']);
        return;
    }
    unset($r);
}
function vdRequest(string $route, ?array $post=null, ?string $cookie=null, ?string $json=null): array {
    global $base;
    if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL extension chua bat');
    $ch=curl_init($base . ltrim($route,'/'));
    $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>18,CURLOPT_USERAGENT=>'VDP-QA-Local/3.0'];
    if ($cookie!==null) {
        $opts[CURLOPT_COOKIEFILE]=$cookie;
        $opts[CURLOPT_COOKIEJAR]=$cookie;
    }
    if ($post!==null) {
        $opts[CURLOPT_POST]=true;
        $opts[CURLOPT_POSTFIELDS]=$json ?? http_build_query($post);
        if($json!==null) $opts[CURLOPT_HTTPHEADER]=['Content-Type: application/json'];
    }
    curl_setopt_array($ch,$opts);
    $raw=curl_exec($ch);
    if($raw===false){ $msg=curl_error($ch);curl_close($ch);throw new RuntimeException($msg); }
    $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
    $n=(int)curl_getinfo($ch,CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return ['http'=>$http,'headers'=>substr($raw,0,$n),'body'=>substr($raw,$n)];
}
function vdCase(string $id,callable $fn): void {
    try { [$pass,$actual,$expected,$evidence]=$fn();vdReplace($id,$pass?'PASS':'FAIL',$actual,$expected,$evidence); }
    catch(Throwable $e) {vdReplace($id,'BLOCKED',get_class($e).': '.$e->getMessage());}
}
function vdTestProductId(): int {
    global $root;
    require $root.'/config/db.php'; // source application DB is used for read-only product selection
    $q=$conn->query('SELECT id FROM products WHERE id>0 ORDER BY id ASC LIMIT 1');
    if(!$q) throw new RuntimeException('products khong doc duoc');
    $row=$q->fetch_assoc(); $conn->close();
    if(!$row) throw new RuntimeException('Can it nhat 1 san pham trong products');
    return (int)$row['id'];
}
// BB08: real PHP cart endpoint; only session-cookie modified, no database writes.
vdCase('BB08',function() {
    $id=vdTestProductId();
    $cookie=tempnam(sys_get_temp_dir(),'vdqa_');
    try {
        $r1=vdRequest('cart.php',['add_to_cart_id'=>(string)$id],$cookie);
        $r2=vdRequest('cart.php',['add_to_cart_id'=>(string)$id],$cookie);
        $r3=vdRequest('cart.php',['update_qty'=>'1','product_id'=>(string)$id,'action'=>'minus'],$cookie);
        $r4=vdRequest('cart.php',['update_qty'=>'1','product_id'=>(string)$id,'action'=>'minus'],$cookie);
        $r5=vdRequest('cart.php',null,$cookie);
        $p=$r1['http']===200 && trim($r1['body'])==='1' && trim($r2['body'])==='2'
          && $r3['http']===200 && $r4['http']===200 && $r5['http']===200
          && !preg_match('/\b'.preg_quote((string)$id,'/').'\b\s*x\s*[12]/i',$r5['body']);
        return [$p,'them1='.trim($r1['body']).'; them2='.trim($r2['body']).'; giam 2 lan; HTTP='. $r5['http'],
            'Them 1=>2, giam 2 lan=>xoa khoi gio; khong ghi CSDL','session cookie tam'];
    } finally { @unlink($cookie); }
});
// BB11: invalid signed callback must be rejected; DOES NOT validate successful sandbox payment.
vdCase('BB11',function() {
    $r=vdRequest('vnpay_return.php?vnp_ResponseCode=00&vnp_Amount=10000&vnp_TxnRef=9999&vnp_SecureHash=invalid');
    $pass=stripos($r['body'],'Chữ ký VNPay không hợp lệ')!==false
       || stripos($r['body'],'ch&#7919; k&#253;')!==false;
    return [$pass,'HTTP '.$r['http'].'; reject_bad_signature='.($pass?'yes':'no'),
        'Callback chu ky sai bi tu choi; KHONG khang dinh thanh toan thanh cong','vnpay_return.php'];
});
// BB13: API error validation, no paid AI call. Not a chatbot-answer test.
vdCase('BB13',function() {
    $r=vdRequest('ai_chat.php',[],null,json_encode(['message'=>''],JSON_UNESCAPED_UNICODE));
    $j=json_decode($r['body'],true);
    $pass=$r['http']===400 && is_array($j) && ($j['ok']??null)===false && !empty($j['reply']);
    return [$pass,'HTTP '.$r['http'].'; json_ok='.json_encode($j['ok']??null),
        'Cau hoi trong: HTTP 400 va JSON ok=false; chua kiem thu AI sinh cau tra loi','ai_chat.php'];
});
// BB15: source Excel endpoint produces HTML table with XLS header; PDF endpoint is HTML page for browser printing, not a generated PDF.
vdCase('BB15',function() {
    $xls=vdRequest('export_excel.php?view=products');
    $pdf=vdRequest('export_pdf.php?view=products');
    $ctype=strtolower($xls['headers']);
    $xlsOK=$xls['http']===200 && str_contains($ctype,'application/vnd.ms-excel') && stripos($xls['body'],'<table')!==false;
    $pdfIsBinary=str_starts_with(ltrim($pdf['body']),'%PDF-');
    $p=$xlsOK && $pdf['http']===200 && $pdfIsBinary;
    return [$p,'XLS='.($xlsOK?'OK':'FAIL').'; PDF_binary='.($pdfIsBinary?'yes':'NO - HTML, not actual PDF'),
        'Excel file hop le va PDF bat dau voi %PDF-; chu y export_pdf.php hien xuat HTML',
        'export_excel.php; export_pdf.php'];
});
// WB04: verify actual price calculation by reading expression in cart.php, then checking boundary cases.
vdCase('WB04',function() {
    global $root;
    $s=file_get_contents($root.'/cart.php');
    if($s===false) throw new RuntimeException('Khong mo duoc cart.php');
    $hasCalc=str_contains($s,"$".'r[\'subtotal\']') && str_contains($s,'$final_total = $after_discount + $vat;');
    $cases=[[50000,2,0,110000],[50000,2,10,99000],[0,3,0,0]];
    $ok=$hasCalc; $actual=[];
    foreach($cases as [$price,$qty,$percent,$want]) {
        $subtotal=$price*$qty;
        $discount=$subtotal*$percent/100;
        $after=$subtotal-$discount;
        $total=$after + $after*0.10;
        $actual[]="$price x $qty discount=$percent% => $total";
        if(abs($total-$want)>0.001) $ok=false;
    }
    // This is a code-guided unit calculation, not invoking original checkout module.
    return [$ok,implode('; ',$actual).'; formula_in_source='.($hasCalc?'yes':'no'),
        '3 mau phep tinh tong tien VAT10%; can tach ham tinh tien de dat unit test thuc su','cart.php: price and VAT expressions'];
});
// BB12: unauthenticated tracking is safe to check, but not valid-order lookup.
vdReplace('BB12','BLOCKED','Can tai khoan TEST co don hang hoac session phone khop mot don hang TEST; GET trang khong du de ket luan tra cuu dung',
    'Trang tra cuu hien thi dung don hang TEST va khong hien don hang nguoi khac','track-order.php');
// Persistent writes, real payment/bank and chat data are not executed without isolated test DB.
foreach ([
  'BB09'=>'COD phai tao orders va order_details tren CSDL TEST rieng (co rollback/cleanup)',
  'BB10'=>'Chuyen khoan phai dung don hang TEST va xac minh trang thai; khong thuc hien chuyen tien that',
  'BB14'=>'Chat init/send ghi chat_conversations/chat_messages; chi thu tren CSDL TEST',
] as $id=>$why) vdReplace($id,'BLOCKED',$why,
    'Phai duoc kiem tra end-to-end voi CSDL test; CHUA thuc thi','Required fixture: disposable DB');
// NF01, NF02, NF03 run with the dedicated scripts included in this package.
foreach(['NF01'=>'Chay Apache JMeter va nap ket qua jtl','NF02'=>'500 luot truy cap chay JMeter, theo doi error rate / P95',
         'NF03'=>'Chay ZAP baseline/passive scan tren site local test'] as $id=>$txt) {
    vdReplace($id,'BLOCKED',$txt,'Can log thuc thi JMeter/ZAP; khong tu danh PASS','See HUONG_DAN_MO_RONG.txt');
}

$dir=__DIR__.'/ket_qua';if(!is_dir($dir)) mkdir($dir,0775,true);
$stamp=date('Ymd_His');$csv=$dir.'/tong_hop_29_'.$stamp.'.csv';
$f=fopen($csv,'wb');fwrite($f,"\xEF\xBB\xBF");
fputcsv($f,['Ma TC','Nhom','Ky thuat','Ten kiem thu','Du lieu dau vao','Ket qua mong doi','Ket qua thuc te','Trang thai','Bang chung']);
foreach($results as $r) fputcsv($f,array_values($r)); fclose($f);
$log=$dir.'/tong_hop_29_'.$stamp.'.json';
file_put_contents($log,json_encode(['time'=>date('c'),'base'=>$base,'results'=>$results],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
$counts=array_count_values(array_column($results,'status'));
echo "\n=== TONG HOP MO RONG ===\n".json_encode($counts,JSON_UNESCAPED_UNICODE)."\nCSV: $csv\nJSON: $log\n";
