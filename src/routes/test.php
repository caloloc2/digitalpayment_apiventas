<?php

// header("Access-Control-Allow-Origin: *");
// header("Access-Control-Allow-Credentials: true");
// header("Access-Control-Max-Age: 10000");
// header("Access-Control-Allow-Headers: X-Requested-With, Content-Type, Origin, Cache-Control, Pragma, Authorization, Accept, Accept-Encoding");
// header("Access-Control-Allow-Methods: *");
// header('Content-Type: application/json; charset=utf-8');

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

        $app->get("/registros", function(Request $request, Response $response){
            $authorization = $request->getHeader('Authorization');
            $respuesta['estado'] = false;
            $respuesta['error'] = "";
        
            $consulta = "SELECT * FROM registros";
        
            try{
                $consulta = Meta::Consulta($consulta);
                
                $respuesta['consulta'] = $consulta;                
                $respuesta['estado'] = true;
        
                $newResponse = $response->withJson($respuesta);
        
                return $newResponse;
        
            }catch(PDOException $e){
                echo "Error: ".$e->getMessage();
            }
        });

        $app->get("/registros/{id}", function(Request $request, Response $response){
            $id = $request->getAttribute('id');
            $authorization = $request->getHeader('Authorization');

            $respuesta['estado'] = false;
            $respuesta['error'] = "";
        
            $consulta = "SELECT * FROM registros WHERE id_registro=".$id;
        
            try{
                $consulta = Meta::Consulta_Unico($consulta);

                $respuesta['consulta'] = $consulta;
                $respuesta['estado'] = true;
        
                $newResponse = $response->withJson($respuesta);
        
                return $newResponse;
        
            }catch(PDOException $e){
                echo "Error: ".$e->getMessage();
            }
        });

        $app->post("/registros", function(Request $request, Response $response){
            $respuesta['estado'] = false;
            $respuesta['error'] = "";

            $authorization = $request->getHeader('Authorization');
            $data = $request->getParsedBody();
            $files = $request->getUploadedFiles();            
        
            try{                
                if ($request->getMethod() == "POST"){
                    // $contador = 0;
                    // if (is_array($data)){
                    //     $contador = count($data);
                    // }else{
                    //     $respuesta['error'] = "No es un array";
                    // }

                    // $x=1;
                    // foreach ($files['archivo'] as $linea) {
                    //     $archivo = $linea->file;
                    //     $s3 = new AWSS3();
                    //     $key = "usuario/contratos/archivo".$x.".pdf";
                    //     $guarda = $s3->saveFileS3($archivo, $key, "calolobucket");
                    //     $x+=1;
                    // }

                    // $filename = $files['archivo']->file;
                    // $s3 = new AWSS3();
                    // $new_bucket = $data['nombres'];
                    // $crear_bucket = $s3->create_bucket($new_bucket);
                    
                    // $new_bucket = "calolobucket";
                    // $filepaths3 = "usuario/contratos/reporte.pdf"; // key de s3 se debe guardar

                    // $guarda = $s3->saveFileS3($filename, $filepaths3, $new_bucket);

                    // $obtiene_url_temp = $s3->get_url_file($new_bucket, $filepaths3, 2);  


                    
                    // $local = new Files("usuario/contratos"); // se especifica la carpeta dnd se guardara el archivo (sino existe lo crea)
                    // $nombre = "reporte.pdf";
                    // $guarda = $local->saveFile($filename, $nombre); // archivo desde input, nombre del archivo

                    $fecha = date("Y-m-d H:i:s");
                    $estado = 0;
                    $ingreso = Meta::Nuevo_Registro($data['nombres'], $data['valor'], $fecha, $estado);
    
                    // $respuesta["contador"] = $contador;
                    // $respuesta['auto'] = $authorization;
                    // $respuesta['data'] = $data;
                    // $respuesta['files'] = $files;
                    // $respuesta['method'] = 'POST';
    
                    $respuesta['estado'] = true;
                }else{
                    $respuesta['error'] = "No es el metodo correcto.";
                }

                $newResponse = $response->withJson($respuesta);
        
                return $newResponse;
        
            }catch(PDOException $e){
                echo "Error: ".$e->getMessage();
            }
        });

        $app->put("/registros/{id}", function(Request $request, Response $response){
            $id = $request->getAttribute('id');

            $respuesta['estado'] = false;
            $respuesta['error'] = "";

            $authorization = $request->getHeader('Authorization');
            $data = $request->getParsedBody();
            $files = $request->getUploadedFiles();

            // $consulta = "SELECT * FROM hoteles WHERE id_hotel=".$id;
        
            try{
                // $consulta = Meta::Consulta_Unico($consulta);
                // $respuesta['consulta'] = $consulta;

                // $s3 = new AWSS3();
                // $lee = $s3->get_buckets();

                if ($request->getMethod() == "PUT"){
                    $contador = 0;
                    if (is_array($data)){
                        $contador = count($data);
                    }else{
                        $respuesta['error'] = "No es un array";
                    }
    
                    $respuesta["contador"] = $contador;
                    $respuesta['auto'] = $authorization;
                    $respuesta['data'] = $data;
                    $respuesta['files'] = $files;
                    // $respuesta['s3'] = $lee;
                    $respuesta['method'] = 'UPDATE';
    
                    $respuesta['estado'] = true;
                }else{
                    $respuesta['error'] = "No es el metodo correcto.";
                }
                
                $newResponse = $response->withJson($respuesta);
        
                return $newResponse;
        
            }catch(PDOException $e){
                echo "Error: ".$e->getMessage();
            }
        });
        
        $app->delete("/registros/{id}", function(Request $request, Response $response){
            $id = $request->getAttribute('id');

            $respuesta['estado'] = false;
            $respuesta['error'] = "";

            $authorization = $request->getHeader('Authorization');            
        
            try{
                // $consulta = Meta::Consulta_Unico($consulta);
                            
                if ($request->getMethod() == "DELETE"){
                        
                    $respuesta['auto'] = $authorization;
                    $respuesta['id'] = $id;                    
                    $respuesta['method'] = 'DELETE';

                    // $s3 = new AWSS3();
                    // $guarda = $s3->remove_file_to_bucket("reporte.pdf", "calolobucket");
    
                    $respuesta['estado'] = true;
                }else{
                    $respuesta['error'] = "No es el metodo correcto.";
                }
                
                $newResponse = $response->withJson($respuesta);
        
                return $newResponse;
        
            }catch(PDOException $e){
                echo "Error: ".$e->getMessage();
            }
        });   
    });

});