<?php

/**
 * Configuration for database connection
 *
 */

$host       = "mariadb1";
$username   = "library_pabs";
$password   = "$ip = getenv('LISTS_MUSIC_LISTENED', true) ?: getenv('LISTS_MUSIC_LISTENED')";
$dbname     = "items";
$dsn        = "mysql:host=$host;dbname=$dbname";
$options    = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
              );
