<?php 

class Estados{

    function __construct(){

    }

    function estadoCliente($estado){
        $retorno = array("estado" => false);

        try{
            $descripcion_estado = "";
            $color_estado = "";

            switch ($estado) {
                case 77:
                    $descripcion_estado = "No Volver a Contactar";
                    $color_estado = "danger";
                    break;
                case 78:
                    $descripcion_estado = "Activo";
                    $color_estado = "info";
                    break;
                case 79:
                    $descripcion_estado = "No Contactado";
                    $color_estado = "warning";
                    break;
                case 80:
                    $descripcion_estado = "Mail Enviado";
                    $color_estado = "primary";
                    break;
                case 81:
                    $descripcion_estado = "Facturado";
                    $color_estado = "success";
                    break;
                default:
                    $descripcion_estado = "Sin Estado";
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

    function estadoCotizacion($estado){
        $retorno = array("estado" => false);

        try{
            $descripcion_estado = "";
            $color_estado = "";

            switch ($estado) {
                case 1:
                    $descripcion_estado = "Ingresada";
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
                    $descripcion_estado = "Sin Estado";
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

    function estadoAbono($estado){
        $retorno = array("estado" => false);


        try{
            $descripcion_estado = "";
            $color_estado = "";

            switch ($estado) {
                case 1:
                    $descripcion_estado = "Ingresada";
                    $color_estado = "warning";
                    break;
                case 2:
                    $descripcion_estado = "Autorizada";
                    $color_estado = "primary";
                    break;
                case 3:
                    $descripcion_estado = "No Autorizada";
                    $color_estado = "danger";
                    break;
                case 4:
                    $descripcion_estado = "Anulada";
                    $color_estado = "danger";
                    break;
                case 5:
                    $descripcion_estado = "Facturado";
                    $color_estado = "success";
                    break;
                case 6:
                    $descripcion_estado = "Anulada AM";
                    $color_estado = "danger";
                    break;
                default:
                    $descripcion_estado = "Sin Estado";
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

    function estadoAsesor($estado){
        $retorno = array("estado" => false);


        try{
            $descripcion_estado = "";
            $color_estado = "";

            switch ($estado) {
                case 1:
                    $descripcion_estado = "Activo";
                    $color_estado = "success";
                    break;
                case 0:
                    $descripcion_estado = "Inhabilitado";
                    $color_estado = "secondary";
                    break;
                default:
                    $descripcion_estado = "Sin Estado";
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