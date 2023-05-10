<?php 

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 10000");
header("Access-Control-Allow-Headers: X-Requested-With, Content-Type, Origin, Cache-Control, Pragma, Authorization, Accept, Accept-Encoding, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header('Content-Type: application/json; charset=utf-8');

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Twilio\Rest\Client; 

require '../vendor/autoload.php';
// require '../src/config/mysql/meta.php';
require '../src/config/mysql/mysql.php';
require '../src/config/s3/index.php';
require '../src/config/files/filesclass.php';
require '../src/config/sri/index.php';
require '../src/config/twilio/whatsapp.php';
require '../src/config/functions/index.php';
require '../src/config/functions/registrocivil.php';
require '../src/config/mail/mailer.php';
// require '../src/config/placetopay/p2p.php';
require '../src/config/stripe/index.php';
require '../src/config/functions/auth.php';
require '../src/config/functions/estados.php';
require '../src/config/functions/valida_documentos.php';
require '../src/config/whatsapp/index.php';
require '../src/config/pdf/index.php';

// Importa librerias Core
require '../src/config/core/funciones.php';
require '../src/config/core/contratos.php';
require '../src/config/core/nibemi.php';
require '../src/config/core/sendinblue.php';
// require '../src/config/core/sri.php';

// define("URL_RETURN", "http://devp2p.mvevip.com");
define("URL_RETURN", "https://pasarelas.mvevip.com");
define("PLACETOPAY", "live"); // test-live
define("PLACETOPAY_EMPRESA", "mvevip_suscription"); // mvevip_unico - mvevip_suscription
define("STRIPE", "live"); // test-live

define("BUCKET_FOLDER_FIRMADOS", "PRODUCCION/FIRMADOS/"); // CARPETA PARA ARCHIVOS FIRMADOS PRUEBAS/PRODUCCION
define("BUCKET_FOLDER_VOUCHERS", "PRODUCCION/VOUCHERS/"); // CARPETA PARA VOUCHERS FIRMADOS PRUEBAS/PRODUCCION
define("BUCKET_MVEVIP", "mvevip");

define("DATABASE", "vtgsa_ventas");
define("CRM", "mve");

$app = new \Slim\App;

// Crea rutas para los endpoints
require '../src/routes/endpoints.php';

$app->run();