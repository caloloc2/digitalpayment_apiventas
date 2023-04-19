<?php 

/*
CODIGO PARA DEPURAR BASE DE CLIENTES
DONDE SE HAYA ASIGNADO PERO VALIDA SI TIENE REGISTRO DE LLAMADAS
CASO CONTRARIO LIBERA PARA NUEVA ASIGNACION
*/

require __DIR__.'/../../vendor/autoload.php';
// require __DIR__.'/../../src/config/mysql/meta.php';
require __DIR__.'/../../src/config/mysql/mysql.php';
require __DIR__.'/../../src/config/s3/s3class.php';
require __DIR__.'/../../src/config/files/filesclass.php';
require __DIR__.'/../../src/config/sri/index.php';
require __DIR__.'/../../src/config/twilio/whatsapp.php';
require __DIR__.'/../../src/config/functions/index.php';
require __DIR__.'/../../src/config/mail/mailer.php';
require __DIR__.'/../../src/config/placetopay/p2p.php';
require __DIR__.'/../../src/config/functions/auth.php';
require __DIR__.'/../../src/config/functions/valida_documentos.php';

$mysql = new Database('vtgsa_ventas');
$funciones = new Functions();

$archivo_csv = "base_diners_actualizacion.csv";
echo $archivo_csv;
if (!empty($archivo_csv)){
    $banco = 2;
    $identificador = 'ACTUALIZADOS';

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
                    $correo = $linea['correo'];
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
                            // Modificacion 
                            $id_lista = $consulta['id_lista'];
                            
                            $modificar_registro = $mysql->Modificar("UPDATE notas_registros SET banco=?, identificador=? WHERE id_lista=?", array($banco, $identificador, $id_lista));
                    
                            array_push($a_procesar['errores'], array(
                                "documento" => $documento,
                                "nombres" => $nombres,
                                "mensaje" => "El contacto ya se encuentra registrado. Se actualizo sus datos."
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

print_r($respuesta);