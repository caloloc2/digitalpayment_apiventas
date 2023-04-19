<?php 

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../../src/config/functions/index.php';
require __DIR__.'/../../src/config/mysql/mysql.php';

$mysql = new Database("vtgsa_ventas");
$call = new CRM_API();

$respuesta['estado'] = false;

$consulta = $mysql->Consulta("SELECT * FROM notas_registros WHERE (comprobacion_llamada=0) AND (banco=2) AND ((identificador='30-05-2022-TIT') OR (identificador='30-05-2022-diners')) ORDER BY id_lista ASC LIMIT 10");

// $respuesta['consulta'] = $consulta;

if (is_array($consulta)){
    if (count($consulta) > 0){
        foreach ($consulta as $linea) {
            $id_lista = $linea['id_lista'];
            $celular = $linea['telefono'];
            $llamadas = $call->Obtener_Log_Llamadas($celular);
        
            $respuesta['dato'] = array(
                "id_lista" => (int) $id_lista,
                "celular" => $celular,
                "llamadas" => $llamadas
            );
        
            if ($llamadas["contenido"]['estado']){
                if (isset($llamadas['contenido']['llamadas'])){
                    $contador = count($llamadas['contenido']['llamadas']);
        
                    $nuevo_estado = 2;
                    if ($contador > 0){
                        $nuevo_estado = 1;
                    }
        
                    $modificar = $mysql->Modificar("UPDATE notas_registros SET comprobacion_llamada=? WHERE id_lista=?", array($nuevo_estado, $id_lista));
        
                    $respuesta['estado'] = true;
                }
            }
            sleep(1.5);
        }
    }
}

print_r($respuesta);