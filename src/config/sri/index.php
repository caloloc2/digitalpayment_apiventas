<?php

class CURLRequest{

    public function setRequest($tipo, $documento, $apikey){
        $url = "";

        switch ($tipo) {
            case 'cedula':
                $url = "https://srienlinea.sri.gob.ec/sri-registro-civil-servicio-internet/rest/DatosRegistroCivil/obtenerPorNumeroIdentificacionConToken?numeroIdentificacion=".$documento;
                break;
            case 'ruc':
                $url = "https://srienlinea.sri.gob.ec/sri-catastro-sujeto-servicio-internet/rest/ConsolidadoContribuyente/obtenerPorNumerosRuc?&ruc=".$documento;
                break;
            case 'contribuyente':
                $url = "https://srienlinea.sri.gob.ec/sri-catastro-sujeto-servicio-internet/rest/ClasificacionMipyme/consultarPorNumeroRuc?numeroRuc=".$documento;
                break;
            case 'agente_retencion':
                $url = "https://srienlinea.sri.gob.ec/sri-catastro-sujeto-servicio-internet/rest/ConsolidadoContribuyente/esAgenteRetencion?numeroRuc=".$documento;
                break;
            case 'existeRuc':
                $url = "https://srienlinea.sri.gob.ec/sri-catastro-sujeto-servicio-internet/rest/ConsolidadoContribuyente/existePorNumeroRuc?numeroRuc=".$documento;
                break;
        }

        $headers = array(
            'Content-Type: application/json',
            'Authorization: '.$apikey[0]
        );
    
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $html = curl_exec($ch);
        $data = curl_exec($ch);
        curl_close($ch);
    
        return $data;        
    }
}

class SRI{
    
    private $apikey;  

    function getApiKey(){
        return $this->apikey;
    }

    function setApiKey($key){
        $this->apikey = $key;
    }

    function consultaCedula($documento){
        
        $consulta = new CURLRequest();
        $valor = $consulta->setRequest('cedula', $documento, $this->apikey);
        
        return json_decode($valor);
    }

    function consultaRUC($documento){
        
        $consulta = new CURLRequest();
        $valor = $consulta->setRequest('ruc', $documento, $this->apikey);
        
        return json_decode($valor);
    }

    function consultaTipoContribuyenteRUC($documento){
        
        $consulta = new CURLRequest();
        $valor = $consulta->setRequest('contribuyente', $documento, $this->apikey);
        
        return json_decode($valor);
    }

    function consultaAgenteRetencionRUC($documento){
        
        $consulta = new CURLRequest();
        $valor = $consulta->setRequest('agente_retencion', $documento, $this->apikey);
        
        return json_decode($valor);
    }

    function existeRUC($documento){
        
        $consulta = new CURLRequest();
        $valor = $consulta->setRequest('existeRuc', $documento, $this->apikey);
        
        return json_decode($valor);
    }
}