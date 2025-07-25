<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class QueueService
{
     private const QUEUE_FILE = __DIR__ . '/../../queue/print_queue.json';

    public static function loadQueue(): array
    {
        if (!file_exists(self::QUEUE_FILE)) {
            file_put_contents(self::QUEUE_FILE, json_encode([]));
        }

        $data = file_get_contents(self::QUEUE_FILE);
        return json_decode($data, true) ?? [];
    }

    public static function getPendingJobs($type = "pending"): array
    {
        $queue = self::loadQueue();
        $filteredArray = array_values(array_filter($queue, fn($job) => $job['status'] === $type));
        if (empty($filteredArray)) {
        
            return ['mensaje' => 'No se encontro cola activa'];
        } else {
            return $filteredArray;
        }
    
    }

    public static function rewriteQueue(array $queue): void
    {
        file_put_contents(self::QUEUE_FILE, json_encode(array_values($queue), JSON_PRETTY_PRINT));
    }

    public static function deleteQueue(string $jobname): bool
    {
        //Cargamos la cola de impresion del archivo JSON
        $queue = self::loadQueue();




        //Filtramos el arreglo
        // $queue = array_filter($queue, function ($job) use ($jobname) {
        //     //Obtenemos los registros por el id del queue en print_queue.json
        //     if ($job['id'] === $jobname) {
        //         //Obtenemos la ruta del archivo
        //         $path = __DIR__ . '/../../' . $job['path'];
        //         // Si la ruta existe, eliminamos el documento
        //         if (file_exists($path)) {
        //             unlink($path);
        //         }
        //         //Obtenemos el registro de el json para eliminarlo
        //         $data = 
        //         // if () {
        //         //     # code...
        //         // }


        //         return true;
        //     }

        //     return false;
            
        // });

        
        // self::saveQueue($queue);
        return true;
    }



    public static function saveQueue(array $queue): void
    {
        file_put_contents(self::QUEUE_FILE, json_encode(array_values($queue), JSON_PRETTY_PRINT));
    }

    
     /**
     * Encola un trabajo de eliminacion en RabbitMQ
     *
     * @param array $job Trabajo con datos como filename, path, user, etc.
     * @return bool
     */
    public static function enqueueDeleteJob(array &$job): bool
    {
        // Conexión con RabbitMQ
        $connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
        $channel = $connection->channel();

        // Declarar cola si no existe
        $channel->queue_declare('cola_eliminacion', false, true, false, false);

        // Crear el mensaje JSON
        $msg = new AMQPMessage(json_encode($job), [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
        ]);

        // Enviar a la cola
        $channel->basic_publish($msg, '', 'cola_eliminacion');

        // Cerrar conexiones
        $channel->close();
        $connection->close();

        return true;
    }








    /**
     * Encola un trabajo de impresión en RabbitMQ
     *
     * @param array $job Trabajo con datos como filename, path, user, etc.
     * @return bool
     */
    public static function enqueuePrintJob(array &$job): bool
    {
        // Asegurar ID único
        if (!isset($job['id'])) {
            $job['id'] = uniqid('job_');
        }

        // Conexión con RabbitMQ
        $connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
        $channel = $connection->channel();

        // Declarar cola si no existe
        $channel->queue_declare('cola_impresion', false, true, false, false);

        // Crear el mensaje JSON
        $msg = new AMQPMessage(json_encode($job), [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
        ]);

        // Enviar a la cola
        $channel->basic_publish($msg, '', 'cola_impresion');

        // Cerrar conexiones
        $channel->close();
        $connection->close();

        return true;
    }
}
