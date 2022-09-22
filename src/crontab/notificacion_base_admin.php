<?php 

/*
CODIGO PARA DEPURAR BASE DE CLIENTES
DONDE SE HAYA ASIGNADO PERO VALIDA SI TIENE REGISTRO DE LLAMADAS
CASO CONTRARIO LIBERA PARA NUEVA ASIGNACION
*/

require __DIR__.'/../../vendor/autoload.php';

require __DIR__.'/../../src/config/mysql/mysql.php';
require __DIR__.'/../../src/config/s3/s3class.php';
require __DIR__.'/../../src/config/files/filesclass.php';
require __DIR__.'/../../src/config/sri/index.php';
require __DIR__.'/../../src/config/twilio/whatsapp.php';
require __DIR__.'/../../src/config/functions/index.php';
require __DIR__.'/../../src/config/mail/mailer.php';
require __DIR__.'/../../src/config/placetopay/p2p.php';
require __DIR__.'/../../src/config/functions/auth.php';
require __DIR__.'/../../src/config/functions/valida_documentos.php';
require __DIR__.'/../../src/config/whatsapp/index.php';

$mysql = new Database('vtgsa_ventas');

$base_clientes = $mysql->Consulta_Unico("SELECT COUNT(id_lista) AS total FROM notas_registros WHERE (estado=0)");

$total_base_clientes = 0;
if (isset($base_clientes['total'])){
    $total_base_clientes = $base_clientes['total'];
}

if ($total_base_clientes <= 100){ // si existen menos de 100 enviar notificacion de whatsapp
    $whatsapp = new Whatsapp();

    $mensaje = "Existen ".$total_base_clientes." clientes en la base para los asesores. Favor recargar nuevos clientes";
    $notificar = $whatsapp->envio("0958978745", $mensaje);
    $notificar = $whatsapp->envio("0983178027", $mensaje);

    echo "Notificacion enviada.";
}

