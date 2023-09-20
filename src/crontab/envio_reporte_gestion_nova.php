<?php 

require __DIR__.'/../../vendor/autoload.php'; 
require __DIR__.'/../../src/config/mysql/mysql.php'; 
require __DIR__.'/../../src/config/core/excel.php';
require __DIR__.'/../../src/config/core/sendinblue.php';

define("DATABASE", "vtgsa_ventas");

$mysql = new Database(DATABASE);

$fecha = date("Y-m-12");

$consulta = $mysql->Consulta("SELECT 
N.identificador, N.documento, N.nombres, N.telefono, U.nombres AS asesor, E.descripcion AS estado, T.motivo, N.fecha_asignacion AS fechaAsignacion, 
N.fecha_ultima_contacto AS fechaUltimoContacto, V.precio, V.actividad, N.observaciones, N.estado AS id_estado
FROM notas_registros N
LEFT JOIN usuarios U
ON (N.asignado=U.id_usuario)
LEFT JOIN notas_registros_bancos B
ON N.banco = B.id_banco
LEFT JOIN notas_registros_estados E
ON N.estado = E.id_estados
LEFT JOIN notas_registros_no_interesados T
ON N.impreso = T.id
LEFT JOIN notas_registros_nova_valores V
ON N.plan_usado = V.id_valor
WHERE 
(N.banco=30)
AND
(DATE(N.fecha_ultima_contacto) BETWEEN '".$fecha."' AND '".$fecha."')
ORDER BY N.fecha_ultima_contacto ASC, N.estado DESC");

if (is_array($consulta)){
    if (count($consulta) > 0){
        $excel = new excel();

        $titulos = ["BASE", "DOCUMENTO", "ESTABLECIMIENTO", "TELEFONO", "ASESOR", "ESTADO", "MOTIVO", "FECHA ASIGNADO", "ULTIMO CONTACTO", "VALOR", "TIPO SEGURO", "OBSERVACIONES"];

        $registros = array();

        foreach ($consulta as $linea) {
            $nodesea = "";
            if ($linea['id_estado'] == 5){
                $nodesea = $linea['motivo'];
            }

            array_push($registros, array(
                $linea['identificador'],
                $linea['documento'],
                $linea['nombres'],
                $linea['telefono'],
                $linea['asesor'],
                $linea['estado'],
                $nodesea,
                $linea['fechaAsignacion'],
                $linea['fechaUltimoContacto'],
                $linea['precio'],
                $linea['actividad'],
                $linea['observaciones']
            ));
        } 
        
        $archivosGenerados = $excel->createExcel($titulos, $registros, "GESTION_NOVA_");

        // envio de archivo a correo
        $sendinblue = new sendinblue();
        $url = "https://api.digitalpaymentnow.com/tmp";
        // $envio = $sendinblue->envioMail(array(
        //     "to" => [array(
        //         "email" => "calolomino@gmail.com",
        //         "name" => "CARLOS MINO"
        //     )], 
        //     // "bcc" => [ 
        //     //     array(
        //     //         "email" => "soporte@digitalpaymentnow.com",
        //     //         "name" => "Ing. Carlos Mino"
        //     //     ),
        //     //     array(
        //     //         "email" => "operaciones@digitalpaymentnow.com",
        //     //         "name" => "Fernanda Ortiz"
        //     //     ),
        //     // ],
        //     "replyTo" => array(
        //         "email" => "operaciones@digitalpaymentnow.com",
        //         "name" => "Fernanda Ortiz"
        //     ),
        //     "templateId" => 6,
        //     "params" => array(
        //         "producto" => "Seguros NOVA",
        //         "fecha" => $fecha,
        //         "dia" => $fecha,
        //         "base" => "Seguros NOVA"
        //     ),
        //     "attachment" => $url."/".$archivosGenerados['filename']
        // ));
        echo $url."/".$archivosGenerados['filename'];
    }
}

// print_r($consulta);
