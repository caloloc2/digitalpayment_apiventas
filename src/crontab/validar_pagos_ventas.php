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

$funciones = new Functions();
$mysql = new Database('vtgsa_ventas');
$p2p = new PlacetoPay("live");
                    
try{
    if ($p2p->get_state()['estado']){
        // primero busca los registros de los intentos de pagos
        $busca_intentos = $mysql->Consulta("SELECT id_pago_p2p, valor, reference, requestId FROM pagos_p2p WHERE procesado=0 ORDER BY id_pago_p2p DESC LIMIT 15");
        
        if (is_array($busca_intentos)){
            if (count($busca_intentos) > 0){
                foreach ($busca_intentos as $compra) {
                    $requestId = $compra['requestId'];
                    $id_pago_p2p = $compra['id_pago_p2p'];

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


                                // Datos del pago para guardar

                                $estado = $consulta_pago['status'];
                                $status_process = $estado['status'];
                                $reason_process = $estado['reason'];
                                $message_process = $estado['message'];
                                $date_process = $estado['date'];
                
                                $requestId = $consulta_pago['requestId'];
                
                                // Datos del pagado
                                $buyer = $consulta_pago['request']['buyer'];
                
                                $document = $buyer['document'];
                                $name = $buyer['name'];
                                $surname = $buyer['surname'];
                                $email = $buyer['email'];
                                $mobile = $buyer['mobile'];
                
                                // Datos de la compra
                                $compra = $consulta_pago['request']['payment'];
                
                                $description = $compra['description'];
                                $tax = $compra['amount']['taxes'][0]['amount'];
                                $sub_doce = $compra['amount']['taxes'][0]['base'];
                                $subtotal = $compra['amount']['details'][1]['amount'];
                                $currency = $compra['amount']['currency'];
                                $total = $compra['amount']['total'];
                
                                $campos = $consulta_pago['request']['fields'];
                                $url_process = $campos[0]['value'];
                
                
                                // Datos del Pago
                                $pago = $consulta_pago['payment'][0];
                
                                $status_pago = $pago['status']['status'];
                                $reason_pago = $pago['status']['reason'];
                                $message_pago = $pago['status']['message'];
                                $date_pago = $pago['status']['date'];
                
                                $internalReference = $pago['internalReference'];
                                $paymentMethod = $pago['paymentMethod'];
                                $paymentMethodName = $pago['paymentMethodName'];
                                $issuerName = $pago['issuerName'];
                                $authorization = $pago['authorization'];
                                $reference = $pago['reference'];
                                $receipt = $pago['receipt'];
                
                                $primeros_digitos = $pago['processorFields'][7]['value'];
                                $ultimos_digitos = $pago['processorFields'][9]['value'];
                                $expiracion = $pago['processorFields'][8]['value'];

                                $guarda_registro = $mysql->Ingreso("INSERT INTO pagos_p2p_confirmacion (status_process, reason_process, message_process, date_process, requestId, document, name, surname, email, mobile, reference, description, tax, sub_doce, subtotal, total, currency, url_process, status_pago, reason_pago, message_pago, date_pago, internalReference, paymentMethod, paymentMethodName, issuerName, authorization, receipt, primeros_digitos, ultimos_digitos, expiracion, recap, estado)  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", array($status_process, $reason_process, $message_process, $date_process, $requestId, $document, $name, $surname, $email, $mobile, $reference, $description, $tax, $sub_doce, $subtotal, $total, $currency, $url_process, $status_pago, $reason_pago, $message_pago, $date_pago, $internalReference, $paymentMethod, $paymentMethodName, $issuerName, $authorization, $receipt, $primeros_digitos, $ultimos_digitos, $expiracion, 0, 0));
                                
                                break;
                            case "REJECTED":
                                $status_pago = $consulta_pago['status']['status'];
                                $date_pago = $consulta_pago['status']['date'];
                                $message_pago = $consulta_pago['status']['message'];
                                $estado_cuota = 5;

                                break;
                            case 'FAILED':
                                $status_pago = $consulta_pago['status']['status'];
                                $date_pago = $consulta_pago['status']['date'];
                                $message_pago = $consulta_pago['status']['message'];
                                $estado_cuota = 5;

                                break;
                            case 'PENDING':
                                $status_pago = $consulta_pago['status']['status'];
                                $date_pago = $consulta_pago['status']['date'];
                                $message_pago = $consulta_pago['status']['message'];
                                $estado_cuota = 0;

                                break;
                            case 'PENDING_VALIDATION':
                                $status_pago = $consulta_pago['status']['status'];
                                $date_pago = $consulta_pago['status']['date'];
                                $message_pago = $consulta_pago['status']['message'];
                                $estado_cuota = 0;
                                
                                break;                                    
                        }        
                                             
                        $actualiza = $mysql->Modificar("UPDATE pagos_p2p SET procesado=? WHERE id_pago_p2p=?", array($estado_cuota, $id_pago_p2p));                     
                        
                    }                                     
                    sleep(2.5);
                }
            }
        }
    }else{
        echo $p2p->get_state()['error'];
    }
    

}catch(Exception $e){
    echo $e->getMessage();
}