<?php
$ip = ['<trusted ips>'];
$requestedIp = $_SERVER['REMOTE_ADDR'];
if (!in_array($requestedIp, $ip))
{
    echo 'Really?!';
    exit;
}

# get and clear url like mvc url
$url = $_SERVER['REQUEST_URI'];
$url = ltrim($url,"/");
$url = explode("/",$url);

# open main file
require 'vendor/autoload.php';
require_once 'main.php';
require 'vendor/finglish/ConvertFinglishToFarsi.php';

# split method name and parameter value from url
$method = $url[1];
$param1 = isset($url[2]) ? urldecode($url[2]) : null;
$param2 = isset($url[3]) ? urldecode($url[3]) : null;
$param3 = isset($url[4]) ? urldecode($url[4]) : null;
$param4 = isset($url[5]) ? urldecode($url[5]) : null;
$param5 = isset($url[6]) ? urldecode($url[6]) : null;

# call method with parameter
$operation = new Main();
$operation->$method($param1, $param2, $param3, $param4, $param5);
