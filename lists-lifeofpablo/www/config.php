<?php

/**
 * Configuration for database connection
 *
 */

$host       = "mariadb1";
$username   = "library_pabs";
$password   = "";
$dbname     = "items";
$dsn        = "mysql:host=$host;dbname=$dbname";
$options    = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
              );
