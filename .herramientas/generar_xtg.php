<?php

require_once __DIR__ . "/openapi.php";

$funciones_php = array();
foreach($OPENAPI as $url_patron => $url_info) // Para cada llamada disponible
{
    // Calculamos la URL de la llamada
    $url_patron_tmp = substr($url_patron, 2, -3);
    $url_patron_elementos = explode("\/", $url_patron_tmp);

    $url_elementos = array();
    foreach($url_patron_elementos as $elemento) // Para cada elemento
    {
        if(strpos($elemento, "<")) // Si es una variable
        {
            $matches = NULL;
            preg_match('/<(.+)>/', $elemento, $matches, PREG_OFFSET_CAPTURE, 0);

            $url_elementos[] = $matches[0][0];
        }
        else // Si no es una variable
        {
            $url_elementos[] = $elemento;
        }
    }
    $url = implode("/", $url_elementos);

    // Calculamos el nombre base de la llamada
    $script_php_ruta = $url_info["script_php_ruta"];
    $script_php_ruta_elementos = preg_split('/(\/|-)/', $script_php_ruta); // Separamos por / y -

    $nombre_base = "";
    foreach($script_php_ruta_elementos as $ruta_elemento) // Para cada sección del nombre
    {
        $nombre_base .= ucfirst($ruta_elemento);
    }

    foreach($url_info["metodos"] as $metodo=>$metodo_info) // Para cada método que la llamada soporta
    {
        $funcion_nombre = "{$metodo}_{$nombre_base}";

        $funcion_parametros_apicall_str = NULL;
        $funcion_parametros = array();

        switch($metodo)
        {
            case "GET":
            case "DELETE":
                $funcion_parametros[] = "?array \$variables=NULL";
                $funcion_parametros[] = "?array \$querystrings=NULL";

                $funcion_parametros_apicall_str = "\$variables, \$querystrings, NULL";

            break;

            case "POST":
            case "PATCH":
                $funcion_parametros[] = "?array \$variables=NULL";
                $funcion_parametros[] = "?array \$querystrings=NULL";
                $funcion_parametros[] = "?array \$body=NULL";

                $funcion_parametros_apicall_str = "\$variables, \$querystrings, \$body";

                break;

            default:
                throw new Exception("Método no implementado");
        }

        $funcion_parametros_str = implode(",", $funcion_parametros);

        if(!isset($funciones_php[$funcion_nombre])) // Si la función no está definida
        {
            $funciones_php[$funcion_nombre] = array("metodo"=>$metodo, "parametros"=>$funcion_parametros_str, "parametros_apicall"=>$funcion_parametros_apicall_str, "llamadas"=>array());
        }

        $variables = array_keys($url_info["variables"]);

        $funciones_php[$funcion_nombre]["llamadas"][] = array("url" => $url, "variables"=>$variables);
    }
}

// Generamos el código PHP de las funciones
$funciones = "";
foreach($funciones_php as $funcion_nombre=>$funcion_parametros)
{
    $metodo = $funcion_parametros["metodo"];
    $funcion_parametros_str = $funcion_parametros["parametros"];
    $funcion_parametros_apicall_str = $funcion_parametros["parametros_apicall"];

    $body = "";

    if( count($funcion_parametros["llamadas"]) > 1) // Si es una llamada con múltiples URLs
    {
        $body .= "\$url = NULL; ";

        $body .= "\$variables_key = \$this->ObtenerFirmaDeVariables(\$variables); ";

        $body .= "switch(\$variables_key) { ";

        $url = NULL;
        $variables_key = NULL;
        foreach($funcion_parametros["llamadas"] as $llamada) // Para cada llamada
        {
            $url = $llamada["url"];

            $variables = $llamada["variables"];
            asort($variables);
            $variables_key = implode("-", $variables);

            $body .= "case \"{$variables_key}\": \$url = \"{$url}\"; break; ";
        }

        $body .= " default: \$url = \"{$url}\"; break; ";
        $body .= "} ";
    }
    else // Si sólo hay una URL
    {
        $llamada = $funcion_parametros["llamadas"][0];
        $url = $llamada["url"];

        $body .= "\$url = \"{$url}\"; ";
    }

    $body .= "return \$this->API_CALL(\"{$metodo}\", \$url, {$funcion_parametros_apicall_str});";

    $funciones .= "\tpublic function {$funcion_nombre}({$funcion_parametros_str}){ {$body} }" . PHP_EOL;
}

// Cargamos la plantilla
$php = file_get_contents(__DIR__ . "/plantilla_xtg.txt");
$php = str_replace("##OPENAPI##", $funciones, $php);
file_put_contents(__DIR__ . "/../src/XTG.php", $php);

echo "<pre>";
echo $funciones;