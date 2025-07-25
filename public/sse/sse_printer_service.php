<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: text/event-stream");
header("Cache-Control: no-cache");
header("Connection: keep-alive");
header("X-Accel-Buffering: no");

ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', 0);
ob_implicit_flush(true);

if (ob_get_level() == 0) {
    ob_start();
}

require_once __DIR__ . '/../../vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$queueFile = realpath(__DIR__ . '/../../queue') . '/print_queue.json';
$lastDataHash = null;
$lastPingTime = time();
$maxAttempts = 10;

function logMessage($type, $message) {
    $payload = json_encode([
        'type' => $type,
        'message' => $message
    ]);
    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json",
            'content' => $payload
        ]
    ]);
    @file_get_contents('http://localhost/logger.php', false, $context);
}

function responseHandler($event, $data) {
    echo "event: $event\n";
    echo "data: $data\n\n";
}

function enviarAColaEliminacion(array $job) {
    try {
        $connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
        $channel = $connection->channel();

        $channel->queue_declare('cola_eliminacion', false, true, false, false);

        $msg = new AMQPMessage(json_encode($job), ['delivery_mode' => 2]);
        $channel->basic_publish($msg, '', 'cola_eliminacion');

        $channel->close();
        $connection->close();

        logMessage('info', '📤 Trabajo enviado a cola de eliminación (ID: ' . ($job['id'] ?? 'sin ID') . ')');
    } catch (Exception $e) {
        logMessage('error', '❌ Error al enviar a cola de eliminación: ' . $e->getMessage());
    }
}

logMessage('info', '🎯 Servicio SSE iniciado.');

while (true) {
    if (connection_aborted()) {
        logMessage('info', '⛔ Cliente desconectado.');
        break;
    }

    $updated = false;

    if (file_exists($queueFile)) {
        $queue = json_decode(file_get_contents($queueFile), true);
        if (!is_array($queue)) $queue = [];

        foreach ($queue as &$job) {
            if ($job['status'] === 'pending') {
                // Si no tiene attempts, inicializar
                if (!isset($job['attempts'])) {
                    $job['attempts'] = 1;
                    $updated = true;
                } else {
                    $job['attempts']++;
                    $updated = true;
                }

                if ($job['attempts'] > $maxAttempts) {
                    $job['status'] = 'error';
                    logMessage('error', '⛔ Trabajo fallido (ID: ' . ($job['id'] ?? 'sin ID') . ') - demasiados intentos.');

                     // Enviar a cola de eliminación
                    enviarAColaEliminacion($job);

                    // Eliminar archivo si existe
                    // if (!empty($job['path']) && file_exists($job['path'])) {
                    //     unlink($job['path']);
                    //     logMessage('info', '🗑️ Archivo eliminado: ' . $job['path']);
                    // }

                    $updated = true;
                }
            }
        }
        // unset($job); // Limpieza de referencia

         // Guardar si se actualizó
        if ($updated) {
            file_put_contents($queueFile, json_encode($queue, JSON_PRETTY_PRINT));
        }

        // Enviar tareas pendientes (si hay)
        $pending = array_filter($queue, fn($job) => $job['status'] === 'pending');

        if (!empty($pending)) {
            $data = json_encode(array_values($pending));
            $currentHash = md5($data);

            if ($currentHash !== $lastDataHash) {
                responseHandler('print_jobs', $data);
                $lastDataHash = $currentHash;
                logMessage('info', '📦 Tareas enviadas: ' . $data);
            }
        }
    }

    // Ping para mantener conexión activa
    if (time() - $lastPingTime >= 2) {
        responseHandler('ping', json_encode(["timestamp" => time()]));
        $lastPingTime = time();
    }

    if (ob_get_level() > 0) ob_flush();
    flush();

    sleep(1);
}