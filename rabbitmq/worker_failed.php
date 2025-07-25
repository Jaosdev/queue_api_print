<?php
require_once __DIR__ . '/../vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;

$queueFile   = __DIR__ . '/../queue/print_queue.json';
$uploadsDir  = __DIR__ . '/../public/uploads';       
$uploadsBase = 'uploads/';

function eliminarTrabajo($job): void {
    global $queueFile, $uploadsDir, $uploadsBase;

    $id = $job['id'] ?? null;
    $file = '';
    echo "Esta entrando a eliminar trabajo: $id \n";
    var_dump($job);
    // echo "Parece que entro aqui: {$job}";

    echo "Esta es la ruta: $queueFile \n";

    if (file_get_contents($queueFile)) {
        echo "Se leyó correctamente el documento";
        $file = file_get_contents($queueFile);
    }

    $data = json_decode($file,true);
    $obtainedPath = $uploadsDir."/".$data[$id]['filename'];
    $isDeleted = false;

    echo "Se imprime la data: \n";
    // var_dump($data[$id]);

    if (!empty($data[$id])) {
        echo "Entro a donde hay datos";
        unset($data[$id]);

        if (is_writable($obtainedPath)) {
            unlink($obtainedPath);
            echo "Se elimino la imagen";
        }else{
            echo "No se elimino la imagen";
        }

       echo "esta es la data path $obtainedPath";

        $isDeleted = true;
    }else{
        echo "No paso nada";
    }

    if ($isDeleted) {
        echo "Se elimino el registro con exito";
        file_put_contents($queueFile,json_encode($data));
        $isDeleted = false;
    }else{
        echo "No se encontro el job";
    }
  
}


// --- Configuración y consumo de RabbitMQ (igual que antes) ---
$connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
$channel    = $connection->channel();
$channel->queue_declare('cola_eliminacion', false, true, false, false);

echo "🧼 Escuchando trabajos en cola_eliminacion...\n";

$callback = function ($msg) {
    $job = json_decode($msg->body, true);
    eliminarTrabajo($job);
};

$channel->basic_consume('cola_eliminacion', '', false, true, false, false, $callback);
while ($channel->is_consuming()) {
    $channel->wait();
}
