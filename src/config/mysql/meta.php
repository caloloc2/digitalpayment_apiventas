<?php

/**
 * Representa el la estructura de las metas
 * almacenadas en la base de datos
 */
require 'conexion.php';

class Meta
{
    function __construct()
    {
    }

    /**
     * Retorna en la fila especificada de la tabla 'meta'
     *
     * @param $idMeta Identificador del registro
     * @return array Datos del registro
     */
    public static function Consulta($sql)
    {
        $consulta = $sql;
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Obtiene los campos de una meta con un identificador
     * determinado
     *
     * @param $id_test Identificador de la meta
     * @return mixed
     */
    public static function Ejecutar($sql) {
        // Consulta de la meta
        $consulta = $sql;

        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute();
                        
            return 1;

        } catch (PDOException $e) {
            // Aquí puedes clasificar el error dependiendo de la excepción
            // para presentarlo en la respuesta Json
            return -1;
        }
    }

    /**
     * Obtiene los campos de una meta con un identificador
     * determinado
     *
     * @param $id_test Identificador de la meta
     * @return mixed
     */
    public static function Consulta_Unico($sql) {
        // Consulta de la meta
        $consulta = $sql;

        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute();
            // Capturar primera fila del resultado
            $row = $comando->fetch(PDO::FETCH_ASSOC);
            return $row;

        } catch (PDOException $e) {
            // Aquí puedes clasificar el error dependiendo de la excepción
            // para presentarlo en la respuesta Json
            return -1;
        }
    }

    /**
     * Eliminar el registro con el identificador especificado
     *
     * @param $idMeta identificador de la meta
     * @return bool Respuesta de la eliminación
     */
    public static function Actualizar_Campo($tabla, $campo, $valor, $id, $id_valida)
    {
        // Sentencia DELETE
        $comando = "UPDATE ".$tabla." SET ".$campo."=? WHERE ".$id."= ?";

        // Preparar la sentencia
        $sentencia = Database::getInstance()->getDb()->prepare($comando);

        return $sentencia->execute(array($valor, $id_valida));
    }

    /**
     * Insertar una nueva meta
     *
     * @param $id_test      identificador
     * @param $nombre       nuevo titulo
     * @param $edad         nueva descripcion 
     * @return PDOStatement
     */
    public static function Nuevo_Registro($fecha_venta, $id_vendedor, $id_lider, $dummy, $id_cliente, $documento, $nombres_apellidos, $ciudad, $correo, $convencional, $celular, $pago_parcial, $tipo_venta, $fecha_caducidad, $tipo, $que_destino, $id_destino, $fecha_alta, $fecha_modificacion, $estado)
    {        
        // Sentencia INSERT
        $comando = "INSERT INTO registros (fecha_venta, id_vendedor, id_lider, dummy, id_cliente, documento, nombres_apellidos, ciudad, correo, convencional, celular, pago_parcial, tipo_venta, fecha_caducidad, tipo, que_destino, id_destino, fecha_alta, fecha_modificacion, estado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = Database::getInstance()->getDb()->prepare($comando);

        return $sentencia->execute(array($fecha_venta, $id_vendedor, $id_lider, $dummy, $id_cliente, $documento, $nombres_apellidos, $ciudad, $correo, $convencional, $celular, $pago_parcial, $tipo_venta, $fecha_caducidad, $tipo, $que_destino, $id_destino, $fecha_alta, $fecha_modificacion, $estado));

    }

    /**
     * Insertar una nueva meta
     *
     * @param $id_test      identificador
     * @param $nombre       nuevo titulo
     * @param $edad         nueva descripcion 
     * @return PDOStatement
     */
    public static function Nuevo_Pago($id_registro, $referencia, $aprobacion, $comision, $valor_voucher, $id_institucion, $primeros_digitos, $ultimos_digitos, $imagen, $fecha_pago, $estado)
    {        
        // Sentencia INSERT
        $comando = "INSERT INTO registros_pagos (id_registro, referencia, aprobacion, comision, valor_voucher, id_institucion, primeros_digitos, ultimos_digitos, imagen, fecha_pago, estado) VALUES (?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = Database::getInstance()->getDb()->prepare($comando);

        return $sentencia->execute(array($id_registro, $referencia, $aprobacion, $comision, $valor_voucher, $id_institucion, $primeros_digitos, $ultimos_digitos, $imagen, $fecha_pago, $estado));

    }
}


?>