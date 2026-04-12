<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// ... reste du code
$servername = 'localhost'; // Changé de $host à $servername
$dbname = 'jipi9175_dbjj';
$username = 'jipi9175_jj';
$password = '1Jjysselp';
try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>
