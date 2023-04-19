<?php 

class Funciones{

    function obtenerEstadoCotizacion($estado){
        $retorno = array("estado" => false);

        try{
            $descripcion_estado = "";
            $color_estado = "";

            switch ($estado) {
                case 1:
                    $descripcion_estado = "Pendiente";
                    $color_estado = "warning";
                    break;
                case 2:
                    $descripcion_estado = "Autorizada";
                    $color_estado = "primary";
                    break;
                case 3:
                    $descripcion_estado = "Pagada";
                    $color_estado = "success";
                    break;
                case 4:
                    $descripcion_estado = "Anulada";
                    $color_estado = "danger";
                    break;
                default:
                    $descripcion_estado = "No especificado";
                    $color_estado = "secondary";
                    break;
            }

            $retorno['descripcion'] = $descripcion_estado;
            $retorno['color'] = $color_estado;

            $retorno['estado'] = true;
        }catch(Exception $e){
            $retorno['error'] = $e->getMessage();
        }
        
        return $retorno;
    }


    function obtenerEstadoAbono($estado){
        $retorno = array("estado" => false);

        try{
            $descripcion_estado = "";
            $color_estado = "";

            switch ($estado) {
                case 1:
                    $descripcion_estado = "Pendiente";
                    $color_estado = "warning";
                    break;
                case 2:
                    $descripcion_estado = "Autorizada";
                    $color_estado = "info";
                    break;
                case 3:
                    $descripcion_estado = "No Autorizada";
                    $color_estado = "danger";
                    break;
                case 4:
                    $descripcion_estado = "Eliminada";
                    $color_estado = "danger";
                    break;
                case 5:
                    $descripcion_estado = "Facturada";
                    $color_estado = "success";
                    break;
                case 5:
                    $descripcion_estado = "Anulada AE";
                    $color_estado = "danger";
                    break;
                default:
                    $descripcion_estado = "No especificado";
                    $color_estado = "secondary";
                    break;
            }

            $retorno['descripcion'] = $descripcion_estado;
            $retorno['color'] = $color_estado;

            $retorno['estado'] = true;
        }catch(Exception $e){
            $retorno['error'] = $e->getMessage();
        }
        
        return $retorno;
    }

}