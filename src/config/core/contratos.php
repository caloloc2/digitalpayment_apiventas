<?php 

class Contratos{
    private $mysql = [];
    private $fechaCompleta = "";
    private $fecha = "";

    function __construct(){
        $this->mysql = new Database(CRM);
        $this->fecha = date("Y-m-d");
        $this->fechaCompleta = date("Y-m-d H:i:s");
    }

    function registroRapido($data){
        $retorno = array("estado" => false, 'input' => $data);

        try{
            $fecha = $this->fecha;
            if ((isset($data['fecha'])) && (!empty($data['fecha']))){
                $fecha = $data['fecha'];

                if ((isset($data['valor'])) && (!empty($data['valor']))){
                    $valor = $data['valor'];

                    if ((isset($data['asesor'])) && (!empty($data['asesor']))){
                        $asesor = $data['asesor'];
    
                        if ((isset($data['lider'])) && (!empty($data['lider']))){
                            $lider = $data['lider'];
        
                            $retorno['estado'] = true;
                        }else{
                            $retorno['error'] = "Debe especificar un líder para la venta.";
                        }
                    }else{
                        $retorno['error'] = "Debe especificar un asesor para la venta.";
                    }
                }else{
                    $retorno['error'] = "El valor debe ser mayor a cero.";
                }
            }
            
           
        }catch(Exception $e){
            $retorno['error'] = $e->getMessage();
        }

        return $retorno;
    }

    function guardarContrato($data){
        $retorno = array("estado" => false);

        try{

            $id_contrato = $this->mysql->Ingreso("INSERT INTO mve_contratos (idCotizacionCRM, fecha_venta, id_vendedor, id_lider, dummy, id_cliente, documento, nombres_apellidos, ciudad, referencia, aprobacion, valor_voucher, pago_parcial, pago_cuadro, es_cuota, tipo_venta, fecha_caducidad, tipo, id_destino, observaciones, observaciones_usuario, fecha_alta, fecha_modificacion, clausulas, observaciones_auditoria, estado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", array($idCotizacionCRM, $fecha_venta, $id_vendedor, $id_lider, $dummy, $id_cliente, $documento, $nombres_apellidos, $ciudad, $referencia, $aprobacion, $valor_voucher, $pago_parcial, $pago_cuadro, $es_cuota, $tipo_venta, $fecha_caducidad, $tipo, $id_destino, $observaciones, $observaciones_usuario, $fecha_alta, $fecha_modificacion, $clausulas, $observaciones_auditoria, $estado));


        }catch(Exception $e){
            $retorno['error'] = $e->getMessage();
        }

        return $retorno;
    }

    function contratoNuevo($data, $files){
        $retorno = array("estado" => false, "input" => $data, "files" => $files);

        try{

            if ((isset($data['pagos'])) && (!empty($data['pagos']))){
                $pagos = json_decode($data['pagos'], true);
                $retorno['pagos'] = $pagos;


                $listaPagos = [];
                if (is_array($pagos)){
                    if (count($pagos) > 0){
                        $i = 0;
                        foreach ($pagos as $linea) {

                            if ((isset($linea['referencia'])) && (!empty($linea['referencia']))){
                                if ((isset($linea['aprobacion'])) && (!empty($linea['aprobacion']))){
                                    if ((isset($linea['lote'])) && (!empty($linea['lote']))){
                                        if ((isset($linea['comision'])) && (!empty($linea['comision']))){
                                            if ((isset($linea['total'])) && (!empty($linea['total']))){
                                                if ((isset($linea['banco'])) && (!empty($linea['banco']))){
                                                    if ((isset($linea['primeros'])) && (!empty($linea['primeros']))){
                                                        if ((isset($linea['ultimos'])) && (!empty($linea['ultimos']))){

                                                            $referencia = $linea['referencia'];
                                                            $aprobacion = $linea['aprobacion'];
                                                            $lote = $linea['lote'];
                                                            $comision = $linea['comision'];
                                                            $total = $linea['total'];
                                                            $banco = $linea['banco'];
                                                            $primeros = $linea['primeros'];
                                                            $ultimos = $linea['ultimos'];
                            
                                                            $imagen = "";
                                                            if ((isset($data['nombre_archivo'.$i])) && (!empty($data['nombre_archivo'.$i]))){
                                                                $imagen = $data['nombre_archivo'.$i];

                                                                array_push($listaPagos, array(
                                                                    "referencia" => $referencia,
                                                                    "aprobacion" => $aprobacion,
                                                                    "lote" => $lote,
                                                                    "comision" => $comision,
                                                                    "total" => $total,
                                                                    "banco" => $banco,
                                                                    "primeros" => $primeros,
                                                                    "ultimos" => $ultimos,
                                                                    "imagen" => $imagen,
                                                                ));
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            
                        }   

                        
                        if (count($listaPagos) == count($pagos)){
                            $retorno['listaPagos'] = $listaPagos;

                            $retorno['estado'] = true;
                        }else{
                            $retorno['error'] = "El detalle de pagos tiene valores no permitidos o erróneos.";
                        }

                    }else{
                        $retorno['error'] = "Debe incluir un pago para poder emitir un contrato.";
                    }
                }
                
            }else{
                $retorno['error'] = "Debe incluir al menos un pago al contrato.";
            }

           
        }catch(Exception $e){
            $retorno['error'] = $e->getMessage();
        }
        
        return $retorno;
    }


}