<?php
function getDBConnection() {
    try {
        $host = 'localhost';
        $dbname = 'web_elearning';
        $username = 'root';
        $password = '';

        $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}
?>