<?php

if(isset($_FILES['upload']['name'])){

    $file = $_FILES['upload']['name'];
    $tmp = $_FILES['upload']['tmp_name'];

    $path = "images/".time()."_".$file;

    move_uploaded_file($tmp,$path);

    $url = $path;

    $funcNum = $_GET['CKEditorFuncNum'];

    echo "<script>
        window.parent.CKEDITOR.tools.callFunction($funcNum, '$url', 'Upload thành công');
    </script>";
}