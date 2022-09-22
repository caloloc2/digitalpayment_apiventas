<?php

$credentials = file_get_contents(__DIR__."/credentials.txt");

$campos = explode(" ", $credentials);

define("HOSTNAME", trim($campos[0]));// Nombre del host (localhost aws instance)
define("USERNAME", trim($campos[1])); // Nombre del usuario
define("PASSWORD", trim($campos[2])); // Contraseña
define("DATABASE", trim($campos[3])); // Nombre de la base de datos
