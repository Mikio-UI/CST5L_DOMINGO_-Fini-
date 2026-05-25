<?php
$SERVER_NAME = getenv('MYSQLHOST')     ?: 'localhost';
$USERNAME    = getenv('MYSQLUSER')     ?: 'root';
$PASSWORD    = getenv('MYSQLPASSWORD') ?: '';
$DB_NAME     = getenv('MYSQLDATABASE') ?: 'railaway';