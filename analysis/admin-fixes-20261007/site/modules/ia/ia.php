<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Para desarrollo, ajusta en producción
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require '../vendor/autoload.php'; // Si usas Composer

use ArdaGnsrn\Ollama\Ollama;
$ollama = Ollama::client('http://localhost:11434'); // puedes cambiar el host

// Modelo que vas a usar (cámbialo según lo que tengas bajado)
define('OLLAMA_MODEL', 'llama3.2:3b');    // liviano y rápido
// define('OLLAMA_MODEL', 'qwen2.5:7b');   // mejor en español
// define('OLLAMA_MODEL', 'deepseek-r1:8b');

try {
    // Recibe la pregunta del frontend
    $input = json_decode(file_get_contents('php://input'), true);
    $pregunta = $input['pregunta'] ?? '';

    if (empty($pregunta)) {
        echo json_encode(['error' => 'No hay pregunta']);
        exit;
    }


    $response = $ollama->chat()->create([
        'model'    => OLLAMA_MODEL,
        'messages' => [
            ['role' => 'user', 'content' => $pregunta],
            // Opcional: agrega system prompt
            // ['role' => 'system', 'content' => 'Responde siempre en español y sé breve.'],
        ],
        'stream'   => false,
        'options'  => [
            'temperature'   => 0.75,
            'num_predict'   => 600,
        ]
    ]);

    $respuesta = $response->message->content ?? 'No se recibió respuesta';

    echo json_encode(['respuesta' => $respuesta]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}