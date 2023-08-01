<?php
require_once __DIR__ . "/vendor/autoload.php";

use \Hoteleus\XTG;

$app = "";
$token = "";

$xtg = new XTG($empresa, $token);

$qs = array("busqueda"=>"Oaxaca");

$estado = $xtg->GET_Lugares(NULL, $qs);

echo json_encode($estado);