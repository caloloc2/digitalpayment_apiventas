<?php

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;

class RegistroCivil{

    private $authorization = null;

    function __construct(){
        $mysql = new Database("vtgsa_ventas");
        $configuracion = $mysql->Consulta_Unico("SELECT * FROM configuracion ORDER BY id_configuracion DESC LIMIT 1");

        if (isset($configuracion['id_configuracion'])){
            $this->authorization = $configuracion['tokenregistrocivil'];
        }
    }

    public function Consultar_Cedula($documento){
        $retorno = array("estado" => false);

        try{
            if ((!is_null($this->authorization)) && (!empty($this->authorization))){
                $url = "https://srienlinea.sri.gob.ec/sri-registro-civil-servicio-internet/rest/DatosRegistroCivil/obtenerPorNumeroIdentificacionConToken?numeroIdentificacion=".$documento;        

                $headers = array(
                    'Content-Type: application/json',
                    'Authorization: '.$this->authorization
                );
            
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                $html = curl_exec($ch);
                $data = curl_exec($ch);
                curl_close($ch);

                $respuestaregistro = json_decode($data, true);
            
                $retorno['data'] = json_decode($data, true);
                if (!isset($respuestaregistro['mensajeServidor'])){
                    $retorno['estado'] = true;
                }else{
                    $retorno['error'] = $respuestaregistro['mensajeServidor']['texto'];
                }
                
            }else{
                $retorno['error'] = "No se ha obtenido la llave para las consultas.";
            }            
        }catch(Exception $e){
            $retorno['error'] = $e->getMessage();
        }
        
        return $retorno;
    }    
}