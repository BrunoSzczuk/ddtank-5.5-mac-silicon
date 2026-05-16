<?php
#---------------------------------#
# Desenvolvido por SkyTank Gamers #
# -Jamaicano & Anounymous © 2016- #
#---------------------------------#

@session_start();
require_once __DIR__ . '/_sqlsrv_shim.php';
$conn                      = null;
$c_host                    = 'mssql';
$config['UID']             = 'sa';
$config['PWD']             = 'ddtank@2016';
$config['Database']        = 'Db_Membership';
$config['CharacterSet']    = 'UTF-8';
$dbtank					   = 'Db_Tank';
$dbmembership			   = 'Db_Membership';
$RateTimeToCoin  		   = 10; // Quantidade de coins por tempo online.

#--------------------------------------------
#--------------------------------------------
#--------------------------------------------

$LinkLogin				= 'http://localhost:8080/';
$LinkFlash				= 'http://localhost:8080/Flash/';
$jogando				= 'DDTank- s02:Vale Curioso - SkyTank Gamers'; 
$icons					= '<link rel="icon" type="image/x-icon" href="http://i.imgur.com/KdyDs8I.png" />';
$titulo					= 'SkyTank - Servidor 2';
$description   			= 'Copyright © 2016 SkyTank Gamers. Todos os direitos reservados.';
$pagina					= 'https://www.facebook.com/SkyTankOficial/';
$grupo					= 'https://www.facebook.com/groups/DDTUltimate/'; 

#--------------------------------------------
#--------------------------------------------
#--------------------------------------------

include('function.php');

#--------------------------------------------
#--------------------------------------------
#--------------------------------------------

$dbhost = 'DRIVER={SQL Server};SERVER=localhost=Db_Tank';
define('HOST','mssql');
define('USER','sa');
define('PASS','ddtank@2016');
$conn = odbc_connect("Driver=FreeTDS;Port=1433;TDS_Version=7.4;Server=".HOST.";", USER, PASS);

#--------------------------------------------
#--------------------------------------------
#--------------------------------------------

$Play[0]			= 'play.php'; //Play com Anuncio
$Play[1]			= 'playvip.php'; //Play com Script

#-----------------------------------------