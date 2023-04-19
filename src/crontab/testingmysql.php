<?php 

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../../src/config/mysql/mysql.php';

$mysql = new Database("digitalpayment");

$consulta = $mysql->Consulta("SELECT * FROM notas_registros ORDER BY id_lista DESC LIMIT 10");

print_r($consulta);