<?php 

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;

class sri{

    public $options = [
        'body'    => "",
        'headers' => [
            "Content-Type" => "application/json",
        ]
    ];

    public $cabecera = [
        'headers' => [ 'Content-Type' => 'application/json', 'Authorization' => ""],
        'base_uri' => "https://api.nibemi.ec", 
        'timeout'  => 0,
    ];

    function __construct(){ 

    } 

    function buscarCedulaRUC($documento){
        $retorno = array("estado" => false);

        try{

            $client = new Client($this->cabecera);
        
            $response = $client->get('/api/v1/sri/ruc/'.$documento);

            $consulta = json_decode($response->getBody(), true);

            if ($consulta['estado']){
                $retorno['consulta'] = $consulta['consulta'];
                $retorno['estado'] = true;  
            }else{
                $retorno['error'] = $consulta['error'];
            }

            // $retorno['url'] = array(
            //     "cabecera" => $this->cabecera,
            //     "link" => '/api/v1/sri/ruc/'.$documento
            // );
            
            
        }catch(Exception $e){
            $retorno['error'] = $e->getMessage();
        }

        return $retorno;
    } 

}