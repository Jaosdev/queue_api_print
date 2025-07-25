<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Annotations as OA;

class LogController
{
    /**
     * @OA\Get(
     *     path="/logs/impresiones",
     *     summary="Obtener el log de trabajos impresos",
     *     @OA\Response(
     *         response=200,
     *         description="Contenido del log en texto plano"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Archivo no encontrado"
     *     )
     * )
     */
    public function getPrintQueueLog(Request $request, Response $response): Response
    {
        return $this->serveLog($response, '/var/log/supervisor/worker.log');
    }

    /**
     * @OA\Get(
     *     path="/logs/eliminaciones",
     *     summary="Obtener el log de trabajos eliminados",
     *     @OA\Response(response=200, description="Contenido del log"),
     *     @OA\Response(response=404, description="Archivo no encontrado")
     * )
     */
    public function getDeleteQueueLog(Request $request, Response $response): Response
    {
        return $this->serveLog($response, '/var/log/supervisor/worker_eliminacion.log');
    }

    /**
     * @OA\Get(
     *     path="/logs/errores-impresion",
     *     summary="Obtener el log de errores en impresión",
     *     @OA\Response(response=200, description="Contenido del log"),
     *     @OA\Response(response=404, description="Archivo no encontrado")
     * )
     */
    public function getErrorQueueLog(Request $request, Response $response): Response
    {
        return $this->serveLog($response, '/var/log/supervisor/worker_error.log');
    }

    /**
     * @OA\Get(
     *     path="/logs/errores-eliminacion",
     *     summary="Obtener el log de errores en eliminación",
     *     @OA\Response(response=200, description="Contenido del log"),
     *     @OA\Response(response=404, description="Archivo no encontrado")
     * )
     */
    public function getErrorDeleteLog(Request $request, Response $response): Response
    {
        return $this->serveLog($response, '/var/log/supervisor/worker_eliminacion_error.log');
    }

    /**
     * @OA\Get(
     *     path="/logs/worker",
     *     summary="Obtener el log completo del worker (para pruebas)",
     *     @OA\Response(response=200, description="Contenido del log"),
     *     @OA\Response(response=404, description="Archivo no encontrado")
     * )
     */
    public function getWorkerLog(Request $request, Response $response): Response
    {
        return $this->serveLog($response, '/var/log/supervisor/worker.log');
    }

    /**
     * Método reutilizable para leer archivos de log.
     * No se expone directamente como endpoint.
     */
    private function serveLog(Response $response, string $logPath): Response
    {
        if (!file_exists($logPath)) {
            $response->getBody()->write(json_encode(['error' => 'Archivo de log no encontrado']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $content = file_get_contents($logPath);
        $response->getBody()->write($content);
        return $response->withHeader('Content-Type', 'text/plain');
    }
}
