<?php

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

header('Content-Type: application/json');

try {
    // Verificar estructura de la tabla
    $query = "DESCRIBE reportes";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $columnas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Contar total de reportes
    $query_count = "SELECT COUNT(*) as total FROM reportes";
    $stmt_count = $conn->prepare($query_count);
    $stmt_count->execute();
    $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Obtener un reporte de ejemplo
    $query_sample = "SELECT * FROM reportes LIMIT 1";
    $stmt_sample = $conn->prepare($query_sample);
    $stmt_sample->execute();
    $primer_registro = $stmt_sample->fetch(PDO::FETCH_ASSOC);
    
    // Verificar usuarios
    $query_usuarios = "SELECT COUNT(*) as total FROM usuarios";
    $stmt_usuarios = $conn->prepare($query_usuarios);
    $stmt_usuarios->execute();
    $total_usuarios = $stmt_usuarios->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'total' => $total,
        'total_usuarios' => $total_usuarios,
        'columnas' => $columnas,
        'primer_registro' => $primer_registro ?: 'No hay registros'
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>