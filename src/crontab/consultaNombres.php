<?php 

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../../src/config/mysql/mysql.php';

$mysql = new Database("vtgsa_ventas");
            
$documentos = $mysql->Consulta("SELECT * FROM consulta_documentos WHERE nombres IS NOT NULL");

echo count($documentos);

if (is_array($documentos)){
    if (count($documentos) > 0){
        foreach ($documentos as $linea) {
            $id_consulta = $linea['id_consulta'];
            $documento = $linea['documento'];

            $consulta = $mysql->Consulta_Unico('SELECT * FROM notas_registros WHERE documento="'.$documento.'"');
            if (isset($consulta['id_lista'])){
                $nombres = $consulta['telefono'];
                $modifica = $mysql->Modificar("UPDATE consulta_documentos SET telefono=? WHERE id_consulta=?", array($nombres, $id_consulta));
            }
        }
    }
}
echo "ok";