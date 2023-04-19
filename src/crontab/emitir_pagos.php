<?php 
require __DIR__.'/../../vendor/autoload.php';
// require __DIR__.'/../../src/config/mysql/meta.php';
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

// define("URL_RETURN", "http://p2pdev.mvevip.com");
define("URL_RETURN", "https://app.mvevip.com");

$funciones = new Functions();
$mysql = new Database('vtgsa_ventas');

try{
    // primero busca compras pendientes de pago
    $busca_compra_paquete = $mysql->Consulta("SELECT * FROM clientes_compras WHERE estado=0");


    // de cada compra pendiente, obteiene la primera cuota pendiente
    if (is_array($busca_compra_paquete)){
        if (count($busca_compra_paquete) > 0){
            foreach ($busca_compra_paquete as $compra) {
                $id_compra = $compra['id_compra'];                

                $siguiente_cuota = $mysql->Consulta_Unico("SELECT * FROM clientes_cuotas WHERE (id_compra=".$id_compra.") AND (estado=0) ORDER BY id_cuota ASC LIMIT 1");

                if (isset($siguiente_cuota['id_compra'])){
                    $id_cliente = $siguiente_cuota['id_cliente'];
                    $id_paquete = $siguiente_cuota['id_paquete'];

                    $iniciar_cobro = $funciones->Realizar_Cobro($id_cliente, $id_paquete);                    

                    if ($iniciar_cobro['estado']){
                        echo $iniciar_cobro['cuota'];
                    }else{
                        echo $iniciar_cobro['error'];
                    }
                }                
            }
        }
    }

}catch(Exception $e){
    echo $e->getMessage();
}