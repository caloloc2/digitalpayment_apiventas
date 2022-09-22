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
$p2p = new PlacetoPay('live');

try{
    if ($p2p->get_state()['estado']){
        // primero busca compras pendientes de pago
        $busca_compra_paquete = $mysql->Consulta("SELECT * FROM clientes_compras WHERE estado=0");


        // de cada compra pendiente, obteiene la primera cuota pendiente
        if (is_array($busca_compra_paquete)){
            if (count($busca_compra_paquete) > 0){
                foreach ($busca_compra_paquete as $compra) {
                    $id_compra = $compra['id_compra'];                

                    $busca_cuota_pendiente = $mysql->Consulta_Unico("SELECT * FROM clientes_cuotas WHERE (id_compra=".$id_compra.") AND (estado=0) ORDER BY id_cuota ASC LIMIT 1");

                    if (isset($busca_cuota_pendiente['id_compra'])){
                        $requestId = $busca_cuota_pendiente['requestId'];
                        $id_cuota = $busca_cuota_pendiente['id_cuota'];

                        if ($requestId != ""){
                            $consulta_pago = $p2p->check_status($requestId);                        
                            
                            $base64 = base64_encode(json_encode($consulta_pago));

                            $status = $consulta_pago['status']['status'];
                            $message = $consulta_pago['status']['message'];
                            $date = $consulta_pago['status']['date'];

                            $requestId = "";
                            $status_pago = "";
                            $date_pago = "";
                            $message_pago = "";
                            $internalReference = "";
                            $paymentMethodName = "";
                            $authorization = "";
                            $reference_cuota = "";
                            $receipt = "";
                            $estado_cuota = 0;
                            
                            $requestId = $consulta_pago['requestId'];

                            $pago = null;
                            $status_pago = "";
                            $date_pago = "";
                            $message_pago = "";

                            if (isset($consulta_pago['payment'][0])){
                                $pago = $consulta_pago['payment'][0];
                                $status_pago = $pago['status']['status'];
                                $date_pago = $pago['status']['date'];
                                $message_pago = $pago['status']['message'];
                            }                  

                            switch ($status) {                                    
                                case 'APPROVED':
                                    $internalReference = $pago['internalReference'];
                                    $paymentMethodName = $pago['paymentMethodName'];
                                    $authorization = $pago['authorization'];
                                    $reference_cuota = $pago['reference'];
                                    $receipt = $pago['receipt'];
                
                                    $estado_cuota = 1;
                                    
                                    break;
                                case "REJECTED":
                                    $status_pago = $consulta_pago['status']['status'];
                                    $date_pago = $consulta_pago['status']['date'];
                                    $message_pago = $consulta_pago['status']['message'];
                                    $estado_cuota = 5;

                                    $respuesta['error'] = $message;
                                    break;
                                case 'FAILED':
                                    $status_pago = $consulta_pago['status']['status'];
                                    $date_pago = $consulta_pago['status']['date'];
                                    $message_pago = $consulta_pago['status']['message'];
                                    $estado_cuota = 5;

                                    $respuesta['error'] = $message;
                                    break;
                                case 'PENDING':
                                    $status_pago = $consulta_pago['status']['status'];
                                    $date_pago = $consulta_pago['status']['date'];
                                    $message_pago = $consulta_pago['status']['message'];
                                    $estado_cuota = 0;

                                    $respuesta['error'] = $message;
                                    break;
                                case 'PENDING_VALIDATION':
                                    $status_pago = $consulta_pago['status']['status'];
                                    $date_pago = $consulta_pago['status']['date'];
                                    $message_pago = $consulta_pago['status']['message'];
                                    $estado_cuota = 0;

                                    $respuesta['error'] = $message;
                                    break;                                    
                            }        

                            $actualiza_cuota = $mysql->Modificar("UPDATE clientes_cuotas SET requestId=?, status=?, date_status=?, message_status=?, internalReference=?, paymentMethodName=?, authorization=?, reference=?, receipt=?, base64=?, estado=? WHERE id_cuota=?", array($requestId, $status_pago, $date_pago, $message_pago, $internalReference, $paymentMethodName, $authorization, $reference_cuota, $receipt, $base64, $estado_cuota, $id_cuota));

                            echo $internalReference;
                        }else{
                            echo "No hay pagos por verificar";
                        }                    
                        echo $requestId;
                    }                
                }
            }
        }
    }else{
        echo $p2p->get_state()['error'];
    }
    

}catch(Exception $e){
    echo $e->getMessage();
}