<?php
session_start();
$_SESSION['user_id'] = 1; // Temporal para test

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    echo "✓ Conexión a BD exitosa<br>";
    
    // Verificar si existe la tabla usuarios
    $stmt = $conn->query("SHOW TABLES LIKE 'usuarios'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Tabla usuarios existe<br>";
    } else {
        echo "✗ Tabla usuarios NO existe<br>";
    }
    
    // Verificar estructura de la tabla
    $stmt = $conn->query("DESCRIBE usuarios");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Columnas de usuarios: " . implode(', ', $columns) . "<br>";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage();
}
?>