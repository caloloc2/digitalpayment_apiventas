<?php 

/*
CODIGO PARA DEPURAR BASE DE CLIENTES
DONDE SE HAYA ASIGNADO PERO VALIDA SI TIENE REGISTRO DE LLAMADAS
CASO CONTRARIO LIBERA PARA NUEVA ASIGNACION
*/

require __DIR__.'/../../vendor/autoload.php';
// require __DIR__.'/../../src/config/mysql/meta.php';
require __DIR__.'/../../src/config/mysql/mysql.php';

$mysql = new Database('mvevip_crm');

$reservas = $mysql->Consulta("SELECT * FROM reservas ORDER BY id_reserva ASC");
$carpeta = __DIR__."../../../public/storage/";
echo $carpeta."\n";

if (is_array($reservas)){
    if (count($reservas) > 0){
        foreach ($reservas as $linea) {
            $id_reserva = $linea['id_reserva'];
            $id_ticket = $linea['id_ticket'];
            $checin = $linea['pickup_fecha'];
            $nombres = $linea['apellidos']." ".$linea['nombres'];
            
            $archivo1 = $linea['voucher_auto'];
            $archivo2 = $linea['recibo_auto'];
            $archivo3 = $linea['pagoimpuestos'];

            if (file_exists($carpeta.$archivo1)){
                echo $id_reserva."|".$checin."|".$nombres."|".$archivo1."|SI";
            }else{
                echo $id_reserva."|".$checin."|".$nombres."|".$archivo1."|NO";
            }

            echo "\n";
        }
    }
}