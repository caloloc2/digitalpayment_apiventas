<?php 

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../../src/config/mysql/mysql.php';

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;

class CURLRequest{

    private $apiKey = "eyJhbGciOiJIUzI1NiJ9.eyJqdGkiOiJERUNMQVJBQ0lPTkVTIiwiaWF0IjoxNjcxMTIyMDc0LCJzdWIiOiJERUNMQVJBVE9SSUEgUFJFU0NSSVBDSU9OIEhFUkVOQ0lBIiwiZXhwIjoxNjcxMTIyNjc0fQ.-N94RkSm5mv2-eDjHROHvhDkxGIel46Vv28hCTx6l5k";

    public function setRequest($tipo, $documento){
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
            case 'establecimientos':
                $url = "https://srienlinea.sri.gob.ec/sri-catastro-sujeto-servicio-internet/rest/Establecimiento/consultarPorNumeroRuc?numeroRuc=".$documento;
        }

        $headers = array(
            'Content-Type: application/json',
            'Authorization: '.$this->apiKey
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

$mysql = new Database("vtgsa_ventas");

$banco = 25;
$identificador = "2022-12-12";

$consulta = $mysql->Consulta("SELECT * FROM notas_registros WHERE (banco=".$banco.") AND (identificador='".$identificador."') AND (ruc='') ORDER BY id_lista LIMIT 1");

if (is_array($consulta)){
    if (count($consulta) > 0){
        $request = new CURLRequest();

        foreach ($consulta as $contacto) {
            $id_lista = $contacto['id_lista'];
            $documento = $contacto['documento'];

            // saca informacion del ruc
            $infoRuc = json_decode($request->setRequest('ruc', $documento), true);
            $contribuyente = json_decode($request->setRequest('contribuyente', $documento), true);
            $establecimientos = json_decode($request->setRequest('establecimientos', $documento), true);

            $resultado = array(
                "ruc" => $infoRuc,
                "contribuyente" => $contribuyente,
                "establecimientos" => $establecimientos
            );

            $guardar = array(
                "ruc" => $infoRuc[0]['numeroRuc'],
                "cedula" => substr($infoRuc[0]['numeroRuc'], 0, 10),
                "razonSocial" => $infoRuc[0]['razonSocial'],
                "actividadContribuyente" => $infoRuc[0]['actividadContribuyente'],
                "fechaInicioActividades" => date("Y-m-d", strtotime($infoRuc[0]['informacionFechasContribuyente']['fechaInicioActividades'])), 
                "clasificacionMiPyme" => $contribuyente['clasificacionMiPyme'],
                "establecimientos" => $establecimientos
            );

            
        
            print_r($guardar);
        }
    }
}