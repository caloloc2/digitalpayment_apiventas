<?php 

require __DIR__.'/../../vendor/autoload.php'; 
require __DIR__.'/../../src/config/mysql/mysql.php'; 
require __DIR__.'/../../src/config/core/excel.php';

$mysql = new Database('vtgsa_ventas');

$fecha = date("Y-m-d");

$consulta = $mysql->Consulta("SELECT 
N.identificador, N.documento, N.nombres, N.telefono, N.observaciones, U.nombres AS asesor, E.descripcion AS estado, N.fecha_asignacion AS fechaAsignacion, 
N.fecha_ultima_contacto AS fechaUltimoContacto, V.precio, V.actividad
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

print_r($consulta);
