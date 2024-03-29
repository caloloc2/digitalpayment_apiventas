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

    private $apiKey = null;

    function __construct($llave){
        $this->apiKey = $llave;
    }

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

$buscaLlave = $mysql->Consulta_Unico("SELECT tokenregistrocivil FROM configuracion WHERE id_configuracion=1 ORDER BY id_configuracion DESC LIMIT 1");

if ((isset($buscaLlave['tokenregistrocivil'])) && (!empty($buscaLlave['tokenregistrocivil']))){

    $llave = $buscaLlave['tokenregistrocivil'];

    $banco = 30;
    $identificador = "2023-05-02";

    $consulta = $mysql->Consulta("SELECT
    id_lista, documento, validadoSRI
    FROM notas_registros
    WHERE (banco=29) AND (validadoSRI=0)
    AND ((estado=0) OR (estado=3) OR (estado=6) OR (estado=10) OR (estado=27) OR (estado=28) OR (estado=29) OR (estado=30) OR (estado=31) OR (estado=40) OR (estado=34) OR (estado=1))");

    if (is_array($consulta)){
        if (count($consulta) > 0){
            $request = new CURLRequest($llave);

            foreach ($consulta as $contacto) {
                $id_lista = $contacto['id_lista'];
                $documento = $contacto['documento'];

                // saca informacion del ruc
                $infoRuc = json_decode($request->setRequest('ruc', $documento), true);
                $contribuyente = json_decode($request->setRequest('contribuyente', $documento), true);
                $establecimientos = json_decode($request->setRequest('establecimientos', $documento), true);

                $resultado = array(
                    "ruc" => $infoRuc, 
                    "establecimientos" => $establecimientos
                );

                if (isset($infoRuc['mensajeServidor'])){
                    echo $infoRuc['mensajeServidor']['texto'];

                    break;
                }else{
                    $guardar = array(
                        "ruc" => $infoRuc[0]['numeroRuc'],
                        "cedula" => substr($infoRuc[0]['numeroRuc'], 0, 10),
                        "razonSocial" => $infoRuc[0]['razonSocial'],
                        "estadoContribuyenteRuc" => $infoRuc[0]['estadoContribuyenteRuc'],
                        "actividadContribuyente" => $infoRuc[0]['actividadEconomicaPrincipal'],
                        "fechaInicioActividades" => $infoRuc[0]['informacionFechasContribuyente']['fechaInicioActividades'],  
                        "representantesLegales" => $infoRuc[0]['representantesLegales'][0],
                        "establecimientos" => $establecimientos
                    );

                    $actualizar = $mysql->Modificar("UPDATE notas_registros SET fechaInicioActividades=? WHERE id_lista=?", array(
                        $guardar['fechaInicioActividades'],
                        $id_lista
                    ));

                    $actualizar = $mysql->Modificar("UPDATE notas_registros SET ruc=?, cedula=?, razonSocial=?, estadoContribuyenteRuc=?, actividadContribuyente=?, fechaInicioActividades=?, docRepresentanteLegal=?, representanteLegal=? WHERE id_lista=?", array(
                        $guardar['ruc'],
                        $guardar['cedula'],
                        $guardar['razonSocial'],
                        $guardar['estadoContribuyenteRuc'],
                        $guardar['actividadContribuyente'],
                        $guardar['fechaInicioActividades'],
                        $guardar['representantesLegales']['identificacion'],
                        $guardar['representantesLegales']['nombre'],
                        $id_lista
                    ));

                    if (is_array($guardar['establecimientos'])){
                        if (count($guardar['establecimientos']) > 0){
                            foreach ($guardar['establecimientos'] as $establecimiento) {
                                
                                $id_establecimiento = $mysql->Ingreso("INSERT INTO notas_registros_establecimientos (id_lista, nombreComercial, tipoEstablecimiento, direccionCompleta, estado, numeroEstablecimiento) VALUES (?,?,?,?,?,?)", array($id_lista, $establecimiento['nombreFantasiaComercial'], $establecimiento['tipoEstablecimiento'], $establecimiento['direccionCompleta'], $establecimiento['estado'], $establecimiento['numeroEstablecimiento']));

                            }
                        }
                    }
                    
                
                    print_r($resultado);
                    $actualizar = $mysql->Modificar("UPDATE notas_registros SET validadoSRI=? WHERE id_lista=?", array(1, $id_lista));
                }

                
            }
        }
    }

}else{
    echo "No se encontro la llave del SRI.";
}

