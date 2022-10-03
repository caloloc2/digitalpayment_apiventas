<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\UploadedFileInterface;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Aws\Credentials\CredentialProvider;

$app = new \Slim\App;

$app->group('/api', function() use ($app) {

    $app->group('/v2', function() use ($app) {

        $app->post("/auth", function(Request $request, Response $response) {
            $data = $request->getParsedBody();
            $respuesta['estado'] = false;
        
            try{                
                $mysql = new Database(CRM);

                if (((isset($data['username'])) && (!empty($data['username']))) && ((isset($data['password'])) && (!empty($data['password'])))){
                    $username = $data['username'];
                    $password = $data['password'];

                    $consulta = $mysql->Consulta_Unico("SELECT U.usu_codigo, U.usu_apellido, U.usu_nombre, CONCAT(U.usu_apellido,' ',U.usu_nombre) AS nombreCompleto, U.usu_email, U.usu_estado, D.tid_nombre AS acceso, D.tid_estado AS estadoGrupo, D.tid_ruta
                    FROM mve_usuarios U
                    LEFT JOIN gen_tipo_detalle D
                    ON U.tid_codigo = D.tid_codigo WHERE (usu_login='".$username."') AND (usu_pass='".$password."')");

                    if (isset($consulta['usu_codigo'])){
                        if ($consulta['estadoGrupo'] == 1){
                            if ($consulta['usu_estado'] == 1){

                                $id_usuario = $consulta['usu_codigo'];
                                $nombres = $consulta['nombreCompleto'];
                                $correo = $consulta['usu_email'];

                                $cadena = $id_usuario."|".$nombres."|".$correo."|".date("Ymd");

                                $autenticacion = new Authentication();
                                $hash = $autenticacion->encrypt_decrypt("encrypt", $cadena);

                                $actualiza = $mysql->Modificar("UPDATE mve_usuarios SET hash=? WHERE usu_codigo=?", array($hash, $id_usuario));

                                $respuesta['accessToken'] = $hash;
                                $respuesta['url'] = $consulta['tid_ruta'];
                                $respuesta['estado'] = true; 
                            }else{
                                $respuesta['error'] = "Su usuario se encuentra deshabilitado.";
                            }
                        }else{
                            $respuesta['error'] = "Su grupo de acceso ha sido deshabilitado.";
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
                $mysql = new Database(CRM);

                if (isset($authorization[0])){
                    $autenticacion = new Authentication();
                    $session = $autenticacion->Valida_Sesion($authorization[0]);

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

        // Formas de Pago
        $app->get("/formasPago", function(Request $request, Response $response){
            $authorization = $request->getHeader('Authorization');
            $params = $request->getQueryParams();
            $respuesta['estado'] = false;
            
            try{                    
                $auth = new Authentication();

                $usuario = $auth->Valida_Sesion($authorization[0]);
                
                if ($usuario['estado']){                                                
                    $id_usuario = $usuario['usuario']['id_usuario'];

                    $mysql = new Database(CRM);
                    $consulta = $mysql->Consulta("SELECT mve.gen_tipo.tip_codigo AS tid_codigo, mve.gen_tipo.tip_nombre AS tid_nombre FROM mve.gen_tipo WHERE mve.gen_tipo.tip_codigo IN (2,7,8,12,13,22) AND mve.gen_tipo.tip_estado=1 ORDER BY 2"); 

                    $listado = [];
                    if (is_array($consulta)){
                        if (count($consulta) > 0){
                            foreach ($consulta as $linea) {
                                array_push($listado, array(
                                    "id" => (int) $linea['tid_codigo'],
                                    "descripcion" => $linea['tid_nombre']
                                ));
                            }
                        }
                    }

                    $respuesta['consulta'] = $listado;
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

        $app->get("/tipoFormasPago/{tid_codigo}", function(Request $request, Response $response){
            $authorization = $request->getHeader('Authorization');
            $tid_codigo = $request->getAttribute('tid_codigo');
            $respuesta['estado'] = false;
            
            try{                    
                $auth = new Authentication();

                $usuario = $auth->Valida_Sesion($authorization[0]);
                
                if ($usuario['estado']){                                                
                    $id_usuario = $usuario['usuario']['id_usuario'];

                    $mysql = new Database(CRM);
                   
                    $consulta = $mysql->Consulta("SELECT gen_tipo_detalle.tid_codigo,gen_tipo_detalle.tid_nombre FROM gen_tipo_detalle WHERE gen_tipo_detalle.tid_estado = 1 AND gen_tipo_detalle.tip_codigo=".$tid_codigo);

                    $extra = [];

                    if ($tid_codigo == 8){
                        $extra = $mysql->Consulta("SELECT mve.gen_tipo_detalle.tid_codigo, mve.gen_tipo_detalle.tid_nombre FROM mve.gen_tipo_detalle WHERE mve.gen_tipo_detalle.tid_estado = 1 AND mve.gen_tipo_detalle.tip_codigo = 9");
                    }
            
                    if (count($consulta) > 0){
                        $respuesta['extra'] = $extra;
                        $respuesta['consulta'] = $consulta;
                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No se encontro formas de pago disponibles.";
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

        // Lista de Paises / Provincias / Ciudades
        $app->get("/paises", function(Request $request, Response $response){
            $authorization = $request->getHeader('Authorization');
            $params = $request->getQueryParams();
            $respuesta['estado'] = false;
            
            try{                    
                $auth = new Authentication();

                $usuario = $auth->Valida_Sesion($authorization[0]);
                
                if ($usuario['estado']){                                                
                    $id_usuario = $usuario['usuario']['id_usuario'];

                    $mysql = new Database(CRM);
                    $consulta = $mysql->Consulta("SELECT * FROM mve_geo_paises WHERE (estado=1) ORDER BY nombre ASC"); 

                    $listado = [];
                    if (is_array($consulta)){
                        if (count($consulta) > 0){
                            foreach ($consulta as $linea) {
                                array_push($listado, array(
                                    "id" => (int) $linea['id'],
                                    "descripcion" => strtoupper($linea['nombre'])
                                ));
                            }
                        }
                    }

                    $respuesta['consulta'] = $listado;
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

        $app->get("/provincias/{idPais}", function(Request $request, Response $response){
            $authorization = $request->getHeader('Authorization');
            $idPais = $request->getAttribute('idPais');
            $respuesta['estado'] = false;
            
            try{                    
                $auth = new Authentication();

                $usuario = $auth->Valida_Sesion($authorization[0]);
                
                if ($usuario['estado']){                                                
                    $id_usuario = $usuario['usuario']['id_usuario'];

                    $mysql = new Database(CRM);
                    $consulta = $mysql->Consulta("SELECT * FROM mve_geo_provincias WHERE (id_pais=".$idPais.") AND (estado=1) ORDER BY nombre ASC"); 

                    $listado = [];
                    if (is_array($consulta)){
                        if (count($consulta) > 0){
                            foreach ($consulta as $linea) {
                                array_push($listado, array(
                                    "id" => (int) $linea['id'],
                                    "descripcion" => $linea['nombre']
                                ));
                            }
                        }
                    }

                    $respuesta['consulta'] = $listado;
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

        $app->get("/ciudades/{idPais}/{idProvincia}", function(Request $request, Response $response){
            $authorization = $request->getHeader('Authorization');
            $idPais = $request->getAttribute('idPais');
            $idProvincia = $request->getAttribute('idProvincia');
            $respuesta['estado'] = false;
            
            try{                    
                $auth = new Authentication();

                $usuario = $auth->Valida_Sesion($authorization[0]);
                
                if ($usuario['estado']){                                                
                    $id_usuario = $usuario['usuario']['id_usuario'];

                    $mysql = new Database(CRM);
                    $consulta = $mysql->Consulta("SELECT * FROM mve_geo_ciudades WHERE (id_pais=".$idPais.") AND (id_provincia=".$idProvincia.") AND (estado=1) ORDER BY nombre ASC"); 

                    $listado = [];
                    if (is_array($consulta)){
                        if (count($consulta) > 0){
                            foreach ($consulta as $linea) {
                                array_push($listado, array(
                                    "id" => (int) $linea['id'],
                                    "descripcion" => strtoupper($linea['nombre'])
                                ));
                            }
                        }
                    }

                    $respuesta['consulta'] = $listado;
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

        // Busqueda General
        $app->get("/busqueda-general", function(Request $request, Response $response){
            $authorization = $request->getHeader('Authorization');
            $params = $request->getQueryParams();
            $respuesta['estado'] = false;
            
            try{                    
                $auth = new Authentication();

                $usuario = $auth->Valida_Sesion($authorization[0]);
                
                if ($usuario['estado']){                                                
                    $id_usuario = $usuario['usuario']['id_usuario'];

                    $mysql = new Database(CRM);
                    $funciones = new Funciones();

                    $contador = 0;

                    // Buscar cotizaciones

                    // Buscar abonos

                    // Buscar Contratos

                    // Separar para mostrar por tipo de documento
                    $respuesta['consulta'] = array(
                        "cotizaciones" => [],
                        "abonos" => [],
                        "contratos" => []
                    );

                    $respuesta['resultados'] = (int) $contador;
                    
                    $respuesta['estado'] = true;

                    sleep(5);
                }else{
                    $respuesta['error'] = $usuario['error'];
                }
            }catch(PDOException $e){
                $respuesta['error'] = $e->getMessage();
            }

            $newResponse = $response->withJson($respuesta);
        
            return $newResponse;
        });

        // Clientes
        $app->get("/clientes", function(Request $request, Response $response){
            $authorization = $request->getHeader('Authorization');
            $params = $request->getQueryParams();
            $respuesta['estado'] = false;
            
            try{                    
                $auth = new Authentication();

                $usuario = $auth->Valida_Sesion($authorization[0]);
                
                if ($usuario['estado']){                                                
                    $id_usuario = $usuario['usuario']['id_usuario'];

                    $mysql = new Database(CRM);
                    $buscarEstados = new Estados();
                  
                    $buscador = "";
                    if ((isset($params['buscador'])) && (!empty($params['buscador']))){
                        $buscador = $params['buscador'];
                    }

                    $filtro = "";
                    if (isset($params['filtro'])){
                        switch (strtoupper($params['filtro'])) {
                            case 'IDCLIENTE':
                                $filtro = "C.cli_codigo LIKE '%".$buscador."%'";
                                break;
                            case 'DOCUMENTO':
                                $filtro = "C.cli_cedula LIKE '%".$buscador."%'";
                                break;
                            case 'APELLIDOS':
                                $filtro = "C.cli_apellido LIKE '%".$buscador."%'";
                                break;
                            case 'NOMBRES':
                                $filtro = "C.cli_nombre LIKE '%".$buscador."%'";
                                break;
                            case 'CELULAR':
                                $filtro = "C.cli_celular LIKE '%".$buscador."%'";
                                break;
                            case 'CORREO':
                                $filtro = "C.cli_email LIKE '%".$buscador."%'";
                                break;
                            case 'ASESOR':
                                $filtro = "(U.usu_apellido LIKE '%".$buscador."%') OR (U.usu_nombre LIKE '%".$buscador."%')";
                                break;
                            default:
                                $filtro = "(C.cli_codigo LIKE '%".$buscador."%') OR (C.cli_apellido LIKE '%".$buscador."%') OR (C.cli_nombre LIKE '%".$buscador."%') OR (C.cli_cedula LIKE '%".$buscador."%') OR (U.usu_apellido LIKE '%".$buscador."%') AND (U.usu_nombre LIKE '%".$buscador."%')
                                OR (C.cli_celular LIKE '%".$buscador."%') OR (C.cli_email LIKE '%".$buscador."%') OR (D.nombre LIKE '%".$buscador."%')";
                                break;
                        }
                    }
                
                    $limite = "";
                    if ((isset($params['limite'])) && (!empty($params['limite']))){
                        $limite = "LIMIT ".$params['limite'];
                    }

                    $consulta = $mysql->Consulta("SELECT
                    C.cli_codigo, C.cli_pais, P.nombre AS pais, C.cli_provincia, V.nombre AS provincia, C.cli_ciudad, D.nombre AS ciudad,
                    C.cli_vendedor, CONCAT(U.usu_apellido,' ',U.usu_nombre) AS vendedor, CONCAT(C.cli_apellido,' ',C.cli_nombre) AS nombresCliente,
                    C.cli_cedula, C.cli_telefono1, C.cli_telefono2, C.cli_celular, C.cli_email, C.cli_sector, C.cli_direccion, C.cli_referencia_dom,
                    C.cli_fecha_ingreso, C.cli_observaciones, C.cli_estado, C.cli_obs_auditoria
                    FROM mve_clientes C
                    LEFT JOIN mve_geo_paises P
                    ON C.cli_pais = P.id
                    LEFT JOIN mve_geo_provincias V
                    ON C.cli_provincia = V.id
                    LEFT JOIN mve_geo_ciudades D
                    ON C.cli_ciudad = D.id
                    LEFT JOIN mve_usuarios U
                    ON C.cli_vendedor = U.usu_codigo
                    WHERE (".$filtro.")
                    ORDER BY C.cli_codigo DESC ".$limite);

                    $listaClientes = [];
                    if (is_array($consulta)){
                        if (count($consulta) > 0){
                            foreach ($consulta as $linea) {
                                $nombresAsesor = "SIN ESPECIFICAR";
                                if (!is_null($linea['vendedor'])){
                                    $nombresAsesor = $linea['vendedor'];
                                }

                                $celularBloqueado = substr($linea['cli_celular'], 0, 2)."XXXXXXXX";
                                $telefono1Bloqueado = substr($linea['cli_telefono1'], 0, 2)."XXXXXXXX";
                                $telefono2Bloqueado = substr($linea['cli_telefono2'], 0, 2)."XXXXXXXX";
                                array_push($listaClientes, array(
                                    "idCliente" => (int) $linea['cli_codigo'],
                                    "ubicacion" => array(
                                        "idPais" => (int) $linea['cli_pais'],
                                        "idProvincia" => (int) $linea['cli_provincia'],
                                        "idCiudad" => (int) $linea['cli_ciudad'],
                                        "pais" => $linea['pais'],
                                        "provincia" => $linea['provincia'],
                                        "ciudad" => $linea['ciudad'],
                                        "direccion" => $linea['cli_direccion'],
                                        "sector" => $linea['cli_sector'],
                                        "referencia" => $linea['cli_referencia_dom']
                                    ),
                                    "asesor" => array(
                                        "id" => (int) $linea['cli_vendedor'],
                                        "nombresCompletos" => $nombresAsesor
                                    ),
                                    "cliente" => array(
                                        "documento" => $linea['cli_cedula'],
                                        "nombresCompletos" => $linea['nombresCliente']
                                    ),
                                    "telefono1" => $linea['cli_telefono1'],
                                    "telefono2" => $linea['cli_telefono2'],
                                    "celular" => $linea['cli_celular'],
                                    "celularBloqueado" => $celularBloqueado,
                                    "telefono1Bloqueado" => $telefono1Bloqueado,
                                    "telefono2Bloqueado" => $telefono2Bloqueado,
                                    "email" => $linea['cli_email'],
                                    "fechaIngreso" => $linea['cli_fecha_ingreso'],
                                    "observaciones" => $linea['cli_observaciones'],
                                    "observacionesAuditoria" => $linea['cli_obs_auditoria'],
                                    "estado" => $buscarEstados->estadoCliente($linea['cli_estado']),
                                ));
                            }
                        }
                    }

                    $respuesta['consulta'] = $listaClientes;
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

        $app->get("/clientes/{idCliente}", function(Request $request, Response $response){
            $authorization = $request->getHeader('Authorization');
            $idCliente = $request->getAttribute('idCliente');
            $respuesta['estado'] = false;
            
            try{                    
                $auth = new Authentication();

                $usuario = $auth->Valida_Sesion($authorization[0]);
                
                if ($usuario['estado']){                                                
                    $id_usuario = $usuario['usuario']['id_usuario'];

                    $mysql = new Database(CRM);
                    $buscarEstados = new Estados();

                    $consulta = $mysql->Consulta_Unico("SELECT
                    C.cli_codigo, C.cli_pais, P.nombre AS pais, C.cli_provincia, V.nombre AS provincia, C.cli_ciudad, D.nombre AS ciudad,
                    C.cli_vendedor, CONCAT(U.usu_apellido,' ',U.usu_nombre) AS vendedor, CONCAT(C.cli_apellido,' ',C.cli_nombre) AS nombresCliente, C.cli_apellido, C.cli_nombre,
                    C.cli_cedula, C.cli_telefono1, C.cli_telefono2, C.cli_celular, C.cli_email, C.cli_sector, C.cli_direccion, C.cli_referencia_dom,
                    C.cli_fecha_ingreso, C.cli_observaciones, C.cli_estado, C.cli_obs_auditoria, C.cli_tipo_id
                    FROM mve_clientes C
                    LEFT JOIN mve_geo_paises P
                    ON C.cli_pais = P.id
                    LEFT JOIN mve_geo_provincias V
                    ON C.cli_provincia = V.id
                    LEFT JOIN mve_geo_ciudades D
                    ON C.cli_ciudad = D.id
                    LEFT JOIN mve_usuarios U
                    ON C.cli_vendedor = U.usu_codigo
                    WHERE C.cli_codigo=".$idCliente);

                    if (isset($consulta['cli_codigo'])){

                        // COTIZACIONES

                        $cotizaciones = $mysql->Consulta("SELECT
                        C.cot_codigo, C.cot_cliente, L.cli_cedula, L.cli_apellido, L.cli_nombre, CONCAT(L.cli_apellido,' ',L.cli_nombre) AS nombreCliente, 
                        C.cot_usuario, U.usu_apellido, U.usu_nombre, CONCAT(U.usu_apellido,' ',U.usu_nombre) AS nombreUsuario, C.cot_numero, C.cot_valor_cobrar, C.cot_valor_saldo,
                        C.cot_usuario_autho, K.usu_apellido, K.usu_nombre, CONCAT(K.usu_apellido,' ',K.usu_nombre) AS nombreUsuarioAutoriza,
                        C.cot_fecha, C.cot_estado
                        FROM mve_cotizaciones C
                        LEFT JOIN mve_clientes L
                        ON C.cot_cliente = L.cli_codigo
                        LEFT JOIN mve_usuarios U
                        ON C.cot_usuario = U.usu_codigo
                        LEFT JOIN mve_usuarios K
                        ON C.cot_usuario_autho = K.usu_codigo
                        WHERE (C.cot_cliente = ".$idCliente.") ORDER BY C.cot_fecha");

                        $listaCotizaciones = [];
                        if (is_array($cotizaciones)){
                            if (count($cotizaciones) > 0){
                                foreach ($cotizaciones as $linea) {
                                    array_push($listaCotizaciones, array(
                                        "id" => (int) $linea['cot_codigo'],
                                        "numero" => $linea['cot_numero'],
                                        "fecha" => $linea['cot_fecha'],
                                        "total" => (float) $linea['cot_valor_cobrar'],
                                        "saldo" => (float) $linea['cot_valor_saldo'],
                                        "asesor" => $linea['nombreUsuario'],
                                        "estado" => $buscarEstados->estadoCotizacion($linea['cot_estado']),
                                    ));
                                }
                            }
                        }

                        // ABONOS

                        $abonos = $mysql->Consulta("SELECT
                        A.abo_codigo, A.abo_cliente, C.cli_cedula, C.cli_apellido, C.cli_nombre, CONCAT(C.cli_apellido,' ',C.cli_nombre) AS nombresCliente,
                        A.abo_cotizacion, O.cot_numero, A.abo_usuario, U.usu_apellido, U.usu_nombre, CONCAT(U.usu_apellido,' ',U.usu_nombre) AS asesor,
                        A.abo_concepto, A.abo_fecha, A.abo_hora, A.abo_forma_pago, T.tip_nombre, A.abo_banco, D.tid_nombre, A.abo_tipo_cheque, A.abo_numero_ch_tar_ret AS meses,
                        A.abo_numero_cuenta_bco AS tipoDiferido, A.abo_lote, A.abo_referencia, A.abo_plazo, A.abo_fecha_vencimiento, A.abo_valor, A.abo_factura, A.abo_fecha_factura,
                        A.abo_file, A.abo_file_autho, A.abo_fecha_autorizacion, A.abo_estado, A.abo_comision,CONCAT(Y.usu_apellido,' ',Y.usu_nombre) AS auditor
                        FROM mve_abonos A
                        LEFT JOIN mve_clientes C
                        ON A.abo_cliente = C.cli_codigo
                        LEFT JOIN mve_cotizaciones O
                        ON A.abo_cotizacion = O.cot_codigo
                        LEFT JOIN gen_tipo T
                        ON A.abo_forma_pago = T.tip_codigo
                        LEFT JOIN gen_tipo_detalle D
                        ON A.abo_banco = D.tid_codigo
                        LEFT JOIN mve_cotizaciones M
                        ON A.abo_cotizacion = M.cot_codigo
                        LEFT JOIN mve_usuarios U
                        ON M.cot_usuario = U.usu_codigo
                        LEFT JOIN mve_usuarios Y
                        ON A.abo_usuario_autorizacion = Y.usu_codigo
                        WHERE A.abo_cliente=".$idCliente." ORDER BY A.abo_fecha DESC");

                        $listadoAbonos = [];
                        if (is_array($abonos)){
                            if (count($abonos) > 0){
                                foreach ($abonos as $linea) {

                                    $separa = explode("/", $linea['abo_file_autho']);
                                    $imagenAutho = "";
                                    if ($separa[(count($separa) - 1)] != "img"){
                                        $imagenAutho = "https://datacrm.mvevip.com/sales/img/".$separa[(count($separa) - 1)];
                                    }
                    
                                    $factura = "S.D.";
                                    $fechaEmisionFactura = "S.D.";
                                    if (!is_null($linea['abo_factura'])){
                                        $factura = $linea['abo_factura'];
                                    }
                                    if (!is_null($linea['abo_fecha_factura'])){
                                        $fechaEmisionFactura = $linea['abo_fecha_factura'];
                                    }
                                    $auditor = "S.D.";
                                    if (!is_null($linea['auditor'])){
                                        $auditor = $linea['auditor'];
                                    }

                                    array_push($listadoAbonos,array(
                                        "codigo" => (int) $linea['abo_codigo'],
                                        "cliente" => array(
                                            "id" => (int) $linea['abo_cliente'],
                                            "documento" => $linea['cli_cedula'],
                                            "apellidos" => $linea['cli_apellido'],
                                            "nombres" => $linea['cli_nombre'],
                                            "nombresCompletos" => $linea['nombresCliente']
                                        ),
                                        "cotizacion" => array(
                                            "id" => (int) $linea['abo_cotizacion'],
                                            "numero" => $linea['cot_numero']
                                        ),
                                        "asesor" => array(
                                            "id" => (int) $linea['abo_usuario'],
                                            "apellidos" => $linea['usu_apellido'],
                                            "nombres" => $linea['usu_nombre'],
                                            "nombresCompletos" => $linea['asesor']
                                        ),
                                        "auditor" => $auditor,
                                        "concepto" => $linea['abo_concepto'],
                                        "lote" => $linea['abo_lote'],
                                        "referencia" => $linea['abo_referencia'],
                                        "valor" => (float) $linea['abo_valor'],
                                        "comision" => (float) $linea['abo_comision'],
                                        "fecha" => $linea['abo_fecha'],
                                        "hora" => $linea['abo_hora'],
                                        "formaPago" => array(
                                            "id" => (int) $linea['abo_forma_pago'],
                                            "descripcion" => $linea['tip_nombre']
                                        ),
                                        "banco" => array(
                                            "id" => (int) $linea['abo_banco'],
                                            "descripcion" => $linea['tid_nombre']
                                        ),
                                        "tipo" => $linea['abo_tipo_cheque'],
                                        "meses" => (int) $linea['meses'],
                                        "tipoDiferido" => $linea['tipoDiferido'],
                                        "plazo" => (int) $linea['abo_plazo'],
                                        "fechaVencimiento" => $linea['abo_fecha_vencimiento'],
                                        "factura" => array(
                                            "comprobante" => $factura,
                                            "fechaEmision" => $fechaEmisionFactura
                                        ),
                                        "respaldos" => array(
                                            "file" => $linea['abo_file'],
                                            "autorizacion" => $linea['abo_file_autho'],
                                            "linkAutorizacion" => $imagenAutho
                                        ),
                                        "estado" => $buscarEstados->estadoAbono($linea['abo_estado'])
                                    ));
                                }
                            }
                        }

                        // INFORMACION DEL CLIENTE

                        $nombresAsesor = "SIN ESPECIFICAR";
                        if (!is_null($consulta['vendedor'])){
                            $nombresAsesor = $consulta['vendedor'];
                        }

                        $celularBloqueado = substr($consulta['cli_celular'], 0, 2)."XXXXXXXX";
                        $telefono1Bloqueado = substr($consulta['cli_telefono1'], 0, 2)."XXXXXXXX";
                        $telefono2Bloqueado = substr($consulta['cli_telefono2'], 0, 2)."XXXXXXXX";

                        $respuesta['consulta'] = array(
                            "idCliente" => (int) $consulta['cli_codigo'],
                            "ubicacion" => array(
                                "idPais" => (int) $consulta['cli_pais'],
                                "idProvincia" => (int) $consulta['cli_provincia'],
                                "idCiudad" => (int) $consulta['cli_ciudad'],
                                "pais" => $consulta['pais'],
                                "provincia" => $consulta['provincia'],
                                "ciudad" => $consulta['ciudad'],
                                "direccion" => $consulta['cli_direccion'],
                                "sector" => $consulta['cli_sector'],
                                "referencia" => $consulta['cli_referencia_dom']
                            ),
                            "asesor" => array(
                                "id" => (int) $consulta['cli_vendedor'],
                                "nombresCompletos" => $nombresAsesor
                            ),
                            "cliente" => array(
                                "tipoDocumento" => (int) $consulta['cli_tipo_id'],
                                "documento" => $consulta['cli_cedula'],
                                "nombresCompletos" => $consulta['nombresCliente'],
                                "apellidos" => $consulta['cli_apellido'],
                                "nombres" => $consulta['cli_nombre']
                            ),
                            "telefono1" => $consulta['cli_telefono1'],
                            "telefono2" => $consulta['cli_telefono2'],
                            "celular" => $consulta['cli_celular'],
                            "celularBloqueado" => $celularBloqueado,
                            "telefono1Bloqueado" => $telefono1Bloqueado,
                            "telefono2Bloqueado" => $telefono2Bloqueado,
                            "email" => $consulta['cli_email'],
                            "fechaIngreso" => $consulta['cli_fecha_ingreso'],
                            "observaciones" => $consulta['cli_observaciones'],
                            "observacionesAuditoria" => $consulta['cli_obs_auditoria'],
                            "estado" => $buscarEstados->estadoCliente($consulta['cli_estado']),
                            "cotizaciones" => $listaCotizaciones,
                            "abonos" => $listadoAbonos
                        );

                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No se encuentra información del cliente.";
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

        // Asesor
        $app->get("/asesor/{idAsesor}", function(Request $request, Response $response){
            $authorization = $request->getHeader('Authorization');
            $idAsesor = $request->getAttribute('idAsesor');
            $respuesta['estado'] = false;
            
            try{                    
                $auth = new Authentication();

                $usuario = $auth->Valida_Sesion($authorization[0]);
                
                if ($usuario['estado']){                                                
                    $id_usuario = $usuario['usuario']['id_usuario'];

                    $mysql = new Database(CRM);
                    $buscarEstados = new Estados();

                    $consulta = $mysql->Consulta_Unico("SELECT * FROM mve_usuarios WHERE usu_codigo=".$idAsesor);

                    if (isset($consulta['usu_codigo'])){
                        $nombreAsesor = $consulta['usu_apellido']." ".$consulta['usu_nombre'];
                        $estadoAsesor = $buscarEstados->estadoAsesor($consulta['usu_estado']);

                        // COTIZACIONES

                        $cotizaciones = $mysql->Consulta("SELECT
                        C.cot_codigo, C.cot_cliente, L.cli_cedula, L.cli_apellido, L.cli_nombre, CONCAT(L.cli_apellido,' ',L.cli_nombre) AS nombreCliente, 
                        C.cot_usuario, U.usu_apellido, U.usu_nombre, CONCAT(U.usu_apellido,' ',U.usu_nombre) AS nombreUsuario, C.cot_numero, C.cot_valor_cobrar, C.cot_valor_saldo,
                        C.cot_usuario_autho, K.usu_apellido, K.usu_nombre, CONCAT(K.usu_apellido,' ',K.usu_nombre) AS nombreUsuarioAutoriza,
                        C.cot_fecha, C.cot_estado
                        FROM mve_cotizaciones C
                        LEFT JOIN mve_clientes L
                        ON C.cot_cliente = L.cli_codigo
                        LEFT JOIN mve_usuarios U
                        ON C.cot_usuario = U.usu_codigo
                        LEFT JOIN mve_usuarios K
                        ON C.cot_usuario_autho = K.usu_codigo
                        WHERE (C.cot_usuario = ".$idAsesor.") ORDER BY C.cot_fecha DESC LIMIT 100");

                        $listaCotizaciones = [];
                        if (is_array($cotizaciones)){
                            if (count($cotizaciones) > 0){
                                foreach ($cotizaciones as $linea) {
                                    array_push($listaCotizaciones, array(
                                        "id" => (int) $linea['cot_codigo'],
                                        "numero" => $linea['cot_numero'],
                                        "fecha" => $linea['cot_fecha'],
                                        "total" => (float) $linea['cot_valor_cobrar'],
                                        "saldo" => (float) $linea['cot_valor_saldo'],
                                        "asesor" => $linea['nombreUsuario'],
                                        "estado" => $buscarEstados->estadoCotizacion($linea['cot_estado']),
                                    ));
                                }
                            }
                        }

                        // ABONOS

                        $abonos = $mysql->Consulta("SELECT
                        A.abo_codigo, A.abo_cliente, C.cli_cedula, C.cli_apellido, C.cli_nombre, CONCAT(C.cli_apellido,' ',C.cli_nombre) AS nombresCliente,
                        A.abo_cotizacion, O.cot_numero, A.abo_usuario, U.usu_apellido, U.usu_nombre, CONCAT(U.usu_apellido,' ',U.usu_nombre) AS asesor,
                        A.abo_concepto, A.abo_fecha, A.abo_hora, A.abo_forma_pago, T.tip_nombre, A.abo_banco, D.tid_nombre, A.abo_tipo_cheque, A.abo_numero_ch_tar_ret AS meses,
                        A.abo_numero_cuenta_bco AS tipoDiferido, A.abo_lote, A.abo_referencia, A.abo_plazo, A.abo_fecha_vencimiento, A.abo_valor, A.abo_factura, A.abo_fecha_factura,
                        A.abo_file, A.abo_file_autho, A.abo_fecha_autorizacion, A.abo_estado, A.abo_comision,CONCAT(Y.usu_apellido,' ',Y.usu_nombre) AS auditor
                        FROM mve_abonos A
                        LEFT JOIN mve_clientes C
                        ON A.abo_cliente = C.cli_codigo
                        LEFT JOIN mve_cotizaciones O
                        ON A.abo_cotizacion = O.cot_codigo
                        LEFT JOIN gen_tipo T
                        ON A.abo_forma_pago = T.tip_codigo
                        LEFT JOIN gen_tipo_detalle D
                        ON A.abo_banco = D.tid_codigo
                        LEFT JOIN mve_cotizaciones M
                        ON A.abo_cotizacion = M.cot_codigo
                        LEFT JOIN mve_usuarios U
                        ON A.abo_usuario = U.usu_codigo
                        LEFT JOIN mve_usuarios Y
                        ON A.abo_usuario_autorizacion = Y.usu_codigo
                        WHERE A.abo_usuario=".$idAsesor." ORDER BY A.abo_fecha DESC");

                        $listadoAbonos = [];
                        if (is_array($abonos)){
                            if (count($abonos) > 0){
                                foreach ($abonos as $linea) {

                                    $separa = explode("/", $linea['abo_file_autho']);
                                    $imagenAutho = "";
                                    if ($separa[(count($separa) - 1)] != "img"){
                                        $imagenAutho = "https://datacrm.mvevip.com/sales/img/".$separa[(count($separa) - 1)];
                                    }
                    
                                    $factura = "S.D.";
                                    $fechaEmisionFactura = "S.D.";
                                    if (!is_null($linea['abo_factura'])){
                                        $factura = $linea['abo_factura'];
                                    }
                                    if (!is_null($linea['abo_fecha_factura'])){
                                        $fechaEmisionFactura = $linea['abo_fecha_factura'];
                                    }
                                    $auditor = "S.D.";
                                    if (!is_null($linea['auditor'])){
                                        $auditor = $linea['auditor'];
                                    }

                                    array_push($listadoAbonos,array(
                                        "codigo" => (int) $linea['abo_codigo'],
                                        "cliente" => array(
                                            "id" => (int) $linea['abo_cliente'],
                                            "documento" => $linea['cli_cedula'],
                                            "apellidos" => $linea['cli_apellido'],
                                            "nombres" => $linea['cli_nombre'],
                                            "nombresCompletos" => $linea['nombresCliente']
                                        ),
                                        "cotizacion" => array(
                                            "id" => (int) $linea['abo_cotizacion'],
                                            "numero" => $linea['cot_numero']
                                        ),
                                        "asesor" => array(
                                            "id" => (int) $linea['abo_usuario'],
                                            "apellidos" => $linea['usu_apellido'],
                                            "nombres" => $linea['usu_nombre'],
                                            "nombresCompletos" => $linea['asesor']
                                        ),
                                        "auditor" => $auditor,
                                        "concepto" => $linea['abo_concepto'],
                                        "lote" => $linea['abo_lote'],
                                        "referencia" => $linea['abo_referencia'],
                                        "valor" => (float) $linea['abo_valor'],
                                        "comision" => (float) $linea['abo_comision'],
                                        "fecha" => $linea['abo_fecha'],
                                        "hora" => $linea['abo_hora'],
                                        "formaPago" => array(
                                            "id" => (int) $linea['abo_forma_pago'],
                                            "descripcion" => $linea['tip_nombre']
                                        ),
                                        "banco" => array(
                                            "id" => (int) $linea['abo_banco'],
                                            "descripcion" => $linea['tid_nombre']
                                        ),
                                        "tipo" => $linea['abo_tipo_cheque'],
                                        "meses" => (int) $linea['meses'],
                                        "tipoDiferido" => $linea['tipoDiferido'],
                                        "plazo" => (int) $linea['abo_plazo'],
                                        "fechaVencimiento" => $linea['abo_fecha_vencimiento'],
                                        "factura" => array(
                                            "comprobante" => $factura,
                                            "fechaEmision" => $fechaEmisionFactura
                                        ),
                                        "respaldos" => array(
                                            "file" => $linea['abo_file'],
                                            "autorizacion" => $linea['abo_file_autho'],
                                            "linkAutorizacion" => $imagenAutho
                                        ),
                                        "estado" => $buscarEstados->estadoAbono($linea['abo_estado'])
                                    ));
                                }
                            }
                        }

                        $respuesta['consulta'] = array(
                            "nombres" => $nombreAsesor,
                            "estadoAsesor" => $estadoAsesor,
                            "cotizaciones" => $listaCotizaciones,
                            "abonos" => $listadoAbonos
                        );

                        sleep(1);
                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No se encuentra información del asesor.";
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

        // Cotizaciones
        $app->get("/cotizaciones", function(Request $request, Response $response){
            $authorization = $request->getHeader('Authorization');
            $params = $request->getQueryParams();
            $respuesta['estado'] = false;
            
            try{                    
                $auth = new Authentication();

                $usuario = $auth->Valida_Sesion($authorization[0]);
                
                if ($usuario['estado']){                                                
                    $id_usuario = $usuario['usuario']['id_usuario'];

                    $mysql = new Database(CRM);
                    $funciones = new Funciones();

                    $from = date("Y-m-01");
                    if (isset($params['from'])){
                        $from = $params['from'];
                    }

                    $to = date("Y-m-d");
                    if (isset($params['to'])){
                        $to = $params['to'];
                    }

                    $buscador = "";
                    if (isset($params['buscador'])){
                        $buscador = $params['buscador'];
                    }

                    $filtroEstados = "";
                    if ((isset($params['filtroEstados'])) && (!empty($params['filtroEstados']))){
                        $filtroEstados = " AND (C.cot_estado=".$params['filtroEstados'].")";
                    }

                    $consulta = $mysql->Consulta("SELECT
                    C.cot_codigo, C.cot_cliente, L.cli_cedula, L.cli_apellido, L.cli_nombre, CONCAT(L.cli_apellido,' ',L.cli_nombre) AS nombreCliente, 
                    C.cot_usuario, U.usu_apellido, U.usu_nombre, CONCAT(U.usu_apellido,' ',U.usu_nombre) AS nombreUsuario, C.cot_numero, C.cot_valor_cobrar, C.cot_valor_saldo,
                    C.cot_usuario_autho, K.usu_apellido, K.usu_nombre, CONCAT(K.usu_apellido,' ',K.usu_nombre) AS nombreUsuarioAutoriza,
                    C.cot_fecha, C.cot_estado
                    FROM mve_cotizaciones C
                    LEFT JOIN mve_clientes L
                    ON C.cot_cliente = L.cli_codigo
                    LEFT JOIN mve_usuarios U
                    ON C.cot_usuario = U.usu_codigo
                    LEFT JOIN mve_usuarios K
                    ON C.cot_usuario_autho = K.usu_codigo
                    WHERE
                    (CAST(C.cot_fecha AS DATE) BETWEEN '".$from."' AND '".$to."') ".$filtroEstados." AND ((C.cot_codigo LIKE '%".$buscador."%') OR (C.cot_numero LIKE '%".$buscador."%') OR (L.cli_codigo LIKE '%".$buscador."%') OR (L.cli_cedula LIKE '%".$buscador."%') OR (L.cli_apellido LIKE '%".$buscador."%') OR (L.cli_nombre LIKE '%".$buscador."%') OR (U.usu_apellido LIKE '%".$buscador."%') OR (U.usu_nombre LIKE '%".$buscador."%'))
                    ORDER BY
                    C.cot_codigo DESC");

                    $listaCotizaciones = [];
                    if (is_array($consulta)){
                        if (count($consulta) > 0){
                            foreach ($consulta as $linea) {
                                $infoEstado = $funciones->obtenerEstadoCotizacion($linea['cot_estado']);

                                $informacionEstado = [];
                                if ($infoEstado['estado']){
                                    $informacionEstado = array(
                                        "valor" => (int) $linea['cot_estado'],
                                        "descripcion" => $infoEstado['descripcion'],
                                        "color" => $infoEstado['color']
                                    );
                                }
                                
                                array_push($listaCotizaciones, array(
                                    "codigo" => (int) $linea['cot_codigo'],
                                    "cotizacion" => $linea['cot_numero'],
                                    "cliente" => array(
                                        "id" => (int) $linea['cot_cliente'],
                                        "documento" => $linea['cli_cedula'],
                                        "apellido" => $linea['cli_apellido'],
                                        "nombre" => $linea['cli_nombre'],
                                        "nombresCompletos" => $linea['nombreCliente'],
                                    ),
                                    "usuario" => array(
                                        "id" => (int) $linea['cot_usuario'],
                                        "apellido" => $linea['usu_apellido'],
                                        "nombre" => $linea['usu_nombre'],
                                        "nombresCompletos" => $linea['nombreUsuario'],
                                    ),
                                    "valor" => (float) $linea['cot_valor_cobrar'],
                                    "saldo" => (float) $linea['cot_valor_saldo'],
                                    "fecha" => $linea['cot_fecha'],
                                    "estado" => $informacionEstado
                                ));
                            }
                        }
                    }

                    $respuesta['consulta'] = $listaCotizaciones;
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

        $app->get("/cotizaciones/{idCotizacion}", function(Request $request, Response $response){
            $authorization = $request->getHeader('Authorization');
            $idCotizacion = $request->getAttribute('idCotizacion');
            $respuesta['estado'] = false;
            
            try{                    
                $auth = new Authentication();

                $usuario = $auth->Valida_Sesion($authorization[0]);
                
                if ($usuario['estado']){                                                
                    $id_usuario = $usuario['usuario']['id_usuario'];

                    $mysql = new Database(CRM);
                    $funciones = new Funciones();

                    $consulta = $mysql->Consulta_Unico("SELECT
                    C.cot_codigo, C.cot_cliente, L.cli_cedula, L.cli_apellido, L.cli_nombre, CONCAT(L.cli_apellido,' ',L.cli_nombre) AS nombreCliente, L.cli_celular, L.cli_telefono1, L.cli_telefono2,
                    L.cli_direccion, L.cli_sector, L.cli_tipo_id, L.cli_email,
                    C.cot_usuario, U.usu_apellido, U.usu_nombre, CONCAT(U.usu_apellido,' ',U.usu_nombre) AS nombreUsuario, C.cot_numero, C.cot_valor_cobrar, C.cot_valor_saldo,
                    C.cot_usuario_autho, K.usu_apellido, K.usu_nombre, CONCAT(K.usu_apellido,' ',K.usu_nombre) AS nombreUsuarioAutoriza,
                    C.cot_fecha, C.cot_estado
                    FROM mve_cotizaciones C
                    LEFT JOIN mve_clientes L
                    ON C.cot_cliente = L.cli_codigo
                    LEFT JOIN mve_usuarios U
                    ON C.cot_usuario = U.usu_codigo
                    LEFT JOIN mve_usuarios K
                    ON C.cot_usuario_autho = K.usu_codigo
                    WHERE (C.cot_codigo=".$idCotizacion.")");

                    if (isset($consulta['cot_codigo'])){
                        
                        $infoEstado = $funciones->obtenerEstadoCotizacion($consulta['cot_estado']);

                        $informacionEstado = [];
                        if ($infoEstado['estado']){
                            $informacionEstado = array(
                                "valor" => (int) $consulta['cot_estado'],
                                "descripcion" => $infoEstado['descripcion'],
                                "color" => $infoEstado['color']
                            );
                        }

                        $detalle = $mysql->Consulta("SELECT 
                        D.cod_codigo, D.cod_num_coti, D.cod_paquete, D.cod_subpaquete, D.cod_cantidad, D.cod_descripcion, D.cod_precio, D.cod_total_linea,
                        M.cmb_codigo, M.cmb_descripcion, M.img_iva 
                        FROM mve_cotizaciones_det D
                        LEFT JOIN mve_cotizaciones C
                        ON D.cod_num_coti = C.cot_numero
                        LEFT JOIN mve_combo M
                        ON D.cod_subpaquete = M.cmb_codigo
                        WHERE
                        (C.cot_codigo = ".$consulta['cot_codigo'].")");

                        $listaDetalle = [];
                        if (is_array($detalle)){
                            if (count($detalle) > 0){
                                foreach ($detalle as $linea) {

                                    $descripcionCompleta = $linea['cod_descripcion'];
                                    if (!empty($linea['cmb_descripcion'])){
                                        $descripcionCompleta .= " | ".$linea['cmb_descripcion'];
                                    }
                                    array_push($listaDetalle, array(
                                        "codigo" => (int) $linea['cod_codigo'],
                                        "paquete" => (int) $linea['cod_paquete'],
                                        "subpaquete" => (int) $linea['cod_subpaquete'], 
                                        "cantidad" => (int) $linea['cod_cantidad'],
                                        "descripcion" => $linea['cod_descripcion'],
                                        "descripcionCompleta" => $descripcionCompleta,
                                        "precio" => (float) $linea['cod_precio'],
                                        "total" => (float) $linea['cod_total_linea'],
                                        "iva" => (int) $linea['img_iva'],
                                        "combo" => array(
                                            "codigo" => (int) $linea['cmb_codigo'],
                                            "descripcion" => $linea['cmb_descripcion']
                                        )
                                    ));
                                }
                            }
                        }

                        $listaPagos = [];
                        $abonos = $mysql->Consulta("SELECT
                        A.abo_codigo, A.abo_concepto, A.abo_fecha, A.abo_hora, A.abo_forma_pago, D.tid_nombre, T.tip_nombre, A.abo_tipo_cheque, A.abo_numero_ch_tar_ret, A.abo_numero_cuenta_bco,
                        A.abo_lote, A.abo_referencia, A.abo_meses, A.abo_plazo, A.abo_fecha_vencimiento, A.abo_valor, A.abo_numero_factura, A.abo_file, A.abo_file_autho, 
                        A.abo_fecha_autorizacion, A.abo_estado
                        FROM mve_abonos A
                        LEFT JOIN gen_tipo_detalle D
                        ON A.abo_banco = D.tid_codigo
                        LEFT JOIN gen_tipo T
                        ON D.tip_codigo = T.tip_codigo
                        WHERE (A.abo_cotizacion = ".$idCotizacion.")");

                        if (is_array($abonos)){
                            if (count($abonos) > 0){
                                foreach ($abonos as $linea) {

                                    $infoEstado = $funciones->obtenerEstadoAbono($linea['abo_estado']);

                                    $informacionEstadoAbono = [];
                                    if ($infoEstado['estado']){
                                        $informacionEstadoAbono = array(
                                            "valor" => (int) $linea['abo_estado'],
                                            "descripcion" => $infoEstado['descripcion'],
                                            "color" => $infoEstado['color']
                                        );
                                    }

                                    $factura = "";
                                    if ((isset($linea['abo_numero_factura'])) && (!empty($linea['abo_numero_factura']))){
                                        $factura = $linea['abo_numero_factura'];
                                    }

                                    $imagen = "";
                                    if ((isset($linea['abo_file'])) && (!empty($linea['abo_file']))){
                                        $file = $linea['abo_file'];
                                        $separa = explode("/", $file);

                                        $nombre = $separa[(count($separa) -1)];
                                        $imagen = "https://datacrm.mvevip.com/sales/img/".$nombre;
                                    }
                                    

                                    array_push($listaPagos, array(
                                        "codigo" => (int) $linea['abo_codigo'],
                                        "concepto" => $linea['abo_concepto'],
                                        "fecha" => $linea['abo_fecha'],
                                        "hora" => $linea['abo_hora'],
                                        "pago" => array(
                                            "id" => (int) $linea['abo_forma_pago'],
                                            "institucion" => $linea['tid_nombre'],
                                            "tipo" => $linea['tip_nombre'],
                                            "tipoCheque" => $linea['abo_tipo_cheque'],
                                            "numeroComprobante" => $linea['abo_numero_ch_tar_ret'],
                                            "cuenta" => $linea['abo_numero_cuenta_bco'],
                                            "lote" => $linea['abo_lote'],
                                            "referencia" => $linea['abo_referencia'],
                                            "meses" => $linea['abo_meses'],
                                            "plazo" => $linea['abo_plazo'],
                                            "fechaVencimiento" => $linea['abo_fecha_vencimiento'],
                                            "valor" => (float) $linea['abo_valor'],
                                        ),
                                        "factura" => $factura,
                                        "imagen" => $imagen,
                                        "estado" => $informacionEstadoAbono
                                    ));
                                }
                            }
                        }

                        if (count($listaDetalle) > 0){
                            $respuesta['consulta'] = array(
                                "codigo" => (int) $consulta['cot_codigo'],
                                "cotizacion" => $consulta['cot_numero'],
                                "cliente" => array(
                                    "id" => (int) $consulta['cot_cliente'],
                                    "documento" => $consulta['cli_cedula'],
                                    "apellido" => $consulta['cli_apellido'],
                                    "nombre" => $consulta['cli_nombre'],
                                    "nombresCompletos" => $consulta['nombreCliente'],
                                ),
                                "usuario" => array(
                                    "id" => (int) $consulta['cot_usuario'],
                                    "apellido" => $consulta['usu_apellido'],
                                    "nombre" => $consulta['usu_nombre'],
                                    "nombresCompletos" => $consulta['nombreUsuario'],
                                ),
                                "detalle" => $listaDetalle,
                                "valor" => (float) $consulta['cot_valor_cobrar'],
                                "saldo" => (float) $consulta['cot_valor_saldo'],
                                "fecha" => $consulta['cot_fecha'],
                                "estado" => $informacionEstado,
                                "abonos" => $listaPagos
                            );

                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = "La cotización no tiene ningún detalle registrado.";
                        }

                    }else{
                        $respuesta['error'] = "No se encuentra información de la cotización.";
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

        $app->put("/cotizaciones/{idCotizacion}", function(Request $request, Response $response){
            $authorization = $request->getHeader('Authorization');
            $idCotizacion = $request->getAttribute('idCotizacion');
            $data = $request->getParsedBody();
            $respuesta['estado'] = false;
            
            try{                    
                $auth = new Authentication();

                $usuario = $auth->Valida_Sesion($authorization[0]);
                
                if ($usuario['estado']){                                                
                    $id_usuario = $usuario['usuario']['id_usuario'];

                    $mysql = new Database(CRM);
                    $funciones = new Funciones();

                    $consulta = $mysql->Consulta_Unico("SELECT cot_codigo FROM mve_cotizaciones WHERE (cot_codigo=".$idCotizacion.")");

                    if (isset($consulta['cot_codigo'])){
                        
                        $detalle = $data['detalle'];

                        $total = 0;
                        if (is_array($detalle)){
                            if (count($detalle) > 0){
                                foreach ($detalle as $linea) {
                                    $codigo = $linea['codigo'];
                                    $cod_cantidad = $linea['cantidad'];
                                    $cod_precio = $linea['precio'];
                                    $cod_total_linea = $linea['total'];
                                    $fecha_modificacion = date("Y-m-d H:i:s");
                                    $cod_usuario_modifica = $id_usuario;
                                    $cod_estado = 1;

                                    $modificarPago = $mysql->Modificar("UPDATE mve_cotizaciones_det SET cod_cantidad=?, cod_precio=?, cod_total_linea=?, cod_fecha_modifica=?, cod_usuario_modifica=?, cod_estado=? WHERE cod_codigo=?", array($cod_cantidad, $cod_precio, $cod_total_linea, $fecha_modificacion, $cod_usuario_modifica, $cod_estado, $codigo));

                                    $total += $cod_total_linea;
                                }
                            }
                        }

                        $codEstado = 2;

                        $modifica = $mysql->Modificar("UPDATE mve_cotizaciones SET cot_valor_cobrar=?, cot_valor_saldo=?, cot_usuario_autho=?, cot_estado=? WHERE cot_codigo=?", array($total, $total, $id_usuario, $codEstado, $idCotizacion));
                    
                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No se encuentra información de la cotización.";
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

        $app->delete("/cotizaciones/{idCotizacion}", function(Request $request, Response $response){
            $authorization = $request->getHeader('Authorization');
            $idCotizacion = $request->getAttribute('idCotizacion');
            $respuesta['estado'] = false;
            
            try{                    
                $auth = new Authentication();

                $usuario = $auth->Valida_Sesion($authorization[0]);
                
                if ($usuario['estado']){                                                
                    $id_usuario = $usuario['usuario']['id_usuario'];

                    $mysql = new Database(CRM);
                    $funciones = new Funciones();

                    $consulta = $mysql->Consulta_Unico("SELECT cot_codigo FROM mve_cotizaciones WHERE (cot_codigo=".$idCotizacion.")");

                    if (isset($consulta['cot_codigo'])){
                        $estadoAnulado = 4;
                        $modificar = $mysql->Modificar("UPDATE mve_cotizaciones SET cot_estado=? WHERE cot_codigo=?", array($estadoAnulado, $idCotizacion));
                
                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No se encuentra información de la cotización.";
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

        $app->post("/abonos/{idCotizacion}", function(Request $request, Response $response){
            $authorization = $request->getHeader('Authorization');
            $idCotizacion = $request->getAttribute('idCotizacion');
            $data = $request->getParsedBody();
            $files = $request->getUploadedFiles();
            $respuesta['estado'] = false;

            
            
            try{                    
                $auth = new Authentication();

                $usuario = $auth->Valida_Sesion($authorization[0]);
                
                if ($usuario['estado']){                                                
                    $id_usuario = $usuario['usuario']['id_usuario'];

                    $mysql = new Database(CRM);
                    sleep(4);
                    $respuesta['estado'] = true;

                    $respuesta['data'] = $data;
                    $respuesta['files'] = $files;
                 
                }else{
                    $respuesta['error'] = $usuario['error'];
                }
            }catch(PDOException $e){
                $respuesta['error'] = $e->getMessage();
            }

            $newResponse = $response->withJson($respuesta);
        
            return $newResponse;
        });

        // REGISTRO RAPIDO

        $app->get("/listaPersonas", function(Request $request, Response $response){
            $authorization = $request->getHeader('Authorization');
            $respuesta['estado'] = false;
            
            try{                    
                $auth = new Authentication();

                $usuario = $auth->Valida_Sesion($authorization[0]);
                
                if ($usuario['estado']){                                                
                    $id_usuario = $usuario['usuario']['id_usuario'];

                    $mysql = new Database(CRM);

                    $listados = [];
                    // $consulta = $mysql->Consulta("SELECT * FROM ");

                    $respuesta['consulta'] = $listados;
                    
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

        $app->post("/registroRapido", function(Request $request, Response $response){
            $authorization = $request->getHeader('Authorization');
            $data = $request->getParsedBody();
            $respuesta['estado'] = false;
            
            try{                    
                $auth = new Authentication();

                $usuario = $auth->Valida_Sesion($authorization[0]);
                
                if ($usuario['estado']){                                                
                    $id_usuario = $usuario['usuario']['id_usuario'];

                    $mysql = new Database(CRM);
                    $contratos = new Contratos();

                    $registroRapido = $contratos->registroRapido($data);

                    if ($registroRapido['estado']){

                        $respuesta['data'] = $registroRapido;
                        $respuesta['estado'] = true;
                        
                    }else{
                        $respuesta['error'] = $registroRapido['error'];
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

        // CONTRATOS

        $app->post("/contratos", function(Request $request, Response $response){
            $authorization = $request->getHeader('Authorization');
            $data = $request->getParsedBody();
            $files = $request->getUploadedFiles();
            $respuesta['estado'] = false;
            
            try{                    
                $auth = new Authentication();

                $usuario = $auth->Valida_Sesion($authorization[0]);
                
                if ($usuario['estado']){                                                
                    $id_usuario = $usuario['usuario']['id_usuario'];

                    $mysql = new Database(CRM);
                    $contratos = new Contratos();

                    $nuevoContrato = $contratos->contratoNuevo($data, $files);

                    if ($nuevoContrato['estado']){
                        $respuesta['contrato'] = $nuevoContrato;

                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = $nuevoContrato['error'];
                    }

                    sleep(4);

                   
                 
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

    // Route Group v1
    $app->group('/v1', function() use ($app) {

        $app->post("/auth", function(Request $request, Response $response) {
            $data = $request->getParsedBody();
            $respuesta['estado'] = false;
        
            try{                
                $mysql = new Database("vtgsa_ventas");

                if (((isset($data['username'])) && (!empty($data['username']))) && ((isset($data['password'])) && (!empty($data['password'])))){
                    $username = $data['username'];
                    $password = $data['password'];

                    $consulta = $mysql->Consulta_Unico("SELECT * FROM usuarios WHERE (correo='".$username."') AND (password='".$password."')");

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
                            // $respuesta['url'] = $consulta['path'];
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
                                        $modificar = $mysql->Modificar("UPDATE notas_registros SET ciudad=?, direccion=?, orden=?, observaciones=?, hora_prox_llamada=?, fecha_prox_llamada=?, fecha_ultima_contacto=?, estado=? WHERE id_lista=?", array($ciudad, $direccion, 1, $observaciones, $hora, $fecha, $fecha_ultima_contacto, $estado, $id_lista));

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
                                                    case 334: // CHACHA
                                                        $id_lider = 30;
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

                    $consulta = $mysql->Consulta("SELECT 
                    N.estado, COUNT(N.estado) AS total
                    FROM notas_registros N
                    WHERE (N.banco=23)
                    GROUP BY N.estado
                    ORDER BY COUNT(N.asignado) DESC");

                    $detalle = [];
                    if (is_array($consulta)){
                        if (count($consulta) > 0){
                            foreach ($consulta as $linea) {
                                array_push($detalle, array(
                                    "id" => (int) $linea['estado'],
                                    "total" => (int) $linea['total'],
                                    "estado" => $funciones->Obtener_Estado($linea['estado'])
                                ));
                            }
                        }
                    }



                    $respuesta['consulta'] = $detalle;
                    
                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
                
                return $newResponse;
            });
        });

        $app->group('/cotizaciones', function() use ($app) {
            
            $app->get("/mis_cotizaciones", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
                
                try{                    
                    $auth = new Authentication();

                    $usuario = $auth->Valida_Sesion($authorization[0]);
                    
                    if ($usuario['estado']){                                                
                        $id_usuario = $usuario['usuario']['id_usuario'];

                        $mysql = new Database("vtgsa_ventas");

                        $consulta = $mysql->Consulta("SELECT
                        C.id_cotizacion, C.id_asesor, U.nombres AS asesor, C.id_lider, L.lider AS lider, C.destino, C.fecha_ida, C.fecha_retorno, C.alojamiento, C.traslados,
                        C.alimentacion, C.actividades, C.tickets, C.adultos, C.ninos, C.infantes, C.tercera_discapacitados, C.id_responsable, S.nombres AS responsable,
                        C.fecha_alta, C.fecha_asignacion, C.fecha_modificacion, C.fecha_caducidad, C.estado, C.correo, C.celular
                        FROM cotizaciones C
                        LEFT JOIN lideres L
                        ON C.id_lider=L.id_lider
                        LEFT JOIN usuarios U
                        ON C.id_asesor=U.id_usuario
                        LEFT JOIN usuarios S
                        ON C.id_responsable=S.id_usuario WHERE (C.estado!=1) ORDER BY C.fecha_caducidad ASC");

                        $filtrado_resultados = [];

                        if (is_array($consulta)){
                            if (count($consulta) > 0){
                                foreach ($consulta as $linea) {

                                    $descripcion_estado = "";
                                    $color_estado = "";
                                    switch ($linea['estado']) {
                                        case 0:
                                            $descripcion_estado = "Pendiente";
                                            $color_estado = "warning";
                                            break;
                                        case 1:
                                            $descripcion_estado = "Entregado";
                                            $color_estado = "success";
                                            break;
                                        case 2:
                                            $descripcion_estado = "Vencido";
                                            $color_estado = "dark";
                                            break;
                                    }

                                    $estilo = '';
                                    $fecha_actual = new DateTime(date("Y-m-d H:i:s"));
                                    $fecha_caducidad = new DateTime($linea['fecha_caducidad']);

                                    $diff = $fecha_actual->diff($fecha_caducidad);

                                    if ($diff->days == 1){
                                        $estilo = 'style="background: #F2AF5C; color: white;"';
                                    }else if ($diff->days == 0){
                                        $estilo = 'style="background: #F25C69; color: white;"';    
                                    }
                                    

                                    array_push($filtrado_resultados, array(
                                        "id_cotizacion" => (int) $linea['id_cotizacion'],
                                        "numeracion" => str_pad($linea['id_cotizacion'], 5, "0", STR_PAD_LEFT),
                                        "lider" => array(
                                            "id" => (int) $linea['id_lider'],
                                            "descripcion" => strtoupper($linea['lider'])
                                        ),
                                        "asesor" => array(
                                            "id" => (int) $linea['id_asesor'],
                                            "descripcion" => strtoupper($linea['asesor']),
                                            "celular" => $linea['celular'],
                                            "correo" => $linea['correo']
                                        ),
                                        "responsable" => array(
                                            "id" => (int) $linea['id_responsable'],
                                            "descripcion" => strtoupper($linea['responsable']),
                                            "fecha_asignacion" => $linea['fecha_asignacion']
                                        ),
                                        "destino" => $linea['destino'],
                                        "fechas" => array(
                                            "ida" => $linea['fecha_ida'],
                                            "retorno" => $linea['fecha_retorno']
                                        ),
                                        "opciones" => array(
                                            "alojamiento" => (int) $linea['alojamiento'],
                                            "traslados" => (int) $linea['traslados'],
                                            "alimentacion" => (int) $linea['alimentacion'],
                                            "actividades" => (int) $linea['actividades'],
                                            "tickets" => (int) $linea['tickets']
                                        ),
                                        "personas" => array(
                                            "adultos" => (int) $linea["adultos"],
                                            "ninos" => (int) $linea["ninos"],
                                            "infantes" => (int) $linea["infantes"],
                                            "tercera_discapacitados" => (int) $linea["tercera_discapacitados"],
                                        ),
                                        "fecha_alta" => $linea['fecha_alta'],
                                        "fecha_modificacion" => $linea['fecha_modificacion'],
                                        "fecha_caducidad" => $linea['fecha_caducidad'],
                                        "estado" => array(
                                            "descripcion" => $descripcion_estado,
                                            "valor" => (int) $linea['estado'],
                                            "color" => $color_estado
                                        ),
                                        "estilo" => $estilo,
                                        "mensaje" => "Faltan ".$diff->days." días"
                                    ));
                                }
                            }
                        }

                        $respuesta['consulta'] = $filtrado_resultados;
                       
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

            $app->get("/mis_cotizaciones/{id_cotizacion}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_cotizacion = $request->getAttribute('id_cotizacion');
                $respuesta['estado'] = false;
                
                try{                    
                    $auth = new Authentication();

                    $usuario = $auth->Valida_Sesion($authorization[0]);
                    
                    if ($usuario['estado']){                                                
                        $id_usuario = $usuario['usuario']['id_usuario'];

                        $mysql = new Database("vtgsa_ventas");

                        $consulta = $mysql->Consulta_Unico("SELECT
                        C.id_cotizacion, C.id_asesor, U.nombres AS asesor, C.id_lider, L.lider AS lider, C.destino, C.fecha_ida, C.fecha_retorno, C.alojamiento, C.traslados,
                        C.alimentacion, C.actividades, C.tickets, C.adultos, C.ninos, C.infantes, C.tercera_discapacitados, C.id_responsable, S.nombres AS responsable,
                        C.fecha_alta, C.fecha_asignacion, C.fecha_modificacion, C.fecha_caducidad, C.estado, C.correo, C.celular
                        FROM cotizaciones C
                        LEFT JOIN lideres L
                        ON C.id_lider=L.id_lider
                        LEFT JOIN usuarios U
                        ON C.id_asesor=U.id_usuario
                        LEFT JOIN usuarios S
                        ON C.id_responsable=S.id_usuario WHERE (C.id_cotizacion=".$id_cotizacion.") ORDER BY C.fecha_caducidad ASC");

                        $filtrado_resultados = [];

                        if (isset($consulta['id_cotizacion'])){
                            $descripcion_estado = "";
                            $color_estado = "";
                            switch ($consulta['estado']) {
                                case 0:
                                    $descripcion_estado = "Pendiente";
                                    $color_estado = "warning";
                                    break;
                                case 1:
                                    $descripcion_estado = "Entregado";
                                    $color_estado = "success";
                                    break;
                                case 2:
                                    $descripcion_estado = "Vencido";
                                    $color_estado = "dark";
                                    break;
                            }
                            $estilo = '';
                            $fecha_actual = new DateTime(date("Y-m-d H:i:s"));
                            $fecha_caducidad = new DateTime($consulta['fecha_caducidad']);

                            $diff = $fecha_actual->diff($fecha_caducidad);

                            if ($diff->days == 1){
                                $estilo = 'style="background: #F2AF5C; color: white;"';
                            }else if ($diff->days == 0){
                                $estilo = 'style="background: #F25C69; color: white;"';    
                            }

                            $filtrado_resultados = array(
                                "id_cotizacion" => (int) $consulta['id_cotizacion'],
                                "numeracion" => str_pad($consulta['id_cotizacion'], 5, "0", STR_PAD_LEFT),
                                "lider" => array(
                                    "id" => (int) $consulta['id_lider'],
                                    "descripcion" => strtoupper($consulta['lider'])
                                ),
                                "asesor" => array(
                                    "id" => (int) $consulta['id_asesor'],
                                    "descripcion" => strtoupper($consulta['asesor']),
                                    "celular" => $consulta['celular'],
                                    "correo" => $consulta['correo']
                                ),
                                "responsable" => array(
                                    "id" => (int) $consulta['id_responsable'],
                                    "descripcion" => strtoupper($consulta['responsable']),
                                    "fecha_asignacion" => $consulta['fecha_asignacion']
                                ),
                                "destino" => $consulta['destino'],
                                "fechas" => array(
                                    "ida" => $consulta['fecha_ida'],
                                    "retorno" => $consulta['fecha_retorno']
                                ),
                                "opciones" => array(
                                    "alojamiento" => (int) $consulta['alojamiento'],
                                    "traslados" => (int) $consulta['traslados'],
                                    "alimentacion" => (int) $consulta['alimentacion'],
                                    "actividades" => (int) $consulta['actividades'],
                                    "tickets" => (int) $consulta['tickets']
                                ),
                                "personas" => array(
                                    "adultos" => (int) $consulta["adultos"],
                                    "ninos" => (int) $consulta["ninos"],
                                    "infantes" => (int) $consulta["infantes"],
                                    "tercera_discapacitados" => (int) $consulta["tercera_discapacitados"],
                                ),
                                "fecha_alta" => $consulta['fecha_alta'],
                                "fecha_modificacion" => $consulta['fecha_modificacion'],
                                "fecha_caducidad" => $consulta['fecha_caducidad'],
                                "estado" => array(
                                    "descripcion" => $descripcion_estado,
                                    "valor" => (int) $consulta['estado'],
                                    "color" => $color_estado
                                ),
                                "estilo" => $estilo,
                                "mensaje" => "Faltan ".$diff->days." días"
                            );

                            $respuesta['consulta'] = $filtrado_resultados;
                       
                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = "No se encontró información de la cotización.";
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

            $app->post("/nueva_cotizacion", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                $respuesta['estado'] = false;

                $respuesta['data'] = $data;
                
                try{                    
                    $auth = new Authentication();

                    $usuario = $auth->Valida_Sesion($authorization[0]);
                    
                    if ($usuario['estado']){                                                

                        $mysql = new Database("vtgsa_ventas");


                        $id_usuario = $usuario['usuario']['id_usuario'];

                        $destino = $data['destino'];
                        $ida = $data['ida'];
                        $retorno = $data['retorno'];

                        $correo = $data['correo'];
                        $celular = $data['celular'];

                        $alojamiento = $data['opciones']['alojamiento'];
                        $traslados = $data['opciones']['traslados'];
                        $alimentacion = $data['opciones']['alimentacion'];
                        $actividades = $data['opciones']['actividades'];
                        $tickets = $data['opciones']['tickets'];

                        $adultos = $data['pasajeros']['adultos'];
                        $ninos = $data['pasajeros']['ninos'];
                        $infantes = $data['pasajeros']['infantes'];
                        $tercera_discapacitados = $data['pasajeros']['tercera_discapacitados'];

                        $fecha_actual = date("Y-m-d H:i:s");
                        $fecha_caducidad = date("Y-m-d H:i:s", strtotime($fecha_actual."+ 2 days"));
                        $fecha_asignacion = null;

                        $respuesta['actual'] = $fecha_actual;
                        $respuesta['caduca'] = $fecha_caducidad;

                        $id_asesor = 21;
                        $id_lider = 16;
                        $id_responsable = 0;

                        $id_cotizacion = $mysql->Ingreso("INSERT INTO cotizaciones (id_asesor, id_lider, destino, fecha_ida, fecha_retorno, alojamiento, traslados, alimentacion, actividades, tickets, adultos, ninos, infantes, tercera_discapacitados, id_responsable, fecha_alta, fecha_asignacion, fecha_caducidad, fecha_modificacion, correo, celular, estado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", array($id_asesor, $id_lider, $destino, $ida, $retorno, $alojamiento, $traslados, $alimentacion, $actividades, $tickets, $adultos, $ninos, $infantes, $tercera_discapacitados, $id_responsable, $fecha_actual, $fecha_asignacion, $fecha_caducidad, $fecha_actual, $correo, $celular, 0));

                        $respuesta['id_cotizacion'] = $id_cotizacion;
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

            $app->post("/envia_cotizacion/{id_cotizacion}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_cotizacion = $request->getAttribute('id_cotizacion');
                $data = $request->getParsedBody();
                $files = $request->getUploadedFiles();

                $respuesta['estado'] = false;

                $respuesta['data'] = $data;
                $respuesta['files'] = $files;
                
                try{                    
                    $auth = new Authentication();

                    $usuario = $auth->Valida_Sesion($authorization[0]);
                    
                    if ($usuario['estado']){                                                
                        $id_usuario = $usuario['usuario']['id_usuario'];
                        
                        $mysql = new Database("vtgsa_ventas");

                        // procesa el archivo a vincular
                        // if (isset($files)){                                                                                                    
                        //     $archivo_temporal = $files['logotipo']->file;
                        
                        //     $carpeta = __DIR__."/../../public/imgs";

                        //     if (!file_exists($carpeta)){
                        //         // Si no existe la carpeta la crea
                        //         mkdir($carpeta, 0777, true);
                        //     }        
                        //     $nombre_archivo = base64_encode("logotipo".date("YmdHis"));
                        //     $destino = $carpeta.'/'.$nombre_archivo;
                        //     move_uploaded_file($archivo_temporal, $destino);

                        //     $logotipo = $nombre_archivo;                                    
                        // }

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
        
        $app->group('/crm', function() use ($app) {   

            // LOGIN

            $app->post("/login", function(Request $request, Response $response){
                $data = $request->getParsedBody();
                $respuesta['estado'] = false;

                try{
                    $functions = new Functions();

                    $username = $data['username'];
                    $password = $data['password'];

                    $mysql = new Database("mvevip_crm");

                    $consulta = $mysql->Consulta_Unico("SELECT * FROM asesores WHERE (correo='".$username."') AND (clave='".$password."')");

                    // $respuesta['sql'] = "SELECT * FROM asesores WHERE (correo='".$username."') AND (clave='".$password."')";

                    if (isset($consulta['id_asesor'])){
                        $acceso = "";
                        switch ($consulta['acceso']) {
                            case 0:
                                $acceso = 'Administrador';
                                break;
                            case 1:
                                $acceso = 'Servicio al Cliente';
                                break;
                            case 2:
                                $acceso = 'Visado';
                                break;
                            case 1:
                                $acceso = 'Peticiones Proveedores Nacionales'; // Sebas
                                break;
                        }

                        $string_hash = $consulta['id_asesor']."-".$acceso."-".date("Ymd");
                        $hash = $functions->encrypt_decrypt('encrypt', $string_hash);

                        $actualiza = $mysql->Modificar("UPDATE asesores SET hash=? WHERE id_asesor=?", array($hash, $consulta['id_asesor']));

                        $respuesta['token'] = $hash;

                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No se encuentra su usuario.";
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
                    $functions = new Functions();

                    $hash = $authorization[0];

                    $string = $functions->encrypt_decrypt('decrypt', $hash);

                    $id_asesor = explode("-", $string)[0];

                    $mysql = new Database("mvevip_crm");

                    $consulta = $mysql->Consulta_Unico("SELECT * FROM asesores WHERE id_asesor=".$id_asesor);

                    if (isset($consulta['id_asesor'])){
                        $acceso = "";
                        switch ($consulta['acceso']) {
                            case 0:
                                $acceso = 'Administrador';
                                break;
                            case 1:
                                $acceso = 'Servicio al Cliente';
                                break;
                            case 2:
                                $acceso = 'Visado';
                                break;
                            case 1:
                                $acceso = 'Peticiones Proveedores Nacionales'; // Sebas
                                break;
                        }

                        //Obtiene Dashboard segun el usuario
                        $por_acceso = ""; // admin
                        if ($consulta['acceso'] != "-1"){ // no es admin                            
                            $por_acceso = "AND (R.id_asesor=".$consulta['id_asesor'].")";
                        }
                        
                        $mes_actual['inicio'] = date("Y-m-01");
                        $mes_actual['final'] = date("Y-m-t");

                        $month_ini = new DateTime("first day of last month");
                        $month_end = new DateTime("last day of last month");

                        $mes_anterior['inicio'] = $month_ini->format('Y-m-d');
                        $mes_anterior['final'] = $month_end->format('Y-m-d');

                        $terminados_mes_actual = $mysql->Consulta_Unico("SELECT COUNT(T.id_ticket) AS total FROM tickets T, reservas R WHERE ((R.pickup_fecha BETWEEN '".$mes_actual['inicio']."' AND '".$mes_actual['final']."') ".$por_acceso." AND (T.estado=6)) AND (T.id_ticket=R.id_ticket)");
                        $por_terminar_mes_actual = $mysql->Consulta_Unico("SELECT COUNT(T.id_ticket) AS total FROM tickets T, reservas R WHERE ((R.pickup_fecha BETWEEN '".$mes_actual['inicio']."' AND '".$mes_actual['final']."') ".$por_acceso." AND (T.estado=4)) AND (T.id_ticket=R.id_ticket)");
                        $por_terminar_mes_anterior = $mysql->Consulta_Unico("SELECT COUNT(T.id_ticket) AS total FROM tickets T, reservas R WHERE ((R.pickup_fecha BETWEEN '".$mes_anterior['inicio']."' AND '".$mes_anterior['final']."') ".$por_acceso." AND (T.estado=4)) AND (T.id_ticket=R.id_ticket)");                        

                        $dashboard = array(
                            "finalizados" => (int) $terminados_mes_actual['total'],
                            "por_finalizar" => (int) $por_terminar_mes_actual['total'],
                            "por_finalizar_anterior" => (int) $por_terminar_mes_anterior['total'],
                        );                        
                                           
                        $respuesta['consulta'] = array(
                            "id_asesor" => $consulta['id_asesor'],
                            "asesor" => $consulta['asesor'],
                            "apodo" => $consulta['apodo'],
                            "correo" => $consulta['correo'],
                            "acceso" => (int) $consulta['acceso'],
                            "nombre_acceso" => $acceso,
                            "estado" => (int) $consulta['estado'],
                            "usu_codigo" => (int) $consulta['usu_codigo'],
                            "dashboard" => $dashboard
                        );

                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No se encuentra su usuario.";
                    }
                    
                    $respuesta['estado'] = true;
                                    
                }catch(Exception $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            // ASESORES

            $app->get("/asesores", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
            
                try{
                    $mysql = new Database('mvevip_crm');

                    $buscador = '';
                    if (isset($params['id_asesor'])){
                        $id_asesor = $params['id_asesor'];

                        $tipo = $mysql->Consulta_Unico("SELECT acceso FROM asesores WHERE id_asesor=".$id_asesor);
                        if (isset($tipo['acceso'])){
                            $buscador = "(acceso=".$tipo['acceso'].") AND ";
                        }                        
                    }

                    $consulta = $mysql->Consulta("SELECT * FROM asesores WHERE ".$buscador." (estado=0) ORDER BY id_asesor ASC");

                    $respuesta['consulta'] = $consulta;
                    $respuesta['estado'] = true;                            
            
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            // DESTINOS

            $app->get("/destinos", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');

                $respuesta['estado'] = false;
            
                try{

                    $functions = new Functions();

                    $validacion = $functions->Validar_Credenciales($authorization[0]);

                    if ($validacion['estado']){
                        $mysql = new Database('mvevip_crm');
                        $acceso_usuario = $validacion['asesor']['acceso']['tipo'];

                        $sql_destinos = "";
                        if ($acceso_usuario == "-1"){
                            $sql_destinos = "SELECT * FROM destinos WHERE (estado=0) ORDER BY id_destino ASC";
                        }else{
                            $sql_destinos = "SELECT * FROM destinos WHERE (area=".$acceso_usuario.") AND (estado=0) ORDER BY id_destino ASC";
                        }

                        $consulta = $mysql->Consulta($sql_destinos);

                        $proveedores = $mysql->Consulta("SELECT * FROM proveedores ORDER BY proveedor ASC");
                        $formas_pago = $mysql->Consulta("SELECT * FROM formas_pago ORDER BY forma_pago ASC");
                        $aerolineas = $mysql->Consulta("SELECT * FROM aerolineas ORDER BY orden DESC, id_aerolinea ASC");

                        $respuesta['consulta'] = $consulta;
                        $respuesta['proveedores'] = $proveedores;
                        $respuesta['formas_pago'] = $formas_pago;
                        $respuesta['aerolineas'] = $aerolineas;
                        $respuesta['estado'] = true;                            
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
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
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
            
                try{
                    $functions = new Functions();

                    $validacion = $functions->Validar_Credenciales($authorization[0]);

                    if ($validacion['estado']){
                        $mysql = new Database('mvevip_crm');

                        $buscador = '';
                        if (isset($params['buscador'])){
                            $buscador = $params['buscador'];
                        }

                        $consulta = $mysql->Consulta("SELECT * FROM clientes WHERE ((documento LIKE '%".$buscador."%') OR (nombres LIKE '%".$buscador."%') OR (apellidos LIKE '%".$buscador."%') OR (idclienteCRM LIKE '%".$buscador."%')) ORDER BY id_cliente ASC");

                        $resultados = [];

                        if (is_array($consulta)){
                            if (count($consulta) > 0){
                                foreach ($consulta as $linea) {
                                    $id_cliente = $linea['id_cliente'];

                                    $abiertos = $mysql->Consulta_Unico("SELECT COUNT(id_ticket) AS total FROM tickets WHERE (id_cliente=".$id_cliente.") AND ((estado!=5) AND (estado!=6) AND (estado!=0))");

                                    $tickets_abiertos = 0;
                                    if (isset($abiertos['total'])){
                                        $tickets_abiertos = $abiertos['total'];
                                    }

                                    array_push($resultados, array(
                                        "id_cliente" => (int) $linea['id_cliente'],
                                        "documento" => $linea['documento'],
                                        "nombres" => $linea['nombres'],
                                        "apellidos" => $linea['apellidos'],
                                        "correo" => $linea['correo'],
                                        "telefono" => $linea['telefono'],
                                        "celular" => $linea['celular'],
                                        "idclienteCRM" => $linea['idclienteCRM'],
                                        "abiertos" => (int) $tickets_abiertos
                                    ));
                                }
                            }
                        }
                    
                        $respuesta['consulta'] = $resultados;
                        $respuesta['estado'] = true;                            
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }

                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });


            $app->get("/clientes/{id}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $id_cliente = $request->getAttribute('id');
                $respuesta['estado'] = false;
            
                try{
                    $functions = new Functions();

                    $validacion = $functions->Validar_Credenciales($authorization[0]);

                    if ($validacion['estado']){
                        $mysql = new Database('mvevip_crm');
                        $consulta = $mysql->Consulta_Unico("SELECT * FROM clientes WHERE (id_cliente=".$id_cliente.") OR (documento='".$id_cliente."')");

                        $respuesta['consulta'] = $consulta;
                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }
                    
            
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            // DASHBOARD

            $app->get("/dashboard", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
            
                try{
                    $functions = new Functions();

                    $validacion = $functions->Validar_Credenciales($authorization[0]);

                    if ($validacion['estado']){
                        $id_usuario = $validacion['asesor']['id_asesor'];
                        $acceso = $validacion['asesor']['acceso']['tipo'];                    

                        $mysql = new Database('mvevip_crm');

                        $por_acceso = ""; // admin
                        if ($acceso != "-1"){ // no es admin                            
                            $por_acceso = "AND (R.id_asesor=".$id_usuario.")";
                        }
                        
                        $mes_actual['inicio'] = date("Y-m-01");
                        $mes_actual['final'] = date("Y-m-t");

                        $month_ini = new DateTime("first day of last month");
                        $month_end = new DateTime("last day of last month");

                        $mes_anterior['inicio'] = $month_ini->format('Y-m-d');
                        $mes_anterior['final'] = $month_end->format('Y-m-d');

                        $terminados_mes_actual = $mysql->Consulta_Unico("SELECT COUNT(T.id_ticket) AS total FROM tickets T, reservas R WHERE ((R.pickup_fecha BETWEEN '".$mes_actual['inicio']."' AND '".$mes_actual['final']."') ".$por_acceso." AND (T.estado=6)) AND (T.id_ticket=R.id_ticket)");
                        $por_terminar_mes_actual = $mysql->Consulta_Unico("SELECT COUNT(T.id_ticket) AS total FROM tickets T, reservas R WHERE ((R.pickup_fecha BETWEEN '".$mes_actual['inicio']."' AND '".$mes_actual['final']."') ".$por_acceso." AND (T.estado=4)) AND (T.id_ticket=R.id_ticket)");
                        $por_terminar_mes_anterior = $mysql->Consulta_Unico("SELECT COUNT(T.id_ticket) AS total FROM tickets T, reservas R WHERE ((R.pickup_fecha BETWEEN '".$mes_anterior['inicio']."' AND '".$mes_anterior['final']."') ".$por_acceso." AND (T.estado=4)) AND (T.id_ticket=R.id_ticket)");                        

                        $respuesta['consulta'] = array(
                            "finalizados" => (int) $terminados_mes_actual['total'],
                            "por_finalizar" => (int) $por_terminar_mes_actual['total'],
                            "por_finalizar_anterior" => (int) $por_terminar_mes_anterior['total'],
                        );                        

                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }                    
            
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });


            // TICKETS

            $app->get("/tickets", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
            
                try{
                    $functions = new Functions();

                    $validacion = $functions->Validar_Credenciales($authorization[0]);

                    if ($validacion['estado']){
                        $id_usuario = $validacion['asesor']['id_asesor'];

                        $mysql = new Database('mvevip_crm');
                        
                        $resultados = [];

                        $buscador = '';
                        if (isset($params['buscador'])){
                            $buscador = "((C.documento LIKE '%".$params['buscador']."%') OR (C.nombres LIKE '%".$params['buscador']."%') OR (C.apellidos LIKE '%".$params['buscador']."%')) AND ";
                        }

                        $id_asesor = '';
                        if (isset($params['id_asesor'])){
                            $id_asesor = "(T.id_asesor=".$params['id_asesor'].") AND ";
                        }

                        $por_estado = '';
                        if (isset($params['estado'])){
                            if ($params['estado'] != "-1"){
                                $por_estado = "(T.estado=".$params['estado'].") AND";
                            }
                        }

                        $administra = '';
                        // if ($params['id_asesor']==8){
                        //     $administra = "(T.estado=2) AND";
                        // }

                        $consulta = $mysql->Consulta("SELECT T.id_ticket, T.requerimiento, T.fecha_lim_contacto, T.hora_lim_contacto, T.fecha_alta, T.fecha_modificacion, T.estado, T.alta_prioridad, T.id_cliente, C.nombres, C.apellidos, C.correo, C.telefono, C.celular, T.id_asesor, A.asesor, A.apodo FROM tickets T, clientes C, asesores A WHERE ".$buscador." ".$id_asesor." ".$por_estado." ".$administra." (T.id_cliente=C.id_cliente) AND (T.id_asesor=A.id_asesor) ORDER BY T.alta_prioridad DESC, T.fecha_lim_contacto ASC, T.hora_lim_contacto ASC");                                               

                        if (is_array($consulta)){
                            if (count($consulta) > 0){
                                foreach ($consulta as $linea) {
                                    $id_ticket = $linea['id_ticket'];                                    

                                    $estado = "";
                                    $color = "";
                                    switch ($linea['estado']) {
                                        case 0:
                                            $estado = "Nueva Tarea";
                                            $color = "primary";
                                            break;
                                        case 1:
                                            $estado = "Pendiente de Coordinación";
                                            $color = "warning";
                                            break;
                                        case 2:
                                            $estado = "Pendiente Confirmación de Proveedor";
                                            $color = "warning";
                                            break;
                                        case 3:
                                            $estado = "Pendiente Elaboracion de Reserva";
                                            $color = "info";
                                            break;
                                        case 4:
                                            $estado = "Pendiente Explicacion a Cliente";
                                            $color = "success";
                                            break;
                                        case 6:
                                            $estado = "Tarea Terminada";
                                            $color = "secondary";
                                            break;
                                        case 5:
                                            $estado = "Tarea Anulada";
                                            $color = "danger";
                                            break;
                                        case 7:
                                            $estado = "Espera a Respuesta de Cliente";
                                            $color = "warning";
                                            break;
                                        case 8:
                                            $estado = "Reserva Cancelada";
                                            $color = "danger";
                                            break;
                                    }

                                    array_push($resultados, array(
                                        "id_ticket" => $linea['id_ticket'],
                                        "id_cliente" => $linea['id_cliente'],
                                        "alta_prioridad" => (int) $linea['alta_prioridad'],
                                        "cliente" => array(
                                            "nombres" => $linea['nombres'],
                                            "apellidos" => $linea['apellidos'],
                                            "correo" => $linea['correo'],
                                            "telefono" => $linea['telefono'],
                                            "celular" => $linea['celular'],
                                        ),
                                        "id_asesor" => $linea['id_asesor'],
                                        "asesor" => array(
                                            "nombres" => $linea['asesor'],
                                            "apodo" => $linea['apodo']
                                        ),
                                        "requerimiento" => $linea['requerimiento'],
                                        "limite" => array(
                                            "fecha" => $linea['fecha_lim_contacto'],
                                            "hora" => $linea['hora_lim_contacto'],
                                        ),                                    
                                        "fecha_alta" => $linea['fecha_alta'],
                                        "fecha_modificacion" => $linea['fecha_modificacion'],
                                        "estado" => array(
                                            "valor" => (int) $linea['estado'],
                                            "descripcion" => $estado,
                                            "color" => $color
                                        )
                                    ));
                                }
                            }
                        }

                        $respuesta['consulta'] = $resultados;
                        $respuesta['estado'] = true;                            

                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }                    
            
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/tickets/{id}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $id_ticket = $request->getAttribute('id');
                $respuesta['estado'] = false;
            
                try{
                    $mysql = new Database('mvevip_crm');
                    $id_usuario = 1;

                    $eventos = new Events($mysql, $id_usuario);
                    
                    $resultados = [];

                    $consulta = $mysql->Consulta_Unico("SELECT T.id_ticket, T.requerimiento, T.fecha_lim_contacto, T.hora_lim_contacto, T.fecha_alta, T.fecha_modificacion, T.estado, T.id_cliente, C.nombres, C.apellidos, C.correo, C.telefono, C.celular, C.idclienteCRM, C.documento, T.id_asesor, A.asesor, A.apodo FROM tickets T, clientes C, asesores A WHERE (T.id_ticket=".$id_ticket.") AND ((T.id_cliente=C.id_cliente) AND (T.id_asesor=A.id_asesor)) ORDER BY T.fecha_lim_contacto ASC, T.hora_lim_contacto ASC");

                    if (isset($consulta['id_ticket'])){
                        $id_ticket = $consulta['id_ticket'];

                        $ticket_eventos = $eventos->Get_Event($id_ticket);

                        $estado = "";
                        $color = "";
                        switch ($consulta['estado']) {
                            case 0:
                                $estado = "Nueva Tarea";
                                $color = "primary";
                                break;
                            case 1:
                                $estado = "Pendiente de Coordinación";
                                $color = "warning";
                                break;
                            case 2:
                                $estado = "Pendiente Confirmación de Proveedor";
                                $color = "warning";
                                break;
                            case 3:
                                $estado = "Pendiente Elaboracion de Reserva";
                                $color = "info";
                                break;
                            case 4:
                                $estado = "Pendiente Explicacion a Cliente";
                                $color = "success";
                                break;
                            case 6:
                                $estado = "Tarea Terminada";
                                $color = "secondary";
                                break;
                            case 5:
                                $estado = "Tarea Anulada";
                                $color = "danger";
                                break;
                            case 7:
                                $estado = "Espera a Respuesta de Cliente";
                                $color = "warning";
                                break;
                            case 8:
                                $estado = "Reserva Cancelada";
                                $color = "danger";
                                break;
                        }

                        // reservas
                        $detalle_reserva = array();
                        $reserva = $mysql->Consulta_Unico("SELECT * FROM reservas WHERE id_ticket=".$id_ticket);
                        if (isset($reserva['id_reserva'])){
                            $id_reserva = $reserva['id_reserva'];

                            // destinos

                            $destinos = $mysql->Consulta("SELECT * FROM reservas_destinos R, destinos D WHERE (R.id_reserva=".$id_reserva.") AND (R.destino=D.id_destino)");

                            // actividades

                            $actividades = $mysql->Consulta("SELECT * FROM reservas_actividades WHERE id_reserva=".$id_reserva);

                            // adjuntos

                            $adjuntos = $mysql->Consulta("SELECT * FROM reservas_adjuntos WHERE id_reserva=".$id_reserva);

                            // impuestos

                            $impuestos = $mysql->Consulta("SELECT * FROM reservas_impuestos WHERE id_reserva=".$id_reserva);                            

                            $detalle_reserva = array(
                                "id_reserva" => $reserva['id_reserva'],
                                "nombres" => $reserva['nombres'],
                                "apellidos" => $reserva['apellidos'],
                                "email1" => $reserva['email1'],
                                "email2" => $reserva['email2'],
                                "telefono" => $reserva['telefono'],
                                "id_asesor" => $reserva['id_asesor'],
                                "aerolinea" => $reserva['aerolinea'],
                                "num_adultos" => $reserva['num_adultos'],
                                "num_ninos" => $reserva['num_ninos'],
                                "num_ninos2" => $reserva['num_ninos2'],
                                "num_discapacitados_3edad" => $reserva['num_discapacitados_3edad'],
                                "observacionesReserva" => $reserva['observacionesReserva'],
                                "pickup_destino" => $reserva['pickup_destino'],
                                "pickup_fecha" => $reserva['pickup_fecha'],
                                "pickup_hora" => $reserva['pickup_hora'],
                                "pickup_codvuelo" => $reserva['pickup_codvuelo'],
                                "dropoff_destino" => $reserva['dropoff_destino'],
                                "dropoff_fecha" => $reserva['dropoff_fecha'],
                                "dropoff_hora" => $reserva['dropoff_hora'],
                                "dropoff_codvuelo" => $reserva['dropoff_codvuelo'],
                                "observaciones" => $reserva['observaciones'],
                                "num_reserva_transfer" => $reserva['num_reserva_transfer'],
                                "costo_transfer" => (float) $reserva['costo_transfer'],
                                "id_forma_pago_transfer" => (int) $reserva['id_forma_pago_transfer'],
                                "id_proveedor_transfer" => (int) $reserva['id_proveedor_transfer'],
                                "conductor_transfer" => $reserva['conductor_auto'],
                                "estado" => $reserva['estado'],
                                "destinos" => $destinos,
                                "actividades" => $actividades,
                                "adjuntos" => $adjuntos,
                                "impuestos" => $impuestos,
                                "pago_impuestos" => $reserva['pagoimpuestos'],
                                "voucher_auto" => $reserva['voucher_auto'],
                                "recibo_auto" => $reserva['recibo_auto'],
                                "monto_paquete" => (float) $reserva['monto_paquete'],
                                "monto_adicional" => (float) $reserva['monto_adicional'],
                                "numero_cotizacion" => $reserva['num_cotizacion'],
                                "incluye_transfer_alojamiento" => (int) $reserva['incluye_transfer_alojamiento']
                            );
                        }

                        $resultados = array(
                            "id_ticket" => $consulta['id_ticket'],
                            "id_cliente" => $consulta['id_cliente'],
                            "cliente" => array(
                                "documento" => $consulta['documento'],
                                "nombres" => $consulta['nombres'],
                                "apellidos" => $consulta['apellidos'],
                                "correo" => $consulta['correo'],
                                "telefono" => $consulta['telefono'],
                                "celular" => $consulta['celular'],
                                "idclienteCRM" => $consulta['idclienteCRM'],
                            ),
                            "id_asesor" => $consulta['id_asesor'],
                            "asesor" => array(
                                "nombres" => $consulta['asesor'],
                                "apodo" => $consulta['apodo']
                            ),
                            "requerimiento" => $consulta['requerimiento'],
                            "limite" => array(
                                "fecha" => $consulta['fecha_lim_contacto'],
                                "hora" => $consulta['hora_lim_contacto'],
                            ),                                    
                            "fecha_alta" => $consulta['fecha_alta'],
                            "fecha_modificacion" => $consulta['fecha_modificacion'],
                            "estado" => array(
                                "valor" => (int) $consulta['estado'],
                                "descripcion" => $estado,
                                "color" => $color
                            ),
                            "eventos" => $ticket_eventos,
                            "reserva" => $detalle_reserva
                        );
                    }                  

                    $respuesta['consulta'] = $resultados;
                    $respuesta['estado'] = true;                            
            
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });            

            $app->post("/tickets", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                $respuesta['estado'] = false;

                $respuesta['data'] = $data;
            
                try{
                    $functions = new Functions();

                    $validacion = $functions->Validar_Credenciales($authorization[0]);                    

                    if ($validacion['estado']){
                        $mysql = new Database('mvevip_crm');
                        $functions = new Functions();

                        $id_usuario = $validacion['asesor']['id_asesor'];

                        $eventos = new Events($mysql, $id_usuario);

                        $id_cliente = $data['id_cliente'];
                        $documento = $data['cliente']['documento'];
                        $nombre = strtoupper($data['cliente']['nombres']);
                        $apellido = strtoupper($data['cliente']['apellidos']);
                        $telefono = $data['cliente']['telefono'];
                        $correo = $data['cliente']['correo'];
                        $idclienteCRM = $data['cliente']['idclienteCRM'];

                        $id_asesor = $data['id_asesor'];
                        $alta_prioridad = $data['alta_prioridad'];
                        $requerimiento = $data['requerimientos'];
                        $fecha_limite = $data['limite']['fecha'];
                        $hora_limite = $data['limite']['hora'];
                        $fecha_alta = date("Y-m-d H:i:s");
                        $fecha_modificacion = $fecha_alta;
                        $estado = 0;

                        $prioridad = 0;
                        if (isset($data['alta_prioridad'])){
                            if ($data['alta_prioridad']){
                                $prioridad = 1;
                            }                            
                        }

                        // verifica q exista el cliente, sino lo crea o actualiza su informacion
                        $info_cliente = 0;
                        $verifica = $mysql->Consulta_Unico("SELECT id_cliente FROM clientes WHERE documento='".$documento."'");

                        if (isset($verifica['id_cliente'])){ // ya existe este documento => lo actualiza 
                            $info_cliente = $verifica['id_cliente'];

                            $actualizacion = $mysql->Modificar("UPDATE clientes SET nombres=?, apellidos=?, telefono=?, correo=?, idclienteCRM=? WHERE id_cliente=?", array($nombre, $apellido, $telefono, $correo, $idclienteCRM, $info_cliente));
                        }else{ // no existe el cliente con este documento => lo crea
                            $info_cliente = $mysql->Ingreso("INSERT INTO clientes (documento, nombres, apellidos, correo, telefono, celular, idclienteCRM, estado) VALUES (?,?,?,?,?,?,?,?)", array($documento, $nombre, $apellido, $correo, $telefono, "", $idclienteCRM, $estado));
                        }                        

                        if ($info_cliente > 0){
                            // verifica q existe el numero de asesor y que este disponible

                            $verifica = $mysql->Consulta_Unico("SELECT id_asesor FROM asesores WHERE id_asesor=".$id_asesor);

                            if (isset($verifica['id_asesor'])){

                                // verifica que el dia y hora sea mayor q el actual
                                $limite = $fecha_limite . " " . $hora_limite;                        

                                if ($fecha_alta < $limite){
                                    
                                    // ingreso de nuevo ticket
                                    $id_ticket = $mysql->Ingreso("INSERT INTO tickets (id_asesor, id_cliente, requerimiento, fecha_lim_contacto, hora_lim_contacto, alta_prioridad, fecha_alta, fecha_modificacion, estado) VALUES (?,?,?,?,?,?,?,?,?)", array($id_asesor, $info_cliente, $requerimiento, $fecha_limite, $hora_limite, $prioridad, $fecha_alta, $fecha_modificacion, $estado));

                                    $nuevo_evento = $eventos->New_Event($id_ticket, "Ingreso de Ticket");

                                    $respuesta['event'] = $nuevo_evento;

                                    $respuesta['id_ticket'] = $id_ticket;                            
                                    $respuesta['estado'] = true;
                                }else{
                                    $respuesta['error'] = "La fecha limite de contacto debe ser mayor que la actual";
                                }                        

                            }else{
                                $respuesta['error'] = "El asesor no existe o se encuentra con el limite de tickets asignados";
                            }                
                        }else{
                            $respuesta['error'] = "Hubo un error al crear o modificar los datos del cliente.";
                        }
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->put("/tickets/{id}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $id_ticket = $request->getAttribute('id');
                $data = $request->getParsedBody();
                $respuesta['estado'] = false;               
            
                try{
                    $modificacion = "";
                    if (isset($data["modificacion"])){
                        $modificacion = $data["modificacion"];
                    }

                    if ($modificacion!=""){
                        $functions = new Functions();

                        $validacion = $functions->Validar_Credenciales($authorization[0]);
    
                        if ($validacion['estado']){
                            $mysql = new Database('mvevip_crm');
                            $functions = new Functions();    
                            $id_usuario = $validacion['asesor']['id_asesor'];
    
                            $eventos = new Events($mysql, $id_usuario);
                                
                            switch ($modificacion) {
                                case 'anulacion':
                                    $motivo_anulacion = "Anulación de Ticket";
                                    $observaciones = "";
                                    if (isset($data['observaciones'])){
                                        $observaciones = $data['observaciones'];
                                        if (trim($observaciones)!=""){
                                            $motivo_anulacion .= " - ".$observaciones;
                                        }

                                        // anulacion de ticket
                                        $anulacion = $mysql->Modificar("UPDATE tickets SET estado=5 WHERE id_ticket=?", array($id_ticket));

                                        $nuevo_evento = $eventos->New_Event($id_ticket, $motivo_anulacion);

                                        // envia correo a cliente de la anulacion
                                        $busqueda = $functions->Informacion_Ticket_Reserva($mysql, $id_ticket);
                                        if ($busqueda['estado']){
                                            $informacion = $busqueda['informacion'];

                                            $mail = new Email($validacion['asesor']['correo'], $validacion['asesor']['password'], $validacion['asesor']['asesor']);

                                            $data = array(
                                                "correo" => $informacion['cliente']['correo'],
                                                "destinatario" => $informacion['cliente']['nombres_apellidos'],
                                                "asunto" => "Estimad@ ".$informacion['cliente']['nombres_apellidos']." su reserva se ha cancelado",
                                                "cliente" => $informacion['cliente'],
                                                "motivo_cancelacion" => "Detalle: ".$observaciones
                                            );

                                            $copias = [];
                                            $adjuntos = [];                                            

                                            array_push($copias, array(
                                                "correo" => $validacion['asesor']['correo'],                                                
                                            ));

                                            $reply = array(
                                                "correo" => $informacion['cliente']['correo_asesor'],
                                                "nombre" => $informacion['cliente']['asesor']
                                            );

                                            $respuesta['envio'] = $mail->Enviar_Email($data, $copias, $adjuntos, $reply, "anulacion_reserva");
                                        }
                                        
                                        
                                        $respuesta['estado'] = true;   
                                    }else{
                                        $respuesta['error']  = "Para anular el ticket debe ingresar un motivo.";
                                    }
                                    break;
                                
                                case 'cancelacion':
                                    $motivo_anulacion = "Anulación de Reserva";
                                    $observaciones = "";
                                    if (isset($data['observaciones'])){
                                        $observaciones = $data['observaciones'];
                                        if (trim($observaciones)!=""){
                                            $motivo_anulacion .= " - ".$observaciones;
                                        }

                                        // anulacion de ticket
                                        $anulacion = $mysql->Modificar("UPDATE tickets SET estado=8 WHERE id_ticket=?", array($id_ticket));

                                        $nuevo_evento = $eventos->New_Event($id_ticket, $motivo_anulacion);

                                        // envia correo a cliente de la anulacion
                                        $busqueda = $functions->Informacion_Ticket_Reserva($mysql, $id_ticket);
                                        if ($busqueda['estado']){
                                            $informacion = $busqueda['informacion'];

                                            $mail = new Email($validacion['asesor']['correo'], $validacion['asesor']['password'], $validacion['asesor']['asesor']);

                                            $data = array(
                                                "correo" => $informacion['cliente']['correo'],
                                                "destinatario" => $informacion['cliente']['nombres_apellidos'],
                                                "asunto" => "Estimad@ ".$informacion['cliente']['nombres_apellidos']." su reserva se ha cancelado",
                                                "cliente" => $informacion['cliente'],
                                                "motivo_cancelacion" => "Detalle: ".$observaciones
                                            );

                                            $copias = [];
                                            $adjuntos = [];                                            

                                            array_push($copias, array(
                                                "correo" => $validacion['asesor']['correo'],                                                
                                            ));

                                            $reply = array(
                                                "correo" => $informacion['cliente']['correo_asesor'],
                                                "nombre" => $informacion['cliente']['asesor']
                                            );

                                            // $respuesta['envio'] = $mail->Enviar_Email($data, $copias, $adjuntos, $reply, "anulacion_reserva");
                                        }                                        
                                        
                                        $respuesta['estado'] = true;   
                                    }else{
                                        $respuesta['error']  = "Para anular la reserva debe ingresar un motivo.";
                                    }
                                    break;
                                case 'reactivacion':
                                    $motivo_anulacion = "Reactivación de Reserva";                                   
                                        // anulacion de ticket
                                        $anulacion = $mysql->Modificar("UPDATE tickets SET estado=2 WHERE id_ticket=?", array($id_ticket));
                                        $nuevo_evento = $eventos->New_Event($id_ticket, $motivo_anulacion);
                                        
                                        $respuesta['estado'] = true;
                                    break;
                                default:
                                    # code...
                                    break;
                            }
                        }else{
                            $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                        }
                    }                    
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            // TICKETS EVENTOS

            $app->get("/tickets/eventos/{id}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $id_ticket = $request->getAttribute('id');
                $respuesta['estado'] = false;
            
                try{
                    $mysql = new Database('mvevip_crm');
                    $id_usuario = 1;

                    $eventos = new Events($mysql, $id_usuario);

                    $respuesta['consulta'] = $eventos->Get_Event($id_ticket);
                    $respuesta['estado'] = true;
            
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->post("/tickets/eventos/{id}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                $files = $request->getUploadedFiles();
                $id_ticket = $request->getAttribute('id');

                $respuesta['estado'] = false;

                // $respuesta['data'] = $data;
                // $respuesta['files'] = $files;
            
                try{
                    $functions = new Functions();

                    $validacion = $functions->Validar_Credenciales($authorization[0]);

                    if ($validacion['estado']){
                        $comentario = "";
                        if ($data['comentario']){
                            $comentario = $data['comentario'];
                        }                    

                        if (trim($comentario) != ''){
                            $mysql = new Database('mvevip_crm');                    
                            $id_asesor = $validacion['asesor']['id_asesor'];
                            
                            $eventos = new Events($mysql, $id_asesor);

                            $verifica = $mysql->Consulta_Unico("SELECT id_ticket FROM tickets WHERE id_ticket=".$id_ticket);

                            if (isset($verifica['id_ticket'])){                                                
                                $link = "";

                                // procesa el archivo a vincular
                                if (isset($files)){
                                    if (isset($data['nombre_archivo'])){
                                        $archivo = $data['nombre_archivo'];
                                        if (isset(explode('.', $archivo)[1])){
                                            $extension = explode('.', $archivo)[1];
                                            
                                            $archivo_temporal = $files['archivo']->file;
                                        
                                            $carpeta = __DIR__."/../../public/storage";
            
                                            if (!file_exists($carpeta)){
                                                // Si no existe la carpeta la crea
                                                mkdir($carpeta, 0777, true);
                                            }        
                                            $nombre_archivo = "file".date("YmdHis").".".$extension;
                                            $destino = $carpeta.'/'.$nombre_archivo;
                                            move_uploaded_file($archivo_temporal, $destino);
            
                                            $link = $nombre_archivo;                                    
                                        }                                                        
                                    }                        
                                }

                                $nuevo = $eventos->New_Event($id_ticket, $comentario, $link);

                                $respuesta['estado'] = true;
                                                        
                            }else{
                                $respuesta['error'] = "No existe o no se encuentra el id del ticket.";
                            }
                        }else{
                            $respuesta['error'] = "Debe especificar una observacion para poder agregar al historial";
                        }  
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }

                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            // RESERVAS

            $app->get("/reservas", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();                
                $respuesta['estado'] = false;

                // $respuesta['params'] = $params;                
            
                try{
                    $functions = new Functions();

                    $validacion = $functions->Validar_Credenciales($authorization[0]);

                    if ($validacion['estado']){

                        $mysql = new Database('mvevip_crm');                    
                        $id_asesor_visor = $validacion['asesor']['id_asesor'];
                        $acceso_nacional_internacional = $validacion['asesor']['acceso']['tipo'];

                        $eventos = new Events($mysql, $id_asesor_visor);
                        
                        $resultados = [];

                        $buscador = '';
                        if (isset($params['buscador'])){
                            $buscador = "((C.documento LIKE '%".$params['buscador']."%') OR (C.nombres LIKE '%".$params['buscador']."%') OR (C.apellidos LIKE '%".$params['buscador']."%')) AND ";
                        }

                        $id_asesor = '';
                        if (isset($params['id_asesor'])){
                            // $id_asesor = "(T.id_asesor=".$params['id_asesor'].") ";
                        }

                        $por_estado = '';
                        if (isset($params['estado'])){
                            if ($params['estado'] != "-1"){                                
                                $por_estado = "(T.estado=".$params['estado'].")";
                            }else{
                                $por_estado = ' (T.estado>=1) AND ((T.estado!=6) AND (T.estado!=8))';
                            }
                        }                        

                        $por_destino = '';
                        if (isset($params['filtro_destinos'])){
                            $filtro_destinos = json_decode($params['filtro_destinos']);
                            if (is_array($filtro_destinos)){
                                if (count($filtro_destinos) > 0){
                                    $numItems = count($filtro_destinos);
                                    $i = 0;
                                    $por_destino .= "(";
                                    foreach ($filtro_destinos as $linea_filtro) {
                                        if(++$i === $numItems) {
                                            $por_destino .= "(R.destino=".$linea_filtro->id_destino.")";
                                        }else{
                                            $por_destino .= "(R.destino=".$linea_filtro->id_destino.") OR ";
                                        }                                        
                                    }
                                    $por_destino .= ") AND ";
                                }
                            }
                        }

                        $sql = "SELECT T.id_ticket, T.requerimiento, T.fecha_lim_contacto, T.hora_lim_contacto, T.fecha_alta, T.fecha_modificacion, T.estado, T.id_cliente, C.nombres, C.apellidos, C.correo, C.telefono, C.celular, T.id_asesor, A.asesor, A.apodo, T.enviado_proveedor, C.idclienteCRM FROM tickets T, clientes C, asesores A WHERE (".$buscador." ".$id_asesor." ".$por_estado.") AND (T.id_cliente=C.id_cliente) AND (T.id_asesor=A.id_asesor) ORDER BY T.fecha_modificacion DESC, T.id_ticket DESC";

                        $consulta = $mysql->Consulta($sql);

                        // $respuesta['sql'] = $sql;

                        if (is_array($consulta)){
                            if (count($consulta) > 0){
                                foreach ($consulta as $linea) {
                                    $id_ticket = $linea['id_ticket'];
                                    $idclienteCRM = $linea['idclienteCRM'];

                                    $ticket_eventos = $eventos->Get_Event($id_ticket);

                                    $mal_ingreso = [];

                                    $estado = "";
                                    $color = "";
                                    switch ($linea['estado']) {
                                        case 0:
                                            $estado = "Nueva Tarea";
                                            $color = "primary";
                                            break;
                                        case 1:
                                            $estado = "Pendiente de Coordinación";
                                            $color = "warning";
                                            break;
                                        case 2:
                                            $estado = "Pendiente Confirmación de Proveedor";
                                            $color = "warning";
                                            break;
                                        case 3:
                                            $estado = "Pendiente Elaboracion de Reserva";
                                            $color = "info";
                                            break;
                                        case 4:
                                            $estado = "Pendiente Explicacion a Cliente";
                                            $color = "success";
                                            break;
                                        case 6:
                                            $estado = "Tarea Terminada";
                                            $color = "secondary";
                                            break;
                                        case 5:
                                            $estado = "Tarea Anulada";
                                            $color = "danger";
                                            break;
                                        case 7:
                                            $estado = "Espera a Respuesta de Cliente";
                                            $color = "warning";
                                            break;
                                        case 8:
                                            $estado = "Reserva Cancelada";
                                            $color = "danger";
                                            break;
                                    }
                                    
                                    // buscar reserva
                                    $filtra_gps = 0;
                                    $filtrado_destino = 0;
                                    $primer_destino = "";
                                    $check_in_destino = "";
                                    $monto_paquete = 0;
                                    $num_cotizacion = "";
                                    $total_impuestos = 0;                                    

                                    $reservaciones = $mysql->Consulta_Unico("SELECT * FROM reservas WHERE id_ticket=".$id_ticket);
                                    if (isset($reservaciones['id_reserva'])){
                                        $id_reserva = $reservaciones['id_reserva'];
                                        $monto_paquete = $reservaciones['monto_paquete'];
                                        $num_cotizacion = $reservaciones['num_cotizacion'];

                                        $fecha_inicio = "";
                                        if (isset($params['fecha_inicio'])){
                                            $fecha_inicio = $params['fecha_inicio'];
                                        }

                                        $fecha_final = "";
                                        if (isset($params['fecha_final'])){
                                            $fecha_final = $params['fecha_final'];
                                        }

                                        $filtro_fecha = "";
                                        if (($fecha_inicio!='')&&($fecha_final!='')){
                                            $filtro_fecha = "AND (R.check_in BETWEEN '".$fecha_inicio."' AND '".$fecha_final."')";
                                        }

                                        $destinos = $mysql->Consulta("SELECT R.destino, D.nombre, R.check_in, D.area, R.mal_ingreso FROM reservas_destinos R, destinos D WHERE (".$por_destino." (R.id_reserva=".$id_reserva.") ".$filtro_fecha.") AND (R.destino=D.id_destino)");
                                     
                                        if (isset($destinos)){
                                            if (count($destinos) > 0){
                                                foreach ($destinos as $linea_destino) {
                                                    $primer_destino = $linea_destino['nombre'];
                                                    $check_in_destino = $linea_destino['check_in'];
                                                    $area = $linea_destino['area'];

                                                    if ($acceso_nacional_internacional == -1){ // admin (ve todos)
                                                        $filtra_gps = 1;
                                                    }else{                                                        
                                                        if ($area == $acceso_nacional_internacional){
                                                            $filtra_gps = 1;
                                                        }
                                                    }

                                                    $filtrado_destino += 1;

                                                    if (!empty($linea_destino['mal_ingreso'])){
                                                        array_push($mal_ingreso, array(
                                                            "observaciones" => $linea_destino['mal_ingreso']
                                                        ));
                                                    }                                                    
                                                }
                                            }                                        
                                        }

                                        $impuestos = $mysql->Consulta("SELECT * FROM reservas_impuestos WHERE id_reserva=".$id_reserva);
                                        if (is_array($impuestos)){
                                            if (count($impuestos) > 0){
                                                foreach ($impuestos as $linea_impuesto) {
                                                    $total_impuestos += $linea_impuesto['total'];
                                                }
                                            }
                                        }
                                    }

                                    if ($filtra_gps == 1){
                                        if ($filtrado_destino > 0){
                                            array_push($resultados, array(
                                                "id_ticket" => $linea['id_ticket'],
                                                "id_cliente" => $linea['id_cliente'],
                                                "cliente" => array(
                                                    "nombres" => $linea['nombres'],
                                                    "apellidos" => $linea['apellidos'],
                                                    "correo" => $linea['correo'],
                                                    "telefono" => $linea['telefono'],
                                                    "celular" => $linea['celular'],
                                                ),
                                                "id_asesor" => $linea['id_asesor'],
                                                "asesor" => array(
                                                    "nombres" => $linea['asesor'],
                                                    "apodo" => $linea['apodo']
                                                ),
                                                "requerimiento" => $linea['requerimiento'],
                                                "limite" => array(
                                                    "fecha" => $linea['fecha_lim_contacto'],
                                                    "hora" => $linea['hora_lim_contacto'],
                                                ),                                    
                                                "fecha_alta" => $linea['fecha_alta'],
                                                "fecha_modificacion" => $linea['fecha_modificacion'],
                                                "estado" => array(
                                                    "valor" => (int) $linea['estado'],
                                                    "descripcion" => $estado,
                                                    "color" => $color
                                                ),
                                                "eventos" => $ticket_eventos,
                                                "enviado_proveedor" => (int) $linea['enviado_proveedor'],
                                                "primer_destino" => $primer_destino,
                                                "check_in_destino" => $check_in_destino,
                                                "id_usuario" => $id_asesor_visor,
                                                "mal_ingreso" => $mal_ingreso,
                                                "monto_paquete" => (float) $monto_paquete,
                                                "num_cotizacion" => $num_cotizacion,
                                                "idclienteCRM" => $idclienteCRM,
                                                "total_impuestos" => (float) $total_impuestos
                                            ));
                                        }                                        
                                    }                                    
                                }
                            }
                        }

                        $respuesta['consulta'] = $resultados;
                        $respuesta['estado'] = true; 

                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->post("/reservas/{id}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                $files = $request->getUploadedFiles();                
                $id_ticket = $request->getAttribute('id');

                $respuesta['estado'] = false;

                $respuesta['data'] = $data;
                $respuesta['files'] = $files;
            
                try{
                    $functions = new Functions();

                    $validacion = $functions->Validar_Credenciales($authorization[0]);

                    if ($validacion['estado']){

                        if (isset($data['documento'])){
                            $mysql = new Database('mvevip_crm');                    
                            $id_asesor = $validacion['asesor']['id_asesor'];
                            
                            $eventos = new Events($mysql, $id_asesor);

                            $documento = $data['documento'];
                            $nombres = $data['nombres'];
                            $apellidos = $data['apellidos'];
                            $email1 = $data['email1'];
                            $email2 = $data['email2'];
                            $telefono = $data['telefono'];
                            $id_asesor = $data['responsable'];
                            $idclienteCRM = $data['idclienteCRM'];
                            $aerolinea = $data['aerolinea'];
                            $num_adultos = $data['num_adultos'];                    
                            $num_ninos = $data['num_ninos'];
                            if ($num_ninos ==""){
                                $num_ninos = 0;
                            }
                            $num_ninos2 = $data['num_ninos2'];
                            if ($num_ninos2 ==""){
                                $num_ninos2 = 0;
                            }
                            $num_discapacitados_3edad = $data['num_discapacitados_3edad'];
                            if ($num_discapacitados_3edad ==""){
                                $num_discapacitados_3edad = 0;
                            }

                            $pickup_fecha = "1990-01-01";
                            if (isset($data['pickup_fecha'])){
                                $pickup_fecha = $data['pickup_fecha'];
                            }
                            $pickup_hora = "00:00:00";
                            if (isset($data['pickup_hora'])){
                                $pickup_hora = $data['pickup_hora'];
                            }

                            $pickup_destino = $data['pickup_destino'];
                            $pickup_codvuelo = $data['pickup_codvuelo'];

                            $dropoff_fecha = "1990-01-01";
                            if (isset($data['dropoff_fecha'])){
                                $dropoff_fecha = $data['dropoff_fecha'];
                            }
                            $dropoff_hora = "00:00:00";
                            if (isset($data['dropoff_hora'])){
                                $dropoff_hora = $data['dropoff_hora'];
                            }

                            $dropoff_destino = $data['dropoff_destino'];                                            
                            $dropoff_codvuelo = $data['dropoff_codvuelo'];

                            $observaciones = $data['observaciones'];
                            $observacionesReserva = $data['observacionesReserva'];
                            $observaciones_impuestos = $data['observaciones_impuestos'];

                            $incluye_tickets = 0;
                            if (isset($data['incluye_tickets'])){
                                if ($data['incluye_tickets'] == "true"){
                                    $incluye_tickets = 1;
                                }
                            }                        
                            $estado = 0;

                            $nuevo_estado = 1;
                        
                            // verifica q exista el cliente y actualiza su informacion                    
                            $verifica = $mysql->Consulta_Unico("SELECT id_cliente FROM clientes WHERE documento='".$documento."'");

                            if (isset($verifica['id_cliente'])){ // ya existe este documento => lo actualiza 
                                $info_cliente = $verifica['id_cliente'];

                                $actualizacion = $mysql->Modificar("UPDATE clientes SET nombres=?, apellidos=?, telefono=?, correo=?, idclienteCRM=? WHERE id_cliente=?", array($nombres, $apellidos, $telefono, $email1, $idclienteCRM, $info_cliente));


                                // valida que no exista otra reserva adjunta al ticket

                                $valida = $mysql->Consulta_Unico("SELECT * FROM reservas WHERE (id_ticket=".$id_ticket.")");

                                if (!isset($valida['id_reserva'])){
                                    // guarda informacion
                                    $id_reserva = $mysql->Ingreso("INSERT INTO reservas (id_ticket, nombres, apellidos, email1, email2, telefono, id_asesor, aerolinea, num_adultos, num_ninos, num_ninos2, num_discapacitados_3edad, observacionesReserva, pickup_destino, pickup_fecha, pickup_hora, pickup_codvuelo, dropoff_destino, dropoff_fecha, dropoff_hora, dropoff_codvuelo, observaciones, observaciones_impuestos, incluye_ticket, estado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", array($id_ticket, $nombres, $apellidos, $email1, $email2, $telefono, $id_asesor, $aerolinea, $num_adultos, $num_ninos, $num_ninos2, $num_discapacitados_3edad, $observacionesReserva, $pickup_destino, $pickup_fecha, $pickup_hora, $pickup_codvuelo, $dropoff_destino, $dropoff_fecha, $dropoff_hora, $dropoff_codvuelo, $observaciones, $observaciones_impuestos, $incluye_tickets, $estado));

                                    if (!isset($id_reserva['error'])){
                                        $destinos = json_decode($data['destinos']);

                                        // GUARDA LOS DESTINOS
                                        if (is_array($destinos)){
                                            if (count($destinos) > 0){
                                                foreach ($destinos as $linea_destino) {
                                                    $destino = $linea_destino->destino;
                                                    $check_in = $linea_destino->check_in;
                                                    $check_out = $linea_destino->check_out;
                                                    $nombre_hotel = $linea_destino->nombre_hotel;
                                                    $direccion_hotel = $linea_destino->direccion_hotel;
                                                    $observacion = $linea_destino->observaciones;

                                                    $num_reserva = "";
                                                    $voucher = "";
                                                    $recibo = "";                                        
                    
                                                    $id_dest = $mysql->Ingreso("INSERT INTO reservas_destinos (id_reserva, destino, check_in, check_out, nombre_hotel, direccion_hotel, num_reserva, voucher, recibo, observacion) VALUES (?,?,?,?,?,?,?,?,?,?)", array($id_reserva, $destino, $check_in, $check_out, $nombre_hotel, $direccion_hotel, $num_reserva, $voucher, $recibo, $observacion));
                                                }
                                            }
                                        }

                                        $actividades = json_decode($data['actividades']);

                                        if (is_array($actividades)){
                                            if (count($actividades) > 0){
                                                foreach ($actividades as $linea_actividad) {
                                                    $actividad = $linea_actividad->actividad;
                                                    $fecha = $linea_actividad->fecha;                                    
                                                    $observacion = $linea_actividad->observaciones;

                                                    $num_reserva = "";
                                                    $voucher = "";
                                                    $recibo = "";
                                                    $estado_actividad = 0;
                    
                                                    $id_dest = $mysql->Ingreso("INSERT INTO reservas_actividades (id_reserva, actividad, fecha, num_reserva, voucher, recibo, observaciones, estado) VALUES (?,?,?,?,?,?,?,?)", array($id_reserva, $actividad, $fecha, $num_reserva, $voucher, $recibo, $observaciones, $estado_actividad));
                                                }
                                            }
                                        }

                                        $impuestos = json_decode($data['impuestos']);
                                        $lista_impuestos = '';
                                        if (is_array($impuestos)){
                                            $nuevo_estado = 2;

                                            if (count($impuestos) > 0){                                        
                                                $total_impuestos = 0;
                                                foreach ($impuestos as $linea_impuesto) {
                                                    $id_destino = $linea_impuesto->id_destino;
                                                    $descripcion = $linea_impuesto->destino;
                                                    $num_noches = $linea_impuesto->num_noches;
                                                    $valor = $linea_impuesto->valor;
                                                    $total = $linea_impuesto->total;
                                                
                                                    $id_imp = $mysql->Ingreso("INSERT INTO reservas_impuestos (id_reserva, id_destino, descripcion, num_noches, valor, total) VALUES (?,?,?,?,?,?)", array($id_reserva, $id_destino, $descripcion, $num_noches, $valor, $total));


                                                    $lista_impuestos .= '<tr>';
                                                    $lista_impuestos .= '<td style="text-align: left;">';
                                                    $lista_impuestos .= '<p style="font-family: `Tahoma`; margin: 0; padding: 0 0.5em; font-size: 13px; color: #666666;">'.$descripcion.'</p>';
                                                    $lista_impuestos .= '</td>';
                                                    $lista_impuestos .= '<td style="text-align: left; width: 100px;">';
                                                    $lista_impuestos .= '<p style="font-family: `Tahoma`; margin: 0; padding: 0 0.5em; font-size: 13px; color: #666666;">'.$num_noches.'</p>';
                                                    $lista_impuestos .= '</td>';
                                                    $lista_impuestos .= '<td style="text-align: right; width: 100px;">';
                                                    $lista_impuestos .= '<p style="font-family: `Tahoma`; margin: 0; padding: 0 0.5em; font-size: 13px; color: #666666;">'.number_format($valor, 2).'</p>';
                                                    $lista_impuestos .= '</td>';
                                                    $lista_impuestos .= '<td style="text-align: right; width: 100px;">';
                                                    $lista_impuestos .= '<p style="font-family: `Tahoma`; margin: 0; padding: 0 0.5em; font-size: 13px; color: #666666;">'.number_format($total ,2).'</p>';
                                                    $lista_impuestos .= '</td>';
                                                    $lista_impuestos .= '</tr>';
                                                    
                                                    $total_impuestos += $total;
                                                }

                                                $lista_impuestos .= '<tr>';
                                                $lista_impuestos .= '<td style="text-align: left;">';
                                                $lista_impuestos .= '<p style="font-family: `Tahoma`; margin: 0; padding: 0 0.5em; font-size: 13px; font-weight: bold; color: #666666;"></p>';
                                                $lista_impuestos .= '</td>';
                                                $lista_impuestos .= '<td style="text-align: left; width: 100px;">';
                                                $lista_impuestos .= '<p style="font-family: `Tahoma`; margin: 0; padding: 0 0.5em; font-size: 13px; font-weight: bold; color: #666666;"></p>';
                                                $lista_impuestos .= '</td>';
                                                $lista_impuestos .= '<td style="text-align: right; width: 100px;">';
                                                $lista_impuestos .= '<p style="font-family: `Tahoma`; margin: 0; padding: 0 0.5em; font-size: 13px; font-weight: bold; color: #666666;">TOTAL</p>';
                                                $lista_impuestos .= '</td>';
                                                $lista_impuestos .= '<td style="text-align: right; width: 100px;">';
                                                $lista_impuestos .= '<p style="font-family: `Tahoma`; margin: 0; padding: 0 0.5em; font-size: 13px; font-weight: bold; color: #666666;">'.number_format($total_impuestos ,2).'</p>';
                                                $lista_impuestos .= '</td>';
                                                $lista_impuestos .= '</tr>';
                                            }
                                        }
                    
                                        // guarda archivos adjuntos
                    
                                        // cedulas y tkt
                    
                                        if (isset($files)){
                                            // $tkt = $files['tkt']->file;
                                            if (is_array($files)){
                                                if (count($files) > 0){
                                                    foreach ($files as $key => $value) {                                    
                                                        $identificador = explode("_", $key)[0];

                                                        // obtiene el nombre del archivo para obtener la extension                                                    
                                                        $ona = $data['nombre_'.$key];

                                                        $separacion = explode(".", $ona);
                                                        $separacion_lng = count($separacion);
                                                        if ($separacion_lng > 0){
                                                            $extension = $separacion[($separacion_lng - 1)];
                        
                                                            $archivo_temporal = $files[$key]->file;
                                                        
                                                            $carpeta = __DIR__."/../../public/storage";
                        
                                                            if (!file_exists($carpeta)){
                                                                // Si no existe la carpeta la crea
                                                                mkdir($carpeta, 0777, true);
                                                            }        
                                                            $nombre_archivo = $identificador.date("YmdHis").".".$extension;
                                                            $destino = $carpeta.'/'.$nombre_archivo;
                                                            move_uploaded_file($archivo_temporal, $destino);
                                                                                                                             
                                                            $link = $nombre_archivo;                                                     
                        
                                                            $id_adjunto = $mysql->Ingreso("INSERT INTO reservas_adjuntos (id_reserva, identificador, link) VALUES (?,?,?)", array($id_reserva, $identificador, $link));
                                                            
                                                            sleep(1);
                                                        }
                                                    }
                                                }
                                            }
                                        }

                                        $nuevo_evento = $eventos->New_Event($id_ticket, "Ingreso de Reserva");

                                        // Modifica el estado del ticket
                                        $fecha_modificacion = date("Y-m-d H:i:s");
                                        $modifica = $mysql->Modificar("UPDATE tickets SET estado=?, fecha_modificacion=? WHERE id_ticket=?", array($nuevo_estado, $fecha_modificacion, $id_ticket));

                                        /// Envio de correo electronico

                                        $busqueda = $functions->Informacion_Ticket_Reserva($mysql, $id_ticket);
                                        if ($busqueda['estado']){
                                            $informacion = $busqueda['informacion'];

                                            $mail = new Email($validacion['asesor']['correo'], $validacion['asesor']['password'], $validacion['asesor']['asesor']);

                                            $data = array(
                                                "correo" => $informacion['cliente']['correo'],
                                                "destinatario" => $informacion['cliente']['nombres_apellidos'],
                                                "asunto" => "Estimad@ ".$informacion['cliente']['nombres_apellidos']." su reserva se encuentra en proceso de emisión",
                                                "cliente" => $informacion['cliente'],
                                                "pasajeros" => $informacion['pasajeros'],
                                                "destinos" => $informacion['destinos'],
                                                "actividades" => $informacion['actividades'],
                                                "transfers" => $informacion['transfers']
                                            );

                                            $copias = [];
                                            $adjuntos = $informacion['adjuntos'];

                                            // "correo" => $informacion['cliente']['email2'],

                                            array_push($copias, array(
                                                "correo" => $validacion['asesor']['correo'],
                                                // "correo" => "fsarzosa@mvevip.com"
                                            ));

                                            $reply = array(
                                                "correo" => $informacion['cliente']['correo_asesor'],
                                                "nombre" => $informacion['cliente']['asesor']
                                            );

                                            $respuesta['envio'] = $mail->Enviar_Email($data, $copias, $adjuntos, $reply, "nueva_reserva");

                                            sleep(2);
                                                                                
                                            /// Envio de correo de tasas hoteleres
                                            if (is_array($impuestos)){
                                                if (count($impuestos) > 0){
                                                    $adjuntos = [];

                                                    $mail2 = new Email($validacion['asesor']['correo'], $validacion['asesor']['password'], $validacion['asesor']['asesor']);

                                                    if ($informacion['galapagos'] != ''){
                                                        $observaciones_impuestos .= "<br><br><br>";
                                                        $observaciones_impuestos .= $informacion['galapagos'];

                                                        // adjunta archivos en informacion de galapagos
                                                        $carpeta = __DIR__."/../../public/mails/nuevareserva";
                                                        array_push($adjuntos, array(
                                                            "link" => $carpeta."/formulario_salud_viajero.pdf"
                                                        ));
                                                        array_push($adjuntos, array(
                                                            "link" => $carpeta."/ingreso_galapagos.jpeg"
                                                        ));
                                                        array_push($adjuntos, array(
                                                            "link" => $carpeta."/req_sep2.jpg"
                                                        ));                                                    
                                                    }

                                                    $data_hoteleras = array(
                                                        "correo" => $informacion['cliente']['correo'],
                                                        "destinatario" => $informacion['cliente']['nombres_apellidos'],
                                                        "asunto" => "Estimad@ ".$informacion['cliente']['nombres_apellidos']." sus tasas hoteleras",
                                                        "cliente" => $informacion['cliente'],
                                                        "impuestos" => $lista_impuestos,
                                                        "observaciones_impuestos" => $observaciones_impuestos
                                                    );                                                
            
                                                    $respuesta['envio_tasas'] = $mail2->Enviar_Tasas($data_hoteleras, $copias, $adjuntos, $reply, "tasa_hotelera");
                                                }
                                            }
                                            
                                        }else{
                                            $respuesta['error'] = $busqueda['error'];
                                        }
                    
                                        $respuesta['estado'] = true;
                                        $respuesta['id_reserva'] = $id_reserva;
                                    }else{
                                        $respuesta['error'] = "Existe un error al ingresar la informacion";
                                        $respuesta['details'] = $id_reserva['error'];
                                    }            
                                }else{
                                    $respuesta['error'] = "Ya existe una reserva adjunta al ticket";
                                }    
                            }else{
                                $respuesta['error'] = "El documento del cliente es incorrecto o no existe.";
                            }
                        }else{
                            $respuesta['error'] = "No enviar mas de 3 archivos separados. Favor unificar en un solo archivo.";
                        }                        
                        
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }                                        

                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->post("/reservas_numeros/{id}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                $files = $request->getUploadedFiles();                
                $id_ticket = $request->getAttribute('id');

                $respuesta['estado'] = false;

                $respuesta['datas'] = $data;
                $respuesta['files'] = $files;
            
                try{
                    $functions = new Functions();

                    $validacion = $functions->Validar_Credenciales($authorization[0]);

                    if ($validacion['estado']){
                        $mysql = new Database('mvevip_crm');
                        $id_asesor = $validacion['asesor']['id_asesor'];                    
                        $eventos = new Events($mysql, $id_asesor);
                        
                        $valida_ticket = $mysql->Consulta_Unico("SELECT id_ticket, id_cliente FROM tickets WHERE id_ticket=".$id_ticket);

                        if (isset($valida_ticket['id_ticket'])){
                                // ACTUALIZACION DE CAMPOS DE LA RESERVACION
                                $id_cliente = $valida_ticket['id_cliente'];

                                $reservacion = $mysql->Consulta_Unico("SELECT id_reserva FROM reservas WHERE id_ticket=".$id_ticket);

                                if (isset($reservacion['id_reserva'])){
                                    $id_reserva = $reservacion['id_reserva'];                                    

                                    $nombres = $data['nombres'];
                                    $apellidos = $data['apellidos'];
                                    $email1 = $data['email1'];
                                    $email2 = $data['email2'];
                                    $telefono = $data['telefono'];
                                    $idclienteCRM = $data['idclienteCRM'];
                                    // $responsable = $data['responsable'];

                                    $aerolinea = $data['aerolinea'];
                                    $adultos = $data['adultos'];
                                    $ninos1 = $data['ninos1'];
                                    $ninos2 = $data['ninos2'];
                                    $discapacidad = $data['discapacidad'];
                                    $observacion_reserva = $data['observacion_reserva'];

                                    $pickup_destino = $data['pickup_destino'];
                                    $pickup_fecha = $data['pickup_fecha'];
                                    $pickup_hora = $data['pickup_hora'];
                                    $pickup_vuelo = $data['pickup_vuelo'];

                                    $dropoff_destino = $data['dropoff_destino'];
                                    $dropoff_fecha = $data['dropoff_fecha'];
                                    $dropoff_hora = $data['dropoff_hora'];
                                    $dropoff_vuelo = $data['dropoff_vuelo'];

                                    $observacionesTransfer = $data['observacionesTransfer'];

                                    $num_reserva_transfer = $data['num_reserva_transfer'];
                                    $costo_transfer = $data['costo_transfer'];
                                    $id_forma_pago_transfer = $data['id_forma_pago_transfer'];
                                    $id_proveedor_transfer = $data['id_proveedor_transfer'];

                                    $conductor_transfer = "";
                                    if ((isset($data['conductorTransfer'])) && (!empty($data['conductorTransfer']))){
                                        $conductor_transfer = $data['conductorTransfer'];
                                    }
                                    

                                    $valor_reserva = 0;
                                    if (isset($data['valor_reserva'])){
                                        $valor_reserva = floatval($data['valor_reserva']);
                                    }                                    

                                    $numero_cotizacion_crm = 0;
                                    if (isset($data['numero_cotizacion_crm'])){
                                        if ($data['numero_cotizacion_crm'] > 0){
                                            $numero_cotizacion_crm = $data['numero_cotizacion_crm'];
                                        }                                        
                                    }
                                    
                                    $incluye_transfer_alojamiento = 0;
                                    if (isset($data['opcion_sin_auto'])){
                                        $incluye_transfer_alojamiento = $data['opcion_sin_auto'];
                                    }

                                    $pago_impuestos_completo = false;
                                    if (isset($data['pago_impuestos_completo'])){
                                        $pago_impuestos_completo = $data['pago_impuestos_completo'];
                                    }

                                    $observaciones_pago_crm = "";
                                    if (isset($data['observaciones_pago_crm'])){
                                        if ($pago_impuestos_completo == "true"){
                                            $observaciones_pago_crm = $data['observaciones_pago_crm'];
                                        }                                        
                                    }

                                    $modificacion = $mysql->Modificar("UPDATE reservas SET nombres=?, apellidos=?, email1=?, email2=?, telefono=?, aerolinea=?, num_adultos=?, num_ninos=?, num_ninos2=?, num_discapacitados_3edad=?, observacionesReserva=?, pickup_destino=?, pickup_fecha=?, pickup_hora=?, pickup_codvuelo=?, dropoff_destino=?, dropoff_fecha=?, dropoff_hora=?, dropoff_codvuelo=?, observaciones=?, num_reserva_transfer=?, costo_transfer=?, id_forma_pago_transfer=?, id_proveedor_transfer=?, monto_paquete=?, incluye_transfer_alojamiento=?, observaciones_pago_crm=?, conductor_auto=? WHERE id_reserva=?", array($nombres, $apellidos, $email1, $email2, $telefono, $aerolinea, $adultos, $ninos1, $ninos2, $discapacidad, $observacion_reserva, $pickup_destino, $pickup_fecha, $pickup_hora, $pickup_vuelo, $dropoff_destino, $dropoff_fecha, $dropoff_hora, $dropoff_vuelo, $observacionesTransfer, $num_reserva_transfer, $costo_transfer, $id_forma_pago_transfer, $id_proveedor_transfer, $valor_reserva, $incluye_transfer_alojamiento, $observaciones_pago_crm, $conductor_transfer, $id_reserva));

                                    // Verifica si el id de proveedor es IVIS (id=75)
                                    if ($id_proveedor_transfer == 75){
                                        $busca_ivis = $mysql->Consulta_Unico('SELECT * FROM reservas_ivis WHERE id_reserva='.$id_reserva);
                                        if (isset($busca_ivis['id_ivis'])){
                                            $id_ivis = $busca_ivis['id_ivis'];

                                            // Actualiza valores
                                            $modificar = $mysql->Modificar("UPDATE reservas_ivis SET id_forma_pago_transfer=?, id_proveedor_transfer=? WHERE id_ivis=?", array($id_forma_pago_transfer, $id_proveedor_transfer, $id_ivis));
                                        }else{
                                            $fee_costo = 40;
                                            $ingreso = $mysql->Ingreso("INSERT INTO reservas_ivis (id_reserva, num_reserva_transfer, costo_transfer, id_proveedor_transfer, id_forma_pago_transfer) VALUES (?,?,?,?,?)", array($id_reserva, $num_reserva_transfer, $fee_costo, $id_proveedor_transfer, $id_forma_pago_transfer));
                                        }
                                    }

                                    // actualiza numero de cotizacion del CRM solamente si tiene un valor
                                    if ($numero_cotizacion_crm > 0){
                                        $actualizacion_crm = $mysql->Modificar("UPDATE reservas SET num_cotizacion=? WHERE id_reserva=?", array($numero_cotizacion_crm, $id_reserva));
                                    }

                                    $anulacion_cotizacion_crm = false;
                                    if (isset($data['anulacion_cotizacion_crm'])){
                                        $anulacion_cotizacion_crm = $data['anulacion_cotizacion_crm'];
                                    }                                   

                                    // ANULA EL PAGO DE IMPUESTOS
                                    if ($anulacion_cotizacion_crm == "true"){
                                        $modificacion = $mysql->Modificar("UPDATE reservas SET pagoimpuestos=?, num_cotizacion=?, observaciones_pago_crm=? WHERE id_reserva=?", array("", "", "", $id_reserva));
                                        // BUSCAR ARCHIVO Y ELIMINAR DEL SERVIDOR
                                    }

                                    // actualizacion informacino de cliente
                                    $actualiza_cliente = $mysql->Modificar("UPDATE clientes SET nombres=?, apellidos=?, correo=?, telefono=?, idclienteCRM=? WHERE id_cliente=?", array($nombres, $apellidos, $email1, $telefono, $idclienteCRM, $id_cliente));

                                    $nuevo_evento = $eventos->New_Event($id_ticket, "Actualizacion de Información de Reserva");
                                    $fecha_modificacion = date("Y-m-d H:i:s");

                                    $actualiza = $mysql->Modificar("UPDATE tickets SET fecha_modificacion=? WHERE id_ticket=?", array($fecha_modificacion, $id_ticket));

                                    $cuadro_impuestos = [];
                                    if (isset($data['cuadro_impuestos'])){
                                        $cuadro_impuestos = json_decode($data['cuadro_impuestos']);

                                        if (is_array($cuadro_impuestos)){
                                            if (count($cuadro_impuestos) > 0){
                                                foreach ($cuadro_impuestos as $linea_imp) {
                                                    $id_impuesto = $linea_imp->id_impuesto;

                                                    $descripcion = $linea_imp->descripcion;
                                                    $num_noches = $linea_imp->num_noches;
                                                    $valor = $linea_imp->valor;
                                                    $total = $linea_imp->total;

                                                    if (floatval($total) > 0){
                                                        $actualizar = $mysql->Modificar("UPDATE reservas_impuestos SET descripcion=?, num_noches=?, valor=?, total=? WHERE id_impuesto=?", array($descripcion, $num_noches, $valor, $total, $id_impuesto));
                                                    }else if (floatval($total) <= 0){
                                                        $eliminar = $mysql->Ejecutar("DELETE FROM reservas_impuestos WHERE id_impuesto=?", array($id_impuesto));
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    
                                }

                                /// ACTUALIZA CAMPOS DE DESTINOS
                                if (isset($data['destinos_modificados'])){
                                    $destinos_modificados = json_decode($data['destinos_modificados']);

                                    $respuesta['destinos_modificados'] = $destinos_modificados;

                                    if (is_array($destinos_modificados)){
                                        if (count($destinos_modificados) > 0){
                                            foreach ($destinos_modificados as $linea_destino_modificado) {
                                                $id_destinos = $linea_destino_modificado->id_destinos;
                                                $check_in = $linea_destino_modificado->check_in;
                                                $check_out = $linea_destino_modificado->check_out;
                                                $costo = $linea_destino_modificado->costo;
                                                $id_forma_pago = $linea_destino_modificado->id_forma_pago;
                                                $id_proveedor = $linea_destino_modificado->id_proveedor;
                                                $observacion = $linea_destino_modificado->observacion;
                                                $id_destino_modificado = $linea_destino_modificado->id_destino;

                                                $nombre_hotel = $linea_destino_modificado->nombre_hotel;
                                                $direccion_hotel = $linea_destino_modificado->direccion_hotel;

                                                $actualiza = $mysql->Modificar("UPDATE reservas_destinos SET destino=?, check_in=?, check_out=?, costo=?, id_forma_pago=?, id_proveedor=?, observacion=?, nombre_hotel=?, direccion_hotel=? WHERE id_destinos=?", array($id_destino_modificado, $check_in, $check_out, $costo, $id_forma_pago, $id_proveedor, $observacion, $nombre_hotel, $direccion_hotel, $id_destinos));
                                            }
                                        }
                                    }
                                }

                                /// ACTUALIZA CAMPOS DE ACTIVIDADES
                                if (isset($data['actividades_modificadas'])){
                                    $actividades_modificadas = json_decode($data['actividades_modificadas']);
                                    
                                    $respuesta['act_mod'] = $actividades_modificadas;

                                    if (is_array($actividades_modificadas)){
                                        if (count($actividades_modificadas) > 0){
                                            foreach ($actividades_modificadas as $linea_actividad_modificada) {
                                                $id_actividad = $linea_actividad_modificada->id_actividad;
                                                $actividad = $linea_actividad_modificada->actividad;
                                                $fecha = $linea_actividad_modificada->fecha;
                                                $numero_reserva = $linea_actividad_modificada->numero_reserva;
                                                $observaciones = $linea_actividad_modificada->observaciones;
                                                $id_forma_pago = $linea_actividad_modificada->id_forma_pago;
                                                $id_proveedor = $linea_actividad_modificada->id_proveedor;
                                                $costo = $linea_actividad_modificada->costo;
                                                $observaciones = $linea_actividad_modificada->observaciones;

                                                $actualiza = $mysql->Modificar("UPDATE reservas_actividades SET actividad=?, fecha=?, num_reserva=?, observaciones=?, id_forma_pago=?, id_proveedor=?, costo=? WHERE id_actividad=?", array($actividad, $fecha, $numero_reserva, $observaciones, $id_forma_pago, $id_proveedor, $costo, $id_actividad));
                                            }
                                        }
                                    }
                                }
                                
                                /// ACTUALIZACION DE IMAGENES DESTINOS, ACTIVIDADES
                                                    
                                foreach ($data as $key => $value) {
                                    $separa = explode("_", $key);
            
                                    if (isset($separa[2])){
                                        $id = $separa[2];
                                        $campo = $separa[1];
                                        $tag = $separa[0];
                                        $valor = $value;
                
                                        if ((trim($valor) !='') && ($valor != 'undefined')){
                                            if ($tag == "d"){ // destinos
                                                $dato_num_reserva = "";
                                                $dato_nombre_hotel = "";
                                                $dato_direccion_hotel = "";
            
                                                switch ($campo) {
                                                    case 'direccionhotel':                                            
                                                        $actualiza = $mysql->Modificar("UPDATE reservas_destinos SET direccion_hotel=? WHERE id_destinos=?", array($valor, $id));
                                                        $nuevo_evento = $eventos->New_Event($id_ticket, "Se modifico la direccion del hotel");
                                                        break;
                                                    case 'nombrehotel':                                            
                                                        $actualiza = $mysql->Modificar("UPDATE reservas_destinos SET nombre_hotel=? WHERE id_destinos=?", array($valor, $id));
                                                        $nuevo_evento = $eventos->New_Event($id_ticket, "Se modifico el nombre del hotel");
                                                        break;
                                                    case 'numeroreserva':                                            
                                                        $actualiza = $mysql->Modificar("UPDATE reservas_destinos SET num_reserva=? WHERE id_destinos=?", array($valor, $id));
                                                        $nuevo_evento = $eventos->New_Event($id_ticket, "Se modifico el numero de reserva del destino");
                                                        break;
                                                }
                                                
                                            }else if ($tag == "a"){ // actividades
                                                $actualiza = $mysql->Modificar("UPDATE reservas_actividades SET num_reserva=? WHERE id_actividad=?", array($valor, $id));
                                                $nuevo_evento = $eventos->New_Event($id_ticket, "Se modifico el numero de reserva de la actividad");
                                            }
                                        }
                                    }
                                    
                                }
            
                                $respuesta['archivo'] = [];
                                foreach ($files as $key => $value) {
                                    $separa = explode("_", $key);
            
                                    if (isset($separa[2])){
                                        $id = $separa[2];
                                        $campo = $separa[1];
                                        $tag = $separa[0];
                                        $valor = $value;
    
                                        // obtiene el nombre del archivo para obtener la extension                                                    
                                        $ona = $data['nombre_'.$key];
                                        $extension = pathinfo($ona, PATHINFO_EXTENSION);
                                        // $ext = explode(".", $ona);
                                        // $extension = $ext[1];
                                        // $extension = "pdf";
    
                                        $identificador = $campo;
                                        $archivo_temporal = $files[$key]->file;
                                    
                                        $carpeta = __DIR__."/../../public/storage";
                                        $respuesta['carpeta'] = $carpeta;
    
                                        if (!file_exists($carpeta)){
                                            // Si no existe la carpeta la crea
                                            mkdir($carpeta, 0777, true);
                                        }        
                                        $nombre_archivo = $identificador.$id.date("YmdHis").".".$extension;
                                        $destino = $carpeta.'/'.$nombre_archivo;
                                        move_uploaded_file($archivo_temporal, $destino);
                                        
                                        $link = $nombre_archivo;                                                     
    
                                        array_push($respuesta['archivo'], array($key => $value, "link" => $link));
    
                                        switch ($campo) {
                                            case 'voucher':
                                                if ($tag == "d"){
                                                    $actualiza = $mysql->Modificar("UPDATE reservas_destinos SET voucher=? WHERE id_destinos=?", array($link, $id));
                                                    $nuevo_evento = $eventos->New_Event($id_ticket, "Se agrego voucher al destino");
                                                }else if ($tag == "a"){
                                                    $actualiza = $mysql->Modificar("UPDATE reservas_actividades SET voucher=? WHERE id_actividad=?", array($link, $id));
                                                    $nuevo_evento = $eventos->New_Event($id_ticket, "Se agrego voucher a la actividad");
                                                }
                                                break;
                                            case 'recibo':                                                                                
                                                if ($tag == "d"){
                                                    $actualiza = $mysql->Modificar("UPDATE reservas_destinos SET recibo=? WHERE id_destinos=?", array($link, $id));
                                                    $nuevo_evento = $eventos->New_Event($id_ticket, "Se agrego recibo al destino");
                                                }else if ($tag == "a"){
                                                    $actualiza = $mysql->Modificar("UPDATE reservas_actividades SET recibo=? WHERE id_actividad=?", array($link, $id));
                                                    $nuevo_evento = $eventos->New_Event($id_ticket, "Se agrego recibo a la actividad");
                                                }
                                                break;
                                            case 'reciboliquidacion':
                                                if ($tag == "d"){
                                                    $actualiza = $mysql->Modificar("UPDATE reservas_destinos SET recibo_liquidacion=? WHERE id_destinos=?", array($link, $id));
                                                    $nuevo_evento = $eventos->New_Event($id_ticket, "Se agrego liquidacion al destino");
                                                }
                                                break;                                
                                            case 'cedula':
                                                $id_adjunto = $mysql->Ingreso("INSERT INTO reservas_adjuntos (id_reserva, identificador, link) VALUES (?,?,?)", array($id_reserva, "cedula", $link));                                      
                                                break; 
                                        }       
                                        
                                        sleep(1);
                                    }
                                    
                                    if (isset($files['pagoimpuestos'])){
                                        $pago_impuestos_completo = false;
                                        if (isset($data['pago_impuestos_completo'])){
                                            $pago_impuestos_completo = $data['pago_impuestos_completo'];
                                        }

                                        if ($pago_impuestos_completo == "true"){
                                            // PAGO DE IMPUESTOS
                                            $identificador = "imp";
                                            $archivo_temporal = $files["pagoimpuestos"]->file;

                                            // obtiene el nombre del archivo para obtener la extension                                                    
                                            // $ona = $data['nombre_'.$key];
                                            // $ext = explode(".", $ona);
                                            // $extension = $ext[1];
                                            // $extension = "pdf";

                                            $carpeta = __DIR__."/../../public/storage";
                                            // $respuesta['carpeta'] = $carpeta;

                                            if (!file_exists($carpeta)){
                                                // Si no existe la carpeta la crea
                                                mkdir($carpeta, 0777, true);
                                            }        
                                            $nombre_archivo = $identificador.date("YmdHis").".jpg";
                                            $destino = $carpeta.'/'.$nombre_archivo;
                                            move_uploaded_file($archivo_temporal, $destino);

                                            $link = $nombre_archivo;                                                    

                                            $actualiza = $mysql->Modificar("UPDATE reservas SET pagoimpuestos=? WHERE id_ticket=?", array($link, $id_ticket));
                                            $nuevo_evento = $eventos->New_Event($id_ticket, "Se agrego pago de impuestos al destino");
                                        }                                        
                                    }  
                                    
                                    if (isset($files['voucherauto'])){
                                        // PAGO DE IMPUESTOS
                                        $identificador = "vau";
                                        $archivo_temporal = $files["voucherauto"]->file;
    
                                        // obtiene el nombre del archivo para obtener la extension                                                    
                                        // $ona = $data['nombre_'.$key];
                                        // $ext = explode(".", $ona);
                                        // $extension = $ext[1];
                                        // $extension = "pdf";
    
                                        $carpeta = __DIR__."/../../public/storage";
                                        // $respuesta['carpeta'] = $carpeta;
    
                                        if (!file_exists($carpeta)){
                                            // Si no existe la carpeta la crea
                                            mkdir($carpeta, 0777, true);
                                        }        
                                        $nombre_archivo = $identificador.date("YmdHis").".pdf";
                                        $destino = $carpeta.'/'.$nombre_archivo;
                                        move_uploaded_file($archivo_temporal, $destino);
    
                                        $link = $nombre_archivo;                                                    
    
                                        $actualiza = $mysql->Modificar("UPDATE reservas SET voucher_auto=? WHERE id_ticket=?", array($link, $id_ticket));
                                        $nuevo_evento = $eventos->New_Event($id_ticket, "Se agrego voucher auto transfer");
                                    }  
    
                                    if (isset($files['reciboauto'])){
                                        // PAGO DE IMPUESTOS
                                        $identificador = "rvau";
                                        $archivo_temporal = $files["reciboauto"]->file;
    
                                        // obtiene el nombre del archivo para obtener la extension                                                    
                                        // $ona = $data['nombre_'.$key];
                                        // $ext = explode(".", $ona);
                                        // $extension = $ext[1];
                                        // $extension = "pdf";
    
                                        $carpeta = __DIR__."/../../public/storage";
                                        // $respuesta['carpeta'] = $carpeta;
    
                                        if (!file_exists($carpeta)){
                                            // Si no existe la carpeta la crea
                                            mkdir($carpeta, 0777, true);
                                        }        
                                        $nombre_archivo = $identificador.date("YmdHis").".pdf";
                                        $destino = $carpeta.'/'.$nombre_archivo;
                                        move_uploaded_file($archivo_temporal, $destino);
    
                                        $link = $nombre_archivo;                                                    
    
                                        $actualiza = $mysql->Modificar("UPDATE reservas SET recibo_auto=? WHERE id_ticket=?", array($link, $id_ticket));
                                        $nuevo_evento = $eventos->New_Event($id_ticket, "Se agrego recibo auto transfer");
                                    }  
                                }
            
                                // VERIFICA QUE LOS NUMEROS DE RESERVA ESTEN INGRESADOS
                                $reserva = $mysql->Consulta_Unico("SELECT id_reserva FROM reservas WHERE id_ticket=".$id_ticket);
            
                                if (isset($reserva['id_reserva'])){                    
                                    $destinos = $mysql->Consulta("SELECT * FROM reservas_destinos WHERE id_reserva=".$reserva['id_reserva']);
                                    $actividades = $mysql->Consulta("SELECT * FROM reservas_actividades WHERE id_reserva=".$reserva['id_reserva']);
            
                                    $contador_total_archivos = 0;
                                    $contador_total_archivos += count($destinos);
                                    $contador_total_archivos += count($actividades);
                                    // DESTINOS
    
                                    // Verifica que exista los dos archivos de voucher y recibo
                                    $contador_archivos = 0;
                                    foreach ($destinos as $linea) {
                                        if (($linea['voucher'] != "") && ($linea['recibo'] != "")){
                                            $contador_archivos += 1;
                                        }
                                    }
    
                                    /// ACTIVIDADES
                                    
                                    foreach ($actividades as $linea) {
                                        if (($linea['voucher'] != "") && ($linea['recibo'] != "")){
                                            $contador_archivos += 1;
                                        }
                                    }
                                    $respuesta['contadores'] = array(
                                        "contador_archivos" => $contador_archivos,
                                        "contador_total" => $contador_total_archivos
                                    );
    
                                    if ($contador_archivos == $contador_total_archivos){
                                        $actualiza = $mysql->Modificar("UPDATE tickets SET estado=3 WHERE id_ticket=?", array($id_ticket));
                                        $nuevo_evento = $eventos->New_Event($id_ticket, "Se cambio el estado de la reservacion");
                                    }                        
    
    
                                    /// DESTINOS
            
                                    $contador = 0;
                                    $completo_destinos = 0;
                                    foreach ($destinos as $linea) {
                                        // if (($linea['num_reserva'] != "") && ($linea['nombre_hotel'] != "") && ($linea['direccion_hotel'] != "")){
                                        if (($linea['nombre_hotel'] != "") && ($linea['direccion_hotel'] != "")){
                                            $contador += 1;
                                        }
                                    }
            
                                    if ($contador == count($destinos)){
                                        $completo_destinos = 1;
                                    }
                                        
                                    // ACTIVIDADES
            
                                    $contador = 0;
                                    $completo_actividades = 0;
                                    foreach ($actividades as $linea) {
                                        if ($linea['num_reserva'] != ""){
                                            $contador += 1;
                                        }
                                    }
            
                                    if ($contador == count($actividades)){
                                        $completo_actividades = 1;
                                    }
                                        
                                    if (($completo_destinos == 1) && ($completo_actividades == 1)){
                                        $listado_impuestos = $mysql->Consulta_Unico("SELECT COUNT(id_impuesto) AS total FROM reservas_impuestos WHERE id_reserva=".$reserva['id_reserva']);
    
                                        if (isset($listado_impuestos['total'])){
                                            if ($listado_impuestos['total'] > 0){
                                                $verifica_pago_impuesto = $mysql->Consulta_Unico("SELECT pagoimpuestos FROM reservas WHERE id_ticket=".$id_ticket);
    
                                                if (isset($verifica_pago_impuesto['pagoimpuestos'])){
                                                    if ($verifica_pago_impuesto['pagoimpuestos'] != ""){
                                                        $actualiza = $mysql->Modificar("UPDATE tickets SET estado=4 WHERE id_ticket=?", array($id_ticket));
                                                        $nuevo_evento = $eventos->New_Event($id_ticket, "Se cambio el estado de la reservacion");
                                                    }
                                                }                                
                                            }else{
                                                $actualiza = $mysql->Modificar("UPDATE tickets SET estado=4 WHERE id_ticket=?", array($id_ticket));
                                                        $nuevo_evento = $eventos->New_Event($id_ticket, "Se cambio el estado de la reservacion");
                                            }
                                        }else{
                                            $actualiza = $mysql->Modificar("UPDATE tickets SET estado=4 WHERE id_ticket=?", array($id_ticket));
                                            $nuevo_evento = $eventos->New_Event($id_ticket, "Se cambio el estado de la reservacion");
                                        }                                
                                    }
                                    
                                    if (($completo_destinos == 1)){
                                        $actualiza = $mysql->Modificar("UPDATE tickets SET estado=4 WHERE id_ticket=?", array($id_ticket));
                                    }
                                    
                                }


                                $reservacion = $mysql->Consulta_Unico("SELECT id_reserva, enviado_contabilidad FROM reservas WHERE id_ticket=".$id_ticket);
                                if (isset($reservacion['id_reserva'])){
                                    if ($reservacion['enviado_contabilidad'] == 0){
                                        /// VERIFICA SI EXISTEN EL RECIBO Y LIQUIDACION PARA ENVIAR A CONTABILIDAD
                                        $destinos_archivos = $mysql->Consulta("SELECT recibo, recibo_liquidacion FROM reservas_destinos WHERE id_reserva=".$reservacion['id_reserva']);
                                        if (is_array($destinos_archivos)){
                                            $contador_conta = 0;
                                            if (count($destinos_archivos) > 0){

                                                foreach ($destinos_archivos as $linea_destino_archivo) {
                                                    if (($linea_destino_archivo['recibo'] != "") && ($linea_destino_archivo['recibo_liquidacion'] != "")){
                                                        $contador_conta += 1;
                                                    }
                                                }
                                            }

                                            if ($contador_conta == count($destinos_archivos)){
                                                $busqueda = $functions->Informacion_Ticket_Reserva($mysql, $id_ticket);
                                                $respuesta['busqueda'] = $busqueda;
                                                if ($busqueda['estado']){
                                                    $informacion = $busqueda['informacion'];

                                                    $nombre_cliente = $informacion['cliente']['nombres_apellidos'];
                                                    $idcrm = $informacion['cliente']['idclienteCRM'];

                                                    $total_pasajeros = 0;
                                                    $total_pasajeros += $informacion['pasajeros']['adultos'];
                                                    $total_pasajeros += $informacion['pasajeros']['ninos1'];
                                                    $total_pasajeros += $informacion['pasajeros']['ninos2'];
                                                    $total_pasajeros += $informacion['pasajeros']['discapacitados'];

                                                    $destino_inicial = $informacion['primer_destino'];

                                                    $data = array(
                                                        "asunto" => "VTG ".$destino_inicial." ".$nombre_cliente." (".$idcrm.") X ".$total_pasajeros
                                                    );

                                                    $copias = [];
                                                    $adjuntos = [];
                                                    if (is_array($informacion['destinos_data'])){
                                                        if (count($informacion['destinos_data']) > 0){
                                                            foreach ($informacion['destinos_data'] as $linea_recibos) {
                                                                array_push($adjuntos, array(
                                                                    "link" => $linea_recibos['recibo']
                                                                ));
                                                            }
                                                        }
                                                    }
                                                    
                                                    array_push($copias, array(
                                                        "correo" => $validacion['asesor']['correo']
                                                    ));

                                                    // Envia de Reseserva
                                                    $mail = new Email($validacion['asesor']['correo'], $validacion['asesor']['password'], $validacion['asesor']['asesor'], "VTG");
                                                    $respuesta['envio'] = $mail->Enviar_Contabilidad($data, $copias, $adjuntos, "contabilidad");

                                                    $actualizar = $mysql->Modificar("UPDATE reservas SET enviado_contabilidad=1 WHERE id_reserva=?", array($reservacion['id_reserva']));
                                                }else{
                                                    $respuesta['error'] = $busqueda['error'];
                                                }
                                            }
                                        }
                                    }else{
                                        $respuesta['envio_contabilidad'] = "Correo ya enviado a Contabilidad.";
                                    }
                                }
                                
                                    
                                $respuesta['estado'] = true;

                        }else{
                            $respuesta['error'] = "No se pudo encontrar informacion del ticket ni de la reservacion";
                        }


                        
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }                    
                            
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });            

            $app->put("/reservas/{id}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                $files = $request->getUploadedFiles();                
                $id_ticket = $request->getAttribute('id');

                $respuesta['estado'] = false;

                $respuesta['data'] = $data;
                $respuesta['files'] = $files;
            
                try{
                    $functions = new Functions();

                    $validacion = $functions->Validar_Credenciales($authorization[0]);

                    if ($validacion['estado']){
                        $mysql = new Database('mvevip_crm');
                        $id_asesor = $validacion['asesor']['id_asesor'];
                        $eventos = new Events($mysql, $id_asesor);

                        $fecha_modificacion = date("Y-m-d H:i:s");
                        
                        $modificar = $mysql->Modificar("UPDATE tickets SET estado=6, fecha_modificacion=? WHERE id_ticket=?", array($fecha_modificacion, $id_ticket));                        

                        $descripcion_motivo = "Se termino la explicacion al cliente y la tarea asignada.";
                        
                        if (isset($data['observaciones'])){
                            $descripcion_motivo .= " - ".$data['observaciones'];
                        }                        

                        $nuevo_evento = $eventos->New_Event($id_ticket, $descripcion_motivo);
                        
                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }                                

                    $respuesta['estado'] = true;

                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->put("/reservas_monto/{id}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();                
                $id_ticket = $request->getAttribute('id');

                $respuesta['estado'] = false;

                $respuesta['data'] = $data;                
            
                try{
                    $functions = new Functions();

                    $validacion = $functions->Validar_Credenciales($authorization[0]);

                    if ($validacion['estado']){
                        $mysql = new Database('mvevip_crm');
                        $id_asesor = $validacion['asesor']['id_asesor'];
                        $eventos = new Events($mysql, $id_asesor);

                        if (isset($data['monto'])){
                            $monto_paquete = $data['monto'];
                            $num_cotizacion = $data['num_cotizacion'];

                            $modificar = $mysql->Modificar("UPDATE reservas SET monto_paquete=?, num_cotizacion=? WHERE id_ticket=?", array($monto_paquete, $num_cotizacion, $id_ticket));                        

                            $descripcion_motivo = "Se actualizo el monto del paquete";
                                                        
                            $nuevo_evento = $eventos->New_Event($id_ticket, $descripcion_motivo);
                            
                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = "No se ha especificado un monto para el paquete";
                        }
                        
                        
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }                                

                    $respuesta['estado'] = true;

                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->put("/numero_cotizacion/{id}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();                
                $id_ticket = $request->getAttribute('id');

                $respuesta['estado'] = false;

                $respuesta['data'] = $data;                
            
                try{
                    $functions = new Functions();

                    $validacion = $functions->Validar_Credenciales($authorization[0]);

                    if ($validacion['estado']){
                        $mysql = new Database('mvevip_crm');
                        $id_asesor = $validacion['asesor']['id_asesor'];
                        $eventos = new Events($mysql, $id_asesor);

                        if (isset($data['num_cotizacion'])){                            
                            $num_cotizacion = $data['num_cotizacion'];

                            $modificar = $mysql->Modificar("UPDATE reservas SET num_cotizacion=? WHERE id_ticket=?", array($num_cotizacion, $id_ticket));                        

                            $descripcion_motivo = "Se actualizo el numero de cotizacion";
                                                        
                            $nuevo_evento = $eventos->New_Event($id_ticket, $descripcion_motivo);
                            
                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = "No se ha especificado el numero de cotizacion";
                        }                                                
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }                                

                    $respuesta['estado'] = true;

                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/ticket_espera/{id}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');                
                $id_ticket = $request->getAttribute('id');

                $respuesta['estado'] = false;                
            
                try{
                    $functions = new Functions();

                    $validacion = $functions->Validar_Credenciales($authorization[0]);

                    if ($validacion['estado']){
                        $mysql = new Database('mvevip_crm');
                        $id_asesor = $validacion['asesor']['id_asesor'];
                        $eventos = new Events($mysql, $id_asesor);
                        
                        $modificar = $mysql->Modificar("UPDATE tickets SET estado=7 WHERE id_ticket=?", array($id_ticket));                        

                        $nuevo_evento = $eventos->New_Event($id_ticket, "Se paso a espera de informacion de cliente");
                        
                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }                                

                    $respuesta['estado'] = true;

                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });
        
            // ENVIO DE CORREOS A PROVEEDORES

            $app->post("/tickets/envio/{id}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $id_ticket = $request->getAttribute('id');
                $data = $request->getParsedBody();
                $files = $request->getUploadedFiles();

                $respuesta['estado'] = false;

                $respuesta['data'] = $data;
                $respuesta['files'] = $files;
            
                try{
                    $functions = new Functions();

                    $validacion = $functions->Validar_Credenciales($authorization[0]);

                    if ($validacion['estado']){
                        $mysql = new Database('mvevip_crm');
                        $id_usuario = $validacion['asesor']['id_asesor'];

                        $eventos = new Events($mysql, $id_usuario);
                        $functions = new Functions();

                        if (isset($data['correo'])){
                            $correo_envio = $data['correo'];
                            
                            $destinos = json_decode($data['destinos']);

                            $adjuntar_tickets = $data['adjuntar_tickets'];
                            $adjuntar_cedulas = $data['adjuntar_cedulas'];

                            $respuesta['destinos'] = $destinos;
                            
                            if (is_array($destinos)){
                                if (count($destinos) > 0){
                                    $detalle_destinos = '';
                                    $dia_check_in = strtotime("2055-01-01 12:59:59");
                                    $dia_check_out = strtotime("1990-01-01 12:59:59");

                                    foreach ($destinos as $linea_destino) {
                                        $ci_destino = strtotime($linea_destino->check_in." 12:59:59");
                                        $co_destino = strtotime($linea_destino->check_out." 12:59:59");

                                        $separa_check_in = explode("-", $linea_destino->check_in);
                                        $separa_check_out = explode("-", $linea_destino->check_out);

                                        $in_mes = "";
                                        $out_mes = "";
                                        if (isset($separa_check_in[2])){
                                            $in_mes = $functions->meses($separa_check_in[1]);                                    
                                        }
                                        if (isset($separa_check_out[2])){
                                            $out_mes = $functions->meses($separa_check_out[1]);
                                        }

                                        if ($ci_destino <= $dia_check_in){
                                            $dia_check_in = date("d", $ci_destino);
                                        }

                                        if ($co_destino >= $dia_check_out){
                                            $dia_check_out = date("d", $co_destino);
                                        }

                                        $detalle_destinos .= "<br><br>Destino: ".$linea_destino->nombre;
                                        $detalle_destinos .= "<br>In: ".$in_mes." ".$separa_check_in[2];;
                                        $detalle_destinos .= "<br>Out: ".$out_mes." ".$separa_check_out[2];
                                        $detalle_destinos .= "<br>Observaciones: ".$linea_destino->observacion;

                                        $mes_reserva = strtoupper($in_mes);
                                    }

                                    $reserva = $mysql->Consulta_Unico("SELECT * FROM reservas WHERE id_ticket=".$id_ticket);

                                    if (isset($reserva['id_reserva'])){

                                        $id_reserva = $reserva['id_reserva'];

                                        $ticket_cedula = $mysql->Consulta("SELECT * FROM reservas_adjuntos WHERE id_reserva=".$id_reserva);

                                        $adjuntos = [];

                                        // Maneja archivos adjuntos que se adjunta manualmente
                                        if (isset($files)){
                                            // $tkt = $files['tkt']->file;
                                            if (is_array($files)){
                                                if (count($files) > 0){
                                                    foreach ($files as $key => $value) {                                    
                                                        $identificador = explode("_", $key)[0];

                                                        // obtiene el nombre del archivo para obtener la extension                                                    
                                                        $ona = $data['nombre_'.$key];
                                                        $ext = explode(".", $ona);
                                                        $extension = $ext[1];
                                                        // $extension = "pdf";
                    
                                                        $archivo_temporal = $files[$key]->file;
                                                    
                                                        $carpeta = __DIR__."/../../public/storage";
                    
                                                        if (!file_exists($carpeta)){
                                                            // Si no existe la carpeta la crea
                                                            mkdir($carpeta, 0777, true);
                                                        }        
                                                        $nombre_archivo = $identificador.date("YmdHis").".".$extension;
                                                        $destino = $carpeta.'/'.$nombre_archivo;
                                                        move_uploaded_file($archivo_temporal, $destino);                                            
                                                        
                                                        sleep(1);

                                                        array_push($adjuntos, array("link" => $destino));
                                                    }
                                                }
                                            }
                                        }

                                        // Adjunto ticket y cedulas
                                        if (is_array($ticket_cedula)){
                                            if (count($ticket_cedula) > 0){
                                                $carpeta = __DIR__."/../../public/storage";

                                                if ($adjuntar_tickets == "true"){
                                                    foreach ($ticket_cedula as $linea_adj) {   
                                                        if ($linea_adj['identificador'] == "tkt"){
                                                            array_push($adjuntos, array(
                                                                "link" => $carpeta."/".$linea_adj['link']
                                                            ));    
                                                        }                                                                                                 
                                                    }
                                                }

                                                if ($adjuntar_cedulas == "true"){
                                                    foreach ($ticket_cedula as $linea_adj) {   
                                                        if ($linea_adj['identificador'] == "cedula"){
                                                            array_push($adjuntos, array(
                                                                "link" => $carpeta."/".$linea_adj['link']
                                                            ));                                                            
                                                        }                                                                                                 
                                                    }
                                                }
                                            }
                                        }
                            
                                        $nombre = $reserva['apellidos']." ".$reserva['nombres'];
                                        $check_in = $reserva['pickup_fecha'];
                                        $check_out = $reserva['dropoff_fecha'];

                                        // $separa_check_in = explode("-", $check_in);
                                        // $separa_check_out = explode("-", $check_out);

                                        // $in_mes = "";
                                        // $out_mes = "";
                                        // if (isset($separa_check_in[2])){
                                        //     $in_mes = $functions->meses($separa_check_in[1]);                                    
                                        // }
                                        // if (isset($separa_check_out[2])){
                                        //     $out_mes = $functions->meses($separa_check_out[1]);
                                        // }                                

                                        $num_adultos = intval($reserva['num_adultos']);
                                        $num_ninos = intval($reserva['num_ninos']);
                                        $num_ninos2 = intval($reserva['num_ninos2']);
                                        $num_discapacitados_3edad = intval($reserva['num_discapacitados_3edad']);                        
                                        $num_pax = $num_adultos + $num_ninos + $num_ninos2 + $num_discapacitados_3edad;

                                        
                                        // $check_in = $in_mes." ".$separa_check_in[2];
                                        // $dia_check_in = $separa_check_in[2];
                                        // $check_out = $out_mes." ".$separa_check_out[2];
                                        // $dia_check_out = $separa_check_out[2];
                                        // $mes_reserva = strtoupper($in_mes); // "SEPTIEMBRE";

                                        $pax = "(".$num_adultos." ADT";
                                        if ($num_ninos > 0){
                                            $pax .= " - ".$num_ninos." CHD [2-5]";
                                        }
                                        if ($num_ninos2 > 0){
                                            $pax .= " - ".$num_ninos2." CHD [6-11]";
                                        }
                                        if ($num_discapacitados_3edad > 0){
                                            $pax .= " - ".$num_discapacitados_3edad." DIS";
                                        }
                                        $pax .= ")";

                                        $auto_transfer = 0;
                                        if (isset($reserva['pickup_hora'])){
                                            $hora_llegada = explode(":", $reserva['pickup_hora']);
                                            $hora_salida = explode(":", $reserva['dropoff_hora']);
    
                                            $vuelo_in = $reserva['pickup_codvuelo']." LLEGA ".$hora_llegada[0].$hora_llegada[1];
                                            $vuelo_out = $reserva['dropoff_codvuelo']." SALE ".$hora_salida[0].$hora_salida[1];
    
                                            $observaciones = $reserva['observaciones'];
                                            $auto_transfer = 1;
                                        }

                                        // ASUNTO DE EMAIL
                                        $asunto = "RESERVA PAX ".$nombre. " X ".$num_pax." ".$dia_check_in."-".$dia_check_out." ".$mes_reserva;

                                        // CONTENIDO DE EMAIL                    

                                        $contenido = "REFERENCIA: ".$nombre." X ".$num_pax;
                                        // $contenido .= "<br>In: ".$check_in;
                                        // $contenido .= "<br>Out: ".$check_out;
                                        $contenido .= $detalle_destinos;
                                        $contenido .= "<br><br>Pax: ".$pax;

                                        if ($auto_transfer == 1){
                                            $contenido .= "<br><br>VUELOS: ";
                                            $contenido .= "<br>IN: ".$vuelo_in;
                                            $contenido .= "<br>OUT: ".$vuelo_out;

                                            // if ($observaciones != ""){
                                            //     $contenido .= "<br><br>OBSERVACIONES: ";
                                            //     $contenido .= "<br>".$observaciones;
                                            // }                                                
                                        }                                        

                                        // if (isset($reserva['observacionesReserva'])){
                                        //     $observacionesReserva = trim($reserva['observacionesReserva']);

                                        //     if (!empty($observacionesReserva)){
                                        //         $contenido .= "<br><br>TIPO DE TOUR: ";
                                        //         $contenido .= "<br>".$observacionesReserva;
                                        //         $contenido .= "<br><br>";
                                        //     }
                                        // }
                                        

                                        if (isset($data['observaciones'])){
                                            $observaciones_adicionales = trim($data['observaciones']);

                                            if (!empty($observaciones_adicionales)){
                                                $contenido .= "<br><br>OBSERVACIONES ADICIONALES: ";
                                                $contenido .= "<br>".$observaciones_adicionales;
                                                $contenido .= "<br><br><br><br>";
                                            }
                                        }

                                        $contenido .= "<br><br><br>";

                                        $copias = [];
                                        array_push($copias, array(
                                            "correo" => $validacion['asesor']['correo']
                                        ));

                                        $mailer = new Email($validacion['asesor']['correo'], $validacion['asesor']['password'], $validacion['asesor']['asesor']);
                                        $envio = $mailer->Enviar_Correo($correo_envio, "Marketing VIP", $asunto, $contenido, $adjuntos, $copias);

                                        $enviado_proveedor = 1;
                                        $actualizacion = $mysql->Modificar("UPDATE tickets SET enviado_proveedor=? WHERE id_ticket=?", array($enviado_proveedor, $id_ticket));

                                        // ELIMINA DEL SERVIDOR LOS ADJUNTOS
                                        // if (count($adjuntos) > 0){
                                        //     foreach ($adjuntos as $linea_adjunto) {
                                        //         unlink($linea_adjunto['link']);
                                        //     }
                                        // }
                                        
                                        $respuesta['estado'] = true;
                                    }else{
                                        $respuesta['error'] = "No se ha encontrado datos para la reserva";
                                    } 
                                }else{
                                    $respuesta['error'] = "Debe seleccionar por lo menos un destino para enviar al proveedor.";
                                }
                            }else{
                                $respuesta['error'] = "No se obtuve datos de reserva.";
                            }
                            
                            
                        }else{
                            $respuesta['error'] = "No se especifico el correo para el envio";
                        }

                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }
            
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            // REENVIO DE CORREO A CLIENTES

            $app->get("/tickets/reserva/{id}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_ticket = $request->getAttribute('id');
                $respuesta['estado'] = false;
            
                try{
                    $functions = new Functions();

                    $validacion = $functions->Validar_Credenciales($authorization[0]);

                    if ($validacion['estado']){
                        $mysql = new Database('mvevip_crm');

                        $busqueda = $functions->Informacion_Ticket_Reserva($mysql, $id_ticket);
                        $respuesta['busqueda'] = $busqueda;
                        if ($busqueda['estado']){
                            $informacion = $busqueda['informacion'];                           

                            $data = array(
                                "correo" => $informacion['cliente']['correo'],
                                "destinatario" => $informacion['cliente']['nombres_apellidos'],
                                "asunto" => "Estimad@ ".$informacion['cliente']['nombres_apellidos']." su reserva ha sido emitida",
                                "cliente" => $informacion['cliente'],
                                "pasajeros" => $informacion['pasajeros'],
                                "destinos" => $informacion['destinos'],
                                "actividades" => $informacion['actividades'],
                                "transfers" => $informacion['transfers']
                            );

                            $copias = [];
                            $adjuntos = $informacion['adjuntos'];

                            array_push($copias, array(
                                "correo" => $validacion['asesor']['correo'],
                                // "correo" => "fsarzosa@mvevip.com"
                            ));

                            $reply = array(
                                "correo" => $informacion['cliente']['correo_asesor'],
                                "nombre" => $informacion['cliente']['asesor']
                            );

                            // Envia de Reseserva
                            $mail = new Email($validacion['asesor']['correo'], $validacion['asesor']['password'], $validacion['asesor']['asesor']);
                            $respuesta['envio'] = $mail->Enviar_Email($data, $copias, $adjuntos, $reply, "nueva_reserva");


                            // Envio de Impuestos, en caso de haberlos
                            $impuestos = $informacion['impuestos'];

                            if (!empty($impuestos)){

                                $adjuntos = [];

                                $mail2 = new Email($validacion['asesor']['correo'], $validacion['asesor']['password'], $validacion['asesor']['asesor']);

                                $observaciones_impuestos = $informacion['observaciones_impuestos'];
                                if ($informacion['galapagos'] != ''){
                                    $observaciones_impuestos .= "<br><br><br>";
                                    $observaciones_impuestos .= $informacion['galapagos'];

                                    // adjunta archivos en informacion de galapagos
                                    $carpeta = __DIR__."/../../public/mails/nuevareserva";
                                    array_push($adjuntos, array(
                                        "link" => $carpeta."/formulario_salud_viajero.pdf"
                                    ));
                                    array_push($adjuntos, array(
                                        "link" => $carpeta."/ingreso_galapagos.jpeg"
                                    ));
                                    array_push($adjuntos, array(
                                        "link" => $carpeta."/req_sep2.jpg"
                                    ));                                    
                                }

                                $data_hoteleras = array(
                                    "correo" => $informacion['cliente']['correo'],
                                    "destinatario" => $informacion['cliente']['nombres_apellidos'],
                                    "asunto" => "Estimad@ ".$informacion['cliente']['nombres_apellidos']." sus tasas hoteleras",
                                    "cliente" => $informacion['cliente'],
                                    "impuestos" => $impuestos,
                                    "observaciones_impuestos" => $observaciones_impuestos
                                );                                

                                $respuesta['envio_tasas'] = $mail2->Enviar_Tasas($data_hoteleras, $copias, $adjuntos, $reply, "tasa_hotelera");
                            }

                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = $busqueda['error'];
                        }
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }
                    
            
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            // ENVIO DE CORREOS A CONTABILIDAD

            $app->get("/tickets/contabilidad/{id}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_ticket = $request->getAttribute('id');
                $respuesta['estado'] = false;
            
                try{
                    $functions = new Functions();

                    $validacion = $functions->Validar_Credenciales($authorization[0]);

                    if ($validacion['estado']){
                        $mysql = new Database('mvevip_crm');

                        $busqueda = $functions->Informacion_Ticket_Reserva($mysql, $id_ticket);
                        $respuesta['busqueda'] = $busqueda;
                        if ($busqueda['estado']){
                            $informacion = $busqueda['informacion'];

                            $data = array(                               
                                "asunto" => "Recibo para Contabilidad"                                
                            );

                            $copias = [];
                            $adjuntos = [];
                            if (is_array($informacion['destinos_data'])){
                                if (count($informacion['destinos_data']) > 0){
                                    foreach ($informacion['destinos_data'] as $linea_recibos) {
                                        array_push($adjuntos, array(
                                            "link" => $linea_recibos['recibo']
                                        ));
                                    }
                                }
                            }
                            $respuesta['adjn'] = $adjuntos;

                            // Envia de Reseserva
                            $mail = new Email($validacion['asesor']['correo'], $validacion['asesor']['password'], $validacion['asesor']['asesor']);                            
                            $respuesta['envio'] = $mail->Enviar_Contabilidad($data, $copias, $adjuntos, "contabilidad");
               
                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = $busqueda['error'];
                        }
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }
                    
            
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            // REPORTES

            $app->get("/reservas_contabilidad", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;

                $respuesta['params'] = $params;
            
                try{
                    $functions = new Functions();

                    $validacion = $functions->Validar_Credenciales($authorization[0]);

                    if ($validacion['estado']){

                        $mysql = new Database('mvevip_crm');                    
                        $id_asesor_visor = $validacion['asesor']['id_asesor'];                                                

                        $eventos = new Events($mysql, $id_asesor_visor);

                        $path = "https://apicrm.mvevip.com/storage/";
                        
                        $resultados = [];

                        // BUSQUEDA DE TRANSFERS                        

                        $buscador = '';
                        if (isset($params['buscador'])){
                            $buscador = $params['buscador'];
                        }
                       
                        $por_destino = '(R.pickup_destino>0)';
                        if (isset($params['filtro_destinos'])){
                            $filtro_destinos = json_decode($params['filtro_destinos']);
                            if (is_array($filtro_destinos)){
                                if (count($filtro_destinos) > 0){
                                    $por_destino = "";
                                    $numItems = count($filtro_destinos);
                                    $i = 0;
                                    $por_destino .= "(";
                                    foreach ($filtro_destinos as $linea_filtro) {
                                        if(++$i === $numItems) {
                                            $por_destino .= "(R.pickup_destino=".$linea_filtro->id_destino.")";
                                        }else{
                                            $por_destino .= "(R.pickup_destino=".$linea_filtro->id_destino.") OR ";
                                        }                                        
                                    }
                                    $por_destino .= ") ";
                                }
                            }
                        }

                        $pago = '(R.id_forma_pago_transfer>=0)';
                        if (isset($params['pago'])){
                            if ($params['pago'] > 0){
                                $pago = '(R.id_forma_pago_transfer='.$params['pago'].')';
                            }
                        }

                        $proveedor = '(R.id_proveedor_transfer>=0)';
                        if (isset($params['proveedor'])){
                            if ($params['proveedor'] > 0){
                                $proveedor = '(R.id_proveedor_transfer='.$params['proveedor'].')';
                            }                            
                        }

                        $fecha_inicio = "";
                        if (isset($params['fecha_inicio'])){
                            $fecha_inicio = $params['fecha_inicio'];
                        }

                        $fecha_final = "";
                        if (isset($params['fecha_final'])){
                            $fecha_final = $params['fecha_final'];
                        }

                        $rango_fechas_auto = "";
                        if (($fecha_inicio!='')&&($fecha_final!='')){
                            $rango_fechas_auto = "(R.pickup_fecha BETWEEN '".$fecha_inicio."' AND '".$fecha_final."')";
                        }

                        $sql = "SELECT R.id_ticket, T.id_cliente, R.id_reserva, R.nombres, R.apellidos, R.pickup_fecha, R.pickup_hora, R.pickup_codvuelo, R.pickup_destino, R.dropoff_fecha, R.dropoff_hora, R.dropoff_codvuelo, R.dropoff_destino, R.estado, R.voucher_auto, R.recibo_auto, R.costo_transfer, R.id_forma_pago_transfer, R.valor_adicional_transfer, R.id_proveedor_transfer, R.incluye_transfer_alojamiento, R.revisado, R.num_reserva_transfer, R.revisado_admin, R.fecha_factura_transfer, R.num_factura_transfer, R.valor_factura_transfer, D.nombre AS nombre_destino, R.num_reserva_transfer FROM reservas R, destinos D, tickets T WHERE (((R.nombres LIKE '%".$buscador."%') OR (R.apellidos LIKE '%".$buscador."%') OR (R.num_reserva_transfer LIKE '%".$buscador."%')) AND (".$por_destino." AND ".$proveedor." AND ".$pago." AND ".$rango_fechas_auto.") AND (R.incluye_transfer_alojamiento=0) AND (T.estado!=8)) AND (R.pickup_destino=D.id_destino) AND (R.id_ticket=T.id_ticket) ORDER BY R.pickup_fecha DESC";                                            

                        $consulta = $mysql->Consulta($sql);                        
                        
                        if (is_array($consulta)){
                            if (count($consulta) > 0){
                                foreach ($consulta as $linea) {
                                    $idclienteCRM = 0;

                                    $dato_cliente = $mysql->Consulta_Unico("SELECT * FROM clientes WHERE id_cliente=".$linea['id_cliente']);
                                    if (isset($dato_cliente['id_cliente'])){
                                        $idclienteCRM = $dato_cliente['idclienteCRM'];
                                    }

                                    $link_voucher = "";
                                    if ($linea['voucher_auto']!=""){
                                        $link_voucher = $path.$linea['voucher_auto'];
                                    }

                                    $link_recibo = "";
                                    if ($linea['recibo_auto']!=""){
                                        $link_recibo = $path.$linea['recibo_auto'];
                                    }
                                    
                                    $item_revisado = false;
                                    if ($linea['revisado'] == 1){
                                        $item_revisado = true;
                                    }

                                    $item_revisado_admin = false;
                                    if ($linea['revisado_admin'] == 1){
                                        $item_revisado_admin = true;
                                    }

                                    if ($linea['pickup_destino'] != 6){

                                        // Si es proveedor IVIS (id=75), restar 40.
                                        $valor = $linea['costo_transfer'];
                                        if ($linea['id_proveedor_transfer'] == 75){
                                            $valor -= 40;
                                        }

                                        $nombreProveedor = "";
                                        if ((isset($linea['id_proveedor_transfer'])) && (!empty($linea['id_proveedor_transfer']))){
                                            $consultaProveedor = $mysql->Consulta_Unico("SELECT * FROM proveedores WHERE id_proveedor=".$linea['id_proveedor_transfer']);
                                            if ((isset($consultaProveedor['proveedor'])) && (!empty($consultaProveedor['proveedor']))){
                                                $nombreProveedor = $consultaProveedor['proveedor'];
                                            }
                                        }

                                        $nombreFormaPago = "";
                                        if ((isset($linea['id_forma_pago_transfer'])) && (!empty($linea['id_forma_pago_transfer']))){
                                            $consultaFormaPago = $mysql->Consulta_Unico("SELECT * FROM formas_pago WHERE id_forma_pago=".$linea['id_forma_pago_transfer']);
                                            if ((isset($consultaFormaPago['forma_pago'])) && (!empty($consultaFormaPago['forma_pago']))){
                                                $nombreFormaPago = $consultaFormaPago['forma_pago'];
                                            }
                                        }

                                        array_push($resultados, array(
                                            "id" => (int) $linea['id_reserva'],
                                            "id_ticket" => (int) $linea['id_ticket'],
                                            "idclienteCRM" => (int) $idclienteCRM,
                                            "apellidos" => $linea['apellidos'],
                                            "nombres" => $linea['nombres'],
                                            "destino" => array(
                                                "id" => (int) $linea['pickup_destino'],
                                                "destino" => $linea['nombre_destino']
                                            ),
                                            "check_in" => $linea['pickup_fecha'],
                                            "check_out" => $linea['dropoff_fecha'],
                                            "valor" => (float) $valor,
                                            "id_proveedor" => (int) $linea['id_proveedor_transfer'],
                                            "proveedor" => $nombreProveedor,
                                            "id_forma_pago" => (int) $linea['id_forma_pago_transfer'],
                                            "formaPago" => $nombreFormaPago,
                                            "factura" => array(
                                                "fecha" => $linea['fecha_factura_transfer'],
                                                "numero" => $linea['num_factura_transfer'],
                                                "valor" => (float) $linea['valor_factura_transfer'],
                                                "reserva" => $linea['num_reserva_transfer'],
                                                "adicional" => (float) $linea['valor_adicional_transfer']
                                            ),
                                            "adjuntos" => array(
                                                "voucher" => $link_voucher,
                                                "recibo" => $link_recibo
                                            ),
                                            "revisado" => $item_revisado,
                                            "revisado_admin" => $item_revisado_admin,
                                            "modulo" => "TRANSFER"
                                        ));
                                    }

                                    /// BUSQUEDA DE DETALLE PARA PROVEEDOR IVIS (ID=75)
                                    if ($linea['id_proveedor_transfer'] == 75){
                                        $ivis = $mysql->Consulta_Unico("SELECT * FROM reservas_ivis WHERE id_reserva=".$linea['id_reserva']);

                                        if (isset($ivis['id_ivis'])){
                                            $item_revisado = false;
                                            if ($ivis['revisado'] == 1){
                                                $item_revisado = true;
                                            }

                                            $item_revisado_admin = false;
                                            if ($ivis['revisado_admin'] == 1){
                                                $item_revisado_admin = true;
                                            }

                                            array_push($resultados, array(
                                                "id" => (int) $linea['id_reserva'],
                                                "id_ticket" => (int) $linea['id_ticket'],
                                                "idclienteCRM" => (int) $idclienteCRM,
                                                "apellidos" => $linea['apellidos'],
                                                "nombres" => $linea['nombres'],
                                                "destino" => array(
                                                    "id" => (int) $linea['pickup_destino'],
                                                    "destino" => $linea['nombre_destino']
                                                ),
                                                "check_in" => $linea['pickup_fecha'],
                                                "check_out" => $linea['dropoff_fecha'],
                                                "valor" => (float) $ivis['costo_transfer'],
                                                "id_proveedor" => (int) $ivis['id_proveedor_transfer'],
                                                "id_forma_pago" => (int) $ivis['id_forma_pago_transfer'],
                                                "factura" => array(
                                                    "fecha" => $ivis['fecha_factura_transfer'],
                                                    "numero" => $ivis['num_factura_transfer'],
                                                    "valor" => (float) $ivis['valor_factura_transfer'],
                                                    "reserva" => $ivis['num_reserva_transfer'],
                                                    "total" => 0
                                                ),
                                                "adjuntos" => array(
                                                    "voucher" => $link_voucher,
                                                    "recibo" => $link_recibo
                                                ),
                                                "revisado" => $item_revisado,
                                                "revisado_admin" => $item_revisado_admin,
                                                "modulo" => "FEE"
                                            ));
                                        }
                                    }
                                    
                                }
                            }
                        }

                        // BUSQUEDA DE DESTINOS

                        $destino_aux = 'AND (R.destino>0)';
                        if (isset($params['filtro_destinos'])){
                            $filtro_destinos = json_decode($params['filtro_destinos']);
                            if (is_array($filtro_destinos)){
                                if (count($filtro_destinos) > 0){                                                
                                    $destino_aux = '';
                                    $numItems = count($filtro_destinos);
                                    $i = 0;
                                    $destino_aux .= "AND (";
                                    foreach ($filtro_destinos as $linea_filtro) {
                                        if(++$i === $numItems) {
                                            $destino_aux .= "(R.destino=".$linea_filtro->id_destino.")";
                                        }else{
                                            $destino_aux .= "(R.destino=".$linea_filtro->id_destino.") OR ";
                                        }                                        
                                    }
                                    $destino_aux .= ")";
                                }
                            }
                        }

                        $pago_aux = 'AND (R.id_forma_pago>=0)';
                        if (isset($params['pago'])){
                            if ($params['pago'] > 0){
                                $pago_aux = 'AND (R.id_forma_pago='.$params['pago'].')';
                            }                            
                        }

                        $proveedor_aux = 'AND (R.id_proveedor>=0)';
                        if (isset($params['proveedor'])){
                            if ($params['proveedor'] > 0){
                                $proveedor_aux = 'AND (R.id_proveedor='.$params['proveedor'].')';
                            }                            
                        }

                        $rango_fechas = "";
                        if (($fecha_inicio!='')&&($fecha_final!='')){
                            $rango_fechas = "AND (R.check_in BETWEEN '".$fecha_inicio."' AND '".$fecha_final."')";
                        }

                        $por_valor = "AND (R.costo>=0)";
                        if ($params['valor']){     
                            if ($params['valor'] == 1){
                                $por_valor = "AND (R.costo>0)";
                            }else if ($params['valor'] == 2){
                                $por_valor = "AND (R.costo=0)";
                            }
                        }

                        $destinos = $mysql->Consulta("SELECT R.id_destinos, A.id_ticket, T.id_cliente, A.id_reserva, A.apellidos, A.nombres, D.nombre AS nombre_destino, R.destino AS id_destino, R.check_in, R.check_out, R.costo, R.id_proveedor, R.id_forma_pago, R.voucher, R.recibo, R.recibo_liquidacion, R.fecha_factura, R.num_factura, R.valor_factura, R.adicional, R.num_reserva, R.revisado, R.revisado_admin FROM reservas_destinos R, reservas A, destinos D, tickets T WHERE ((R.id_reserva>=0) AND ((A.nombres LIKE '%".$buscador."%') OR (A.apellidos LIKE '%".$buscador."%')) ".$destino_aux." ".$pago_aux." ".$proveedor_aux." ".$por_valor." AND (R.revisado!=2) ".$rango_fechas.") AND (R.destino=D.id_destino) AND (R.id_reserva=A.id_reserva) AND (A.id_ticket=T.id_ticket) ORDER BY R.check_in DESC"); 
                                              
                        if (is_array($destinos)){
                            if (count($destinos) > 0){
                                foreach ($destinos as $linea) {
                                    $idclienteCRM = 0;

                                    $dato_cliente = $mysql->Consulta_Unico("SELECT * FROM clientes WHERE id_cliente=".$linea['id_cliente']);
                                    if (isset($dato_cliente['id_cliente'])){
                                        $idclienteCRM = $dato_cliente['idclienteCRM'];
                                    }

                                    $link_voucher = "";
                                    if ($linea['voucher']!=""){
                                        $link_voucher = $path.$linea['voucher'];
                                    }

                                    $link_recibo = "";
                                    if ($linea['recibo']!=""){
                                        $link_recibo = $path.$linea['recibo'];
                                    }  

                                    $link_liquidacion = "";
                                    if ($linea['recibo_liquidacion']!=""){
                                        $link_liquidacion = $path.$linea['recibo_liquidacion'];
                                    }
                                    
                                    $item_revisado = false;
                                    if ($linea['revisado'] == 1){
                                        $item_revisado = true;
                                    }

                                    $item_revisado_admin = false;
                                    if ($linea['revisado_admin'] == 1){
                                        $item_revisado_admin = true;
                                    }

                                    $nombreProveedor = "";
                                    if ((isset($linea['id_proveedor'])) && (!empty($linea['id_proveedor']))){
                                        $consultaProveedor = $mysql->Consulta_Unico("SELECT * FROM proveedores WHERE id_proveedor=".$linea['id_proveedor']);
                                        if ((isset($consultaProveedor['proveedor'])) && (!empty($consultaProveedor['proveedor']))){
                                            $nombreProveedor = $consultaProveedor['proveedor'];
                                        }
                                    }

                                    $nombreFormaPago = "";
                                    if ((isset($linea['id_forma_pago'])) && (!empty($linea['id_forma_pago']))){
                                        $consultaFormaPago = $mysql->Consulta_Unico("SELECT * FROM formas_pago WHERE id_forma_pago=".$linea['id_forma_pago']);
                                        if ((isset($consultaFormaPago['forma_pago'])) && (!empty($consultaFormaPago['forma_pago']))){
                                            $nombreFormaPago = $consultaFormaPago['forma_pago'];
                                        }
                                    }

                                    array_push($resultados, array(
                                        "id" => (int) $linea['id_destinos'],
                                        "id_ticket" => (int) $linea['id_ticket'],
                                        "idclienteCRM" => (int) $idclienteCRM,
                                        "apellidos" => $linea['apellidos'],
                                        "nombres" => $linea['nombres'],
                                        "destino" => array(
                                            "id" => (int) $linea['id_destino'],
                                            "destino" => $linea['nombre_destino']
                                        ),
                                        "check_in" => $linea['check_in'],
                                        "check_out" => $linea['check_out'],
                                        "valor" => (float) $linea['costo'],
                                        "id_proveedor" => (int) $linea['id_proveedor'],
                                        "proveedor" => $nombreProveedor,
                                        "id_forma_pago" => (int) $linea['id_forma_pago'],
                                        "formaPago" => $nombreFormaPago,
                                        "factura" => array(
                                            "fecha" => $linea['fecha_factura'],
                                            "numero" => $linea['num_factura'],
                                            "valor" => (float) $linea['valor_factura'],
                                            "reserva" => $linea['num_reserva'],
                                            "adicional" => (float) $linea['adicional'],
                                            "total" => 0
                                        ),
                                        "adjuntos" => array(
                                            "voucher" => $link_voucher,
                                            "recibo" => $link_recibo,
                                            "liquidacion" => $link_liquidacion
                                        ),
                                        "revisado" => $item_revisado,
                                        "revisado_admin" => $item_revisado_admin,
                                        "modulo" => "DESTINO"
                                    ));
                                }
                            }
                        }

                        // BUSQUEDA DE ACTIVIDADES 

                        $rango_fechas_actividades = "";
                        if (($fecha_inicio!='')&&($fecha_final!='')){
                            $rango_fechas_actividades = "AND (R.fecha BETWEEN '".$fecha_inicio."' AND '".$fecha_final."')";
                        }

                        $por_valor = "AND (R.costo>=0)";
                        if ($params['valor']){     
                            if ($params['valor'] == 1){
                                $por_valor = "AND (R.costo>0)";
                            }else if ($params['valor'] == 2){
                                $por_valor = "AND (R.costo=0)";
                            }
                        }

                        $pago_aux = 'AND (R.id_forma_pago>0)';
                        if (isset($params['pago'])){
                            if ($params['pago'] > 0){
                                $pago_aux = 'AND (R.id_forma_pago='.$params['pago'].')';
                            }                            
                        }

                        $proveedor_aux = 'AND (R.id_proveedor>0)';
                        if (isset($params['proveedor'])){
                            if ($params['proveedor'] > 0){
                                $proveedor_aux = 'AND (R.id_proveedor='.$params['proveedor'].')';
                            }                            
                        }

                        $por_destino = false;
                        if (isset($params['filtro_destinos'])){
                            $filtro_destinos = json_decode($params['filtro_destinos']);
                            if (is_array($filtro_destinos)){
                                if (count($filtro_destinos) > 0){                                           
                                    foreach ($filtro_destinos as $linea_filtro) {
                                        if ($linea_filtro->id_destino != 6 ){
                                            $por_destino = true;
                                        }                                        
                                    }                                    
                                }
                            }
                        }

                        if ($por_destino){
                            $actividades = $mysql->Consulta("SELECT R.id_actividad, A.id_ticket, T.id_cliente, R.id_reserva, A.apellidos, A.nombres, R.actividad AS nombre_destino, R.fecha, R.costo, R.id_forma_pago, R.id_proveedor,
                            R.fecha_factura, R.num_factura, R.valor_factura, R.num_reserva, R.voucher, R.adicional, R.recibo, R.revisado, R.revisado_admin FROM reservas_actividades R, reservas A, tickets T  WHERE ((R.id_reserva>=0) AND ((A.nombres LIKE '%".$buscador."%') OR (A.apellidos LIKE '%".$buscador."%')) ".$rango_fechas_actividades." ".$pago_aux." ".$proveedor_aux." ".$por_valor.") AND (R.id_reserva=A.id_reserva) AND (A.id_ticket=T.id_ticket)");

                            if (is_array($actividades)){
                                if (count($actividades) > 0){
                                    foreach ($actividades as $linea) {
                                        $idclienteCRM = 0;

                                        $dato_cliente = $mysql->Consulta_Unico("SELECT * FROM clientes WHERE id_cliente=".$linea['id_cliente']);
                                        if (isset($dato_cliente['id_cliente'])){
                                            $idclienteCRM = $dato_cliente['idclienteCRM'];
                                        }

                                        $link_voucher = "";
                                        if ($linea['voucher']!=""){
                                            $link_voucher = $path.$linea['voucher'];
                                        }

                                        $link_recibo = "";
                                        if ($linea['recibo']!=""){
                                            $link_recibo = $path.$linea['recibo'];
                                        }               
                                        
                                        $item_revisado = false;
                                        if ($linea['revisado'] == 1){
                                            $item_revisado = true;
                                        }

                                        $item_revisado_admin = false;
                                        if ($linea['revisado_admin'] == 1){
                                            $item_revisado_admin = true;
                                        }

                                        $nombreProveedor = "";
                                        if ((isset($linea['id_proveedor'])) && (!empty($linea['id_proveedor']))){
                                            $consultaProveedor = $mysql->Consulta_Unico("SELECT * FROM proveedores WHERE id_proveedor=".$linea['id_proveedor']);
                                            if ((isset($consultaProveedor['proveedor'])) && (!empty($consultaProveedor['proveedor']))){
                                                $nombreProveedor = $consultaProveedor['proveedor'];
                                            }
                                        }

                                        $nombreFormaPago = "";
                                        if ((isset($linea['id_forma_pago'])) && (!empty($linea['id_forma_pago']))){
                                            $consultaFormaPago = $mysql->Consulta_Unico("SELECT * FROM formas_pago WHERE id_forma_pago=".$linea['id_forma_pago']);
                                            if ((isset($consultaFormaPago['forma_pago'])) && (!empty($consultaFormaPago['forma_pago']))){
                                                $nombreFormaPago = $consultaFormaPago['forma_pago'];
                                            }
                                        }

                                        array_push($resultados, array(
                                            "id" => (int) $linea['id_actividad'],
                                            "id_ticket" => (int) $linea['id_ticket'],
                                            "idclienteCRM" => (int) $idclienteCRM,
                                            "apellidos" => $linea['apellidos'],
                                            "nombres" => $linea['nombres'],
                                            "destino" => array(                                            
                                                "destino" => $linea['nombre_destino']
                                            ),
                                            "check_in" => $linea['fecha'],
                                            "check_out" => "",
                                            "valor" => (float) $linea['costo'],
                                            "id_proveedor" => (int) $linea['id_proveedor'],
                                            "proveedor" => $nombreProveedor,
                                            "id_forma_pago" => (int) $linea['id_forma_pago'],
                                            "formaPago" => $nombreFormaPago,
                                            "factura" => array(
                                                "fecha" => $linea['fecha_factura'],
                                                "numero" => $linea['num_factura'],
                                                "valor" => (float) $linea['valor_factura'],
                                                "reserva" => $linea['num_reserva'],
                                                "adicional" => (float) $linea['adicional'],
                                                "total" => 0
                                            ),
                                            "adjuntos" => array(
                                                "voucher" => $link_voucher,
                                                "recibo" => $link_recibo
                                            ),
                                            "revisado" => $item_revisado,
                                            "revisado_admin" => $item_revisado_admin,
                                            "modulo" => "ACTIVIDAD"
                                        ));
                                    }
                                }
                            }      
                        }
                                      

                        // $resultados = [];

                        // if (is_array($consulta)){
                        //     if (count($consulta) > 0){
                        //         foreach ($consulta as $linea) {
                        //             $id_ticket = $linea['id_ticket'];
                        //             $id_reserva = $linea['id_reserva'];
                                    
                        //             $datos_ticket = $mysql->Consulta_Unico("SELECT T.id_ticket, T.id_asesor, T.id_cliente, T.requerimiento, T.fecha_lim_contacto, T.hora_lim_contacto, T.alta_prioridad, T.enviado_proveedor, T.nueva_tarea, T.fecha_alta, T.fecha_modificacion, T.estado, C.documento, C.nombres, C.apellidos, C.correo, C.celular, C.telefono, C.idclienteCRM, A.asesor, T.revisado FROM tickets T, clientes C, asesores A WHERE (T.id_ticket=".$id_ticket.") AND (T.id_cliente=C.id_cliente) AND (T.id_asesor=A.id_asesor)");

                        //             $ticket_eventos = $eventos->Get_Event($id_ticket);

                        //             $estado_revision = false;
                        //             if ($datos_ticket['revisado'] == 1){
                        //                 $estado_revision = true;
                        //             }

                        //             $estado = "";
                        //             $color = "";
                        //             switch ($datos_ticket['estado']) {
                        //                 case 0:
                        //                     $estado = "Nueva Tarea";
                        //                     $color = "primary";
                        //                     break;
                        //                 case 1:
                        //                     $estado = "Pendiente de Coordinación";
                        //                     $color = "warning";
                        //                     break;
                        //                 case 2:
                        //                     $estado = "Pendiente Confirmación de Proveedor";
                        //                     $color = "warning";
                        //                     break;
                        //                 case 3:
                        //                     $estado = "Pendiente Elaboracion de Reserva";
                        //                     $color = "info";
                        //                     break;
                        //                 case 4:
                        //                     $estado = "Pendiente Explicacion a Cliente";
                        //                     $color = "success";
                        //                     break;
                        //                 case 6:
                        //                     $estado = "Tarea Terminada";
                        //                     $color = "secondary";
                        //                     break;
                        //                 case 5:
                        //                     $estado = "Tarea Anulada";
                        //                     $color = "danger";
                        //                     break;
                        //                 case 7:
                        //                     $estado = "Espera a Respuesta de Cliente";
                        //                     $color = "warning";
                        //                     break;
                        //             }

                        //             $prioridad = "NORMAL";
                        //             if ($datos_ticket['alta_prioridad'] == 1){
                        //                 $prioridad = "ALTA";
                        //             }

                        //             $enviado_proveedor = false;
                        //             if ($datos_ticket['enviado_proveedor'] == 1){
                        //                 $enviado_proveedor = true;
                        //             }

                        //             // DESTINOS                                    
                        //             $destino_aux = 'AND (R.destino>0)';
                        //             if (isset($params['filtro_destinos'])){
                        //                 $filtro_destinos = json_decode($params['filtro_destinos']);
                        //                 if (is_array($filtro_destinos)){
                        //                     if (count($filtro_destinos) > 0){                                                
                        //                         $destino_aux = '';
                        //                         $numItems = count($filtro_destinos);
                        //                         $i = 0;
                        //                         $destino_aux .= "AND (";
                        //                         foreach ($filtro_destinos as $linea_filtro) {
                        //                             if(++$i === $numItems) {
                        //                                 $destino_aux .= "(R.destino=".$linea_filtro->id_destino.")";
                        //                             }else{
                        //                                 $destino_aux .= "(R.destino=".$linea_filtro->id_destino.") OR ";
                        //                             }                                        
                        //                         }
                        //                         $destino_aux .= ")";
                        //                     }
                        //                 }
                        //             }

                        //             $pago_aux = 'AND (R.id_forma_pago>0)';
                        //             if (isset($params['pago'])){
                        //                 if ($params['pago'] > 0){
                        //                     $pago_aux = 'AND (R.id_forma_pago='.$params['pago'].')';
                        //                 }                            
                        //             }

                        //             $proveedor_aux = 'AND (R.id_proveedor>0)';
                        //             if (isset($params['proveedor'])){
                        //                 if ($params['proveedor'] > 0){
                        //                     $proveedor_aux = 'AND (R.id_proveedor='.$params['proveedor'].')';
                        //                 }                            
                        //             }

                        //             $rango_fechas = "";
                        //             if (($fecha_inicio!='')&&($fecha_final!='')){
                        //                 $rango_fechas = "AND (R.check_in BETWEEN '".$fecha_inicio."' AND '".$fecha_final."')";
                        //             }

                        //             $por_valor = "AND (R.costo>=0)";
                        //             if ($params['valor']){     
                        //                 if ($params['valor'] == 1){
                        //                     $por_valor = "AND (R.costo>0)";
                        //                 }else if ($params['valor'] == 2){
                        //                     $por_valor = "AND (R.costo=0)";
                        //                 }
                        //             }

                        //             $destinos = $mysql->Consulta("SELECT * FROM reservas_destinos R, destinos D, formas_pago P, proveedores O WHERE ((R.id_reserva=".$id_reserva.") ".$destino_aux." ".$pago_aux." ".$proveedor_aux." ".$por_valor." AND (R.revisado!=2) ".$rango_fechas.") AND (R.destino=D.id_destino) AND (R.id_forma_pago=P.id_forma_pago) AND (R.id_proveedor=O.id_proveedor) ORDER BY R.check_in DESC");

                        //             $listado_destinos = [];

                        //             if (is_array($destinos)){
                        //                 if (count($destinos) > 0){
                        //                     foreach ($destinos as $linea_destino) {

                        //                         $link_voucher = "";
                        //                         if ($linea_destino['voucher']!=""){
                        //                             $link_voucher = $path.$linea_destino['voucher'];
                        //                         }

                        //                         $link_recibo = "";
                        //                         if ($linea_destino['recibo']!=""){
                        //                             $link_recibo = $path.$linea_destino['recibo'];
                        //                         }

                        //                         $link_recibo_liquidacion = "";
                        //                         if ($linea_destino['recibo_liquidacion']!=""){
                        //                             $link_recibo_liquidacion = $path.$linea_destino['recibo_liquidacion'];
                        //                         }

                        //                         $item_revisado = false;
                        //                         if ($linea_destino['revisado'] == 1){
                        //                             $item_revisado = true;
                        //                         }

                        //                         $item_revisado_admin = false;
                        //                         if ($linea_destino['revisado_admin'] == 1){
                        //                             $item_revisado_admin = true;
                        //                         }

                        //                         array_push($listado_destinos, array(
                        //                             "id_destino" => (int) $linea_destino['id_destinos'],
                        //                             "area" => (int) $linea_destino['area'],
                        //                             "destino" => $linea_destino['nombre'],
                        //                             "check_in" => $linea_destino['check_in'],
                        //                             "check_out" => $linea_destino['check_out'],
                        //                             "nombre_hotel" => $linea_destino['nombre_hotel'],
                        //                             "direccion_hotel" => $linea_destino['direccion_hotel'],
                        //                             "numero_reserva" => $linea_destino['num_reserva'],
                        //                             "costo" => (float) $linea_destino['costo'],
                        //                             "forma_pago" => array(
                        //                                 "id_forma_pago" => (int) $linea_destino['id_forma_pago'],
                        //                                 "forma_pago" => $linea_destino['forma_pago']
                        //                             ),
                        //                             "proveedor" => array(
                        //                                 "id_proveedor" => (int) $linea_destino['id_proveedor'],
                        //                                 "proveedor" => $linea_destino['proveedor']
                        //                             ),
                        //                             "observacion" => (float) $linea_destino['observacion'],
                        //                             "adjuntos" => array(
                        //                                 "voucher" => $link_voucher,
                        //                                 "recibo" => $link_recibo,
                        //                                 "recibo_liquidacion" => $link_recibo_liquidacion
                        //                             ),
                        //                             "factura" => array(
                        //                                 "fecha" => $linea_destino['fecha_factura'],
                        //                                 "numero" => $linea_destino['num_factura'],
                        //                                 "valor" => (float) $linea_destino['valor_factura']
                        //                             ),
                        //                             "revisado" => $item_revisado,
                        //                             "revisado_admin" => $item_revisado_admin
                        //                         ));
                        //                     }
                        //                 }
                        //             }

                        //             // ACTIVIDADES

                        //             $rango_fechas_actividades = "";
                        //             if (($fecha_inicio!='')&&($fecha_final!='')){
                        //                 $rango_fechas_actividades = "AND (R.fecha BETWEEN '".$fecha_inicio."' AND '".$fecha_final."')";
                        //             }

                        //             $por_valor = "AND (R.costo>=0)";
                        //             if ($params['valor']){     
                        //                 if ($params['valor'] == 1){
                        //                     $por_valor = "AND (R.costo>0)";
                        //                 }else if ($params['valor'] == 2){
                        //                     $por_valor = "AND (R.costo=0)";
                        //                 }
                        //             }

                        //             $pago_aux = 'AND (R.id_forma_pago>0)';
                        //             if (isset($params['pago'])){
                        //                 if ($params['pago'] > 0){
                        //                     $pago_aux = 'AND (R.id_forma_pago='.$params['pago'].')';
                        //                 }                            
                        //             }

                        //             $proveedor_aux = 'AND (R.id_proveedor>0)';
                        //             if (isset($params['proveedor'])){
                        //                 if ($params['proveedor'] > 0){
                        //                     $proveedor_aux = 'AND (R.id_proveedor='.$params['proveedor'].')';
                        //                 }                            
                        //             }

                        //             $actividades = $mysql->Consulta("SELECT * FROM reservas_actividades R, formas_pago P, proveedores O WHERE ((R.id_reserva=".$id_reserva.") ".$rango_fechas_actividades." ".$pago_aux." ".
                        //             $proveedor_aux." ".$por_valor.") AND (R.id_forma_pago=P.id_forma_pago) AND (R.id_proveedor=O.id_proveedor)");

                        //             $respuesta['sql'] = "SELECT * FROM reservas_actividades R, formas_pago P, proveedores O WHERE ((R.id_reserva=".$id_reserva.") ".$rango_fechas_actividades." ".$pago_aux." ".$proveedor_aux." ".$por_valor.") AND (R.id_forma_pago=P.id_forma_pago) AND (R.id_proveedor=O.id_proveedor)";

                        //             $listado_actividades = [];

                        //             if (is_array($actividades)){
                        //                 if (count($actividades) > 0){
                        //                     foreach ($actividades as $linea_actividad) {

                        //                         $link_voucher = "";
                        //                         if ($linea_actividad['voucher']!=""){
                        //                             $link_voucher = $path.$linea_actividad['voucher'];
                        //                         }

                        //                         $link_recibo = "";
                        //                         if ($linea_actividad['recibo']!=""){
                        //                             $link_recibo = $path.$linea_actividad['recibo'];
                        //                         }
                                                
                        //                         $item_revisado = false;
                        //                         if ($linea_actividad['revision'] == 1){
                        //                             $item_revisado = true;
                        //                         }

                        //                         $item_revisado_admin = false;
                        //                         if ($linea_actividad['revision_admin'] == 1){
                        //                             $item_revisado_admin = true;
                        //                         }

                        //                         array_push($listado_actividades, array(
                        //                             "id_actividad" => (int) $linea_actividad['id_actividad'],
                        //                             "actividad" => $linea_actividad['actividad'],
                        //                             "fecha" => $linea_actividad['fecha'],
                        //                             "observaciones" => $linea_actividad['observaciones'],
                        //                             "costo" => (float) $linea_actividad['costo'],
                        //                             "forma_pago" => array(
                        //                                 "id_forma_pago" => (int) $linea_actividad['id_forma_pago'],
                        //                                 "forma_pago" => $linea_actividad['forma_pago']
                        //                             ),
                        //                             "proveedor" => array(
                        //                                 "id_proveedor" => (int) $linea_actividad['id_proveedor'],
                        //                                 "proveedor" => $linea_actividad['proveedor']
                        //                             ),
                        //                             "adjuntos" => array(
                        //                                 "voucher" => $link_voucher,
                        //                                 "recibo" => $link_recibo                                                        
                        //                             ), 
                        //                             "factura" => array(
                        //                                 "fecha" => $linea_actividad['fecha_factura'],
                        //                                 "numero" => $linea_actividad['num_factura'],
                        //                                 "valor" => (float) $linea_actividad['valor_factura']
                        //                             ),
                        //                             "revisado" => $item_revisado,
                        //                             "revisado_admin" => $item_revisado_admin
                        //                         ));
                        //                     }
                        //                 }
                        //             }

                        //             // LISTA DE IMPUESTOS 
                        //             $impuestos = $mysql->Consulta("SELECT * FROM reservas_impuestos R, destinos D WHERE (R.id_reserva=".$id_reserva.") AND (R.id_destino=D.id_destino)");
                        //             $listado_impuestos = [];

                        //             if (is_array($impuestos)){
                        //                 if (count($impuestos) > 0){
                        //                     foreach ($impuestos as $linea_impuesto) {
                        //                         array_push($listado_impuestos, array(
                        //                             "destino" => $linea_impuesto['nombre'],
                        //                             "descripcion" => $linea_impuesto['descripcion'],
                        //                             "cantidad" => (int) $linea_impuesto['num_noches'],
                        //                             "valor" => (float) $linea_impuesto['valor'],
                        //                             "total" => (float) $linea_impuesto['total']
                        //                         ));
                        //                     }
                        //                 }
                        //             }

                        //             // ADJUNTOS

                        //             $adjuntos = $mysql->Consulta("SELECT * FROM reservas_adjuntos WHERE id_reserva=".$id_reserva);
                        //             $listado_adjuntos = [];

                        //             if (is_array($adjuntos)){
                        //                 if (count($adjuntos) > 0){
                        //                     foreach ($adjuntos as $linea_adjunto) {

                        //                         $link = "";
                        //                         if ($linea_adjunto['link'] != ""){
                        //                             $link = $path.$linea_adjunto['link'];
                        //                         }

                        //                         array_push($listado_adjuntos, array(
                        //                             "identificador" => $linea_adjunto['identificador'],
                        //                             "link" => $link
                        //                         ));
                        //                     }
                        //                 }
                        //             }

                        //             // TRANSFER
                        //             $detalle_transfer = null;

                        //             // $por_valor = true;
                        //             // if ($params['valor']){     
                        //             //     if ($params['valor'] == 1){
                        //             //         if ($linea['costo_transfer'] > 0){
                        //             //             $por_valor = true;
                        //             //         }else{
                        //             //             $por_valor = false;
                        //             //         }
                        //             //     }else if ($params['valor'] == 2){
                        //             //         if ($linea['costo_transfer'] == 0){
                        //             //             $por_valor = true;
                        //             //         }else{
                        //             //             $por_valor = false;
                        //             //         }
                        //             //     }
                        //             // }
                                   
                        //             // $por_pago = true;
                        //             // if (isset($params['pago'])){
                        //             //     if ($params['pago'] > 0){
                        //             //         if ($linea['id_forma_pago_transfer'] == $params['pago']){
                        //             //             $por_pago = true;
                        //             //         }else{
                        //             //             $por_pago = false;
                        //             //         }
                        //             //     }
                        //             // }
                                    
                        //             // $por_proveedor = true;
                        //             // if (isset($params['proveedor'])){
                        //             //     if ($params['proveedor'] > 0){
                        //             //         if ($linea['id_proveedor_transfer'] == $params['proveedor']){
                        //             //             $por_proveedor = true; 
                        //             //         }else{
                        //             //             $por_proveedor = false; 
                        //             //         }
                        //             //     }
                        //             // }

                        //             // if (($linea['incluye_transfer_alojamiento'] == 0) && ($por_valor) && ($por_pago) && ($por_proveedor)){
                        //             if ($linea['incluye_transfer_alojamiento'] == 0){

                        //                 $item_revisado = false;
                        //                 if ($linea['revisado'] == 1){
                        //                     $item_revisado = true;
                        //                 }

                        //                 $item_revisado_admin = false;
                        //                 if ($linea['revisado_admin'] == 1){
                        //                     $item_revisado_admin = true;
                        //                 }

                        //                 $detalle_transfer = array(
                        //                     "pickup" => array(
                        //                         "fecha" => $linea['pickup_fecha'],
                        //                         "hora" => $linea['pickup_hora'],
                        //                         "destino" => $linea['pickup_destino'],
                        //                         "cod_vuelo" => $linea['pickup_codvuelo']
                        //                     ),
                        //                     "dropoff" => array(
                        //                         "fecha" => $linea['dropoff_fecha'],
                        //                         "hora" => $linea['dropoff_hora'],
                        //                         "destino" => $linea['dropoff_destino'],
                        //                         "cod_vuelo" => $linea['dropoff_codvuelo']
                        //                     ),
                        //                     "adjuntos" => array(
                        //                         "voucher_auto" => $linea['voucher_auto'],
                        //                         "recibo_auto" => $linea['recibo_auto'],
                        //                         "link_voucher_auto" => $path.$linea['voucher_auto'],
                        //                         "link_recibo_auto" => $path.$linea['recibo_auto'],
                        //                     ),
                        //                     "factura" => array(
                        //                         "fecha" => $linea['fecha_factura_transfer'],
                        //                         "numero" => $linea['num_factura_transfer'],
                        //                         "valor" => (float) $linea['valor_factura_transfer']
                        //                     ),
                        //                     "costo" => (float) $linea['costo_transfer'],
                        //                     "forma_pago" => $linea['id_forma_pago_transfer'],
                        //                     "proveedor" => $linea['id_proveedor_transfer'],
                        //                     "revisado" => $item_revisado,
                        //                     "revisado_admin" => $item_revisado_admin,
                        //                     "id_reserva" => (int) $id_reserva
                        //                 );
                        //             }
                                    

                        //             array_push($resultados, array(
                        //                 "documento" => $datos_ticket['documento'],
                        //                 "nombres" => $datos_ticket['nombres'],
                        //                 "apellidos" => $datos_ticket['apellidos'],
                        //                 "correo" => $datos_ticket['correo'],
                        //                 "telefono" => $datos_ticket['telefono'],
                        //                 "celular" => $datos_ticket['celular'],
                        //                 "idclienteCRM" => $datos_ticket['idclienteCRM'],
                        //                 "estado" => array(
                        //                     "descripcion" => $estado,
                        //                     "color" => $color,
                        //                     "valor" => (int) $datos_ticket['estado']
                        //                 ),
                        //                 "revision" => $estado_revision,                                        
                        //                 "ticket" => array(
                        //                     "id_ticket" => (int) $datos_ticket['id_ticket'],
                        //                     "requerimiento" => $datos_ticket['requerimiento'],
                        //                     "limite_contacto" => array(
                        //                         "fecha" => $datos_ticket['fecha_lim_contacto'],
                        //                         "hora" => $datos_ticket['hora_lim_contacto']
                        //                     ),
                        //                     "alta_prioridad" => $prioridad,
                        //                     "enviado_proveedor" => $enviado_proveedor,
                        //                     "fecha_alta" => $datos_ticket['fecha_alta'],
                        //                     "fecha_modificacion" => $datos_ticket['fecha_modificacion']
                        //                 ),
                        //                 "destinos" => $listado_destinos,
                        //                 "actividades" => $listado_actividades,
                        //                 "transfer" => $detalle_transfer,
                        //                 "impuestos" => $listado_impuestos,
                        //                 "eventos" => $ticket_eventos,
                        //                 "adjuntos" => $listado_adjuntos
                        //             ));
                        //         }
                        //     }
                        // }

                        $respuesta['resultados'] = $resultados;
                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->post("/reservas_contabilidad/{estado}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                $estado = $request->getAttribute('estado');
                $respuesta['estado'] = false;
    
                $respuesta['data'] = $data;
                $respuesta['estado_modificacion'] = $estado;
            
                try{
                    $functions = new Functions();
    
                    $validacion = $functions->Validar_Credenciales($authorization[0]);
                    $crm = new CRM_API();
    
                    if ($validacion['estado']){
    
                        $mysql = new Database('mvevip_crm');                    
                        $id_asesor_visor = $validacion['asesor']['id_asesor'];                                                
    
                        $eventos = new Events($mysql, $id_asesor_visor);

                        if (is_array($data)){
                            if (count($data) > 0){
                                foreach ($data as $linea) {
                                    $id = $linea['id'];
                                    $modulo = $linea['modulo'];

                                    $fecha = $linea['fecha'];
                                    $numero = $linea['numero'];
                                    $id_proveedor = $linea['id_proveedor'];
                                    $id_forma_pago = $linea['id_forma_pago'];
                                    $valor = $linea['valor'];
                                    $adicional = $linea['adicional'];
                                    $id_cliente = $linea['id_cliente'];
                                    $nombre_cliente = $linea['nombresCliente'];

                                    $tipo_gasto = 65;
                                    switch ($modulo) {
                                        case 'TRANSFER':
                                            $tipo_gasto = 70;
                                            $actualizar = $mysql->Modificar("UPDATE reservas SET revisado=1, id_proveedor_transfer=?, id_forma_pago_transfer=?, fecha_factura_transfer=?, num_factura_transfer=?, valor_factura_transfer=?, valor_adicional_transfer=?, costo_transfer=? WHERE id_reserva=?", array($id_proveedor, $id_forma_pago, $fecha, $numero, $valor, $adicional, $valor, $id));
                                            break;
                                        case 'FEE':
                                            # code...
                                            break;
                                        case 'DESTINO':
                                            $tipo_gasto = 65;
                                            $actualizar = $mysql->Modificar("UPDATE reservas_destinos SET revisado=1, id_proveedor=?, id_forma_pago=?, fecha_factura=?, num_factura=?, valor_factura=?, adicional=?, costo=? WHERE id_destinos=?", array($id_proveedor, $id_forma_pago, $fecha, $numero, $valor, $adicional, $valor, $id));
                                            break;
                                        case 'ACTIVIDAD':
                                            $tipo_gasto = 67;
                                            $actualizar = $mysql->Modificar("UPDATE reservas_actividades SET revisado=1, id_proveedor=?, id_forma_pago=?, fecha_factura=?, num_factura=?, valor_factura=?, adicional=?, costo=? WHERE id_actividad=?", array($id_proveedor, $id_forma_pago, $fecha, $numero, $valor, $adicional, $valor, $id));
                                            break;
                                    }

                                    /// Busca informacion del proveedor
                                    $infoProveedor = $mysql->Consulta_Unico("SELECT * FROM proveedores WHERE (id_proveedor=".$id_proveedor.")");
                                    $respuesta['proveedor'] = $infoProveedor;

                                    if (isset($infoProveedor['id_proveedor'])){
                                        if ($infoProveedor['estado'] == 0 ){
                                            $proveedor = strtoupper($infoProveedor['proveedor']);
                                            $codigo = $infoProveedor['codigo'];
                                            $retencion = $infoProveedor['retencion'];

                                            if (!empty($codigo)){ 

                                                $infoFormaPago = $mysql->Consulta_Unico("SELECT * FROM formas_pago WHERE (id_forma_pago=".$id_forma_pago.")");
                                                $respuesta['formaPago'] = $infoFormaPago;

                                                if (isset($infoFormaPago['id_forma_pago'])){
                                                    $formaPago = $infoFormaPago['forma_pago'];
                                                    $codigoPago = $infoFormaPago['codigo'];
        
                                                     // envio a tabla liquidaciones
                                                    $total = $valor + $adicional;
                                                    $campos = array(
                                                        "liq_proveedor" => $codigo,
                                                        "liq_nombre_proveedor" => $proveedor,
                                                        "liq_nombre_comercial" => $proveedor,
                                                        "liq_forma_pago" => $codigoPago,
                                                        "liq_fecha_emision" => $fecha,
                                                        "liq_secuencial" => $numero,
                                                        "liq_detalle" => $formaPago." ".$proveedor,
                                                        "liq_valor" => $total,
                                                        "liq_id_cliente" => $id_cliente,
                                                        "liq_nombre_cliente" => $nombre_cliente,
                                                        "liq_autorizacion" => "9999999999",
                                                        "liq_tipo_gasto" => $tipo_gasto,
                                                        "liq_codigo_retencion" => $retencion,
                                                        "liq_grupo" => 0
                                                    );
                                                    $respuesta['campos'] = $campos;
                                                    $envio = $crm->agregarLiquidaciones($campos);
                                                    $respuesta['envio'] = $envio;
                                                }
                                                
                                            }

                                            
                                        }
                                    }
                                }

                                $respuesta['numeracion'] = $crm->numerarLiquidacionesPendientes();
                                $respuesta['estado'] = true;
                            }
                        }














                        // if (isset($data)){
                        //     // $modificados_destinos = $data['modificados']['destinos'];
                        //     // $modificados_actividades = $data['modificados']['actividades'];
                        //     // $modificados_transfer = $data['modificados']['transfer'];
                        //     // $conciliados = $data['conciliados'];
                        //     // $procesar = [];

                        //     // if ($estado == 1){ // solo se guarda
                        //     //      // DESTINOS
                        //     //     if (is_array($modificados_destinos)){
                        //     //         if (count($modificados_destinos) > 0){
                        //     //             foreach ($modificados_destinos as $linea_modificado) {

                        //     //                 $id = $linea_modificado['id'];
                                            
                        //     //                 $fecha_factura = $linea_modificado['factura']['fecha'];
                        //     //                 $num_factura = $linea_modificado['factura']['numero'];
                        //     //                 $valor_factura = $linea_modificado['factura']['valor'];
                        //     //                 $valor_adicional = $linea_modificado['factura']['adicional'];
    
                        //     //                 $forma_pago = $linea_modificado['id_forma_pago'];
                        //     //                 $proveedor = $linea_modificado['id_proveedor'];
                        //     //                 $num_reserva = $linea_modificado['factura']['reserva'];

                        //     //                 switch ($linea_modificado['modulo']) {
                        //     //                     case 'TRANSFER':
                        //     //                         $actualizar = $mysql->Modificar("UPDATE reservas SET id_forma_pago_transfer=?, id_proveedor_transfer=?, fecha_factura_transfer=?, num_factura_transfer=?, valor_factura_transfer=?, valor_adicional_transfer=?, revisado=? WHERE id_reserva=?", array($forma_pago, $proveedor, $fecha_factura, $num_factura, $valor_factura, $valor_adicional, 1, $id));
                        //     //                         break;
                        //     //                     case 'FEE':
                        //     //                         $busca_ivis = $mysql->Consulta_Unico('SELECT * FROM reservas_ivis WHERE id_reserva='.$id);
                        //     //                         if (isset($busca_ivis['id_ivis'])){
                        //     //                             $id_ivis = $busca_ivis['id_ivis'];

                        //     //                             // Actualiza valores
                        //     //                             $modificar = $mysql->Modificar("UPDATE reservas_ivis SET id_forma_pago_transfer=?, id_proveedor_transfer=?, fecha_factura_transfer=?, num_factura_transfer=?, valor_factura_transfer=?, revisado=? WHERE id_ivis=?", array($forma_pago, $proveedor, $fecha_factura, $num_factura, $valor_factura, 1, $id_ivis));
                        //     //                         }else{
                        //     //                             $fee_costo = 40;
                        //     //                             $ingreso = $mysql->Ingreso("INSERT INTO reservas_ivis (id_reserva, num_reserva_transfer, costo_transfer, id_proveedor_transfer, id_forma_pago_transfer, fecha_factura_transfer, num_factura_transfer, valor_factura_transfer, revisado) VALUES (?,?,?,?,?,?,?,?,?)", array($id, $num_reserva, $fee_costo, $proveedor, $forma_pago, $fecha_factura, $num_factura, $valor_factura, 1));
                        //     //                         }
                                                    
                        //     //                         break;
                        //     //                     case 'DESTINO':
                        //     //                         $actualizar = $mysql->Modificar("UPDATE reservas_destinos SET id_forma_pago=?, id_proveedor=?, fecha_factura=?, num_factura=?, valor_factura=?, adicional=?, revisado=? WHERE id_destinos=?", array($forma_pago, $proveedor, $fecha_factura, $num_factura, $valor_adicional, $valor_factura, 1, $id));
                        //     //                         break;
                        //     //                     case 'ACTIVIDAD':
                        //     //                         $actualizar = $mysql->Modificar("UPDATE reservas_actividades SET id_forma_pago=?, id_proveedor=?, fecha_factura=?, num_factura=?, valor_factura=?, adicional=?, revisado=? WHERE id_actividad=?", array($forma_pago, $proveedor, $fecha_factura, $num_factura, $valor_factura, $valor_adicional, 1, $id));
                        //     //                         break;
                        //     //                 }
    
                                            
    
                        //     //                 // $actualizar = $mysql->Modificar("UPDATE reservas_destinos SET id_forma_pago=?, id_proveedor=?, fecha_factura=?, num_factura=?, valor_factura=?, revisado=? WHERE id_destinos=?", array($forma_pago, $proveedor, $fecha_factura, $num_factura, $valor_factura, 1, $id_destino));

                        //     //                 // if ($linea_modificado['id_proveedor'] == 75){
                        //     //                 //     // primero busca si existe el registro en tabla reservas_ivis
                        //     //                 //     $busca_ivis = $mysql->Consulta_Unico("SELECT * FROM reservas R, reservas_ivis I WHERE (R.id_reserva=".$id_destino.") AND (R.id_reserva=I.id_reserva)");
                        //     //                 // }

                        //     //                 // // $nuevo_evento = $eventos->New_Event($id_ticket, "Se cambio el estado de la reservacion");
                        //     //             }
                        //     //         }
                        //     //     }
                        
                        //     // }else if ($estado == 2){
                        //     //     // if (is_array($conciliados)){
                        //     //     //     if (count($conciliados) > 0){
                        //     //     //         foreach ($conciliados as $linea_modificado) {
    
                        //     //     //             $id_destino = $linea_modificado['id_destino'];
                                            
                        //     //     //             $fecha_factura = $linea_modificado['factura']['fecha'];
                        //     //     //             $num_factura = $linea_modificado['factura']['numero'];
                        //     //     //             $valor_factura = $linea_modificado['factura']['valor'];
    
                        //     //     //             $forma_pago = $linea_modificado['forma_pago']['id_forma_pago'];
                        //     //     //             $proveedor = $linea_modificado['proveedor']['id_proveedor'];
    
                        //     //     //             $actualizar = $mysql->Modificar("UPDATE reservas_destinos SET id_forma_pago=?, id_proveedor=?, fecha_factura=?, num_factura=?, valor_factura=?, revisado=? WHERE id_destinos=?", array($forma_pago, $proveedor, $fecha_factura, $num_factura, $valor_factura, 2, $id_destino));
                        //     //     //         }
                        //     //     //     }
                        //     //     // }
                        //     // }

                        //     // $respuesta['estado'] = true;


                        //         //         // ACTIVIDADES
                        //         //         if (is_array($modificados_actividades)){
                        //         //             if (count($modificados_actividades) > 0){
                        //         //                 foreach ($modificados_actividades as $linea_modificado) {
            
                        //         //                     $id_actividad = $linea_modificado['id_actividad'];
                                                    
                        //         //                     $fecha_factura = $linea_modificado['factura']['fecha'];
                        //         //                     $num_factura = $linea_modificado['factura']['numero'];
                        //         //                     $valor_factura = $linea_modificado['factura']['valor'];
            
                        //         //                     $forma_pago = $linea_modificado['forma_pago']['id_forma_pago'];
                        //         //                     $proveedor = $linea_modificado['proveedor']['id_proveedor'];
            
                        //         //                     $actualizar = $mysql->Modificar("UPDATE reservas_actividades SET id_forma_pago=?, id_proveedor=?, fecha_factura=?, num_factura=?, valor_factura=?, revision=? WHERE id_actividad=?", array($forma_pago, $proveedor, $fecha_factura, $num_factura, $valor_factura, 1, $id_actividad));

                        //         //                     // $nuevo_evento = $eventos->New_Event($id_ticket, "Se cambio el estado de la reservacion");
                        //         //                 }
                        //         //             }
                        //         //         }
                        //         //         // TRANSFER
                        //         //         if (is_array($modificados_transfer)){
                        //         //             if (count($modificados_transfer) > 0){
                        //         //                 foreach ($modificados_transfer as $linea_modificado) {
            
                        //         //                     $id_reserva = $linea_modificado['id_reserva'];
                                                    
                        //         //                     $fecha_factura = $linea_modificado['factura']['fecha'];
                        //         //                     $num_factura = $linea_modificado['factura']['numero'];
                        //         //                     $valor_factura = $linea_modificado['factura']['valor'];
            
                        //         //                     $forma_pago = $linea_modificado['forma_pago'];
                        //         //                     $proveedor = $linea_modificado['proveedor'];
            
                        //         //                     $actualizar = $mysql->Modificar("UPDATE reservas SET id_forma_pago_transfer=?, id_proveedor_transfer=?, fecha_factura_transfer=?, num_factura_transfer=?, valor_factura_transfer=?, revisado=? WHERE id_reserva=?", array($forma_pago, $proveedor, $fecha_factura, $num_factura, $valor_factura, 1, $id_reserva));
                        //         //                 }
                        //         //             }
                        //         //         }

                        //         // if (is_array($conciliados)){
                        //         //     if (count($conciliados) > 0){
                        //         //         foreach ($conciliados as $linea_modificado) {
    
                        //         //             $id_destino = $linea_modificado['id_destino'];
                                            
                        //         //             $fecha_factura = $linea_modificado['factura']['fecha'];
                        //         //             $num_factura = $linea_modificado['factura']['numero'];
                        //         //             $valor_factura = $linea_modificado['factura']['valor'];
    
                        //         //             $forma_pago = $linea_modificado['forma_pago']['id_forma_pago'];
                        //         //             $proveedor = $linea_modificado['proveedor']['id_proveedor'];
    
                        //         //             $actualizar = $mysql->Modificar("UPDATE reservas_destinos SET id_forma_pago=?, id_proveedor=?, fecha_factura=?, num_factura=?, valor_factura=?, revisado=? WHERE id_destinos=?", array($forma_pago, $proveedor, $fecha_factura, $num_factura, $valor_factura, 1, $id_destino));
                        //         //         }
                        //         //     }
                        //         // }

                        // }else{
                        //     $respuesta['error'] = "No se obtuvo información para modificar.";
                        // }                                    
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }
    
                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->post("/reservas_contabilidad_admin", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();                
                $respuesta['estado'] = false;
    
                $respuesta['data'] = $data;                
            
                try{
                    $functions = new Functions();
    
                    $validacion = $functions->Validar_Credenciales($authorization[0]);
    
                    if ($validacion['estado']){
    
                        $mysql = new Database('mvevip_crm');                    
                        $id_asesor_visor = $validacion['asesor']['id_asesor'];                                                
    
                        $eventos = new Events($mysql, $id_asesor_visor);

                        if (isset($data)){
                            $modificados_destinos = $data['modificados']['destinos'];
                            $modificados_actividades = $data['modificados']['actividades'];
                            $modificados_transfer = $data['modificados']['transfer'];
                            $procesar = [];
                            
                            if (is_array($modificados_destinos)){
                                if (count($modificados_destinos) > 0){
                                    foreach ($modificados_destinos as $linea_modificado) {

                                        $id_destino = $linea_modificado['id_destino'];

                                        $forma_pago = $linea_modificado['forma_pago']['id_forma_pago'];
                                        $proveedor = $linea_modificado['proveedor']['id_proveedor'];

                                        $actualizar = $mysql->Modificar("UPDATE reservas_destinos SET id_forma_pago=?, id_proveedor=?, revisado_admin=? WHERE id_destinos=?", array($forma_pago, $proveedor, 1, $id_destino));
                                    }
                                }
                            }

                            if (is_array($modificados_actividades)){
                                if (count($modificados_actividades) > 0){
                                    foreach ($modificados_actividades as $linea_modificado) {

                                        $id_actividad = $linea_modificado['id_actividad'];

                                        $forma_pago = $linea_modificado['forma_pago']['id_forma_pago'];
                                        $proveedor = $linea_modificado['proveedor']['id_proveedor'];

                                        $actualizar = $mysql->Modificar("UPDATE reservas_actividades SET id_forma_pago=?, id_proveedor=?, revision_admin=? WHERE id_actividad=?", array($forma_pago, $proveedor, 1, $id_actividad));
                                    }
                                }
                            }

                            if (is_array($modificados_transfer)){
                                if (count($modificados_transfer) > 0){
                                    foreach ($modificados_transfer as $linea_modificado) {

                                        $id_reserva = $linea_modificado['id_reserva'];

                                        $forma_pago = $linea_modificado['id_forma_pago'];
                                        $proveedor = $linea_modificado['id_proveedor'];

                                        $actualizar = $mysql->Modificar("UPDATE reservas SET id_forma_pago_transfer=?, id_proveedor_transfer=?, revisado_admin=? WHERE id_reserva=?", array($forma_pago, $proveedor, 1, $id_reserva));
                                    }
                                }
                            }

                            $respuesta['estado'] = true;

                        }else{
                            $respuesta['error'] = "No se obtuvo información para modificar.";
                        }                                    
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }
    
                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });
            
            $app->put("/reservas_contabilidad/{id}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                $id = $request->getAttribute('id');
                $respuesta['estado'] = false;
    
                $respuesta['data'] = $data;
                $respuesta['id'] = $id;
            
                try{
                    $functions = new Functions();
    
                    $validacion = $functions->Validar_Credenciales($authorization[0]);
    
                    if ($validacion['estado']){
    
                        $mysql = new Database('mvevip_crm');                    
                        $id_asesor_visor = $validacion['asesor']['id_asesor'];                                                
    
                        $eventos = new Events($mysql, $id_asesor_visor);

                        if (isset($data['razon'])){
                            $razon = $data['razon'];

                            $actualizar = $mysql->Modificar("UPDATE reservas_destinos SET mal_ingreso=? WHERE id_destinos=?", array($razon, $id));

                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = "Debe ingresar una razon o motivo.";
                        }
             
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }
    
                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->put("/proveedor-pago/{modulo}/{tag}/{id}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                $modulo = $request->getAttribute('modulo');
                $tag = $request->getAttribute('tag');
                $id = $request->getAttribute('id');
                $respuesta['estado'] = false;
    
                $respuesta['modulo'] = $modulo;
                $respuesta['tag'] = $tag;
                $respuesta['id'] = $id;
                $respuesta['data'] = $data;
            
                try{
                    $functions = new Functions();
    
                    $validacion = $functions->Validar_Credenciales($authorization[0]);
    
                    if ($validacion['estado']){
    
                        $mysql = new Database('mvevip_crm');                    
                        $id_asesor_visor = $validacion['asesor']['id_asesor'];

                        $valor = 0;
                        if (isset($data['valor'])){
                            $valor = $data['valor'];

                            switch ($modulo) {
                                case 'TRANSFER':
                                    if ($tag == "proveedor"){
                                        $modificar = $mysql->Modificar("UPDATE reservas SET id_proveedor_transfer=? WHERE id_reserva=?", array($valor, $id));
                                    }else if ($tag == "pago"){
                                        $modificar = $mysql->Modificar("UPDATE reservas SET id_forma_pago_transfer=? WHERE id_reserva=?", array($valor, $id));
                                    }
                                    break;
                                case 'FEE':
                                    # code...
                                    break;
                                case 'DESTINO':
                                    if ($tag == "proveedor"){
                                        $modificar = $mysql->Modificar("UPDATE reservas_destinos SET id_proveedor=? WHERE id_destinos=?", array($valor, $id));
                                    }else if ($tag == "pago"){
                                        $modificar = $mysql->Modificar("UPDATE reservas_destinos SET id_forma_pago=? WHERE id_destinos=?", array($valor, $id));
                                    }
                                   
                                    break;
                                case 'ACTIVIDAD':
                                    if ($tag == "proveedor"){
                                        $modificar = $mysql->Modificar("UPDATE reservas_actividades SET id_proveedor=? WHERE id_actividad=?", array($valor, $id));
                                    }else if ($tag == "pago"){
                                        $modificar = $mysql->Modificar("UPDATE reservas_actividades SET id_forma_pago=? WHERE id_actividad=?", array($valor, $id));
                                    }
                                    break;
                                
                                default:
                                    # code...
                                    break;
                            }

                            $respuesta['estado'] = true;
                            
                        }else{
                            $respuesta['error'] = "No se ha recibido ningún cambio.";
                        }
                        
                       
                        
    
             
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }
    
                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->post("/actualizaAdjuntos/{modulo}/{id}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                $modulo = $request->getAttribute('modulo');
                $id = $request->getAttribute('id');
                $files = $request->getUploadedFiles();        
                
                $respuesta['estado'] = false;
    
                $respuesta['modulo'] = $modulo;
                $respuesta['files'] = $files;
                $respuesta['id'] = $id;
                $respuesta['data'] = $data;
            
                try{
                    $functions = new Functions();
                    $carpeta = __DIR__."/../../public/storage";
    
                    $validacion = $functions->Validar_Credenciales($authorization[0]);
    
                    if ($validacion['estado']){
    
                        $mysql = new Database('mvevip_crm');                    
                        $id_asesor_visor = $validacion['asesor']['id_asesor'];

                        if (is_array($files)){
                            if (count($files) > 0){
                                foreach ($files as $key => $value) {
                                    if (isset($data['nombre_'.$key])){
                                        $nombre_archivo = $data['nombre_'.$key];
                                        $extension = pathinfo($nombre_archivo, PATHINFO_EXTENSION);
                                        $nombreActual = $key.date("YmdHis").".".$extension;
                                        $archivo_temporal = $value->file;
       
                                        $destino = $carpeta.'/'.$nombreActual;
                                        
                                        // Guarda el archivo
                                        move_uploaded_file($archivo_temporal, $destino);

                                        // Guarda el registro
                                        switch ($modulo) {
                                            case 'TRANSFER':
                                                if ($key == "cliente0"){
                                                    $modificar = $mysql->Modificar("UPDATE reservas SET voucher_auto=? WHERE id_reserva=?", array($nombreActual, $id));
                                                }else if ($key == "empresa0"){
                                                    $modificar = $mysql->Modificar("UPDATE reservas SET recibo_auto=? WHERE id_reserva=?", array($nombreActual, $id));
                                                }
                                                break;
                                            case 'DESTINO':
                                                if ($key == "cliente0"){
                                                    $modificar = $mysql->Modificar("UPDATE reservas_destinos SET voucher=? WHERE id_destinos=?", array($nombreActual, $id));
                                                }else if ($key == "empresa0"){
                                                    $modificar = $mysql->Modificar("UPDATE reservas_destinos SET recibo=? WHERE id_destinos=?", array($nombreActual, $id));
                                                }
                                                break;
                                            case 'ACTIVIDAD':
                                                if ($key == "cliente0"){
                                                    $modificar = $mysql->Modificar("UPDATE reservas_actividades SET voucher=? WHERE id_actividad=?", array($nombreActual, $id));
                                                }else if ($key == "empresa0"){
                                                    $modificar = $mysql->Modificar("UPDATE reservas_actividades SET recibo=? WHERE id_actividad=?", array($nombreActual, $id));
                                                }
                                                break;
                                        }
                                       
                                        sleep(1.5);

                                        $respuesta['estado'] = true;
                                    }
                                }
                            }
                        }
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }
    
                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            // NOTIFICACIONES

            $app->get("/notificaciones/{id}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_usuario = $request->getAttribute('id');
                $respuesta['estado'] = false;
            
                try{
                    $functions = new Functions();

                    $validacion = $functions->Validar_Credenciales($authorization[0]);

                    if ($validacion['estado']){
                        $mysql = new Database('mvevip_crm');

                        $notificaciones = $mysql->Consulta("SELECT * FROM notificaciones WHERE (id_usuario=".$id_usuario.") AND (leido=0) ORDER BY fecha_hora DESC");

                        $resultados = [];
                        if (is_array($notificaciones)){
                            if (count($notificaciones) > 0){
                                foreach ($notificaciones as $linea_notificacion) {
                                    $newDate = date("F j, Y, g:i a", strtotime($linea_notificacion['fecha_hora']));

                                    $prioridad = false;
                                    if ($linea_notificacion['prioridad'] == 1){
                                        $prioridad = true;
                                    }

                                    array_push($resultados, array(
                                        "id_notificacion" => (int) $linea_notificacion['id_notificacion'],
                                        "id_usuario" => (int) $linea_notificacion['id_usuario'],
                                        "mensaje" => $linea_notificacion['mensaje'],
                                        "id_ticket" => (int) $linea_notificacion['id_ticket'],
                                        "id_reserva" => (int) $linea_notificacion['id_reserva'],
                                        "fecha_hora" => $newDate,
                                        "prioridad" => $prioridad
                                    ));
                                }
                            }
                        }

                        $respuesta['notificaciones'] = $resultados;
                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }
                    
            
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->put("/notificacion_leida/{id}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_notificacion = $request->getAttribute('id');
                $respuesta['estado'] = false;
            
                try{
                    $functions = new Functions();

                    $validacion = $functions->Validar_Credenciales($authorization[0]);

                    if ($validacion['estado']){
                        $mysql = new Database('mvevip_crm');

                        $notificaciones = $mysql->Modificar("UPDATE notificaciones SET leido=1 WHERE id_notificacion=?", array($id_notificacion));
                        
                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }
                    
            
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            // VERIFICA QUE TODO ESTE LISTO PARA CREAR LA RESERVA EN CRM ANTIGUO

            $app->get("/tickets_verifica/{id}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $id_ticket = $request->getAttribute('id');
                $respuesta['estado'] = false;
            
                try{
                    $mysql = new Database('mvevip_crm');
                    $id_usuario = 1;

                    $eventos = new Events($mysql, $id_usuario);
                    
                    $resultados = [];
                    $reserva_completa = true;

                    $consulta = $mysql->Consulta_Unico("SELECT T.id_ticket, T.requerimiento, T.fecha_lim_contacto, T.hora_lim_contacto, T.fecha_alta, T.fecha_modificacion, T.estado, T.id_cliente, C.nombres, C.apellidos, C.correo, C.telefono, C.celular, C.idclienteCRM, C.documento, T.id_asesor, A.asesor, A.apodo FROM tickets T, clientes C, asesores A WHERE (T.id_ticket=".$id_ticket.") AND ((T.id_cliente=C.id_cliente) AND (T.id_asesor=A.id_asesor)) ORDER BY T.fecha_lim_contacto ASC, T.hora_lim_contacto ASC");

                    if (isset($consulta['id_ticket'])){
                        $id_ticket = $consulta['id_ticket'];                        

                        $estado = "";
                        $color = "";
                        switch ($consulta['estado']) {
                            case 0:
                                $estado = "Nueva Tarea";
                                $color = "primary";
                                break;
                            case 1:
                                $estado = "Pendiente de Coordinación";
                                $color = "warning";
                                break;
                            case 2:
                                $estado = "Pendiente Confirmación de Proveedor";
                                $color = "warning";
                                break;
                            case 3:
                                $estado = "Pendiente Elaboracion de Reserva";
                                $color = "info";
                                break;
                            case 4:
                                $estado = "Pendiente Explicacion a Cliente";
                                $color = "success";
                                break;
                            case 6:
                                $estado = "Tarea Terminada";
                                $color = "secondary";
                                break;
                            case 5:
                                $estado = "Tarea Anulada";
                                $color = "danger";
                                break;
                            case 7:
                                $estado = "Espera a Respuesta de Cliente";
                                $color = "warning";
                                break;
                        }

                        // reservas
                        $detalle_reserva = array();
                        $reserva = $mysql->Consulta_Unico("SELECT * FROM reservas WHERE id_ticket=".$id_ticket);
                        if (isset($reserva['id_reserva'])){
                            $id_reserva = $reserva['id_reserva'];

                            if ($reserva['incluye_transfer_alojamiento'] == 0){
                                if (
                                    ($reserva['pickup_destino']<=0) || 
                                    ($reserva['pickup_fecha']=='') ||                                 
                                    ($reserva['pickup_codvuelo']=='') || 
                                    ($reserva['dropoff_destino']<=0) || 
                                    ($reserva['dropoff_fecha']=='') ||                                 
                                    ($reserva['dropoff_codvuelo']=='') || 
                                    ($reserva['num_reserva_transfer']=='') || 
                                    ($reserva['costo_transfer']==0) || 
                                    ($reserva['id_forma_pago_transfer']<=0) || 
                                    ($reserva['monto_paquete']<=0) || 
                                    ($reserva['num_cotizacion']==0) || 
                                    ($reserva['id_proveedor_transfer']<=0)                                
                                    ){
                                    $reserva_completa = false;
                                }
                            }else{
                                if (                                    
                                    ($reserva['monto_paquete']<=0) || 
                                    ($reserva['num_cotizacion']==0)                                    
                                    ){
                                    $reserva_completa = false;
                                }
                            }                    
                            // destinos

                            $destinos = $mysql->Consulta("SELECT * FROM reservas_destinos R, destinos D WHERE (R.id_reserva=".$id_reserva.") AND (R.destino=D.id_destino)");

                            if (is_array($destinos)){
                                if (count($destinos) > 0){
                                    foreach ($destinos as $linea_destino) {
                                        if (($linea_destino['destino']<=0) || ($linea_destino['check_in']=='') || ($linea_destino['check_out']=='') || ($linea_destino['nombre_hotel']=='') || ($linea_destino['direccion_hotel']=='') || ($linea_destino['num_reserva']=='') || ($linea_destino['id_forma_pago']<=0) || ($linea_destino['id_proveedor']<=0) || ($linea_destino['costo']<=0)){
                                            $reserva_completa = false;
                                        }
                                    }
                                }
                            }

                            // actividades

                            $actividades = $mysql->Consulta("SELECT * FROM reservas_actividades WHERE id_reserva=".$id_reserva);

                            if (is_array($actividades)){
                                if (count($actividades) > 0){
                                    foreach ($actividades as $linea_actividad) {
                                        if (($linea_actividad['actividad']=='') || ($linea_actividad['fecha']=='') || ($linea_actividad['num_reserva']=='') || ($linea_actividad['id_forma_pago']<=0) || ($linea_actividad['id_proveedor']<=0) || ($linea_actividad['costo']<=0)){
                                            $reserva_completa = false;
                                        }
                                    }
                                }
                            }

                            // adjuntos

                            $adjuntos = $mysql->Consulta("SELECT * FROM reservas_adjuntos WHERE id_reserva=".$id_reserva);

                            // impuestos

                            $impuestos = $mysql->Consulta("SELECT * FROM reservas_impuestos WHERE id_reserva=".$id_reserva);                            

                            $detalle_reserva = array(
                                "id_reserva" => $reserva['id_reserva'],
                                "nombres" => $reserva['nombres'],
                                "apellidos" => $reserva['apellidos'],
                                "email1" => $reserva['email1'],
                                "email2" => $reserva['email2'],
                                "telefono" => $reserva['telefono'],
                                "id_asesor" => $reserva['id_asesor'],
                                "aerolinea" => $reserva['aerolinea'],
                                "num_adultos" => $reserva['num_adultos'],
                                "num_ninos" => $reserva['num_ninos'],
                                "num_ninos2" => $reserva['num_ninos2'],
                                "num_discapacitados_3edad" => $reserva['num_discapacitados_3edad'],
                                "observacionesReserva" => $reserva['observacionesReserva'],
                                "pickup_destino" => $reserva['pickup_destino'],
                                "pickup_fecha" => $reserva['pickup_fecha'],
                                "pickup_hora" => $reserva['pickup_hora'],
                                "pickup_codvuelo" => $reserva['pickup_codvuelo'],
                                "dropoff_destino" => $reserva['dropoff_destino'],
                                "dropoff_fecha" => $reserva['dropoff_fecha'],
                                "dropoff_hora" => $reserva['dropoff_hora'],
                                "dropoff_codvuelo" => $reserva['dropoff_codvuelo'],
                                "observaciones" => $reserva['observaciones'],
                                "num_reserva_transfer" => $reserva['num_reserva_transfer'],
                                "costo_transfer" => (float) $reserva['costo_transfer'],
                                "id_forma_pago_transfer" => (int) $reserva['id_forma_pago_transfer'],
                                "id_proveedor_transfer" => (int) $reserva['id_proveedor_transfer'],
                                "estado" => $reserva['estado'],
                                "destinos" => $destinos,
                                "actividades" => $actividades,
                                "adjuntos" => $adjuntos,
                                "impuestos" => $impuestos,
                                "pago_impuestos" => $reserva['pagoimpuestos'],
                                "voucher_auto" => $reserva['voucher_auto'],
                                "recibo_auto" => $reserva['recibo_auto'],
                                "monto_paquete" => (float) $reserva['monto_paquete'],
                                "monto_adicional" => (float) $reserva['monto_adicional'],
                                "numero_cotizacion" => $reserva['num_cotizacion'],
                                "incluye_transfer_alojamiento" => (int) $reserva['incluye_transfer_alojamiento']
                            );
                        }

                        $resultados = array(
                            "id_ticket" => $consulta['id_ticket'],
                            "id_cliente" => $consulta['id_cliente'],
                            "cliente" => array(
                                "documento" => $consulta['documento'],
                                "nombres" => $consulta['nombres'],
                                "apellidos" => $consulta['apellidos'],
                                "correo" => $consulta['correo'],
                                "telefono" => $consulta['telefono'],
                                "celular" => $consulta['celular'],
                                "idclienteCRM" => $consulta['idclienteCRM'],
                            ),
                            "id_asesor" => $consulta['id_asesor'],
                            "asesor" => array(
                                "nombres" => $consulta['asesor'],
                                "apodo" => $consulta['apodo']
                            ),
                            "requerimiento" => $consulta['requerimiento'],
                            "limite" => array(
                                "fecha" => $consulta['fecha_lim_contacto'],
                                "hora" => $consulta['hora_lim_contacto'],
                            ),                                    
                            "fecha_alta" => $consulta['fecha_alta'],
                            "fecha_modificacion" => $consulta['fecha_modificacion'],
                            "estado" => array(
                                "valor" => (int) $consulta['estado'],
                                "descripcion" => $estado,
                                "color" => $color
                            ),                            
                            "reserva" => $detalle_reserva
                        );
                    }
                    
                    $respuesta['consulta'] = $resultados;
                    $respuesta['reserva_completa'] = $reserva_completa;

                    if (!$reserva_completa){
                        $respuesta['error'] = "La reserva no está completa. Debe completar toda la información para poder finalizar.";
                    }else{                                                
                        $respuesta['estado'] = true;                            
                    }                            
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->put("/cancelar_reserva/{id}", function(Request $request, Response $response){            
                $authorization = $request->getHeader('Authorization');
                $id_ticket = $request->getAttribute('id');
                $data = $request->getParsedBody();
                $respuesta['estado'] = false;   
                
                $respuesta['data'] = $data;
            
                try{
                    $modificacion = "";
                    if (isset($data["modificacion"])){
                        $modificacion = $data["modificacion"];
                    }

                    if ($modificacion!=""){
                        $functions = new Functions();

                        $validacion = $functions->Validar_Credenciales($authorization[0]);
    
                        if ($validacion['estado']){
                            $mysql = new Database('mvevip_crm');
                            $functions = new Functions();    
                            $id_usuario = $validacion['asesor']['id_asesor'];
    
                            $eventos = new Events($mysql, $id_usuario);
                                
                            switch ($modificacion) {
                                
                                
                                default:
                                    # code...
                                    break;
                            }
                        }else{
                            $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                        }
                    }                    
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/reporte_general/{from}/{to}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $from = $request->getAttribute('from');                
                $to = $request->getAttribute('to');                
                $respuesta['estado'] = false;
            
                try{
                   
                    $functions = new Functions();

                    $validacion = $functions->Validar_Credenciales($authorization[0]);

                    if ($validacion['estado']){
                        $mysql = new Database('mvevip_crm');                        
                        $id_usuario = $validacion['asesor']['id_asesor'];

                        $resultados = [];
                        
                        $transfers_csv = [];
                        $destinos_csv = [];
                        $actividades_csv = [];
                        $impuestos_csv = [];
                            
                        $reservas = $mysql->Consulta("SELECT R.id_reserva, R.id_ticket, R.apellidos, C.idclienteCRM, R.nombres, R.aerolinea AS id_aerolinea, A.aerolinea, 
                        R.num_adultos, R.num_ninos, R.num_ninos2, R.num_discapacitados_3edad, R.pickup_fecha, D.nombre AS destino, R.pickup_codvuelo, R.observaciones, R.observacionesReserva,
                        R.monto_paquete, R.num_reserva_transfer, R.costo_transfer, P.proveedor, F.forma_pago, R.num_cotizacion, T.estado
                        FROM reservas R
                        LEFT JOIN tickets T
                        ON R.id_ticket=T.id_ticket
                        LEFT JOIN clientes C
                        ON T.id_cliente=C.id_cliente
                        LEFT JOIN aerolineas A
                        ON R.aerolinea=A.id_aerolinea
                        LEFT JOIN destinos D
                        ON R.pickup_destino=D.id_destino
                        LEFT JOIN proveedores P
                        ON R.id_proveedor_transfer=P.id_proveedor
                        LEFT JOIN formas_pago F
                        ON F.id_forma_pago=R.id_forma_pago_transfer
                        WHERE
                        (R.pickup_fecha BETWEEN '".$from."' AND '".$to."') AND (T.estado=6)");

                        if (is_array($reservas)){
                            if (count($reservas) > 0){
                                foreach ($reservas as $linea_reserva) {
                                    array_push($transfers_csv, [ $linea_reserva['apellidos'], $linea_reserva['nombres'], $linea_reserva['idclienteCRM'], $linea_reserva['aerolinea'], $linea_reserva['pickup_fecha'], $linea_reserva['destino'], $linea_reserva['monto_paquete'], $linea_reserva['num_reserva_transfer'], $linea_reserva['costo_transfer'], $linea_reserva['proveedor'], $linea_reserva['forma_pago'], $linea_reserva['num_cotizacion'] ]);
                                    // destinos 
                                   
                                    $destinos = $mysql->Consulta("SELECT
                                    D.id_destinos, E.nombre AS destino, D.check_in, D.check_out, D.nombre_hotel, D.direccion_hotel, D.num_reserva, P.proveedor, F.forma_pago, D.costo, D.observacion
                                    FROM reservas_destinos D
                                    LEFT JOIN destinos E
                                    ON D.destino=E.id_destino
                                    LEFT JOIN proveedores P
                                    ON D.id_proveedor=P.id_proveedor
                                    LEFT JOIN formas_pago F
                                    ON F.id_forma_pago=D.id_forma_pago
                                    WHERE D.id_reserva=".$linea_reserva['id_reserva']);
                                    
                                    if (is_array($destinos)){
                                        if (count($destinos) > 0){
                                            foreach ($destinos as $linea_destino) {
                                                array_push($destinos_csv, [ $linea_reserva['apellidos'], $linea_reserva['nombres'], $linea_reserva['idclienteCRM'], $linea_destino['destino'], $linea_destino['check_in'], $linea_destino['check_out'], $linea_destino['nombre_hotel'], $linea_destino['num_reserva'], $linea_destino['proveedor'], $linea_destino['forma_pago'], $linea_destino['costo'], $linea_destino['observacion'] ]);
                                            }
                                        }
                                    }

                                    // actividades
                                    $actividades = $mysql->Consulta("SELECT 
                                    D.id_actividad, D.actividad, D.fecha, D.num_reserva, P.proveedor, F.forma_pago, D.costo, D.observaciones
                                    FROM reservas_actividades D
                                    LEFT JOIN proveedores P
                                    ON D.id_proveedor=P.id_proveedor
                                    LEFT JOIN formas_pago F
                                    ON F.id_forma_pago=D.id_forma_pago
                                    WHERE D.id_reserva=".$linea_reserva['id_reserva']);

                                    if (is_array($actividades)){
                                        if (count($actividades) > 0){
                                            foreach ($actividades as $linea_actividad) {
                                                array_push($actividades_csv, [ $linea_reserva['apellidos'], $linea_reserva['nombres'], $linea_reserva['idclienteCRM'], $linea_actividad['actividad'], $linea_actividad['fecha'], $linea_actividad['num_reserva'], $linea_actividad['proveedor'], $linea_actividad['forma_pago'], $linea_actividad['costo'], $linea_actividad['observaciones'] ]);
                                            }
                                        }
                                    }

                                     // impuestos
                                     $impuestos = $mysql->Consulta("SELECT 
                                     descripcion, num_noches AS cantidad, valor, total FROM reservas_impuestos WHERE id_reserva=".$linea_reserva['id_reserva']);
 
                                     if (is_array($impuestos)){
                                         if (count($impuestos) > 0){
                                             foreach ($impuestos as $linea_impuesto) {
                                                 array_push($impuestos_csv, [ $linea_reserva['apellidos'], $linea_reserva['nombres'], $linea_reserva['idclienteCRM'], $linea_impuesto['descripcion'], $linea_impuesto['cantidad'], $linea_impuesto['valor'], $linea_impuesto['total'] ]);
                                             }
                                         }
                                     }

                                    array_push($resultados, array(
                                        "reserva" => $linea_reserva,
                                        "destinos" => $destinos,
                                        "actividades" => $actividades,
                                        "impuestos" => $impuestos
                                    ));

                                    $file_handle = fopen("transfers.csv", 'w');
                                    foreach ($transfers_csv as $linea) {
                                        fputcsv($file_handle, $linea, ';', '"');
                                    }
                                    rewind($file_handle);
                                    fclose($file_handle);

                                    $file_handle = fopen("destinos.csv", 'w');
                                    foreach ($destinos_csv as $linea) {
                                        fputcsv($file_handle, $linea, ';', '"');
                                    }
                                    rewind($file_handle);
                                    fclose($file_handle);

                                    $file_handle = fopen("actividades.csv", 'w');
                                    foreach ($actividades_csv as $linea) {
                                        fputcsv($file_handle, $linea, ';', '"');
                                    }
                                    rewind($file_handle);
                                    fclose($file_handle);

                                    $file_handle = fopen("impuestos.csv", 'w');
                                    foreach ($impuestos_csv as $linea) {
                                        fputcsv($file_handle, $linea, ';', '"');
                                    }
                                    rewind($file_handle);
                                    fclose($file_handle);
                                }
                            }
                        }

                        $respuesta['reservas'] = $resultados;    
                        $respuesta['csv']['transfers'] = $transfers_csv;
                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }                           
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/correccion_fechas", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');                
                $respuesta['estado'] = false;
            
                try{
                   
                    $functions = new Functions();

                    $validacion = $functions->Validar_Credenciales($authorization[0]);

                    if ($validacion['estado']){
                        $mysql = new Database('mvevip_crm');                        
                        $id_usuario = $validacion['asesor']['id_asesor'];

                        $resultados = [];                        
                            
                        $reservas = $mysql->Consulta("SELECT id_reserva, pickup_fecha, dropoff_fecha FROM reservas");

                        if (is_array($reservas)){
                            if (count($reservas) > 0){
                                foreach ($reservas as $linea_reserva) {
                                    $id_reserva = $linea_reserva['id_reserva']; 

                                    // destinos 
                                    $inicio = "";                                    
                                    $final = "";

                                    $destinos = $mysql->Consulta("SELECT id_destinos, check_in, check_out FROM reservas_destinos WHERE id_reserva=".$linea_reserva['id_reserva']);

                                    if (is_array($destinos)){
                                        if (count($destinos)> 0){
                                            $cont = 0;
                                            foreach ($destinos as $linea_destino) {
                                                if (($inicio != $linea_destino['check_in']) && ($cont == 0)){
                                                    $inicio = $linea_destino['check_in'];
                                                }
                                                if ($final != $linea_destino['check_out']){
                                                    $final = $linea_destino['check_out'];
                                                }
                                                $cont += 1;
                                            }
                                        }
                                    }

                                    array_push($resultados, array(
                                        "reserva" => $linea_reserva,
                                        "destinos" => $destinos,
                                        "inicio" => $inicio,
                                        "final" => $final
                                    ));

                                    // actualizar
                                    $actualizar = $mysql->Modificar("UPDATE reservas SET pickup_fecha=?, dropoff_fecha=? WHERE id_reserva=?", array($inicio, $final, $id_reserva));
                                }
                            }
                        }

                        $respuesta['reservas'] = $resultados;
                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }                           
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });


            $app->get("/facturas_pendientes/{id_cliente}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_cliente = $request->getAttribute('id_cliente');

                $respuesta['estado'] = false;
            
                try{                   
                    $functions = new Functions();

                    $validacion = $functions->Validar_Credenciales($authorization[0]);

                    if ($validacion['estado']){
                        $mysql = new Database('mvevip_crm');                        
                        $id_usuario = $validacion['asesor']['id_asesor'];

                        $crm = new CRM_API();

                        $facturas_pendientes = $crm->Obtener_Facturas_Pendientes($id_cliente);

                        $respuesta['pendientes'] = $facturas_pendientes;
                       
                        $respuesta['estado'] = true;
                    }else{
                        $respuesta['error'] = "No se encuentra su credencial o su token se encuentra caducado.";
                    }                           
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });
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

        // PLACE TO PLAY

        $app->group('/p2p', function() use ($app) {

            $app->get("/verifica/{reference}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');                
                $reference = $request->getAttribute('reference');
                            
                try{
                    $placetopay = new PlacetoPay("live");

                    $respuesta['pago'] = $placetopay->check_status($reference);

                    $respuesta['estado'] = true;

                }catch(PDOException $e){                    
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });
            
            



            $app->post("/suscripcion", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');            
                $data = $request->getParsedBody();

                $respuesta['estado'] = false;
            
                try{
                    $p2p = new PlacetoPay("test", "mvevip_suscription");

                    $payment = array(
                        "documento" => $data['documento'],
                        "nombres" => $data['nombres'],
                        "apellidos" => $data['apellidos'],
                        "correo" => $data['correo'],
                        "celular" => $data['celular'],
                        "descripcion" => $data['descripcion'],
                        "tipo" => $data['tipo'],
                    );

                    $referencia = "REF-".date("YmdHis");

                    $respuesta['suscripcion'] = $p2p->make_suscription($referencia, $payment);
                    
                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->post("/cobro_suscripcion/{token}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');            
                $token = $request->getAttribute('token');
                $data = $request->getParsedBody();

                $respuesta['estado'] = false;    
            
                try{
                    $p2p = new PlacetoPay("live", "mvevip_suscription");                   

                    $payment = array(
                        "documento" => $data['documento'],
                        "tipo_documento" => $data['tipo'],
                        "nombres" => $data['nombres'],
                        "apellidos" => $data['apellidos'],
                        "correo" => $data['correo'],
                        "celular" => $data['celular'],                                               
                        "valor" => $data['total'],
                        "tipo" => $data['paquete']
                    );
                    
                    $referencia = "REF_SUS-".date("YmdHis");

                    $respuesta['pago_suscripcion'] = $p2p->make_payment_suscription($token, $payment);                    
                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->delete("/anulacion/{internalReference}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');            
                $internalReference = $request->getAttribute('internalReference');             

                $respuesta['estado'] = false;    
            
                try{
                    $p2p = new PlacetoPay("test", "mvevip_suscription");

                    $respuesta['pago_suscripcion'] = $p2p->reversar($internalReference);
                    $respuesta['estado'] = true;
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            // Generacion de pago manual de suscripcion
            $app->get("/cobro/{id_suscripcion}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');            
                $id_suscripcion = $request->getAttribute('id_suscripcion');

                $respuesta['estado'] = false;    
            
                try{
                    $p2p = new PlacetoPay("live", "mvevip_suscription");
                    $mysql = new Database("vtgsa_ventas");

                    $informacion = $mysql->Consulta("SELECT * FROM pagos_p2p_tokens WHERE id_suscripcion=".$id_suscripcion);

                    if (isset($informacion['id_suscripcion'])){
                        
                        $tipo_documento = $informacion['tipo_documento'];
                        $documento = $informacion['documento'];
                        $apellidos = $informacion['apellidos'];
                        $nombres = $informacion['nombres'];
                        $celular = $informacion['celular'];
                        $correo = $informacion['correo'];
                        $token = $informacion['token'];

                        $cuotas = $mysql->Consulta("SELECT * FROM pagos_p2p_cuotas WHERE (id_pago=".$id_suscripcion.") AND (estado=0) ORDER BY fecha ASC");

                        $respuesta['cuotas'] = $cuotas;

                    }else{
                        $respuesta['error'] = "No se encontro ninguna suscripcion.";
                    }                                                            
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            }); 
        });

        // PAGOS STRIPE

        $app->group('/stripe', function() use ($app) {

            $app->post("/usuario", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');            
                $data = $request->getParsedBody();

                $respuesta['estado'] = false;    
            
                try{
                    if (isset($authorization[0])){
                        $autenticacion = new Authentication();
                        $session = $autenticacion->Valida_Sesion($authorization[0]);

                        $mysql = new Database("vtgsa_ventas");
                        $funciones = new Functions();
    
                        if ($session['estado']){
                            $id_asesor = $session['usuario']['id_usuario'];
                            
                            if ((isset($data['nombres'])) && (!empty($data['nombres'])) && (isset($data['telefono'])) && (!empty($data['telefono'])) && (isset($data['email'])) && (!empty($data['email'])) && (isset($data['direccion'])) && (!empty($data['direccion'])) && (isset($data['numero'])) && (!empty($data['numero'])) && (isset($data['mes'])) && (!empty($data['mes'])) && (isset($data['anio'])) && (!empty($data['anio'])) && (isset($data['cvc'])) && (!empty($data['cvc']))){

                                $descripcion = "";
                                if (isset($data['descripcion'])){
                                    $descripcion = $data['descripcion'];
                                }

                                $email = "";
                                if (isset($data['email'])){
                                    $email = $data['email'];
                                }

                                $nombres = "";
                                if (isset($data['nombres'])){
                                    $nombres = $data['nombres'];
                                }

                                $telefono = "";
                                if (isset($data['telefono'])){
                                    $telefono = $data['telefono'];
                                }

                                $direccion = "";
                                if (isset($data['direccion'])){
                                    $direccion = $data['direccion'];
                                }

                                $numero = "";
                                if (isset($data['numero'])){
                                    $numero = $data['numero'];
                                }

                                $mes = "";
                                if (isset($data['mes'])){
                                    $mes = $data['mes'];
                                }

                                $anio = "";
                                if (isset($data['anio'])){
                                    $anio = $data['anio'];
                                }

                                $cvc = "";
                                if (isset($data['cvc'])){
                                    $cvc = $data['cvc'];
                                }    

                                if ((intval($mes) > 0) && (intval($mes) < 13)){

                                    if (intval($anio) >= 2022){
                                        $verifica = $mysql->Consulta_Unico("SELECT * FROM stripe_clientes WHERE email='".$email."'");

                                        if (!isset($verifica['id_cliente'])){
                                            $stripe = new Stripe(STRIPE);
        
                                            // CREACION DE CLIENTE/USUARIO
        
                                            $cliente = [];
                                            array_push($cliente, array(
                                                "description" => $descripcion,
                                                "email" => $email,
                                                "name" => $nombres,
                                                "phone" => $telefono,                    
                                                "balance" => 0
                                            ));
                                            
                                            $nuevo_cliente = $stripe->setNewCustomer($cliente);
                                            // $respuesta["cliente"] = $nuevo_cliente;
        
                                            if (isset($nuevo_cliente['id'])){
                                                $codigo_stripe = $nuevo_cliente['id'];
        
                                                $id_cliente = $mysql->Ingreso("INSERT INTO stripe_clientes (id, nombres, direccion, telefono, email, descripcion, estado) VALUES (?,?,?,?,?,?,?)", array($codigo_stripe, $nombres, $direccion, $telefono, $email, $descripcion, 0));

                                                $tarjeta = [
                                                    'card' => [
                                                        'number' => $numero,
                                                        'exp_month' => (int) $mes,
                                                        'exp_year' => (int) $anio,
                                                        'cvc' => $cvc,
                                                    ]
                                                ];
                            
                                                $idtoken = $stripe->TokenCard($tarjeta);
                                                // $respuesta["idtoken"] = $idtoken;
    
                                                if (isset($idtoken['id'])){
                                                    $token = $idtoken['id'];
    
                                                    $cliente = [];
                                                    array_push($cliente, array(
                                                        "card" => $idtoken
                                                    ));
                                                                    
                                                    $vinculacion_tarjeta = $stripe->UpdateCustomer($codigo_stripe, $cliente); 
                                                    // $respuesta['vinculacion_tarjeta'] = $vinculacion_tarjeta;
                                                    
                                                    if (isset($vinculacion_tarjeta['id'])){
                                                        $codigo_cliente = $vinculacion_tarjeta['id'];
                                                        $vinculacion = $mysql->Ingreso("INSERT INTO stripe_cards (id_cliente, id, numero, mes, anio, cvc, estado) VALUES (?,?,?,?,?,?,?)", array($codigo_cliente, $token, $numero, $mes, $anio, $cvc, 0));
                                                    
                                                        $respuesta['vinculacion'] = array("id" => $token, "id_card" => $vinculacion);
                
                                                        $respuesta['estado'] = true;  
                                                    }else{
                                                        $respuesta['error'] = "Hubo un error al vincular la tarjeta al cliente seleccionado.";
                                                    }
                                                }else{
                                                    $respuesta['error'] = "Hubo un error al tokenizar la tarjeta.";
                                                }
        
                                                $respuesta['id_cliente'] = array("id" => $codigo_stripe, "id_cliente" => $id_cliente);
        
                                                $respuesta['estado'] = true;   
                                            }else{
                                                $respuesta['error'] = "Hubo un error al crear el cliente.";
                                            }
                                        }else{
                                            $respuesta['error'] = "Ya existe un cliente registrado con este correo electrónico.";
                                        }
                                    }else{
                                        $respuesta['error'] = "El anio de caducidad debe ser superior al actual.";
                                    }
                                }else{
                                    $respuesta['error'] = "El mes de caducidad debe estar entre 1 y 12.";
                                }

                                
                            }else{
                                $respuesta['error'] = "Debe ingresar todos los campos con asterisco.";
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

            $app->put("/usuario/{id}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id = $request->getAttribute('id');
                $data = $request->getParsedBody();

                $respuesta['estado'] = false;    
            
                try{
                    $descripcion = "";
                    if (isset($data['descripcion'])){
                        $descripcion = $data['descripcion'];
                    }

                    $email = "";
                    if (isset($data['email'])){
                        $email = $data['email'];
                    }

                    $nombres = "";
                    if (isset($data['nombres'])){
                        $nombres = $data['nombres'];
                    }

                    $telefono = "";
                    if (isset($data['telefono'])){
                        $telefono = $data['telefono'];
                    }

                    $direccion = "";
                    if (isset($data['direccion'])){
                        $direccion = $data['direccion'];
                    }

                    // $tarjeta = "";
                    // if (isset($data['tarjeta'])){
                    //     $tarjeta = $data['tarjeta'];
                    // }

                    $stripe = new Stripe(STRIPE);

                    // CREACION DE CLIENTE/USUARIO

                    $cliente = [];
                    array_push($cliente, array(
                        "description" => $descripcion,
                        "email" => $email,
                        "name" => $nombres,
                        "phone" => $telefono
                    ));
                                    
                    $respuesta['verifica'] = $stripe->UpdateCustomer($id, $cliente);
                    $respuesta['estado'] = true;                
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });
            
            $app->put("/tarjeta/{id}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id = $request->getAttribute('id');
                $data = $request->getParsedBody();

                $respuesta['estado'] = false;    
            
                try{
                    if (isset($authorization[0])){
                        $autenticacion = new Authentication();
                        $session = $autenticacion->Valida_Sesion($authorization[0]);

                        $mysql = new Database("vtgsa_ventas");
                        $funciones = new Functions();
    
                        if ($session['estado']){
                            $id_asesor = $session['usuario']['id_usuario'];
                            
                            if ((isset($data['numero'])) && (!empty($data['numero'])) && (isset($data['mes'])) && (!empty($data['mes'])) && (isset($data['anio'])) && (!empty($data['anio'])) && (isset($data['cvc'])) && (!empty($data['cvc']))){

                                $numero = "";
                                if (isset($data['numero'])){
                                    $numero = $data['numero'];
                                }

                                $mes = "";
                                if (isset($data['mes'])){
                                    $mes = $data['mes'];
                                }

                                $anio = "";
                                if (isset($data['anio'])){
                                    $anio = $data['anio'];
                                }

                                $cvc = "";
                                if (isset($data['cvc'])){
                                    $cvc = $data['cvc'];
                                }               

                                if ((intval($mes) > 0) && (intval($mes) < 13)){

                                    if (intval($anio) >= 2022){
                                        // $verifica = $mysql->Consulta_Unico("SELECT * FROM stripe_cards WHERE numero='".$numero."'");

                                        // if (!isset($verifica['id_cliente'])){
                                            $stripe = new Stripe(STRIPE);
                                            $tarjeta = [
                                                'card' => [
                                                    'number' => $numero,
                                                    'exp_month' => (int) $mes,
                                                    'exp_year' => (int) $anio,
                                                    'cvc' => $cvc,
                                                ]
                                            ];
                        
                                            $idtoken = $stripe->TokenCard($tarjeta);
                                            // $respuesta["idtoken"] = $idtoken;

                                            if (isset($idtoken['id'])){
                                                $token = $idtoken['id'];

                                                $cliente = [];
                                                array_push($cliente, array(
                                                    "card" => $idtoken
                                                ));
                                                                
                                                $vinculacion_tarjeta = $stripe->UpdateCustomer($id, $cliente); 
                                                // $respuesta['vinculacion_tarjeta'] = $vinculacion_tarjeta;
                                                
                                                if (isset($vinculacion_tarjeta['id'])){
                                                    $codigo_cliente = $vinculacion_tarjeta['id'];
                                                    $vinculacion = $mysql->Ingreso("INSERT INTO stripe_cards (id_cliente, id, numero, mes, anio, cvc, estado) VALUES (?,?,?,?,?,?,?)", array($codigo_cliente, $token, $numero, $mes, $anio, $cvc, 0));
                                                
                                                    $respuesta['vinculacion'] = array("id" => $token, "id_card" => $vinculacion);
            
                                                    $respuesta['estado'] = true;  
                                                }else{
                                                    $respuesta['error'] = "Hubo un error al vincular la tarjeta al cliente seleccionado.";
                                                }
                                            }else{
                                                $respuesta['error'] = "Hubo un error al tokenizar la tarjeta.";
                                            }
                                        // }else{
                                        //     $respuesta['error'] = "Ya existe esta tarjeta asociada al cliente.";
                                        // }
                                    }else{
                                        $respuesta['error'] = "El anio de caducidad debe ser superior al actual.";
                                    }
                                }else{
                                    $respuesta['error'] = "El mes de caducidad debe estar entre 1 y 12.";
                                }

                                
                            }else{
                                $respuesta['error'] = "Debe ingresar todos los campos con asterisco.";
                            }
                            
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

            $app->post("/pago/{id}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id = $request->getAttribute('id');
                $data = $request->getParsedBody();

                $respuesta['estado'] = false;    
            
                try{
                    if (isset($authorization[0])){
                        $autenticacion = new Authentication();
                        $session = $autenticacion->Valida_Sesion($authorization[0]);

                        $mysql = new Database("vtgsa_ventas");
                        $funciones = new Functions();
    
                        if ($session['estado']){
                            $id_asesor = $session['usuario']['id_usuario'];
                            
                            if ((isset($data['monto'])) && (!empty($data['monto'])) && (isset($data['descripcion'])) && (!empty($data['descripcion']))){

                                $monto = "";
                                if (isset($data['monto'])){
                                    $monto = floatval($data['monto']) * 100;
                                }

                                $descripcion = "";
                                if (isset($data['descripcion'])){
                                    $descripcion = $data['descripcion'];
                                }          

                                if (floatval($monto) > 0){
                                    $stripe = new Stripe(STRIPE);
                                    $pago = [];
                                    array_push($pago, array(
                                        "amount" => $monto,
                                        "currency" => "usd",
                                        "customer" => $id,
                                        "description" => $descripcion                    
                                    ));
                        
                                    // $respuesta['data'] = $pago;
                                    $nuevo_pago = $stripe->setNewCharge($pago);
                                    // $respuesta['nuevo_pago'] = $nuevo_pago;

                                    if (isset($nuevo_pago['id'])){
                                        $token = $nuevo_pago['id'];
                                        $url_receipt = $nuevo_pago['receipt_url'];
                                        $fecha_alta = date("Y-m-d H:i:s");

                                        $monto_real = $monto/100;
                                        $id_pago = $mysql->Ingreso("INSERT INTO stripe_pagos (id_cliente, id, monto, descripcion, url_receipt, fecha_alta, estado) VALUES (?,?,?,?,?,?,?)", array($id, $token, $monto_real, $descripcion, $url_receipt, $fecha_alta, 1));

                                        $respuesta['pago'] = array("id" => $token, "url" => $url_receipt);
                                        $respuesta['estado'] = true;  
                                    }else{
                                        $respuesta['error'] = "Hubo un error al generar el pago con la tarjeta del cliente.";
                                        $respuesta['detalle'] = $nuevo_pago;
                                    }
                                }else{
                                    $respuesta['error'] = "El monto a procesar debe ser mayor a cero.";
                                }
                                
                            }else{
                                $respuesta['error'] = "Debe ingresar todos los campos con asterisco.";
                            }
                            
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

            $app->delete("/pago/{id}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id = $request->getAttribute('id');

                $respuesta['estado'] = false;    
            
                try{
                    if (isset($authorization[0])){
                        $autenticacion = new Authentication();
                        $session = $autenticacion->Valida_Sesion($authorization[0]);

                        $mysql = new Database("vtgsa_ventas");
                        $funciones = new Functions();
    
                        if ($session['estado']){
                            $id_asesor = $session['usuario']['id_usuario'];
                            $razones = ['duplicate', 'fraudulent', 'requested_by_customer'];
                    
                            $cancelacion = [];
                            array_push($cancelacion, array(
                                "charge" => $id,
                                "reason" => $razones[2]
                            ));
                            $stripe = new Stripe(STRIPE);
                            $reembolso = $stripe->setRefunds($cancelacion);

                            if (isset($reembolso['id'])){
                                $token_reembolso = $reembolso['id'];
                                $fecha_alta = date("Y-m-d H:i:s");
                                
                                $id_reembolso = $mysql->Ingreso("INSERT INTO stripe_reembolsos (id_pago, id, descripcion, fecha_alta, estado) VALUES (?,?,?,?,?)", array($id, $token_reembolso, $razones[2], $fecha_alta, 0));

                                $modificar = $mysql->Modificar("UPDATE stripe_pagos SET estado=? WHERE id=?", array(2, $id));

                                $respuesta['reembolso'] = array("id" => $token_reembolso, "id_reembolso" => $id_reembolso);

                                $respuesta['estado'] = true;
                            }else{
                                $respuesta['error'] = "No se pudo realizar el reembolso del pago.";
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

            $app->get("/clientes-stripe", function(Request $request, Response $response){
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

                            $buscador = "";
                            if (isset($params['buscador'])){
                                $buscador = $params['buscador'];
                            }
                            
                            $consulta = $mysql->Consulta("SELECT 
                            * FROM stripe_clientes WHERE ((nombres LIKE '%".$buscador."%') OR (email LIKE '%".$buscador."%') OR (telefono LIKE '%".$buscador."%')) ORDER BY id_cliente DESC");

                            $filtrado = [];
                            if (is_array($consulta)){
                                if (count($consulta) > 0){
                                    foreach ($consulta as $linea) {

                                        array_push($filtrado, array(
                                            "id" => (int) $linea['id_cliente'],
                                            "codigo" => $linea['id'],
                                            "nombres" => $linea['nombres'],
                                            "direccion" => $linea['direccion'],
                                            "telefono" => $linea['telefono'],
                                            "email" => $linea['email']
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

            $app->get("/pagos-stripe", function(Request $request, Response $response){
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

                            $from = date("Y-m-01");
                            $to = date("Y-m-t");

                            if (isset($params['from'])){
                                $from = $params['from'];
                            }

                            if (isset($params['to'])){
                                $to = $params['to'];
                            }

                            $buscador = "";
                            if (isset($params['buscador'])){
                                $buscador = $params['buscador'];
                            }
                            
                            $consulta = $mysql->Consulta("SELECT 
                            P.id_pago, P.id AS codigo_pago, P.monto, P.descripcion, P.url_receipt, P.fecha_alta, P.estado, P.id_cliente AS codigo_cliente, C.id_cliente, C.nombres, C.direccion, C.telefono, C.email, C.estado AS estado_cliente
                            FROM stripe_pagos P
                            LEFT JOIN stripe_clientes C
                            ON P.id_cliente=C.id
                            WHERE (P.fecha_alta BETWEEN '".$from."' AND '".$to."') AND ((C.nombres LIKE '%".$buscador."%') OR (C.email LIKE '%".$buscador."%'))
                            ORDER BY P.fecha_alta DESC");

                            $filtrado = [];
                            if (is_array($consulta)){
                                if (count($consulta) > 0){
                                    foreach ($consulta as $linea) {
                                        $descripcion_estado = "";
                                        $color_estado = "light";

                                        switch ($linea['estado']) {
                                            case 0:
                                                $descripcion_estado = "Pago Pendiente";
                                                $color_estado = "info";
                                                break;
                                            case 1:
                                                $descripcion_estado = "Pago Realizado";
                                                $color_estado = "success";
                                                break;
                                            case 2:
                                                $descripcion_estado = "Reembolso";
                                                $color_estado = "danger";
                                                break;
                                        }

                                        array_push($filtrado, array(
                                            "id_pago" => (int) $linea['id_pago'],
                                            "codigo" => $linea['codigo_pago'],
                                            "monto" => (float) $linea['monto'],
                                            "descripcion" => $linea['descripcion'],
                                            "fecha_alta" => $linea['fecha_alta'],
                                            "url" => $linea['url_receipt'],
                                            "estado" => array(
                                                "id" => (int) $linea['estado'],
                                                "descripcion" => $descripcion_estado,
                                                "color" => $color_estado
                                            ),
                                            "cliente" => array(
                                                "id" => (int) $linea['id_cliente'],
                                                "codigo" => $linea['codigo_cliente'],
                                                "nombres" => $linea['nombres'],
                                                "direccion" => $linea['direccion'],
                                                "telefono" => $linea['telefono'],
                                                "email" => $linea['email']
                                            )
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

        });
         
        // GRUPO PARA DIFERENTES USOS

        $app->group('/funciones', function() use ($app) {
            
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

        // GRUPO PARA FUNCIONES DE CAYETANO

        $app->group('/cayetano', function() use ($app) {           

            $app->get("/validar", function(Request $request, Response $response){                
                $respuesta['estado'] = false;
                $params = $request->getQueryParams();

                try{
                    $mysql = new Database("sparedes");

                    if (isset($params['cedula'])){

                        $consulta = $mysql->Consulta_Unico("SELECT * FROM invitados WHERE (codigo='".$params['cedula']."')");

                        if (isset($consulta['id_invitado'])){
                            $respuesta['codigo'] = base64_encode($consulta['id_invitado']."_".date("YmdHis"));
                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = "Ops, tenemos un problema con nuestros servidores. Si deseas puedes contactarnos a nuestro Whatsapp.";
                        }                        
                    }else{
                        $respuesta['error'] = "Debe ingresar obligatoriamente su numero de cedula.";
                    }

                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/invitado", function(Request $request, Response $response){                
                $authorization = $request->getHeader('Authorization');
                $respuesta['estado'] = false;
                
                try{                    
                    if (isset($authorization[0])){
                        $decodificado = base64_decode($authorization[0]);

                        $id_invitado = explode("_", $decodificado)[0];

                        $mysql = new Database("sparedes");

                        $consulta = $mysql->Consulta_Unico("SELECT * FROM invitados WHERE id_invitado=".$id_invitado);

                        if (isset($consulta['id_invitado'])){

                            // documentos 
                            $consulta_documentos = $mysql->Consulta("SELECT * FROM documentos WHERE id_invitado=".$id_invitado);
                            $documentos = [];

                            if (is_array($consulta_documentos)){
                                if (count($consulta_documentos) > 0){
                                    foreach ($consulta_documentos as $linea) {
                                        array_push($documentos, array(
                                            "id_documento" => (int) $linea['id_documento'],
                                            "modulo" => $linea['modulo'],
                                            "link" => "https://apicrm.mvevip.com/sparedes/".$linea['link']
                                        ));
                                    }
                                }
                            }

                            $respuesta['invitado'] = array(
                                "documento" => $consulta['documento'],
                                "invitado" => $consulta['invitado'],                                
                                "telefono" => $consulta['telefono'],
                                "numero_adultos" => (int) $consulta['numero_adultos'],
                                "numero_ninos" => (int) $consulta['numero_ninos'],
                                "observaciones" => $consulta['observaciones'],
                                "documentos" => $documentos,
                                "estado" => (int) $consulta['estado']
                            );
                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = "No se ha encontrado informacion del invitado.";
                        }                        
                    }else{
                        $respuesta['error'] = "Su token no existe";
                    }                                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->post("/documentos", function(Request $request, Response $response){                
                $authorization = $request->getHeader('Authorization');
                $files = $request->getUploadedFiles();
                $data = $request->getParsedBody();
                $respuesta['estado'] = false;

                // $respuesta['files'] = $files;
                // $respuesta['data'] = $data;
                
                try{                    
                    if (isset($authorization[0])){
                        $decodificado = base64_decode($authorization[0]);

                        $id_invitado = explode("_", $decodificado)[0];

                        $modulo = $data['modulo'];

                        $mysql = new Database("sparedes");

                        $consulta = $mysql->Consulta_Unico("SELECT * FROM invitados WHERE id_invitado=".$id_invitado);

                        if (isset($consulta['id_invitado'])){
                            
                            $archivos_recibidos = [];
                            if (isset($files)){
                                if (count($files) > 0){
                                    foreach ($files as $key => $value) {
                                        array_push($archivos_recibidos, array(
                                            "temporal" => $files[$key]->file,
                                            "nombre" => $data["nombre_".$key],
                                            "base64" => base64_encode($data["nombre_".$key])
                                        ));
                                    }
                                }
                            }

                            // guarda archivos y registros
                            if (count($archivos_recibidos) > 0){
                                $carpeta = __DIR__."/../../public/sparedes";

                                foreach ($archivos_recibidos as $linea) {                                
                                    if (!file_exists($carpeta)){
                                        // Si no existe la carpeta la crea
                                        mkdir($carpeta, 0777, true);
                                    }                       
                                    $nombre_archivo = $linea['base64'];                     
                                    $destino = $carpeta.'/'.$nombre_archivo;
                                    move_uploaded_file($linea['temporal'], $destino);

                                    // registro

                                    $id_docummento = $mysql->Ingreso("INSERT INTO documentos (id_invitado, modulo, link, estado) VALUES (?,?,?,?)", array($id_invitado, $modulo, $nombre_archivo, 0));
                                }
                            }

                            $respuesta['archivos'] = $archivos_recibidos;

                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = "No se ha encontrado informacion del invitado.";
                        }                        
                    }else{
                        $respuesta['error'] = "Su token no existe";
                    }                                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->delete("/documentos/{id_documento}", function(Request $request, Response $response){                
                $authorization = $request->getHeader('Authorization');                
                $id_documento = $request->getAttribute('id_documento');
                $respuesta['estado'] = false;                
                
                try{                    
                    if (isset($authorization[0])){
                        $decodificado = base64_decode($authorization[0]);

                        $id_invitado = explode("_", $decodificado)[0];

                        $modulo = $data['modulo'];

                        $mysql = new Database("sparedes");

                        $consulta = $mysql->Consulta_Unico("SELECT * FROM invitados WHERE id_invitado=".$id_invitado);

                        if (isset($consulta['id_invitado'])){

                            $documento = $mysql->Consulta_Unico("SELECT * FROM documentos WHERE id_documento=".$id_documento);

                            if (isset($documento['id_documento'])){
                                $carpeta = __DIR__."/../../public/sparedes";                                                                     
                                $destino = $carpeta.'/'.$documento['link'];                                

                                if (file_exists($destino)){
                                    unlink($destino);
                                }

                                $eliminar = $mysql->Ejecutar("DELETE FROM documentos WHERE id_documento=?", array($id_documento));

                                $respuesta['estado'] = true;
                            }else{
                                $respuesta['error'] = "No se ha encontrado el documento a eliminar.";
                            }
                            
                        }else{
                            $respuesta['error'] = "No se ha encontrado informacion del invitado.";
                        }                        
                    }else{
                        $respuesta['error'] = "Su token no existe";
                    }                                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->put("/invitado", function(Request $request, Response $response){                
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                $respuesta['estado'] = false;                
                
                // $respuesta['datas'] = $data;

                try{                    
                    if (isset($authorization[0])){
                        $decodificado = base64_decode($authorization[0]);

                        $id_invitado = explode("_", $decodificado)[0];                        

                        $mysql = new Database("sparedes");

                        $consulta = $mysql->Consulta_Unico("SELECT * FROM invitados WHERE id_invitado=".$id_invitado);

                        if (isset($consulta['id_invitado'])){                            
                            
                            $telefono = $data['telefono'];
                            $observaciones = $data['observaciones'];
                            $total_adultos = $data['total_adultos'];
                            $total_ninos = $data['total_ninos'];
                            $fecha_confirmacion = date("Y-m-d H:i:s");
                            $estado_confirmacion = 1;

                            if (($total_adultos<=$consulta['numero_adultos']) && ($total_ninos<=$consulta['numero_ninos'])){
                                $actualizacion = $mysql->Modificar("UPDATE invitados SET numero_adultos=?, numero_ninos=?, telefono=?, observaciones=?, fecha_confirmacion=?, estado=? WHERE id_invitado=?", array($total_adultos, $total_ninos, $telefono, $observaciones, $fecha_confirmacion, $estado_confirmacion, $id_invitado));                        

                                $respuesta['estado'] = true;
                            }else{
                                $respuesta['error'] = "La cantidad de adultos y/o niños deber ser igual o menor a la establecida.";
                            }
                            
                        }else{
                            $respuesta['error'] = "No se ha encontrado informacion del invitado.";
                        }                        
                    }else{
                        $respuesta['error'] = "Su token no existe";
                    }                                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });
    
        });

        // PASARELAS DE PAGOS
        $app->group('/pasarelas', function() use ($app) {

            $app->post("/login", function(Request $request, Response $response){
                $data = $request->getParsedBody();
                $respuesta['estado'] = false;
                
                try{                    
                    if ((isset($data['username'])) && (isset($data['password']))){
                        $mysql = new Database("vtgsa_ventas");

                        $username = $data['username'];
                        $password = $data['password'];

                        $consulta = $mysql->Consulta_Unico("SELECT id_usuario, estado, tipo FROM usuarios WHERE (correo='".$username."') AND (password='".$password."')");

                        if (isset($consulta['id_usuario'])){
                            $estado_usuario = $consulta['estado'];
                            $id_usuario = $consulta['id_usuario'];
                            $tipo = $consulta['tipo'];

                            if ($estado_usuario == 0){
                                if (($tipo == 1) || ($tipo==7)){
                                    $auth = new Authentication();

                                    $cadena = $id_usuario."|".date("Ymd");
                                    $hash = $auth->encrypt_decrypt("encrypt", $cadena);

                                    $actualiza = $mysql->Modificar("UPDATE usuarios SET hash=? WHERE id_usuario=?", array($hash, $id_usuario));

                                    $respuesta['hash'] = $hash;
                                    $respuesta['estado'] = true;
                                }else{
                                    $respuesta['error'] = "Su usuario no está habilitado para procesar pagos.";
                                }                                
                            }else{
                                $respuesta['error'] = "Sus credenciales se encuentran deshabilitadas.";
                            }
                        }else{
                            $respuesta['error'] = "No se encuentra el usuario con las credenciales ingresadas.";
                        }
                    }else{
                        $respuesta['error'] = "Debe ingresar el nombre de usuario o correo y contraseña asignada";
                    }
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/session", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');                
                $respuesta['estado'] = false;
                
                try{                    
                    $auth = new Authentication();

                    $usuario = $auth->Valida_Sesion($authorization[0]);
                    
                    if ($usuario['estado']){

                        $respuesta['usuario'] = $usuario['usuario'];
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

            $app->post("/pagoventas", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                $respuesta['estado'] = false;
                
                // $respuesta['data'] = $data;

                try{                    
                    $mysql = new Database("vtgsa_ventas");
                    $auth = new Authentication();                    
                    $funciones = new Functions();
                    $usuario = $auth->Valida_Sesion($authorization[0]);
                    
                    if ($usuario['estado']){
                        $id_usuario = $usuario['usuario']['id_usuario'];

                        $payment = array(
                            "tipo_documento" => $data['tipo_documento'],
                            "documento" => $data['documento'],
                            "apellidos" => strtoupper($data['apellidos']),
                            "nombres" => strtoupper($data['nombres']),
                            "email" => strtolower($data['correo']),
                            "telefono" => $data['telefono'],
                            "tipo" => $data['tipo_pago'],
                            "valor" => $data['valor']
                        );                        

                        // VALIDACION DE DATOS QUE ESTEN COMPLETOS

                        if (filter_var($data['correo'], FILTER_VALIDATE_EMAIL)) {
                            $valida_documento = array('estado' => false);
                            $documento = $data['documento'];
                            switch ($data['tipo_documento']) {
                                case 0: // CEDULA
                                    if (strlen($documento) == 10){
                                        $valida_ci = $funciones->Validar_Documento_Identidad($documento);
                                        if ($valida_ci['estado']){
                                            $valida_documento['codigo'] = $valida_ci['codigo'];
                                            $payment['tipo_documento'] = $valida_ci['codigo'];
                                            $valida_documento['estado'] = true;
                                        }else{
                                            $valida_documento['error'] = $valida_ci['tipo'];
                                            $valida_documento['estado'] = true;
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
                                            $payment['tipo_documento'] = $valida_ruc['codigo'];
                                            $valida_documento['estado'] = true;
                                        }else{
                                            $valida_documento['error'] = $valida_ruc['tipo'];
                                            $payment['tipo_documento'] = $valida_ruc['codigo'];
                                        }
                                    }else{
                                        $valida_documento['error'] = "El RUC debe contener 13 dígitos.";
                                    }
                                    break;
                                case 2: // CEDULA
                                    if (((strlen($documento) >= 5) && (strlen($documento) <= 15)) && ((ctype_alnum($documento)))){                                        
                                        $valida_documento['codigo'] = "PPN";
                                        $valida_documento['estado'] = true;
                                        $payment['tipo_documento'] = "PPN";
                                    }else{
                                        $valida_documento['error'] = "El pasaporte debe ser alfanumerico y contener entre 5 y 15 dígitos.";
                                    }
                                    break;
                            }
                            $validado = true; 
                            if (($valida_documento['estado']) || ($validado)){ // Prosigue si el documento esta correcto y validado

                                $union_nombres = $data['apellidos']." ".$data['nombres'];
                                $validacion_nombres = array("estado" => false);

                                if (($data['tipo_documento'] == "CI") || ($data['tipo_documento'] == "PPN")){
                                    if ((trim($data['apellidos'])!="") && (trim($data['nombres'])!="")){
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
                                    // Validacion de telefono celular
                                    if (strlen($data['telefono']) == 10){
                                        if (isset($data['tipo_venta'])){                                            
                                            $tipo_venta = $data['tipo_venta'];

                                            $procesar_pago = null;
                                            $meses = 0;
                                            $cuotas = 0;

                                            if ($tipo_venta == 0){ // pago unico
                                                $p2p = new PlacetoPay(PLACETOPAY, "mvevip_unico");
                                                $procesar_pago = $p2p->make_payment_visado($payment);
                                            }else if ($tipo_venta == 1){ // suscripcion
                                                $meses = $data['meses'];
                                                $cuotas = $data['cuotas'];

                                                $respuesta['asdf'] = array(
                                                    "meses" => (int) $meses,
                                                    "cuotas" => (float) $cuotas
                                                );
                                                $p2p = new PlacetoPay(PLACETOPAY, "mvevip_suscription");
                                                $procesar_pago = $p2p->make_suscription($payment);
                                            }
                                            
                                            $respuesta['pago'] = $procesar_pago;

                                            $referencia = $procesar_pago['reference'];
                                            $response_p2p = $procesar_pago['response'];
                                            $link = $procesar_pago['response']['processUrl'];
                                            $requestId = $response_p2p['requestId'];
                                            $status_request = $response_p2p['status'];

                                            $date = $status_request['date'];
                                            $message = $status_request['message'];
                                            $reason = $status_request['reason'];
                                            $status = $status_request['status'];

                                            $respuesta['link'] = $link;

                                            // guarda registro de intento de pago
                                            $identificacion = $payment['documento'];
                                            $nombre = $payment['nombres'];
                                            $apellido = $payment['apellidos'];
                                            $email = $payment['email'];
                                            $telefono = $payment['telefono'];
                                            $valor = $payment['valor'];
                                            $tipo = $payment['tipo'];
                                            $procesado = 0;

                                            $id_pago = $mysql->Ingreso("INSERT INTO pagos_p2p (identificacion, nombre, apellido, email, telefono, valor, tipo, reference, requestId, processUrl, status, reason, message, date, tipo_venta, meses, cuota, procesado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", array($identificacion, $nombre, $apellido, $email, $telefono, $valor, $tipo, $referencia, $requestId, $link, $status, $reason, $message, $date, $tipo_venta, $meses, $cuotas, $procesado));

                                            $respuesta['estado'] = true;
                                        }else{
                                            $respuesta['error'] = "Debe especificar el tipo de venta.";
                                        }  
                                    }else{
                                        $respuesta['error'] = "El teléfono celular debe contener 10 dígitos.";
                                    }
                                
                                }else{
                                    $respuesta['error'] = $validacion_nombres['error'];
                                }
                                
                            }else{
                                $respuesta['error'] = $valida_documento['error'];
                            }

                            
                        }else{
                            $respuesta['error'] = "El correo no tiene un formato incorrecto.";
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

            $app->get("/verificacion/{id_pago}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_pago = $request->getAttribute('id_pago');
                $respuesta['estado'] = false;                           

                try{                    
                    $mysql = new Database("vtgsa_ventas");
                    $auth = new Authentication();                    
                    $funciones = new Functions();
                    $usuario = $auth->Valida_Sesion($authorization[0]);
                    
                    if ($usuario['estado']){
                        $id_usuario = $usuario['usuario']['id_usuario'];


                        $consulta = $mysql->Consulta_Unico("SELECT * FROM pagos_p2p WHERE id_pago_p2p=".$id_pago);

                        if (isset($consulta['id_pago_p2p'])){
                            $tipo_venta = $consulta['tipo_venta'];
                            $requestId = $consulta['requestId'];

                            $documento = $consulta['identificacion'];
                            $nombres = $consulta['nombre'];
                            $apellidos = $consulta['apellido'];
                            $tipo_documento = "CI";
                            $celular = $consulta['telefono'];
                            $correo = $consulta['email'];

                            $tipo_pago = $consulta['tipo'];

                            $p2p = null;
                            if ($tipo_venta == 0){ // PAGO UNICO
                                $p2p = new PlacetoPay(PLACETOPAY, "mvevip_unico");
                            }else{ // PAGO SUSCRIPCION
                                $p2p = new PlacetoPay(PLACETOPAY, "mvevip_suscription");
                            }

                            if (!is_null($p2p)){
                                $verificacion = $p2p->check_status($requestId);

                                $estado_proceso = $verificacion['status']['status'];

                                if ($estado_proceso == "APPROVED"){                                    

                                    $respuesta['verificacion'] = $verificacion;
    
                                    if ($tipo_venta == 0){ // PAGO UNICO
                                        $payment = $verificacion['payment'];

                                        if (is_array($payment)){
                                            if (count($payment) > 0){
                                                foreach ($payment as $linea_payment) {
                                                    $estado_pago = $linea_payment['status']['status'];

                                                    if ($estado_pago == "APPROVED"){
                                                        $actualiza_estado = $mysql->Modificar("UPDATE pagos_p2p SET procesado=1 WHERE id_pago_p2p=?", array($id_pago));

                                                        $respuesta['estado']  = true;
                                                    }else{
                                                        $respuesta['error'] = "El pago no ha sido procesado correctamente. Estado: ".$estado_pago;
                                                        $actualiza_estado = $mysql->Modificar("UPDATE pagos_p2p SET procesado=2 WHERE id_pago_p2p=?", array($id_pago));
                                                    }
                                                }
                                            }
                                        }

                                    }else{ //  PAGO SUSCRIPCION
                                        $suscripcion = $verificacion['subscription'];
                                        $instrument = $suscripcion['instrument'];

                                        $meses = $consulta['meses'];
                                        $cuota = $consulta['cuota'];

                                        if (is_array($instrument)){
                                            if (count($instrument) > 0){
                                                $encontrado = false;
                                                $token_encontrado = "";
                                                $subtoken = "";
                                                foreach ($instrument  as $linea_instrument) {
                                                    $keyword = $linea_instrument['keyword'];
                                                
                                                    if ($keyword == "token"){
                                                        $encontrado = true;
                                                        $token_encontrado = $linea_instrument['value'];
                                                    }

                                                    if ($keyword == "subtoken"){                                                     
                                                        $subtoken = $linea_instrument['value'];
                                                    }
                                                }

                                                if ($encontrado){
                                                    $actualiza_estado = $mysql->Modificar("UPDATE pagos_p2p SET procesado=3 WHERE id_pago_p2p=?", array($id_pago));

                                                    // Guarda token
                                                    $verifica_token = $mysql->Consulta_Unico("SELECT * FROM pagos_p2p_tokens WHERE token='".$token_encontrado."'");

                                                    if (!isset($verifica_token['id_suscripcion'])){
                                                        $id_suscripcion = $mysql->Ingreso("INSERT INTO pagos_p2p_tokens (id_pago_p2p, tipo_documento, documento, apellidos, nombres, celular, correo, requestId, token, subtoken, estado) VALUES (?,?,?,?,?,?,?,?,?,?,?)", array($id_pago, $tipo_documento, $documento, $apellidos, $nombres, $celular, $correo, $requestId, $token_encontrado, $subtoken, 0));

                                                        // crea la estructura de cuotas

                                                        $fecha_actual = strtotime(date("Y-m-d"));
                                                        // crea cuotas para cobros
                                                        for ($x=1; $x<=$meses; $x+=1){
                                                            // $fecha_actual = strtotime('+10 minute', $fecha_actual);
                                                            
                                                            $fecha_pago = date("Y-m-d", $fecha_actual);                                                    

                                                            $ingreso = $mysql->Ingreso("INSERT INTO pagos_p2p_cuotas (id_pago, tipo_pago, fecha, valor, estado) VALUES (?,?,?,?,?)", array($id_suscripcion, $tipo_pago, $fecha_pago, $cuota, 0));

                                                            $fecha_actual = strtotime('+1 month', $fecha_actual);
                                                        }
                                                    }
                                                }else{
                                                    $respuesta['error'] = "No se ha tokenizado la tarjeta.";
                                                    $actualiza_estado = $mysql->Modificar("UPDATE pagos_p2p SET procesado=2 WHERE id_pago_p2p=?", array($id_pago));
                                                }

                                                $respuesta['estado'] = true;
                                            }
                                        }
                                    }
                                }else{
                                    $respuesta['error'] = "La petición se encuentra en estado ".$estado_proceso;
                                    $actualiza_estado = $mysql->Modificar("UPDATE pagos_p2p SET procesado=2 WHERE id_pago_p2p=?", array($id_pago));
                                }
                                
                            }else{
                                $respuesta['error'] = "No se logro realizar la conexion con PlacetoPay";
                            }
                        }else{
                            $respuesta['error'] = "No se encuentra informacion del pago.";
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

            // Verifica si se obtuvo el token de la tarjeta, crea plan de cuotas y genera primer pago
            $app->get("/pagoventas/{tipo}/{requestId}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');            
                $requestId = $request->getAttribute('requestId');
                $tipo = $request->getAttribute('tipo');

                $respuesta['estado'] = false;                    

                try{                    
                    $mysql = new Database("vtgsa_ventas");
                    $auth = new Authentication();                    
                    $funciones = new Functions();
                    $usuario = $auth->Valida_Sesion($authorization[0]);
                    
                    if ($usuario['estado']){
                        $id_usuario = $usuario['usuario']['id_usuario'];

                        $modulo = "";
                        if ($tipo == "pagounico"){
                            $modulo = "mvevip_unico";
                        }else if ($tipo == "suscripcion"){
                            $modulo = "mvevip_suscription";
                        }

                        if (empty($modulo)){
                            $p2p = new PlacetoPay(PLACETOPAY, $modulo);

                            $suscripcion = $p2p->check_status($requestId);
                            $respuesta['suscripcion'] = $suscripcion;

                            $consulta = $mysql->Consulta("SELECT * FROM pagos_p2p FROM requestId=");

                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = "No se especifica el tipo de pago";
                        }                

                    }else{
                        $respuesta['error'] = $usuario['error'];
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;


            
                // try{





                    
                //     $p2p = new PlacetoPay("live", "mvevip_suscription");
                //     $mysql = new Database("vtgsa_ventas");

                //     
                //     

                //     $requestId = $suscripcion['requestId'];
                //     $status = $suscripcion['status']['status'];

                //     if ($status == "APPROVED"){
                //         $request = $suscripcion['request'];

                //         $buyer = $request['buyer'];
                //         $tipo_documento = $buyer['documentType'];
                //         $documento = $buyer['document'];
                //         $apellidos = $buyer['name'];
                //         $nombres = $buyer['surname'];
                //         $celular = $buyer['mobile'];
                //         $correo = $buyer['email'];                        

                //         $subscription = $suscripcion['subscription'];
                //         $instrument = $subscription['instrument'];

                //         if (is_array($instrument)){
                //             if (count($instrument) > 0){
                //                 $token = "";
                //                 $subtoken = "";

                //                 foreach ($instrument as $linea) {
                //                     $keyword = $linea['keyword'];                                    

                //                     if ($keyword == "token"){
                //                         $token = $linea['value'];
                //                     }

                //                     if ($keyword == "subtoken"){
                //                         $subtoken = $linea['value'];
                //                     }                                                              
                //                 }                                

                //                 if (!empty($token)){
                //                     $verifica = $mysql->Consulta_Unico("SELECT * FROM pagos_p2p_tokens WHERE token='".$token."'");

                //                     if (!isset($verifica['id_suscripcion'])){                                        
                //                         $busca_peticion = $mysql->Consulta_Unico("SELECT * FROM pagos_p2p WHERE requestId='".$requestId."'");

                //                         if (isset($busca_peticion['id_pago_p2p'])){

                //                             $id_suscripcion = $mysql->Ingreso("INSERT INTO pagos_p2p_tokens (tipo_documento, documento, apellidos, nombres, celular, correo, requestId, token, subtoken, estado) VALUES (?,?,?,?,?,?,?,?,?,?)", array($tipo_documento, $documento, $apellidos, $nombres, $celular, $correo, $requestId, $token, $subtoken, 0));

                //                             // Crea lista de cuotas
                //                             $meses = $busca_peticion['meses'];
                //                             $cuota = $busca_peticion['cuota'];
                //                             $tipo_venta = $busca_peticion['tipo_venta'];
                //                             $tipo = $busca_peticion['tipo'];

                //                             if ($tipo_venta == 1){

                //                                 $fecha_actual = strtotime(date("Y-m-d"));
                //                                 // crea cuotas para cobros
                //                                 for ($x=1; $x<=$meses; $x+=1){
                //                                     // $fecha_actual = strtotime('+10 minute', $fecha_actual);
                                                    
                //                                     $fecha_pago = date("Y-m-d", $fecha_actual);                                                    

                //                                     $ingreso = $mysql->Ingreso("INSERT INTO pagos_p2p_cuotas (id_pago, tipo_pago, fecha, valor, estado) VALUES (?,?,?,?,?)", array($id_suscripcion, $tipo, $fecha_pago, $cuota, 0));

                //                                     $fecha_actual = strtotime('+1 month', $fecha_actual);
                //                                 }

                //                                 $respuesta['estado'] = true;
                //                             }else{
                //                                 $respuesta['error'] = "El tipo de venta no es de suscripcion";
                //                             }
                //                         }else{
                //                             $respuesta['error'] = "No se encuentra la informacion de meses y cuotas del cliente";
                //                         }

                                        
                //                     }else{
                //                         $respuesta['error'] = "Ya se ha guardado la informacion de la tarjeta.";
                //                     }
                                    
                //                 }else{
                //                     $respuesta['error'] = "No se obtuvo ningun token de tarjeta";
                //                 }
                //             }
                //         }
                        
                //     }else{
                //         $respuesta['error'] = $status;
                //     }

                    
                    
                // }catch(PDOException $e){
                //     $respuesta['error'] = $e->getMessage();
                // }

                // $newResponse = $response->withJson($respuesta);
            
                // return $newResponse;
            });

            $app->get("/pagos", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
                
                try{                    
                    $auth = new Authentication();

                    $usuario = $auth->Valida_Sesion($authorization[0]);
                    
                    if ($usuario['estado']){                                                
                        $id_usuario = $usuario['usuario']['id_usuario'];

                        $mysql = new Database("vtgsa_ventas");
                    
                        // $por_estado = "";
                        // if (isset($params['por_estado'])){
                        //     $por_estado = $params['por_estado'];
                        //     switch ($por_estado) {
                        //         case '1':
                        //             $por_estado = "AND (status_pago='APPROVED')";
                        //             break;
                        //         case '2':
                        //             $por_estado = "AND (status_pago='PENDING')";
                        //             break;
                        //         case '3':
                        //             $por_estado = "AND (status_pago='REJECTED')";
                        //             break;
                                
                        //         default:
                        //             $por_estado = "AND (status_pago='APPROVED')";
                        //             break;
                        //     }
                        // }                      

                        // $buscador = "";
                        // if (isset($params['buscador'])){
                        //     $buscador = $params['buscador'];
                        // }
                        $fecha_ahora = date("Y-m-d");

                        $consulta = $mysql->Consulta("SELECT P.id_pago_p2p, P.identificacion, P.nombre, P.apellido, P.email, P.telefono, P.valor, P.tipo, P.reference, P.requestId, P.processUrl, P.date, P.tipo_venta, P.meses, P.cuota, P.procesado
                        FROM pagos_p2p P
                        LEFT JOIN pagos_p2p_confirmacion C
                        ON P.requestId=C.requestId
                        WHERE
                        (DATE(P.date) BETWEEN '".$fecha_ahora."' and '".$fecha_ahora."')
                        ORDER BY P.id_pago_p2p DESC");

                        $resultados = [];

                        if (is_array($consulta)){
                            if (count($consulta) > 0){
                                foreach ($consulta as $linea) {
                                    $descripcion_tipo_pago = "";                                   

                                    switch ($linea['tipo_venta']) {
                                        case 0:
                                            $descripcion_tipo_pago = "Punto Pago";
                                            break;
                                        case 1:
                                            $descripcion_tipo_pago = "Venta sin Cupo";
                                            break;
                                        case 2:
                                            $descripcion_tipo_pago = "Cuota";
                                            break;
                                    }

                                    $descripcion_procesado = "";
                                    $color_procesado = "light";

                                    switch ($linea['procesado']) {
                                        case 0:
                                            $descripcion_procesado = "Pendiente";
                                            $color_procesado = "warning";
                                            break;
                                        case 1:
                                            $descripcion_procesado = "Aprobado";
                                            $color_procesado = "success";
                                            break;
                                        case 2:
                                            $descripcion_procesado = "Rechazado";
                                            $color_procesado = "danger";
                                            break;
                                        case 3:
                                            $descripcion_procesado = "Tokenizado";
                                            $color_procesado = "info";
                                            break;
                                    }

                                    if ($linea['procesado'] == 0){

                                    }
                                    array_push($resultados, array(
                                        "id_pago" => (int) $linea['id_pago_p2p'],
                                        "requestId" => $linea['requestId'],
                                        "documento" => $linea['identificacion'],
                                        "nombres" => strtoupper($linea['apellido'])." ".strtoupper($linea['nombre']),
                                        "email" => $linea['email'],
                                        "mobile" => $linea['telefono'],
                                        "reference" => $linea['reference'],                                        
                                        "total" => (float) $linea['valor'],                                        
                                        "estado" => array(
                                            "descripcion" => $descripcion_procesado,
                                            "color" => $color_procesado,
                                            "valor" => (int) $linea['procesado']
                                        ),
                                        "tipo_venta" => array(
                                            "descripcion" => $descripcion_tipo_pago,
                                            "valor" => (int) $linea['tipo_venta']
                                        ),
                                        "fecha" => date("Y-m-d H:i:s", strtotime($linea['date']))
                                    ));
                                }
                            }
                        }

                        $respuesta['consulta'] = $resultados;
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

            $app->get("/cuotas/{id_pago}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_pago = $request->getAttribute('id_pago');
                $respuesta['estado'] = false;
                
                try{                    
                    $auth = new Authentication();

                    $usuario = $auth->Valida_Sesion($authorization[0]);
                    
                    if ($usuario['estado']){                                                
                        $id_usuario = $usuario['usuario']['id_usuario'];

                        $mysql = new Database("vtgsa_ventas");
                    
                        $consulta = $mysql->Consulta("SELECT T.id_suscripcion, C.id_cuota, C.tipo_pago, C.fecha, C.valor, C.estado FROM pagos_p2p_tokens T, pagos_p2p_cuotas C WHERE (T.id_pago_p2p=".$id_pago.") AND (T.id_suscripcion=C.id_pago) ORDER BY C.id_cuota ASC");
                        $resultados = [];

                        if (is_array($consulta)){
                            if (count($consulta) > 0){                        
                                foreach ($consulta as $linea) {
                                    $descripcion_estado = "";
                                    $color_estado  = "";                                

                                    switch ($linea['estado']) {
                                        case 0: // Cuando se tokeniza las cuotas quedan en pendiente para enviar el primer pago
                                            $descripcion_estado = "Enviar Pago";
                                            $color_estado = "info";
                                            break;
                                        case 1: // Pendientes para hacer cobros automaticamente una vez enviado el primer pago manualmente
                                            $descripcion_estado = "Pendiente";
                                            $color_estado = "warning";
                                            break;
                                        case 2: // Pago concretado
                                            $descripcion_estado = "Pagado";
                                            $color_estado = "success";
                                            break;
                                        case 3: // Pago no concretado
                                            $descripcion_estado = "Rechazado";
                                            $color_estado = "danger";
                                            break;
                                        case 5: // Pago Cancelado manualmente o por falta de cupos y tratando varias veces
                                            $descripcion_estado = "Cancelada";
                                            $color_estado = "danger";
                                            break;                                        
                                    }

                                    array_push($resultados, array(
                                        "id_suscripcion" => (int) $linea['id_suscripcion'],
                                        "id_cuota" => (int) $linea['id_cuota'],
                                        "tipo_pago" => (int) $linea['tipo_pago'],
                                        "fecha" => $linea['fecha'],
                                        "valor" => (float) $linea['valor'],
                                        "estado" => array(
                                            "valor" => (int) $linea['estado'],
                                            "descripcion" => $descripcion_estado,
                                            "color" => $color_estado
                                        )
                                    ));

                                    $respuesta['estado'] = true;
                                }
                            }
                        }

                        $respuesta['consulta'] = $resultados;
                                            
                    }else{
                        $respuesta['error'] = $usuario['error'];
                    }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/cuotas_cobro/{id_cuota}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_cuota = $request->getAttribute('id_cuota');
                $respuesta['estado'] = false;
                
                try{                    
                    $auth = new Authentication();

                    $usuario = $auth->Valida_Sesion($authorization[0]);
                    
                    if ($usuario['estado']){                                                
                        $id_usuario = $usuario['usuario']['id_usuario'];

                        $mysql = new Database("vtgsa_ventas");
                    
                        $consulta = $mysql->Consulta_Unico("SELECT C.id_cuota, T.id_suscripcion, T.tipo_documento, T.documento, T.apellidos, T.nombres, T.celular, T.correo, T.token, C.fecha, C.valor, C.tipo_pago, C.estado FROM pagos_p2p_cuotas C, pagos_p2p_tokens T
                        WHERE (C.id_cuota=".$id_cuota.") AND (C.id_pago=T.id_suscripcion)");
                        
                        if (isset($consulta['id_cuota'])){

                            $payment = array(
                                "documento" => $consulta['documento'],
                                "tipo_documento" => $consulta['tipo_documento'],
                                "nombres" => $consulta['nombres'],
                                "apellidos" => $consulta['apellidos'],
                                "correo" => $consulta['correo'],
                                "celular" => $consulta['celular'],                                               
                                "valor" => $consulta['valor'],
                                "tipo" => $consulta['tipo_pago']
                            );

                            $respuesta['payment'] = $payment;

                            $p2p = new PlacetoPay(PLACETOPAY, "mvevip_suscription");                                                        

                            $pago_suscripcion = $p2p->make_payment_suscription($consulta['token'], $payment);
                            // $respuesta['asdf '] = $pago_suscripcion;                            

                            if (isset($pago_suscripcion['response']['requestId'])){
                                $requestId = $pago_suscripcion['response']['requestId'];
                                $reference = $pago_suscripcion['reference'];
                                $processUrl = "";

                                $identificacion = $consulta['documento'];
                                $nombre = $consulta['nombres'];
                                $apellido = $consulta['apellidos'];
                                $email = $consulta['correo'];
                                $telefono = $consulta['celular'];

                                $valor = $consulta['valor'];
                                $tipo = $consulta['tipo_pago'];

                                $status_proceso = $pago_suscripcion['response']['status'];
                                $status = $status_proceso['status'];
                                $reason = $status_proceso['reason'];
                                $message = $status_proceso['status'];
                                $date = $status_proceso['date'];

                                $procesado = 0;

                                $payment = [];
                                if (isset($pago_suscripcion['response']['payment'][0])){
                                    $payment = $pago_suscripcion['response']['payment'][0];

                                    $status_pago = $payment['status']['status'];
                                    if ($status_pago == "APPROVED"){
                                        $procesado = 1;

                                        $modificar = $mysql->Modificar("UPDATE pagos_p2p_cuotas SET estado=? WHERE id_cuota=?", array(2, $id_cuota));
                                    }else if ($procesado == "REJECTED"){
                                        $procesado = 2;

                                        $modificar = $mysql->Modificar("UPDATE pagos_p2p_cuotas SET estado=? WHERE id_cuota=?", array(3, $id_cuota));
                                    }else{
                                        $modificar = $mysql->Modificar("UPDATE pagos_p2p_cuotas SET estado=? WHERE id_cuota=?", array(1, $id_cuota));
                                    }
                                }

                                $tipo_venta = 2;
                                $meses = 0;
                                $cuota = 0;
                            
                                // ingresa en pago_p2p 
                                $id_pago_p2p = $mysql->Ingreso("INSERT INTO pagos_p2p (identificacion, nombre, apellido, email, telefono, valor, tipo, reference, requestId, processUrl, status, reason, message, date, tipo_venta, meses, cuota, procesado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", array($identificacion, $nombre, $apellido, $email, $telefono, $valor, $tipo, $reference, $requestId, $processUrl, $status, $reason, $message, $date, $tipo_venta, $meses, $cuota, $procesado));

                                $respuesta['estado'] = true;
                            }
                        }else{
                            $respuesta['error'] = "No se pudo obtener información de la cuota seleccionada.";
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

            $app->get("/confirmados", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta['estado'] = false;
                
                try{                    
                    $auth = new Authentication();

                    $usuario = $auth->Valida_Sesion($authorization[0]);
                    
                    if ($usuario['estado']){                                                
                        $id_usuario = $usuario['usuario']['id_usuario'];

                        $mysql = new Database("vtgsa_ventas");
                    
                        $por_estado = "";
                        if (isset($params['por_estado'])){
                            $por_estado = $params['por_estado'];
                            switch ($por_estado) {
                                case '1':
                                    $por_estado = "AND (status_pago='APPROVED')";
                                    break;
                                case '2':
                                    $por_estado = "AND (status_pago='PENDING')";
                                    break;
                                case '3':
                                    $por_estado = "AND (status_pago='REJECTED')";
                                    break;
                                
                                default:
                                    $por_estado = "AND (status_pago='APPROVED')";
                                    break;
                            }
                        }                      

                        $buscador = "";
                        if (isset($params['buscador'])){
                            $buscador = $params['buscador'];
                        }

                        $consulta = $mysql->Consulta("SELECT * FROM pagos_p2p_confirmacion WHERE ((name LIKE '%".$buscador."%') OR (surname LIKE '%".$buscador."%') OR (document LIKE '%".$buscador."%') OR (reference LIKE '%".$buscador."%') OR (email LIKE '%".$buscador."%') OR (mobile LIKE '%".$buscador."%') OR (description LIKE '%".$buscador."%')) ".$por_estado." ORDER BY id_pago DESC LIMIT 10");

                        $resultados = [];

                        if (is_array($consulta)){
                            if (count($consulta) > 0){
                                foreach ($consulta as $linea) {
                                    array_push($resultados, array(
                                        "id_pago" => (int) $linea['id_pago'],
                                        "requestId" => $linea['requestId'],
                                        "documento" => $linea['document'],
                                        "nombres" => strtoupper($linea['name'])." ".strtoupper($linea['surname']),
                                        "email" => $linea['email'],
                                        "mobile" => $linea['mobile'],
                                        "reference" => $linea['reference'],
                                        "description" => $linea['description'],
                                        "total" => (float) $linea['total'],
                                        "estado" => $linea['status_pago'],
                                        "paymentMethod" =>  $linea['paymentMethod'],
                                        "date_pago" => $linea['date_pago']
                                    ));
                                }
                            }
                        }

                        $respuesta['consulta'] = $resultados;
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
        });

        // ENDPOINT PARA CONTRATOS

        $app->group('/contratos', function() use ($app) {

            $app->post("/nuevo_contrato", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                $files = $request->getUploadedFiles();
                $respuesta['estado'] = false;
                
                // $respuesta['data'] = $data;
                // $respuesta['files'] = $files;

                try{                    
                    $mysql = new Database('vtgsa_ventas');
                    $functions = new Functions();
                    // $s3 = new AWSS3();
                    $crm = new CRM_API();

                    if ((isset($data['numero_documento'])) && (!empty($data['numero_documento']))){
                        $numero_documento = $data['numero_documento'];

                        if ((isset($data['id_cliente'])) && (!empty($data['id_cliente']))){
                            $id_cliente = $data['id_cliente']; 

                            // Obtiene datos del cliente
                            $datos_cliente = $crm->getIdCliente($id_cliente);

                            if ((isset($datos_cliente['cli_codigo'])) && (!empty($datos_cliente['cli_codigo']))){
                                $documento = $datos_cliente['cli_cedula'];
                                $nombres_apellidos = $datos_cliente['cli_apellido']." ".$datos_cliente['cli_nombre'];
                                $ciudad = $datos_cliente['nombre'];
                                $correo = $datos_cliente['cli_email'];
                                $celular = $datos_cliente['cli_celular'];

                                $links = array("voucher" => "", "dummy" => "");

                                $continuar = false;
                                $lote ="";
                                $referencia = "";
                                $meses_diferido = "";
                                $tipo_diferido = "";
                                $primeros_digitos = "";
                                $ultimos_digitos = "";

                                if ($data['formas_pago'] == 2){
                                    if ( ((isset($data['lote'])) && (!empty($data['lote']))) && ((isset($data['referencia'])) && (!empty($data['referencia']))) && ((isset($data['meses_diferido'])) && (!empty($data['meses_diferido']))) && ((isset($data['tipo_diferido'])) && (!empty($data['tipo_diferido']))) && ((isset($data['primeros_digitos'])) && (!empty($data['primeros_digitos']))) && ((isset($data['ultimos_digitos'])) && (!empty($data['ultimos_digitos']))) ){

                                        $lote = $data['lote'];
                                        $referencia = $data['referencia'];
                                        $meses_diferido = $data['meses_diferido'];
                                        $tipo_diferido = $data['tipo_diferido'];
                                        $primeros_digitos = $data['primeros_digitos'];
                                        $ultimos_digitos = $data['ultimos_digitos'];

                                        $continuar = true;
                                    }else{
                                        $respuesta['error'] = "Debe ingresar los datos del pago con tarjeta.";
                                    }
                                }else{
                                    $continuar = true;
                                }

                                if ($continuar){
                                    if (isset($files)){
                                        if (is_array($files)){
                                            if (count($files) == 2){
                                                foreach ($files as $key => $value) {
                                                    /// VOUCHER
                                                    $tipo_de_archivo = 'voucher_0';
                                                    if (isset($data['nombre_'.$tipo_de_archivo])){
                                                        $nombre_voucher = $data["nombre_".$tipo_de_archivo];
                                                        $archivo_temporal = $files[$tipo_de_archivo]->file;
                                                        $tmp = $functions->Archivo_Temporal($nombre_voucher, $archivo_temporal, $tipo_de_archivo);                                    
                                                        
                                                        if ($tmp['estado']){                                    
                                                            $nombre_archivo = BUCKET_FOLDER_VOUCHERS.$id_cliente."_".$tmp['archivo']['nombre'];
                                                            $links['voucher'] = $nombre_archivo;
                                                            // $envios3 = $s3->saveFileS3($tmp['archivo']['tmp'], $nombre_archivo, BUCKET_MVEVIP);
                                                            // if (file_exists($tmp['archivo']['tmp'])){
                                                            //     unlink($tmp['archivo']['tmp']);
                                                            // }
                                                        }
                                                    }

                                                    /// VOUCHER
                                                    $tipo_de_archivo = 'dummy_0';
                                                    if (isset($data['nombre_'.$tipo_de_archivo])){
                                                        $nombre_voucher = $data["nombre_".$tipo_de_archivo];
                                                        $archivo_temporal = $files[$tipo_de_archivo]->file;
                                                        $tmp = $functions->Archivo_Temporal($nombre_voucher, $archivo_temporal, $tipo_de_archivo);                                    
                                                        
                                                        if ($tmp['estado']){                                    
                                                            $nombre_archivo = BUCKET_FOLDER_VOUCHERS.$id_cliente."_".$tmp['archivo']['nombre'];
                                                            $links['dummy'] = $nombre_archivo;
                                                            // $envios3 = $s3->saveFileS3($tmp['archivo']['tmp'], $nombre_archivo, BUCKET_MVEVIP);
                                                            // if (file_exists($tmp['archivo']['tmp'])){
                                                            //     unlink($tmp['archivo']['tmp']);
                                                            // }
                                                        }
                                                    }
                                                } 
                                                
                                                $total_pago = $data['valor'];
                                                $pasajeros = $data['pasajeros'];
                                                $dias = $data['dias'];
                                                $tipo_seguro = $data['tipo_seguro'];

                                                $formas_pago = $data['formas_pago'];
                                                $tipos_forma_pagos = $data['tipos_forma_pagos'];

                                                $observaciones = "";
                                                if (isset($data['observaciones'])){
                                                    $observaciones = $data['observaciones'];
                                                }

                                                $fecha_venta = date("Y-m-d");
                                                $fecha_alta = date("Y-m-d H:i:s");
                                                $fecha_modificacion = $fecha_alta;

                                                $id_vendedor = 21;
                                                $id_lider = 12;
                                                $dummy = $links['dummy'];

                                                $aprobacion = $lote;
                                                $valor_voucher = $data['valor'];
                                                $comision = $valor_voucher;
                                                $fecha_caducidad = date("Y-m-d");

                                                $estado = 0;
                                                $id_institucion = 2;

                                                /// FORMA JSON PARA ENVIO A CRM FAUSTO
                                                $envio_crm = array(
                                                    "id_cliente" => $id_cliente,
                                                    "id_usuario" => 279, // USUARIO VENDEDOR EN CRM FAUSTO
                                                    "total" => array(
                                                        "dias" => $dias,
                                                        "pasajeros" => $pasajeros,
                                                        "combo" => $tipo_seguro,
                                                        "valor" => $valor_voucher,
                                                        "saldo" => 0
                                                    ),
                                                    "pago" => array(
                                                        "meses" => $meses_diferido,
                                                        "numero_cuenta" => $tipo_diferido,
                                                        "concepto" => $numero_documento,
                                                        "forma_pago" => $formas_pago,
                                                        "banco" => $tipos_forma_pagos,
                                                        "cheque" => "",
                                                        "lote" => $lote,
                                                        "referencia" => $referencia 
                                                    )
                                                );

                                                // $respuesta['envio'] = $envio_crm;

                                                // $respuesta['crm'] = $crm->NuevoSeguro($envio_crm);

                                                $id_registro = $mysql->Ingreso("INSERT INTO registros (fecha_venta, id_vendedor, id_lider, dummy, id_cliente, documento, nombres_apellidos, ciudad, referencia, aprobacion, valor_voucher, pago_parcial, copia_voucher, comision, correo, convencional, celular, institucion, primeros_digitos, ultimos_digitos, tipo_venta, fecha_caducidad, tipo, que_destino, id_destino, observaciones, observaciones_usuario, fecha_alta, fecha_modificacion, estado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", array($fecha_venta, $id_vendedor, $id_lider, $dummy, $id_cliente, $documento, $nombres_apellidos, $ciudad, $referencia, $aprobacion, $valor_voucher, 0, "", 0, $correo, "", $celular, 0, "", "", 3, $fecha_caducidad, 0, "", 0, $observaciones, "", $fecha_alta, $fecha_modificacion, $estado));

                                                $id_pago = $mysql->Ingreso("INSERT INTO registros_pagos (id_registro, referencia, aprobacion, lote, comision, valor_voucher, id_institucion, primeros_digitos, ultimos_digitos, imagen, fecha_pago, estado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)", array($id_registro, $referencia, $numero_documento, $lote, $comision, $valor_voucher, $id_institucion, $primeros_digitos, $ultimos_digitos, $links['voucher'], $fecha_venta, $estado));
                                                
                                            


                                                // PROCESA PARA EL CONTRATO

                                                $registro = $mysql->Consulta_Unico("SELECT * FROM registros WHERE id_registro=".$id_registro);
                                                // $respuesta['asdf'] = $registro;
                                                if (isset($registro['id_registro'])){

                                                    $registro_pago = $mysql->Consulta("SELECT * FROM registros_pagos P, instituciones I WHERE (P.id_registro=".$id_registro.") AND (P.id_institucion=I.id_institucion)");
                                                    $total = 0;

                                                    $institucion_financiera = '';
                                                    $tipo_pago = 0;
                                                    $primeros_digitos = "";
                                                    $ultimos_digitos = "";
                                                    if (is_array($registro_pago)){
                                                        if (count($registro_pago) > 0){
                                                            foreach ($registro_pago as $pago) {
                                                                $total += floatval($pago['valor_voucher']);
                                                                $institucion_financiera = $pago['institucion'];
                                                                $tipo_pago = $pago['tipo'];
                                                                $primeros_digitos = $pago['primeros_digitos'];
                                                                $ultimos_digitos = $pago['ultimos_digitos'];
                                                            }
                                                        }
                                                    }

                                                    $pdf = new PDF();

                                                    $tipo_pago = 1; // 0: tarejta, 1: efectivo/deposito/transfer
                                                    $datos = array(
                                                        "documento" => $registro['documento'],
                                                        "nombres" => $registro['nombres_apellidos'],
                                                        "personas" => $pasajeros,
                                                        "dias" => $dias,
                                                        "fecha_caducidad" => "2023-05-17",//$registro['fecha_caducidad'],
                                                        "valor" => (float) $total,
                                                        "forma_pago" => $tipo_pago,
                                                        "primeros_digitos" => $primeros_digitos,
                                                        "ultimos_digitos" => $ultimos_digitos,
                                                        "institucion" => "MASTERCARD", //$institucion_financiera,
                                                        "fecha_modificacion" => $registro['fecha_modificacion']
                                                    );
                            
                                                    $genera_contrato = $pdf->Contrato($datos, 'seguros');
                                                    if (isset($genera_contrato['base64'])){
                                                        $respuesta['contrato'] = $genera_contrato['base64'];
                                                    }
                                                }else{
                                                    $respuesta['error'] = "No se encontró información con el ID.";
                                                }
                                                // $respuesta['links'] = $links;
                                                $respuesta['estado'] = true;
                                            }else{
                                                $respuesta['error'] = "Debe subir dos archivos (voucher/dummy)";
                                            }
                                        }
                                    }
                                }
                            }else{
                                $respuesta['error'] = "El ID del cliente no se encuentra o es incorrecto.";
                            }
                        
                            
                        }else{
                            $respuesta['error'] = "Especifique un ID de cliente.";
                        }
                        
                    }else{
                        $respuesta['error'] = "Debe especificar el número de aprobación o depósito/transferencia.";
                    }
                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/pdf/{id_registro}", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $id_registro = $request->getAttribute('id_registro');
                $respuesta['estado'] = false;
                
                try{                    
                    // $auth = new Authentication();

                    // $usuario = $auth->Valida_Sesion($authorization[0]);
                    
                    // if ($usuario['estado']){                                                
                        // $id_usuario = $usuario['usuario']['id_usuario'];
                        $mysql = new Database("vtgsa_ventas");

                        $registro = $mysql->Consulta_Unico("SELECT * FROM registros WHERE id_registro=".$id_registro);
                        // $respuesta['asdf'] = $registro;
                        if (isset($registro['id_registro'])){

                            $registro_pago = $mysql->Consulta("SELECT * FROM registros_pagos P, instituciones I WHERE (P.id_registro=".$id_registro.") AND (P.id_institucion=I.id_institucion)");
                            $total = 0;

                            $institucion_financiera = '';
                            $tipo_pago = 0;
                            $primeros_digitos = "";
                            $ultimos_digitos = "";
                            if (is_array($registro_pago)){
                                if (count($registro_pago) > 0){
                                    foreach ($registro_pago as $pago) {
                                        $total += floatval($pago['valor_voucher']);
                                        $institucion_financiera = $pago['institucion'];
                                        $tipo_pago = 1;//$pago['tipo'];
                                        $primeros_digitos = $pago['primeros_digitos'];
                                        $ultimos_digitos = $pago['ultimos_digitos'];
                                    }
                                }
                            }

                            $pdf = new PDF();

                            $datos = array(
                                "documento" => $registro['documento'],
                                "nombres" => $registro['nombres_apellidos'],
                                "personas" => 4,
                                "dias" => 11,
                                "fecha_caducidad" => $registro['fecha_caducidad'],
                                "valor" => (float) $total,
                                "forma_pago" => $tipo_pago,
                                "primeros_digitos" => $primeros_digitos,
                                "ultimos_digitos" => $ultimos_digitos,
                                "institucion" => $institucion_financiera,
                                "fecha_modificacion" => $registro['fecha_modificacion']
                            );
    
                            $respuesta['pdf'] = $pdf->Contrato($datos, 'seguros')['link'];
                            $respuesta['estado'] = true;
                        }else{
                            $respuesta['error'] = "No se encontró información con el ID.";
                        }

                        
                    // }else{
                    //     $respuesta['error'] = $usuario['error'];
                    // }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/descargo", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $respuesta['estado'] = false;
                
                try{                    
                    // $auth = new Authentication();

                    // $usuario = $auth->Valida_Sesion($authorization[0]);
                    
                    // if ($usuario['estado']){                                                
                        // $id_usuario = $usuario['usuario']['id_usuario'];
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

                        $respuesta['pdf'] = $pdf->Contrato($datos, "descargo");
                        $respuesta['estado'] = true;

                        
                    // }else{
                    //     $respuesta['error'] = $usuario['error'];
                    // }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->get("/reservas", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $respuesta['estado'] = false;
                
                try{                    
                    // $auth = new Authentication();

                    // $usuario = $auth->Valida_Sesion($authorization[0]);
                    
                    // if ($usuario['estado']){                                                
                        // $id_usuario = $usuario['usuario']['id_usuario'];
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

                        
                    // }else{
                    //     $respuesta['error'] = $usuario['error'];
                    // }
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

        });

        // ENDPOINT PARA CONTRATOS

        $app->group('/whatsapp', function() use ($app) {


            $app->get("/webhook", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $params = $request->getQueryParams();
                $respuesta = "";
                
                try{
                    $token = "385402292Mica_02";

                    if (((isset($params['hub_mode'])) & (!empty($params['hub_mode']))) && ((isset($params['hub_verify_token'])) & (!empty($params['hub_verify_token'])))){
                        $mode = $params['hub_mode'];
                        $verify_token = $params['hub_verify_token'];
                        $challenge = $params['hub_challenge'];

                        if (($mode == "subscribe") && ($verify_token == $token)){
                            $respuesta = $challenge;
                        }
                    }

                }catch(PDOException $e){
                    $respuesta = ($e->getMessage());
                }

                // $newResponse = $response->withJson($respuesta);
            
                return $respuesta;
            });
            
            $app->post("/webhook", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                $respuesta['estado'] = false;
                
                try{
                    $mysql = new Database("whatsapp");
                    $object = $data['object'];
                    $entry = $data['entry'];

                    $recibido = json_encode($data);

                    $id_resp = $mysql->Ingreso("INSERT INTO respuestas (respuesta) VALUES (?)", array($recibido));

                    $whatsapp = new WhatsappAPI();

                    if (is_array($entry)){
                        if (count($entry) > 0){
                            foreach ($entry as $linea) {
                                $id = $linea['id'];
                                $changes = $linea['changes'];

                                if (is_array($changes)){
                                    if (count($changes) > 0){
                                        foreach ($changes as $linea_cambio) {
                                            $field = $linea_cambio['field'];
                                            $value = $linea_cambio['value'];

                                            $messaging_product = $value['messaging_product'];
                                            $metadata = $value['metadata'];
                                            $contacts = $value['contacts'];

                                            if (is_array($contacts)){
                                                if (count($contacts) > 0){
                                                    foreach ($contacts as $linea_contact) {
                                                        $profile = $linea_contact['profile']['name'];
                                                        $wa_id = $linea_contact['wa_id'];
                                                       
                                                        $whatsapp->newUser($linea_contact);
                                                    }
                                                }
                                            }

                                            $messages = $value['messages'];

                                            if (is_array($messages)){
                                                if (count($messages) > 0){
                                                    foreach ($messages as $linea_message) {
                                                        $from = $linea_message['from'];
                                                        $id = $linea_message['id'];
                                                        $timestamp = $linea_message['timestamp'];
                                                        $text = $linea_message['text']['body'];
                                                        $type = $linea_message['type'];

                                                        $id_message = $mysql->Ingreso("INSERT INTO messages (fromId, cuerpo, id, tipo, tiempo) VALUES (?,?,?,?,?)", array($from, $text, $id, $type, $timestamp));
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    
                }catch(PDOException $e){
                    $respuesta['error'] = $e->getMessage();
                }

                $newResponse = $response->withJson($respuesta);
            
                return $newResponse;
            });

            $app->post("/messages", function(Request $request, Response $response){
                $authorization = $request->getHeader('Authorization');
                $data = $request->getParsedBody();
                $respuesta['estado'] = false;
                
                try{
                    $whatsaapp = new whatsappAPI();

                    $recipient = $data['recipient'];
                    $message = $data['message'];

                    $coordenadas = array(
                        "latitud" => (float) -0.19006272432606738,
                        "longitud" => (float) -78.48439330289092
                    );
                    $nombre = "Marketing VIP";
                    $direccion = "Quito Eloy Alfaro y Andrade MArin";

                    $buttons = [];
                    array_push($buttons, array(
                        "type" => "reply",
                        "reply" => array(
                            "id" => "123",
                            "title" => "Si"
                        )
                    ));
                    array_push($buttons, array(
                        "type" => "reply",
                        "reply" => array(
                            "id" => "124",
                            "title" => "Si"
                        )
                    ));
                    $respuesta['buttons'] = $buttons;

                    // $respuesta['envio'] = $whatsaapp->newTextMessage($recipient, $message);
                    // $respuesta['imange'] = $whatsaapp->newImageMessage($recipient, $message, "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/14/5993/8035");
                    // $respuesta['imange'] = $whatsaapp->newDocumentMessage($recipient, $message, "https://ventas.mvevip.com/php/contratos/49466-20220602145730.pdf");
                    // $respuesta['imange'] = $whatsaapp->newVideoMessage($recipient, $message, "https://www.youtube.com/watch?v=iR0FwHzSkac");
                    // $respuesta['emvio'] = $whatsaapp->newLocationMessage($recipient, $coordenadas, $nombre, $direccion);
                    // $respuesta['envio'] = $whatsaapp->newAudioMessage($recipient, "https://www.learningcontainer.com/wp-content/uploads/2020/02/Kalimba.mp3");
                    $respuesta['envio'] = $whatsaapp->newButtonMessage($recipient, $message, $buttons);
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