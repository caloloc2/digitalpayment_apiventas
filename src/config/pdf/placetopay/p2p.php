<?php 

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;

class PlacetoPay{
    private $placetopay = null;
    private $returnUrl = "http://54.188.245.185/p2p_redirect/index.php?id=";
    private $IPAddress = "127.0.0.1"; // "181.188.211.212"; //  IP CLIENTE
    private $login = null;
    private $trankey = null;
    private $endpoint = null;
    private $ambiente = null;
    private $empresa = null;
    
    private $filepath = __DIR__."/credentials.json";
    private $estado_p2p = null;

    private $options = [
        'body'    => "",
        'headers' => [
            "Content-Type" => "application/json",
        ]
    ];

    private $cabecera = [
        'headers' => [ 'Content-Type' => 'application/json'],
        'base_uri' => "",
        'timeout'  => 0,
    ];

    function __construct($ambiente = "test", $empresa = "mvevip_suscription"){
        $this->ambiente = $ambiente;
        $this->empresa = $empresa;
        $this->estado_p2p = $this->__getCredentials();         

        if ($this->estado_p2p['estado']){
            $llaves = $this->estado_p2p['keys'];
            if ($ambiente == "test"){
                $this->endpoint = "https://checkout-test.placetopay.ec/";
                $this->cabecera['base_uri'] = "https://test.placetopay.ec";
            }else if ($ambiente == "live"){
                $this->endpoint = "https://checkout.placetopay.ec/";
                $this->cabecera['base_uri'] = "https://checkout.placetopay.ec";
            }

            $this->login = $llaves['login'];
            $this->secretKey = $llaves['secretKey'];
        }        
    }

    function __getCredentials(){
        $retorno = array("estado" => false);
        if (file_exists($this->filepath)){
            $informacion = json_decode(file_get_contents($this->filepath), true);
            $retorno['keys'] = $informacion[$this->empresa][$this->ambiente];
            $retorno['estado'] = true;
        }else{
            $retorno['error'] = "Archivo de credenciales no encontrado.";
        }
        
        return $retorno;
    }

    function get_state(){
        return array("credentials" => $this->estado_p2p, "endpoint" => $this->endpoint);
    }

    function getAuth(){
        $seed = date('c');

        if (function_exists('random_bytes')) {
            $nonce = bin2hex(random_bytes(16));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $nonce = bin2hex(openssl_random_pseudo_bytes(16));
        } else {
            $nonce = mt_rand();
        }        

        $nonceBase64 = base64_encode($nonce);
        $tranKey = base64_encode(sha1($nonce . $seed . $this->secretKey, true));

        $this->placetopay['login'] = $this->login;
        $this->placetopay['seed'] = $seed;
        $this->placetopay['nonce'] = $nonceBase64;
        $this->placetopay['tranKey'] = $tranKey;

        return $this->placetopay;
    }

    function getExpiration(){
        return date('c', strtotime('+15 minutes'));
    }    


    /// SUSCRIPCIONES

    function make_suscription($payment){        
        $reference = "S".'_'.$payment['documento'].'_'.date("YmdHis");

        $descripcion = '';

        if (($payment['tipo'] == 1) || ($payment['tipo'] == 4)){
            if ($payment['tipo'] == 1) {
                $descripcion = "Paquete Nuevo";
            }elseif ($payment['tipo'] == 4){
                $descripcion = "Ampliación de Tiempo";
            }
        }else if (($payment['tipo'] == 2) || ($payment['tipo'] == 3) || ($payment['tipo'] == 5)){
            if ($payment['tipo'] == 2) {
                $descripcion = "Cambio Titular";
            }elseif ($payment['tipo'] == 3){
                $descripcion = "Cambio Destino INT";
            }elseif ($payment['tipo'] == 5){
                $descripcion = "Cambio Destino NAC";
            }
        }

        $request = [
            'locale' => "es_CO",
            'auth' => $this->getAuth(),
            'buyer' => [
                'document' => $payment['documento'],
                'documentType' => $payment['tipo_documento'],
                'name' => $payment['apellidos'],
                'surname' => $payment['nombres'],
                'email' => $payment['email'],
                "mobile" => $payment['telefono']
            ], 
            // 'payer' => [
            //     'document' => $payment['documento'],
            //     'documentType' => $payment['tipo'],
            //     'name' => $payment['apellidos'],
            //     'surname' => $payment['nombres'],
            //     'email' => $payment['correo'],
            //     "mobile" => $payment['celular']
            // ],
            'subscription' => [
                'reference' => $reference,
                'description' => $descripcion
            ],
            'expiration' => $this->getExpiration(),
            'skipResult' => true,
            'returnUrl' => URL_RETURN.'/app.html#!',
            'ipAddress' => $this->IPAddress,
            // 'noBuyerFill' => false,
            'userAgent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Safari/537.36',
        ];
        
        return array(
            "request" => $request,
            "response" => $this->query($request),
            "reference" => $reference
        );        
    }

    function make_payment_suscription($token, $payment){
        $total = (float) $payment['valor'];
        $subtotal_doce = 0;
        $subtotal_cero = 0;
        $impuesto = 0;
        $descripcion = '';

        if (($payment['tipo'] == 1) || ($payment['tipo'] == 4)){
            if ($payment['tipo'] == 1) {
                $descripcion = "Paquete Nuevo";
            }elseif ($payment['tipo'] == 4){
                $descripcion = "Ampliación de Tiempo";
            }

            $tmp0 = $total * 0.46;
            $tmp12 = $total * 0.54;

            $subtotal_cero = $tmp0;
            
            $subtotal_doce = $tmp12 / 1.12;
            $impuesto = $subtotal_doce * 0.12;
        }else if (($payment['tipo'] == 2) || ($payment['tipo'] == 3) || ($payment['tipo'] == 5)){
            if ($payment['tipo'] == 2) {
                $descripcion = "Cambio Titular";
            }elseif ($payment['tipo'] == 3){
                $descripcion = "Cambio Destino INT";
            }elseif ($payment['tipo'] == 5){
                $descripcion = "Cambio Destino NAC";
            }

            $subtotal_doce = $total / 1.12;
            $impuesto = $subtotal_doce * 0.12;
        }

        $subtotal = $subtotal_doce + $subtotal_cero;

        $reference = "C".'_'.$payment['documento'].'_'.date("YmdHis");
         //$payment['tipo'],
        $request = [
            'locale' => "es_CO",
            'auth' => $this->getAuth(),
            'buyer' => [
                'document' => $payment['documento'],
                'documentType' => $payment['tipo_documento'],
                'name' => $payment['apellidos'],
                'surname' => $payment['nombres'],
                'email' => $payment['correo'],
                'mobile' => $payment['celular'],
            ],
            'payer' => [
                'document' => $payment['documento'],
                'documentType' => $payment['tipo_documento'],
                'name' => $payment['apellidos'],
                'surname' => $payment['nombres'],
                'email' => $payment['correo'],
                'mobile' => $payment['celular'],
            ],
            'payment' => [
                'reference' => $reference,
                'description' => $descripcion,
                'amount' => [
                    "taxes" => [
                        [
                            "kind" => "valueAddedTax",
                            "amount" => (float) round($impuesto, 2),
                            "base" => (float) round($subtotal_doce, 2)
                        ]
                    ],
                    "details" => [
                        [
                            "kind" => "subtotal",
                            "amount" => (float) round($subtotal, 2)
                        ]
                    ],
                    'currency' => 'USD',
                    'total' => (float) round($total, 2)
                ]                
            ],
            "instrument" => [
                "token" => [
                    "token" => $token
                ]
            ],
            'skipResult' => true,
            'noBuyerFill' => false,
            "expiration" => $this->getExpiration(),
            "returnUrl" => URL_RETURN.'/app.html#!',
            "ipAddress" => "127.0.0.1",
        ];    
                                
        return array(
            "request" => $request,
            "response" => $this->collect($request),
            "reference" => $reference
        ); 
    }

    function make_payment_actualizar($reference, $token, $payment){       
        $request = [
            'locale' => "es_CO",
            'auth' => $this->getAuth(),
            'buyer' => [
                'document' => $payment['documento'],
                'documentType' => $payment['tipo_documento'],
                'name' => $payment['apellidos'],
                'surname' => $payment['nombres'],
                'email' => $payment['correo'],
                'mobile' => $payment['celular'],
            ],
            'payer' => [
                'document' => $payment['documento'],
                'documentType' => $payment['tipo_documento'],
                'name' => $payment['apellidos'],
                'surname' => $payment['nombres'],
                'email' => $payment['correo'],
                'mobile' => $payment['celular'],
            ],
            'payment' => [
                'reference' => $reference,
                'description' => $payment['descripcion'],
                'amount' => [                    
                    'currency' => 'USD',
                    'total' => 1
                ]                
            ],
            "instrument" => [
                "token" => [
                    "token" => $token
                ]
            ],
            'skipResult' => true,
            "expiration" => $this->getExpiration(),
            "returnUrl" => URL_RETURN.'/app.html#!',
            "ipAddress" => "127.0.0.1",            
        ];    
                                
        return array(
            "request" => $request,
            "response" => $this->collect($request)
        ); 
    }

    // PAGOS UNICOS 

    function make_payment_visado($payment){
        $total = (float) $payment['valor'];
        $subtotal_doce = 0;
        $subtotal_cero = 0;
        $impuesto = 0;
        $descripcion = '';

        if (($payment['tipo'] == 1) || ($payment['tipo'] == 4)){
            if ($payment['tipo'] == 1) {
                $descripcion = "Paquete Nuevo";
            }elseif ($payment['tipo'] == 4){
                $descripcion = "Ampliación de Tiempo";
            }

            $tmp0 = $total * 0.46;
            $tmp12 = $total * 0.54;

            $subtotal_cero = $tmp0;
            
            $subtotal_doce = $tmp12 / 1.12;
            $impuesto = $subtotal_doce * 0.12;
        }else if (($payment['tipo'] == 2) || ($payment['tipo'] == 3) || ($payment['tipo'] == 5)){
            if ($payment['tipo'] == 2) {
                $descripcion = "Cambio Titular";
            }elseif ($payment['tipo'] == 3){
                $descripcion = "Cambio Destino INT";
            }elseif ($payment['tipo'] == 5){
                $descripcion = "Cambio Destino NAC";
            }

            $subtotal_doce = $total / 1.12;
            $impuesto = $subtotal_doce * 0.12;
        }

        $subtotal = $subtotal_doce + $subtotal_cero;

        $reference = "R".'_'.$payment['documento'].'_'.date("YmdHis");

        $request = [
            'auth' => $this->getAuth(),
            'buyer' => [
                'document' => $payment['documento'],
                'documentType' => $payment['tipo_documento'],
                'name' => $payment['apellidos'],
                'surname' => $payment['nombres'],
                'email' => $payment['email'],
                'mobile' => $payment['telefono']                
            ],
            'payment' => [
                'reference' => $reference,
                'description' => $descripcion,
                'amount' => [
                    "taxes" => [
                        [
                            "kind" => "valueAddedTax",
                            "amount" => (float) round($impuesto, 2),
                            "base" => (float) round($subtotal_doce, 2)
                        ]
                    ],
                    "details" => [
                        [
                            "kind" => "shipping",
                            "amount" => 0
                        ],                        
                        [
                            "kind" => "subtotal",
                            "amount" => (float) round($subtotal, 2)
                        ]
                    ],      
                    'currency' => 'USD',
                    'total' => (float) round($total, 2)
                ]
                ],
        
            'expiration' => $this->getExpiration(),
            'returnUrl' => URL_RETURN.'/app.html#!/',
            'ipAddress' => $this->IPAddress,            
            'userAgent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Safari/537.36',
        ];
        
        return array(
            "request" => $request,
            "response" => $this->query($request),
            "reference" => $reference
        );
    }    

    // VERIIFICAR PAGOS

    function check_status($requestId){
        $request = [
            'auth' => $this->getAuth()
        ];

        return $this->query($request, "/".$requestId);        
    }

    // REVERSAR PAGOS

    function reversar($internalReference){
        $request = [
            'auth' => $this->getAuth(),
            'internalReference' => $internalReference
        ];
        return $this->query_reverse($request);
    }   


    

    /// Usando guzzle, verificar y borrar
    function envio_query($request, $id = ""){
        $this->options = ['body' => json_encode($request)];

        $client = new Client($this->cabecera);
        
        $response = $client->post('/api/session'.$id, $this->options);

        // return array("cabecera" => $this->cabecera, "options" => $this->options, "url" => '/redirection/api/session'.$id);
        return json_decode($response->getBody());  
    }

    function envio_collet($request){
        $options = ['body' => json_encode($request)];

        $client = new Client($this->cabecera);
        
        $response = $client->post('/redirection/api/collect', $options);
        return json_decode($response->getBody(), true);  
    }

    function envio_reverse($request){
        $options = ['body' => json_encode($request)];

        $client = new Client($this->cabecera);
        
        $response = $client->post('/redirection/api/reverse', $options);
        return json_decode($response->getBody(), true);
    }



    /// verificar y borrar

    function query($request, $id=""){
        try{
            $ch = curl_init($this->endpoint.'api/session'.$id);
            $jsonDataEncoded = json_encode($request);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            $result = curl_exec($ch);
    
            return json_decode($result, true);        
        }catch(Exception $e){
            return array("error" => $e->getMessage());
        }        
    }

    function collect($request){        
        $ch = curl_init($this->endpoint.'api/collect');
        $jsonDataEncoded = json_encode($request);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_USERAGENT, 'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Safari/537.36');
        $result = curl_exec($ch);

        return json_decode($result, true);
    }

    function query_reverse($request){        
        $ch = curl_init($this->endpoint.'api/reverse');
        $jsonDataEncoded = json_encode($request);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $result = curl_exec($ch);

        return json_decode($result, true);
    }
}



