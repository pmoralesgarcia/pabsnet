<?php

/**
 * Configuration for database connection
 *
 */

$host       = "mariadb";
$username   = "lists";
$password   = "";
$dbname     = "items";
$dsn        = "mysql:host=$host;dbname=$dbname";
$options    = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
              );
