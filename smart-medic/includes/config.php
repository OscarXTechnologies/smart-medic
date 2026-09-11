<?php

$host = "localhost";
$username = "root";
$password = "";
$database = "smart_medic";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed.");
}

if (!$conn->set_charset("utf8mb4")) {
    die("Database character set configuration failed.");
}
