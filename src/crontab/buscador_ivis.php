<?php 

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../../src/config/mysql/mysql.php';

$mysql = new Database("mvevip_crm");

$consulta = $mysql->Consulta("SELECT * FROM reservas WHERE id_proveedor_transfer=75");

if (is_array($consulta)){
    if (count($consulta) > 0){
        foreach ($consulta as $linea) {
            $id_reserva = $linea['id_reserva'];
            $num_reserva_transfer = $linea['num_reserva_transfer'];
            $id_forma_pago_transfer = $linea['id_forma_pago_transfer'];
            $id_proveedor_transfer = $linea['id_proveedor_transfer'];
            $fecha_factura_transfer = $linea['fecha_factura_transfer'];
            $num_factura_transfer = $linea['num_factura_transfer'];
            $valor_factura_transfer = $linea['valor_factura_transfer'];
            $costo_transfer = floatval($linea['costo_transfer']);

            $restado = 40;

            $ingreso = $mysql->Ingreso("INSERT INTO reservas_ivis (id_reserva, num_reserva_transfer, costo_transfer, id_proveedor_transfer, id_forma_pago_transfer, fecha_factura_transfer, num_factura_transfer, valor_factura_transfer) VALUES (?,?,?,?,?,?,?,?)", array($id_reserva, $num_reserva_transfer, $restado, $id_proveedor_transfer, $id_forma_pago_transfer, $fecha_factura_transfer, $num_factura_transfer, $valor_factura_transfer));
        }
    }
}