<?php 

/*
CODIGO PARA DEPURAR BASE DE CLIENTES
DONDE SE HAYA ASIGNADO PERO VALIDA SI TIENE REGISTRO DE LLAMADAS
CASO CONTRARIO LIBERA PARA NUEVA ASIGNACION
*/

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

$mysql = new Database('vtgsa_ventas');

$base_clientes = $mysql->Consulta("SELECT * FROM notas_registros WHERE (asignado>0)");

$total_depurados = 0;

if (is_array($base_clientes)){
    if (count($base_clientes) > 0){
        foreach ($base_clientes as $linea_cliente) {
            $id_lista = $linea_cliente['id_lista'];

            $contador = $mysql->Consulta_Unico("SELECT COUNT(id_log_llamada) AS total FROM notas_registros_llamadas WHERE id_lista=".$id_lista);

            $total_llamadas = 0;
            if (isset($contador['total'])){
                $total_llamadas = $contador['total'];
            }


            if ($total_llamadas < 2){ // si no tiene al menos dos registros que pueden ser inicio y terminacion de llamada, libera cliente
                $modificar = $mysql->Modificar("UPDATE notas_registros SET asignado=?, observaciones=?, llamado=?, orden=?, estado=? WHERE id_lista=?", array(0, "", 0, 0, 0, $id_lista));
                $total_depurados +=1;
            }
        }
    }
}

echo "Total depurados = ".$total_depurados;