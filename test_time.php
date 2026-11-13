<?php
require_once "config/db.php";

$result = $conn->query("SELECT NOW() as time");

$row = $result->fetch_assoc();

echo $row['time'];