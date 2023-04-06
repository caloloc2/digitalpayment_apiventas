<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\UploadedFileInterface;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Aws\Credentials\CredentialProvider;

$app = new \Slim\App;

$app->group('/api', function() use ($app) {

    // Route Group v1
    $app->group('/v1', function() use ($app) {


        $app->group('/documentacion', function() use ($app) {

            // BASE

            $app->get("/base/{id}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');   
                $data = $request->getParsedBody();
                $id = $request->getAttribute('id');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
    
                try{
                    // $auth = new Authentication();
                    // $autenticacion = $auth->Verifica_Comercio($authorization[0]);
    
                    // if ($autenticacion['estado']){
                    //     $id_usuario = $autenticacion['id_usuario'];
                    //     $database = $autenticacion['database'];
                        
                    //     $mysql = new Database($database);
    
                    // }else{
                        // $respuesta['error'] = $autenticacion['error'];
                    // }
                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }
    
                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });         

            $app->post("/login", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                $respuesta['estado'] = false;
    
                try{
                    $mysql = new Database("digitalpayment");
                    $auth = new Authentication();

                    $correo = $data['correo'];
                    $password = $data['password'];
                    $fecha = date("Y-m-d H:00:00"); // el hash caducada cada hora
                    $fecha_ingreso = date("Y-m-d H:i:s");

                    $consulta = $mysql->Consulta_Unico("SELECT U.id_usuario, U.nombres, A.acceso, A.url, U.estado FROM usuarios U, usuarios_accesos A WHERE ((U.correo='".$correo."') AND (U.password='".$password."')) AND (U.id_acceso=A.id_acceso)");

                    if (isset($consulta['id_usuario'])){
                        $estado = $consulta['estado'];

                        if ($estado == 0){
                            $id_usuario = $consulta['id_usuario'];
                            $nombres = $consulta['nombres'];
                            
                            $string_hash = $id_usuario."|".$nombres."|".$fecha;
        
                            $hash = $auth->encrypt_decrypt('encrypt', $string_hash);
        
                            $actualiza_usuario = $mysql->Modificar("UPDATE usuarios SET hash=?, fecha_ultimo_ingreso=? WHERE id_usuario=?", array($hash, $fecha_ingreso, $id_usuario));
        
                            $respuesta['acceso'] = $consulta['url'];
                            $respuesta['hash'] = $hash;
                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = "Sus credenciales se encuentran deshabilitadas.";
                        }                    
                    }else{
                        $respuesta['error'] = "No se encuentra las credenciales, su usuario no se encuentra registrado.";
                    }

                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }
    
                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            }); 
            
            $app->get("/sesion", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');                
                $respuesta['estado'] = false;
    
                try{                    
                    $auth = new Authentication();
                    $respuesta['usuario'] = $auth->Valida_Usuario($authorization[0]);

                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }
    
                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            }); 
            
            // ESTABLECIMIENTOS

            $app->get("/establecimientos", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization'); 
                $params = $request->getQueryParams();               
                $respuesta['estado'] = false;
    
                try{                    
                    // $auth = new Authentication();
                    // $sesion = $auth->Valida_Usuario($authorization[0]);

                    // if ($sesion['estado']){
                        $mysql = new Database("digitalpayment");

                        $id_usuario = 1;//$sesion['usuario']['id_usuario'];

                        $buscador = "";
                        if (isset($params['buscador'])){
                            $buscador = $params['buscador'];
                        }

                        $consulta = $mysql->Consulta("SELECT
                        E.id_establecimiento, E.ruc, E.establecimiento, E.direccion, E.contacto, E.telefono, E.celular, E.correo, E.dependiente, E.logotipo, E.observaciones, E.estado,
                        E.id_sector, S.sector, S.id_ciudad, C.ciudad, C.id_provincia, P.provincia, E.id_usuario, U.nombres
                        FROM establecimientos E
                        LEFT JOIN params_sectores S
                        ON E.id_sector = S.id_sector
                        LEFT JOIN params_ciudades C
                        ON S.id_ciudad = C.id_ciudad
                        LEFT JOIN params_provincias P
                        ON C.id_provincia = P.id_provincia
                        LEFT JOIN usuarios U
                        ON E.id_usuario = U.id_usuario
                        WHERE
                        (E.ruc LIKE '%".$buscador."%')");

                        $resultados = [];
                        if (is_array($consulta)){
                            if (count($consulta) > 0){
                                foreach ($consulta as $linea) {
                                    $adjuntos = $mysql->Consulta("SELECT * FROM establecimientos_adjuntos WHERE id_establecimiento=".$linea['id_establecimiento']);

                                    array_push($resultados, array(
                                        "id_establecimiento" => (int) $linea['id_establecimiento'],
                                        "ruc" => $linea['ruc'],
                                        "establecimiento" => $linea['establecimiento'],
                                        "direccion" => $linea['direccion'],
                                        "contacto" => $linea['contacto'],
                                        "telefono" => $linea['telefono'],
                                        "celular" => $linea['celular'],
                                        "correo" => $linea['correo'],
                                        "dependiente" => $linea['dependiente'],
                                        "logotipo" => $linea['logotipo'],
                                        "observaciones" => $linea['observaciones'],
                                        "estado" => array(
                                            "valor" => (int) $linea['estado'],
                                            "descripcion" => "Pendiente"
                                        ),
                                        "sector" => array(
                                            "id" => (int) $linea['id_sector'],
                                            "descripcion" => $linea['sector']
                                        ),
                                        "ciudad" => array(
                                            "id" => (int) $linea['id_ciudad'],
                                            "descripcion" => $linea['ciudad']
                                        ),
                                        "provincia" => array(
                                            "id" => (int) $linea['id_provincia'],
                                            "descripcion" => $linea['provincia']
                                        ),
                                        "usuario" => array(
                                            "id" => (int) $linea['id_usuario'],
                                            "descripcion" => $linea['nombres']
                                        ),
                                        "adjuntos" => $adjuntos
                                    ));
                                }
                            }
                        }

                        $respuesta['resultados'] = $resultados;
                        $respuesta['estado'] = true;

                    // }else{
                    //     $respuesta['error'] = $sesion['error'];
                    // }

                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }
    
                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            }); 

            $app->get("/establecimientos/{id_establecimiento}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization'); 
                $id_establecimiento = $request->getAttribute('id_establecimiento');
                $respuesta['estado'] = false;
    
                try{                    
                    $auth = new Authentication();
                    $sesion = $auth->Valida_Usuario($authorization[0]);

                    // if ($sesion['estado']){
                        $mysql = new Database("digitalpayment");

                        $id_usuario =  1; //$sesion['usuario']['id_usuario'];

                        $buscador = "";
                        if (isset($params['buscador'])){
                            $buscador = $params['buscador'];
                        }

                        $consulta = $mysql->Consulta_Unico("SELECT
                        E.id_establecimiento, E.ruc, E.establecimiento, E.direccion, E.contacto, E.telefono, E.celular, E.correo, E.dependiente, E.logotipo, E.observaciones, E.estado,
                        E.id_sector, S.sector, S.id_ciudad, C.ciudad, C.id_provincia, P.provincia, E.id_usuario, U.nombres
                        FROM establecimientos E
                        LEFT JOIN params_sectores S
                        ON E.id_sector = S.id_sector
                        LEFT JOIN params_ciudades C
                        ON S.id_ciudad = C.id_ciudad
                        LEFT JOIN params_provincias P
                        ON C.id_provincia = P.id_provincia
                        LEFT JOIN usuarios U
                        ON E.id_usuario = U.id_usuario
                        WHERE
                        E.id_establecimiento=".$id_establecimiento);

                        $resultados = [];

                        if (isset($consulta['id_establecimiento'])){
                            $resultados = array(
                                "id_establecimiento" => (int) $consulta['id_establecimiento'],
                                "ruc" => $consulta['ruc'],
                                "establecimiento" => $consulta['establecimiento'],
                                "direccion" => $consulta['direccion'],
                                "contacto" => $consulta['contacto'],
                                "telefono" => $consulta['telefono'],
                                "celular" => $consulta['celular'],
                                "correo" => $consulta['correo'],
                                "dependiente" => $consulta['dependiente'],
                                "logotipo" => $consulta['logotipo'],
                                "observaciones" => $consulta['observaciones'],
                                "estado" => array(
                                    "valor" => (int) $consulta['estado'],
                                    "descripcion" => "Pendiente"
                                ),
                                "sector" => array(
                                    "id" => (int) $consulta['id_sector'],
                                    "descripcion" => $consulta['sector']
                                ),
                                "ciudad" => array(
                                    "id" => (int) $consulta['id_ciudad'],
                                    "descripcion" => $consulta['ciudad']
                                ),
                                "provincia" => array(
                                    "id" => (int) $consulta['id_provincia'],
                                    "descripcion" => $consulta['provincia']
                                ),
                                "usuario" => array(
                                    "id" => (int) $consulta['id_usuario'],
                                    "descripcion" => $consulta['nombres']
                                ),
                                "adjuntos" => []
                            );                            

                            $respuesta['resultados'] = $resultados;
                            $respuesta['estado'] = true;
                            
                        }else{
                            $respuesta['error'] = "No se encuentra informacion del establecimiento.";
                        }                       

                        

                    // }else{
                    //     $respuesta['error'] = $sesion['error'];
                    // }

                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }
    
                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            }); 

        });



        $app->post("/auth", function(Request $request, Response $response) {
            $data = $request->getParsedBody();
            $respuesta['estado'] = false;
        
            try{                
                $mysql = new Database("vtgsa_ventas");

                if (((isset($data['username'])) && (!empty($data['username']))) && ((isset($data['password'])) && (!empty($data['password'])))){
                    $username = $data['username'];
                    $password = $data['password'];

                    $consulta = $mysql->Consulta_Unico("SELECT 
                    U.id_usuario, U.nombres, U.correo, T.url, U.estado
                    FROM usuarios U 
                    LEFT JOIN usuarios_tipos T
                    ON U.tipo = T.id_usuario_tipo WHERE (U.correo='".$username."') AND (U.password='".$password."')");

                    if (isset($consulta['id_usuario'])){
                        if ($consulta['estado'] == 0){

                            $id_usuario = $consulta['id_usuario'];
                            $nombres = $consulta['nombres'];
                            $correo = $consulta['correo'];

                            $cadena = $id_usuario."|".$nombres."|".$correo."|".date("Ymd");

                            $autenticacion = new Authentication();
                            $hash = $autenticacion->encrypt_decrypt("encrypt", $cadena);

                            $actualiza = $mysql->Modificar("UPDATE usuarios SET hash=? WHERE id_usuario=?", array($hash, $id_usuario));

                            $respuesta['accessToken'] = $hash;
                            $respuesta['url'] = $consulta['url'];
                            $respuesta['estado'] = true; 
                        }else{
                            $respuesta['error'] = "Su usuario se encuentra deshabilitado.";
                        }
                    }else{
                        $respuesta['error'] = "No se ha encontrado ningún usuario con estas credenciales.";
                    }
                    
                }else{
                    $respuesta['error'] = "Debe ingresar los campos obligatoriamente.";
                }

                
            }catch(PDOException $e){
                $respuesta['error'] = $e->getMessage();
            }

            $newResponse = $response->withJson($respuesta);
        
            return $newResponse;
        });

        $app->get("/session", function(Request $request, Response $response) {
            $authorization = $request->getHeader('Authorization');
            $respuesta['estado'] = false;
        
            try{                
                $mysql = new Database("vtgsa_ventas");

                if (isset($authorization[0])){
                    $autenticacion = new Authentication();
                    $session = $autenticacion->Valida_Sesion($authorization[0]);

                    $respuesta['asdf'] = $authorization[0];

                    if ($session['estado']){
                        $respuesta['usuario'] = $session['usuario'];

                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = $session['error'];
                    }

                    
                }else{
                    $respuesta['error'] = "Su token de acceso no se encuentra.";
                }
                
            }catch(PDOException $e){
                $respuesta['error'] = $e->getMessage();
            }

            $newResponse = $response->withJson($respuesta);
        
            return $newResponse;
        });

        $app->group('/admin', function() use ($app) {

            $app->post("/nuevo-usuario", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                
                $respuesta['estado'] = false;
            
                try{                
                    $mysql = new Database("vtgsa_ventas");
                    $Functions = new Functions();

                    $correo = strtolower($data['correo']);
                    $nombres = strtoupper($data['nombres']);
                    $tipo = $data['tipo'];

                    $consulta = $mysql->Consulta_Unico("SELECT * FROM usuarios WHERE correo='".$correo."'");

                    if (!isset($consulta['id_usuario'])){
                        $temp_pass = $Functions->number_random(8);
                        $id_referencia = 0;

                        if ($tipo==6){
                            $id_referencia = $mysql->Ingreso("INSERT INTO vendedores (id_usuario, vendedor) VALUES (?,?)", array(0, $nombres));
                        }


                        $id_usuario = $mysql->Ingreso("INSERT INTO usuarios (nombres, correo, tipo, `password`, id_referencia) VALUES (?,?,?,?,?)", array($nombres, $correo, $tipo, $temp_pass, $id_referencia));

                        $respuesta['id'] = $id_usuario;
                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "Ya existe un usuario registrado con el correo electrónico.";
                    }
            
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

        });

        $app->group('/ventas', function() use ($app) {

            // LOGIN

            $app->post("/login", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                
                $respuesta = [];
            
                try{                
                    if ($request->getMethod() == "POST"){
                        
                        $fecha = date("Y-m-d H:i:s");
                        $estado = 0;              

                        $consulta = Meta::Consulta_Unico("SELECT * FROM usuarios WHERE ((correo='".$data['correo']."') AND (password='".$data['password']."') AND (estado=0))");
                    }

                    $newResponse = $response->withJson($consulta);
            
                    return $newResponse;
            
                }catch(PDOException $e){
                    echo "Error: ".$e->getMessage();
                }
            });

            // NUEVA VENTA

            $app->post("/nueva_venta", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();            
            
                try{                
                    if ($request->getMethod() == "POST"){
                        $path = __DIR__."/../../../vtgventas/php/voucher/";

                        if (file_exists($path)){
                            $id_lider = $data['lider'];
                            $vendedor = $data['vendedor'];                        
                            $celular = $data['celular'];

                            $referencia = $data['referencia'];
                            $aprobacion = $data['aprobacion'];
                            $valor_voucher = $data['valor_voucher'];

                            $imagen = $data['imagen'];
                            $decodeImagen = base64_decode($imagen);                 

                            $consulta_vendedor = Meta::Consulta_Unico("SELECT * FROM vendedores WHERE vendedor='".$vendedor."'");

                            if ($consulta_vendedor['id_vendedor']!=""){
                                $id_vendedor = $consulta_vendedor['id_vendedor'];

                                $id_cliente = "";            
                                $documento = "";
                                $nombres_apellidos = "";
                                $ciudad = '';
                                $convencional = '';
                                $correo = "";
                                $pago_parcial = 0;

                                $dummy= '';                                                        

                                $tipo_venta = 0;            
                                $tipo = 0;
                                $que_destino = "";
                                $destino = 0;
                                            
                                $fecha_alta = date('Y-m-d H:i:s');
                                $fecha_modificacion = $fecha_alta;            
                                $fecha_caducidad = $fecha_alta;
                                                
                                $estado = 4;

                                $ingreso = Meta::Nuevo_Registro($fecha_alta, $id_vendedor, $id_lider, $dummy, $id_cliente, $documento, $nombres_apellidos, $ciudad, $correo, $convencional, $celular, $pago_parcial, $tipo_venta, $fecha_caducidad, $tipo, $que_destino, $destino, $fecha_alta, $fecha_modificacion, $estado);                           

                                $creacion = Meta::Consulta_Unico("SELECT id_registro FROM registros ORDER BY id_registro DESC LIMIT 1");

                                if ($creacion['id_registro']!=''){
                                    $id_registro = $creacion['id_registro'];

                                    $actualizar = Meta::Ejecutar("UPDATE registros SET referencia='".$referencia."' WHERE id_registro=".$id_registro);

                                    $nombre_voucher = $id_registro."_".date("YmdHis").".jpg";
                                    $return = file_put_contents($path.$nombre_voucher, $decodeImagen);                                
                                    
                                    $comision = 0;                                

                                    $id_institucion = 1;
                                    $primeros_digitos = '';
                                    $ultimos_digitos = '';
                                    $estado = 0;

                                    $nuevo_pago = Meta::Nuevo_Pago($id_registro, $referencia, $aprobacion, $comision, $valor_voucher, $id_institucion, $primeros_digitos, $ultimos_digitos, $nombre_voucher, $fecha_alta, $estado);

                                    $json = fileparams_contents('https://cutt.ly/api/api.php?key=1f2833c2e41a93bdf6beefbe8c3c68a46e71f&short=https://ventas.mvevip.com/php/voucher/'.$nombre_voucher);
                                    $encoded = json_decode($json);
                                    $consulta['shorturl'] = $encoded->url->shortLink;

                                    $consulta['mensaje'] = "Venta registrada correctamente";
                                }                            
                            }else{
                                $consulta['mensaje'] = "No se pudo encontrar al vendedor.";                            
                                
                            }
                        }else{
                            $consulta['mensaje'] = "No existe o no se puede acceder a la ubicacion.";
                        }                                    
                    }

                    $newResponse = $response->withJson($consulta);
            
                    return $newResponse;
            
                }catch(PDOException $e){
                    echo "Error: ".$e->getMessage();
                }
            });

            $app->post("/nuevo_seguro", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                
                $respuesta['estado'] = false;
            
                try{                
                    $mysql = new Database("vtgventas");

                    

                    $respuesta['estado'] = true;
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($consulta);
                return $newResponse;
            });

            // REGISTROS POR VENDEDOR

            $app->post("/registros/{id}", function(Request $request, Response $response){
                $id = $request->getAttribute('id');            
                $authorization = $request->getHeader('Authorization');  
                $data = $request->getParsedBody();           
            
                try{
                    $buscador = $data['texto_buscador'];
                    $fecha_inicio = date("Y-m-01");
                    $fecha_final = date("Y-m-d");
                    // $sql = "SELECT R.id_registro, R.fecha_venta, V.vendedor, L.lider, R.id_cliente, R.documento, R.nombres_apellidos, R.ciudad, R.referencia, R.aprobacion, R.valor_voucher, R.pago_parcial, R.comision, R.correo, R.fecha_alta, R.estado FROM registros R, vendedores V, lideres L WHERE (R.id_vendedor=".$id.") AND (R.id_vendedor=V.id_vendedor) AND (R.id_lider=L.id_lider) ORDER BY R.fecha_venta DESC";

                    $sql = "SELECT R.id_registro, R.fecha_venta, R.id_cliente, R.documento, R.nombres_apellidos, R.ciudad, R.referencia, R.aprobacion, V.vendedor, L.lider, SUM(P.valor_voucher) AS valor_voucher, R.pago_parcial, R.comision, R.correo, R.fecha_alta, R.estado FROM registros R, registros_pagos P, vendedores V, lideres L
                    WHERE (((R.referencia LIKE '%".$buscador."%') OR (R.id_cliente LIKE '%".$buscador."%') OR (R.nombres_apellidos LIKE '%".$buscador."%') OR (R.documento LIKE '%".$buscador."%') OR (R.aprobacion LIKE '%".$buscador."%') OR (R.correo LIKE '%".$buscador."%')) AND (R.id_vendedor=".$id.") AND (R.fecha_venta BETWEEN '".$fecha_inicio."' AND '".$fecha_final."')) AND ((R.id_registro=P.id_registro) AND (R.id_vendedor=V.id_vendedor) AND (R.id_lider=L.id_lider)) GROUP BY R.id_registro ORDER BY R.id_registro DESC";

                    $consulta = Meta::Consulta($sql);
                            
                    $newResponse = $response->withJson($consulta);
            
                    return $newResponse;
            
                }catch(PDOException $e){
                    echo "Error: ".$e->getMessage();
                }
            });

            // REGISTROS POR LIDER

            $app->post("/ventas_lider/{id}", function(Request $request, Response $response){
                $id = $request->getAttribute('id');
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
            
                try{
                    $fecha_inicio = date("Y-m-01");
                    $fecha_final = date("Y-m-d");
                    $buscador = $data['texto_buscador'];

                    $sql = "SELECT R.id_registro, R.fecha_venta, R.id_cliente, R.documento, R.nombres_apellidos, R.ciudad, R.referencia, R.aprobacion, V.vendedor, L.lider, SUM(P.valor_voucher) AS valor_voucher, R.pago_parcial, R.comision, R.correo, R.fecha_alta, R.estado FROM registros R, registros_pagos P, vendedores V, lideres L
                    WHERE (((R.referencia LIKE '%".$buscador."%') OR (R.id_cliente LIKE '%".$buscador."%') OR (R.nombres_apellidos LIKE '%".$buscador."%') OR (R.documento LIKE '%".$buscador."%') OR (R.aprobacion LIKE '%".$buscador."%') OR (R.correo LIKE '%".$buscador."%')) AND (R.id_lider=".$id.") AND (R.fecha_venta BETWEEN '".$fecha_inicio."' AND '".$fecha_final."')) AND ((R.id_registro=P.id_registro) AND (R.id_vendedor=V.id_vendedor) AND (R.id_lider=L.id_lider)) GROUP BY R.id_registro ORDER BY R.id_registro DESC"; 

                    $consulta = Meta::Consulta($sql);
            
                    $newResponse = $response->withJson($consulta);
            
                    return $newResponse;
            
                }catch(PDOException $e){
                    echo "Error: ".$e->getMessage();
                }
            });

            // REGISTROS EN GENERAL

            $app->post("/ventas/{id}", function(Request $request, Response $response){
                $id = $request->getAttribute('id');
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();            
            
                try{                
                    $fecha_inicio = date("Y-m-01");
                    $fecha_final = date("Y-m-d");
                    $buscador = $data['texto_buscador'];

                    $sql = "SELECT R.id_registro, R.fecha_venta, R.id_cliente, R.documento, R.nombres_apellidos, R.ciudad, R.referencia, R.aprobacion, V.vendedor, L.lider, SUM(P.valor_voucher) AS valor_voucher, R.pago_parcial, R.comision, R.correo, R.fecha_alta, R.estado FROM registros R, registros_pagos P, vendedores V, lideres L
                    WHERE ((R.referencia LIKE '%".$buscador."%') OR (R.id_cliente LIKE '%".$buscador."%') OR (R.nombres_apellidos LIKE '%".$buscador."%') OR (R.documento LIKE '%".$buscador."%') OR (R.aprobacion LIKE '%".$buscador."%') OR (R.correo LIKE '%".$buscador."%')) AND (R.fecha_venta BETWEEN '".$fecha_inicio."' AND '".$fecha_final."') AND ((R.id_registro=P.id_registro) AND (R.id_vendedor=V.id_vendedor) AND (R.id_lider=L.id_lider)) GROUP BY R.id_registro ORDER BY R.id_registro DESC";

                    $consulta = Meta::Consulta($sql);
            
                    $newResponse = $response->withJson($consulta);
            
                    return $newResponse;
            
                }catch(PDOException $e){
                    echo "Error: ".$e->getMessage();
                }
            });

            // VENDEDORES

            $app->get("/vendedores", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $respuesta['estado'] = false;
                $respuesta['error'] = "";
            
                $consulta = "SELECT id_vendedor, vendedor, imagen FROM vendedores ORDER BY vendedor ASC";
            
                try{
                    $consulta = Meta::Consulta($consulta);
                    
                    $respuesta['consulta'] = $consulta;                
                    $respuesta['estado'] = true;
            
                    $newResponse = $response->withJson($consulta);
            
                    return $newResponse;
            
                }catch(PDOException $e){
                    echo "Error: ".$e->getMessage();
                }
            });

            // PODIO

            $app->get("/podio", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $respuesta['estado'] = false;
                $respuesta['error'] = "";
            
                $mes = date("m");            
                $anio = date("Y");
                $fecha_inicio = $anio."-".$mes."-01";
                $fecha_final = $anio."-".$mes."-31";

                try{                
                    $consulta = Meta::Consulta("SELECT R.id_vendedor, SUM(R.valor_voucher) AS total, V.vendedor, V.imagen FROM registros R, vendedores V WHERE (R.fecha_venta BETWEEN '".$fecha_inicio."' AND '".$fecha_final."') AND (R.id_vendedor = V.id_vendedor) AND ((V.id_vendedor!=33) AND (V.id_vendedor!=38) AND (V.id_vendedor!=71)) GROUP BY R.id_vendedor ORDER BY SUM(R.valor_voucher) DESC LIMIT 3");                
            
                    $newResponse = $response->withJson($consulta);
            
                    return $newResponse;
            
                }catch(PDOException $e){
                    echo "Error: ".$e->getMessage();
                }
            });

            // REGISTROS EN GENERAL

            $app->get("/ventas_usuario/{id}", function(Request $request, Response $response){
                $id = $request->getAttribute('id');
                $authorization = $request->getHeader('Authorization');    

                try{                
                    $fecha_inicio = date("Y-m-01");
                    $fecha_final = date("Y-m-d");

                    // OBTIENE INFORMACION DEL USUARIO PARA VER SI ES ADMIN, LIDER O VENDEDOR
                    $usuario = Meta::Consulta_Unico("SELECT * FROM usuarios WHERE id_usuario=".$id);

                    if ($usuario['id_usuario']!=''){
                        $tipo = $usuario['tipo'];
                        $id_usuario = $usuario['id_usuario'];
                        $id_referencia = $usuario['id_referencia'];

                        if ($tipo == 6) { // vendedor
                            $sql = "SELECT SUM(P.valor_voucher) AS total FROM registros R, registros_pagos P WHERE (R.fecha_venta BETWEEN '".$fecha_inicio."' AND '".$fecha_final."') AND (R.id_vendedor=".$id_referencia.") AND (R.id_registro=P.id_registro) GROUP BY R.id_vendedor";
                        }else if($tipo == 7){ // lider
                            $sql = "SELECT SUM(P.valor_voucher) AS total FROM registros R, registros_pagos P WHERE (R.fecha_venta BETWEEN '".$fecha_inicio."' AND '".$fecha_final."') AND (R.id_lider=".$id_referencia.") AND (R.id_registro=P.id_registro) GROUP BY R.id_lider";
                        }else{ // otros usuarios
                            $sql = "SELECT SUM(P.valor_voucher) AS total FROM registros R, registros_pagos P WHERE (R.fecha_venta BETWEEN '".$fecha_inicio."' AND '".$fecha_final."') AND (R.id_registro=P.id_registro) GROUP BY R.id_vendedor";
                        }
                        
                        $consulta = Meta::Consulta_Unico($sql);
        
                        $newResponse = $response->withJson($consulta);
        
                        return $newResponse;
                    }
                
                }catch(PDOException $e){
                    echo "Error: ".$e->getMessage();
                }
            });


            // CODIGO DE DESCUENTO PARA VOYSTORES

            $app->get("/descuento/{id}", function(Request $request, Response $response){
                $id = $request->getAttribute('id');
                $authorization = $request->getHeader('Authorization');           

                $respuesta['estado'] = false;

                try{
                    // busca el codigo de descuento del cliente
                    $consulta = Meta::Consulta_Unico("SELECT id_registro, nombres_apellidos, codigo_usado, codigo_aplicacion, codigo_documento, codigo_saldo FROM registros WHERE codigo_descuento='".$id."'");
                    
                    if (isset($consulta['id_registro'])){
                        $estado_codigo = "Disponible";

                        if ($consulta['codigo_usado']== 1){
                            $estado_codigo = "No Disponible";

                            $respuesta['consulta'] = array("id_registro" => (int) $consulta['id_registro'], "nombres" => $consulta['nombres_apellidos'], "codigo" => $estado_codigo, "fecha" => $consulta['codigo_aplicacion'], "documento" => $consulta['codigo_documento'], "saldo" => (float)$consulta['codigo_saldo']);
                        }else if ($consulta['codigo_usado'] == 0){
                            $respuesta['consulta'] = array("id_registro" => (int) $consulta['id_registro'], "nombres" => $consulta['nombres_apellidos'], "codigo" => $estado_codigo, "saldo" => (float) $consulta['codigo_saldo']);
                        }
                        
                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No se encuentra el codigo de descuento.";
                    }
                
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
        
                return $newResponse;
            });

            $app->post("/aplicardescuento/{id}", function(Request $request, Response $response){
                $id = $request->getAttribute('id');
                $authorization = $request->getHeader('Authorization');
                
                $data = $request->getParsedBody();

                $documento = $data['documento'];
                $descuento = $data['descuento'];
                $fecha_aplicacion = date("Y-m-d H:i:s");

                $respuesta['estado'] = false;

                try{
                    // busca el codigo de descuento del cliente
                    $consulta = Meta::Consulta_Unico("SELECT id_registro, nombres_apellidos, codigo_saldo FROM registros WHERE codigo_descuento='".$id."'");

                    if ($consulta['id_registro']){
                        $id_registro = $consulta['id_registro'];
                        $codigo_saldo = (float) $consulta['codigo_saldo'];

                        if ($codigo_saldo>0){
                            $nuevo_saldo = floatval($codigo_saldo) - floatval($descuento);

                            $codigo_usado = 0;

                            if ($nuevo_saldo <= 0){
                                $nuevo_saldo = 0;
                                $codigo_usado = 1;
                            }

                            $modificar = Meta::Ejecutar("UPDATE registros SET codigo_usado=".$codigo_usado.", codigo_aplicacion='".$fecha_aplicacion."', codigo_saldo=".$nuevo_saldo.", codigo_documento='".$documento."' WHERE id_registro=".$id_registro);

                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = "No tiene saldo disponible con este codigo.";
                        }                 
                    }else{
                        $respuesta['error'] = "El codigo ya ha sido utilizado.";
                    }                            
                
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
        
                return $newResponse;
            });



        
            // CLIENTES

            $app->get("/clientes", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');

                $respuesta['estado'] = false;
            
                try{
                    $mysql = new Database($authorization[0]);
                    $consulta = $mysql->Consulta("SELECT * FROM clientes");               

                    $respuesta['consulta'] = $consulta;
                    $respuesta['estado'] = true;
            
                    $newResponse = $response->withJson($respuesta);
            
                    return $newResponse;
            
                }catch(PDOException $e){
                    echo "Error: ".$e->getMessage();
                }
            });


            // REGISTROS

            $app->get("/registros", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();

                $respuesta['estado'] = false;            
                
                $fecha_inicio = date("Y-03-01");
                if (isset($params['from'])){
                    $fecha_inicio = $params['from'];
                }
                
                $fecha_final = date("Y-03-30");
                if (isset($params['to'])){
                    $fecha_final = $params['to'];
                }

                $buscador = "";
                if (isset($params['buscador'])){
                    $buscador = $params['buscador'];
                }

                $por_estado = " AND (estado_cierre>=0)";
                if (isset($params['filtro'])){
                    if ($params['filtro'] > -1) {
                        $por_estado = " AND (estado_cierre=".$params['filtro'].")";
                    }
                }

                $por_tipo_contrato = " AND (tipo_venta>=0)";
                if (isset($params['tipo_contrato'])){
                    if ($params['tipo_contrato'] > -1) {
                        $por_tipo_contrato = " AND (tipo_venta=".$params['tipo_contrato'].")";
                    }
                }
            
                $resultados = [];

                try{

                    $mysql = new Database('vtgsa_ventas');
                    $functions = new Functions();
                    $consulta = $mysql->Consulta("SELECT * FROM registros WHERE (fecha_venta BETWEEN '".$fecha_inicio."' AND '".$fecha_final."') AND ((id_cliente LIKE '%".$buscador."%') OR (nombres_apellidos LIKE '%".$buscador."%') OR (ciudad LIKE '%".$buscador."%')) ".$por_estado." ".$por_tipo_contrato." AND (estado = 1) ORDER BY fecha_alta DESC");

                    if (is_array($consulta)){
                        if (count($consulta) > 0){
                            foreach ($consulta as $linea) {
                                $id_registro = $linea['id_registro'];

                                $final = $linea['id_cliente'].'-'.$functions->Fecha_Sin_Separadores($linea['fecha_modificacion']).'.pdf';
                                $path_contrato = __DIR__.'/contratos/'.$final;
                                $link_contrato = "https://ventas.mvevip.com/php/contratos/".$final;

                                $pagos = $mysql->Consulta("SELECT * FROM registros_pagos WHERE id_registro=".$id_registro);

                                $nombres_usuario_seguimiento = "Sin Asignar";
                                $nombres_usuario_cierre = "";
                                $fecha_cierre = "Pendiente";
                                $color_estado_cierre = "warning";
                                if (!is_null($linea['fecha_cierre_caso'])){
                                    $fecha_cierre = $linea['fecha_cierre_caso'];
                                    $color_estado_cierre = "primary";
                                }
                                

                                $nombre_usuario_asignado = $mysql->Consulta_Unico("SELECT * FROM usuarios WHERE id_usuario=".$linea['id_usuario_seguimiento']);                                
                                $color_seguimiento = "light";
                                if (isset($nombre_usuario_asignado['id_usuario'])){
                                    $nombres_usuario_seguimiento = $nombre_usuario_asignado['nombres'];
                                    $color_seguimiento = "info";
                                }

                                $tipo_venta = $linea['tipo_venta'];
                                $descripcion_tipo_venta = "";
                                switch ($tipo_venta) {
                                    case 0:
                                        $descripcion_tipo_venta = "Contrato Nuevo";
                                        break;
                                    case 1:
                                        $descripcion_tipo_venta = "Adendum";
                                        break;
                                    case 2:
                                        $descripcion_tipo_venta = "Contrato Nuevo + Voystores";
                                        break;
                                }

                                $tipo_adendum = $linea['tipo'];
                                $descripcion_tipo_adendum = "";

                                switch ($tipo_adendum) {
                                    case 0:
                                        $descripcion_tipo_adendum = "Tiempo";
                                        break;
                                    case 1:
                                        $descripcion_tipo_adendum = "Cuota";
                                        break;
                                    case 2:
                                        $descripcion_tipo_adendum = "Persona";
                                        break;
                                    case 3:
                                        $descripcion_tipo_adendum = "Destino";
                                        break;
                                    case 4:
                                        $descripcion_tipo_adendum = "Titular";
                                        break;
                                }
                                $tipo_adendum_info = [];
                                if ($tipo_venta == 1){
                                    $tipo_adendum_info = array(
                                        "id" => (int) $tipo_adendum,
                                        "descripcion" => $descripcion_tipo_adendum
                                    );
                                }                                

                                array_push($resultados, array(
                                    "id_registro" => $linea['id_registro'],
                                    "fecha_venta" => $linea['fecha_venta'],
                                    "id_cliente" => $linea['id_cliente'],
                                    "celular" => $linea['celular'],
                                    "nombres_apellidos" => $linea['nombres_apellidos'],
                                    "archivos" => array(
                                        "cedula" => $linea['link_cedula'],
                                        "contrato" => array(
                                            "original" => $link_contrato,
                                            "firmado" => $linea['link_contrato_firmado']
                                        ),
                                        "voucher" => array(
                                            "original" => "original",
                                            "firmado" => $linea['link_voucher_firmado']
                                        )
                                    ),
                                    "tipo_venta" => array(
                                        "id" => (int) $tipo_venta,
                                        "descripcion" => $descripcion_tipo_venta,
                                        "tipo" => $tipo_adendum_info
                                    ),
                                    "usuario_seguimiento" => array(
                                        "id_usuario" => (int) $linea['id_usuario_seguimiento'],
                                        "nombres" => $nombres_usuario_seguimiento,
                                        "color" => $color_seguimiento
                                    ),
                                    "canal_respuesta" => $linea['canal_respuesta'],
                                    "auditoria" => array(
                                        "contactado" => (int) $linea['auditoria_contactado'],
                                        "fecha" => $linea['fecha_auditoria_contacto']
                                    ),
                                    "cierre" => array(
                                        "fecha" => $fecha_cierre,
                                        "id_usuario" => (int) $linea['id_usuario_cierre'],
                                        "nombres" => $nombres_usuario_cierre,
                                        "observaciones" => $linea['observaciones_cierre'],
                                        "color" => $color_estado_cierre,
                                        "estado" => (int) $linea['estado_cierre']
                                    ),                                    
                                ));
                            }
                        }
                    }
                    
                    $respuesta['consulta'] = $resultados;
                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){                    
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->post("/registros/archivos/{id_registro}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');                
                $files = $request->getUploadedFiles();                
                $id_registro = $request->getAttribute('id_registro');
                $data = $request->getParsedBody();

                $respuesta['estado'] = false;
                $respuesta['data'] = $data;
                $respuesta['files'] = $files;

                try{
                    $mysql = new Database('vtgsa_ventas');
                    $functions = new Functions();
                    $s3 = new AWSS3();

                    if (isset($data['id_usuario'])){
                        $id_usuario_asignado = $data['id_usuario'];

                        if (isset($data['canal_cierre'])){
                            $canal_respuesta = $data['canal_cierre'];

                            // Busca la informacion del registro 
                            $consulta = $mysql->Consulta_Unico("SELECT * FROM registros WHERE id_registro=".$id_registro);

                            if (isset($consulta['id_registro'])){
                                $documento_cliente = $consulta['documento'];

                                $nombre_archivo = "";
                                if (isset($files)){
                                    if (is_array($files)){
                                        if (count($files) > 0){
                                            foreach ($files as $key => $value) {

                                                /// CEDULA
                                                $tipo_de_archivo = 'cedula';
                                                if (isset($data['nombre_'.$tipo_de_archivo])){
                                                    $nombre_voucher = $data["nombre_".$tipo_de_archivo];
                                                    $archivo_temporal = $files[$tipo_de_archivo]->file;
                                                    $tmp = $functions->Archivo_Temporal($nombre_voucher, $archivo_temporal, $tipo_de_archivo);                                    
                                                    if ($tmp['estado']){                                    
                                                        $nombre_archivo = BUCKET_FOLDER_FIRMADOS.$documento_cliente."_".$tmp['archivo']['nombre'];
            
                                                        $envios3 = $s3->saveFileS3($tmp['archivo']['tmp'], $nombre_archivo, BUCKET_MVEVIP);
                                                        if (file_exists($tmp['archivo']['tmp'])){
                                                            unlink($tmp['archivo']['tmp']);
                                                        }                                                

                                                        $actualizacion = $mysql->Modificar("UPDATE registros SET link_cedula=?, canal_respuesta=?, id_usuario_seguimiento=? WHERE id_registro=?", array($nombre_archivo, 
                                                        $canal_respuesta, $id_usuario_asignado, $id_registro));
                                                    }
                                                }

                                                /// CONTRATO
                                                $tipo_de_archivo = 'contrato';
                                                if (isset($data['nombre_'.$tipo_de_archivo])){
                                                    $nombre_voucher = $data["nombre_".$tipo_de_archivo];
                                                    $archivo_temporal = $files[$tipo_de_archivo]->file;
                                                    $tmp = $functions->Archivo_Temporal($nombre_voucher, $archivo_temporal, $tipo_de_archivo);                                    
                                                    if ($tmp['estado']){                                    
                                                        $nombre_archivo = BUCKET_FOLDER_FIRMADOS.$documento_cliente."_".$tmp['archivo']['nombre'];
            
                                                        $envios3 = $s3->saveFileS3($tmp['archivo']['tmp'], $nombre_archivo, BUCKET_MVEVIP);                                                                                        
                                                        if (file_exists($tmp['archivo']['tmp'])){
                                                            unlink($tmp['archivo']['tmp']);
                                                        }

                                                        $actualizacion = $mysql->Modificar("UPDATE registros SET link_contrato_firmado=?, canal_respuesta=?, id_usuario_seguimiento=? WHERE id_registro=?", array($nombre_archivo, $canal_respuesta, $id_usuario_asignado, $id_registro));
                                                    }
                                                }

                                                /// VOUCHER
                                                $tipo_de_archivo = 'voucher';
                                                if (isset($data['nombre_'.$tipo_de_archivo])){
                                                    $nombre_voucher = $data["nombre_".$tipo_de_archivo];
                                                    $archivo_temporal = $files[$tipo_de_archivo]->file;
                                                    $tmp = $functions->Archivo_Temporal($nombre_voucher, $archivo_temporal, $tipo_de_archivo);                                    
                                                    if ($tmp['estado']){                                    
                                                        $nombre_archivo = BUCKET_FOLDER_FIRMADOS.$documento_cliente."_".$tmp['archivo']['nombre'];
            
                                                        $envios3 = $s3->saveFileS3($tmp['archivo']['tmp'], $nombre_archivo, BUCKET_MVEVIP);                                                                                        
                                                        if (file_exists($tmp['archivo']['tmp'])){
                                                            unlink($tmp['archivo']['tmp']);
                                                        }

                                                        $actualizacion = $mysql->Modificar("UPDATE registros SET link_voucher_firmado=?, canal_respuesta=?, id_usuario_seguimiento=? WHERE id_registro=?", array($nombre_archivo, $canal_respuesta, $id_usuario_asignado, $id_registro));
                                                    }
                                                }
                                            }                                
                                        }
                                    }
                                }

                                // Verifica si estan los 3 archivos subidos
                                $verificacion = $mysql->Consulta_Unico("SELECT * FROM registros WHERE id_registro=".$id_registro);

                                if (isset($verificacion['id_registro'])){
                                    $link_cedula = $verificacion['link_cedula'];
                                    $link_contrato_firmado = $verificacion['link_contrato_firmado'];
                                    $link_voucher_firmado = $verificacion['link_voucher_firmado'];

                                    
                                    $fecha_cierre = date("Y-m-d H:i:s");
                                    $observaciones_cierre = "";
                                    $estado_cierre = 1;

                                    if (($link_cedula != '') && ($link_voucher_firmado != '')){
                                        $modificar = $mysql->Modificar("UPDATE registros SET canal_respuesta=?, fecha_cierre_caso=?, id_usuario_cierre=?, observaciones_cierre=?, estado_cierre=? WHERE id_registro=?", array($canal_respuesta, $fecha_cierre, $id_usuario_asignado, $observaciones_cierre, $estado_cierre, $id_registro));
                                    }
                                }

                                $respuesta['estado'] = true;

                            }else{
                                $respuesta['error'] = "No se encontro la informacion del registro.";
                            }
                        }else{
                            $respuesta['error'] = "No se ha detallado el canal de comunicación.";
                        }    
                    }else{
                        $respuesta['error'] = "Su sesion se ha caducado o existe problemas. Favor reinicie su sesión";
                    }
                    
                }catch(PDOException $e){                    
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/registros/archivos/{id_registro}/{tipo_archivo}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $id_registro = $request->getAttribute('id_registro');
                $tipo_archivo = $request->getAttribute('tipo_archivo');
                $respuesta['estado'] = false;                                            

                try{
                    $mysql = new Database('vtgsa_ventas');
                    $s3 = new AWSS3();

                    $consulta = $mysql->Consulta_Unico("SELECT * FROM registros WHERE id_registro=".$id_registro);

                    if (isset($consulta['id_registro'])){

                        $link = "";
                        switch (strtoupper($tipo_archivo)) {
                            case 'CEDULA':
                                $link = $consulta['link_cedula'];
                                break;
                            case 'CONTRATO':
                                $link = $consulta['link_contrato_firmado'];
                                break;
                            case 'VOUCHER':
                                $link = $consulta['link_voucher_firmado'];
                                break;
                        }
                        if ($link != ""){                            
                            $respuesta['link'] = $s3->get_url_file(BUCKET_MVEVIP, $link, 1);
                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = "No existe link o documento.";
                        }
                        
                    }else{
                        $respuesta['error'] = "No se ha encontrado informacion del registro.";
                    }                    
                    
                }catch(PDOException $e){                    
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->post("/registros/cerrar/{id_registro}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $id_registro = $request->getAttribute('id_registro');
                $data = $request->getParsedBody();

                $respuesta['estado'] = false;
                $respuesta['data'] = $data;

                try{
                    $mysql = new Database('vtgsa_ventas');

                    $consulta = $mysql->Consulta_Unico("SELECT * FROM registros WHERE id_registro=".$id_registro);

                    if (isset($consulta['id_registro'])){
                        $link_cedula = trim($consulta['link_cedula']);
                        $link_contrato_firmado = trim($consulta['link_contrato_firmado']);
                        $link_voucher_firmado = trim($consulta['link_voucher_firmado']);

                        if ((!empty($link_cedula)) && (!empty($link_contrato_firmado)) && (!empty($link_voucher_firmado))){

                            $fecha_cierre = date("Y-m-d H:i:s");
                            $id_usuario = 1;
                            $canal = $data['canal'];
                            $observaciones = $data['observaciones'];
                            $estado_cierre = 1;

                            $actualizacion = $mysql->Modificar("UPDATE registros SET canal_respuesta=?, fecha_cierre_caso=?, id_usuario_cierre=?, observaciones_cierre=?, estado_cierre=? WHERE id_registro=?", array($canal, $fecha_cierre, $id_usuario, $observaciones, $estado_cierre, $id_registro));

                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = "Debe ingresar los 3 documentos para poder cerrar el caso.";
                        }
                    }else{
                        $respuesta['error'] = "No se encuentra el registro.";
                    }                    
                    
                }catch(PDOException $e){                    
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->put("/registros/contactado/{id_registro}/{id_usuario}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $id_registro = $request->getAttribute('id_registro');
                $id_usuario = $request->getAttribute('id_usuario');
                
                $respuesta['estado'] = false;                

                try{
                    $mysql = new Database('vtgsa_ventas');

                    $consulta = $mysql->Consulta_Unico("SELECT * FROM registros WHERE id_registro=".$id_registro);

                    if (isset($consulta['id_registro'])){                        
                        

                        if ($consulta['id_usuario_seguimiento'] == 0){

                            $actualizacion = $mysql->Modificar("UPDATE registros SET id_usuario_seguimiento=? WHERE id_registro=?", array($id_usuario, $id_registro));
                            $respuesta['estado'] = true;

                        }else{
                            $respuesta['error'] = "El cliente ya ha sido contactado por otro agente.";
                        }                        
                    }else{
                        $respuesta['error'] = "No se encuentra el registro.";
                    }                    
                    
                }catch(PDOException $e){                    
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

    
            // RULETA RUSA
            
            $app->get("/ruleta", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;

                $resultados = [];

                try{
                    $mysql = new Database('vtgsa_ventas');
                    
                    // Busca 8 mejores ventas realizadas el dia de ayer
                    $ayer = date('Y-m-d', strtotime("-1 days"));
                    // $ayer = date('Y-m-d');

                    $ventas = $mysql->Consulta("SELECT R.id_vendedor, V.vendedor, V.imagen, SUM(P.valor_voucher) AS total FROM registros_pagos P, registros R, vendedores V 
                    WHERE ((R.fecha_venta='".$ayer."') AND (R.id_vendedor!=33)) AND (P.id_registro=R.id_registro) AND (R.id_vendedor=V.id_vendedor) 
                    GROUP BY R.id_vendedor ORDER BY SUM(P.valor_voucher) DESC LIMIT 8");

                    $filtrado = [];
                    if (is_array($ventas)){
                        if (count($ventas) > 0){
                            foreach ($ventas as $linea_venta) {
                                $link = "";
                                if ($linea_venta['imagen'] != ''){
                                    $link = "https://ventas.mvevip.com/php/asesores/".$linea_venta['imagen'];
                                }
                                array_push($filtrado, array(
                                    "id_vendedor" => (int) $linea_venta['id_vendedor'],
                                    "vendedor" => $linea_venta['vendedor'],
                                    "imagen" => $link,
                                    "valor" => (float) $linea_venta['total'],
                                    "estado" => false
                                ));
                            }
                        }
                    }

                    $aleatoriamente = shuffle($filtrado);
                    
                    $respuesta['consulta'] = $filtrado;

                    $respuesta['estado'] = true;                                            
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/ruleta/{id}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $id = $request->getAttribute('id');
                $respuesta['estado'] = false;

                $resultados = [];

                try{
                    $mysql = new Database('vtgsa_ventas');
                    $whatsaapp = new Whatsapp();

                    $vendedor = $mysql->Consulta_Unico("SELECT * FROM vendedores WHERE id_vendedor=".$id);

                    if (isset($vendedor['id_vendedor'])){
                        $vendedor = $vendedor['vendedor']; 

                        $respuesta['envio'] = $whatsaapp->envio("0958978745", $vendedor." has sido el ganador. Tienes 1 minuto para confirmar tu asistencia.");
    
                        $respuesta['estado'] = true;
                    }
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/ruleta/asistencia/{id_vendedor}/{valor}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $id_vendedor = $request->getAttribute('id_vendedor');
                $valor = $request->getAttribute('valor');
                $respuesta['estado'] = false;

                $resultados = [];

                try{
                    $mysql = new Database('vtgsa_ventas');
                    $whatsaapp = new Whatsapp();

                    $vendedor = $mysql->Consulta_Unico("SELECT * FROM vendedores WHERE id_vendedor=".$id_vendedor);

                    if (isset($vendedor['id_vendedor'])){
                        $vendedor = $vendedor['vendedor']; 

                        if ($valor == 0){ // No esta presente
                            $respuesta['envio'] = $whatsaapp->envio("0958978745", $vendedor." has perdido. Para la próxima debes estar puntualmente para poder ganar.");
                        }else if($valor == 1){ // Esta Presente
                            $respuesta['envio'] = $whatsaapp->envio("0958978745", $vendedor." has sido premiado por tu puntualidad. Felicidades!");
                        }
                        
                        $respuesta['estado'] = true;
                    }
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });
    
            // BASE DE CLIENTES PARA LOS ASESORES

            $app->get("/procesar_base_nueva", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');                
                $respuesta['estado'] = false;                

                try{
                    $son_urgente = false;

                    $mysql = new Database("vtgsa_ventas");
                    $carpeta = __DIR__."/../../public/tmp/";

                    $archivo_temporal = "basecargar.csv";

                    // if (!file_exists($carpeta)){
                    //     // Si no existe la carpeta la crea
                    //     mkdir($carpeta, 0777, true);
                    // }        
                    // $nombre_archivo = "file".date("YmdHis").".csv";
                    // $destino = $carpeta.'/'.$nombre_archivo;
                    // move_uploaded_file($archivo_temporal, $destino);


                    $destino = $carpeta.'/'.$archivo_temporal;

                    // # La longitud máxima de la línea del CSV. Si no la sabes,
                    // # ponla en 0 pero la lectura será un poco más lenta
                    $longitudDeLinea = 2500;
                    $delimitador = ";"; # Separador de columnas
                    $caracterCircundante = "'"; # A veces los valores son encerrados entre comillas   
                    
                    if (file_exists($destino)){
                        // # Abrir el archivo
                        $gestor = fopen($destino, "r");
                        if (!$gestor) {
                            $respuesta['error'] = "No se puede abrir el archivo";                        
                        }

                        $resultados = array(
                            "procesados" => array(
                                "total" => 0 
                            ),
                            "no_procesados" => array(
                                "total" => 0,
                                "errores" => []
                            ),
                            "actualizados" => array(
                                "total" => 0,
                                "mensaje" => []
                            )
                        );
                        
                        $numeroDeFila = 1;                    
                        while (($fila = fgetcsv($gestor, $longitudDeLinea, $delimitador, $caracterCircundante)) !== false) {
                            if ($numeroDeFila >= 2) {
                                $banco = utf8_decode(trim($fila[0]));
                                $identificador = utf8_decode(trim($fila[1]));
                                $documento = utf8_decode(trim($fila[2]));
                                $nombres = utf8_decode(trim($fila[3]));

                                $telefono1 = "0".utf8_decode(trim($fila[4]));
                                $telefono2 = "0".utf8_decode(trim($fila[5]));
                                $telefono3 = "0".utf8_decode(trim($fila[6]));
                                $telefono4 = "0".utf8_decode(trim($fila[7]));
                                $telefono5 = "0".utf8_decode(trim($fila[8]));
                                $telefono6 = "0".utf8_decode(trim($fila[9]));

                                $celular_principal = "";
                                if ((trim($telefono1) != "") && (strlen(trim($telefono1)) == 10)){
                                    $celular_principal = $telefono1;
                                }else if ((trim($telefono2) != "") && (strlen(trim($telefono2)) == 10)){
                                    $celular_principal = $telefono2;
                                }else if ((trim($telefono3) != "") && (strlen(trim($telefono3)) == 10)){
                                    $celular_principal = $telefono3;
                                }else if ((trim($telefono4) != "") && (strlen(trim($telefono4)) == 10)){
                                    $celular_principal = $telefono4;
                                }else if ((trim($telefono5) != "") && (strlen(trim($telefono5)) == 10)){
                                    $celular_principal = $telefono5;
                                }else if ((trim($telefono6) != "") && (strlen(trim($telefono6)) == 10)){
                                    $celular_principal = $telefono6;
                                }

                                $ciudad = utf8_decode(trim($fila[10]));
                                $direccion = utf8_decode(trim($fila[11]));
                                $correo = utf8_decode(trim($fila[12]));
                                $observaciones = utf8_decode(trim($fila[13]));
                                $urgente = utf8_decode(trim($fila[14]));

                                $estado_lista = 0;
                                if (isset($fila[15])){
                                    $estado_lista = utf8_decode(trim($fila[15]));
                                }

                                if (intval($urgente) > 0){
                                    $son_urgente = true;
                                }else{
                                    $son_urgente = false;
                                }

                                $consulta = $mysql->Consulta_Unico("SELECT * FROM notas_registros WHERE (documento='".$documento."') AND (banco=".$banco.")");

                                if (trim($documento) != ""){
                                    if (!isset($consulta['id_lista'])){
                                        // ingresar cliente nuevo
                                        $id_notas = 0;
                                        $asignado = 0;
                                        $llamado = 0;
                                        $orden = 0;
                                        $fecha_prox_llamada = null;
                                        $hora_prox_llamada = "00:00:00";
                                        $fecha_alta = date("Y-m-d H:i:s");
                                        $fecha_modificacion = $fecha_alta;
                                        $estado = $estado_lista;

                                        $id_lista = $mysql->Ingreso("INSERT INTO notas_registros (banco, identificador, documento, nombres, telefono, ciudad, direccion, correo, observaciones, id_notas, asignado, llamado, orden, fecha_prox_llamada, hora_prox_llamada, fecha_alta, fecha_modificacion, estado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", array($banco, $identificador, $documento, $nombres, $celular_principal, $ciudad, $direccion, $correo, $observaciones, $id_notas, $asignado, $llamado, $orden, $fecha_prox_llamada, $hora_prox_llamada, $fecha_alta, $fecha_modificacion, $estado));

                                        if ((trim($telefono1) != "") && (strlen(trim($telefono1)) >= 9)){
                                            $id_contacto = $mysql->Ingreso("INSERT INTO notas_registros_contactos (id_lista, contacto, estado) VALUES (?,?,?)", array($id_lista, $telefono1, 0));
                                        }
                                        if ((trim($telefono2) != "") && (strlen(trim($telefono2)) >= 9)){
                                            $id_contacto = $mysql->Ingreso("INSERT INTO notas_registros_contactos (id_lista, contacto, estado) VALUES (?,?,?)", array($id_lista, $telefono2, 0));
                                        }
                                        if ((trim($telefono3) != "") && (strlen(trim($telefono3)) >= 9)){
                                            $id_contacto = $mysql->Ingreso("INSERT INTO notas_registros_contactos (id_lista, contacto, estado) VALUES (?,?,?)", array($id_lista, $telefono3, 0));
                                        }
                                        if ((trim($telefono4) != "") && (strlen(trim($telefono4)) >= 9)){
                                            $id_contacto = $mysql->Ingreso("INSERT INTO notas_registros_contactos (id_lista, contacto, estado) VALUES (?,?,?)", array($id_lista, $telefono4, 0));
                                        }
                                        if ((trim($telefono5) != "") && (strlen(trim($telefono5)) >= 9)){
                                            $id_contacto = $mysql->Ingreso("INSERT INTO notas_registros_contactos (id_lista, contacto, estado) VALUES (?,?,?)", array($id_lista, $telefono5, 0));
                                        }
                                        if ((trim($telefono6) != "") && (strlen(trim($telefono6)) >= 9)){
                                            $id_contacto = $mysql->Ingreso("INSERT INTO notas_registros_contactos (id_lista, contacto, estado) VALUES (?,?,?)", array($id_lista, $telefono6, 0));
                                        }

                                        $resultados['procesados']['total'] += 1;
                                    }else{
                                        $id_lista = $consulta['id_lista'];
                                        if ($son_urgente){
                                            $mensaje_actualizacion = "Se aplico prioridad urgente #".$urgente;
                                            $modificar = $mysql->Modificar("UPDATE notas_registros SET urgente=?, telefono=?, ciudad=?, direccion=? WHERE id_lista=?", array($urgente, $celular_principal, $ciudad, $direccion, $id_lista));
                                            // $modificar = $mysql->Modificar("UPDATE notas_registros SET urgente=?, telefono=?, ciudad=?, direccion=?, observaciones=?, estado=? WHERE id_lista=?", array($urgente, $celular_principal, $ciudad, $direccion, $observaciones, 0, $id_lista));

                                            if ($celular_principal == ""){
                                                $modificar = $mysql->Modificar("UPDATE notas_registros SET estado=? WHERE id_lista=?", array(12, $id_lista));
                                                $mensaje_actualizacion = "Se deshabilito por no tener completa la informacion. Celular de contacto incompleto";
                                            }

                                            array_push($resultados['actualizados']['mensaje'], array(
                                                "documento" => $documento,
                                                "id_lista" => $id_lista,
                                                "mensaje" => $mensaje_actualizacion
                                            ));
                                            
                                            $resultados['actualizados']['total'] += 1;
                                        }else{
                                            array_push($resultados['no_procesados']['errores'], array(
                                                "documento" => $documento,
                                                "mensaje" => "Ya existe. Cliente duplicado"
                                            ));
                                            $resultados['no_procesados']['total'] += 1;
                                        }                                        
                                    }
                                }else{                                
                                    array_push($resultados['no_procesados']['errores'], array(
                                        "documento" => $documento,
                                        "mensaje" => "No hay suficiente informacion para agregar"
                                    ));
                                    $resultados['no_procesados']['total'] += 1;
                                }
                            }
                            $numeroDeFila++;                        
                        }

                        fclose($gestor);
                        // unlink($destino);  

                        $respuesta['estado'] = true;
                        $respuesta['resultados'] = $resultados;
                    }else{
                        $respuesta['error'] = "No existe el archivo";
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/informacion/{id_asesor}", function(Request $request, Response $response){ 
                $authorization = $request->getHeader('Authorization');
                $id_asesor = $request->getAttribute('id_asesor');
                $respuesta['estado'] = false;

                try{
                    $mysql = new Database('vtgsa_ventas');

                    $consulta = $mysql->Consulta_Unico("SELECT * FROM usuarios WHERE id_usuario=".$id_asesor);

                    if (isset($consulta['id_usuario'])){

                        $respuesta['consulta'] = array(
                            "id_usuario" => (int) $consulta['id_usuario'],
                            "nombres" => $consulta['nombres']
                        );
                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No se obtuvo información del asesor.";
                    }
                    
                   
                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/bases", function(Request $request, Response $response){ 
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();

                try{
                    $mysql = new Database('vtgsa_ventas');

                    $bancos = $mysql->Consulta("SELECT * FROM notas_registros_bancos WHERE estado=0");
                    $resultados = [];

                    if (is_array($bancos)){
                        if (count($bancos) > 0){
                            foreach ($bancos as $linea) {
                                
                                // $lista_identificadores = [];
                                // if ($linea['id_banco'] == 13){
                                //     $identificadores = $mysql->Consulta("SELECT
                                //     identificador, COUNT(identificador) AS total
                                //     FROM notas_registros
                                //     WHERE banco=".$linea['id_banco']."
                                //     GROUP BY identificador
                                //     ORDER BY fecha_alta DESC");

                                //     if (is_array($identificadores)){
                                //         if (count($identificadores) > 0){
                                //             foreach ($identificadores as $linea_identificador) {
                                //                 array_push($lista_identificadores, array(
                                //                     "identificador" => $linea_identificador['identificador']
                                //                 ));
                                //             }
                                //         }
                                //     }
                                // }

                                array_push($resultados, array(
                                    "id_banco" => (int) $linea['id_banco'],
                                    "banco" => $linea['banco']
                                ));
                            }
                        }
                    }

                    $respuesta['consulta'] = $resultados;
                    $respuesta['estado'] = true;

                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/bases-identificadores/{id_banco}", function(Request $request, Response $response){ 
                $authorization = $request->getHeader('Authorization');
                $id_banco = $request->getAttribute('id_banco');

                try{
                    $mysql = new Database('vtgsa_ventas');

                    $lista_identificadores = [];
                    if ($id_banco == 13){
                        $identificadores = $mysql->Consulta("SELECT
                        identificador, COUNT(identificador) AS total
                        FROM notas_registros
                        WHERE banco=13
                        GROUP BY identificador
                        ORDER BY identificador DESC");

                        if (is_array($identificadores)){
                            if (count($identificadores) > 0){
                                foreach ($identificadores as $linea_identificador) {
                                    array_push($lista_identificadores, array(
                                        "identificador" => $linea_identificador['identificador']
                                    ));
                                }
                            }
                        }
                    }

                    $respuesta['id_banco'] = $id_banco;
                    $respuesta['consulta'] = $lista_identificadores;
                    $respuesta['estado'] = true;

                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/cupos/{id_asesor}/{banco}/{identificador}", function(Request $request, Response $response){ 
                $authorization = $request->getHeader('Authorization');
                $id_asesor = $request->getAttribute('id_asesor');
                $banco = $request->getAttribute('banco');
                $identificador = $request->getAttribute('identificador');
                $params = $request->getQueryParams();

                try{
                    $mysql = new Database('vtgsa_ventas');

                    // Consulta cupo dependiendo la base elegida
                    $cupo_total = 0;
                    
                    $consulta_cupo = $mysql->Consulta("SELECT * FROM notas_registros_cupos WHERE (id_banco=".$banco.") AND (id_usuario=".$id_asesor.")");
                    if (is_array($consulta_cupo)){
                        if (count($consulta_cupo) > 0){
                            foreach ($consulta_cupo as $linea) {
                                $cupo_call = intval($linea['cupo']);

                                $listado = $mysql->Consulta_Unico("SELECT COUNT(id_lista) AS total FROM notas_registros WHERE (asignado=".$id_asesor.") AND (identificador='".$linea['id_identificador']."') AND (banco=".$banco.") AND ((estado>0) AND (estado<10))");

                                $total_marcados = 0;
                                if (isset($listado['total'])){
                                    $total_marcados = $listado['total'];
                                }

                                $diferencia = $cupo_call - $total_marcados;

                                if ($diferencia > 0){
                                    $cupo_total += $diferencia;
                                }
                            }
                        }
                    }
                    $color = "light";

                    if (($cupo_total <= 10) && ($cupo_total > 0)){
                        $color = "warning";
                    }else if ($diferencia <=0){
                        $color = "danger";
                        $cupo_total = 0;
                    }

                    $respuesta['mensaje'] = $cupo_total;
                    $respuesta['color'] = $color;
                    $respuesta['estado'] = true;
                   
                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });
            
            $app->get("/bases/{banco}", function(Request $request, Response $response){ 
                $authorization = $request->getHeader('Authorization');
                $banco = $request->getAttribute('banco');

                try{
                    $mysql = new Database('vtgsa_ventas');

                    $bancos = $mysql->Consulta("SELECT identificador, COUNT(identificador) AS total
                    FROM notas_registros
                    WHERE (banco=".$banco.")
                    GROUP BY identificador
                    ORDER BY identificador ASC");
                    $resultados = [];

                    if (is_array($bancos)){
                        if (count($bancos) > 0){
                            foreach ($bancos as $linea) {
                                array_push($resultados, array(
                                    "banco" => (int) $banco,
                                    "identificador" => $linea['identificador'],
                                    "total" => (int) $linea['total']
                                ));
                            }
                        }
                    }

                    $respuesta['consulta'] = $resultados;
                    $respuesta['estado'] = true;

                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/bases_estadisticas/{banco}/{identificador}", function(Request $request, Response $response){ 
                $authorization = $request->getHeader('Authorization');
                $banco = $request->getAttribute('banco');
                $identificador = $request->getAttribute('identificador');

                try{
                    $mysql = new Database('vtgsa_ventas');

                    $por_identificador = "";
                    if ((isset($identificador)) && (!empty($identificador))){
                        $por_identificador = " AND (identificador='".$identificador."')";
                    }

                    // ESTADISTICAS SEGUN BANCO
                    $consulta = $mysql->Consulta_Unico("SELECT COUNT(id_lista) AS total FROM notas_registros WHERE (estado<=9) AND (banco=".$banco.")".$por_identificador);
                    $total_base = 0;
                    if (isset($consulta['total'])){
                        $total_base = $consulta['total'];
                    }

                    $consulta = $mysql->Consulta_Unico("SELECT COUNT(id_lista) AS total FROM notas_registros WHERE (asignado>0) AND ((estado<=9) AND (estado>0)) AND (banco=".$banco.")".$por_identificador);
                    $total_asignado = 0;
                    if (isset($consulta['total'])){
                        $total_asignado = $consulta['total'];
                    }

                    $consulta = $mysql->Consulta_Unico("SELECT COUNT(id_lista) AS total FROM notas_registros WHERE (asignado>0) AND (estado=9) AND (banco=".$banco.")".$por_identificador);
                    $total_ventas = 0;
                    if (isset($consulta['total'])){
                        $total_ventas = $consulta['total'];
                    }

                    $consulta = $mysql->Consulta_Unico("SELECT COUNT(id_lista) AS total FROM notas_registros WHERE (asignado=0) AND (estado=0) AND (banco=".$banco.")".$por_identificador);
                    $total_por_asignar = 0;
                    if (isset($consulta['total'])){
                        $total_por_asignar = $consulta['total'];
                    }

                    $respuesta['estadisticas'] = array(
                        "total" => (int) $total_base,
                        "asignados" => (int) $total_asignado,
                        "ventas" => (int) $total_ventas,
                        "faltantes" => (int) $total_por_asignar,
                    );
                    
                    $respuesta['estado'] = true;

                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/base_asignada/{id_asesor}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_asesor = $request->getAttribute('id_asesor');

                try{
                    $mysql = new Database('vtgsa_ventas');

                    $vendidos_asesor = $mysql->Consulta_Unico("SELECT COUNT(id_lista) AS total FROM notas_registros WHERE (asignado=".$id_asesor.") AND (banco=2) AND ((identificador=5) OR (identificador=6) OR (identificador=7))");

                    $total_ventas = 0;
                    if (isset($vendidos_asesor['total'])){
                        $total_asignados = $vendidos_asesor['total'];
                    }

                    $respuesta['total_asignados'] = (int) $total_asignados;
                    $respuesta['estado'] = true;

                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/baseclientes/{id_asesor}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $id_asesor = $request->getAttribute('id_asesor');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;

                $resultados = [];

                try{
                    $mysql = new Database('vtgsa_ventas');
                    $funciones = new Functions();
                    // "telefono_marcar" => $funciones->Esconder_Numero(trim($llamar_ahora['telefono'])),

                    $verifica_asesor = $mysql->Consulta_Unico("SELECT id_usuario, nombres, cupo_call, estado FROM usuarios WHERE id_usuario=".$id_asesor);
                    
                    if (isset($verifica_asesor['id_usuario'])){
                        if ($verifica_asesor['estado'] == 0){
                            // $cupo_call = $verifica_asesor['cupo_call'];
                            $cupo_call = 0;

                            if (isset($params['banco'])){
                                $banco = $params['banco'];

                                $busca_identificador = "";
                                $busca_identificador_cupo = "";
                                $respuesta['iden'] = $params['identificador'];
                                if( (isset($params['identificador'])) && (!empty($params['identificador']))){
                                    $busca_identificador_cupo = "AND (id_identificador='".$params['identificador']."')";
                                    // if ($banco == 13){ // solamente se muestra para el banco MI BASE (13)
                                        $busca_identificador = "AND (identificador='".$params['identificador']."')";
                                    // }
                                }

                                // Consulta Cupos Disponibles en el banco Seleccionado
                                $consulta_cupo = $mysql->Consulta("SELECT * FROM notas_registros_cupos WHERE (id_banco=".$banco.") ".$busca_identificador_cupo." AND (id_usuario=".$id_asesor.") AND (cupo>0) ORDER BY cupo DESC");

                                $log = [];
                                
                                $identificador = "";
                                $seguir_asignando = false;
                                $cupo_total = 0;
                                if (is_array($consulta_cupo)){
                                    if (count($consulta_cupo) > 0){
                                        foreach ($consulta_cupo as $linea) {
                                            if ($linea['cupo'] > 0){

                                                if ($banco != 13){
                                                    $clientes_registrados_con_intentos = $mysql->Consulta_Unico("SELECT COUNT(id_lista) AS total FROM notas_registros WHERE ((asignado=".$id_asesor.") AND (banco=".$banco.") AND (identificador='".$linea['id_identificador']."'))");
                                                }else{
                                                    $clientes_registrados_con_intentos = $mysql->Consulta_Unico("SELECT COUNT(id_lista) AS total FROM notas_registros WHERE ((asignado=".$id_asesor.") AND (banco=".$banco.") AND (identificador='".$linea['id_identificador']."') AND (estado!=8))");
                                                }
                                                

                                                $total_reg_intentos = 0;
                                                if (isset($clientes_registrados_con_intentos['total'])){
                                                    $total_reg_intentos = intval($clientes_registrados_con_intentos['total']);
                                                }

                                                $diferencia = intval($linea['cupo']) - $total_reg_intentos;

                                                // Verifica si el banco e identificador tienen base disponible
                                                // $consulta_base_disponible = $mysql->Consulta_Unico("SELECT 
                                                // identificador, COUNT(identificador) AS total 
                                                // FROM notas_registros
                                                // WHERE (banco=".$banco.") AND (asignado=0) AND (llamado=0) AND (estado=0) AND (identificador='".$linea['id_identificador']."') GROUP BY identificador
                                                // ORDER BY COUNT(identificador) DESC");

                                                // if (isset($consulta_base_disponible['identificador'])){
                                                    // if ($consulta_base_disponible['total'] > 0){
                                                        if ($diferencia > 0){
                                                            $cupo_total += $diferencia;
                                                        }
        
                                                        if (empty($identificador)){
                                                            $identificador = $linea['id_identificador'];
                                                            $cupo_call = $linea['cupo'];
        
                                                            if ($diferencia > 0){
                                                                $seguir_asignando = true;
                                                            }else{
                                                                $identificador = "";
                                                                $cupo_call = 0;
                                                            }                                  
                                                        }
        
                                                        array_push($log, array(
                                                            "linea" => $linea,
                                                            "clientes_con_intentos" => $clientes_registrados_con_intentos,
                                                            "total_reg_intentos" => $total_reg_intentos,
                                                            "diferencia" => $diferencia,
                                                            "seguir_asignando" => $seguir_asignando,
                                                            "identificador" => $identificador,
                                                            "cupo_call" => $cupo_call
                                                        ));
                                                //     }
                                                // }
                                            }
                                        }
                                    }
                                }

                                // $respuesta['log'] = $log;
                                $respuesta['identificador'] = $identificador;
                                $respuesta['sigue'] = $seguir_asignando;
                                $respuesta['cupo_total'] = $cupo_total;

                                // PRIMERO BUSCA CONTACTOS A LOS QUE DE LES DEBE LLAMAR SEGUN EL HORARIO (LLAMAR MAS TARDE)
                                $fecha_hoy = date("Y-m-d");
                                $hora_hoy = date("H:i:s");
                                $llamar_ahora = $mysql->Consulta_Unico("SELECT * FROM notas_registros WHERE (banco=".$banco.") ".$busca_identificador." AND (asignado=".$id_asesor.") AND (fecha_prox_llamada='".$fecha_hoy."') AND (hora_prox_llamada<='".$hora_hoy."') AND (estado=1) ORDER BY hora_prox_llamada ASC, nombres ASC");

                                $respuesta['hoy'] = $llamar_ahora;
    
                                if (isset($llamar_ahora['id_lista'])){
                                    $resultados = array(
                                        "id_lista" => (int) $llamar_ahora['id_lista'],
                                        "documento" => trim($llamar_ahora['documento']),
                                        "nombres" => trim($llamar_ahora['nombres']),
                                        "telefono" => trim($llamar_ahora['telefono']),
                                        "telefono_marcar" => trim($llamar_ahora['telefono']),
                                        "ciudad" => trim($llamar_ahora['ciudad']),
                                        "direccion" => trim($llamar_ahora['direccion']),
                                        "correo" => trim($llamar_ahora['correo']),
                                        "observaciones" => trim($llamar_ahora['observaciones']),
                                        "estado" => $funciones->Obtener_Estado($llamar_ahora['estado']),
                                        "fecha_hora_llamada" => $llamar_ahora['fecha_prox_llamada']." ".$llamar_ahora['hora_prox_llamada'],
                                        "tipo" => "ahora"
                                    );
                                }

                                // BUSCA CONTACTOS QUE SE QUEDARON EN EL AIRE Y ESTAN ASIGNADOS, ES DECIR, EN ESTADO 0
                                if (count($resultados) == 0){
                                    $estado_cero = $mysql->Consulta_Unico("SELECT * FROM notas_registros WHERE (banco=".$banco.") ".$busca_identificador." AND (identificador='".$identificador."') AND (asignado=".$id_asesor.") AND (estado=0) ORDER BY orden ASC, nombres ASC");

                                    $respuesta['estado_cero'] = $estado_cero;

                                    if (isset($estado_cero['id_lista'])){
                                        $resultados = array(
                                            "id_lista" => (int) $estado_cero['id_lista'],
                                            "documento" => trim($estado_cero['documento']),
                                            "nombres" => trim($estado_cero['nombres']),
                                            "telefono" => trim($estado_cero['telefono']),
                                            "telefono_marcar" => trim($estado_cero['telefono']),
                                            "ciudad" => trim($estado_cero['ciudad']),
                                            "direccion" => trim($estado_cero['direccion']),
                                            "correo" => trim($estado_cero['correo']),
                                            "observaciones" => trim($estado_cero['observaciones']),
                                            "estado" => $funciones->Obtener_Estado($estado_cero['estado']),
                                            "tipo" => "aire"
                                        );
                                    }

                                }
                                
                                if (count($resultados) == 0){ // SI NO HAY POR LLAMAR Y NO TIENE CONTACTOS EN EL AIRE
                                    if ($seguir_asignando){
                                        if ($banco != 13){
                                            $base_clientes = $mysql->Consulta("SELECT * FROM notas_registros WHERE (banco=".$banco.") AND (identificador='".$identificador."') AND (asignado=0) AND (estado=0) ORDER BY urgente DESC, orden ASC, nombres ASC");
                                        }else{
                                            $base_clientes = $mysql->Consulta("SELECT * FROM notas_registros WHERE (banco=".$banco.") ".$busca_identificador." AND (asignado=".$id_asesor.") AND ((estado=0) OR (estado=8)) ORDER BY id_lista ASC");
                                        }

                                        $respuesta['sql'] = "SELECT * FROM notas_registros WHERE (banco=".$banco.") ".$busca_identificador." AND (asignado=0) AND (estado=0) ORDER BY urgente DESC, orden ASC, nombres ASC";
                                        $respuesta['base_clientes'] = $base_clientes;

                                        if (is_array($base_clientes)){
                                            $total_registros = count($base_clientes);
                                            if ($total_registros > 0){
                                                if ($total_registros == 1){
                                                    $aleatorio = 0;
                                                    $resultados = array(
                                                        "id_lista" => (int) $base_clientes[$aleatorio]['id_lista'],
                                                        "documento" => trim($base_clientes[$aleatorio]['documento']),
                                                        "nombres" => trim($base_clientes[$aleatorio]['nombres']),
                                                        "telefono" => trim($base_clientes[$aleatorio]['telefono']),
                                                        "telefono_marcar" => trim($base_clientes[$aleatorio]['telefono']),
                                                        "ciudad" => trim($base_clientes[$aleatorio]['ciudad']),
                                                        "direccion" => trim($base_clientes[$aleatorio]['direccion']),
                                                        "correo" => trim($base_clientes[$aleatorio]['correo']),
                                                        "observaciones" => trim($base_clientes[$aleatorio]['observaciones']),
                                                        "estado" => $funciones->Obtener_Estado($base_clientes[$aleatorio]['estado']),
                                                        "tipo" => "nuevo"
                                                    );
                                                }else{

                                                    if ($banco != 13){
                                                        $aleatorio = mt_rand(0, $total_registros); // selecciona aleatoriamente un cliente de la lista y asigna al asesor
        
                                                        $resultados = array(
                                                            "id_lista" => (int) $base_clientes[$aleatorio]['id_lista'],
                                                            "documento" => trim($base_clientes[$aleatorio]['documento']),
                                                            "nombres" => trim($base_clientes[$aleatorio]['nombres']),
                                                            "telefono" => trim($base_clientes[$aleatorio]['telefono']),
                                                            "telefono_marcar" => trim($base_clientes[$aleatorio]['telefono']),
                                                            "ciudad" => trim($base_clientes[$aleatorio]['ciudad']),
                                                            "direccion" => trim($base_clientes[$aleatorio]['direccion']),
                                                            "correo" => trim($base_clientes[$aleatorio]['correo']),
                                                            "observaciones" => trim($base_clientes[$aleatorio]['observaciones']),
                                                            "estado" => $funciones->Obtener_Estado($base_clientes[$aleatorio]['estado']),
                                                            "tipo" => "nuevo"
                                                        );
                                                    }else{
                                                        $aleatorio = 0;
                                                        $resultados = array(
                                                            "id_lista" => (int) $base_clientes[$aleatorio]['id_lista'],
                                                            "documento" => trim($base_clientes[$aleatorio]['documento']),
                                                            "nombres" => trim($base_clientes[$aleatorio]['nombres']),
                                                            "telefono" => trim($base_clientes[$aleatorio]['telefono']),
                                                            "telefono_marcar" => trim($base_clientes[$aleatorio]['telefono']),
                                                            "ciudad" => trim($base_clientes[$aleatorio]['ciudad']),
                                                            "direccion" => trim($base_clientes[$aleatorio]['direccion']),
                                                            "correo" => trim($base_clientes[$aleatorio]['correo']),
                                                            "observaciones" => trim($base_clientes[$aleatorio]['observaciones']),
                                                            "estado" => $funciones->Obtener_Estado($base_clientes[$aleatorio]['estado']),
                                                            "tipo" => "nuevo"
                                                        );
                                                    }
                                                    
                                                }
                                                $fecha_asignacion = date("Y-m-d H:i:s");
                                                $asigna = $mysql->Modificar("UPDATE notas_registros SET asignado=?, fecha_asignacion=? WHERE id_lista=?", array($id_asesor, $fecha_asignacion, $resultados['id_lista']));
                                            }else{
                                                $respuesta['error'] = "No existen mas registros en la base.";
                                            }
                                        }
                                    }else{
                                        // YA NO TIENE CUPO DE CLIENTES PARA ASIGNAR
                                        // ENTONCES BUSCA DE SU BASE DE CLIENTES ASIGNADOS QUE ESTEN EN APAGADOS O NO CONTESTA
                                        if (count($resultados) == 0){
                                            $estado_remarcados = $mysql->Consulta_Unico("SELECT * FROM notas_registros WHERE (banco=".$banco.") ".$busca_identificador." AND (asignado=".$id_asesor.") AND ((estado>1) AND (estado<4)) ORDER BY llamado ASC, fecha_modificacion ASC LIMIT 10");

                                            if (isset($estado_remarcados['id_lista'])){
                                                $resultados = array(
                                                    "id_lista" => (int) $estado_remarcados['id_lista'],
                                                    "documento" => trim($estado_remarcados['documento']),
                                                    "nombres" => trim($estado_remarcados['nombres']),
                                                    "telefono" => trim($estado_remarcados['telefono']),
                                                    "telefono_marcar" => trim($estado_remarcados['telefono']),
                                                    "ciudad" => trim($estado_remarcados['ciudad']),
                                                    "direccion" => trim($estado_remarcados['direccion']),
                                                    "correo" => trim($estado_remarcados['correo']),
                                                    "observaciones" => trim($estado_remarcados['observaciones']),
                                                    "estado" => $funciones->Obtener_Estado($estado_remarcados['estado']),
                                                    "tipo" => "remarcado"
                                                );
                                            }
                                        }
                                    }   
                                }

                                if (count($resultados) == 0){
                                    $resultados = array(
                                        "id_lista" => 0,
                                        "documento" => "S/N",
                                        "nombres" => "S/N",
                                        "telefono" => "S/N",
                                        "telefono_marcar" => "S/N",
                                        "ciudad" => "S/N",
                                        "direccion" => "S/N",
                                        "correo" => "S/N",
                                        "observaciones" => "S/N",
                                        "estado" => $funciones->Obtener_Estado(0),
                                        "tipo" => "sin_dato"
                                    );
                                }

                                $respuesta['consulta'] = $resultados;
                                $respuesta['estado'] = true;
                            }else{
                                $respuesta['error'] = "No se ha especificado el tipo de lista (banco)";
                            }
                        }else{
                            $respuesta['error'] = "Su usuario se encuentra deshabilitado.";
                        }
                    }else{
                        $respuesta['error'] = "Su usuario no se encuentra en el sistema.";
                    }
                        
                   
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/baseclientes_listados/{id_asesor}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $id_asesor = $request->getAttribute('id_asesor');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;

                $resultados = [];

                try{
                    $mysql = new Database('vtgsa_ventas');
                    $funciones = new Functions();
                    // "telefono_marcar" => $funciones->Esconder_Numero(trim($llamar_ahora['telefono'])),

                    $verifica_asesor = $mysql->Consulta_Unico("SELECT id_usuario, nombres, cupo_call, estado FROM usuarios WHERE id_usuario=".$id_asesor);
                    
                    if (isset($verifica_asesor['id_usuario'])){
                        if ($verifica_asesor['estado'] == 0){
                            // $cupo_call = $verifica_asesor['cupo_call'];
                            $cupo_call = 0;

                            if (isset($params['banco'])){
                                $banco = $params['banco'];

                                $identificador = "";
                                if (isset($params['identificador'])){
                                    $identificador = $params['identificador'];
                                }

                                $buscador = "";
                                if (isset($params['buscador'])){
                                    $buscador = $params['buscador'];
                                }

                                // Busca las listas de clientes y sus estados de acuerdo al asesor
                                $registros = $mysql->Consulta("SELECT * FROM notas_registros WHERE ((documento LIKE '%".$buscador."%') OR (nombres LIKE '%".$buscador."%')) AND (banco=".$banco.") AND (identificador LIKE '%".$identificador."%') AND (asignado=".$id_asesor.")");
    
                                $interesados = [];
                                $mas_tarde = [];
                                $apagado = [];
                                $no_contesta = [];
                                $mi_base = [];
                                $no_interesados = [];
                                $ventas = [];
                                $incorrectos = [];
                                $formularios = [];
                                $temporal = [];
                                if (is_array($registros)){
                                    if (count($registros) > 0){
                                        foreach ($registros as $linea) {
                                            $estado = $linea['estado'];
    
                                            $hora_actual = date("H:i:00");
    
                                            $estado_prox_llamada = false;
                                            if ($linea['hora_prox_llamada'] <= $hora_actual){
                                                $estado_prox_llamada = true;
                                            }
    
                                            // ultimo registro de llamada                                    
                                            // $ultimas_llamadas = $mysql->Consulta("SELECT * FROM notas_registros_llamadas WHERE id_lista=".$linea['id_lista']." ORDER BY id_log_llamada DESC LIMIT 2");
                                            // $diff = 0;
                                            // $ultima_llamada_registro = "";
                                            // if (is_array($ultimas_llamadas)){
                                            //     if (count($ultimas_llamadas) == 2){
                                            //         // sacar diferencia de tiempo
                                            //         $diff = strtotime($ultimas_llamadas[0]['fecha_hora']) - strtotime($ultimas_llamadas[1]['fecha_hora']);
                                            //         $ultima_llamada_registro = $ultimas_llamadas[0]['fecha_hora'];
                                            //     }
                                            // }            
                                            
                                            $diff = 0;

                                            $ultimo_contacto = "Sin Contactar";
                                            if (!is_null($linea['fecha_ultima_contacto'])){
                                                $ultimo_contacto = $linea['fecha_ultima_contacto'];
                                            }
    
                                            $datos = array(
                                                "id_lista" => (int) $linea['id_lista'],
                                                // "documento" => $linea['documento'],
                                                "nombres" => $linea['nombres'],
                                                // "telefono" => $linea['telefono'],
                                                // "ciudad" => $linea['ciudad'],
                                                // "correo" => $linea['correo'],
                                                "observaciones" => $linea['observaciones'],
                                                "llamado" => (int) $linea['llamado'],
                                                "fecha_prox_llamada" => $linea['fecha_prox_llamada'],
                                                "hora_prox_llamada" => $linea['hora_prox_llamada'],
                                                "fecha_asignacion" => $linea['fecha_asignacion'],
                                                "llamar_ahora" => $estado_prox_llamada,
                                                "llamadas" => array(
                                                    "ultima" => $ultimo_contacto, //$ultima_llamada_registro,
                                                    "duracion" => date("i:s", $diff)
                                                )
                                            );
    
                                            switch ($estado) {
                                                case 1:
                                                    array_push($mas_tarde, $datos);
                                                    break;
                                                case 2:
                                                    array_push($apagado, $datos);
                                                    break;
                                                case 3:
                                                    array_push($no_contesta, $datos);
                                                    break;
                                                case 4:
                                                    array_push($interesados, $datos);
                                                    break;
                                                case 8:
                                                    array_push($mi_base, $datos);
                                                    break;
                                                case 5:
                                                    array_push($no_interesados, $datos);
                                                    break;
                                                case 6:
                                                    array_push($incorrectos, $datos);
                                                    break;
                                                case 7:
                                                    array_push($ventas, $datos);
                                                    break;
                                                case 14:
                                                    array_push($formularios, $datos);
                                                    break;
                                                case 13:
                                                    array_push($temporal, $datos);
                                                    break;
                                            }
                                        }
                                    }
                                }
    
                                // Busca los paquetes para observaciones
                                $busca_paquetes = $mysql->Consulta("SELECT * FROM notas_registros_paquetes WHERE estado=0 ORDER BY id_paquete ASC");
                                $paquetes = [];
                                if (is_array($busca_paquetes)){
                                    if (count($busca_paquetes) > 0){
                                        foreach ($busca_paquetes as $linea) {
                                            array_push($paquetes, array(
                                                "id_paquete" => (int) $linea['id_paquete'],
                                                "paquete" => $linea['nombre']
                                            ));
                                        }
                                    }
                                }
    
                                $respuesta['registros']['interesados'] = $interesados;
                                $respuesta['registros']['mas_tarde'] = $mas_tarde;
                                $respuesta['registros']['apagado'] = $apagado;
                                $respuesta['registros']['no_contesta'] = $no_contesta;
                                $respuesta['registros']['mi_base'] = $mi_base;
                                $respuesta['registros']['incorrectos'] = $incorrectos;
                                $respuesta['registros']['no_interesados'] = $no_interesados;
                                $respuesta['registros']['ventas'] = $ventas;
                                $respuesta['registros']['formularios'] = $formularios;
                                $respuesta['registros']['temporal'] = $temporal;
                                $respuesta['paquetes'] = $paquetes;

                                $respuesta['estado'] = true;
                            }else{
                                $respuesta['error'] = "No se ha especificado el tipo de lista (banco)";
                            }
                        }else{
                            $respuesta['error'] = "Su usuario se encuentra deshabilitado.";
                        }
                    }else{
                        $respuesta['error'] = "Su usuario no se encuentra en el sistema.";
                    }
                        
                   
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/guarda_extension/{id_asesor}/{extension}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $id_asesor = $request->getAttribute('id_asesor');
                $extension = $request->getAttribute('extension');
                $respuesta['estado'] = false;

                try{
                    $mysql = new Database('vtgsa_ventas');

                    $guarda_extension = $mysql->Modificar("UPDATE usuarios SET extension=? WHERE id_usuario=?", array($extension, $id_asesor));

                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/baseclientes_telefonos/{id_lista}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $id_lista = $request->getAttribute('id_lista');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;

                $resultados = [];                

                try{
                    $mysql = new Database('vtgsa_ventas');
                    $funciones = new Functions();

                    $mas_contactos = $mysql->Consulta("SELECT * FROM notas_registros_contactos WHERE (id_lista=".$id_lista.") AND (estado=0)");
                    $resultados = [];

                    if (is_array($mas_contactos)){
                        if (count($mas_contactos) > 0){
                            foreach ($mas_contactos as $linea) {
                                array_push($resultados, array(
                                    "id_contacto" => (int) $linea['id_contacto'],
                                    "contacto" => $linea['contacto'],
                                    "contacto_llamar" => $funciones->Esconder_Numero(trim($linea['contacto']))
                                ));
                            }
                        }
                    }
                    
                    $respuesta['consulta'] = $resultados;
                    $respuesta['estado'] = true;                
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/baseclientes/{id_asesor}/{id_lista}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $id_asesor = $request->getAttribute('id_asesor');
                $id_lista = $request->getAttribute('id_lista');
                $respuesta['estado'] = false;

                $resultados = [];

                try{
                    $mysql = new Database('vtgsa_ventas');

                    $base_clientes = $mysql->Consulta_Unico("SELECT * FROM notas_registros WHERE (asignado=".$id_asesor.") AND (id_lista=".$id_lista.")");

                    if (isset($base_clientes['id_lista'])){
                        $resultados = array(
                            "id_lista" => (int) $base_clientes['id_lista'],
                            "documento" => $base_clientes['documento'],
                            "nombres" => $base_clientes['nombres'],
                            "telefono" => $base_clientes['telefono'],
                            "telefono_marcar" => $base_clientes['telefono'],
                            "ciudad" => $base_clientes['ciudad'],
                            "correo" => $base_clientes['correo'],
                            "direccion" => $base_clientes['direccion'],
                            "observaciones" => $base_clientes['observaciones']                                    
                        );                        
                        
                        $respuesta['consulta'] = $resultados;
                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No se encontro informacion del cliente";
                    }
                                                   
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/log_llamadas/{id_lista}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $id_lista = $request->getAttribute('id_lista');
                $respuesta['estado'] = false;
                
                $resultados = [];

                try{
                    $mysql = new Database('vtgsa_ventas');
                    $fecha_hoy = date("Y-m-d 05:00:00");                    

                    $lista_logs = $mysql->Consulta("SELECT * FROM notas_registros_llamadas WHERE (id_lista=".$id_lista.") AND (fecha_hora>='".$fecha_hoy."') ORDER BY fecha_hora DESC LIMIT 10");

                    $resultados = [];

                    if (is_array($lista_logs)){
                        if (count($lista_logs) > 0){
                            foreach ($lista_logs as $linea) {
                                array_push($resultados, array(
                                    "id" => (int) $linea['id_log_llamada'],
                                    "descripcion" => $linea['descripcion'],
                                    "fecha_hora" => $linea['fecha_hora']
                                ));
                            }
                        }
                    }

                    $respuesta['consulta'] = $resultados;

                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->post("/log_llamadas/{id_lista}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $id_lista = $request->getAttribute('id_lista');
                $data = $request->getParsedBody();
                $respuesta['estado'] = false;

                $respuesta['data'] = $data;

                try{
                    $mysql = new Database('vtgsa_ventas');

                    $descripcion = $data['descripcion'];
                    $fecha_hora = date("Y-m-d H:i:s");
                    $estado = 0;

                    /// Segun el id_lista busca el id del asesor asignado
                    $buscar = $mysql->Consulta_Unico("SELECT id_lista, asignado FROM notas_registros WHERE id_lista=".$id_lista);

                    $asignado = 0;
                    if (isset($buscar['id_lista'])){
                        $asignado = $buscar['asignado'];
                    }

                    $id_log = $mysql->Ingreso("INSERT INTO notas_registros_llamadas (id_lista, id_usuario, descripcion, fecha_hora, estado) VALUES (?,?,?,?,?)", array($id_lista, $asignado, $descripcion, $fecha_hora, $estado));

                    if ($descripcion == "Llamada iniciada"){
                        $fecha_intento_llamada = date("Y-m-d H:i:s");
                        $actualizacion = $mysql->Modificar("UPDATE notas_registros SET llamado=llamado+?, fecha_modificacion=? WHERE id_lista=?", array(1, $fecha_intento_llamada, $id_lista));
                    }
                    
                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/llamadas_cuadro", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $id_lista = $request->getAttribute('id_lista');
                $respuesta['estado'] = false;
                
                $resultados = [];

                try{
                    $mysql = new Database('vtgsa_ventas');
                    
                    $consulta = $mysql->Consulta("SELECT COUNT(R.asignado) AS total, R.asignado, U.nombres FROM notas_registros R, usuarios U WHERE ((R.asignado>0) AND (R.estado>0)) AND (R.asignado=U.id_usuario) GROUP BY R.asignado ORDER BY COUNT(R.asignado) DESC");

                    $respuesta['consulta'] = $consulta;

                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/enviar_info_whatsapp/{celular}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');                
                $celular = $request->getAttribute('celular');
                $respuesta['estado'] = false;
                
                $resultados = [];

                try{
                    $whatsaapp = new Whatsapp();

                    $respuesta['envio'] = $whatsaapp->envio($celular, "Mensaje automatico al cliente.");

                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->post("/guardar_estado/{id_lista}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');                
                $id_lista = $request->getAttribute('id_lista');
                $data = $request->getParsedBody();
                $respuesta['estado'] = false;
                
                $respuesta['data'] = $data;

                try{
                    $mysql = new Database('vtgsa_ventas');

                    $verifica = $mysql->Consulta_Unico("SELECT * FROM notas_registros WHERE id_lista=".$id_lista);

                    if (isset($verifica['id_lista'])){
                        $id_asesor = $verifica['asignado'];
                        $impreso = $verifica['impreso']; // ESTE CAMPO OCUPAR PARA VALIDAR QUE LLAME O NO OBLIGATORIAMENTE... USADA PARA NO USAR SIP.
                        $intentos_llamado = $verifica['llamado'];

                        $estado = $data['estado'];
                        $observaciones = $data['observaciones'];
                        $hora = $data['hora'];                        
                        $fecha = $data['fecha'];                        
                        $direccion = trim($data['direccion']);
                        $ciudad = trim($data['ciudad']);
                        $id_delivery = 0;
                        
                        $opciones = $data['opciones'];
                        $opcionOP1 = 0;
                        $opcionOP2 = 0;
                        $opcionOP3 = 0;
                        $opcionOP4 = 0;
                        if (isset($opciones['opcionOP1'])){
                            $opcionOP1 = (bool) $opciones['opcionOP1'];
                        }
                        if (isset($opciones['opcionOP2'])){
                            $opcionOP2 = (bool) $opciones['opcionOP2'];
                        }
                        if (isset($opciones['opcionOP3'])){
                            $opcionOP3 = (bool) $opciones['opcionOP3'];
                        }
                        if (isset($opciones['opcionOP4'])){
                            $opcionOP4 = (bool) $opciones['opcionOP4'];
                        } 

                        $opcionNoDesea = 0;
                        if (isset($data['opcionNoDesea'])){
                            $opcionNoDesea = $data['opcionNoDesea'];
                        } 

                        $opcionesRegistradas = $opcionOP1."|".$opcionOP2."|".$opcionOP3."|".$opcionOP4;

                        $deja_pasar = true;
                        if ($estado != 4){
                            $deja_pasar = true;                            
                        }else{
                            if ((!empty($direccion)) && (!empty($ciudad))){
                                $deja_pasar = true; 
                            }else{
                                $respuesta['error'] = "Debe confirmar la ciudad y dirección de entrega.";
                            }
                        }
                        
                        if ($deja_pasar){
                            $fecha_actual = date("Y-m-d");
                            $valida_fecha = false;
                            if ($estado == 1){ // solo si es llamar mas tarde

                                // VALIDA QUE LA FECHA SEA MAYOR O IGUAL A LA ACTUAL
                                // VALIDAR HORA DE LLAMADA ENTRE 09 DE 19
                                if ($fecha >= $fecha_actual){
                                    $hora_inicial = strtotime("09:00:00");
                                    $hora_final = strtotime("21:00:00");
                                    $hora_apuntada = strtotime($hora);
                                    $hora_actual = strtotime(date("H:i:s"));

                                    if ($fecha > $fecha_actual){
                                        if ( ($hora_apuntada >= $hora_inicial) && ($hora_apuntada <= $hora_final) ){
                                            $valida_fecha = true;
                                        }else{
                                            $respuesta['error'] = "La hora de llamada debe ser entre las 09:00 y 21:00.";
                                        }
                                    }else{
                                        if ( (($hora_apuntada >= $hora_inicial) && ($hora_apuntada <= $hora_final)) && ($hora_apuntada > $hora_actual) ){
                                            $valida_fecha = true;
                                        }else{
                                            $respuesta['error'] = "La hora de llamada debe ser entre las 09:00 y 21:00.";
                                        }
                                    }
                                    
                                }else{
                                    $respuesta['error'] = "La fecha para la llamada debe ser igual o mayor a la actual.";
                                }
                            }else{
                                $valida_fecha = true;
                            }

                            if ($valida_fecha){

                                $valida_llamada_tiempo_minimo = false;

                                // VALIDAR QUE EL TIEMPO ENTRE LAS DOS ULTIMAS LLAMADAS SEAN 30 SEGUNDOS
                                $ultimas_llamadas = $mysql->Consulta("SELECT * FROM notas_registros_llamadas WHERE id_lista=".$id_lista." ORDER BY id_log_llamada DESC LIMIT 2");
                                // $respuesta['asdfad'] = $ultimas_llamadas;
                                if (is_array($ultimas_llamadas)){
                                    if (count($ultimas_llamadas) == 2){
                                        // sacar diferencia de tiempo
                                        $diff = strtotime($ultimas_llamadas[0]['fecha_hora']) - strtotime($ultimas_llamadas[1]['fecha_hora']);
                                        $respuesta['segundos'] = $diff;
                                        // if ($diff >= 1){ // si es mayor a 15 segundos entonces si contesto
                                            $valida_llamada_tiempo_minimo = true;
                                        // }
                                    }else{
                                        $valida_llamada_tiempo_minimo = true; // aqui validar q siempre haya las dos llamadas de inicio y fin y sino mandar notificacion para reinicio en pagina
                                    }
                                }
                                                            
                                $siguiente_cliente = false;
                                $respuesta['impreso'] = $impreso;
                                if ($impreso == 0){
                                    if ($valida_llamada_tiempo_minimo){
                                        $respuesta['adfasdf'] = $estado;
                                        $respuesta['intentos'] = $intentos_llamado;
                                        if (($estado == 2) || ($estado == 3)){
                                            if ($intentos_llamado >= 2){
                                                $siguiente_cliente = true;
                                            }else{
                                                $respuesta['error'] = "Debe tener por lo menos 2 intentos de llamada al cliente.";
                                            }
                                        }else{
                                            $siguiente_cliente = true;
                                        }
                                    }else{
                                        $respuesta['error'] = "No se han registrado llamadas previas, o el tiempo de la ultima llamada es muy corta. Debe intentar nuevamente.";
                                    }
                                }else if($impreso == 2){
                                    $siguiente_cliente = true;
                                }
                                
                                $siguiente_cliente = true;

                                if ($siguiente_cliente){

                                    // SI EL ESTADO ES VENTA REALIZADA (7) => VALIDA QUE SE INGRESE LOS DATOS DE LA TARJETA
                                    $finalizaModificacion = false;
                                    if ($estado == 7){
                                        if (isset($data['tarjeta'])){
                                            $tarjeta = $data['tarjeta'];

                                            if (((isset($tarjeta['numero'])) && (!empty($tarjeta['numero']))) && ((isset($tarjeta['fecha'])) && (!empty($tarjeta['fecha']))) && ((isset($tarjeta['cvc'])) && (!empty($tarjeta['cvc'])))){
                                                $lenTarjeta = strlen($tarjeta['numero']);

                                                // if (($lenTarjeta >=13) && ($lenTarjeta <=16)){
                                                    $numeroTarjeta = base64_encode($tarjeta['numero']);
                                                    $fechaTarjeta = base64_encode($tarjeta['fecha']);
                                                    $codigoTarjeta = base64_encode($tarjeta['cvc']);
                                                    $fechaTarjetaAlta = date("Y-m-d H:i:s");

                                                    $id_tarjeta = $mysql->Ingreso("INSERT INTO notas_registros_tarjetas (id_lista, numero, fecha, codigo, fechaAlta) VALUES (?,?,?,?,?)", array($id_lista, $numeroTarjeta, $fechaTarjeta, $codigoTarjeta, $fechaTarjetaAlta));

                                                    $finalizaModificacion = true;
                                                // }else{
                                                //     $respuesta['error'] = "El número de tarjeta está incorrecta. Verifiquela por favor.";
                                                // }
                                               
                                            }else{
                                                $respuesta['error'] = "Debe ingresar todos los campos de la tarjeta de crédito.";
                                            }
                                        }else{
                                            $respuesta['error'] = "No se ha recibido los datos de la tarjeta utilizada.";
                                        }
                                    }else{
                                        $finalizaModificacion = true;
                                    }

                                    if ($finalizaModificacion){
                                        $fecha_ultima_contacto = date("Y-m-d H:i:s");
                                        $modificar = $mysql->Modificar("UPDATE notas_registros SET observaciones_entregada=?, impreso=?, ciudad=?, direccion=?, orden=?, observaciones=?, hora_prox_llamada=?, fecha_prox_llamada=?, fecha_ultima_contacto=?, id_delivery=?, estado=? WHERE id_lista=?", array($opcionesRegistradas, $opcionNoDesea, $ciudad, $direccion, 1, $observaciones, $hora, $fecha, $fecha_ultima_contacto, $id_delivery, $estado, $id_lista));

                                        if ($estado == 7){
                                            $consultaVendedorCodigo = $mysql->Consulta_Unico("SELECT * FROM usuarios WHERE id_usuario=".$id_asesor);
                                            

                                            if (isset($consultaVendedorCodigo['id_usuario'])){
                                                $idVendedor = $consultaVendedorCodigo['id_referencia'];
                                                $fechaVenta = date("Y-m-d");
                                                
                                                $id_lider = 26;

                                                switch ($id_asesor) {
                                                    case 331: // ALCIVAR
                                                        $id_lider = 26;
                                                        break;
                                                    case 332: // CARRASCO
                                                        $id_lider = 28;
                                                        break;
                                                    case 333: // ORTIZ
                                                        $id_lider = 29;
                                                        break;
                                                    case 334: // CHACHA STEFANIA
                                                        $id_lider = 30;
                                                        break;
                                                    case 337: // CHACHA JENNIFER
                                                        $id_lider = 32;
                                                        break;
                                                    case 338: // AMAYA
                                                        $id_lider = 33;
                                                        break;
                                                    case 339: // VILANEZ
                                                        $id_lider = 34;
                                                        break;
                                                    case 340: // RAZA
                                                        $id_lider = 35;
                                                        break; 
                                                    case 341: // vintimilla
                                                        $id_lider = 36;
                                                        break; 
                                                    case 343: // CHAMORRO
                                                        $id_lider = 37;
                                                        break; 
                                                    case 344: // LAPO
                                                        $id_lider = 38;
                                                        break;
                                                    case 345: // NORMA
                                                        $id_lider = 39;
                                                        break;
                                                    case 346: // LUPITA
                                                        $id_lider = 40;
                                                        break;
                                                    case 347: // NICOLE
                                                        $id_lider = 41;
                                                        break;
                                                    case 348: // ALEXIS ENRIQUEZ
                                                        $id_lider = 42;
                                                        break;
                                                }

                                                $dummy = "";
                                                $id_cliente = "";
                                                $documento = "";
                                                $nombres_apellidos = "";
                                                $ciudad = "";
                                                $correo = "";
                                                $convencional = "";
                                                $celular = "";
                                                $pago_cuadro = 1;
                                                $tipo_venta = 0;
                                                $tipo = 0;
                                                $que_destino = "";
                                                $id_destino = 0;
                                                $fecha_alta = date("Y-m-d H:i:s");
                                                $fecha_modificacion = $fecha_alta;            
                                                $fecha_caducidad = $fecha_alta;
                                                
                                                $estado = 4;

                                                // REaliza el registro rapido
                                                $nuevaVentaRapida = $mysql->Ingreso("INSERT INTO registros (fecha_venta, id_vendedor, id_lider, dummy, id_cliente, documento, nombres_apellidos, ciudad, correo, convencional, celular, pago_cuadro, tipo_venta, fecha_caducidad, tipo, que_destino, id_destino, fecha_alta, fecha_modificacion, estado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", array($fechaVenta, $idVendedor, $id_lider, $dummy, $id_cliente, $documento, $nombres_apellidos, $ciudad, $correo, $convencional, $celular, $pago_cuadro, $tipo_venta, $fecha_caducidad, $tipo, $que_destino, $id_destino, $fecha_alta, $fecha_modificacion, $estado));
                                            }
                                            
                                        }   

                                        $respuesta['estado'] = true;
                                    }
                                }                            
                            }
                        }
                        
                    }else{
                        $respuesta['error'] = "No se encuentra informacion del cliente.";
                    }
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->post("/guardar_accion/{id_asesor}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');                
                $id_asesor = $request->getAttribute('id_asesor');
                $data = $request->getParsedBody();
                $respuesta['estado'] = false;
                
                $respuesta['data'] = $data;

                try{
                    $mysql = new Database('vtgsa_ventas');

                    $verifica = $mysql->Consulta_Unico("SELECT * FROM vendedores WHERE id_vendedor=".$id_asesor);

                    if (isset($verifica['id_vendedor'])){
                        $estado = $data['estado'];
                        $segundos = $data['segundos'];
                        $fecha_hora = date("Y-m-d H:i:s");
                        
                        $descripcion = "";

                        if ($estado == "Empieza"){
                            $descripcion = "Empieza ciclo de llamadas.";
                        }else if ($estado == "Detiene"){
                            $descripcion = "Detiene ciclo de llamadas.";
                        }else if ($estado == "Actualizacion"){
                            $descripcion = "Actualizacion ciclo de llamadas.";
                        }

                        $id_auditoria = $mysql->Ingreso("INSERT INTO notas_registros_cronometros (id_asesor, descripcion, segundos, fecha_hora, estado) VALUES (?,?,?,?,?)", array($id_asesor, $descripcion, $segundos, $fecha_hora, 0));

                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No se encuentra el asesor.";
                    }                  
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/obtener_tiempo/{id_asesor}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');                
                $id_asesor = $request->getAttribute('id_asesor');                
                $respuesta['estado'] = false;                            

                try{
                    $mysql = new Database('vtgsa_ventas');
                    $fecha_actual = date("Y-m-d 00:00:00");

                    $consulta = $mysql->Consulta_Unico("SELECT segundos FROM notas_registros_cronometros WHERE (id_asesor=".$id_asesor.") AND (fecha_hora >= '".$fecha_actual."') ORDER BY id_auditoria DESC LIMIT 1");

                    $segundos = 0;

                    if (isset($consulta['segundos'])){
                        $segundos = $consulta['segundos'];                        
                    }
                    
                    $respuesta['segundos'] = (int) $segundos;
                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->put("/actualizar_base/{id_lista}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');                
                $id_lista = $request->getAttribute('id_lista');
                $data = $request->getParsedBody();

                $respuesta['estado'] = false;     
                
                $respuesta['data'] = $data;

                try{
                    $mysql = new Database('vtgsa_ventas');
                    
                    $verifica = $mysql->Consulta_Unico("SELECT * FROM notas_registros WHERE id_lista=".$id_lista);

                    if (isset($verifica['id_lista'])){
                        $ciudad = strtoupper($data['ciudad']);
                        $correo = strtolower($data['correo']);
                        $direccion = strtoupper($data['direccion']);
                        $fecha_modificacion = date("Y-m-d H:i:s");

                        // if (filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                            $actualizar = $mysql->Modificar("UPDATE notas_registros SET ciudad=?, ciudad_confirmada=?, correo=?, direccion_confirmada=?, direccion=?, fecha_modificacion=? WHERE id_lista=?", array($ciudad, $ciudad, $correo, $direccion, $direccion, $fecha_modificacion, $id_lista));

                            $respuesta['estado'] = true;
                        // } else {
                        //     $respuesta['error'] = "El correo electronico es invalido.";
                        // }
                                                
                    }else{
                        $respuesta['error'] = "No se ha encontrado informacion del cliente. No se pudo actualizar la informacion.";
                    }
                     
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });     

            $app->get("/asesorescall", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;

                try{
                    $mysql = new Database("vtgsa_ventas");

                    if (isset($authorization[0])){
                        $autenticacion = new Authentication();
                        $session = $autenticacion->Valida_Sesion($authorization[0]);
    
                        if ($session['estado']){
                            
                            $buscador = "";
                            if (isset($params['buscador'])){
                                $buscador = $params['buscador'];
                            }

                            $banco = 0;
                            if (isset($params['banco'])){
                                $banco = $params['banco'];
                            }

                            $identificador = "";
                            if (isset($params['identificador'])){
                                $identificador = $params['identificador'];
                            }

                            $id_oficina = "";
                            if (isset($params['id_oficina'])){
                                $id_oficina = $params['id_oficina'];
                            }

                            $ahora = date("Y-m-d");

                            $functions = new Functions();

                            if (!empty($identificador)){
                                $consulta = $mysql->Consulta("SELECT
                                U.id_usuario, U.nombres, U.extension, C.id_banco, C.id_identificador, C.cupo
                                FROM notas_registros_cupos C
                                LEFT JOIN usuarios U
                                ON C.id_usuario=U.id_usuario
                                WHERE ((U.nombres LIKE '%".$buscador."%') AND (U.tipo=6) AND (U.estado=0) AND (U.id_oficina=".$id_oficina.")) AND ((C.id_banco=".$banco.") AND (C.id_identificador='".$identificador."'))
                                ORDER BY U.nombres ASC");

                                $resultados = [];
                                $respuesta['consulta'] = $consulta;

                                if (is_array($consulta)){
                                    if (count($consulta) > 0){
                                        foreach ($consulta as $linea) {
                                            // Consulta cupo dependiendo la base elegida
                                            $cupo_call = $linea['cupo'];
    
                                            $listado = $mysql->Consulta("SELECT estado, COUNT(estado) AS total FROM notas_registros WHERE (asignado=".$linea['id_usuario'].") AND (banco=".$banco.") AND (identificador='".$identificador."') GROUP BY estado ORDER BY COUNT(estado) DESC");

                                            $detalle = [];
    
                                            if (is_array($listado)){
                                                if (count($listado) > 0){
                                                    foreach ($listado as $linea_estado) {
                                                        $estado_valores = $functions->Obtener_Estado($linea_estado['estado']);
                                                        array_push($detalle, array(
                                                            "id" => (int) $linea_estado['estado'],
                                                            "descripcion" => $estado_valores['descripcion'],
                                                            "total" => (int) $linea_estado['total']
                                                        ));
                                                    }
                                                }
                                            }
    
                                            array_push($resultados, array(
                                                "id_usuario" => (int) $linea['id_usuario'],
                                                "nombres" => $linea['nombres'],
                                                "cupo" => (int) $cupo_call,
                                                "extension" => (int) $linea['extension'],
                                                "detalle" => $detalle
                                            ));
    
                                        }
                                    }
                                }
    
                                $respuesta['consulta'] = $resultados;
        
                                $respuesta['estado'] = true;
                            }else{
                                $respuesta['error'] = "Debe seleccionar un identificador.";
                            }
                        }else{
                            $respuesta['error'] = $session['error'];
                        }
                    }else{
                        $respuesta['error'] = "Su token de acceso no se encuentra.";
                    }
                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/lideres/{clave}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $clave = $request->getAttribute('clave');
                $respuesta['estado'] = false;

                try{
                    $mysql = new Database("vtgsa_ventas");

                    $buscar = $mysql->Consulta_Unico("SELECT * FROM lideres WHERE (password='".$clave."')");

                    if (isset($buscar['id_lider'])){
                        $respuesta['lider'] = "Acceso permitido. Lider: ".$buscar['lider'];
                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No se obtuvo acceso con esta credencial.";
                    }
                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->put("/asesorescall/{id_banco}/{id_usuario}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_usuario = $request->getAttribute('id_usuario');
                $id_banco = $request->getAttribute('id_banco');
                $data = $request->getParsedBody();
                $respuesta['estado'] = false;

                $respuesta['data'] = $data;

                try{
                    $mysql = new Database("vtgsa_ventas");

                    if (isset($authorization[0])){
                        $autenticacion = new Authentication();
                        $session = $autenticacion->Valida_Sesion($authorization[0]);
    
                        if ($session['estado']){
                            if ((isset($data['identificador'])) && (!empty($data['identificador']))){
                                $identificador = $data['identificador'];
                                $cupo = $data['cupo'];

                                $modificar = $mysql->Modificar("UPDATE notas_registros_cupos SET cupo=? WHERE ((id_usuario=?) AND (id_banco=?) AND (id_identificador=?))", array($cupo, $id_usuario, $id_banco, $identificador));
    
                                $respuesta['estado'] = true;
                            }else{
                                $respuesta['error'] = "Debe seleccionar un identificador.";
                            }
                        }else{
                            $respuesta['error'] = $session['error'];
                        }
                    }else{
                        $respuesta['error'] = "Su token de acceso no se encuentra.";
                    }
                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/llamadas/{celular}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $celular = $request->getAttribute('celular');
                $respuesta['estado'] = false;
            
                try{
                    $call = new CRM_API();

                    $audio = "true";
                    if ((isset($params['audio'])) && (!empty($params['audio']))){
                        $audio = $params['audio'];
                    }

                    $consulta = $call->Obtener_Log_Llamadas($celular, $audio);
                    $archivos = [];
                    
                    if (isset($consulta['contenido']['archivos'])){
                        $archivos = $consulta['contenido']['archivos'];

                        $decodificado = [];
                        if (is_array($archivos)){
                            if (count($archivos) > 0){
                                foreach ($archivos as $linea) {
                                    $tmp = __DIR__."/../../public/tmp/".$linea['nombre'];
                                    $contenido = base64_decode($linea['base64']);
                                    file_put_contents($tmp, $contenido);
                                    
                                    $output=null;
                                    $retval=null;
                                    exec("ffmpeg -i ".$tmp." 2>&1 | grep Duration | sed 's/Duration: \(.*\), start/\1/g'", $output, $retval);
                                    // $mp3 = new MP3Analizador($linea['nombre']);
                                    // $informacion = $mp3->get_metadata();
                                    $informacion = [];
                                    array_push($decodificado, array(
                                        "base64" => $linea['base64'],
                                        "extension" => $linea['extension'],
                                        "fecha" => $linea['fecha'],
                                        "nombre" => $linea['nombre'],
                                        "ubicacion" => $linea['ubicacion'],
                                        "informacion" => $informacion,
                                        "tmp" => $tmp,
                                        "result" => $output
                                    ));

                                    unlink($tmp);
                                }
                            }
                        }

                        $respuesta['archivos'] = $decodificado;
                    }
                  
                    if (isset($consulta['contenido']['llamadas'])){
                        $llamadas = $consulta['contenido']['llamadas'];

                        if (is_array($llamadas)){
                            if (count($llamadas) > 0){
                                $nuevo = [];

                                foreach ($llamadas as $archivo) {
                                    $nombre = basename($archivo);
                                    $separa = explode("-", $nombre);
                                    $fecha = "";
                                    $extension = "";
                                    if (isset($separa[2])){
                                        $fecha = $separa[0];
                                        
                                        $anio = substr($fecha, 0, 4);
                                        $mes = substr($fecha, 4, 2);
                                        $dia = substr($fecha, 6, 2);

                                        $hora = substr($fecha, 8, 2);
                                        $min = substr($fecha, 10, 2);
                                        $sec = substr($fecha, 12, 2);

                                        $fecha = $anio."-".$mes."-".$dia." ".$hora.":".$min.":".$sec;

                                        $extension = $separa[1];
                                    }
                                    array_push($nuevo, array(
                                        "grabacion" => $nombre,
                                        "fecha" => $fecha,
                                        "extension" => $extension
                                    ));
                                }

                                $respuesta['llamadas'] = $nuevo;
                            }
                        }
                        // $respuesta['llamadas'] = $llamadas;
                    }
                    
                    $respuesta['estado'] = true;

                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
                
                return $newResponse;
            });

            $app->get("/obtener-llamada/{grabacion}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $grabacion = $request->getAttribute('grabacion');
                $respuesta['estado'] = false;
            
                try{
                    $call = new CRM_API();
                    $consulta = $call->Obtener_Audio_Llamada($grabacion);

                    if (isset($consulta['contenido']['archivos'])){
                        $archivo = $consulta['contenido']['archivos'][0];
                        $base64 = $archivo['base64'];

                        $respuesta['audio'] = $base64;
                        $respuesta['consulta'] = $archivo;
                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No se pudo obtener el registro de llamada.";
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
                
                return $newResponse;
            });

            $app->get("/status-extension/{extension}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $extension = $request->getAttribute('extension');
                $respuesta['estado'] = false;
            
                try{
                    $call = new CRM_API();

                    $consulta = $call->Obtener_Status_Extension($extension);

                    if ($consulta['contenido']['estado']){
                        $respuesta['consulta'] = $consulta['contenido']['extension'];
                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "Error al obtener estado de la extensión ".$extension;
                    }

                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
                
                return $newResponse;
            });

            $app->get("/asignacion-asesor/{id_asesor}/{banco}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_asesor = $request->getAttribute('id_asesor');
                $banco = $request->getAttribute('banco');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
            
                try{
                    $mysql = new Database("vtgsa_ventas");
                    $funciones = new Functions();

                    $por_identificador = "";
                    if ((isset($params['identificador'])) && (!empty($params['identificador']))){
                        $por_identificador = "AND (identificador='".$params['identificador']."')";
                    }

                    $buscador = "";
                    if ((isset($params['buscador'])) && (!empty($params['buscador']))){
                        $buscador = $params['buscador'];
                    }

                    $consulta = $mysql->Consulta("SELECT R.id_lista, R.banco AS id_banco, B.banco, R.identificador, R.documento, R.nombres, R.telefono, R.direccion, R.ciudad, R.direccion_confirmada, R.ciudad_confirmada, R.correo, R.observaciones, R.llamado, R.fecha_asignacion, R.fecha_ultima_contacto, R.fecha_alta, R.fecha_modificacion, R.estado FROM notas_registros R, notas_registros_bancos B WHERE (((R.banco=".$banco.") AND (R.asignado=".$id_asesor.") ".$por_identificador.") AND ((documento LIKE '%".$buscador."%') OR (nombres LIKE '%".$buscador."%'))) AND (R.banco=B.id_banco) ORDER BY R.fecha_asignacion DESC");

                    $filtrado = [];
                    if (is_array($consulta)){
                        if (count($consulta) > 0){
                            foreach ($consulta as $linea) {
                                $consulta_estados = $funciones->Obtener_Estado($linea['estado']);

                                array_push($filtrado, array(
                                    "id_lista" => (int) $linea['id_lista'],
                                    "base" => array(
                                        "id" => (int) $linea['id_banco'],
                                        "descripcion" => $linea['banco'],
                                        "identificador" => $linea['identificador']
                                    ),
                                    "contacto" => array(
                                        "documento" => $linea['documento'],
                                        "nombres" => $linea['nombres'],
                                        "telefono" => $linea['telefono'],
                                        "ciudad" => $linea['ciudad'],
                                        "direccion" => $linea['direccion'],
                                        "correo" => $linea['correo']
                                    ),
                                    "observaciones" => $linea['observaciones'],
                                    "intentos" => (int) $linea['llamado'],
                                    "fecha_asignado" => $linea['fecha_asignacion'],
                                    "fecha_ultimo_contacto" => $linea['fecha_ultima_contacto'],
                                    "fecha_alta" => $linea['fecha_alta'],
                                    "fecha_modificacion" => $linea['fecha_modificacion'],
                                    "estado" => array(
                                        "valor" => (int) $linea['estado'],
                                        "descripcion" => $consulta_estados['descripcion'],
                                        "color" => $consulta_estados['color']
                                    )
                                ));
                            }
                        }
                    }

                    $respuesta['consulta'] = $filtrado;
                    $respuesta['estado'] = true;
 
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
                
                return $newResponse;
            });

            $app->put("/asignacion-asesor/{id_lista}/{estado}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_lista = $request->getAttribute('id_lista');
                $estado = $request->getAttribute('estado');
                $respuesta['estado'] = false;
            
                try{
                    $mysql = new Database("vtgsa_ventas");
                    $fecha = date("Y-m-d H:i:s");
                    $modificar = $mysql->Modificar("UPDATE notas_registros SET estado=?, fecha_modificacion=? WHERE id_lista=?", array($estado, $fecha, $id_lista));

                    $respuesta['estado'] = true;
 
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
                
                return $newResponse;
            });

            $app->get("/informacion-contacto/{id_lista}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_lista = $request->getAttribute('id_lista');
                $respuesta['estado'] = false;
            
                try{
                    $mysql = new Database("vtgsa_ventas");
                    $funciones = new Functions();

                    $consulta = $mysql->Consulta_Unico("SELECT R.id_lista, R.banco AS id_banco, B.banco, R.identificador, R.documento, R.nombres, R.telefono, R.direccion, R.ciudad, R.direccion_confirmada, R.ciudad_confirmada, R.correo, R.observaciones, R.llamado, R.fecha_asignacion, R.fecha_ultima_contacto, R.fecha_alta, R.fecha_modificacion, R.estado FROM notas_registros R, notas_registros_bancos B WHERE R.id_lista=".$id_lista);

                    if (isset($consulta['id_lista'])){
                        $consulta_estados = $funciones->Obtener_Estado($consulta['estado']);

                        $resultado = array(
                            "id_lista" => (int) $consulta['id_lista'],
                            "base" => array(
                                "id" => (int) $consulta['id_banco'],
                                "descripcion" => $consulta['banco'],
                                "identificador" => $consulta['identificador']
                            ),
                            "contacto" => array(
                                "documento" => $consulta['documento'],
                                "nombres" => $consulta['nombres'],
                                "telefono" => $consulta['telefono'],
                                "ciudad" => $consulta['ciudad'],
                                "direccion" => $consulta['direccion'],
                                "correo" => $consulta['correo']
                            ),
                            "observaciones" => $consulta['observaciones'],
                            "intentos" => (int) $consulta['llamado'],
                            "fecha_asignado" => $consulta['fecha_asignacion'],
                            "fecha_ultimo_contacto" => $consulta['fecha_ultima_contacto'],
                            "fecha_alta" => $consulta['fecha_alta'],
                            "fecha_modificacion" => $consulta['fecha_modificacion'],
                            "estado" => array(
                                "valor" => (int) $consulta['estado'],
                                "descripcion" => $consulta_estados['descripcion'],
                                "color" => $consulta_estados['color']
                            )
                        );

                        $respuesta['consulta'] = $resultado;
                        $respuesta['estado'] = true;

                    }else{
                        $respuesta['error'] = "No se encontró información del contacto.";
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
                
                return $newResponse;
            });

            $app->get("/verificar-base/{banco}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $banco = $request->getAttribute('banco');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
            
                try{
                    $mysql = new Database("vtgsa_ventas");

                    if ((isset($params['identificador'])) && (!empty($params['identificador']))){
                        
                        $consulta = $mysql->Consulta_Unico("SELECT COUNT(id_lista) AS total FROM notas_registros WHERE (banco=".$banco.") AND (identificador='".$params['identificador']."') AND (estado=10) AND (asignado=0)");

                        if (isset($consulta['total'])){
                            if ($consulta['total'] > 0){
                                $respuesta['consulta'] = array(
                                    "bloqueados" => (int) $consulta['total']
                                );
                                $respuesta['estado'] = true;
                            }else{
                                $respuesta['error'] = "No existen contactos a desbloquear.";
                            }
                        }
                    }else{
                        $respuesta['error'] = "Debe seleccionar un identificador.";
                    }

                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
                
                return $newResponse;
            });

            $app->get("/desbloquear-base/{banco}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $banco = $request->getAttribute('banco');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
            
                try{
                    $mysql = new Database("vtgsa_ventas");

                    if ((isset($params['identificador'])) && (!empty($params['identificador']))){
                        if ((isset($params['limite'])) && (!empty($params['limite']))){
                            $limite = $params['limite'];

                            if ($limite > 0){
                                $consulta = $mysql->Consulta_Unico("SELECT COUNT(id_lista) AS total FROM notas_registros WHERE (banco=".$banco.") AND (identificador='".$params['identificador']."') AND (estado=10) AND (asignado=0)");

                                if (isset($consulta['total'])){
                                    if ($consulta['total'] > 0){
                                        if ($consulta['total'] >= $limite){

                                            $a_desbloquear = $mysql->Consulta("SELECT id_lista FROM notas_registros WHERE (banco=".$banco.") AND (identificador='".$params['identificador']."') AND (estado=10) AND (asignado=0) ORDER BY id_lista ASC LIMIT ".$limite);

                                            if (is_array($a_desbloquear)){
                                                if (count($a_desbloquear) > 0){
                                                    foreach ($a_desbloquear as $linea) {
                                                        $id_lista = $linea['id_lista'];

                                                        $modificar = $mysql->Modificar("UPDATE notas_registros SET estado=? WHERE id_lista=?", array(0, $id_lista));
                                                    }

                                                    $respuesta['consulta'] = array(
                                                        "a_desbloquear" => (int) count($a_desbloquear),
                                                        "listado" => $a_desbloquear
                                                    );
                                                    $respuesta['estado'] = true;
                                                }
                                            }

                                            
                                        }else{
                                            $respuesta['error'] = "El valor debe ser menor al indicado en registros bloqueados.";
                                        }
                                    }else{
                                        $respuesta['error'] = "No existen contactos a desbloquear.";
                                    }
                                }
                            }else{
                                $respuesta['error'] = "Debe ingresar un valor mayor a cero.";
                            }
                        }else{
                            $respuesta['error'] = "Debe ingresar un valor numérico.";
                        }
                        
                    }else{
                        $respuesta['error'] = "Debe seleccionar un identificador.";
                    }

                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
                
                return $newResponse;
            });

            $app->get("/bancos", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;

                try{
                    $mysql = new Database('vtgsa_ventas');

                    $buscador = "";
                    if (isset($params['buscador'])){
                        $buscador = $params['buscador'];
                    }

                    $consulta = $mysql->Consulta("SELECT * FROM notas_registros_bancos WHERE (banco LIKE '%".$buscador."%') AND (estado=0) ORDER BY id_banco ASC");

                    $filtrado = [];
                    if (is_array($consulta)){
                        if (count($consulta) > 0){
                            foreach ($consulta as $linea) {
                                array_push($filtrado, array(
                                    "id_banco" => (int) $linea['id_banco'],
                                    "banco" => $linea['banco']
                                ));
                            }
                        }
                    }

                    $respuesta['consulta'] = $consulta;
                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/oficinas", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;

                try{
                    $mysql = new Database('vtgsa_ventas');

                    if (isset($authorization[0])){
                        $autenticacion = new Authentication();
                        $session = $autenticacion->Valida_Sesion($authorization[0]);
    
                        if ($session['estado']){
                           
                            $id_usuario = $session['usuario']['id_usuario'];

                            $buscador = "";
                            if (isset($params['buscador'])){
                                $buscador = $params['buscador'];
                            }

                            $sql = "";
                            if ($id_usuario == 288){ // RICARDO PALACIOS
                                $sql = "SELECT * FROM usuarios_oficina WHERE (oficina LIKE '%".$buscador."%') AND (estado=0) AND (id_oficina=5) ORDER BY id_oficina ASC";
                            }else{
                                $sql = "SELECT * FROM usuarios_oficina WHERE (oficina LIKE '%".$buscador."%') AND (estado=0) ORDER BY id_oficina ASC";
                            }
        
                            $consulta = $mysql->Consulta($sql);
        
                            $filtrado = [];
                            if (is_array($consulta)){
                                if (count($consulta) > 0){
                                    foreach ($consulta as $linea) {
                                        array_push($filtrado, array(
                                            "id_oficina" => (int) $linea['id_oficina'],
                                            "oficina" => $linea['oficina']
                                        ));
                                    }
                                }
                            }
        
                            $respuesta['consulta'] = $filtrado;
                            $respuesta['estado'] = true;
                          
                        }else{
                            $respuesta['error'] = $session['error'];
                        }
                    }else{
                        $respuesta['error'] = "Su token de acceso no se encuentra.";
                    }
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->post("/bancos", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                $respuesta['estado'] = false;

                try{
                    $mysql = new Database('vtgsa_ventas');

                    if ((isset($data['banco'])) && (!empty($data['banco']))){
                        $banco = $data['banco'];

                        $verifica = $mysql->Consulta_Unico("SELECT * FROM notas_registros_bancos WHERE (banco='".$banco."')");

                        if (!isset($verifica['id_banco'])){
                            $id_banco = $mysql->Ingreso("INSERT INTO notas_registros_bancos (banco, estado) VALUES (?,?)", array($banco, 0));
                            
                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = "Ya existe un banco con este nombre. Seleccione otro por favor.";
                        }
                    }else{
                        $respuesta['error'] = "Debe ingresar un nombre para el banco a crear.";
                    }
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->post("/nueva-base", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                $files = $request->getUploadedFiles();          

                $respuesta['estado'] = false;

                // $respuesta['data'] = $data;
                // $respuesta['files'] = $files;

                try{
                    $mysql = new Database('vtgsa_ventas');
                    $funciones = new Functions();

                    if ((isset($data['identificador'])) && (!empty($data['identificador']))){
                        $banco = $data['id_banco'];
                        $identificador = $data['identificador'];

                        // Verifica que no exista el identificador en el banco
                        $verifica = $mysql->Consulta_Unico("SELECT * FROM notas_registros WHERE (identificador='".$identificador."') AND (banco=".$banco.") ORDER BY id_lista ASC LIMIT 1");

                        if (!isset($verifica['id_lista'])){
                            // procesa el archivo a vincular
                            $archivo_csv =  "";
                            if (isset($files)){                                                                                                    
                                $archivo_temporal = $files['base0']->file;
                            
                                $carpeta = __DIR__."/../../public/tmp";

                                if (!file_exists($carpeta)){
                                    // Si no existe la carpeta la crea
                                    mkdir($carpeta, 0777, true);
                                }        
                                $nombre_archivo = base64_encode("base".date("YmdHis"));
                                $destino = $carpeta.'/'.$nombre_archivo;
                                move_uploaded_file($archivo_temporal, $destino);

                                $archivo_csv = $nombre_archivo;                                    
                            }

                            if (!empty($archivo_csv)){
                                $procesamiento = $funciones->Procesar_Nueva_Base($archivo_csv);

                                // $respuesta['procesamiento'] = $procesamiento;

                                if ($procesamiento['estado']){
                                    $listado = $procesamiento['procesamiento']['listado'];

                                    $a_procesar['correctos'] = [];
                                    $a_procesar['errores'] = [];

                                    if (is_array($listado)){
                                        if (count($listado) > 0){
                                            $total_procesar = count($listado);
                                            $i = 0;

                                            foreach ($listado as $linea) {
                                                $documento = $linea['documento'];
                                                $nombres = $linea['nombres'];
                                                $telefono1 = $linea['telefono1'];
                                                $telefono2 = $linea['telefono2'];
                                                $telefono3 = $linea['telefono3'];
                                                $telefono4 = $linea['telefono4'];
                                                $telefono5 = $linea['telefono5'];
                                                $telefono6 = $linea['telefono6'];
                                                $principal = $linea['principal'];
                                                $ciudad = $linea['ciudad'];
                                                $direccion = $linea['direccion'];
                                                $observaciones = $linea['observaciones'];
                                                $correo = strtolower($linea['correo']);
                                                $estado = $linea['estado'];
                                                $fecha = date("Y-m-d H:i:s");

                                                if (!empty($documento)){
                                                    $consulta = $mysql->Consulta_Unico("SELECT * FROM notas_registros WHERE (documento='".$documento."') AND (nombres='".$nombres."') AND (telefono='".$principal."') AND (banco=".$banco.")");

                                                    if (!isset($consulta['id_lista'])){
                                                        $id_lista = $mysql->Ingreso("INSERT INTO notas_registros (banco, identificador, documento, nombres, telefono, ciudad, direccion, correo, observaciones, id_notas, asignado, llamado, orden, fecha_alta, fecha_modificacion, estado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", array($banco, $identificador, $documento, $nombres, $principal, $ciudad, $direccion, $correo, $observaciones, 0, 0, 0, 0, $fecha, $fecha, $estado));

                                                        if ((trim($telefono1) != "") && (strlen(trim($telefono1)) >= 9)){
                                                            $id_contacto = $mysql->Ingreso("INSERT INTO notas_registros_contactos (id_lista, contacto, estado) VALUES (?,?,?)", array($id_lista, $telefono1, 0));
                                                        }
                                                        if ((trim($telefono2) != "") && (strlen(trim($telefono2)) >= 9)){
                                                            $id_contacto = $mysql->Ingreso("INSERT INTO notas_registros_contactos (id_lista, contacto, estado) VALUES (?,?,?)", array($id_lista, $telefono2, 0));
                                                        }
                                                        if ((trim($telefono3) != "") && (strlen(trim($telefono3)) >= 9)){
                                                            $id_contacto = $mysql->Ingreso("INSERT INTO notas_registros_contactos (id_lista, contacto, estado) VALUES (?,?,?)", array($id_lista, $telefono3, 0));
                                                        }
                                                        if ((trim($telefono4) != "") && (strlen(trim($telefono4)) >= 9)){
                                                            $id_contacto = $mysql->Ingreso("INSERT INTO notas_registros_contactos (id_lista, contacto, estado) VALUES (?,?,?)", array($id_lista, $telefono4, 0));
                                                        }
                                                        if ((trim($telefono5) != "") && (strlen(trim($telefono5)) >= 9)){
                                                            $id_contacto = $mysql->Ingreso("INSERT INTO notas_registros_contactos (id_lista, contacto, estado) VALUES (?,?,?)", array($id_lista, $telefono5, 0));
                                                        }
                                                        if ((trim($telefono6) != "") && (strlen(trim($telefono6)) >= 9)){
                                                            $id_contacto = $mysql->Ingreso("INSERT INTO notas_registros_contactos (id_lista, contacto, estado) VALUES (?,?,?)", array($id_lista, $telefono6, 0));
                                                        }
                                                        
                                                        // Procesamiento_Bloque
                                                        array_push($a_procesar['correctos'], array(
                                                            "documento" => $documento,
                                                            "nombres" => $nombres
                                                        )); 
                                                    }else{
                                                        array_push($a_procesar['errores'], array(
                                                            "documento" => $documento,
                                                            "nombres" => $nombres,
                                                            "mensaje" => "El contacto ya se encuentra registrado."
                                                        ));    
                                                    }
                                                }else{
                                                    array_push($a_procesar['errores'], array(
                                                        "documento" => $documento,
                                                        "nombres" => $nombres,
                                                        "mensaje" => "No se encuentra el documento del contacto."
                                                    )); 
                                                }
                                            }
                                        }
                                    }

                                    $respuesta['listado'] = $listado;
                                    $respuesta['procesado'] = $a_procesar;

                                    $proseguir = false;
                                    if ((count($a_procesar['correctos']) == 0) && (count($a_procesar['errores']) > 0)){
                                        $respuesta['error'] = "No se procesó ningún registro del archivo.";
                                    }else if ((count($a_procesar['correctos']) > 0) && (count($a_procesar['errores']) > 0)){
                                        $respuesta['mensaje'] = "Se guardaron ".count($a_procesar['correctos'])." registros. \nExisten ".count($a_procesar['errores'])." registros con errores que no se procesaron.";
                                        $respuesta['estado'] = true;
                                        $proseguir = true;
                                    }else if ((count($a_procesar['correctos']) > 0) && (count($a_procesar['errores']) == 0)){
                                        $respuesta['mensaje'] = "Se procesaron ".count($listado)." registros de ".count($a_procesar['correctos']).".";
                                        $respuesta['estado'] = true;
                                        $proseguir = true;
                                    }

                                    if ($proseguir){
                                        // Crea listado de asesores disponibles para asignar cupos
                                        $asesores = $mysql->Consulta("SELECT * FROM usuarios WHERE (tipo=6) AND (estado=0)");

                                        if (is_array($asesores)){
                                            if (count($asesores) > 0){
                                                foreach ($asesores as $linea) {
                                                    $id_usuario = $linea['id_usuario'];

                                                    $verifica = $mysql->Consulta("SELECT * FROM notas_registros_cupos WHERE (id_usuario=".$id_usuario.") AND (id_banco=".$banco.") AND (id_identificador='".$identificador."')");

                                                    if (isset($verifica['id_cupo'])){
                                                        $modifica = $mysql->Modificar("UPDATE notas_registros_cupos SET cupo=? WHERE id_cupo=?", array(0, $verifica['id_cupo']));
                                                    }else{
                                                        $agregar = $mysql->Ingreso("INSERT INTO notas_registros_cupos (id_usuario, id_banco, id_identificador, cupo, estado) VALUES (?,?,?,?,?)", array($id_usuario, $banco, $identificador, 0, 0));
                                                    }
                                                }
                                            }else{
                                                $respuesta['error'] = "No existen asesores disponibles para asignar cupos a la base a crear.";
                                            }
                                        }
                                    }
                                }else{
                                    $respuesta['error'] = $procesamiento['error'];
                                }
                            }else{
                                $respuesta['error'] = "No se logró obtener el archivo a procesar.";
                            }
                        }else{
                            $respuesta['error'] = "Ya existe un identificador con este nombre. Favor cambiarlo.";
                        }
                     }else{
                        $respuesta['error'] = "Debe especificar un identificador para la nueva base.";
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/bases-bancos/{banco}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $banco = $request->getAttribute('banco');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
            
                try{
                    $mysql = new Database("vtgsa_ventas");
                   
                    $buscador = "";
                    if (isset($params['buscador'])){
                        $buscador = $params['buscador'];
                    }
            
                    $identificadores = $mysql->Consulta("SELECT identificador, COUNT(identificador) AS total
                    FROM notas_registros
                    WHERE (banco=".$banco.") AND (identificador LIKE '%".$buscador."%')
                    GROUP BY identificador ORDER BY identificador ASC");

                    $filtrado = [];

                    if (is_array($identificadores)){
                        if (count($identificadores) > 0){
                            foreach ($identificadores as $linea) {

                                $total_bloqueados = 0;
                                $bloqueados = $mysql->Consulta_Unico("SELECT
                                estado, COUNT(estado) AS total
                                FROM notas_registros
                                WHERE (banco=".$banco.") AND (identificador='".$linea['identificador']."') AND (estado=10)");

                                if (isset($bloqueados['total'])){
                                    $total_bloqueados = $bloqueados['total'];
                                }

                                $total_asignados = 0;
                                $asignados = $mysql->Consulta_Unico("SELECT
                                estado, COUNT(estado) AS total
                                FROM notas_registros
                                WHERE (banco=".$banco.") AND (identificador='".$linea['identificador']."') AND ((estado>0) AND (estado<=7))");

                                if (isset($asignados['total'])){
                                    $total_asignados = $asignados['total'];
                                }

                                $activos = intval($linea['total']) - intval($total_bloqueados);
                                $por_asignar = intval($activos) - intval($total_asignados);
                                $total = intval($total_bloqueados) + intval($activos);

                                array_push($filtrado, array(
                                    "id_banco" => (int) $banco,
                                    "identificador" => $linea['identificador'],
                                    "total" => (int) $linea['total'],
                                    "bloqueados" => (int) $bloqueados['total'],
                                    "activos" => (int) $activos,
                                    "asignados" => (int) $total_asignados,
                                    "por_asignar" => (int) $por_asignar,
                                    "total" => (int) $total
                                ));
                            }
                        }
                    }

                    $respuesta['consulta'] = $filtrado;
                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
                
                return $newResponse;
            });



            $app->get("/listado-tarjetas-asesores", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
            
                try{
                    $mysql = new Database("vtgsa_ventas");
                   
                    $buscador = "";
                    if (isset($params['buscador'])){
                        $buscador = $params['buscador'];
                    }

                    $from = date("Y-m-01");
                    if ((isset($params['from'])) && (!empty($params['from']))){
                        $from = $params['from'];
                    }

                    $to = date("Y-m-d");
                    if ((isset($params['to'])) && (!empty($params['to']))){
                        $to = $params['to'];
                    }

                    $consulta = $mysql->Consulta("SELECT 
                    T.id_tarjeta, T.numero, T.fecha, T.codigo, T.fechaAlta, R.id_lista, R.documento, R.nombres, R.ciudad, R.telefono, B.id_banco, B.banco, T.estado
                    FROM notas_registros_tarjetas T
                    LEFT JOIN notas_registros R
                    ON T.id_lista = R.id_lista
                    LEFT JOIN notas_registros_bancos B
                    ON R.banco = B.id_banco
                    WHERE (T.fechaAlta BETWEEN '".$from." 01:00:00' AND '".$to." 23:59:59') AND ((R.documento LIKE '%".$buscador."%') OR (R.nombres LIKE '%".$buscador."%') OR (R.telefono LIKE '%".$buscador."%'))
                    ORDER BY T.fechaAlta DESC");

                    $listado = [];
                    if (is_array($consulta)){
                        if (count($consulta) > 0){
                            foreach ($consulta as $linea) {
                                $descripcionEstado = "Sin Utilizar";
                                $color = "warning";

                                switch ($linea['estado']) {
                                    case '0':
                                        $descripcionEstado = "Sin Utilizar";
                                        $color = "warning";
                                        break;
                                    case '1':
                                        $descripcionEstado = "Pagado";
                                        $color = "success";
                                        break;
                                    case '2':
                                        $descripcionEstado = "Sin Cupo";
                                        $color = "info";
                                        break;
                                    case '3':
                                        $descripcionEstado = "Pago Parcial";
                                        $color = "primary";
                                        break;
                                }


                                array_push($listado, array(
                                    "id" => (int) $linea['id_tarjeta'],
                                    "banco" => array(
                                        "id" => (int) $linea['id_banco'],
                                        "descripcion" => $linea['banco']
                                    ),
                                    "cliente" => array(
                                        "id_lista" => (int) $linea['id_lista'],
                                        "documento" => $linea['documento'],
                                        "nombres" => $linea['nombres']
                                    ),
                                    "tarjeta" => array(
                                        "numero" => base64_decode($linea['numero'], true),
                                        "fecha" => base64_decode($linea['fecha'], true),
                                        "codigo" => base64_decode($linea['codigo'], true),
                                    ),
                                    "asesor" => array(
                                        "id" => 0,
                                        "nombres" => "Calsosd"
                                    ),
                                    "fechaAlta" => $linea['fechaAlta'],
                                    "estado" => array(
                                        "valor" => (int) $linea['estado'],
                                        "descripcion" => $descripcionEstado,
                                        "color" => $color
                                    )
                                ));
                            }
                        }
                    }

                    $respuesta['consulta'] = $listado;
            
                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
                
                return $newResponse;
            });

            $app->post("/listado-tarjetas-asesores-cambio/{idTarjeta}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $idTarjeta = $request->getAttribute('idTarjeta');
                $data = $request->getParsedBody();
                $respuesta['estado'] = false;
            
                try{
                    $mysql = new Database("vtgsa_ventas");

                    $valor = 0;
                    if ((isset($data['estado'])) && (!empty($data['estado']))){
                        $valor = $data['estado'];
                    }
                   
                    $modificar = $mysql->Modificar("UPDATE notas_registros_tarjetas SET estado=? WHERE id_tarjeta=?", array($valor, $idTarjeta));
            
                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
                
                return $newResponse;
            });


            $app->get("/estadisticas2", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;

                $respuesta['params'] = $params;
            
                try{
                    $mysql = new Database("vtgsa_ventas");

                    $from = date("Y-m-01");
                    if (isset($params['from'])){
                        $from = $params['from'];
                    }

                    $to = date("Y-m-d");
                    if (isset($params['to'])){
                        $to = $params['to'];
                    }

                    // Contratos vencidos
                    $contratos_vencidos = $mysql->Consulta("SELECT
                    R.fecha_venta, COUNT(R.fecha_venta) AS total
                    FROM registros R
                    WHERE (R.fecha_venta BETWEEN '".$from."' AND '".$to."') AND (R.estado=0) AND (R.fecha_venta<=DATE_FORMAT(DATE_SUB(NOW(),INTERVAL 2 DAY),'%Y-%m-%d'))
                    GROUP BY R.fecha_venta
                    ORDER BY R.fecha_venta DESC, COUNT(R.fecha_venta) DESC");

                    $listado_vencidos = [];
                    if (is_array($contratos_vencidos)){
                        if (count($contratos_vencidos) > 0){
                            foreach ($contratos_vencidos as $linea) {
                                array_push($listado_vencidos, array(
                                    "name" => $linea['fecha_venta'],
                                    "y" => (int) $linea['total']
                                ));
                            }
                        }
                    }

                    // Ventas por lider
                    $listado_lideres_categorias = [];
                    $listado_lideres_data = [];
                    $lideres = $mysql->Consulta("SELECT * FROM lideres");
                    if (is_array($lideres)){
                        if (count($lideres) > 0){
                            foreach ($lideres as $lider) {
                                $id_lider = $lider['id_lider'];

                                $consulta = $mysql->Consulta("SELECT
                                R.estado, SUM(P.valor_voucher) AS total
                                FROM registros_pagos P
                                LEFT JOIN registros R
                                ON P.id_registro = R.id_registro
                                WHERE (fecha_venta BETWEEN '".$from."' AND '".$to."') AND (R.id_lider=".$id_lider.")
                                GROUP BY R.estado
                                ORDER BY SUM(P.valor_voucher) DESC");

                                $resultados = [];
                                if (is_array($consulta)){
                                    if (count($consulta) > 0){
                                        foreach ($consulta as $valores) {
                                            array_push($resultados, array(
                                                
                                            ));
                                        }
                                    }
                                }

                                array_push($listado_lideres_data, array(
                                    "id_lider" => (int) $lider['id_lider'],
                                    "lider" => strtoupper($lider['lider']),
                                    "valores" => $consulta
                                ));

                                array_push($listado_lideres_categorias, $lider['lider']);
                            }
                        }
                    }
                    
                    $respuesta['contratos_vencidos'] = $listado_vencidos;
                    $respuesta['ventas_lider']['categorias'] = $listado_lideres_categorias;
                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
                
                return $newResponse;
            });

            $app->get("/estadisticas", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;

                $respuesta['params'] = $params;
            
                try{
                    $mysql = new Database("vtgsa_ventas");
                    $funciones = new Functions();

                    $from = date("Y-m-01");
                    if ((isset($params['from'])) && (!empty($params['from']))){
                        $from = $params['from'];
                    }

                    $to = date("Y-m-d");
                    if ((isset($params['to'])) && (!empty($params['to']))){
                        $to = $params['to'];
                    }

                    $bases = "2022-10-07 3000";
                    if ((isset($params['bases'])) && (!empty($params['bases']))){
                        $bases = $params['bases'];
                    }

                    $consulta = $mysql->Consulta("SELECT 
                    N.estado, COUNT(N.estado) AS total
                    FROM notas_registros N 
                    GROUP BY N.estado
                    ORDER BY COUNT(N.asignado) DESC");

                    $detalle = [];
                    $pie = [];
                    if (is_array($consulta)){
                        if (count($consulta) > 0){

                            $total_registros = 0;
                            foreach ($consulta as $linea) {
                                $infoEstado =  $funciones->Obtener_Estado($linea['estado']);
                                array_push($detalle, array(
                                    "id" => (int) $linea['estado'],
                                    "total" => (int) $linea['total'],
                                    "estado" => $infoEstado['descripcion']
                                ));

                               $total_registros += $linea['total']; 
                            }

                            foreach ($consulta as $linea) {
                                $infoEstado =  $funciones->Obtener_Estado($linea['estado']);
                                $porcentaje = ($linea['total'] / $total_registros) * 100;
                                array_push($pie, array(
                                    "name" => $infoEstado['descripcion'],
                                    "y" => (float) $porcentaje,
                                ));
                            }
                        }
                    }

                    $respuesta['consulta'] = $detalle;
                    $respuesta['pie'] = $pie;
                    
                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
                
                return $newResponse;
            });
        
            $app->get("/estadisticas-dia", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;

                $respuesta['params'] = $params;
            
                try{
                    $mysql = new Database("vtgsa_ventas");
                    $funciones = new Functions();

                    $from = date("Y-m-01");
                    if ((isset($params['from'])) && (!empty($params['from']))){
                        $from = $params['from'];
                    }

                    $to = date("Y-m-d");
                    if ((isset($params['to'])) && (!empty($params['to']))){
                        $to = $params['to'];
                    }

                    $consulta = $mysql->Consulta("SELECT
                    fecha_venta, COUNT(fecha_venta) AS total
                    FROM registros
                    WHERE (fecha_venta BETWEEN '".$from."' AND '".$to."') 
                    GROUP BY fecha_venta
                    ORDER BY fecha_venta ASC");

                    $detalle = []; 
                    $categorias = [];
                    if (is_array($consulta)){
                        if (count($consulta) > 0){
                            foreach ($consulta as $linea) {
                               array_push($detalle, [$linea['fecha_venta'], (int) $linea['total']]);

                            //    array_push($categorias, $linea['fecha_venta']);
                            //    array_push($detalle, (int) $linea['total']);
                            }
                        }
                    }

                    // $respuesta['categorias'] = $categorias;
                    $respuesta['consulta'] = $detalle;
                    
                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
                
                return $newResponse;
            }); 

            $app->get("/enviarmeFormulario/{id_lista}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_lista = $request->getAttribute('id_lista');
                $respuesta['estado'] = false;

                $respuesta['id_lista'] = $id_lista;
            
                try{

                    if ($id_lista > 0){
                        $mysql = new Database("vtgsa_ventas");

                        $consulta = $mysql->Consulta_Unico("SELECT
                        R.id_lista, R.documento, R.ruc, R.cedula, R.razonSocial, R.formulario, U.nombres, U.celular
                        FROM notas_registros R
                        LEFT JOIN usuarios U
                        ON R.asignado = U.id_usuario
                        WHERE (R.id_lista=".$id_lista.")");

                        if (isset($consulta['id_lista'])){

                            $nibemi = new nibemi();

                            $respuesta['envio'] = $nibemi->enviarPlantilla(array(
                                "phone" => "0958978745",
                                "header" => [
                                    array(
                                        "type" => "document",
                                        "document" => array(
                                            "link" => "http://api.digitalpaymentnow.com/tmp/Formulario-0100348937001.pdf"
                                        )
                                    )
                                ],
                                "body" => [
                                    array(
                                        "type" => "text",
                                        "text" => "Carlos Mino"
                                    )
                                ]
                            ), "diners_formulario");

                            

                        }else{
                            $respuesta['error'] = "No se encuentra el registro para enviar.";
                        }

                    }
                    
                     
                    
                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
                
                return $newResponse;
            }); 

            $app->post("/formulario-nova/{id_lista}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_lista = $request->getAttribute('id_lista');
                $data = $request->getParsedBody();
                $respuesta['estado'] = false;

                $respuesta['id_lista'] = $id_lista;
                $respuesta['data'] = $data;
            
                try{

                    if ($id_lista > 0){
                        $mysql = new Database("vtgsa_ventas");
                        $banco_nova = 30;
                        // verifica que el dato sea solamente de la base nova
                        $consulta = $mysql->Consulta_Unico("SELECT * FROM notas_registros WHERE (id_lista=".$id_lista.") AND (banco=".$banco_nova.")"); 

                        if (isset($consulta['id_lista'])){

                            $consulta = $mysql->Consulta_Unico("SELECT * FROM notas_registros_formularios_nova WHERE id_lista=".$id_lista);

                            if (!isset($consulta['id_formulario'])){
                                if ((isset($data['documento'])) && (!empty($data['documento']))){
                                    $documento = $data['documento'];

                                    if ((isset($data['nombres'])) && (!empty($data['nombres']))){
                                        $nombres = strtoupper($data['nombres']);
        
                                        if ((isset($data['fecha_nacimiento'])) && (!empty($data['fecha_nacimiento']))){
                                            $fecha_nacimiento = $data['fecha_nacimiento'];
            
                                            if ((isset($data['telefono'])) && (!empty($data['telefono']))){
                                                $telefono = $data['telefono'];
                
                                                if ((isset($data['correo'])) && (!empty($data['correo']))){
                                                    $correo = strtolower($data['correo']);
                    
                                                    if ((isset($data['direccion'])) && (!empty($data['direccion']))){
                                                        $direccion = strtoupper($data['direccion']);
                        
                                                        if ((isset($data['tipo_construccion'])) && (!empty($data['tipo_construccion']))){
                                                            $tipo_construccion = strtoupper($data['tipo_construccion']);
                            
                                                            if ((isset($data['anio_construccion'])) && (!empty($data['anio_construccion']))){
                                                                $anio_construccion = $data['anio_construccion'];
                                
                                                                if ((isset($data['num_pisos'])) && (!empty($data['num_pisos']))){
                                                                    $num_pisos = $data['num_pisos'];
                                    
                                                                    $id_formulario = $mysql->Ingreso("INSERT INTO notas_registros_formularios_nova (id_lista, documento, nombres, fecha_nacimiento, celular, correo, direccion, tipo_construccion, anio_construccion, numero_pisos) VALUES (?,?,?,?,?,?,?,?,?,?)", array($id_lista, $documento, $nombres, $fecha_nacimiento, $telefono, $correo, $direccion, $tipo_construccion, $anio_construccion, $num_pisos));
                                                                    $respuesta['id_formulario'] = $id_formulario;

                                                                    $respuesta['estado'] = true;
                                                                }else{
                                                                    $respuesta['error'] = "Debe ingresar el número de pisos de la construcción.";
                                                                }
                                                            }else{
                                                                $respuesta['error'] = "Debe ingresar el a&ntilde;o de construcción.";
                                                            }
                                                        }else{
                                                            $respuesta['error'] = "Debe ingresar un tipo de construcción.";
                                                        }
                                                    }else{
                                                        $respuesta['error'] = "Debe ingresar una dirección completa válida.";
                                                    }
                                                }else{
                                                    $respuesta['error'] = "Debe ingresar un corre electrónico válido.";
                                                }
                                            }else{
                                                $respuesta['error'] = "Debe ingresar un teléfono o celular válido.";
                                            }
                                        }else{
                                            $respuesta['error'] = "Debe ingresar la fecha de nacimiento.";
                                        }
                                    }else{
                                        $respuesta['error'] = "Debe ingresar los nombres completos.";
                                    }
                                }else{
                                    $respuesta['error'] = "Debe ingresar un documento válido.";
                                }
                            }else{
                                $respuesta['error'] = "Ya existe un formulario lleno con éste cliente.";
                            }

                           
                             
                        }else{
                            $respuesta['error'] = "No se encuentra información del contacto o éste no corresponde a SEGUROS NOVA";
                        }

                    }else{
                        $respuesta['error'] = "No se puede iniciar el formulario.";
                    }
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
                
                return $newResponse;
            });


        }); 

        $app->group('/analisis', function() use ($app) {


            $app->get("/informacion", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
                $respuesta['params'] = $params; 
            
                try{
                    $mysql = new Database("vtgsa_ventas");

                    $filtro = "";
                    if ((isset($params['filtro'])) && (!empty($params['filtro']))){
                        $filtro = "AND (banco LIKE '%".$params['filtro']."%')";
                    }

                    $bancos = $mysql->Consulta("SELECT * FROM notas_registros_bancos WHERE (estado=0) ".$filtro);

                    $listaBancos = [];
                    if (is_array($bancos)){
                        if (count($bancos) > 0){
                            foreach ($bancos as $linea) {
                                array_push($listaBancos, array(
                                    "id" => (int) $linea['id_banco'],
                                    "banco" => strtoupper($linea['banco'])
                                ));
                            }
                        }
                    }
                    
                    $respuesta['bancos'] = $listaBancos;
                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
                
                return $newResponse;
            });
            
            
            $app->get("/identificadores/{idBanco}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $idBanco = $request->getAttribute('idBanco');
                $respuesta['estado'] = false; 
            
                try{
                    $mysql = new Database("vtgsa_ventas");

                    $identificadores = $mysql->Consulta("SELECT
                    identificador
                    FROM notas_registros
                    WHERE (banco=".$idBanco.")
                    GROUP BY identificador");

                    $respuesta['identificadores'] = $identificadores;

                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
                
                return $newResponse;
            });

            $app->get("/estadisticas/{idBanco}/{identificador}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $idBanco = $request->getAttribute('idBanco');
                $identificador = $request->getAttribute('identificador');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false; 

                $respuesta['params'] = $params;
            
                try{
                    $mysql = new Database("vtgsa_ventas");

                    $from = date("Y-m-01");
                    if ((isset($params['from'])) && (!empty($params['from']))){
                        $from = $params['from'];
                    } 

                    $to = date("Y-m-d");
                    if ((isset($params['to'])) && (!empty($params['to']))){
                        $to = $params['to'];
                    } 

                    $identificadores = "";
                    if ((isset($identificador)) && (!empty($identificador))){
                        $identificadores = "AND (R.identificador='".$identificador."')";
                    } 

                    $filtro = "";
                    if ((isset($params['filtro'])) && (!empty($params['filtro']))){
                        $filtro = "AND (B.banco LIKE '%".$params['filtro']."%')";
                    }

                    $sql = "SELECT
                    R.estado, E.descripcion, COUNT(R.estado) AS total
                    FROM notas_registros R
                    LEFT JOIN notas_registros_bancos B
                    ON R.banco = B.id_banco
                    LEFT JOIN notas_registros_estados E
                    ON R.estado = E.id_estados
                    WHERE (R.banco=".$idBanco.") ".$identificadores." ".$filtro."
                    GROUP BY R.estado
                    ORDER BY COUNT(R.estado) DESC"; 

                    // AND (DATE(R.fecha_ultima_contacto) BETWEEN '".$from."' AND '".$to."')

                    $porEstado = $mysql->Consulta($sql);

                    $listaporEstado = [];
                    $efectividad = 0;
                    $totalVentas = 0;
                    $totalContactados = 0;

                    if (is_array($porEstado)){
                        if (count($porEstado) > 0){

                            $total = 0;
                            foreach ($porEstado as $linea) {
                                $total += $linea['total'];
                            }

                            foreach ($porEstado as $linea) {

                                $id_estado = $linea['estado'];

                                if ($id_estado == 7){
                                    $totalVentas += $linea['total'];
                                }

                                if (($id_estado != 2) && ($id_estado != 3) && ($id_estado != 6)){
                                    $totalContactados += $linea['total'];
                                }

                                $porcentaje = ($linea['total'] * 100) / $total; 

                                array_push($listaporEstado, array(
                                    "name" => $linea['descripcion'],
                                    "y" => (float) $linea['total']
                                ));
                            }
                        }
                    }

                    $efectividad = ($totalVentas / $totalContactados) * 100;


                    /// PARA GRAFICO DE AVANCES POR MES
                    $mesAnio = $mysql->Consulta("SELECT
                    MONTH(R.fecha_ultima_contacto) AS mes, YEAR(R.fecha_ultima_contacto) AS anio
                    FROM notas_registros R
                    LEFT JOIN notas_registros_bancos B
                    ON R.banco = B.id_banco
                    WHERE (R.banco=".$idBanco.") ".$identificadores." ".$filtro."
                    GROUP BY MONTH(R.fecha_ultima_contacto), YEAR(R.fecha_ultima_contacto)
                    ORDER BY YEAR(R.fecha_ultima_contacto) ASC, MONTH(R.fecha_ultima_contacto) ASC");

                    // Obtiene las categorias de los meses con anios
                    $categoriasProductos = [];
                    if (is_array($mesAnio)){
                        if (count($mesAnio) > 0){
                            foreach ($mesAnio as $linea) {
                                if (!is_null($linea['mes'])){
                                    $mes = $linea['mes'];
                                    $anio = $linea['anio'];

                                    $am = $anio."-".$mes;

                                    array_push($categoriasProductos, date("M, Y", strtotime($am)));
                                }
                            }
                        }
                    }

                    $productos = $mysql->Consulta("SELECT
                    B.id_banco, B.banco
                    FROM notas_registros_bancos B
                    WHERE (B.estado=0) ".$filtro);

                    $seriesProductos = [];

                    if (is_array($productos)){
                        if (count($productos) > 0){
                            foreach ($productos as $producto) {
                                $idProducto = $producto['id_banco'];
                                $nombreProducto = $producto['banco'];
                                $data = [];

                                if (is_array($mesAnio)){
                                    if (count($mesAnio) > 0){
                                        foreach ($mesAnio as $linea) {
                                            if (!is_null($linea['mes'])){
                                                $mes = $linea['mes'];
                                                $anio = $linea['anio'];
            
                                                $am = $anio."-".$mes;
            
                                                $from = date("Y-m-01", strtotime($am));
                                                $to = date("Y-m-t", strtotime($am));

                                                $avances = $mysql->Consulta("SELECT
                                                B.banco, COUNT(R.estado) AS total
                                                FROM notas_registros R
                                                LEFT JOIN notas_registros_bancos B
                                                ON R.banco = B.id_banco
                                                WHERE (R.banco=".$idProducto.") ".$identificadores." AND (DATE(R.fecha_ultima_contacto) BETWEEN '".$from."' AND '".$to."') AND (R.estado=7) AND (B.estado=0) ");
            
                                                if (is_array($avances)){
                                                    if (count($avances) > 0){
                                                        foreach ($avances as $lineaAvance) {
                                                            array_push($data, (float) $lineaAvance['total']);
                                                        }
                                                    }else{
                                                        array_push($data, 0);
                                                    }
                                                }
                                               
                                            }
                                            
                                        }
                                    }
                                }

                                array_push($seriesProductos, array(
                                    "name" => $nombreProducto,
                                    "data" => $data
                                ));
                            }
                        }
                    }  

                    // WIDGETS GENERALES
                    $widgets = [];

                    // Obtiene el avance de contactabilidad en general
                    $consulta = $mysql->Consulta("SELECT
                    R.estado, COUNT(R.id_lista) AS total
                    FROM notas_registros R
                    LEFT JOIN notas_registros_bancos B
                    ON R.banco = B.id_banco
                    WHERE (B.estado=0) ".$filtro."
                    GROUP BY R.estado
                    ORDER BY COUNT(R.id_lista) DESC"); 
                    
                    if (is_array($consulta)){
                        $totalContactados = 0;
                        $totalBases = 0;
                        $porcentaje = 0;
                        $totalVentas = 0;
                        $totalIncontactables = 0;
                        $totalNoInteresados = 0;

                        if (count($consulta) > 0){ 
                            
                            foreach ($consulta as $linea) {
                                $totalBases += $linea['total'];
                                if ($linea['estado'] != 0){
                                    $totalContactados += $linea['total'];
                                } 

                                if ($linea['estado'] == 7){
                                    $totalVentas += $linea['total'];
                                } 

                                if (($linea['estado'] == 2) || ($linea['estado'] == 3) || ($linea['estado'] == 6)){
                                    $totalIncontactables += $linea['total'];
                                } 

                                if ($linea['estado'] == 5){
                                    $totalNoInteresados += $linea['total'];
                                } 
                            } 
                        }

                        $porcentaje = ($totalContactados / $totalBases) * 100; 
                        array_push($widgets, array(
                            "total" => (int) $totalContactados,
                            "base" => number_format($totalBases, 0, ".", ","),
                            "nombre" => "Registros de ".number_format($totalBases, 0, ".", ","),
                            "porcentaje" => (float) $porcentaje,
                            "icon" => "aperture",
                            "color" => "blue"
                        ));

                        $porcentaje = ($totalVentas / $totalBases) * 100; 
                        array_push($widgets, array(
                            "total" => (int) $totalVentas,
                            "base" => number_format($totalBases, 0, ".", ","),
                            "nombre" => "Ventas Efectivas de ".number_format($totalBases, 0, ".", ","),
                            "porcentaje" => (float) $porcentaje,
                            "icon" => "award",
                            "color" => "success"
                        ));

                        $porcentaje = ($totalIncontactables / $totalBases) * 100; 
                        array_push($widgets, array(
                            "total" => (int) $totalIncontactables,
                            "base" => number_format($totalBases, 0, ".", ","),
                            "nombre" => "Incontactables de ".number_format($totalBases, 0, ".", ","),
                            "porcentaje" => (float) $porcentaje,
                            "icon" => "phone-off",
                            "color" => "danger"
                        ));

                        $porcentaje = ($totalNoInteresados / $totalBases) * 100; 
                        array_push($widgets, array(
                            "total" => (int) $totalNoInteresados,
                            "base" => number_format($totalBases, 0, ".", ","),
                            "nombre" => "No Interesados de ".number_format($totalBases, 0, ".", ","),
                            "porcentaje" => (float) $porcentaje,
                            "icon" => "trending-down",
                            "color" => "warning"
                        ));
                    } 

                    // Obtiene las ventas efectivas en general 


                   
                    $respuesta['widgets'] = $widgets;

                    $respuesta['avances'] = array(
                        "categorias" => $categoriasProductos,
                        "series" => $seriesProductos
                    );
 
                    $respuesta['efectividad'] = (float) $efectividad;
                    $respuesta['porEstado'] = $listaporEstado;  
                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
                
                return $newResponse;
            });

            $app->get("/estadisticas-asesor", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization'); 
                $params = $request->getQueryParams();
                $respuesta['estado'] = false; 
            
                try{
                    $mysql = new Database("vtgsa_ventas");

                    $from = date("Y-m-01");
                    if ((isset($params['from'])) && (!empty($params['from']))){
                        $from = $params['from'];
                    }

                    $to = date("Y-m-d");
                    if ((isset($params['to'])) && (!empty($params['to']))){
                        $to = $params['to'];
                    }

                    $producto = "";
                    if ((isset($params['producto'])) && (!empty($params['producto']))){
                        $producto = " AND (R.banco=".$params['producto'].")";
                    }

                    $identificador = "";
                    if ((isset($params['identificador'])) && (!empty($params['identificador']))){
                        $identificador = " AND (R.identificador='".$params['identificador']."')";
                    }

                    $buscador = "";
                    if ((isset($params['buscador'])) && (!empty($params['buscador']))){
                        $buscador = $params['buscador'];
                    }

                    $consulta = $mysql->Consulta("SELECT
                    R.asignado, UPPER(U.nombres) AS nombres, COUNT(R.asignado) AS total
                    FROM notas_registros R
                    LEFT JOIN usuarios U
                    ON R.asignado = U.id_usuario
                    WHERE (DATE(R.fecha_ultima_contacto) BETWEEN '".$from."' AND '".$to."') ".$producto." ".$identificador." AND (U.nombres LIKE '%".$buscador."%')
                    GROUP BY R.asignado 
                    ORDER BY COUNT(R.asignado) DESC"); 

                    $listados = [];

                    $categories = [];

                    $series1 = [];
                    array_push($series1, array(
                        "name" => 'Ventas Efectivas',
                        "type" => 'column',
                        "yAxis" => 1,
                        "data" => [],
                        "tooltip" => array(
                            "valueSuffix" => ' '
                        )
                    ));
                    array_push($series1, array(
                        "name" => 'Contactos Asignados',
                        "type" => 'spline', 
                        "data" => [],
                        "tooltip" => array(
                            "valueSuffix" => ' '
                        )
                    ));

                    $series2 = [];

                    if (is_array($consulta)){
                        if (count($consulta) > 0){
                            foreach ($consulta as $linea) {
                                $id_asesor = $linea['asignado'];
                                $total = $linea['total'];
                                $asesor = strtoupper($linea['nombres']);

                                array_push($categories, $asesor);

                                $detalle = [];

                                $porEstados = $mysql->Consulta("SELECT
                                R.estado, E.descripcion, COUNT(R.estado) AS total, E.es_venta
                                FROM notas_registros R
                                LEFT JOIN notas_registros_estados E
                                ON R.estado = E.id_estados
                                WHERE (R.asignado=".$id_asesor.") AND (DATE(R.fecha_ultima_contacto) BETWEEN '".$from."' AND '".$to."') ".$producto." ".$identificador."
                                GROUP BY R.estado 
                                ORDER BY COUNT(R.estado) DESC");

                                $totalVentas = 0;
                                $totalRegistros = 0;
                                $porcentajeEfectividad = 0;

                                if (is_array($porEstados)){
                                    if (count($porEstados) > 0){
                                        foreach ($porEstados as $lineaEstado) {

                                            if ($lineaEstado['es_venta'] == 1){
                                                $totalVentas += $lineaEstado['total'];
                                            }
                                            $totalRegistros += $lineaEstado['total'];

                                            array_push($detalle, array(
                                                "id" => (int) $lineaEstado['estado'],
                                                "descripcion" => strtoupper($lineaEstado['descripcion']),
                                                "total" => (int) $lineaEstado['total']
                                            ));
                                        }
                                    }
                                }

                                $porcentajeEfectividad = ($totalVentas / $totalRegistros) * 100; 
                                
                                array_push($series1[0]['data'], $totalVentas);
                                array_push($series1[1]['data'], $totalRegistros);

                                if (count($detalle) > 0){
                                    array_push($listados, array(
                                        "id" => (int) $id_asesor,
                                        "asesor" => $asesor,
                                        "total" => (int) $total,
                                        "efectividad" => array(
                                            "ventas" => (int) $totalVentas,
                                            "porcentaje" => (float) $porcentajeEfectividad
                                        ),
                                        "detalle" => $detalle
                                    ));
                                }
                            }
                        }
                    }
                    
                    $respuesta['categories'] = $categories;
                    $respuesta['series1'] = $series1;
                    $respuesta['listados'] = $listados;
                    $respuesta['consulta'] = $consulta;
                    
                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
                
                return $newResponse;
            });

        }); 

        $app->group('/canales', function() use ($app) {

            $app->get("/informacion", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
                
                try{                    
                    $auth = new Authentication();

                    $usuario = $auth->Valida_Sesion($authorization[0]);
                    
                    if ($usuario['estado']){                                                
                        $id_usuario = $usuario['usuario']['id_usuario'];

                        $mysql = new Database("vtgsa_ventas");

                        $departamentos = $mysql->Consulta("SELECT * FROM departamentos WHERE (estado=0) ORDER BY departamento ASC");
                        $canales = $mysql->Consulta("SELECT * FROM canales_lista WHERE (estado=0) ORDER BY canal ASC");

                        $respuesta['departamentos'] = $departamentos;
                        $respuesta['canales'] = $canales;
                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = $usuario['error'];
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/asesores/{id_departamento}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_departamento = $request->getAttribute('id_departamento');
                $respuesta['estado'] = false;
                
                try{                    
                    $auth = new Authentication();

                    $usuario = $auth->Valida_Sesion($authorization[0]);
                    
                    if ($usuario['estado']){                                                
                        $id_usuario = $usuario['usuario']['id_usuario'];

                        $mysql = new Database("vtgsa_ventas");

                        $asesores = $mysql->Consulta("SELECT * FROM usuarios WHERE (id_departamento=".$id_departamento.") AND (estado=0) ORDER BY nombres ASC");

                        $respuesta['asesores'] = $asesores;
                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = $usuario['error'];
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/asignacion", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
                
                try{                    
                    $auth = new Authentication();

                    $usuario = $auth->Valida_Sesion($authorization[0]);
                    
                    if ($usuario['estado']){                                                
                        $id_usuario = $usuario['usuario']['id_usuario'];

                        $mysql = new Database("vtgsa_ventas");

                        $buscador = "";
                        if (isset($params['buscador'])){
                            $buscador = $params['buscador'];
                        }

                        $from = date("Y-m-01 00:00:00");
                        $to = date("Y-m-d 23:59:59");

                        $consulta = $mysql->Consulta("SELECT 
                        C.id_caso, C.id_canal, L.canal, C.id_asesor, U.nombres AS nombres_asesor, C.id_usuario, A.nombres AS nombres_usuario, C.documento, C.nombres AS nombres_cliente,
                        C.celular, C.email, C.fecha_alta, C.estado, C.ciudad, C.id_departamento
                        FROM canales C
                        LEFT JOIN usuarios U
                        ON C.id_asesor=U.id_usuario
                        LEFT JOIN usuarios A
                        ON C.id_usuario=A.id_usuario
                        LEFT JOIN canales_lista L
                        ON C.id_canal=L.id_canal
                        WHERE (C.fecha_alta BETWEEN '".$from."' AND '".$to."') AND 
                        ((C.documento LIKE '%".$buscador."%') OR (C.nombres LIKE '%".$buscador."%') OR (U.nombres LIKE '%".$buscador."%') OR (A.nombres LIKE '%".$buscador."%') OR (L.canal LIKE '%".$buscador."%'))");

                        $respuesta['consulta'] = $consulta;
                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = $usuario['error'];
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/asignacion/{id_caso}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_caso = $request->getAttribute('id_caso');
                $respuesta['estado'] = false;
                
                try{                    
                    $auth = new Authentication();

                    $usuario = $auth->Valida_Sesion($authorization[0]);
                    
                    if ($usuario['estado']){                                                
                        $id_usuario = $usuario['usuario']['id_usuario'];

                        $mysql = new Database("vtgsa_ventas");

                        $consulta = $mysql->Consulta_Unico("SELECT 
                        C.id_caso, C.id_canal, L.canal, C.id_asesor, U.nombres AS nombres_asesor, C.id_usuario, A.nombres AS nombres_usuario, C.documento, C.nombres AS nombres_cliente,
                        C.celular, C.email, C.fecha_alta, C.estado, C.observaciones, C.ciudad, C.id_departamento
                        FROM canales C
                        LEFT JOIN usuarios U
                        ON C.id_asesor=U.id_usuario
                        LEFT JOIN usuarios A
                        ON C.id_usuario=A.id_usuario
                        LEFT JOIN canales_lista L
                        ON C.id_canal=L.id_canal
                        WHERE (C.id_caso=".$id_caso.")");

                        $respuesta['consulta'] = $consulta;
                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = $usuario['error'];
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });
            
            $app->post("/asignacion", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                $respuesta['estado'] = false;

                $respuesta['data'] = $data;
                
                try{                    
                    $auth = new Authentication();

                    $usuario = $auth->Valida_Sesion($authorization[0]);
                    
                    if ($usuario['estado']){                                                
                        $id_usuario = $usuario['usuario']['id_usuario'];

                        $mysql = new Database("vtgsa_ventas");

                        if ((isset($data['departamento'])) && (!empty($data['departamento']))){
                            
                            if ( ((isset($data['documento'])) && (!empty($data['documento']))) && ((isset($data['nombres'])) && (!empty($data['nombres']))) && ((isset($data['celular'])) && (!empty($data['celular']))) && ((isset($data['ciudad'])) && (!empty($data['ciudad']))) ){
                                $id_departamento = $data['departamento'];
                                $id_canal = $data['canal'];
                                $id_asesor = $data['asesor'];
                                $documento = $data['documento'];
                                $nombres = $data['nombres'];
                                $celular = $data['celular'];
                                $ciudad = $data['ciudad'];
                                $observaciones = "";
                                if (isset($data['observaciones'])){
                                    $observaciones = $data['observaciones'];
                                }
                                                                
                                $email = "";
                                $fecha_alta = date("Y-m-d H:i:s");
                                $estado = 0;

                                $id_asignacion = $mysql->Ingreso("INSERT INTO canales (id_canal, id_asesor, id_usuario, id_departamento, documento, nombres, celular, ciudad, email, observaciones, fecha_alta, estado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)", array($id_canal, $id_asesor, $id_usuario, $id_departamento, $documento, $nombres, $celular, $ciudad, $email, $observaciones, $fecha_alta, $estado));

                                $respuesta['id_asignacion'] = $id_asignacion;

                                $respuesta['estado'] = true;
                            }else{
                                $respuesta['error'] = "Debe ingresar todos los datos del cliente.";
                            }
                        }else{
                            $respuesta['error'] = "Debe seleccionar un departamento.";
                        }

                        
                    }else{
                        $respuesta['error'] = $usuario['error'];
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

        });

        $app->group('/clientes', function() use ($app) {

            // LOGIN

            $app->post("/login", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                
                $respuesta['estado'] = false;                
            
                try{
                    $email = $data['email'];
                    $password = $data['password'];

                    $mysql = new Database('vtgsa_ventas');
                    $auth = new Authentication();

                    $consulta = $mysql->Consulta_Unico("SELECT * FROM clientes WHERE (correo = '".$email."') AND (identificador= '".$password."')");

                    if (isset($consulta['id_cliente'])){
                        $id_cliente = $consulta['id_cliente'];
                        $correo = $consulta['correo'];
                        $identificador = $consulta['identificador'];
                        $fecha = date("Ymd");
                        $cadena = $correo."|".$identificador."|".$fecha;
                        $hash = $auth->encrypt_decrypt('encrypt', $cadena);

                        $actualizar = $mysql->Modificar("UPDATE clientes SET hash=? WHERE id_cliente=?", array($hash, $id_cliente));
                        $respuesta['hash'] = $hash;
                        $respuesta['estado'] = true;
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();                    
                }

                $newResponse = $response->withJson($respuesta);            
                return $newResponse;
            });            

            $app->get("/info", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                
                $respuesta['estado'] = false;

                $auth = new Authentication();
            
                try{
                    $valida_sesion = $auth->Valida_Hash($authorization[0]);
                    
                    if ($valida_sesion['estado']){
                        $id_cliente = $valida_sesion['usuario']['id_cliente'];

                        $respuesta['cliente'] = array(
                            "tipo_documento" => (int) $valida_sesion['usuario']['tipo_documento'],
                            "documento" => $valida_sesion['usuario']['documento'],                            
                            "apellidos" => $valida_sesion['usuario']['apellidos'],
                            "nombres" => $valida_sesion['usuario']['nombres'],
                            "correo" => $valida_sesion['usuario']['correo'],
                            "celular" => $valida_sesion['usuario']['celular']
                        );

                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = $valida_sesion['error'];
                    }
                        
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();                    
                }

                $newResponse = $response->withJson($respuesta);            
                return $newResponse;
            });

            $app->get("/paquetes", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                
                $respuesta['estado'] = false;

                $auth = new Authentication();
            
                try{
                    $valida_sesion = $auth->Valida_Hash($authorization[0]);
                    
                    if ($valida_sesion['estado']){
                        $id_cliente = $valida_sesion['usuario']['id_cliente'];

                        $mysql = new Database('vtgsa_ventas');

                        $consulta = $mysql->Consulta("SELECT * FROM paquetes_tienda WHERE estado=0");
                        $filtrado = [];
                        if (is_array($consulta)){
                            if (count($consulta) > 0){
                                foreach ($consulta as $linea) {
                                    array_push($filtrado, array(
                                        "id_paquete" => (int) $linea['id_paquete'],
                                        "nombre_paquete" => $linea['nombre_paquete'],
                                        "valor" => (float) $linea['valor'],
                                        "imagen" => $linea['imagen'],
                                    ));
                                }
                            }
                        }

                        $respuesta['consulta'] = $filtrado;

                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = $valida_sesion['error'];
                    }
                        
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();                    
                }

                $newResponse = $response->withJson($respuesta);            
                return $newResponse;
            });

            $app->post("/suscribir/{id_paquete}/{meses}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                $id_paquete = $request->getAttribute('id_paquete');
                $meses = $request->getAttribute('meses');
                
                $respuesta['estado'] = false;                
                $respuesta['id'] = $id_paquete;
                $respuesta['data'] = $data;

                $auth = new Authentication();
                $funciones = new Functions();                
            
                try{
                    $valida_sesion = $auth->Valida_Hash($authorization[0]);
                    
                    if ($valida_sesion['estado']){
                        $id_cliente = $valida_sesion['usuario']['id_cliente'];

                        $mysql = new Database('vtgsa_ventas');

                        $consulta_paquete = $mysql->Consulta_Unico("SELECT * FROM paquetes_tienda WHERE id_paquete=".$id_paquete);

                        if (isset($consulta_paquete['id_paquete'])){

                            // verifica si existe algun id_paquete comprado con este cliente

                            $ya_compro = $mysql->Consulta_Unico("SELECT * FROM clientes_compras WHERE (id_cliente=".$id_cliente.") AND (id_paquete=".$id_paquete.") AND (estado=0) ORDER BY id_compra DESC LIMIT 1");

                            if (!isset($ya_compro['id_compra'])){
                                // Validacion de campos

                                $tipo_documento = $data['tipo_documento'];
                                $documento = $data['documento'];
                                $nombres = $data['nombres'];
                                $apellidos = $data['apellidos'];
                                $celular = $data['celular'];
                                $email = $data['email'];

                                // $valida_documento = $funciones->Validar_Documento_Identidad($payment['documento']);

                                $valida_documento = array('estado' => false);
                                switch ($tipo_documento) {
                                    case 0: // CEDULA
                                        if (strlen($documento) == 10){
                                            $valida_ci = $funciones->Validar_Documento_Identidad($documento);
                                            if ($valida_ci['estado']){
                                                $valida_documento['codigo'] = $valida_ci['codigo'];
                                                $valida_documento['estado'] = true;
                                            }else{
                                                $valida_documento['error'] = $valida_ci['tipo'];
                                            }
                                            
                                        }else{
                                            $valida_documento['error'] = "La cédula de identidad debe contener 10 dígitos.";
                                        }
                                        break;
                                    case 1: // RUC
                                        if (strlen($documento) == 13){
                                            $valida_ruc = $funciones->Validar_Documento_Identidad($documento);
                                            if ($valida_ruc['estado']){
                                                $valida_documento['codigo'] = $valida_ruc['codigo'];
                                                $valida_documento['estado'] = true;
                                            }else{
                                                $valida_documento['error'] = $valida_ruc['tipo'];
                                            }
                                        }else{
                                            $valida_documento['error'] = "El RUC debe contener 13 dígitos.";
                                        }
                                        break;
                                    case 2: // CEDULA
                                        if (((strlen($documento) >= 5) && (strlen($documento) <= 15)) && ((ctype_alnum($documento)))){                                        
                                            $valida_documento['codigo'] = "PPN";
                                            $valida_documento['estado'] = true;
                                        }else{
                                            $valida_documento['error'] = "El pasaporte debe ser alfanumerico y contener entre 5 y 15 dígitos.";
                                        }
                                        break;
                                }

                                if ($valida_documento['estado']){ // Prosigue si el documento esta correcto y validado

                                    // Validacion de correo electronico
                                    $valida_correo = $funciones->Validar_Email($email);

                                    if ($valida_correo){
                                        // Validacion de nombre apellidos y/o razon social

                                        $union_nombres = $apellidos." ".$nombres;
                                        $validacion_nombres = array("estado" => false);

                                        if (($tipo_documento == 0) || ($tipo_documento == 2)){
                                            if ((trim($apellidos)!="") && (trim($nombres)!="")){
                                                $valnom = $funciones->Validar_Solo_Texto($union_nombres);

                                                if ($valnom){
                                                    $validacion_nombres['estado'] = true;
                                                }else{
                                                    $validacion_nombres['error'] = "Los nombres NO deben contener caracteres especiales.";
                                                }
                                            }else{
                                                $validacion_nombres['error'] = "Debe ingresar al menos un apellido y un nombre.";
                                            }
                                        }else{
                                            $validacion_nombres['estado'] = true;
                                        }                                        

                                        if ($validacion_nombres['estado']){

                                            /// validados los campos se procede al inicio de la suscripcion
                                            $valor = floatval($consulta_paquete['valor']);
                                            $cuotas = $valor / intval($meses);

                                            $p2p = new PlacetoPay(PLACETOPAY);

                                            if ($p2p->get_state()['estado']){

                                                $cuotas_pendientes = $funciones->Verificar_Cuotas_Pendientes($id_cliente);

                                                $respuesta['pendientes'] = $cuotas_pendientes;

                                                if ($cuotas_pendientes['estado'] == false){

                                                    $reference = "PQT".$id_paquete.date("YmdHis");
                                                    $payment = array(
                                                        "documento" => $documento,
                                                        "tipo" => $valida_documento['codigo'],
                                                        "nombres" => $nombres,
                                                        "apellidos" => $apellidos,
                                                        "correo" => $email,
                                                        "celular" => $celular,
                                                        "descripcion" => "Compra Suscripcion Paquete ".$id_paquete,
                                                        "pago_inicial" => 1 //(float) $cuotas
                                                    );
                                                    
                                                    $suscripcion = $p2p->make_suscription($reference, $payment);
                                                    $respuesta['payment'] = $suscripcion;

                                                    $requestId = $suscripcion['response']['requestId'];
                                                    $processUrl = $suscripcion['response']['processUrl'];
                                                    $status = $suscripcion['response']['status']['status'];
                                                    $reason = $suscripcion['response']['status']['reason'];
                                                    $message = $suscripcion['response']['status']['message'];
                                                    $date = $suscripcion['response']['status']['date'];
                                                                                                        
                                                    $tipo = $id_paquete;
                                    
                                                    $id_creado = $mysql->Ingreso("INSERT INTO pagos_p2p_suscripciones (id_cliente, identificacion, nombre, apellido, email, telefono, valor, tipo, meses, reference, requestId, processUrl, status, reason, message, date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", array($id_cliente, $documento, $nombres, $apellidos, $email, $celular, $valor, $tipo, $meses, $reference, $requestId, $processUrl, $status, $reason, $message, $date));

                                                    // ACTUALIZA INFORMACION DE CLIENTE
                                                    $modificar = $mysql->Modificar("UPDATE clientes SET tipo_documento=?, documento=?, nombres=?, apellidos=?, celular=?, correo=? WHERE id_cliente=?", array($tipo_documento, $documento, $nombres, $apellidos, $celular, $email, $id_cliente));
                                    
                                                    // OBTIENE LA URL DEL PAGO

                                                    $processUrl = '';

                                                    if (isset($suscripcion['response']['processUrl'])){
                                                        $processUrl = $suscripcion['response']['processUrl'];

                                                        $respuesta['processUrl'] = $processUrl;
                                                    
                                                        $respuesta['estado'] = true;
                                                    }else{
                                                        $respuesta['error'] = "No se pudo obtener un link de pago.";
                                                    }  

                                                }else{
                                                    $ultima_referencia = "";
                                                    if ($cuotas_pendientes['referencia'] != ''){
                                                        $ultima_referencia = "\nUltima referencia = ".$cuotas_pendientes['referencia'];
                                                        $ultima_referencia .= "\nValor = ".number_format($cuotas_pendientes['valor'], 2)." USD";
                                                    }
                                                    $respuesta['error'] = "El cliente mantiene cuotas pendientes de verificacion. ".$ultima_referencia;
                                                }

                                            }else{
                                                $respuesta['error'] = $p2p->get_state()['error'];
                                            }
                                        }else{
                                            $respuesta['error'] = $validacion_nombres['error'];
                                        }

                                    }else{
                                        $respuesta['error'] = "La estructura del correo electronico es incorrecta.";
                                    }

                                }else{
                                    $respuesta['error'] = $valida_documento['error'];
                                }
                
                            }else{
                                $respuesta['error'] = "Ya se adquirió este paquete y esta pendiente de pago.";
                            }                                                        
                                                  
                        }else{
                            $respuesta['error'] = "No se encuentra el paquete seleccionado.";
                        }                        
                        
                    }else{
                        $respuesta['error'] = $valida_sesion['error'];
                    }
                        
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();                    
                }

                $newResponse = $response->withJson($respuesta);            
                return $newResponse;
            });

            $app->get("/actualizar_tarjeta/{id_compra}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_compra = $request->getAttribute('id_compra');
                $respuesta['estado'] = false;

                $auth = new Authentication();
                $funciones = new Functions();                
            
                try{
                    $valida_sesion = $auth->Valida_Hash($authorization[0]);
                    
                    if ($valida_sesion['estado']){
                        $id_cliente = $valida_sesion['usuario']['id_cliente'];

                        $mysql = new Database('vtgsa_ventas');

                        // informacion de cliente 
                        $informacion = $mysql->Consulta_Unico("SELECT * FROM clientes WHERE id_cliente=".$id_cliente);

                        if (isset($informacion['id_cliente'])){
                            $valida_documento = "CI";
                            switch ($informacion['tipo_documento']) {
                                case 0:
                                    $valida_documento = "CI";
                                    break;
                                case 1:
                                    $valida_documento = "RUC";
                                    break;
                                case 2:
                                    $valida_documento = "PPN";
                                    break;
                            }

                            $reference = "PC".$id_cliente.date("YmdHis");
                            $payment = array(
                                "documento" => $informacion['documento'],
                                "tipo" => $valida_documento,
                                "nombres" => $informacion['nombres'],
                                "apellidos" => $informacion['apellidos'],
                                "correo" => $informacion['correo'],
                                "celular" => $informacion['celular'],
                                "descripcion" => "Actualizacion Tarjeta"                                
                            );

                            $p2p = new PlacetoPay(PLACETOPAY);

                            if ($p2p->get_state()['estado']){

                                $respuesta['req'] = $payment;
                                
                                $suscripcion = $p2p->make_suscription($reference, $payment);
                                $respuesta['payment'] = $suscripcion;

                                $requestId = $suscripcion['response']['requestId'];
                                $processUrl = $suscripcion['response']['processUrl'];
                                $status = $suscripcion['response']['status']['status'];
                                $reason = $suscripcion['response']['status']['reason'];
                                $message = $suscripcion['response']['status']['message'];
                                $date = $suscripcion['response']['status']['date'];
                                                                                    
                                $tipo = -1;
                                $identificador = $id_compra; // esta valor va en donde se guarda el valor de la cuota, pero para la acualizacion contiene el id de la compra correspondiente
                
                                $id_creado = $mysql->Ingreso("INSERT INTO pagos_p2p_suscripciones (id_cliente, identificacion, nombre, apellido, email, telefono, valor, tipo, meses, reference, requestId, processUrl, status, reason, message, date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", array($id_cliente, $informacion['documento'], $informacion['nombres'], $informacion['apellidos'], $informacion['correo'], $informacion['celular'], $identificador, $tipo, 0, $reference, $requestId, $processUrl, $status, $reason, $message, $date));
                
                                // OBTIENE LA URL DEL PAGO

                                $processUrl = '';

                                if (isset($suscripcion['response']['processUrl'])){
                                    $processUrl = $suscripcion['response']['processUrl'];

                                    $respuesta['processUrl'] = $processUrl;
                                
                                    $respuesta['estado'] = true;
                                }else{
                                    $respuesta['error'] = "No se pudo obtener un link de pago.";
                                }  
                            }else{
                                $respuesta['error'] = $p2p->get_state()['error'];
                            }
                            
                        }else{
                            $respuesta['error'] = "No se encontro informacion del cliente.";
                        }                                           
                        
                    }else{
                        $respuesta['error'] = $valida_sesion['error'];
                    }
                        
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();                    
                }

                $newResponse = $response->withJson($respuesta);            
                return $newResponse;
            });

            $app->get("/verificar_suscripcion/{reference}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');                
                $reference = $request->getAttribute('reference');
                
                $respuesta['estado'] = false;

                $auth = new Authentication();
                $funciones = new Functions();
            
                try{
                    $valida_sesion = $auth->Valida_Hash($authorization[0]);
                    
                    if ($valida_sesion['estado']){
                        $id_cliente = $valida_sesion['usuario']['id_cliente'];

                        $mysql = new Database('vtgsa_ventas');
                        $p2p =new PlacetoPay(PLACETOPAY);

                        if ($p2p->get_state()['estado']){
                            $busca_id = $mysql->Consulta_Unico("SELECT * FROM pagos_p2p_suscripciones WHERE reference='".$reference."'");

                            if (isset($busca_id['id_pago_p2p'])){
                                $requestId = $busca_id['requestId'];
                                $valor_paquete = $busca_id['valor'];
                                $id_paquete = $busca_id['tipo'];
                                $meses = $busca_id['meses'];

                                $consulta_pago = $p2p->check_status($requestId);
                                $respuesta['consulta_pago'] = $consulta_pago;

                                $status = $consulta_pago['status']['status'];
                                $message = $consulta_pago['status']['message'];
                                $date = $consulta_pago['status']['date'];

                                $fecha_aux = strtotime($date);
                                $fecha_pago = date("Y-m-d H:i:s", $fecha_aux);

                                switch ($status) {                                    
                                    case 'APPROVED':
                                        // reservar pago inicial

                                        $instrument = $consulta_pago['subscription']['instrument'];
                                        $token = "";
                                        if (is_array($instrument)){
                                            if (count($instrument) > 0){
                                                foreach ($instrument as $linea) {
                                                    if ($linea['keyword'] == "token"){
                                                        $token = $linea['value'];
                                                    }
                                                }
                                            }
                                        }

                                        if ($id_paquete == -1){ // Actualizacion de token de tarjeta
                                            $id_compra = $valor_paquete;

                                            // informacion cliente
                                            $informacion_cliente = $mysql->Consulta_Unico("SELECT * FROM clientes WHERE id_cliente=".$id_cliente);

                                            if (isset($informacion_cliente['id_cliente'])){
                                                $tipo_documento = $informacion_cliente['tipo_documento'];
                                                $documento = $informacion_cliente['documento'];
                                                $apellidos = $informacion_cliente['apellidos'];
                                                $nombres = $informacion_cliente['nombres'];
                                                $correo = $informacion_cliente['correo'];
                                                $celular = $informacion_cliente['celular'];

                                                $valida_tipo_documento = "CI";
                                                switch ($tipo_documento) {
                                                    case 0:
                                                        $valida_tipo_documento = "CI";
                                                        break;
                                                    case 1:
                                                        $valida_tipo_documento = "RUC";
                                                        break;
                                                    case 2:
                                                        $valida_tipo_documento = "PPN";
                                                        break;
                                                }

                                                // Realiza cobro de 1 dolar
                                                $reference = $reference = "ACT".$id_compra.date("YmdHis");
                                                $payment = array(
                                                    "documento" => $documento,
                                                    "tipo" => $valida_tipo_documento,
                                                    "nombres" => $nombres,
                                                    "apellidos" => $apellidos,
                                                    "correo" => $correo,
                                                    "celular" => $celular,
                                                    "descripcion" => "Actualizacion Tarjeta"
                                                );                                           

                                                $info_pago = $p2p->make_payment_actualizar($reference, $token, $payment);
                                                // Si es correcto o aprobado, entonces reversa el pago correspondiente
                                                $respuesta['dolar'] = $info_pago;
                                
                                                $status = $info_pago['response']['status']['status'];
                                                $message = $info_pago['response']['status']['message'];
                                                $date = $info_pago['response']['status']['date'];

                                                $informacion = $info_pago['response'];
                                                $requestId = $informacion['requestId'];

                                                $pago = null;
                                                $status_pago = "";
                                                $date_pago = "";
                                                $message_pago = "";

                                                $internalReference = "";

                                                if (isset($informacion['payment'][0])){
                                                    $pago = $informacion['payment'][0];
                                                    $status_pago = $pago['status']['status'];
                                                    $date_pago = $pago['status']['date'];
                                                    $message_pago = $pago['status']['message'];
                                                }

                                                $fecha_pago = "";                                            
                                                $fecha_aux = strtotime($date);
                                                $fecha_pago = date("Y-m-d H:i:s", $fecha_aux);                                            
                                                
                                                $respuesta_actualizacion = [];
                                                if ($status == "APPROVED"){
                                                    $internalReference = $pago['internalReference'];

                                                    // reversa pago
                                                    $respuesta['reverso'] = $p2p->reversar($internalReference);
                                                    
                                                    // guarda informacino de nuevo token
                                                    $tarjeta_compra = $mysql->Consulta_Unico("SELECT id_tarjeta FROM clientes_compras WHERE id_compra=".$id_compra);

                                                    if (isset($tarjeta_compra['id_tarjeta'])){
                                                        $id_tarjeta = $tarjeta_compra['id_tarjeta'];
                                                        $actualizacion = $mysql->Modificar("UPDATE clientes_tarjetas SET token=? WHERE id_tarjeta=?", array($token, $id_tarjeta));
                                                    }
                                                }else if ($status == "PENDING"){
                                                    // guarda informacion de requestID para preguntar nuevamente despues
                                                    $tarjeta_compra = $mysql->Consulta_Unico("SELECT id_tarjeta FROM clientes_compras WHERE id_compra=".$id_compra);

                                                    if (isset($tarjeta_compra['id_tarjeta'])){
                                                        $id_tarjeta = $tarjeta_compra['id_tarjeta'];
                                                        $actualizacion = $mysql->Modificar("UPDATE clientes_tarjetas SET requestId=? WHERE id_tarjeta=?", array($requestId, $id_tarjeta));
                                                    }
                                                }

                                                $respuesta['info_pago'] = array(
                                                    "estado" => $funciones->Traducir_Estado($status),
                                                    "referencia" => "Actualizacion Tarjeta",
                                                    "valor" => (float) 1,
                                                    "fecha" => $fecha_pago,
                                                    "razon" => $message
                                                );

                                                $respuesta['estado'] = true;
                                            }
                                            
                                            
                                        }else{
                                            $actualizar_cliente = $mysql->Modificar("UPDATE clientes SET token=? WHERE id_cliente=?", array($token, $id_cliente));

                                            // Verifica que no exista el registro ya de la compra 
                                            $verifica_compra = $mysql->Consulta_Unico("SELECT * FROM clientes_compras WHERE (id_cliente=".$id_cliente.") AND (id_paquete=".$id_paquete.")");

                                            if (!isset($verifica_compra['id_compra'])){
                                                // Ingresa el token de la nueva tarjeta a la compra 
                                                $fecha_alta = date("Y-m-d H:i:s");
                                                $fecha_modificacion = $fecha_alta;

                                                $id_tarjeta = $mysql->Ingreso("INSERT INTO clientes_tarjetas (id_cliente, token, fecha_alta, fecha_modificacion, estado) VALUES (?,?,?,?,?)", array($id_cliente, $token, $fecha_alta, $fecha_modificacion, 0));

                                                // Ingresa registro de compra

                                                $id_compra = $mysql->Ingreso("INSERT INTO clientes_compras (id_paquete, id_cliente, id_tarjeta, valor, cuotas, fecha_alta, estado) VALUES (?,?,?,?,?,?,?)", array($id_paquete, $id_cliente, $id_tarjeta, $valor_paquete, $meses, $fecha_alta, 0));
                                                                                    
                                                $valor_cuota = $valor_paquete / $meses;
                                                $estado = 0;
                                                $fecha_actual = strtotime(date("Y-m-d H:i:s"));                                    
                                                // crea cuotas para cobros
                                                for ($x=1; $x<=$meses; $x+=1){
                                                    // $fecha_actual = strtotime('+10 minute', $fecha_actual);
                                                    
                                                    $fecha_pago = date("Y-m-d H:i:s", $fecha_actual);

                                                    $ingreso = $mysql->Ingreso("INSERT INTO clientes_cuotas (id_compra, id_cliente, id_paquete, valor, fecha_pago, estado) VALUES (?,?,?,?,?,?)", array($id_compra, $id_cliente, $id_paquete, $valor_cuota, $fecha_pago, $estado));                                        

                                                    $fecha_actual = strtotime('+1 month', $fecha_actual);
                                                }         

                                                // realizar primer pago
                                                $iniciar_cobro = $funciones->Realizar_Cobro($id_cliente, $id_paquete);
                                                
                                                $respuesta['primer_cobro'] = $iniciar_cobro;

                                                if ($iniciar_cobro['estado']){ 
                                                    
                                                    
                                                    $estado_pago_inicial = $iniciar_cobro['process']['response']['status']['status'];
                                                
                                                    $fecha_pago = "";
                                                    if (isset($iniciar_cobro['process']['response']['status']['date'])){
                                                        $fecha_aux = strtotime($iniciar_cobro['process']['response']['status']['date']);
                                                        $fecha_pago = date("Y-m-d H:i:s", $fecha_aux);
                                                    }
                                                    
                                                    $respuesta['info_pago'] = array(
                                                        "estado" => $funciones->Traducir_Estado($estado_pago_inicial),
                                                        "referencia" => $iniciar_cobro['process']['response']['request']['payment']['reference'],
                                                        "valor" => (float) $valor_cuota,
                                                        "fecha" => $fecha_pago,
                                                        "razon" => $iniciar_cobro['process']['response']['status']['message']
                                                    );                                           

                                                    $respuesta['estado'] = true;
                                                }else{
                                                    $respuesta['error'] = $iniciar_cobro['error'];
                                                }
                                            }else{
                                                $respuesta['error'] = "El registro ya ha sido realizado.";
                                            }
                                        }                                                                                
                                        break;
                                    case "REJECTED":
                                        $respuesta['error'] = $message;
                                        break;
                                    case 'FAILED':
                                        $respuesta['error'] = $message;
                                        break;
                                    case 'PENDING':
                                        $respuesta['error'] = $message;
                                        break;
                                    case 'PENDING_VALIDATION':
                                        $respuesta['error'] = $message;
                                        break;
                                }                                                        
                                
                            }else{
                                $respuesta['error'] = "No se encontro la referencia del pago.";
                            }  
                        }else{
                            $respuesta['error'] = $p2p->get_state()['error'];
                        }
                    }else{
                        $respuesta['error'] = $valida_sesion['error'];
                    }                    
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();                    
                }

                $newResponse = $response->withJson($respuesta);            
                return $newResponse;
            });

            $app->get("/pago/{id_cliente}/{id_paquete}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_cliente = $request->getAttribute('id_cliente');
                $id_paquete = $request->getAttribute('id_paquete');
                
                $respuesta['estado'] = false;

                $auth = new Authentication();
                $funciones = new Functions();

                try{
                    $mysql = new Database('vtgsa_ventas');

                    $busca_compra_paquete = $mysql->Consulta_Unico("SELECT * FROM clientes_compras WHERE (id_cliente=".$id_cliente.") AND (id_paquete=".$id_paquete.")");

                    if (isset($busca_compra_paquete['id_compra'])){
                        $id_compra = $busca_compra_paquete['id_compra'];
                        $estado = $busca_compra_paquete['estado'];

                        if ($estado == 0){
                            $iniciar_cobro = $funciones->Realizar_Cobro($id_cliente, $id_paquete);

                            $respuesta['respuesta_cobro'] = $iniciar_cobro;

                            if ($iniciar_cobro['estado']){
                                $respuesta['cuota'] = $iniciar_cobro['cuota'];
                                $respuesta['estado'] = true;
                            }else{
                                $respuesta['error'] = $iniciar_cobro['error'];
                            }
                        }else{
                            if ($estado == 1){
                                $respuesta['error'] = "Su suscripcion ya ha sido pagada en su totalidad.";
                            }else if ($estado == 2){
                                $respuesta['error'] = "Su suscricion ha sido cancelada.";
                            }                                
                        }
                    }else{
                        $respuesta['error'] = "No se pudo encontrar el paquete o suscripcion contratada.";
                    }
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();                    
                }

                $newResponse = $response->withJson($respuesta);            
                return $newResponse;
            });

            $app->get("/pagos/{id_compra}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');                
                $id_compra = $request->getAttribute('id_compra');
                $respuesta['estado'] = false;

                $auth = new Authentication();
            
                try{
                    $valida_sesion = $auth->Valida_Hash($authorization[0]);
                    
                    if ($valida_sesion['estado']){
                        $id_cliente = $valida_sesion['usuario']['id_cliente'];                        

                        $mysql = new Database('vtgsa_ventas');
                        $funciones = new Functions();
                        
                        $consulta = $mysql->Consulta("SELECT C.id_cuota, C.id_compra, C.id_cliente, C.id_paquete, T.nombre_paquete, C.valor, C.fecha_pago, C.date_status, C.status, C.estado, C.reference FROM clientes_cuotas C, paquetes_tienda T WHERE (C.id_cliente=".$id_cliente.") AND (C.id_compra=".$id_compra.") AND (C.id_paquete=T.id_paquete)");

                        $filtrado = [];

                        if (is_array($consulta)){
                            if (count($consulta) > 0){
                                foreach ($consulta as $linea) {
                                    $fecha_pago = "";
                                    if (trim($linea['date_status']) != ''){
                                        $fecha_pago = date("Y-m-d H:i:s", strtotime($linea['date_status']));
                                    }
                                    
                                    $total = $linea['valor'];
                                    $base_imponible_cero = $total * 0.46;
                                    $base_imponible = ($total * 0.54) / 1.12;
                                    $impuesto = $base_imponible * 0.12;
                                    
                                    array_push($filtrado, array(
                                        "id_cuota" => (int) $linea['id_cuota'],
                                        "paquete" => $linea['nombre_paquete'],
                                        "estado" => array(
                                            "valor" => (int) $linea['estado'],
                                            "descripcion" => $funciones->Traducir_Estado($linea['status'])
                                        ),
                                        "cuota" => array(
                                            "base_cero" => (float) $base_imponible_cero,
                                            "base_imponible" =>  (float) $base_imponible,
                                            "impuesto" =>  (float) $impuesto,
                                            "total" =>  (float) $total
                                        ),
                                        "referencia" => $linea['reference'],
                                        "fecha_cuota" => $linea['fecha_pago'],
                                        "fecha_pago" => $fecha_pago
                                    ));
                                }
                            }
                        }

                        $respuesta['consulta'] = $filtrado;
                        $respuesta['estado'] = true;
                        
                    }else{
                        $respuesta['error'] = $valida_sesion['error'];
                    }
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();                    
                }

                $newResponse = $response->withJson($respuesta);            
                return $newResponse;
            });

            $app->get("/compras", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');                
                
                $respuesta['estado'] = false;

                $auth = new Authentication();
            
                try{
                    $valida_sesion = $auth->Valida_Hash($authorization[0]);
                    
                    if ($valida_sesion['estado']){
                        $id_cliente = $valida_sesion['usuario']['id_cliente'];                        

                        $mysql = new Database('vtgsa_ventas');
                        
                        $consulta = $mysql->Consulta("SELECT C.id_compra, C.id_paquete, T.nombre_paquete, C.valor, C.cuotas, C.fecha_alta, C.estado FROM clientes_compras C, paquetes_tienda T WHERE (C.id_cliente=".$id_cliente.") AND (C.id_paquete=T.id_paquete)");

                        $filtrado = [];

                        if (is_array($consulta)){
                            if (count($consulta) > 0){
                                foreach ($consulta as $linea) {

                                    $status = "No Activo";
                                    if ($linea['estado'] == 0){
                                        $status = "Activo";
                                    }

                                    array_push($filtrado, array(
                                        "id_compra" => (int) $linea['id_compra'],
                                        "id_paquete" => (int) $linea['id_paquete'],
                                        "paquete" => $linea['nombre_paquete'],
                                        "estado" => array(
                                            "valor" => (int) $linea['estado'],
                                            "descripcion" => $status 
                                        ),
                                        "cuotas" => (float) $linea['cuotas'],
                                        "fecha_alta" => $linea['fecha_alta'],
                                        "valor" => (float) $linea['valor']
                                    ));
                                }
                            }
                        }

                        $respuesta['consulta'] = $filtrado;
                        $respuesta['estado'] = true;
                        
                    }else{
                        $respuesta['error'] = $valida_sesion['error'];
                    }
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();                    
                }

                $newResponse = $response->withJson($respuesta);            
                return $newResponse;
            });

            $app->get("/reversar/{id_compra}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');                
                $id_compra = $request->getAttribute('id_compra');
                $respuesta['estado'] = false;

                $auth = new Authentication();
            
                try{
                    $valida_sesion = $auth->Valida_Hash($authorization[0]);
                    
                    if ($valida_sesion['estado']){
                        $id_cliente = $valida_sesion['usuario']['id_cliente'];                        

                        $mysql = new Database('vtgsa_ventas');

                        $busca_paquete_compra = $mysql->Consulta_Unico("SELECT * FROM clientes_compras WHERE (id_compra=".$id_compra.")");                        

                        if (isset($busca_paquete_compra['id_compra'])){
                            $compra_cancelada = 2;
                            $id_compra = $busca_paquete_compra['id_compra'];

                            $modificar = $mysql->Modificar("UPDATE clientes_compras SET estado=? WHERE id_compra=?", array($compra_cancelada, $id_compra));

                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = "No se encontro ninguna suscripcion.";
                        }
                    }else{
                        $respuesta['error'] = $valida_sesion['error'];
                    }
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();                    
                }

                $newResponse = $response->withJson($respuesta);            
                return $newResponse;
            });

            $app->get("/validar_cuota/{id_cuota}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');                
                $id_cuota = $request->getAttribute('id_cuota');
                
                $respuesta['estado'] = false;

                $auth = new Authentication();
                $funciones = new Functions();
            
                try{
                    $valida_sesion = $auth->Valida_Hash($authorization[0]);
                    
                    if ($valida_sesion['estado']){
                        $id_cliente = $valida_sesion['usuario']['id_cliente'];

                        $mysql = new Database('vtgsa_ventas');
                        $p2p = new PlacetoPay(PLACETOPAY);

                        if ($p2p->get_state()['estado']){

                            $busca_cuota = $mysql->Consulta_Unico("SELECT * FROM clientes_cuotas WHERE id_cuota=".$id_cuota);

                            if (isset($busca_cuota['id_cuota'])){
                                $requestId = $busca_cuota['requestId'];

                                $consulta_pago = $p2p->check_status($requestId);
                                $respuesta['consulta_pago'] = $consulta_pago;
                                
                                $base64 = base64_encode(json_encode($consulta_pago));

                                $status = $consulta_pago['status']['status'];
                                $message = $consulta_pago['status']['message'];
                                $date = $consulta_pago['status']['date'];

                                $requestId = "";
                                $status_pago = "";
                                $date_pago = "";
                                $message_pago = "";
                                $internalReference = "";
                                $paymentMethodName = "";
                                $authorization = "";
                                $reference_cuota = "";
                                $receipt = "";
                                $estado_cuota = 0;
                                
                                $requestId = $consulta_pago['requestId'];

                                $pago = null;
                                $status_pago = "";
                                $date_pago = "";
                                $message_pago = "";

                                if (isset($consulta_pago['payment'][0])){
                                    $pago = $consulta_pago['payment'][0];
                                    $status_pago = $pago['status']['status'];
                                    $date_pago = $pago['status']['date'];
                                    $message_pago = $pago['status']['message'];
                                }                  

                                switch ($status) {                                    
                                    case 'APPROVED':
                                        $internalReference = $pago['internalReference'];
                                        $paymentMethodName = $pago['paymentMethodName'];
                                        $authorization = $pago['authorization'];
                                        $reference_cuota = $pago['reference'];
                                        $receipt = $pago['receipt'];
                    
                                        $estado_cuota = 1;
                                        
                                        break;
                                    case "REJECTED":
                                        $status_pago = $consulta_pago['status']['status'];
                                        $date_pago = $consulta_pago['status']['date'];
                                        $message_pago = $consulta_pago['status']['message'];
                                        $estado_cuota = 5;

                                        $respuesta['error'] = $message;
                                        break;
                                    case 'FAILED':
                                        $status_pago = $consulta_pago['status']['status'];
                                        $date_pago = $consulta_pago['status']['date'];
                                        $message_pago = $consulta_pago['status']['message'];
                                        $estado_cuota = 5;

                                        $respuesta['error'] = $message;
                                        break;
                                    case 'PENDING':
                                        $status_pago = $consulta_pago['status']['status'];
                                        $date_pago = $consulta_pago['status']['date'];
                                        $message_pago = $consulta_pago['status']['message'];
                                        $estado_cuota = 0;

                                        $respuesta['error'] = $message;
                                        break;
                                    case 'PENDING_VALIDATION':
                                        $status_pago = $consulta_pago['status']['status'];
                                        $date_pago = $consulta_pago['status']['date'];
                                        $message_pago = $consulta_pago['status']['message'];
                                        $estado_cuota = 0;

                                        $respuesta['error'] = $message;
                                        break;                                    
                                }        

                                $actualiza_cuota = $mysql->Modificar("UPDATE clientes_cuotas SET requestId=?, status=?, date_status=?, message_status=?, internalReference=?, paymentMethodName=?, authorization=?, receipt=?, base64=?, estado=? WHERE id_cuota=?", array($requestId, $status_pago, $date_pago, $message_pago, $internalReference, $paymentMethodName, $authorization, $receipt, $base64, $estado_cuota, $id_cuota));
                            }else{
                                $respuesta['error'] = "No se encontro informacion de la cuota o pago.";
                            }                           
                        }else{
                            $respuesta['error'] = $p2p->get_state()['error'];
                        }
                    }else{
                        $respuesta['error'] = $valida_sesion['error'];
                    }                    
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();                    
                }

                $newResponse = $response->withJson($respuesta);            
                return $newResponse;
            });

            $app->get("/validar_actualizacion/{id_tarjeta}/{id_compra}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');                
                $id_tarjeta = $request->getAttribute('id_tarjeta');
                $id_compra = $request->getAttribute('id_compra');
                
                $respuesta['estado'] = false;

                $auth = new Authentication();
                $funciones = new Functions();
            
                try{
                    $valida_sesion = $auth->Valida_Hash($authorization[0]);
                    
                    if ($valida_sesion['estado']){
                        $id_cliente = $valida_sesion['usuario']['id_cliente'];

                        $mysql = new Database('vtgsa_ventas');
                        $p2p = new PlacetoPay(PLACETOPAY);

                        if ($p2p->get_state()['estado']){

                            // obtiene el token correspondiente
                            $para_token = $mysql->Consulta_Unico("SELECT * FROM pagos_p2p_suscripciones WHERE (id_cliente=".$id_cliente.") AND (valor=".$id_compra.") AND (tipo=-1) ORDER BY id_pago_p2p DESC LIMIT 1");

                            if (isset($para_token['requestId'])){
                                $requestId_token = $para_token['requestId'];

                                $consulta_pago = $p2p->check_status($requestId_token);
                                $respuesta['consulta_pago'] = $consulta_pago;

                                $status_token = $consulta_pago['status']['status'];

                                if ($status_token == "APPROVED"){
                                    $instrument = $consulta_pago['subscription']['instrument'];
                                    $token = "";
                                    if (is_array($instrument)){
                                        if (count($instrument) > 0){
                                            foreach ($instrument as $linea) {
                                                if ($linea['keyword'] == "token"){
                                                    $token = $linea['value'];
                                                }
                                            }
                                        }
                                    }
                                    
                                     // busca el pago pendiente 

                                    $busca_cuota = $mysql->Consulta_Unico("SELECT requestId FROM clientes_tarjetas WHERE (id_tarjeta=".$id_tarjeta.")");

                                    if (isset($busca_cuota['requestId'])){
                                        $requestId = $busca_cuota['requestId'];

                                        $consulta_pago = $p2p->check_status($requestId);
                                        $respuesta['consulta_pago'] = $consulta_pago;

                                        $base64 = base64_encode(json_encode($consulta_pago));

                                        $status = $consulta_pago['status']['status'];
                                        $message = $consulta_pago['status']['message'];
                                        $date = $consulta_pago['status']['date'];

                                        $requestId = "";
                                        $status_pago = "";
                                        $date_pago = "";
                                        $message_pago = "";
                                        $internalReference = "";
                                        $paymentMethodName = "";
                                        $authorization = "";
                                        $reference_cuota = "";
                                        $receipt = "";
                                        $estado_cuota = 0;
                                        
                                        $requestId = $consulta_pago['requestId'];

                                        $pago = null;
                                        $status_pago = "";
                                        $date_pago = "";
                                        $message_pago = "";  
                                        
                                        if (isset($consulta_pago['payment'][0])){
                                            $pago = $consulta_pago['payment'][0];
                                            $status_pago = $pago['status']['status'];
                                            $date_pago = $pago['status']['date'];
                                            $message_pago = $pago['status']['message'];
                                        }  

                                        if ($status == 'APPROVED'){
                                            $internalReference = $pago['internalReference'];                                            

                                            // reversar
                                            $respuesta['reverso'] = $p2p->reversar($internalReference);

                                            $actualizacion = $mysql->Modificar("UPDATE clientes_tarjetas SET token=?, requestId=? WHERE id_tarjeta=?", array($token, "", $id_tarjeta));
                                        }

                                        // $actualiza_cuota = $mysql->Modificar("UPDATE clientes_cuotas SET requestId=?, status=?, date_status=?, message_status=?, internalReference=?, paymentMethodName=?, authorization=?, receipt=?, base64=?, estado=? WHERE id_cuota=?", array($requestId, $status_pago, $date_pago, $message_pago, $internalReference, $paymentMethodName, $authorization, $receipt, $base64, $estado_cuota, $id_cuota));
                                    }       
                                }                                
                            }else{
                                $respuesta['error'] = "No se encontro informacion para la tokenizacion";
                            }
                        }else{
                            $respuesta['error'] = $p2p->get_state()['error'];
                        }
                    }else{
                        $respuesta['error'] = $valida_sesion['error'];
                    }                    
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();                    
                }

                $newResponse = $response->withJson($respuesta);            
                return $newResponse;
            });
    
        });

        $app->post("/notificacion", function(Request $request, Response $response){
            $authorization = $request->getHeader('Authorization');
            $data = $request->getParsedBody();
            
            $respuesta['estado'] = false;
        
            try{
                $base64 = json_encode($data);

                $carpeta = __DIR__."/../../public/json/resp_".date("Ymd_His").".json";
                                                
                file_put_contents($carpeta, $base64);
                                    
                $respuesta['estado'] = true;

            }catch(PDOException $e){
                $respuesta['error'] = $e->getMessage();                    
            }
    
            $newResponse = $response->withJson($respuesta);            
            return $newResponse;
        });

        // PORTAL 
        $app->group('/portal', function() use ($app) {
            // LOGIN

            $app->post("/login", function(Request $request, Response $response){
                $data = $request->getParsedBody();
                $respuesta['estado'] = false;

                try{
                    $functions = new Functions();

                    $username = $data['username'];
                    $password = $data['password'];

                    $mysql = new Database("mvevip_crm");

                    $consulta = $mysql->Consulta_Unico("SELECT * FROM clientes WHERE (documento='".$username."') AND (password='".$password."')");

                    if (isset($consulta['id_cliente'])){
                        if ($consulta['estado'] == 0){
                            $hoy = date("Y-m-d H:i:s");

                            $cadena = $consulta['id_cliente']."|PORTAL|".date("Y-m-d");
                            $autenticacion = new Authentication();
                            $hash = $autenticacion->encrypt_decrypt('encrypt', $cadena);

                            $respuesta['hash'] = $hash;
                            
                            $modificar = $mysql->Modificar("UPDATE clientes SET hash=?, ultimo_ingreso=? WHERE id_cliente=?", array($hash, $hoy, $consulta['id_cliente']));

                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = "Su usuario se encuentra deshabilitado.";
                        }
                    }else{
                        $respuesta['error'] = "No se encuentra sus credenciales.";
                    }
                    
                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/session", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');                
                $respuesta['estado'] = false;

                try{
                    if (isset($authorization[0])){
                        $hash = $authorization[0];
                        $autenticacion = new Authentication();

                        $validacion = $autenticacion->Valida_Sesion_Cliente($hash);

                        if ($validacion['estado']){
                            $respuesta['cliente'] = $validacion['cliente'];
                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = $validacion['error'];
                        }
                        
                    }else{
                        $respuesta['error'] = "No se ha especificado token.";
                    }
                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            // IMAGENES SLIDER
            $app->get("/slider", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');                
                $respuesta['estado'] = false;

                try{
                    if (isset($authorization[0])){
                        $hash = $authorization[0];
                        $autenticacion = new Authentication();

                        $validacion = $autenticacion->Valida_Sesion_Cliente($hash);

                        if ($validacion['estado']){
                            // Busca imagenes para slider
                            $mysql = new Database("mvevip_crm");

                            $consulta = $mysql->Consulta("SELECT * FROM slider WHERE estado=0 ORDER BY orden ASC");

                            $filtrado = [];
                            if (is_array($consulta)){
                                if (count($consulta) > 0){
                                    foreach ($consulta as $linea) {
                                        array_push($filtrado, array(
                                            "url" => $linea['url']
                                        ));
                                    }
                                }
                            }
                            $respuesta['slider'] = $filtrado;
                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = $validacion['error'];
                        }
                        
                    }else{
                        $respuesta['error'] = "No se ha especificado token.";
                    }
                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            // RESERVAS POR CLIENTE
            $app->get("/reservas", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');                
                $respuesta['estado'] = false;

                try{
                    if (isset($authorization[0])){
                        $hash = $authorization[0];
                        $autenticacion = new Authentication();

                        $validacion = $autenticacion->Valida_Sesion_Cliente($hash);

                        if ($validacion['estado']){
                            $id_cliente = $validacion['cliente']['id_cliente'];

                            // Busca imagenes para slider
                            $mysql = new Database("mvevip_crm");

                            $consulta = $mysql->Consulta("SELECT 
                            R.id_reserva, R.id_ticket, R.num_adultos, R.num_ninos, R.num_ninos2, R.num_discapacitados_3edad, D.nombre, R.pickup_fecha, R.dropoff_fecha, D.imagen, T.estado
                            FROM 
                            reservas R
                            LEFT JOIN tickets T
                            ON R.id_ticket=T.id_ticket
                            LEFT JOIN destinos D
                            ON R.pickup_destino=D.id_destino
                            WHERE (T.id_cliente=".$id_cliente.")");

                            $filtrado = [];
                            if (is_array($consulta)){
                                if (count($consulta) > 0){
                                    foreach ($consulta as $linea) {
                                        $total_pasajeros = $linea['num_adultos'] + $linea['num_ninos'] + $linea['num_ninos2'] + $linea['num_discapacitados_3edad'];
                                        array_push($filtrado, array(
                                            "id_reserva" => (int) $linea['id_reserva'],
                                            "id_ticket" => (int) $linea['id_ticket'],
                                            "destino" => $linea['nombre'],
                                            "check_in" => $linea['pickup_fecha'],
                                            "check_out" => $linea['dropoff_fecha'],
                                            "imagen" => $linea['imagen'],
                                            "personas" => array(
                                                "total" => (int) $total_pasajeros,
                                                "detalle" => array(
                                                    "adultos" => (int) $linea['num_adultos'],
                                                    "ninos" => (int) $linea['num_ninos'],
                                                    "ninos2" => (int) $linea['num_ninos2'],
                                                    "discapacitados_3edad" => (int) $linea['num_discapacitados_3edad'],
                                                )
                                            )
                                        ));
                                    }
                                }
                            }
                            $respuesta['consulta'] = $filtrado;
                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = $validacion['error'];
                        }
                        
                    }else{
                        $respuesta['error'] = "No se ha especificado token.";
                    }
                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            // RESERVAS POR CLIENTE
            $app->get("/reservas/{id_reserva}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_reserva = $request->getAttribute('id_reserva');
                $respuesta['estado'] = false;

                try{
                    if (isset($authorization[0])){
                        $hash = $authorization[0];
                        $autenticacion = new Authentication();

                        $validacion = $autenticacion->Valida_Sesion_Cliente($hash);

                        if ($validacion['estado']){
                            $id_cliente = $validacion['cliente']['id_cliente'];

                            // Busca imagenes para slider
                            $mysql = new Database("mvevip_crm");

                            $consulta = $mysql->Consulta("SELECT
                            E.id_destinos, E.check_in, E.check_out, E.nombre_hotel, E.direccion_hotel, E.num_reserva, D.nombre, D.portada, R.id_reserva, (R.num_adultos + R.num_ninos + R.num_ninos2 + R.num_discapacitados_3edad) AS total
                            FROM reservas_destinos E
                            LEFT JOIN reservas R
                            ON E.id_reserva = R.id_reserva
                            LEFT JOIN destinos D
                            ON E.destino = D.id_destino
                            WHERE
                            R.id_reserva=".$id_reserva);

                            $filtrado = [];
                            if (is_array($consulta)){
                                if (count($consulta) > 0){
                                    foreach ($consulta as $linea) {
                                        $check_in = date_create($linea['check_in']);
                                        $check_out = date_create($linea['check_out']);

                                        $calculo = date_diff($check_in, $check_out);
                                        $dias = intval($calculo->format('%R%a'));
                                        $noches = $dias - 1;
                                        array_push($filtrado, array(
                                            "id_reserva" => (int) $linea['id_reserva'],
                                            "destino" => array(
                                                "id" => (int) $linea['id_destinos'],
                                                "descripcion" => $linea['nombre']
                                            ),
                                            "check_in" => $linea['check_in'],
                                            "check_out" => $linea['check_out'],
                                            "nombre_hotel" => $linea['nombre_hotel'],
                                            "direccion_hotel" => $linea['direccion_hotel'],
                                            "num_reserva" => $linea['num_reserva'],
                                            "portada" => $linea['portada'],
                                            "dias" => $dias,
                                            "noches" => $noches,
                                            "personas" => (int) $linea['total']
                                        ));
                                    }
                                }
                            }
                            $respuesta['consulta']= $filtrado;
                            $respuesta['estado'] = true;
                         
                        }else{
                            $respuesta['error'] = $validacion['error'];
                        }
                        
                    }else{
                        $respuesta['error'] = "No se ha especificado token.";
                    }
                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            // RESERVAS POR CLIENTE
            $app->get("/reservas/{id_reserva}/{id_destino}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_reserva = $request->getAttribute('id_reserva');
                $id_destino = $request->getAttribute('id_destino');
                $respuesta['estado'] = false;

                try{
                    if (isset($authorization[0])){
                        $hash = $authorization[0];
                        $autenticacion = new Authentication();

                        $validacion = $autenticacion->Valida_Sesion_Cliente($hash);

                        if ($validacion['estado']){
                            $id_cliente = $validacion['cliente']['id_cliente'];

                            // Busca imagenes para slider
                            $mysql = new Database("mvevip_crm");

                            $consulta = $mysql->Consulta_Unico("SELECT
                            E.id_destinos, E.check_in, E.check_out, E.nombre_hotel, E.direccion_hotel, E.num_reserva, D.nombre, D.portada, R.id_reserva, (R.num_adultos + R.num_ninos + R.num_ninos2 + R.num_discapacitados_3edad) AS total
                            FROM reservas_destinos E
                            LEFT JOIN reservas R
                            ON E.id_reserva = R.id_reserva
                            LEFT JOIN destinos D
                            ON E.destino = D.id_destino
                            WHERE
                            (R.id_reserva=".$id_reserva.") AND (E.id_destinos=".$id_destino.")");

                            if (isset($consulta['id_destinos'])){
                                $check_in = date_create($consulta['check_in']);
                                $check_out = date_create($consulta['check_out']);

                                $calculo = date_diff($check_in, $check_out);
                                $dias = intval($calculo->format('%R%a'));
                                $noches = $dias - 1;

                                $amenities = $mysql->Consulta("SELECT * FROM reservas_amenities WHERE (id_reserva=".$id_reserva.") AND (id_destino=".$id_destino.")");

                                $respuesta['consulta'] = array(
                                    "id_reserva" => (int) $consulta['id_reserva'],
                                    "destino" => array(
                                        "id" => (int) $consulta['id_destinos'],
                                        "descripcion" => $consulta['nombre']
                                    ),
                                    "check_in" => $consulta['check_in'],
                                    "check_out" => $consulta['check_out'],
                                    "nombre_hotel" => $consulta['nombre_hotel'],
                                    "direccion_hotel" => $consulta['direccion_hotel'],
                                    "num_reserva" => $consulta['num_reserva'],
                                    "portada" => $consulta['portada'],
                                    "dias" => $dias,
                                    "noches" => $noches,
                                    "personas" => (int) $consulta['total'],
                                    "amenities" => $amenities
                                );

                                $respuesta['estado'] = true;
                            }else{
                                $respuesta['error'] = "Error al encontrar información del destino.";
                            }
                        }else{
                            $respuesta['error'] = $validacion['error'];
                        }
                        
                    }else{
                        $respuesta['error'] = "No se ha especificado token.";
                    }
                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            // EXPORTA CONTRATO DE CLIENTE
            $app->get("/contrato/{id_reserva}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_reserva = $request->getAttribute('id_reserva');
                $respuesta['estado'] = false;

                try{
                    if (isset($authorization[0])){
                        $hash = $authorization[0];
                        $autenticacion = new Authentication();

                        $validacion = $autenticacion->Valida_Sesion_Cliente($hash);

                        if ($validacion['estado']){
                            $id_cliente = $validacion['cliente']['id_cliente'];

                            $pdf = new PDF();
                        
                            $datos = array(
                                "documento" => "1719708677",
                                "nombres" => "CARLOS MINO",
                                // "personas" => 4,
                                // "dias" => 5,
                                // "fecha_caducidad" => $registro['fecha_caducidad'],
                                // "valor" => (float) $total,
                                // "forma_pago" => $tipo_pago,
                                // "primeros_digitos" => $primeros_digitos,
                                // "ultimos_digitos" => $ultimos_digitos,
                                // "institucion" => $institucion_financiera,
                                // "fecha_modificacion" => $registro['fecha_modificacion']
                            );

                            $respuesta['pdf'] = $pdf->Reserva($datos);

                            $respuesta['estado'] = true;
                         
                        }else{
                            $respuesta['error'] = $validacion['error'];
                        }
                        
                    }else{
                        $respuesta['error'] = "No se ha especificado token.";
                    }
                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });
        });

        // CALL CENTER
        $app->group('/call', function() use ($app) {
            
            // MANEJO DE CLIENTES

            $app->get("/paises", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $respuesta['estado'] = false;

                try{
                    if (isset($authorization[0])){
                        $autenticacion = new Authentication();
                        $session = $autenticacion->Valida_Sesion($authorization[0]);
    
                        if ($session['estado']){

                            $crm = new CRM_API();

                            $consulta = $crm->Lista_Paises();

                            if ($consulta['estado']){
                                $consulta = $consulta['consulta'];

                                $filtrado = [];

                                if (is_array($consulta)){
                                    if (count($consulta) > 0){
                                        foreach ($consulta as $linea) {
                                            
                                            $default = false;

                                            if ($linea['nombre'] == "Ecuador"){
                                                $default = true;
                                            }

                                            array_push($filtrado, array(
                                                "id" => (int) $linea['id'],
                                                "nombre" => strtoupper($linea['nombre']),
                                                "default" => $default,
                                            ));
                                        }
                                    }
                                }
                                $respuesta['consulta'] = $filtrado;
                                $respuesta['estado'] = true;
                            }else{
                                $respuesta['error'] = $consulta['error'];
                            }
                            
                        }else{
                            $respuesta['error'] = $session['error'];
                        }
                    }else{
                        $respuesta['error'] = "Su token de acceso no se encuentra.";
                    }
                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/provincias/{id_pais}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_pais = $request->getAttribute('id_pais');
                $respuesta['estado'] = false;

                try{
                    if (isset($authorization[0])){
                        $autenticacion = new Authentication();
                        $session = $autenticacion->Valida_Sesion($authorization[0]);
    
                        if ($session['estado']){

                            $crm = new CRM_API();

                            $consulta = $crm->Lista_Provincias($id_pais);

                            if ($consulta['estado']){
                                $consulta = $consulta['consulta'];

                                $filtrado = [];

                                if (is_array($consulta)){
                                    if (count($consulta) > 0){
                                        foreach ($consulta as $linea) {

                                            $default = false;

                                            if ($linea['nombre'] == "PICHINCHA"){
                                                $default = true;
                                            }

                                            array_push($filtrado, array(
                                                "id" => (int) $linea['id'],
                                                "nombre" => strtoupper($linea['nombre']),
                                                "default" => $default,
                                            ));
                                        }
                                    }
                                }
                                $respuesta['consulta'] = $filtrado;
                                $respuesta['estado'] = true;
                            }else{
                                $respuesta['error'] = $consulta['error'];
                            }
                            
                        }else{
                            $respuesta['error'] = $session['error'];
                        }
                    }else{
                        $respuesta['error'] = "Su token de acceso no se encuentra.";
                    }
                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/ciudades/{id_pais}/{id_provincia}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_pais = $request->getAttribute('id_pais');
                $id_provincia = $request->getAttribute('id_provincia');
                $respuesta['estado'] = false;

                try{
                    if (isset($authorization[0])){
                        $autenticacion = new Authentication();
                        $session = $autenticacion->Valida_Sesion($authorization[0]);
    
                        if ($session['estado']){

                            $crm = new CRM_API();

                            $consulta = $crm->Lista_Ciudades($id_pais, $id_provincia);

                            if ($consulta['estado']){
                                $consulta = $consulta['consulta'];

                                $filtrado = [];

                                if (is_array($consulta)){
                                    if (count($consulta) > 0){
                                        foreach ($consulta as $linea) {

                                            $default = false;

                                            if ($linea['nombre'] == "QUITO"){
                                                $default = true;
                                            }

                                            array_push($filtrado, array(
                                                "id" => (int) $linea['id'],
                                                "nombre" => strtoupper($linea['nombre']),
                                                "default" => $default,
                                            ));
                                        }
                                    }
                                }
                                $respuesta['consulta'] = $filtrado;
                                $respuesta['estado'] = true;
                            }else{
                                $respuesta['error'] = $consulta['error'];
                            }
                            
                        }else{
                            $respuesta['error'] = $session['error'];
                        }
                    }else{
                        $respuesta['error'] = "Su token de acceso no se encuentra.";
                    }
                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->post("/clientes", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                $respuesta['estado'] = false;

                // $respuesta['data'] = $data;

                try{
                    if (isset($authorization[0])){
                        $autenticacion = new Authentication();
                        $session = $autenticacion->Valida_Sesion($authorization[0]);

                        $mysql = new Database("vtgsa_ventas");
                        $funciones = new Functions();
    
                        if ($session['estado']){
                            $id_asesor = $session['usuario']['id_usuario'];
                            $id_usuario_crm = $session['usuario']['id_usuario_crm'];

                            if ($id_usuario_crm > 0){
                                $telefono1 = "";
                                if (isset($data['telefono1'])){
                                    $telefono1 = $data['telefono1'];
                                }

                                $telefono2 = "";
                                if (isset($data['telefono2'])){
                                    $telefono2 = $data['telefono2'];
                                }

                                $observaciones = "";
                                if (isset($data['observaciones'])){
                                    $observaciones = $data['observaciones'];
                                }

                                $tipo_documento = 1;
                                if (isset($data['tipo_documento'])){
                                    $tipo_documento = $data['tipo_documento'];
                                }

                                $pais = 0;
                                if (isset($data['pais'])){
                                    $pais = $data['pais'];
                                }

                                $provincia = 0;
                                if (isset($data['provincia'])){
                                    $provincia = $data['provincia'];
                                }

                                $ciudad = 0;
                                if (isset($data['ciudad'])){
                                    $ciudad = $data['ciudad'];
                                }

                                $continuar = false;

                                if ((isset($data['documento'])) && (!empty($data['documento']))){

                                    $documento = $data['documento'];
                                    $long = strlen($documento);

                                    switch ($tipo_documento) {
                                        case 1: // cedula
                                            if ($long == 10){
                                                $continuar = true; 
                                            }else{
                                                $respuesta['error'] = "La cédula de identidad debe contener 10 dígitos.";
                                            }
                                            break;
                                        case 2: // ruc
                                            if ($long == 13){
                                                $continuar = true; 
                                            }else{
                                                $respuesta['error'] = "El RUC debe contener 13 dígitos.";
                                            }
                                            break;
                                        case 3: // pasaporte
                                            $continuar = true; 
                                            break;
                                    }

                                    if ($continuar){
                                        $continuar = false;
                                        if ((isset($data['apellidos'])) && (!empty($data['apellidos']))){

                                            if ((isset($data['nombres'])) && (!empty($data['nombres']))){

                                                if ((isset($data['celular'])) && (!empty($data['celular']))){

                                                    $celular = $data['celular'];
                                                    $long = strlen($celular);

                                                    if ($long == 10){
                                                        $continuar = true;
                                                    }else{
                                                        $respuesta['error'] = "El celular debe contener 10 dígitos.";
                                                    }

                                                    if ($continuar){
                                                        $continuar = false;

                                                        if ((isset($data['email'])) && (!empty($data['email']))){

                                                            $email = $data['email'];

                                                            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                                                $continuar = true;
                                                            }else{
                                                                $respuesta['error'] = "La estructura del correo electrónico es inválida.";
                                                            }

                                                            if ($continuar){
                                                                $continuar = false;

                                                                if ((isset($data['direccion'])) && (!empty($data['direccion']))){

                                                                    if ((isset($data['sector'])) && (!empty($data['sector']))){

                                                                        if ((isset($data['referencia'])) && (!empty($data['referencia']))){

                                                                            $apellidos = $data['apellidos'];
                                                                            $nombres = $data['nombres'];
                                                                            
                                                                            $direccion = $data['direccion'];
                                                                            $sector = $data['sector'];
                                                                            $referencia = $data['referencia'];
                                                                            
                                                                            
                                                                            $continuar = true;
                                                                        }else{
                                                                            $respuesta['error'] = "Debe ingresar una referencia de la dirección.";
                                                                        }

                                                                    }else{
                                                                        $respuesta['error'] = "Debe ingresar el sector.";
                                                                    }

                                                                }else{
                                                                    $respuesta['error'] = "Debe ingresar la dirección exacta.";
                                                                }
                                                            }

                                                        }else{
                                                            $respuesta['error'] = "Debe ingresar el correo electrónico del cliente.";
                                                        }
                                                    }

                                                    

                                                }else{
                                                    $respuesta['error'] = "Debe ingresar el número de celular del cliente.";
                                                }

                                            }else{
                                                $respuesta['error'] = "Debe ingresar los nombres del cliente.";
                                            }

                                        }else{
                                            $respuesta['error'] = "Debe ingresar los apellidos del cliente.";
                                        }
                                    }

                                    // VALIDADOS LOS CAMPOS SE PROCEDE A GUARDAR AL CLIENTE
                                    if ($continuar){
                                        $datos = array(
                                            "tipo_documento" => $tipo_documento,
                                            "documento" => $documento,
                                            "apellidos" => strtoupper($apellidos),
                                            "nombres" => strtoupper($nombres),
                                            "celular" => $celular,
                                            "telefono1" => $telefono1,
                                            "telefono2" => $telefono2,
                                            "email" => strtolower($email),
                                            "pais" => $pais,
                                            "provincia" => $provincia,
                                            "ciudad" => $ciudad,
                                            "direccion" => strtoupper($direccion),
                                            "sector" => strtoupper($sector),
                                            "referencia" => strtoupper($referencia),
                                            "observaciones" => $observaciones,
                                            "usuario" => $id_asesor,
                                        );

                                        $crm = new CRM_API();
                                        $envio = $crm->Crear_Cliente($datos);

                                        if ($envio['estado']){
                                            $respuesta['id_cliente'] = $envio['id_cliente'];
                                            $respuesta['estado'] = true;
                                        }else{
                                            $respuesta['error'] = $envio['error'];
                                        }
                                    }

                                }else{
                                    $respuesta['error'] = "Debe ingresar el documento de identidad.";
                                }
                            }else{
                                $respuesta['error'] = "Su usuario no se encuentra vinculado en el CRM.";
                            }
                        }else{
                            $respuesta['error'] = $session['error'];
                        }
                    }else{
                        $respuesta['error'] = "Su token de acceso no se encuentra.";
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/clientes", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;

                try{
                    if (isset($authorization[0])){
                        $autenticacion = new Authentication();
                        $session = $autenticacion->Valida_Sesion($authorization[0]);

                        $mysql = new Database("vtgsa_ventas");
                        $crm = new CRM_API();
    
                        if ($session['estado']){
                            $id_asesor = $session['usuario']['id_usuario'];
                            $id_usuario_crm = $session['usuario']['id_usuario_crm'];

                            $buscador = "";
                            if ((isset($params['buscador'])) && (!empty($params['buscador']))){
                                $buscador = $params['buscador'];
                            }

                            $consulta = $crm->Lista_Clientes($id_usuario_crm, $buscador);
                            if ($consulta['estado']){
                                $consulta = $consulta['consulta'];

                                $filtrado = [];
                                if (is_array($consulta)){
                                    if (count($consulta) > 0){
                                        foreach ($consulta as $linea) {
                                            $color = "secondary";

                                            switch ($linea['tid_nombre']) {
                                                case 'NO VOLVER A CONTACTAR':
                                                    $color = 'danger';
                                                    break;
                                                case 'ACTIVO':
                                                    $color = 'info';
                                                    break;
                                                case 'NO CONTACTADO':
                                                    $color = 'secondary';
                                                    break;
                                                case 'MAIL ENVIADO':
                                                    $color = 'primary';
                                                    break;
                                                case 'FACTURADO':
                                                    $color = 'success';
                                                    break;
                                            }

                                            array_push($filtrado, array(
                                                "id" => (int) $linea['cli_codigo'],
                                                "documento" => $linea['cli_cedula'],
                                                "apellidos" => $linea['cli_apellido'],
                                                "nombres" => $linea['cli_nombre'],
                                                "fecha_ingreso" => $linea['cli_fecha_ingreso'],
                                                "ciudad" => $linea['nombre'],
                                                "estado" => array(
                                                    "color" => $color,
                                                    "descripcion" => $linea['tid_nombre']
                                                )
                                            ));
                                        }
                                    }
                                }
    
                                $respuesta['original'] = $consulta;
                                $respuesta['consulta'] = $filtrado;
                                $respuesta['estado'] = true;
                            }else{
                                $respuesta['error'] = $consulta['error'];
                            }
                            
                        }else{
                            $respuesta['error'] = $session['error'];
                        }
                    }else{
                        $respuesta['error'] = "Su token de acceso no se encuentra.";
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            // MANEJO DE PRODUCTOS

            $app->get("/productos", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;

                try{
                    if (isset($authorization[0])){
                        $autenticacion = new Authentication();
                        $session = $autenticacion->Valida_Sesion($authorization[0]);

                        $mysql = new Database("vtgsa_ventas");
                        $crm = new CRM_API();
    
                        if ($session['estado']){
                            $id_asesor = $session['usuario']['id_usuario'];
                            $id_usuario_crm = $session['usuario']['id_usuario_crm'];
                            
                            $consulta = $crm->Lista_Productos();
                            if ($consulta['estado']){
                                $consulta = $consulta['consulta'];

                                $filtrado = [];
                                if (is_array($consulta)){
                                    if (count($consulta) > 0){
                                        foreach ($consulta as $linea) {
                                            array_push($filtrado, array(
                                                "id" => (int) $linea['tid_codigo'],
                                                "nombre" => $linea['tid_nombre']
                                            ));
                                        }
                                    }
                                }
    
                                $respuesta['original'] = $consulta;
                                $respuesta['consulta'] = $filtrado;
                                $respuesta['estado'] = true;
                            }else{
                                $respuesta['error'] = $consulta['error'];
                            }
                            
                        }else{
                            $respuesta['error'] = $session['error'];
                        }
                    }else{
                        $respuesta['error'] = "Su token de acceso no se encuentra.";
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });


            // MANEJO DE CONTACTOS

            $app->get("/contactos", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;

                try{
                    if (isset($authorization[0])){
                        $autenticacion = new Authentication();
                        $session = $autenticacion->Valida_Sesion($authorization[0]);

                        $mysql = new Database("vtgsa_ventas");
                        $funciones = new Functions();
    
                        if ($session['estado']){
                            $id_asesor = $session['usuario']['id_usuario'];

                            $id_banco = 2;
                            if ((isset($params['id_banco'])) && (!empty($params['id_banco']))){
                                $id_banco = $params['id_banco'];
                            }

                            $estado = 0;
                            if ((isset($params['estado'])) && (!empty($params['estado']))){
                                $estado = $params['estado'];
                            }

                            $consulta = $mysql->Consulta("SELECT * FROM notas_registros WHERE (asignado=".$id_asesor.") AND (banco=".$id_banco.") AND (estado=".$estado.")");

                            $filtrado = [];
                            if (is_array($consulta)){
                                if (count($consulta) > 0){
                                    foreach ($consulta as $linea) {
                                        array_push($filtrado, array(
                                            "id_lista" => (int) $linea['id_lista'],
                                            "documento" => $linea['documento'],
                                            "nombres" => $linea['nombres'],
                                            "ciudad" => $linea['ciudad'],
                                            "direccion" => $linea['direccion'],
                                            "telefono" => $linea['telefono'],
                                            "fecha_alta" => $linea['fecha_alta'],
                                            "fecha_modificacion" => $linea['fecha_modificacion'],
                                            "fecha_asignacion" => $linea['fecha_asignacion'],
                                            "observaciones" => $linea['observaciones'],
                                            "intentos" => (int) $linea['llamado'],
                                            "estado" => $funciones->Obtener_Estado($linea['estado'])
                                        ));
                                    }
                                }
                            }
                            
                            $respuesta['consulta'] = $filtrado;
                            $respuesta['original'] = $consulta;
                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = $session['error'];
                        }
                    }else{
                        $respuesta['error'] = "Su token de acceso no se encuentra.";
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            // BASE DE CLIENTES PARA LOS ASESORES

            $app->get("/informacion", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $respuesta['estado'] = false;

                try{
                    if (isset($authorization[0])){
                        $autenticacion = new Authentication();
                        $session = $autenticacion->Valida_Sesion($authorization[0]);

                        $mysql = new Database("vtgsa_ventas");
    
                        if ($session['estado']){

                            $bancos = $mysql->Consulta("SELECT * FROM notas_registros_bancos WHERE (estado=0)");
                            $estados = $mysql->Consulta("SELECT * FROM notas_registros_estados WHERE (visual=0) AND (estado=0)");

                            $filtrado_bancos = [];
                            if (is_array($bancos)){
                                if (count($bancos) > 0){
                                    foreach ($bancos as $linea) {
                                        array_push($filtrado_bancos, array(
                                            "id" => (int) $linea['id_banco'],
                                            "descripcion" => $linea['banco']
                                        ));
                                    }
                                }
                            }

                            $filtrado_estados = [];
                            if (is_array($estados)){
                                if (count($estados) > 0){
                                    foreach ($estados as $linea) {

                                        $necesita_horario = false;

                                        if ($linea['necesita_horario'] == 1){
                                            $necesita_horario = true;
                                        }

                                        array_push($filtrado_estados, array(
                                            "id" => (int) $linea['id_estados'],
                                            "descripcion" => $linea['descripcion'],
                                            "necesita_horario" => $necesita_horario
                                        ));
                                    }
                                }
                            }

                            // Busca los paquetes para observaciones
                            $busca_paquetes = $mysql->Consulta("SELECT * FROM notas_registros_paquetes WHERE estado=0 ORDER BY id_paquete ASC ");
                            $filtrado_paquetes = [];
                            if (is_array($busca_paquetes)){
                                if (count($busca_paquetes) > 0){
                                    foreach ($busca_paquetes as $linea) {
                                        array_push($filtrado_paquetes, array(
                                            "id_paquete" => (int) $linea['id_paquete'],
                                            "paquete" => $linea['nombre']
                                        ));
                                    }
                                }
                            }

                            $respuesta['bancos'] = $filtrado_bancos;
                            $respuesta['estados'] = $filtrado_estados;
                            $respuesta['paquetes'] = $filtrado_paquetes;
                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = $session['error'];
                        }
                    }else{
                        $respuesta['error'] = "Su token de acceso no se encuentra.";
                    }
                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/resumen/{banco}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $banco = $request->getAttribute('banco');
                $respuesta['estado'] = false;

                $resultados = [];

                try{
                    if (isset($authorization[0])){
                        $autenticacion = new Authentication();
                        $session = $autenticacion->Valida_Sesion($authorization[0]);

                        $mysql = new Database("vtgsa_ventas");
                        $funciones = new Functions();
    
                        if ($session['estado']){
                            $id_asesor = $session['usuario']['id_usuario'];
                            $cupo_call = 0;
                                    
                            // Consulta cupo dependiendo la base elegida
                            $consulta_cupo = $mysql->Consulta_Unico("SELECT * FROM notas_registros_cupos WHERE (id_banco=".$banco.") AND (id_usuario=".$id_asesor.")");
                            if (isset($consulta_cupo['id_cupo'])){
                                $cupo_call = $consulta_cupo['cupo'];
                            }

                            $listado = $mysql->Consulta_Unico("SELECT COUNT(id_lista) AS total FROM notas_registros WHERE (asignado=".$id_asesor.") AND (banco=".$banco.") AND (estado>0)");
                            $total_marcados = 0;
                            if (isset($listado['total'])){
                                $total_marcados = $listado['total'];
                            }
        
                            $diferencia = $cupo_call - $total_marcados;
                            $color = "light";
        
                            if (($diferencia <= 10) && ($diferencia > 0)){
                                $color = "warning";
                            }else if ($diferencia <=0){
                                $color = "danger";
                                $diferencia = 0;
                            }
        
                            $respuesta['faltante']['mensaje'] = "Te faltan ".$diferencia." registros";
                            $respuesta['faltante']['color'] = $color;

                            // Busca las listas de clientes y sus estados de acuerdo al asesor
                            $registros = $mysql->Consulta("SELECT * FROM notas_registros WHERE (banco=".$banco.") AND (asignado=".$id_asesor.")");

                            $ventas = [];
                            $interesados = [];
                            $mas_tarde = [];
                            $apagado = [];
                            $no_contesta = [];
                            if (is_array($registros)){
                                if (count($registros) > 0){
                                    foreach ($registros as $linea) {
                                        $estado = $linea['estado'];

                                        $hora_actual = date("H:i:00");

                                        $estado_prox_llamada = false;
                                        if ($linea['hora_prox_llamada'] <= $hora_actual){
                                            $estado_prox_llamada = true;
                                        }

                                        // ultimo registro de llamada                                    
                                        $ultimas_llamadas = $mysql->Consulta("SELECT * FROM notas_registros_llamadas WHERE id_lista=".$linea['id_lista']." ORDER BY id_log_llamada DESC LIMIT 2");
                                        $diff = 0;
                                        $ultima_llamada_registro = "";
                                        if (is_array($ultimas_llamadas)){
                                            if (count($ultimas_llamadas) == 2){
                                                // sacar diferencia de tiempo
                                                $diff = strtotime($ultimas_llamadas[0]['fecha_hora']) - strtotime($ultimas_llamadas[1]['fecha_hora']);
                                                $ultima_llamada_registro = $ultimas_llamadas[0]['fecha_hora'];
                                            }
                                        }                           

                                        $datos = array(
                                            "id_lista" => (int) $linea['id_lista'],
                                            // "documento" => $linea['documento'],
                                            "nombres" => $linea['nombres'],
                                            // "telefono" => $linea['telefono'],
                                            // "ciudad" => $linea['ciudad'],
                                            // "correo" => $linea['correo'],
                                            "observaciones" => $linea['observaciones'],
                                            "llamado" => (int) $linea['llamado'],
                                            "fecha_prox_llamada" => $linea['fecha_prox_llamada'],
                                            "hora_prox_llamada" => $linea['hora_prox_llamada'],
                                            "llamar_ahora" => $estado_prox_llamada,
                                            "llamadas" => array(
                                                "ultima" => $ultima_llamada_registro,
                                                "duracion" => date("i:s", $diff)
                                            )
                                        );

                                        switch ($estado) {
                                            case 1:
                                                array_push($mas_tarde, $datos);
                                                break;
                                            case 2:
                                                array_push($apagado, $datos);
                                                break;
                                            case 3:
                                                array_push($no_contesta, $datos);
                                                break;
                                            case 4:
                                                array_push($interesados, $datos);
                                                break;
                                            case 7:
                                                array_push($ventas, $datos);
                                                break;
                                        }
                                    }
                                }
                            }

                            $respuesta['registros']['interesados'] = $interesados;
                            $respuesta['registros']['mas_tarde'] = $mas_tarde;
                            $respuesta['registros']['apagado'] = $apagado;
                            $respuesta['registros']['no_contesta'] = $no_contesta;
                            $respuesta['registros']['ventas'] = $ventas;
                            
                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = $session['error'];
                        }
                    }else{
                        $respuesta['error'] = "Su token de acceso no se encuentra.";
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/basecliente/{banco}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $banco = $request->getAttribute('banco');
                $respuesta['estado'] = false;

                $resultados = [];

                try{
                    if (isset($authorization[0])){
                        $autenticacion = new Authentication();
                        $session = $autenticacion->Valida_Sesion($authorization[0]);

                        $mysql = new Database("vtgsa_ventas");
                        $funciones = new Functions();
    
                        if ($session['estado']){
                            $id_asesor = $session['usuario']['id_usuario'];
                            $cupo_call = 0;
                                    
                            // Consulta cupo dependiendo la base elegida
                            $consulta_cupo = $mysql->Consulta_Unico("SELECT * FROM notas_registros_cupos WHERE (id_banco=".$banco.") AND (id_usuario=".$id_asesor.")");
                            if (isset($consulta_cupo['id_cupo'])){
                                $cupo_call = $consulta_cupo['cupo'];
                            }

                            $clientes_registrados_con_intentos = $mysql->Consulta_Unico("SELECT COUNT(id_lista) AS total FROM notas_registros WHERE ((asignado=".$id_asesor.") AND (banco=".$banco."))");
                            
                            $seguir_asignando = false;
                            if ($cupo_call == 0){
                                $seguir_asignando = false; // es ilimitado (pero no se toma en cuenta, sino con el valor)
                            }else{
                                if (isset($clientes_registrados_con_intentos['total'])){
                                    if (intval($clientes_registrados_con_intentos['total']) <= intval($cupo_call)){
                                        $seguir_asignando = true;
                                    }
                                }
                            }

                            // PRIMERO BUSCA CONTACTOS A LOS QUE DE LES DEBE LLAMAR SEGUN EL HORARIO (LLAMAR MAS TARDE)
                            $fecha_hoy = date("Y-m-d");
                            $hora_hoy = date("H:i:s");
                            $llamar_ahora = $mysql->Consulta_Unico("SELECT * FROM notas_registros WHERE (banco=".$banco.") AND (asignado=".$id_asesor.") AND (fecha_prox_llamada='".$fecha_hoy."') AND (hora_prox_llamada<='".$hora_hoy."') AND (estado=1) ORDER BY hora_prox_llamada ASC, nombres ASC");

                            if (isset($llamar_ahora['id_lista'])){
                                $resultados = array(
                                    "id_lista" => (int) $llamar_ahora['id_lista'],
                                    "documento" => trim($llamar_ahora['documento']),
                                    "nombres" => trim($llamar_ahora['nombres']),
                                    "telefono" => trim($llamar_ahora['telefono']),
                                    "telefono_marcar" => trim($llamar_ahora['telefono']),
                                    "ciudad" => trim($llamar_ahora['ciudad']),
                                    "direccion" => trim($llamar_ahora['direccion']),
                                    "correo" => trim($llamar_ahora['correo']),
                                    "observaciones" => trim($llamar_ahora['observaciones']),
                                    "estado" => $funciones->Obtener_Estado($llamar_ahora['estado']),
                                    "fecha_hora_llamada" => $llamar_ahora['fecha_prox_llamada']." ".$llamar_ahora['hora_prox_llamada'],
                                    "tipo" => "ahora"
                                );
                            }

                            // BUSCA CONTACTOS QUE SE QUEDARON EN EL AIRE Y ESTAN ASIGNADOS, ES DECIR, EN ESTADO 0
                            if (count($resultados) == 0){
                                $estado_cero = $mysql->Consulta_Unico("SELECT * FROM notas_registros WHERE (banco=".$banco.") AND (asignado=".$id_asesor.") AND (estado=0) ORDER BY orden ASC, nombres ASC");

                                if (isset($estado_cero['id_lista'])){
                                    $resultados = array(
                                        "id_lista" => (int) $estado_cero['id_lista'],
                                        "documento" => trim($estado_cero['documento']),
                                        "nombres" => trim($estado_cero['nombres']),
                                        "telefono" => trim($estado_cero['telefono']),
                                        "telefono_marcar" => trim($estado_cero['telefono']),
                                        "ciudad" => trim($estado_cero['ciudad']),
                                        "direccion" => trim($estado_cero['direccion']),
                                        "correo" => trim($estado_cero['correo']),
                                        "observaciones" => trim($estado_cero['observaciones']),
                                        "estado" => $funciones->Obtener_Estado($estado_cero['estado']),
                                        "tipo" => "pendiente"
                                    );
                                }

                            }
                            
                            if (count($resultados) == 0){ // SI NO HAY POR LLAMAR Y NO TIENE CONTACTOS EN EL AIRE
                                if ($seguir_asignando){
                                    $base_clientes = $mysql->Consulta("SELECT * FROM notas_registros WHERE (banco=".$banco.") AND (asignado=0) AND (estado=0) ORDER BY urgente DESC, orden ASC, nombres ASC");

                                    if (is_array($base_clientes)){
                                        $total_registros = count($base_clientes);
                                        if ($total_registros > 0){
                                            if ($total_registros == 1){
                                                $aleatorio = 0;
                                                $resultados = array(
                                                    "id_lista" => (int) $base_clientes[$aleatorio]['id_lista'],
                                                    "documento" => trim($base_clientes[$aleatorio]['documento']),
                                                    "nombres" => trim($base_clientes[$aleatorio]['nombres']),
                                                    "telefono" => trim($base_clientes[$aleatorio]['telefono']),
                                                    "telefono_marcar" => trim($base_clientes[$aleatorio]['telefono']),
                                                    "ciudad" => trim($base_clientes[$aleatorio]['ciudad']),
                                                    "direccion" => trim($base_clientes[$aleatorio]['direccion']),
                                                    "correo" => trim($base_clientes[$aleatorio]['correo']),
                                                    "observaciones" => trim($base_clientes[$aleatorio]['observaciones']),
                                                    "estado" => $funciones->Obtener_Estado($base_clientes[$aleatorio]['estado']),
                                                    "tipo" => "nuevo"
                                                );
                                            }else{
                                                $aleatorio = mt_rand(0, ($total_registros - 1)); // selecciona aleatoriamente un cliente de la lista y asigna al asesor
                                                $respuesta['aleatorio'] = $aleatorio;
                                                $resultados = array(
                                                    "id_lista" => (int) $base_clientes[$aleatorio]['id_lista'],
                                                    "documento" => trim($base_clientes[$aleatorio]['documento']),
                                                    "nombres" => trim($base_clientes[$aleatorio]['nombres']),
                                                    "telefono" => trim($base_clientes[$aleatorio]['telefono']),
                                                    "telefono_marcar" => trim($base_clientes[$aleatorio]['telefono']),
                                                    "ciudad" => trim($base_clientes[$aleatorio]['ciudad']),
                                                    "direccion" => trim($base_clientes[$aleatorio]['direccion']),
                                                    "correo" => trim($base_clientes[$aleatorio]['correo']),
                                                    "observaciones" => trim($base_clientes[$aleatorio]['observaciones']),
                                                    "estado" => $funciones->Obtener_Estado($base_clientes[$aleatorio]['estado']),
                                                    "tipo" => "nuevo"
                                                );
                                            }
                                            $fecha_asignacion = date("Y-m-d H:i:s");
                                            $asigna = $mysql->Modificar("UPDATE notas_registros SET asignado=?, fecha_asignacion=? WHERE id_lista=?", array($id_asesor, $fecha_asignacion, $resultados['id_lista']));
                                        }else{
                                            $respuesta['error'] = "No existen mas registros en la base.";
                                        }
                                    }
                                }else{
                                    // YA NO TIENE CUPO DE CLIENTES PARA ASIGNAR
                                    // ENTONCES BUSCA DE SU BASE DE CLIENTES ASIGNADOS QUE ESTEN EN APAGADOS O NO CONTESTA
                                    if (count($resultados) == 0){
                                        $estado_remarcados = $mysql->Consulta_Unico("SELECT * FROM notas_registros WHERE (banco=".$banco.") AND (asignado=".$id_asesor.") AND ((estado>1) AND (estado<4)) ORDER BY fecha_modificacion DESC, id_lista DESC");

                                        if (isset($estado_remarcados['id_lista'])){
                                            $resultados = array(
                                                "id_lista" => (int) $estado_remarcados['id_lista'],
                                                "documento" => trim($estado_remarcados['documento']),
                                                "nombres" => trim($estado_remarcados['nombres']),
                                                "telefono" => trim($estado_remarcados['telefono']),
                                                "telefono_marcar" => trim($estado_remarcados['telefono']),
                                                "ciudad" => trim($estado_remarcados['ciudad']),
                                                "direccion" => trim($estado_remarcados['direccion']),
                                                "correo" => trim($estado_remarcados['correo']),
                                                "observaciones" => trim($estado_remarcados['observaciones']),
                                                "estado" => $funciones->Obtener_Estado($estado_remarcados['estado']),
                                                "tipo" => "remarcado"
                                            );
                                        }
                                    }
                                }   
                            }

                            if (count($resultados) == 0){
                                $resultados = array(
                                    "id_lista" => 0,
                                    "documento" => "S/N",
                                    "nombres" => "S/N",
                                    "telefono" => "S/N",
                                    "telefono_marcar" => "S/N",
                                    "ciudad" => "S/N",
                                    "direccion" => "S/N",
                                    "correo" => "S/N",
                                    "observaciones" => "S/N",
                                    "estado" => $funciones->Obtener_Estado(0),
                                    "tipo" => "sin_dato"
                                );
                            }

                            $respuesta['consulta'] = $resultados;
                            
                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = $session['error'];
                        }
                    }else{
                        $respuesta['error'] = "Su token de acceso no se encuentra.";
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->put("/clientes/{id_lista}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $id_lista = $request->getAttribute('id_lista');
                $data = $request->getParsedBody();
                $respuesta['estado'] = false;

                $respuesta['data'] = $data;

                try{
                    if (isset($authorization[0])){
                        $autenticacion = new Authentication();
                        $session = $autenticacion->Valida_Sesion($authorization[0]);

                        $mysql = new Database("vtgsa_ventas");
                        $funciones = new Functions();
    
                        if ($session['estado']){
                            $id_asesor = $session['usuario']['id_usuario'];

                            $verifica = $mysql->Consulta_Unico("SELECT * FROM notas_registros WHERE id_lista=".$id_lista);

                            if (isset($verifica['id_lista'])){
                                $intentos_llamado = $verifica['llamado'];

                                $estado = $data['estado'];
                                $observaciones = $data['observaciones'];
                                $hora = $data['hora'];                        
                                $fecha = $data['fecha'];

                                $documento = strtoupper($data['documento']);
                                $nombres = strtoupper($data['nombres']);
                                $ciudad = strtoupper($data['ciudad']);
                                $correo = strtolower($data['correo']);
                                $direccion = strtoupper($data['direccion']);
                                $fecha_modificacion = date("Y-m-d H:i:s");

                                $validado = false;
                                // VALIDACION DE CORREO ELECTRONICO
                                $correo_anterior = $verifica['correo'];
                                if (empty($correo_anterior)){
                                    if (empty($correo)){
                                        $validado = true;
                                    }else{
                                        if (filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                                            $validado = true;
                                        } else {
                                            $respuesta['error'] = "El correo electronico es invalido.";
                                        }
                                    }
                                }else{
                                    if (empty($correo)){
                                        $validado = true;
                                        $correo = $correo_anterior;
                                    }else{
                                        if (filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                                            $validado = true;
                                        } else {
                                            $respuesta['error'] = "El correo electronico es invalido.";
                                        }
                                    }
                                }

                                if ($validado){
                                    $actualizar = $mysql->Modificar("UPDATE notas_registros SET documento=?, nombres=?, ciudad=?, direccion=?, correo=?, fecha_modificacion=? WHERE id_lista=?", array($documento, $nombres, $ciudad, $direccion, $correo, $fecha_modificacion, $id_lista));
                                }

                                
                                $fecha_actual = date("Y-m-d");
                                $valida_fecha = false;
                                if ($estado == 1){ // solo si es llamar mas tarde

                                    // VALIDA QUE LA FECHA SEA MAYOR O IGUAL A LA ACTUAL
                                    // VALIDAR HORA DE LLAMADA ENTRE 09 DE 19
                                    if ($fecha >= $fecha_actual){
                                        $hora_inicial = strtotime("09:00:00");
                                        $hora_final = strtotime("21:00:00");
                                        $hora_apuntada = strtotime($hora);
                                        $hora_actual = strtotime(date("H:i:s"));

                                        if ($fecha > $fecha_actual){
                                            if ( ($hora_apuntada >= $hora_inicial) && ($hora_apuntada <= $hora_final) ){
                                                $valida_fecha = true;
                                            }else{
                                                $respuesta['error'] = "La hora de llamada debe ser entre las 09:00 y 22:00.";
                                            }
                                        }else{
                                            if ( (($hora_apuntada >= $hora_inicial) && ($hora_apuntada <= $hora_final)) && ($hora_apuntada > $hora_actual) ){
                                                $valida_fecha = true;
                                            }else{
                                                $respuesta['error'] = "La hora de llamada debe ser entre las 09:00 y 22:00.";
                                            }
                                        }
                                        
                                    }else{
                                        $respuesta['error'] = "La fecha para la llamada debe ser igual o mayor a la actual.";
                                    }
                                }else{
                                    $valida_fecha = true;
                                }

                                if ($valida_fecha){

                                    $valida_llamada_tiempo_minimo = false;

                                    // VALIDAR QUE EL TIEMPO ENTRE LAS DOS ULTIMAS LLAMADAS SEAN 30 SEGUNDOS
                                    $ultimas_llamadas = $mysql->Consulta("SELECT * FROM notas_registros_llamadas WHERE id_lista=".$id_lista." ORDER BY id_log_llamada DESC LIMIT 2");
                                    // $respuesta['asdfad'] = $ultimas_llamadas;
                                    if (is_array($ultimas_llamadas)){
                                        if (count($ultimas_llamadas) == 2){
                                            // sacar diferencia de tiempo
                                            $diff = strtotime($ultimas_llamadas[0]['fecha_hora']) - strtotime($ultimas_llamadas[1]['fecha_hora']);
                                            $respuesta['segundos'] = $diff;
                                            // if ($diff >= 1){ // si es mayor a 15 segundos entonces si contesto
                                                $valida_llamada_tiempo_minimo = true;
                                            // }
                                        }else{
                                            $valida_llamada_tiempo_minimo = true; // aqui validar q siempre haya las dos llamadas de inicio y fin y sino mandar notificacion para reinicio en pagina
                                        }
                                    }
                                                                
                                    $siguiente_cliente = false;
                                    if ($valida_llamada_tiempo_minimo){
                                        if (($estado == 2) || ($estado == 3)){
                                            if ($intentos_llamado >= 2){
                                                $siguiente_cliente = true;
                                            }else{
                                                $respuesta['error'] = "Debe tener por lo menos 2 intentos de llamada al cliente.";
                                            }
                                        }else{
                                            $siguiente_cliente = true;
                                        }
                                        
                                    }else{
                                        $respuesta['error'] = "No se han registrado llamadas previas, o el tiempo de la ultima llamada es muy corta. Debe intentar nuevamente.";
                                    }

                                    if ($siguiente_cliente){
                                        $modificar = $mysql->Modificar("UPDATE notas_registros SET orden=?, observaciones=?, hora_prox_llamada=?, fecha_prox_llamada=?, estado=? WHERE id_lista=?", array(1, $observaciones, $hora, $fecha, $estado, $id_lista));

                                        $respuesta['estado'] = true;
                                    }                            
                                }
                                
                            }else{
                                $respuesta['error'] = "No se encuentra informacion del cliente.";
                            }
                        }else{
                            $respuesta['error'] = $session['error'];
                        }
                    }else{
                        $respuesta['error'] = "Su token de acceso no se encuentra.";
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });
        });
         
        // GRUPO PARA DIFERENTES USOS

        $app->group('/funciones', function() use ($app) {

            $app->get("/sendinblue", function(Request $request, Response $response){
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;

                try{              
                    $sendinblue = new sendinblue();

                     $data = array(
                        "to" => [array(
                            "email" => $params['email'],
                            "name" => $params['nombre']
                        )],
                        // "cc" => [],
                        // "bcc" => [],
                        "templateId" => 1,
                        "params" => array(
                            "fecha" => date("Y-m-d"),
                            "ciudad" => "QUITO"
                        ),
                        // "replyTo" => array(
                        //     "email" => "info@mvevip.com",
                        //     "name" => "Marketing VIP"
                        // ),
                        // "attachment" => [
                        //     array(
                        //         "url" => "https://ventas.mvevip.com/php/contratos/56579-20230301112043.pdf",
                        //         "name" => "Contrato.pdf"
                        //     ),
                        //     array(
                        //         "url" => "https://ventas.mvevip.com/php/voucher/voucher_0_20230301131209.jpg",
                        //         "name" => "Voucher.jpg"
                        //     )
                        // ]
                    );

                    $respuesta['correo'] = $sendinblue->envioMail($data);
                    
                    $respuesta['estado'] = true;
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });
            
            $app->get("/testing", function(Request $request, Response $response){
                $respuesta['estado'] = false;
                try{                    
                    $respuesta['estado'] = true;
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/getusuario", function(Request $request, Response $response){
                $respuesta['estado'] = false;
                $params = $request->getQueryParams();

                try{
                    if (isset($params['documento'])){
                        $crm = new CRM_API();

                        $respuesta['crm'] = $crm->Obtener_Usuario($params['documento']);
                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No existe documento a validar";
                    }
                    
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/masivo_clientes", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');

                $respuesta['estado'] = false;
            
                try{
                    $mysql = new Database("whatsapp_chats");
                    $whatsapp = new Whatsapp();

                    $buscador = '';
                    if (isset($params['buscador'])){
                        $buscador = $params['buscador'];
                    }

                    $consulta = $mysql->Consulta("SELECT * FROM contactos_masivo WHERE ((nombres_apellidos LIKE '%".$buscador."%') OR (celular LIKE '%".$buscador."%'))");

                    $respuesta['contactos'] = $consulta;

                    $respuesta['estado'] = true;

                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/masivo", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $respuesta['estado'] = false;
            
                try{
                    $mysql = new Database("whatsapp_chats");
                    $whatsapp = new Whatsapp();

                    $hora_actual = date("H:i:s");

                    $respuesta['hora'] = $hora_actual;

                    if ($hora_actual >= "21:40:00"){
                        $contacto = $mysql->Consulta_Unico("SELECT * FROM contactos_masivo WHERE (estado=0) ORDER BY id_contacto ASC LIMIT 1");

                        if (isset($contacto['id_contacto'])){
                            $id_contacto = $contacto['id_contacto'];
                            $celular = $contacto['celular'];
                            $mensaje = "Estimado, ".$contacto['nombres_apellidos'].". Asi se enviaria los mensajes desde el codigo de forma masiva. Cualquier informacion ingrese a https://www.mvevip.com";

                            // $link = "https://aloha.vtgsa.com/paquete1.jpg";
                            $archivo = file_get_contents(__DIR__."/../../public/tmp/envio.jpg");
                            $link = "data:image/jpeg;base64,".base64_encode($archivo);
                            $filename = "miarchivo.jpg";

                            // $respuesta['envio'] = $whatsapp->envio($celular, $mensaje);
                            $envio = $whatsapp->sendfile2($celular, $link, $filename, $mensaje);

                            if ($envio->sent){
                                $respuesta['id'] = $envio->id;
                                $respuesta['queueNumber'] = $envio->queueNumber;

                                $actualiza = $mysql->Modificar("UPDATE contactos_masivo SET estado=1 WHERE id_contacto=?", array($id_contacto));

                                $respuesta['estado'] = true;
                            }else{
                                $respuesta['error'] = "No se envio el mensaje.";
                            }
                            
                        }else{
                            $respuesta['error'] = "Ya no existen contactos por enviar.";
                        }
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/estado_masivo", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
            
                try{
                    $mysql = new Database("whatsapp_chats");

                    $limite = "";
                    if (isset($params['limite'])){
                        if ($params['limite'] > 0){
                            $limite = "LIMIT ".$params['limite'];
                        }                        
                    }

                    $filtro = "";
                    if (isset($params['filtro'])){
                        switch ($params['filtro']) {
                            case 0: // todos
                                $filtro = "";
                                break;
                            case 1: // enviados
                                $filtro = "enviados";
                                break;
                            case 2: // entregados
                                $filtro = "entregados";
                                break;
                            case 3: // leidos
                                $filtro = "leidos";
                                break;
                            case 4: // respondidos
                                $filtro = "respondidos";
                                break;
                        }
                    }

                    $contactos = $mysql->Consulta("SELECT * FROM contactos_masivo ORDER BY id_contacto ASC");

                    $consulta = [];
                    if (is_array($contactos)){
                        if (count($contactos) > 0){
                            foreach ($contactos as $linea) {
                                $idMessage = $linea['idMessage'];
                                $queueNumber = $linea['queueNumber'];
                                $chatId = $linea['chatId'];

                                $estado_envio = $mysql->Consulta("SELECT * FROM acusaciones WHERE (id='".$idMessage."') AND (queueNumber='".$queueNumber."')");

                                $estado_enviado = false;
                                $estado_entregado = false;
                                $estado_leido = false;
                                $estado_respondido = "";

                                if (is_array($estado_envio)){
                                    if (count($estado_envio) > 0){
                                        foreach ($estado_envio as $linea_estado) {
                                            
                                            switch ($linea_estado['status']) {
                                                case 'sent':
                                                    $estado_enviado = true;
                                                    break;
                                                case 'delivered':
                                                    $estado_entregado = true;
                                                    break;
                                                case 'viewed':
                                                    $estado_leido = true;
                                                    break;                                                
                                            }

                                        }
                                    }
                                }

                                $estado_respondido_busqueda = $mysql->Consulta_Unico("SELECT * FROM acusaciones WHERE (chatId='".$chatId."') AND (status='chat') ORDER BY id_chat DESC LIMIT 1");

                                if (isset($estado_respondido_busqueda['id_chat'])){
                                    $estado_respondido = $estado_respondido_busqueda['body'];
                                }


                                $incluye = false;
                                if ($filtro != ''){
                                    switch ($filtro) {
                                        case 'enviados':
                                            if ($estado_enviado){
                                                $incluye = true;
                                            }
                                            break;
                                        case 'entregados':
                                            if ($estado_entregado){
                                                $incluye = true;
                                            }
                                            break;
                                        case 'leidos':
                                            if ($estado_leido){
                                                $incluye = true;
                                            }
                                            break;
                                        case 'respondidos':
                                            if ($estado_respondido_busqueda != ""){
                                                $incluye = true;
                                            }
                                            break;
                                    }
                                }else{
                                    $incluye = true;
                                }   

                                if ($incluye){
                                    array_push($consulta, array(
                                        "id_contacto" => (int) $linea['id_contacto'],
                                        "contacto" => $linea['nombres_apellidos'],
                                        "celular" => $linea['celular'],
                                        "enviado" => $estado_enviado,
                                        "entregado" => $estado_entregado,
                                        "leido" => $estado_leido,
                                        "respondido" => $estado_respondido
                                    ));
                                }

                                
                            }
                        }
                    }

                    $respuesta['consulta'] = $consulta;
                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            // Procesamiento de reporte de tarjetas diners, etc.
            $app->get("/pendientes_tarjetas", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization'); 
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;

                // $respuesta['files'] = $files;
                $funciones = new Functions();
            
                try{
                    $crm = new CRM_API();
                                        
                    $consulta = $crm->Registros_Pendientes_Tarjetas($params);
                    $consolidados = $crm->Registros_Consolidado_Tarjetas($params);

                    $respuesta['consolidados']  = $consolidados;

                    $resultados = [];
                    if (is_array($consulta)){
                        if (count($consulta) > 0){
                            foreach ($consulta as $linea) {
                                $separa = explode("_", $linea['transaccion']);

                                $lote = $separa[0];
                                $id_cliente = $separa[1];
                                $referencia = $separa[2];

                                $estado_transaccion = "";
                                $color_estado = "";
                                switch ($linea['estado_transaccion']) {
                                    case 0:
                                        $estado_transaccion = "Por revisar";
                                        $color_estado = "warning";
                                        break;
                                   case 1:
                                        $estado_transaccion = "Por revisar";
                                        $color_estado = "warning";
                                        break;
                                    case 2:
                                        $estado_transaccion = "Por revisar";
                                        $color_estado = "warning";
                                        break;
                                    case 3:
                                        $estado_transaccion = "Revisado";
                                        $color_estado = "success";
                                        break;
                                    case 4:
                                        $estado_transaccion = "Cerrado";
                                        $color_estado = "secondary";
                                        break;                                        
                                }

                                $estado_descripcion = "";
                                if ($linea['estado'] != 0){
                                    $estado_descripcion = $linea['estado'];
                                }

                                $ingreso_sip = "";
                                $observaciones = "";
                                if (isset($linea['ingreso_sip'])){
                                    $ingreso_sip = $linea['ingreso_sip'];
                                    
                                    if ($linea['estado_transaccion'] == 3){
                                        $estado_transaccion = "Revisado";
                                        $color_estado = "success";
                                    }else if ($linea['estado_transaccion'] == 4){
                                        $estado_transaccion = "Cerrado";
                                        $color_estado = "secondary";
                                    }
                                }

                                if (isset($linea['observaciones'])){
                                    $observaciones = $linea['observaciones'];
                                }

                                array_push($resultados, array(
                                    "id_transaccion" => (int) $linea['id_transaccion'],
                                    "concepto" => $linea['concepto'],
                                    "det_banco" => $linea['det_banco'],
                                    "retencion" => $linea['retencion'],
                                    "numero_comprobante" => $linea['numero_comprobante'],
                                    "total_cobrar" => (float) $linea['total_cobrar'],
                                    "lote" => $lote,
                                    "id_cliente" => $id_cliente,
                                    "referencia" => $referencia,
                                    "ingreso_sip" => $ingreso_sip,
                                    "fecha" => $linea['fecha_pago'],
                                    "observaciones" => $observaciones,
                                    "registro" => $estado_descripcion,
                                    "fp" => $linea['det_fp'],
                                    "valor" => (float) $linea['det_valor'],
                                    "estado" => array(
                                        "valor" => (int) $linea['estado_transaccion'],
                                        "descripcion" => $estado_transaccion,
                                        "color" => $color_estado
                                    )
                                ));
                            }
                        }
                    }

                    $respuesta['consulta'] = $resultados;
                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/verificar_tarjetas", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');                 
                $respuesta['estado'] = false;
            
                try{
                    $crm = new CRM_API();
                                        
                    $consulta = $crm->Verificar_Estados_Transacciones();

                    $respuesta['consulta'] = $consulta;                                 
                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->post("/procesar_tarjetas", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                $files = $request->getUploadedFiles();

                $respuesta['estado'] = false;

                $respuesta['data'] = $data;
                $respuesta['files'] = $files;
                $funciones = new Functions();
            
                try{

                    $banco_a_procesar = $data['banco'];

                    $crm = new CRM_API();
                    $mysql = new Database("mvevip_tarjetas");
                    $mysql_p2p = new Database("vtgsa_ventas");
                    
                    // guarda el archivo csv de manera temporal
                    $archivo_temporal = $files['archivo']->file;
                    $carpeta = __DIR__."/../../public/storage/csv";

                    if (!file_exists($carpeta)){
                        // Si no existe la carpeta la crea
                        mkdir($carpeta, 0777, true);
                    }        
                    $nombre_archivo = "file".date("YmdHis").".csv";
                    $destino = $carpeta.'/'.$nombre_archivo;
                    move_uploaded_file($archivo_temporal, $destino);

                    // # La longitud máxima de la línea del CSV. Si no la sabes,
                    // # ponla en 0 pero la lectura será un poco más lenta
                    $longitudDeLinea = 2500;
                    $delimitador = ";"; # Separador de columnas
                    $caracterCircundante = "'"; # A veces los valores son encerrados entre comillas            

                    // # Abrir el archivo
                    $gestor = fopen($destino, "r");
                    if (!$gestor) {
                        $respuesta['error'] = "No se puede abrir el archivo";                        
                    }

                    $resultados = [];                   
                    
                    $numeroDeFila = 1;                    
                    while (($fila = fgetcsv($gestor, $longitudDeLinea, $delimitador, $caracterCircundante)) !== false) {
                        if ($numeroDeFila >= 2) {

                            $proseguir = false;

                            $fecha_voucher = $funciones->Cambiando_Fecha(utf8_decode(trim($fila[1])));

                            $comprobante_retencion = utf8_decode(trim($fila[37]));

                            if ($comprobante_retencion != 0){
                                $numero_tarjeta = utf8_decode(trim($fila[10]));
                                $ultimos_digitos_tarjeta = explode("-", $numero_tarjeta)[3];
                                $marca_tarjeta = utf8_decode(trim($fila[7]));

                                // Busca en el CRM de Fausto la referencia del pago
                                $info = $crm->getAbono(array(
                                    "lote" => utf8_decode(trim($fila[8])),
                                    "referencia" => utf8_decode(trim($fila[9])),
                                    "fecha" => $fecha_voucher,
                                    "valor" => utf8_decode(trim($fila[18]))
                                ));

                                // En caso de que el pago sea MARKETING VIP INTERNET, busca en la tabla de pagos de PlacetoPay                            

                                $lote = 0;                                
                                $numero_referencia = utf8_decode(trim($fila[9]));
                                $id_cliente = "";

                                $sql_p2p = "";

                                if (isset($info['consulta']['id_cliente'])){
                                    $id_cliente = $info['consulta']['id_cliente'];

                                    $lote = utf8_decode(trim($fila[8]));
                                    
                                }else{
                                    $id_cliente = "001";

                                    // consulta la transaccion en placetoplay
                                    // $placetopay = $mysql_p2p->Consulta_Unico("SELECT P.id_pago, P.document, P.name, P.surname, R.id_cliente FROM pagos_p2p_confirmacion P, registros R WHERE ((P.receipt LIKE '%".$numero_referencia."%') AND (P.ultimos_digitos='".$ultimos_digitos_tarjeta."') AND (P.status_pago='APPROVED')) AND (P.document=R.documento)");

                                    $placetopay = $mysql_p2p->Consulta_Unico("SELECT P.id_pago, P.document, P.name, P.surname, R.id_cliente FROM pagos_p2p_confirmacion P, registros R WHERE ((P.receipt LIKE '%".$numero_referencia."%') AND (P.status_pago='APPROVED')) AND (P.document=R.documento)");

                                    $sql_p2p = $placetopay;
                                    
                                    if (isset($placetopay['id_cliente'])){
                                        $id_cliente = $placetopay['id_cliente'];
                                    }

                                    $lote = utf8_decode(trim($fila[9]));                                    
                                }                            
                                
                                $fecha_pago = $funciones->Cambiando_Fecha(utf8_decode(trim($fila[3])));
                                $total_cobrar = utf8_decode(trim($fila[18]));
                                
                                $valor_neto = utf8_decode(trim($fila[12]));

                                $valor_pago_cuota = utf8_decode(trim($fila[23]));

                                $total_comision = utf8_decode(trim($fila[32]));   
                                
                                $valor_comision_cuota = utf8_decode(trim($fila[19]));
                                $total_retencion_iva = utf8_decode(trim($fila[33]));
                                $total_retencion_irf = utf8_decode(trim($fila[34]));                                
                                
                                
                                $comprobante_pago =  utf8_decode(trim($fila[38]));

                                $codigo_unico = utf8_decode(trim($fila[4]));

                                // guarda para el registro en la contabilidad
                                
                                $codigo_tarjeta = $banco_a_procesar;
                                $concepto = "LOTE -> ".$lote." ID -> ".$id_cliente;
                                
                                $suma_retenciones = $total_retencion_iva + $total_retencion_irf;

                                $transaccion = $lote."_".$id_cliente."_".$numero_referencia;
                                
                                $detalle = [];

                                $revisar_archivo = false;
                                $divisor = 0;

                                if ((($banco_a_procesar == 26) || ($banco_a_procesar == 15)) && ($marca_tarjeta == "DC")){
                                    $revisar_archivo = true;
                                    $divisor = 3;
                                }else if (($banco_a_procesar == 4) && ($marca_tarjeta == "ID")){
                                    $revisar_archivo = true;
                                    $divisor = 4;
                                }else if (($banco_a_procesar == 6) && ($marca_tarjeta == "PI")){
                                    $revisar_archivo = true;
                                    $divisor = 4;
                                }

                                if ($revisar_archivo){
                                    array_push($detalle, array(                                
                                        "retencion" => $comprobante_retencion,
                                        "transaccion" => $transaccion, 
                                        "codigo_tarjeta" => $codigo_tarjeta, 
                                        "concepto" => $concepto, 
                                        "fecha_pago" => $fecha_pago, 
                                        "total_cobrar" => $total_cobrar, 
                                        "det_fp" => "DP",
                                        "det_cod_banco" => 1,
                                        "det_banco" => "BANCO PICHINCHA COMTUMARK CTA CORRIENTE",
                                        "det_chtjrtdp" => $comprobante_pago,
                                        "det_voucher" => "2100091682",
                                        "det_fvence" => "",
                                        "det_valor" => $valor_pago_cuota,
                                        "numero_comprobante" => $comprobante_pago
                                    ));
    
                                    array_push($detalle, array(                                
                                        "retencion" => $comprobante_retencion,
                                        "transaccion" => $transaccion, 
                                        "codigo_tarjeta" => $codigo_tarjeta, 
                                        "concepto" => $concepto, 
                                        "fecha_pago" => $fecha_pago, 
                                        "total_cobrar" => $total_cobrar, 
                                        "det_fp" => "CO",
                                        "det_cod_banco" => "",
                                        "det_banco" => "",
                                        "det_chtjrtdp" => $comprobante_pago,
                                        "det_voucher" => $comprobante_pago,
                                        "det_fvence" => "",
                                        "det_valor" => $valor_comision_cuota,
                                        "numero_comprobante" => $comprobante_pago
                                    ));
    
                                    if (($banco_a_procesar == 26) || ($banco_a_procesar == 15)){ // solo diners y discover                            
                                        array_push($detalle, array(                                
                                            "retencion" => $comprobante_retencion,
                                            "transaccion" => $transaccion, 
                                            "codigo_tarjeta" => $codigo_tarjeta, 
                                            "concepto" => $concepto, 
                                            "fecha_pago" => $fecha_pago, 
                                            "total_cobrar" => $total_cobrar, 
                                            "det_fp" => "AD",
                                            "det_cod_banco" => "",
                                            "det_banco" => "",
                                            "det_chtjrtdp" => $comprobante_retencion,
                                            "det_voucher" => "",
                                            "det_fvence" => "",
                                            "det_valor" => $suma_retenciones,
                                            "numero_comprobante" => $comprobante_pago
                                        ));
    
                                    }else{ // resto de tarjetas
                                        array_push($detalle, array(                                
                                            "retencion" => $comprobante_retencion,
                                            "transaccion" => $transaccion, 
                                            "codigo_tarjeta" => $codigo_tarjeta, 
                                            "concepto" => $concepto, 
                                            "fecha_pago" => $fecha_pago, 
                                            "total_cobrar" => $total_cobrar, 
                                            "det_fp" => "RF",
                                            "det_cod_banco" => "",
                                            "det_banco" => "",
                                            "det_chtjrtdp" => $comprobante_retencion,
                                            "det_voucher" => "3440",
                                            "det_fvence" => "",
                                            "det_valor" => $total_retencion_irf,
                                            "numero_comprobante" => $comprobante_pago
                                        ));
    
                                        array_push($detalle, array(                                
                                            "retencion" => $comprobante_retencion,
                                            "transaccion" => $transaccion, 
                                            "codigo_tarjeta" => $codigo_tarjeta, 
                                            "concepto" => $concepto, 
                                            "fecha_pago" => $fecha_pago, 
                                            "total_cobrar" => $total_cobrar, 
                                            "det_fp" => "RI",
                                            "det_cod_banco" => "",
                                            "det_banco" => "",
                                            "det_chtjrtdp" => $comprobante_retencion,
                                            "det_voucher" => "70",
                                            "det_fvence" => "",
                                            "det_valor" => $total_retencion_iva,
                                            "numero_comprobante" => $comprobante_pago                                        
                                        ));
                                    }                                                      
    
                                    array_push($resultados, array(
                                        "id_comercio" => utf8_decode(trim($fila[0])),
                                        "fecha_vale" => $fecha_voucher,
                                        "fecha_facturacion" => $funciones->Cambiando_Fecha(utf8_decode(trim($fila[2]))),
                                        "fecha_pago" => $fecha_pago,
                                        "codigo_unico" => $codigo_unico,
                                        "nombre_comercio" => utf8_decode(trim($fila[5])),
                                        "canal_captura" => utf8_decode(trim($fila[6])),
                                        "marca" => $marca_tarjeta,
                                        "recap_lote" => $lote,
                                        "numero_vale" => $numero_referencia,
                                        "numero_tarjeta" => $numero_tarjeta,
                                        "tipo_credito" => utf8_decode(trim($fila[11])),
    
                                        "valor_cuota_consumo" => (float) $valor_neto,
                                        "valor_iva_cuota" => (float) utf8_decode(trim($fila[13])),
                                        "valor_otros_impuestos_cuota" => (float) utf8_decode(trim($fila[14])),
                                        "valor_propina_cuota" => (float) utf8_decode(trim($fila[15])),
                                        "valor_ice_cuota" => (float) utf8_decode(trim($fila[16])),
                                        "valor_intereses_socio_cuota" => (float) utf8_decode(trim($fila[17])),
                                        "valor_bruto_cuota" => (float) $total_cobrar,
                                        "valor_comision_cuota" => (float) $valor_comision_cuota,
                                        
                                        "porcentaje_cuota" => (float) utf8_decode(trim($fila[20])),
                                        "valor_retencion_iva_cuota" => (float) utf8_decode(trim($fila[21])),
                                        "valor_retencion_irf_cuota" => (float) utf8_decode(trim($fila[22])),
                                        "valor_pago_cuota" => (float) $valor_pago_cuota,
                                        "cuotas_trasladadas" => (float) utf8_decode(trim($fila[24])),
                                        "total_cuotas" => (float) utf8_decode(trim($fila[25])),
                                        "valor_total_consumo" => (float) utf8_decode(trim($fila[26])),
                                        "valor_total_iva" => (float) utf8_decode(trim($fila[27])),
                                        "valor_total_otros_impuestos" => (float) utf8_decode(trim($fila[28])),
    
                                        "valor_total_propina" => (float) utf8_decode(trim($fila[29])),
                                        "valor_total_ice" => (float) utf8_decode(trim($fila[30])),
                                        "valor_total_bruto" => (float) utf8_decode(trim($fila[31])),
                                        "valor_total_comision" => (float) $total_comision,
                                        "valor_total_retencion_iva" => (float) $total_retencion_iva,
                                        "valor_total_retencion_irf" => (float) $total_retencion_irf,
                                        "valor_total_pago" => (float) utf8_decode(trim($fila[35])),
    
                                        "valor_pendiente_vale" => (float) utf8_decode(trim($fila[36])),
                                        "comprobante_retencion" => $comprobante_retencion,
                                        "comprobante_pago" => $comprobante_pago,
                                        "factura" => utf8_decode(trim($fila[39])),
                                        "estado_vale" => utf8_decode(trim($fila[40])),
                                        "id_cliente" => $id_cliente,
                                        "crm" => $info,
                                        "detalle" => $detalle,
                                        "sql_p2p" => $sql_p2p
                                    )); 
                                }
                                
                            }                                                      
                        }
                        $numeroDeFila++;                        
                    }

                    fclose($gestor);
                    unlink($destino);                                    

                    if (count($resultados) > 0){
                        $respGuardados = $crm->guardarPagos($resultados);

                        if ($respGuardados['estado']){
                            $procesados = $respGuardados['procesados'];
                            $voucher_procesados = $procesados / $divisor;

                            if ($procesados == 0){
                                $respuesta['mensaje'] = "No se procesó ningún voucher del archivo recibido.";
                            }else{
                                $respuesta['mensaje'] = "Se procesaron correctamente ".$voucher_procesados." vouchers.";
                            }
                        
                            $respuesta['procesados']['total'] = $procesados;
                            $respuesta['procesados']['vouchers'] = $voucher_procesados;
                            $respuesta['resultados'] = $resultados;
                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = $respGuardados['error'];
                        }                    
                    }else{
                        $respuesta['error'] = "Es posible que el archivo no corresponda a la tarjeta seleccionada. Favor verifique y vuelva a intentar. No se proceso ningún voucher.";
                    }
                    
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/data", function(Request $request, Response $response){
                $respuesta['estado'] = false;
                try{                    

                    $crm = new CRM_API();

                    $respuesta['data'] = $crm->getAbono();
                    $respuesta['estado'] = true;
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->post("/cerrar_tarjetas_manual", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();                

                $respuesta['estado'] = false;

                $respuesta['data'] = $data;                
                $crm = new CRM_API();
            
                try{
                    $id_transaccion = 0;
                    if (isset($data['id_transaccion'])){
                        $id_transaccion = $data['id_transaccion'];
                    }

                    $registro = "";
                    if (isset($data['registro'])){
                        $registro = $data['registro'];
                    }
                    
                    $observaciones = "";
                    if (isset($data['observaciones'])){
                        $observaciones = $data['observaciones'];
                    }

                    $id_cliente = "";
                    if (isset($data['id_cliente'])){
                        $id_cliente = $data['id_cliente'];
                    }

                    if ($id_transaccion > 0){
                        
                        $actualizacion =  $crm->ActualizarPagos($id_transaccion, array(
                            "registro" => $registro,
                            "observaciones" => $observaciones,
                            "id_cliente" => $id_cliente
                        ));
                        $respuesta['actualizacion'] = $actualizacion;
                        $respuesta['estado'] = true;                        
                        
                    }else{
                        $respuesta['error'] = "No se identifica la transacción.";
                    }
                    
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            // Comparacion listado con clientes existentes en CRM

            $app->get("/verifica_clientes", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');

                $respuesta['estado'] = false;
            
                try{
                    $crm = new CRM_API();

                    $carpeta = __DIR__."../../../public/tmp/";

                    $destino = $carpeta."base_bgr_quito.csv";                                

                    // # La longitud máxima de la línea del CSV. Si no la sabes,
                    // # ponla en 0 pero la lectura será un poco más lenta
                    $longitudDeLinea = 2500;
                    $delimitador = ";"; # Separador de columnas
                    $caracterCircundante = "'"; # A veces los valores son encerrados entre comillas            

                    // # Abrir el archivo
                    $gestor = fopen($destino, "r");
                    if (!$gestor) {
                        $respuesta['error'] = "No se puede abrir el archivo";                        
                    }

                    $comparacion = [];
                    $numeroDeFila = 1;                    
                    while (($fila = fgetcsv($gestor, $longitudDeLinea, $delimitador, $caracterCircundante)) !== false) {
                        if ($numeroDeFila >= 2) {
                            $cedula = utf8_decode(trim($fila[0]));
                            $nombres = utf8_decode(trim($fila[1]));
                            $contacto1 = utf8_decode(trim($fila[2]));
                            $contacto2 = utf8_decode(trim($fila[3]));
                            $contacto3 = utf8_decode(trim($fila[4]));
                            $contacto4 = utf8_decode(trim($fila[5]));
                            $contacto5 = utf8_decode(trim($fila[6]));
                            $contacto6 = utf8_decode(trim($fila[7]));
                            
                            $idcliente = $crm->getIdCliente($cedula);

                            if (isset($idcliente['cli_codigo'])){
                                $cli_celular = $idcliente['cli_celular'];
                                $cli_email = $idcliente['cli_email'];
                                $registro = [$cedula, $nombres, $contacto1, $contacto2, $contacto3, $contacto4, $contacto5, $contacto6, $cli_celular, $cli_email];
                                array_push($comparacion, $registro);
                            }
                        }
                        $numeroDeFila++;                        
                    }

                    fclose($gestor);
                    // unlink($destino); 
                    $respuesta['comparacion'] = $comparacion;

                    $fp = fopen($carpeta.'comparacion.csv', 'w');
                    foreach ($comparacion as $fields) {
                        fputcsv($fp, $fields);
                    }                      
                    fclose($fp);

                    $respuesta['filas'] = $numeroDeFila;

                    $respuesta['estado'] = true;

                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            }); 
            
            // CAMPANA ENCUESTAS

            $app->get("/campana_encuesta/{correo}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $correo = $request->getAttribute('correo');
                $respuesta['estado'] = false;
            
                try{
                    $mysql = new Database("mvevip_crm_temp");
                    $autenticacion = new Authentication();

                    $fecha_inicio ="2022-02-01"; // obtiene las reservas que tienen checkout desde febrero 2022

                    $reserva = $mysql->Consulta_Unico("SELECT R.id_reserva, R.apellidos, R.nombres, R.pickup_fecha, R.dropoff_fecha, R.email1, D.nombre AS destino FROM reservas R, destinos D WHERE ((R.pickup_fecha>='".$fecha_inicio."') AND (R.dropoff_fecha>='".$fecha_inicio."')) AND ((R.envio_encuesta=0) AND (R.encuestado=0)) AND (R.pickup_destino=D.id_destino) ORDER BY R.id_reserva ASC LIMIT 1");

                    if (isset($reserva)){
                        $id_reserva = $reserva['id_reserva'];
                        $apellidos = $reserva['apellidos'];
                        $nombres = $reserva['nombres'];
                        $email = $reserva['email1'];
                        $pickup_fecha = $reserva['pickup_fecha'];
                        $dropoff_fecha = $reserva['dropoff_fecha'];
                        $destino = $reserva['destino'];

                        $cadena = $id_reserva."|".$apellidos."|".$nombres."|".$pickup_fecha."|".$dropoff_fecha."|".date("YmdHis");
                        $hash_encuesta = $autenticacion->encrypt_decrypt("encrypt", $cadena);

                        $modificar = $mysql->Modificar("UPDATE reservas SET hash_encuesta=? WHERE id_reserva=?", array($hash_encuesta, $id_reserva));

                        $datos_reserva = array(
                            "id_reserva" => (int) $id_reserva,
                            "apellidos" => $apellidos,
                            "nombres" => $nombres,
                            "pickup_fecha" => $pickup_fecha,
                            "dropoff_fecha" => $dropoff_fecha,
                            "email" => $email,
                            "destino" => $destino,
                            "hash" => $hash_encuesta
                        );

                        $respuesta['reserva'] = $datos_reserva;                        

                        if (!empty($email)){
                            $email = new Email("marketingvip@mvevip.com", "Tul72181", "Aseguramiento de Calidad", $empresa = "Marketing VIP");

                            $data = array(                            
                                "correo" => $datos_reserva['email'],                                
                                "destinatario" => $datos_reserva['apellidos']." ".$datos_reserva['nombres'],
                                "pickup_fecha" => $datos_reserva['pickup_fecha'],
                                "dropoff_fecha" => $datos_reserva['dropoff_fecha'],
                                "hash" => $datos_reserva['hash'],
                                "destino" => $datos_reserva['destino'],
                                "asunto" => "Encuesta Servicio Marketing VIP"
                            );

                            $respuesta['email'] = $email->Enviar_Encuesta($data, 'encuesta');
                            
                            $modificar = $mysql->Modificar("UPDATE reservas SET envio_encuesta=? WHERE id_reserva=?", array(1, $id_reserva));

                            $respuesta['estado'] = true;
                        }else{
                            $modificar = $mysql->Modificar("UPDATE reservas SET envio_encuesta=? WHERE id_reserva=?", array(2, $id_reserva));
                        }
                    }                

                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
                
                return $newResponse;
            }); 


            $app->get("/datos_encuesta", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
            
                try{
                    if ((isset($params['hash'])) && (!empty($params['hash']))){
                        $hash = $params['hash'];

                        $mysql = new Database("mvevip_crm_temp");
                        $autenticacion = new Authentication();
    
                        $decrypt = $autenticacion->encrypt_decrypt("decrypt", $hash);
                        $separa = explode("|", $decrypt);

                        if (isset($separa[5])){
                            $id_reserva = $separa[0];

                            // busca reserva con trasfer o auto
                            $reserva = $mysql->Consulta_Unico("SELECT R.id_reserva, D.nombre AS destino, R.encuestado, R.incluye_transfer_alojamiento FROM reservas R, destinos D WHERE (R.id_reserva=".$id_reserva.") AND (R.pickup_destino=D.id_destino)");

                            if (isset($reserva['id_reserva'])){
                                if ($reserva['encuestado'] == 0){
                                    
                                    $destino_cambiado = "";
                                    if ($reserva['destino'] == "GPS"){
                                        $destino_cambiado = "GALÁPAGOS";
                                    }else{
                                        $destino_cambiado = $reserva['destino'];
                                    }
                                    $respuesta['reserva'] = array(
                                        "destino" => $destino_cambiado,
                                        "encuestado" => (int) $reserva['encuestado'],
                                        "id_reserva" => (int) $reserva['id_reserva'],
                                        "incluye_transfer_alojamiento" => (int) $reserva['incluye_transfer_alojamiento']
                                    );

                                    // destinos  
                                    $destinos = $mysql->Consulta("SELECT R.id_destinos, D.nombre as destino, R.nombre_hotel FROM reservas_destinos R, destinos D WHERE (R.id_reserva=".$id_reserva.") AND (R.destino=D.id_destino)");

                                    $filtro_destinos = [];
                                    if (is_array($destinos)){
                                        if (count($destinos) > 0){
                                            foreach ($destinos as $linea_destino) {
                                                $destino_cambiado = "";
                                                if ($linea_destino['destino'] == "GPS"){
                                                    $destino_cambiado = "GALÁPAGOS";
                                                }else{
                                                    $destino_cambiado = $linea_destino['destino'];
                                                }

                                                array_push($filtro_destinos, array(
                                                    "destino" => $destino_cambiado,
                                                    "id_destinos" => (int) $linea_destino['id_destinos'],
                                                    "nombre_hotel" => $linea_destino['nombre_hotel']
                                                ));
                                            }
                                        }
                                    }

                                    $respuesta['destinos'] = $filtro_destinos;

                                    $respuesta['estado'] = true;
                                }else{
                                    $respuesta['error'] = "Ya se ha completado la encuesta.";
                                }
                            }else{
                                $respuesta['error'] = "No se encontro la reserva indicada.";
                            }
                        }else{
                            $respuesta['error'] = "No se ha encontrado toda la informacion de la reserva.";
                        }
                    }else{
                        $respuesta['error'] = "No se ha encontrado codigo de la reserva.";
                    }
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
                
                return $newResponse;
            }); 
    
            $app->post("/datos_encuesta", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();                

                $respuesta['estado'] = false;

                $respuesta['data'] = $data;

                try{
                    if ((isset($authorization[0])) && (!empty($authorization[0]))){
                        $mysql = new Database("mvevip_crm_temp");
                        $autenticacion = new Authentication();

                        $decrypt = $autenticacion->encrypt_decrypt("decrypt", $authorization[0]);
                        $separa = explode("|", $decrypt);

                        if (isset($separa[5])){
                            $id_reserva = $separa[0];

                            $respuestas_preguntas = $data['respuestas'];
                            $respuestas_observaciones = $data['observaciones'];

                            $respuestas = "";
                            if (is_array($respuestas_preguntas)){
                                if (count($respuestas_preguntas) > 0){
                                    foreach ($respuestas_preguntas as $linea) {
                                        $respuestas .= $linea."|";
                                    }
                                }
                            }
                            $fecha_alta = date("Y-m-d H:i:s");
                            $estado = 0;
                            
                            $ingreso = $mysql->Ingreso("INSERT INTO encuestas (id_reserva, respuestas, observaciones, fecha_alta, estado) VALUES (?,?,?,?,?)", array($id_reserva, $respuestas, $respuestas_observaciones, $fecha_alta, $estado));                           

                            $modificar = $mysql->Modificar("UPDATE reservas SET encuestado=? WHERE id_reserva=?", array(1, $id_reserva));
                            
                            $respuesta['estado'] = true;

                        }else{
                            $respuesta['error'] = "Error al recibir la información de la encuesta";
                        }
                    }else{
                        $respuesta['error'] = "No existe información suficiente para la encuesta.";
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });


            $app->get("/calculos_costos", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
            
                try{
                    $mysql = new Database("mve");

                    $buscador = "";
                    if (isset($params['buscador'])){
                        $buscador = $params['buscador'];
                    }
                    
                    $consulta = $mysql->Consulta("SELECT
                    A.abo_codigo, A.abo_cliente, A.abo_fecha, A.abo_forma_pago, A.abo_numero_ch_tar_ret AS abo_meses_diferido, A.abo_valor
                    FROM mve_abonos A
                    LEFT JOIN mve_cotizaciones C
                    ON A.abo_cotizacion=C.cot_codigo
                    LEFT JOIN mve_cotizaciones_det D
                    ON C.cot_numero=D.cod_num_coti
                    WHERE (A.abo_fecha BETWEEN '2022-01-01' AND '2022-01-31') AND ((A.abo_estado=5) || (A.abo_estado=6)) AND (D.cod_descripcion LIKE '%".$buscador."%')");

                   
                    if (is_array($consulta)){
                        if (count($consulta) > 0){
                            foreach ($consulta as $linea) {
                                $abo_valor = $linea['abo_valor'];

                                
                            }
                        }
                    }

                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
                
                return $newResponse;
            });

            // REVISION DE LLAMADAS

            $app->get("/revision-llamadas", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
            
                try{
                    $mysql = new Database("vtgsa_ventas");
                    $call = new CRM_API();

                    $consulta = $mysql->Consulta_Unico("SELECT * FROM notas_registros WHERE (comprobacion_llamada=0) AND (banco=2) AND ((identificador='30-05-2022-TIT') OR (identificador='30-05-2022-diners')) ORDER BY id_lista ASC LIMIT 1");

                    // $respuesta['consulta'] = $consulta;

                    if (isset($consulta['id_lista'])){
                        $id_lista = $consulta['id_lista'];
                        $celular = $consulta['telefono'];
                        $llamadas = $call->Obtener_Log_Llamadas($celular);

                        $respuesta['dato'] = array(
                            "id_lista" => (int) $id_lista,
                            "celular" => $celular,
                            "llamadas" => $llamadas
                        );

                        if ($llamadas["contenido"]['estado']){
                            if (isset($llamadas['contenido']['llamadas'])){
                                $contador = count($llamadas['contenido']['llamadas']);

                                $nuevo_estado = 2;
                                if ($contador > 0){
                                    $nuevo_estado = 1;
                                }

                                $modificar = $mysql->Modificar("UPDATE notas_registros SET comprobacion_llamada=? WHERE id_lista=?", array($nuevo_estado, $id_lista));

                                $respuesta['estado'] = true;
                            }
                        }
                        
                    }

                    
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });




            $app->get("/buscar-id-cliente", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
            
                try{
                    $mysql = new Database("vtgsa_ventas");
                    $call = new CRM_API();

                    $consulta = $mysql->Consulta("SELECT * FROM notas_registros WHERE (banco=4) AND (identificador='CRM-2019-2020') AND (asignado=0) AND (observaciones='') ORDER BY id_lista ASC LIMIT 1000");

                    if (is_array($consulta)){
                        if (count($consulta) > 0){
                            foreach ($consulta as $linea) {
                                $id_lista = $linea['id_lista'];
                                $idcliente = $call->getIdCliente($linea['documento']);

                                if (isset($idcliente['cli_codigo'])){
                                    $id_cliente = $idcliente['cli_codigo'];

                                    $modificar = $mysql->Modificar("UPDATE notas_registros SET observaciones=? WHERE id_lista=?", array($id_cliente, $id_lista));
                                }
                            }
                        }
                        
                    }

                    $respuesta['estado'] = true;

                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });


            $app->post("/imprimirContratoSeguros", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();  
                $respuesta['estado'] = false;
            
                try{
                    $pdf = new PDF();

                    $tipo_pago = 0; // 0: tarejta, 1: efectivo/deposito/transfer
                    // $datos = array(
                    //     "documento" => $registro['documento'],
                    //     "nombres" => $registro['nombres_apellidos'],
                    //     "personas" => $pasajeros,
                    //     "dias" => $dias,
                    //     "fecha_caducidad" => "2023-07-01",//$registro['fecha_caducidad'],
                    //     "valor" => (float) $total,
                    //     "forma_pago" => $tipo_pago,
                    //     "primeros_digitos" => $primeros_digitos,
                    //     "ultimos_digitos" => $ultimos_digitos,
                    //     "institucion" => "DISCOVER", //$institucion_financiera,
                    //     "fecha_modificacion" => $registro['fecha_modificacion']
                    // );
        
                    // $genera_contrato = $pdf->Contrato($datos, 'seguros');

                    $respuesta['estado'] = true;

                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });
           

            $app->get("/verificar_estados_cedulas", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
            
                try{
                    $mysql = new Database("vtgsa_ventas");
                    
                    $documentos = [
                        "1714341193",
                        "0930907399",
                        "2000073821",
                        "1314059641",
                        "0603052234",
                        "0930071576",
                        "1600418444",
                        "0927753657",
                        "1706478516",
                        "1002582177",
                        "1756201875",
                        "1205766502",
                        "1500601776",
                        "0952997658",
                        "1721849774",
                        "1712339363",
                        "1714383500",
                        "0401741210",
                        "1900578210",
                        "0940373863",
                        "1001692480",
                        "1312766841",
                        "1725585614",
                        "1757794191",
                        "1718673260",
                        "1719414367",
                        "1709094351",
                        "0940514169",
                        "0910893445",
                        "0401630009",
                        "0804737492",
                        "0912859402",
                        "1804605606",
                        "0705847622",
                        "1719195172",
                        "1722816152",
                        "0302077755",
                        "0931422117",
                        "1719723494",
                        "0950059873",
                        "0961344082",
                        "0951706142",
                        "0803023480",
                        "1756276588",
                        "0604082347",
                        "1712231461",
                        "0802318501",
                        "1725331068",
                        "1316064466",
                        "0102897741",
                        "1103572192",
                        "0926507518",
                        "0503973935",
                        "0926853185",
                        "0920875119",
                        "0401577846",
                        "1724060858",
                        "1721894374",
                        "1723305072",
                        "0925377186",
                        "1721965679",
                        "1750198770",
                        "1704507340",
                        "0101005627",
                        "0931691737",
                        "0917098642",
                        "1714666920",
                        "1724942642",
                        "1725686719",
                        "0926827411",
                        "1314882851",
                        "0912632080",
                        "1206108274",
                        "1311051203",
                        "1804476222",
                        "1204361339",
                        "0503246431",
                        "0105614440",
                        "0930603147",
                        "1712923083",
                        "1716391485",
                        "1900713528",
                        "1756748172",
                        "0961662681",
                        "1721919676",
                        "1719789016",
                        "1715636856",
                        "0931204333",
                        "0912097417",
                        "0928979244",
                        "0926862673",
                        "1758036055",
                        "0962053104",
                        "0915427850",
                        "0918275223",
                        "1724573983",
                        "1756842975",
                        "1713065629",
                        "0603356312",
                        "1757098270",
                        "0603540956",
                        "0602620668",
                        "0930670070",
                        "1722256110",
                        "1707154272",
                        "1717550923",
                        "0925660078",
                        "0102275047",
                        "1720994381",
                        "1716650054",
                        "1002337549",
                        "1708793714",
                        "1719324897",
                        "0104492350",
                        "0922195987",
                        "1715922421",
                        "0924127608",
                        "0919754234",
                        "0920574076",
                        "0803140102",
                        "1707598841",
                        "0102227436",
                        "1400654701",
                        "1719693960",
                        "1718408188",
                        "1716641301",
                        "1714163951",
                        "1002428546",
                        "1750373118",
                        "0104415922",
                        "0916400583",
                        "1756717961",
                        "1710437946",
                        "1718709213",
                        "1308611399",
                        "0955346648",
                        "1711653657",
                        "0704394568",
                        "1706865514",
                        "0911383743",
                        "1721042446",
                        "2200109631",
                        "0917297517",
                        "0704516038",
                        "1102118666",
                        "0300971496",
                        "1850202936",
                        "1103987192",
                        "0101308310",
                        "1710089986",
                        "1710168947",
                        "0103889978",
                        "1712532181",
                        "1712609948",
                        "1715585095",
                        "1717643413",
                        "1304261488",
                        "1720875366",
                        "1758813560",
                        "1313910232",
                        "1709095341",
                        "1757682990",
                        "1758153777",
                        "0803988799",
                        "0931017149",
                        "0101854958",
                        "0501908198",
                        "1711126415",
                        "1708391253",
                        "0916626963",
                        "1724761075",
                        "0919284380",
                        "1307253623",
                        "0603378779",
                        "0706673076",
                        "1311413858",
                        "1718613167",
                        "0931818918",
                        "0923528582",
                        "1721836540",
                        "0801037896",
                        "0960540797",
                        "1713716163",
                        "0706713096",
                        "1706970595",
                        "0928435296",
                        "0928418425",
                        "1723747679",
                        "1758505745",
                        "0104482286",
                        "0908320732",
                        "0916907603",
                        "1711232882",
                        "0961388907",
                        "0923168215",
                        "1707282610",
                        "1719354571",
                        "0923284244",
                        "1002090593",
                        "1723650840",
                        "0801909797",
                        "1707617427",
                        "0913094256",
                        "0928288463",
                        "0907938468",
                        "1721846929",
                        "0921318580",
                        "2200007702",
                        "1710471978",
                        "1003473129",
                        "0920472149",
                        "0103687497",
                        "1309538351",
                        "0916048242",
                        "0703865048",
                        "0602281578",
                        "1311415051",
                        "0952444297",
                        "1715038913",
                        "0940871841",
                        "1714331723",
                        "1002794749",
                        "1720132008",
                        "1718959024",
                        "1717575870",
                        "0502671860",
                        "1725079766",
                        "1309063939",
                        "1722462551",
                        "1725787863",
                        "1003862164",
                        "0909536849",
                        "1756776637",
                        "1311727562",
                        "1205927054",
                        "1717644346",
                        "0951370196",
                        "1757660772",
                        "0904334562",
                        "0925805046",
                        "0602681207",
                        "0151487899",
                        "0704264480",
                        "1103411201",
                        "1713131512",
                        "1708284532",
                        "1722648878",
                        "1714556113",
                        "1204688848",
                        "0503254799",
                        "0941451411",
                        "1312295155",
                        "0705848836",
                        "0911938454",
                        "0919668228",
                        "0604882381",
                        "0104207790",
                        "0104726377",
                        "1714267927",
                        "0202089108",
                        "1757597057",
                        "0924096258",
                        "1716898380",
                        "1104708712",
                        "1726538703",
                        "0918136557",
                        "0703813865",
                        "0929608818",
                        "0103218889",
                        "1713790929",
                        "0919746685",
                        "0105193734",
                        "1755525423",
                        "1203793870",
                        "1723355093",
                        "1721354767",
                        "1102971775",
                        "0959562398",
                        "0200514479",
                        "1715570147",
                        "0917566580",
                        "0925683187",
                        "0914895156",
                        "0922049606",
                        "0926905753",
                        "0926515925",
                        "0706754637",
                        "0951610864",
                        "0104303490",
                        "0924791536",
                        "1003362991",
                        "0918315490",
                        "1717179343",
                        "1719050294",
                        "0924763956",
                        "1050174711",
                        "0704895812",
                        "0914791413",
                        "1900676956",
                        "1711198562",
                        "1716399355",
                        "1805118021",
                        "0910659754",
                        "0931479356",
                        "0706360799",
                        "0930256110",
                        "0802174060",
                        "1722926415",
                        "1709421430",
                        "0918978651",
                        "1757611957",
                        "0925424970",
                        "0914083399",
                        "1753989167",
                        "1721540381",
                        "1723820682",
                        "0925648990",
                        "0919373886",
                        "0704290550",
                        "1710456375",
                        "1804797502",
                        "0930119748",
                        "1313730911",
                        "1308611837",
                        "1003519095",
                        "0401325758",
                        "1759802174",
                        "1756479315",
                        "0916959471",
                        "1715918064",
                        "0921271235",
                        "1721309373",
                        "1713381885",
                        "1712134830",
                        "1311005050",
                        "1719159871",
                        "0605155605",
                        "0927697508",
                        "1715067128",
                        "1713028973",
                        "0926248220",
                        "1723637938",
                        "1717436560",
                        "1206510594",
                        "0919815266",
                        "1751203942",
                        "0962915096",
                        "1716367998",
                        "0954337663",
                        "1312469164",
                        "1757904220",
                        "0919198176",
                        "0925762643",
                        "1710036581",
                        "1721994703",
                        "0940568280",
                        "1757124555",
                        "1719701581",
                        "1314451004",
                        "1202951552",
                        "1002971024",
                        "0704804749",
                        "0706377702",
                        "1310583412",
                        "0921938577",
                        "1716117310",
                        "0102072618",
                        "0602067878"
                    ];

                    $funciones = new Functions();

                    $resultados['si'] = [];
                        $resultados['no'] = [];

                    foreach ($documentos as $linea) {
                        $consulta = $mysql->Consulta_Unico("SELECT id_lista, banco, identificador, documento, estado, asignado FROM notas_registros WHERE (documento LIKE '%".$linea."%')");

                        
                        if (isset($consulta['id_lista'])){
                            array_push($resultados['si'], array(
                                "documento" => $linea,
                                "id_lista" => $consulta['id_lista'],
                                "estado" => $funciones->Obtener_Estado($consulta['estado']),
                                "asignado" => $consulta['asignado'],
                                "banco" => $consulta['banco'],
                                "identificador" => $consulta['identificador']
                            ));
                        }else{
                            array_push($resultados['no'], array(
                                "doucmento" => $linea
                            ));
                        }
                    }
                    
                    $respuesta['consulta'] = $resultados;
                    $respuesta['estado'] = true;

                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/verificador-email", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
            
                try{
                    $correo = $params['correo'];
                    $password = $params['password'];

                    $nombre = "Nombre del Usuario";
                    if (isset($params['nombre'])){
                        $nombre = $params['nombre'];
                    }

                    $email = new Email($correo, $password, $nombre, $empresa = "Marketing VIP");

                    $contenido = "<h1>Correo de Prueba</h1>";
                    $asunto = "Correo de Prueba";
                    $correo = $params['envio'];
                    $nombres = "Nombre del Receptor";
                    $envio = $email->Enviar_Correo($correo, $nombres, $asunto, $contenido, [], []);

                    $respuesta['envio'] = $envio;
                    
                    $respuesta['estado'] = true;

                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });
        });




        /// BANCO INTERNACIONAL
        $app->group('/internacional', function() use ($app) {
    
            $app->get("/estados", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
                
                try{
                    $mysql = new Database("vtgsa_ventas");

                    $filtro = "";
                    $sql = "";
                    if (isset($params['filtro'])){
                        switch ($params['filtro']) {
                            case 'visitas':
                                $sql = "SELECT
                                id_estado, UPPER(descripcion) AS descripcion
                                FROM registros_internacional_visitas_estados
                                WHERE (estado=1)";
                                break; 
                            default:
                                $sql = "SELECT
                                id_estado, UPPER(descripcion) AS descripcion
                                FROM registros_internacional_estados
                                WHERE (estado=1)";
                                break;
                        }
                    }else{
                        $sql = "SELECT
                        id_estado, UPPER(descripcion) AS descripcion
                        FROM registros_internacional_estados
                        WHERE (estado=1)";
                    }

                    $consulta = $mysql->Consulta($sql);

                    $listado = [];
                    if (is_array($consulta)){
                        if (count($consulta) > 0){
                            foreach ($consulta as $linea) {
                                array_push($listado, array(
                                    "id" => (int) $linea['id_estado'],
                                    "descripcion" => $linea['descripcion']
                                ));
                            }
                        }
                    }

                    $respuesta['consulta'] = $listado;
                    $respuesta['estado'] = true;

                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/ciudades", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
                
                try{
                    $mysql = new Database("vtgsa_ventas");

                    $filtro = "";
                    $sql = "";
                    if (isset($params['filtro'])){
                        switch ($params['filtro']) {
                            case 'visitas':
                                $sql = "SELECT
                                id_ciudad, ciudad, sector
                                FROM registros_internacional_visitas_ciudades
                                WHERE (estado=1)";
                                break; 
                            default:
                                $sql = "SELECT
                                id_ciudad, ciudad, sector
                                FROM registros_internacional_ciudades
                                WHERE (estado=1)";
                                break;
                        }
                    }else{
                        $sql = "SELECT
                        id_ciudad, ciudad, sector
                        FROM registros_internacional_ciudades
                        WHERE (estado=1)";
                    }

                    $consulta = $mysql->Consulta($sql);

                    $listado = [];
                    if (is_array($consulta)){
                        if (count($consulta) > 0){
                            foreach ($consulta as $linea) {
                                array_push($listado, array(
                                    "id" => (int) $linea['id_ciudad'],
                                    "ciudad" => $linea['ciudad'],
                                    "sector" => $linea['sector']
                                ));
                            }
                        }
                    }

                    $respuesta['consulta'] = $listado;
                    $respuesta['estado'] = true;

                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/dashboard", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
                
                try{
                    $mysql = new Database("vtgsa_ventas");

                    $ciudades = [];

                    $consulta = $mysql->Consulta("SELECT
                    R.id_ciudad, C.ciudad, COUNT(R.id_ciudad) AS total
                    FROM registros_internacional R
                    LEFT JOIN registros_internacional_ciudades C
                    ON R.id_ciudad = C.id_ciudad
                    GROUP BY R.id_ciudad
                    ORDER BY COUNT(R.id_ciudad) DESC");

                    $listado = [];
                    if (is_array($consulta)){
                        if (count($consulta) > 0){
                            foreach ($consulta as $linea) {
                                array_push($ciudades, array(
                                    "name" => $linea['ciudad'],
                                    "y" => (int) $linea['total']
                                ));
                            }
                        }
                    }

                    // con documentacion
                    $documentacion = array(
                        "categories" => [],
                        "series" => [
                            array(
                                "name" => "Pendientes",
                                "data" => []
                            ),
                            array(
                                "name" => "Parcialmente",
                                "data" => []
                            ),
                            array(
                                "name" => "Completo",
                                "data" => []
                            ),
                        ]
                    );

                    $listaCiudades = $mysql->Consulta("SELECT
                    R.id_ciudad, C.ciudad, COUNT(A.id_adjunto) AS total
                    FROM registros_internacional_adjuntos A
                    LEFT JOIN registros_internacional R
                    ON A.id_lead = R.id_lead
                    LEFT JOIN registros_internacional_ciudades C
                    ON R.id_ciudad = C.id_ciudad
                    WHERE (A.archivo!='')
                    GROUP BY R.id_ciudad"); 

                    if (is_array($listaCiudades)){
                        if (count($listaCiudades) > 0){
                            foreach ($listaCiudades as $linea) {
                                $id_ciudad = $linea['id_ciudad'];

                                array_push($documentacion['categories'], $linea['ciudad']);

                                $data = [];
                                $listaEstablecimientos = $mysql->Consulta("SELECT
                                *
                                FROM registros_internacional R
                                WHERE (R.id_ciudad = ".$id_ciudad.")");

                                if (is_array($listaEstablecimientos)){
                                    if (count($listaEstablecimientos) > 0){ 
                                        $totalDocs = 0;
                                        $totalValidados = 0;

                                        foreach ($listaEstablecimientos as $linea) {
                                            $id_lead = $linea['id_lead']; 
                                            
                                            $verAdjuntos = $mysql->Consulta("SELECT
                                            E.id_estado, E.descripcion, 
                                            (SELECT COUNT(A.id_adjunto) FROM registros_internacional_adjuntos A WHERE (A.estado=E.id_estado) AND (A.id_lead=".$id_lead.")) AS total
                                            FROM registros_internacional_adjuntos_estado E");

                                            if (is_array($verAdjuntos)){
                                                if (count($verAdjuntos) > 0){
                                                    foreach ($verAdjuntos as $lineaAdjunto) {
                                                        $totalDocs += $lineaAdjunto['total'];

                                                        if ($lineaAdjunto['id_estado'] == 2){
                                                            $totalValidados += $lineaAdjunto['total'];
                                                        }
                                                    }
                                                }
                                            } 
                                        }

                                        $pendientes = $totalDocs - $totalValidados;
                                        $parcialmente = 0;

                                        array_push($documentacion['series'][0]['data'], (int) $pendientes );
                                        array_push($documentacion['series'][1]['data'], (int) $parcialmente );
                                        array_push($documentacion['series'][2]['data'], (int) $totalValidados );
                                    }
                                }


 
                            }
                        }
                    }

                    $respuesta['documentacion'] = $documentacion;
                    $respuesta['ciudades'] = $ciudades;
                    $respuesta['estado'] = true;

                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/establecimientos", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
                $respuesta['params'] = $params;
                
                try{
                    $mysql = new Database("vtgsa_ventas");

                    $buscador = '';
                    if ((isset($params['buscador'])) && (!empty($params['buscador']))){
                        $buscador = $params['buscador'];
                    }

                    $ciudad = '';
                    if ((isset($params['ciudad']))){
                        if ($params['ciudad'] >= 0){
                            $ciudad = "AND (C.id_ciudad = ".$params['ciudad'].")";
                        }
                    }

                    $estado = '';
                    if ((isset($params['estado']))){
                        if ($params['estado'] >= 0){
                            $estado = "AND (R.estado = ".$params['estado'].")";
                        }
                    }

                    $consulta = $mysql->Consulta("SELECT
                    R.id_lead, R.ruc, R.comercio, R.propietario, C.ciudad, R.direccion, R.telefono, R.celular, R.fechaAlta, R.fechaModificacion, R.estado, E.descripcion, E.color, E.icono, E.linkeable
                    FROM registros_internacional R
                    LEFT JOIN registros_internacional_ciudades C
                    ON R.id_ciudad = C.id_ciudad
                    LEFT JOIN registros_internacional_estados E
                    ON R.estado = E.id_estado
                    WHERE ((R.comercio LIKE '%".$buscador."%') OR (R.propietario LIKE '%".$buscador."%') OR (R.ruc LIKE '%".$buscador."%')) ".$ciudad." ".$estado);

                    $listado = [];
                    if (is_array($consulta)){
                        if (count($consulta) > 0){
                            foreach ($consulta as $linea) {
                                array_push($listado, array(
                                    "id" => (int) $linea['id_lead'],
                                    "ruc" => $linea['ruc'],
                                    "comercio" => $linea['comercio'],
                                    "propietario" => $linea['propietario'],
                                    "ciudad" => $linea['ciudad'],
                                    "direccion" => $linea['direccion'],
                                    "telefono" => $linea['telefono'],
                                    "celular" => $linea['celular'],
                                    "fechaAlta" => $linea['fechaAlta'],
                                    "fechaModificacion" => $linea['fechaModificacion'],
                                    "estado" => array(
                                        "valor" => (int) $linea['estado'],
                                        "descripcion" => $linea['descripcion'],
                                        "color" => $linea['color'],
                                        "icono" => $linea['icono'],
                                        "linkeable" => (bool) $linea['linkeable']
                                    ),
                                ));
                            }
                        }
                    }
                    
                    $respuesta['consulta'] = $listado;
                    $respuesta['estado'] = true;

                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/establecimientos/{id}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id = $request->getAttribute('id');
                $respuesta['estado'] = false; 
                
                try{
                    $mysql = new Database("vtgsa_ventas");
                    $carpeta = "https://api.digitalpaymentnow.com/tmp/"; 

                    $consulta = $mysql->Consulta_Unico("SELECT
                    R.id_lead, R.ruc, R.comercio, R.propietario, C.ciudad, R.direccion, R.telefono, R.celular, R.fechaAlta, R.fechaModificacion, R.estado, E.descripcion, E.color, E.icono, R.latitud, R.longitud
                    FROM registros_internacional R
                    LEFT JOIN registros_internacional_ciudades C
                    ON R.id_ciudad = C.id_ciudad
                    LEFT JOIN registros_internacional_estados E
                    ON R.estado = E.id_estado
                    WHERE (R.id_lead=".$id.")");

                    if (isset($consulta['id_lead'])){ 

                        $adjuntos = $mysql->Consulta("SELECT
                        A.id_adjunto, A.archivo, A.extension, A.fechaAlta, A.fechaModificacion, D.nombre, A.estado, E.descripcion, E.color
                        FROM registros_internacional_adjuntos A
                        LEFT JOIN registros_internacional_documentacion D
                        ON A.id_formulario = D.id_formulario
                        LEFT OUTER JOIN registros_internacional_adjuntos_estado E
                        ON A.estado = E.id_estado
                        WHERE (A.id_lead=".$id.") AND (A.archivo!='')");

                        $logs = $mysql->Consulta("SELECT
                        L.descripcion, L.fecha, UPPER(U.nombres) AS responsable
                        FROM registros_internacional_logs L
                        LEFT JOIN usuarios U
                        ON L.id_usuario = U.id_usuario
                        WHERE L.id_lead=".$id."
                        ORDER BY L.fecha DESC");

                        $listaAdjuntos = [];
                        if (is_array($adjuntos)){
                            if (count($adjuntos) > 0){
                                foreach ($adjuntos as $linea) {
                                    array_push($listaAdjuntos, array(
                                        "id" => (int) $linea['id_adjunto'],
                                        "archivo" => array(
                                            "descripcion" => $linea['nombre'],
                                            "nombre" => $linea['archivo'],
                                            "extension" => $linea['extension'],
                                            "link" => $carpeta.$linea['archivo'].".".$linea['extension']
                                        ),
                                        "fechaAlta" => $linea['fechaAlta'],
                                        "fechaModificacion" => $linea['fechaModificacion'],
                                        "estado" => array(
                                            "valor" => (int) $linea['estado'],
                                            "descripcion" => $linea['descripcion'],
                                            "color" => $linea['color']
                                        )
                                    ));
                                }
                            }
                        }

                        $respuesta['consulta'] = array(
                            "id" => (int) $consulta['id_lead'],
                            "ruc" => $consulta['ruc'],
                            "comercio" => $consulta['comercio'],
                            "propietario" => $consulta['propietario'],
                            "ciudad" => $consulta['ciudad'],
                            "direccion" => $consulta['direccion'],
                            "posicion" => array(
                                "latitud" => $consulta['latitud'],
                                "longitud" => $consulta['longitud'],
                            ),
                            "telefono" => $consulta['telefono'],
                            "celular" => $consulta['celular'],
                            "fechaAlta" => $consulta['fechaAlta'],
                            "fechaModificacion" => $consulta['fechaModificacion'],
                            "estado" => array(
                                "valor" => (int) $consulta['estado'],
                                "descripcion" => $consulta['descripcion'],
                                "color" => $consulta['color'],
                                "icono" => $consulta['icono'],
                            ),
                            "adjuntos" => $listaAdjuntos,
                            "logs" => $logs
                        );

                        $respuesta['estado'] = true;

                    }else{
                        $respuesta['error'] = "No se encuentra información del establecimiento.";
                    } 

                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->put("/establecimientos/{id}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id = $request->getAttribute('id');
                $data = $request->getParsedBody();
                $respuesta['estado'] = false; 

                $respuesta['data'] = $data;
                
                try{
                    $mysql = new Database("vtgsa_ventas");

                    $modificar = $mysql->Modificar("UPDATE registros_internacional SET estado=? WHERE id_lead=?", array($data['estado'], $id));
                    
                    $respuesta['mensaje'] = "Establecimiento actualizado correctamente.";
                    $respuesta['estado'] = true;

                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

        });

    });
});