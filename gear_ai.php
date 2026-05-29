<?php
// gear_ai.php - Recibe preguntas y busca en los PDFs

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pregunta = $_POST['pregunta'] ?? '';
    
    if (empty($pregunta)) {
        echo json_encode(['error' => 'No se recibió ninguna pregunta']);
        exit;
    }
    
    // Escapar la pregunta para el comando
    $pregunta_escapada = escapeshellarg($pregunta);
    
    // Ejecutar el comando npx desde PHP
    $comando = "npx mcp-local-rag query $pregunta_escapada 2>&1";
    $output = shell_exec($comando);
    
    // Decodificar la respuesta JSON
    $resultados = json_decode($output, true);
    
    if ($resultados && isset($resultados['results'])) {
        // Formatear los resultados como respuesta legible
        $respuesta = [];
        foreach ($resultados['results'] as $item) {
            $respuesta[] = [
                'texto' => $item['text'],
                'archivo' => $item['source'] ?? 'Desconocido',
                'relevancia' => $item['score'] ?? 'N/A'
            ];
        }
        echo json_encode(['success' => true, 'resultados' => $respuesta]);
    } else {
        echo json_encode(['error' => 'No se encontraron resultados', 'raw' => $output]);
    }
    exit;
}
?>

<!-- Interfaz HTML simple -->
<!DOCTYPE html>
<html>
<head>
    <title>Chatbot - Pregunta a tus PDFs</title>
    <style>
        body { font-family: Arial; max-width: 800px; margin: 50px auto; padding: 20px; }
        #chat { border: 1px solid #ccc; height: 400px; overflow-y: auto; padding: 10px; margin-bottom: 10px; }
        .user-msg { text-align: right; margin: 10px; padding: 8px; background: #007bff; color: white; border-radius: 10px; }
        .bot-msg { margin: 10px; padding: 8px; background: #e9ecef; border-radius: 10px; }
        input { width: 80%; padding: 10px; }
        button { padding: 10px 20px; }
    </style>
</head>
<body>
    <h2>🤖 Chatbot de Documentos</h2>
    <div id="chat">
        <div class="bot-msg">¡Hola! Pregúntame sobre la información en los PDFs cargados.</div>
    </div>
    <input type="text" id="pregunta" placeholder="Escribe tu pregunta...">
    <button onclick="enviarPregunta()">Enviar</button>

    <script>
        async function enviarPregunta() {
            const input = document.getElementById('pregunta');
            const pregunta = input.value.trim();
            if (!pregunta) return;
            
            // Mostrar mensaje del usuario
            const chat = document.getElementById('chat');
            const userDiv = document.createElement('div');
            userDiv.className = 'user-msg';
            userDiv.textContent = pregunta;
            chat.appendChild(userDiv);
            input.value = '';
            
            // Mostrar "escribiendo..."
            const botDiv = document.createElement('div');
            botDiv.className = 'bot-msg';
            botDiv.textContent = '🔍 Buscando en los documentos...';
            chat.appendChild(botDiv);
            chat.scrollTop = chat.scrollHeight;
            
            // Enviar al backend PHP
            const formData = new FormData();
            formData.append('pregunta', pregunta);
            
            const response = await fetch('gear_ai.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            // Actualizar respuesta del bot
            if (data.success && data.resultados.length > 0) {
                botDiv.innerHTML = '<strong>📄 Resultados encontrados:</strong><br><br>';
                data.resultados.forEach(r => {
                    botDiv.innerHTML += `<strong>📁 ${r.archivo}</strong> (relevancia: ${r.relevancia.toFixed(2)})<br>`;
                    botDiv.innerHTML += `${r.texto.substring(0, 500)}...<br><br>`;
                });
            } else {
                botDiv.textContent = '❌ No se encontró información relacionada en los documentos.';
            }
            chat.scrollTop = chat.scrollHeight;
        }
    </script>
</body>
</html>