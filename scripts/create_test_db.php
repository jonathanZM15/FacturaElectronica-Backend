<?php
$host='127.0.0.1';
$port=5432;
$user='postgres';
$pass='postgres';
$dbname='MaximoFactura_test';
try{
    $pdo=new PDO("pgsql:host=$host;port=$port",$user,$pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE \"$dbname\"");
    echo "DB created\n";
}catch(Exception $e){
    echo 'ERR: '.$e->getMessage()."\n";
}
