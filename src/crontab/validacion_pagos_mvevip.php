<?php 
header('Content-Type: application/json; charset=utf-8');

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../../src/config/mysql/mysql.php';
require __DIR__.'/../../src/config/functions/index.php';
require __DIR__.'/../../src/config/placetopay/p2p.php';

$funciones = new Functions();
$mysql = new Database('vtgsa_ventas');
$PLACETOPAY = "test";

try{
    
    $intentos_pago = $mysql->Consulta("SELECT * FROM pagos_p2p WHERE procesado=0 ORDER BY id_pago_p2p DESC LIMIT 1");

    if (is_array($intentos_pago)){
        if (count($intentos_pago) > 0){
            foreach ($intentos_pago as $linea) {
                $requestId = $linea['requestId'];
                $referencia = $linea['reference'];
                $tipo_venta = $linea['tipo_venta'];

                $p2p_unico = new PlacetoPay($PLACETOPAY, "mvevip_unico");
                $p2p_suscripcion = new PlacetoPay($PLACETOPAY, "mvevip_suscription");

                if ($tipo_venta == 0){ // PAGOS UNICOS
                    $verifica = $p2p_unico->check_status($requestId);
                    // print_r($verifica);

                    $estado_proceso = $verifica['status']['status'];
                    
                    if ($estado_proceso == "APPROVED"){
                        $payment = $verifica['payment'];
                        print_r($payment);
                        $estado_pago = $payment['status']['status'];
                    }
                    
                }else if ($tipo_venta == 1){ // PAGOS SUSCRIPCIONES PARA TOKEN
                    $verifica = $p2p_suscripcion->check_status($requestId);
                    print_r($verifica);
                }
            }
        }
    }

}catch(Exception $e){
    echo $e->getMessage();
}