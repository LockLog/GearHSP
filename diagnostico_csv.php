<?php
// Habilitar todos los errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

echo "<h2>Diagnóstico de Conexión BD</h2>";

// 1. Verificar conexión
try {
    echo "✅ Conexión BD exitosa<br>";
    
    // 2. Verificar tabla existe
    $stmt = $conn->query("SHOW TABLES LIKE 'reportes'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabla 'reportes' existe<br>";
        
        // 3. Verificar estructura
        $stmt = $conn->query("DESCRIBE reportes");
        echo "<h3>Estructura de la tabla 'reportes':</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>{$row['Field']}</td>";
            echo "<td>{$row['Type']}</td>";
            echo "<td>{$row['Null']}</td>";
            echo "<td>{$row['Key']}</td>";
            echo "<td>{$row['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "❌ Tabla 'reportes' NO existe<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Error BD: " . $e->getMessage() . "<br>";
}

echo "<hr><h2>Probar Inserción Manual</h2>";

// Insertar un registro de prueba
try {
    $sql = "INSERT INTO reportes (
        Fecha_Atencion, Tipo_Reporte, RUT, Agenda, Profesional, 
        Hora, Paciente, Tipo_Atencion, Estado_Cita, Num_Reporte, 
        Agendado_por, ID_agenda, Grupo, Usuario_Carga, Fecha_Carga
    ) VALUES (
        '2023-12-01', 'Test', '11.111.111-1', 'Test Agenda', 'Dr. Test',
        '10:00:00', 'Paciente Test', 'Consulta', 'Activo', 'TEST001',
        'System', 'TEST001', 'Test Grupo', 'diagnostico', NOW()
    )";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    echo "✅ Inserción manual exitosa<br>";
    echo "ID insertado: " . $conn->lastInsertId() . "<br>";
    
} catch (PDOException $e) {
    echo "❌ Error en inserción: " . $e->getMessage() . "<br>";
    echo "SQL: " . $sql . "<br>";
}

echo "<hr><h2>Probar CSV Simulado</h2>";

// Simular procesamiento CSV
$csv_data = "2023-12-01,Control,11.111.111-1,Medicina General,Dr. Test,10:00:00,Paciente Test,Consulta,Activo,TEST001,System,TEST001,Test Grupo";

$datos = explode(',', $csv_data);
echo "Datos CSV: <pre>" . print_r($datos, true) . "</pre>";

try {
    $sql = "INSERT INTO reportes (
        Fecha_Atencion, Tipo_Reporte, RUT, Agenda, Profesional, 
        Hora, Paciente, Tipo_Atencion, Estado_Cita, Num_Reporte, 
        Agendado_por, ID_agenda, Grupo, Usuario_Carga, Fecha_Carga
    ) VALUES (
        :fecha_atencion, :tipo_reporte, :rut, :agenda, :profesional,
        :hora, :paciente, :tipo_atencion, :estado_cita, :num_reporte,
        :agendado_por, :id_agenda, :grupo, :usuario_carga, NOW()
    )";
    
    $stmt = $conn->prepare($sql);
    
    $params = [
        ':fecha_atencion'  => $datos[0] ?? NULL,
        ':tipo_reporte'    => $datos[1] ?? NULL,
        ':rut'             => $datos[2] ?? NULL,
        ':agenda'          => $datos[3] ?? NULL,
        ':profesional'     => $datos[4] ?? NULL,
        ':hora'            => $datos[5] ?? NULL,
        ':paciente'        => $datos[6] ?? NULL,
        ':tipo_atencion'   => $datos[7] ?? NULL,
        ':estado_cita'     => $datos[8] ?? NULL,
        ':num_reporte'     => $datos[9] ?? NULL,
        ':agendado_por'    => $datos[10] ?? NULL,
        ':id_agenda'       => $datos[11] ?? NULL,
        ':grupo'           => $datos[12] ?? NULL,
        ':usuario_carga'   => 'diagnostico'
    ];
    
    echo "Parámetros: <pre>" . print_r($params, true) . "</pre>";
    
    $result = $stmt->execute($params);
    
    if ($result) {
        echo "✅ Inserción desde CSV simulada exitosa<br>";
        echo "ID insertado: " . $conn->lastInsertId() . "<br>";
    } else {
        echo "❌ Error en ejecución<br>";
        echo "Error info: <pre>" . print_r($stmt->errorInfo(), true) . "</pre>";
    }
    
} catch (PDOException $e) {
    echo "❌ Error PDO: " . $e->getMessage() . "<br>";
}

echo "<hr><h2>Verificar Registros</h2>";

try {
    $stmt = $conn->query("SELECT COUNT(*) as total FROM reportes");
    $total = $stmt->fetchColumn();
    echo "Total registros en tabla: " . $total . "<br>";
    
    $stmt = $conn->query("SELECT * FROM reportes ORDER BY Fecha_Carga DESC LIMIT 5");
    echo "Últimos 5 registros:<br>";
    echo "<table border='1' cellpadding='5'>";
    
    // Encabezados
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo "<tr>";
        foreach (array_keys($row) as $key) {
            echo "<th>" . htmlspecialchars($key) . "</th>";
        }
        echo "</tr>";
        
        // Primera fila
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value) . "</td>";
        }
        echo "</tr>";
        
        // Resto de filas
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "❌ Error al leer registros: " . $e->getMessage() . "<br>";
}

echo "<hr><h2>Comprobar Variables POST/FILES</h2>";
echo "Método de solicitud: " . $_SERVER['REQUEST_METHOD'] . "<br>";
echo "¿Existe archivo_csv? " . (isset($_FILES['archivo_csv']) ? 'SÍ' : 'NO') . "<br>";

if (isset($_FILES['archivo_csv'])) {
    echo "<pre>" . print_r($_FILES['archivo_csv'], true) . "</pre>";
}

// Formulario simple para probar
?>
<hr>
<h2>Probar Subida Real</h2>
<form method="POST" enctype="multipart/form-data">
    <input type="file" name="archivo_csv" accept=".csv">
    <button type="submit">Probar subida</button>
</form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_csv'])) {
    echo "<h3>Archivo recibido:</h3>";
    echo "<pre>" . print_r($_FILES['archivo_csv'], true) . "</pre>";
    
    // Leer contenido
    if ($_FILES['archivo_csv']['error'] === UPLOAD_ERR_OK) {
        $contenido = file_get_contents($_FILES['archivo_csv']['tmp_name']);
        echo "<h3>Primeras 500 caracteres del archivo:</h3>";
        echo "<pre>" . htmlspecialchars(substr($contenido, 0, 500)) . "</pre>";
        
        // Probar inserción real
        echo "<h3>Probar inserción real:</h3>";
        
        $handle = fopen($_FILES['archivo_csv']['tmp_name'], 'r');
        $encabezados = fgetcsv($handle, 1000, ',');
        echo "Encabezados: <pre>" . print_r($encabezados, true) . "</pre>";
        
        $contador = 0;
        while (($datos = fgetcsv($handle, 1000, ',')) !== FALSE) {
            $contador++;
            echo "Línea $contador: " . implode(', ', $datos) . "<br>";
            
            // Intentar insertar solo la primera línea
            if ($contador === 1) {
                try {
                    $sql = "INSERT INTO reportes (
                        Fecha_Atencion, Tipo_Reporte, RUT, Agenda, Profesional, 
                        Hora, Paciente, Tipo_Atencion, Estado_Cita, Num_Reporte, 
                        Agendado_por, ID_agenda, Grupo, Usuario_Carga, Fecha_Carga
                    ) VALUES (
                        :fecha_atencion, :tipo_reporte, :rut, :agenda, :profesional,
                        :hora, :paciente, :tipo_atencion, :estado_cita, :num_reporte,
                        :agendado_por, :id_agenda, :grupo, :usuario_carga, NOW()
                    )";
                    
                    $stmt = $conn->prepare($sql);
                    
                    $params = [
                        ':fecha_atencion'  => $datos[0] ?? NULL,
                        ':tipo_reporte'    => $datos[1] ?? NULL,
                        ':rut'             => $datos[2] ?? NULL,
                        ':agenda'          => $datos[3] ?? NULL,
                        ':profesional'     => $datos[4] ?? NULL,
                        ':hora'            => $datos[5] ?? NULL,
                        ':paciente'        => $datos[6] ?? NULL,
                        ':tipo_atencion'   => $datos[7] ?? NULL,
                        ':estado_cita'     => $datos[8] ?? NULL,
                        ':num_reporte'     => $datos[9] ?? NULL,
                        ':agendado_por'    => $datos[10] ?? NULL,
                        ':id_agenda'       => $datos[11] ?? NULL,
                        ':grupo'           => $datos[12] ?? NULL,
                        ':usuario_carga'   => 'test_upload'
                    ];
                    
                    $result = $stmt->execute($params);
                    
                    if ($result) {
                        echo "✅ <strong>Inserción REAL exitosa! ID: " . $conn->lastInsertId() . "</strong><br>";
                        echo "Parámetros usados: <pre>" . print_r($params, true) . "</pre>";
                    } else {
                        echo "❌ Error en inserción real<br>";
                        echo "Error info: <pre>" . print_r($stmt->errorInfo(), true) . "</pre>";
                    }
                    
                } catch (PDOException $e) {
                    echo "❌ Error PDO en inserción real: " . $e->getMessage() . "<br>";
                }
                break;
            }
        }
        fclose($handle);
    }
}