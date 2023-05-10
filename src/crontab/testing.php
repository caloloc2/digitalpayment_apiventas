<?php 

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../../src/config/mysql/mysql.php';
require __DIR__.'/../../src/config/core/sri.php';

$mysql = new Database('vtgsa_ventas');

try{
    $sri = new sri();

    $consulta = $sri->buscarCedulaRUC("0591737112001");

    print_r($consulta);

}catch(Exception $e){
    echo $e->getMessage();
}