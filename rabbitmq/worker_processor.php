<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$queueFile = __DIR__ . '/../queue/print_queue.json';
$maxAttempts = 10;

function actualizarEstado(array $job, string $status): void {
    if (!isset($job['id'])) {
        echo "⚠️ El trabajo no tiene 'id', no se puede actualizar\n";
        return;
    }

    $job['status'] = $status;
    $file = [];

    if (file_exists($GLOBALS['queueFile'])) {
        $json = file_get_contents($GLOBALS['queueFile']);
        $file = json_decode($json, true);
        if ($file === null && json_last_error() !== JSON_ERROR_NONE) {
            echo "⚠️ Error al decodificar JSON: " . json_last_error_msg() . "\n";
            $file = [];
        }
    }
    
    echo "El documento esta siendo guardado";
    $file[$job['id']] = $job;
    file_put_contents($GLOBALS['queueFile'], json_encode($file, JSON_PRETTY_PRINT));
}

$connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
$channel = $connection->channel();

$channel->queue_declare('cola_impresion', false, true, false, false);
$channel->queue_declare('cola_eliminacion', false, true, false, false);

echo "🖨️ Escuchando trabajos de impresión...\n";

$callback = function ($msg) use ($channel, $maxAttempts) {
    $job = json_decode($msg->body, true);

    // Asegura ID único
    if (!isset($job['id'])) {
        $job['id'] = uniqid('job_');
    }

    echo "➡️ Procesando trabajo: {$job['id']}\n";

    // ✅ Registrar como pendiente al llegar
    actualizarEstado($job, 'pending');
};


$channel->basic_consume('cola_impresion', '', false, true, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}
