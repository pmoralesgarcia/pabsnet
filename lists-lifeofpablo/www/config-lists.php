<?php

/**
 * Configuration for database connection
 *
 */
$ip = getenv('LISTS_MUSIC_LISTENED', true) ?: getenv('LISTS_MUSIC_LISTENED');

$host       = "mariadb";
$username   = "library_pabs";
$password   = "$ip";
$dbname     = "lists";
$dsn        = "mysql:host=$host;dbname=$dbname";
$options    = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
              );
