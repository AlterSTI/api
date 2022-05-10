<?php
require_once 'Api.php';
require_once 'DataCache.php';
require_once 'SiteSettings.php';

function pre($array){
    echo '<pre>';
    print_r($array);
    echo '</pre>';
}

function _pre($array){
    echo '<pre>';
    var_dump($array);
    echo '</pre>';
}

try {
    $api = new Api();
    echo $api->run();
} catch (Exception $e) {
    header("HTTP/1.1 404 Not Found");
    echo json_encode(['error' => $e->getMessage()]);
}