<?php

/**
 * Configuration for database connection
 *
 */


$host       = "mariadb";
$username   = "library_pabs";
$password   = getenv('LISTS_MUSIC_LISTENED', true) ?: getenv('LISTS_MUSIC_LISTENED');
$dbname     = "lists";
$dsn        = "mysql:host=$host;dbname=$dbname";
$options    = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
              );
