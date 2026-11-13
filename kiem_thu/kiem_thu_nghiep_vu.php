<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
$root = realpath(__DIR__.'/..');
$base = rtrim((string)($argv[1]??''),'/').'/';
$parts = parse_url($base);
if (!$root || !is_file($root.'/cart.php') || !$parts || !in_array($parts['host']??'', ['localhost','127.0.0.1'],true)) {
    fwrite(STDERR,"Usage: php kiem_thu_nghiep_vu.php http://localhost/QA_COPY/.../BANTHUOC_chot/\n"); exit(2);
}
require $root.'/config/db.php';
$currentDb = $conn->query('SELECT DATABASE() AS db')->fetch_assoc()['db'] ?? '';
if (!preg_match('/_test$/i',$currentDb)) {
    fwrite(STDERR,"STOP: DATABASE()='$currentDb'. Chỉ cho phép DB kết thúc bằng _test (ví dụ shop_db_test).\n"); exit(3);
}
// Ensure request target is the QA copy, not the production app.
if (stripos($base,'_QA/')===false) {
    fwrite(STDERR,"STOP: URL phải chứa _QA/ để tránh gửi request vào website chính.\n"); exit(3);
}
function http(string $path, ?array $post, string $cookie): array {
    global $base;
    $c=curl_init($base.ltrim($path,'/'));
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>20,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,
        CURLOPT_USERAGENT=>'VDP-Local-QA-Isolated/1.0']);
    if ($post!==null) {curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($post));}
    $raw=curl_exec($c);
    if($raw===false){$msg=curl_error($c);curl_close($c);throw new RuntimeException($msg);}
    $http=(int)curl_getinfo($c,CURLINFO_HTTP_CODE); $h=(int)curl_getinfo($c,CURLINFO_HEADER_SIZE);curl_close($c);
    return ['http'=>$http,'headers'=>substr($raw,0,$h),'body'=>substr($raw,$h)];
}
function stmtRows(mysqli $conn, string $sql, string $type, string $value): array {
    $stmt=$conn->prepare($sql);if(!$stmt) throw new RuntimeException($conn->error);
    $stmt->bind_param($type,$value);$stmt->execute();$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();return $rows;
}
$tests=[]; $ids=[];
function mark(string $id,string $name,string $input,string $expected,string $actual,string $status,string $proof=''):void{
    global $tests;
    $tests[]=compact('id','name','input','expected','actual','status','proof');
    printf("%-5s %-8s %s\n",$id,$status,$name);
}
$qaPrefix='QA_'.date('Ymd_His').'_'.bin2hex(random_bytes(3));
try {
    $r=$conn->query('SELECT id, name, price_new FROM products WHERE price_new > 0 ORDER BY id ASC LIMIT 1');
    $product=$r ? $r->fetch_assoc():null;
    if(!$product) throw new RuntimeException('Không có products.price_new > 0 trên DB TEST');
    $productId=(int)$product['id'];
    $firstPhone='090'.substr(preg_replace('/\D/','',$qaPrefix),-7); // can be shorter; identify order by exact phone in DB
    foreach(['BB09'=>'COD','BB10'=>'BANK'] as $case=>$method){
        $cookie=tempnam(sys_get_temp_dir(),'qa_cookie_');
        $phone='09'.str_pad((string)random_int(0,99999999),8,'0',STR_PAD_LEFT);
        $name=$qaPrefix.'_'.$method;
        try {
            $added=http('cart.php',['add_to_cart_id'=>(string)$productId],$cookie);
            if($added['http']!==200 || trim($added['body'])!=='1') throw new RuntimeException('Thêm giỏ không thành công: '.$added['http'].' '.substr($added['body'],0,80));
            $posted=http('cart.php',['checkout_action'=>'1','fullname'=>$name,'phone'=>$phone,
                'address'=>'Dia chi QA - khong giao hang','payment_method'=>$method],$cookie);
            $rows=stmtRows($conn,'SELECT id, total_amount, payment_method FROM orders WHERE phone=? ORDER BY id DESC','s',$phone);
            $order=$rows[0]??null;
            $details=$order ? stmtRows($conn,'SELECT product_id,quantity,price FROM order_details WHERE order_id=?','i',(string)$order['id']):[];
            $pass=$posted['http']===200 && $order && $order['payment_method']===$method && count($details)>0
                && (int)$details[0]['product_id']===$productId && (int)$details[0]['quantity']>=1;
            $actual='HTTP '.$posted['http'].'; order_id='.($order['id']??'none').'; details='.count($details).'; method='.($order['payment_method']??'none');
            mark($case,'Đặt hàng '.$method,'1 sản phẩm + '.$method.' + '.$phone,
                'orders và order_details có 1 đơn đúng phương thức, giữ cùng session', $actual,$pass?'PASS':'FAIL','SQL order + detail');
            if($order){ $ids[]=(int)$order['id']; }
            if($method==='COD') {
                $lookup=http('track-order.php',null,$cookie);
                $found=$order && $lookup['http']===200 && str_contains($lookup['body'],'Đơn hàng #'.(int)$order['id']);
                mark('BB12','Tra cứu đúng đơn của khách','GET track-order.php; cookie sau COD',
                     'Hiển thị đúng mã đơn vừa tạo','HTTP '.$lookup['http'].'; order_id='.($order['id']??'none').'; found='.($found?'yes':'no'),$found?'PASS':'FAIL','session cookie');
            }
        }catch(Throwable $e){
            mark($case,'Đặt hàng '.$method,'checkout '.$method,'Lưu đơn và chi tiết',$e->getMessage(),'BLOCKED');
            if($method==='COD')mark('BB12','Tra cứu đúng đơn của khách','COD session','Hiển thị đúng đơn','Chưa có đơn COD TEST','BLOCKED');
        }finally{@unlink($cookie);}
    }
    $cookie=tempnam(sys_get_temp_dir(),'qa_chat_');
    try {
        // Chat init creates a conversation in QA DB, not on production.
        $start=http('chat_live_api.php?action=init',null,$cookie);
        $js=json_decode($start['body'],true);
        $cid=(int)($js['conversation_id']??0);
        if($start['http']!==200 || !$cid) throw new RuntimeException('Chat init HTTP '.$start['http'].'; '.substr($start['body'],0,160));
        $message=$qaPrefix.'_chat_message';
        $send=http('chat_live_api.php?action=send',['message'=>$message],$cookie);
        $sj=json_decode($send['body'],true);
        $saved=stmtRows($conn,'SELECT id,message FROM chat_messages WHERE conversation_id=? ORDER BY id DESC LIMIT 10','i',(string)$cid);
        $poll=http('chat_live_api.php?action=poll&after=0',null,$cookie);
        $pj=json_decode($poll['body'],true);
        $inDb=false;foreach($saved as $item){if($item['message']===$message)$inDb=true;}
        $inPoll=false;foreach(($pj['messages']??[]) as $item){if(($item['message']??'')===$message)$inPoll=true;}
        $pass=$send['http']===200 && ($sj['ok']??false)===true && $inDb && $inPoll;
        mark('BB14','Chat trực tiếp init/send/poll','Chat token mới; '.$message,
            'Tạo conversation; lưu message; poll thấy message',
            'init='.$cid.'; sendHTTP='.$send['http'].'; DB='.($inDb?'yes':'no').'; poll='.($inPoll?'yes':'no'),$pass?'PASS':'FAIL','chat_conversations/chat_messages');
    }catch(Throwable $e){mark('BB14','Chat trực tiếp init/send/poll','QA chat','Lưu và đọc lại tin nhắn',$e->getMessage(),'BLOCKED');}
    finally{@unlink($cookie);}
}catch(Throwable $e){fwrite(STDERR,"Lỗi chuẩn bị DB QA: ".$e->getMessage()."\n");exit(4);}
$dir=__DIR__.'/ket_qua';if(!is_dir($dir))mkdir($dir,0775,true);
$stamp=date('Ymd_His');$csv=$dir.'/nghiep_vu_'.$stamp.'.csv';$f=fopen($csv,'wb');fwrite($f,"\xEF\xBB\xBF");
fputcsv($f,['Ma TC','Ten kiem thu','Dau vao','Ket qua mong doi','Ket qua thuc te','Trang thai','Bang chung']);
foreach($tests as $t)fputcsv($f,array_values($t));fclose($f);
file_put_contents($dir.'/nghiep_vu_'.$stamp.'.json',json_encode(['db'=>$currentDb,'url'=>$base,'test_orders'=>$ids,'results'=>$tests],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo 'TỔNG HỢP: '.json_encode(array_count_values(array_column($tests,'status')),JSON_UNESCAPED_UNICODE)."\nCSV: $csv\n";
echo 'Các đơn/hội thoại QA được giữ lại trong DB TEST để đối chiếu; không tự xóa bằng code.\n';
