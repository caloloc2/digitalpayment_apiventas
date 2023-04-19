<?php 

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;

class Whatsapp{
        
    private $token = "d0wy1qce8mrlmwdf";
    private $options = [
        'body'    => "",
        'headers' => [
            "Content-Type" => "application/json",
        ]
    ];

    private $cabecera = [
        'headers' => [ 'Content-Type' => 'application/json'],
        'base_uri' => "https://api.chat-api.com",
        'timeout'  => 0,
    ];
    
    function shortlink($link){
        $url = urlencode($link);
        $json = file_get_contents("https://cutt.ly/api/api.php?key=aa3beb35c3c2759c4931cb799763c0e6b0570&short=$url");
        $data = json_decode($json, true);
        return $data['url']['shortLink'];
    }

    function envio($numero, $mensaje){

        $options = ['body' => json_encode([
            "phone" => '593'.substr($numero, 1, strlen($numero)),
            "body" => $mensaje
        ])];

        $client = new Client($this->cabecera);
        
        $response = $client->post('/instance244929/sendMessage?token='.$this->token, $options);
        return json_decode($response->getBody());        
    }

    function getmessages($chatId, $count){        
        // reemplaza @ por %
        $chatId = str_replace("@", "%40", $chatId);
        $client = new Client($this->cabecera);
        
        $response = $client->get('/instance244929/messagesHistory?token='.$this->token.'&chatId='.$chatId.'&page=0&count='.$count);
        return json_decode($response->getBody());
    }

    function sendfile($chatId, $link, $filename, $titulo){
        $options = ['body' => json_encode([            
            "body" => $link,
            "caption" => $titulo,
            "filename" => $filename,
            "chatId" => $chatId
        ])];

        $client = new Client($this->cabecera);
        
        $response = $client->post('/instance244929/sendFile?token='.$this->token, $options);
        return json_decode(array("respuesta" => $response->getBody(), "options" => $options));   
    }

    function sendfile2($numero, $link, $filename, $titulo){
        $options = ['body' => json_encode([     
            "phone" => '593'.substr($numero, 1, strlen($numero)),       
            "body" => $link,
            "caption" => $titulo,
            "filename" => $filename            
        ])];

        $client = new Client($this->cabecera);        
        
        $response = $client->post('/instance244929/sendFile?token='.$this->token, $options);
        return json_decode($response->getBody());
    }
}