<?php
$c = @mysqli_connect('127.0.0.1', 'root', '', '', 3306);
if (! $c) {
    echo 'CONNECT FAIL: ' . mysqli_connect_error() . "\n";
    exit(1);
}
$sql = 'CREATE DATABASE IF NOT EXISTS `motovault` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
if (mysqli_query($c, $sql)) {
    echo "DATABASE motovault READY\n";
    echo 'SERVER: ' . mysqli_get_server_info($c) . "\n";
    exit(0);
}
echo 'CREATE FAIL: ' . mysqli_error($c) . "\n";
exit(1);
