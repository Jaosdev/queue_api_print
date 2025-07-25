<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;


$connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
$channel = $connection->channel();

// Cola principal
$channel->queue_declare('cola_impresion', false, true, false, false);

// Cola de eliminación
$channel->queue_declare('cola_eliminacion', false, true, false, false);

// (Opcional) Cola SSE para enviar eventos en tiempo real
$channel->queue_declare('cola_sse', false, true, false, false);

// (Opcional) Configura TTL y DLX para trabajos que expiren
// Ejemplo: mensajes que duren solo 30 segundos en cola_impresion_ttl
$channel->queue_declare('cola_impresion_ttl', false, true, false, false, false, [
    'x-message-ttl' => ['I', 30000], // 30 segundos
    'x-dead-letter-exchange' => ['S', ''],
    'x-dead-letter-routing-key' => ['S', 'cola_eliminacion']
]);

echo "✅ Todas las colas han sido declaradas correctamente.\n";

$channel->close();
$connection->close();