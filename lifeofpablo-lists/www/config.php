<?php

/**
 * Configuration for database connection
 *
 */
$ip         = getenv(LISTS_MUSIC_LISTENED, $local_only=true);
$host       = "mariadb";
$username   = "library_pabs";
$password   = "$ip";
$dbname     = "items";
$dsn        = "mysql:host=$host;dbname=$dbname";
$options    = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
              );
