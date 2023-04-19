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

echo "si";

// PRIMERO BORRA TABLA DE CUPOS
$borrar = $mysql->Ejecutar_SQL("TRUNCATE TABLE notas_registros_cupos");

$bancos = $mysql->Consulta("SELECT * FROM notas_registros_bancos ORDER BY id_banco ASC");

$usuarios = $mysql->Consulta("SELECT * FROM usuarios WHERE (tipo=6) AND (estado=0)");

if (is_array($bancos)){
    if (count($bancos) > 0){
        foreach ($bancos as $linea_banco) {
            $id_banco = $linea_banco['id_banco'];

            $identificadores = $mysql->Consulta("SELECT banco, identificador, COUNT(identificador) AS total FROM notas_registros WHERE (banco=".$id_banco.") GROUP BY identificador ORDER BY identificador ASC");

            if (is_array($identificadores)){
                if (count($identificadores) > 0){
                    foreach ($identificadores as $linea_identificador) {
                        $id_identificador = $linea_identificador['identificador'];

                        foreach ($usuarios as $linea_usuario) {
                            $id_usuario = $linea_usuario['id_usuario'];
                            $agregar = $mysql->Ingreso("INSERT INTO notas_registros_cupos (id_usuario, id_banco, id_identificador, cupo, estado) VALUES (?,?,?,?,?)", array($id_usuario, $id_banco, $id_identificador, 0, 0));
                        }
                    }
                }
            }
        }
    }
}

// 

// $base_clientes = $mysql->Consulta("SELECT * FROM notas_registros WHERE (asignado>0)");

// $total_depurados = 0;

// if (is_array($base_clientes)){
//     if (count($base_clientes) > 0){
//         foreach ($base_clientes as $linea_cliente) {
//             $id_lista = $linea_cliente['id_lista'];

//             $contador = $mysql->Consulta_Unico("SELECT COUNT(id_log_llamada) AS total FROM notas_registros_llamadas WHERE id_lista=".$id_lista);

//             $total_llamadas = 0;
//             if (isset($contador['total'])){
//                 $total_llamadas = $contador['total'];
//             }


//             if ($total_llamadas < 2){ // si no tiene al menos dos registros que pueden ser inicio y terminacion de llamada, libera cliente
//                 $modificar = $mysql->Modificar("UPDATE notas_registros SET asignado=?, observaciones=?, llamado=?, orden=?, estado=? WHERE id_lista=?", array(0, "", 0, 0, 0, $id_lista));
//                 $total_depurados +=1;
//             }
//         }
//     }
// }

// echo "Total depurados = ".$total_depurados;