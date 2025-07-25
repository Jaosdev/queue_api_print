<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\QueueService;
use OpenApi\Annotations as OA;

class PrintController
{

    public function addToQueue(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $required = ['filename', 'path', 'type', 'user', 'printerType', 'impType'];

        foreach ($required as $key) {
            if (empty($data[$key])) {
                return $this->json($response, ['error' => "Falta el campo: $key"], 400);
            }
        }

        QueueService::addJob($data);
        return $this->json($response, ['message' => 'Trabajo añadido a la cola']);
    }


    public function getQueue(Request $request, Response $response,array $args): Response
    {
        $status = $args['status'] ?? '';
        $queue = QueueService::getPendingJobs($status);
        return $this->json($response, $queue);
    }

    public function processDeleteJob(Request $request, Response $response,array $args): Response
    {
        $jobName = $args['jobName'] ?? null;

        if ($jobName === null) {
            return $this->json($response, ['error' => 'Falta el nombre del job'], 400);
        }

        $preparedJob = [
            'id' =>$jobName
        ];

        // 1) Solicitamos a rabbitMQ que ponga el job en cola de eliminacion
        if (QueueService::enqueueDeleteJob($preparedJob)) {
            return $this->json($response, [
                "mensaje" => "Parámetros recibidos",
                "params" => "Trabajo enviado para su eliminacion"
            ]);
        }
    }

  
    public function updateStatus(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (empty($data['filename']) || empty($data['status'])) {
            return $this->json($response, ['error' => 'Faltan datos'], 400);
        }

        QueueService::updateStatus($data['filename'], $data['status']);
        return $this->json($response, ['message' => 'Estado actualizado']);
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }


    public function processPrintJob(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $printer = $data['printer'] ?? 'Default';

        // 📦 Cargar configuración después de conocer el printer
        $printerConfigs = json_decode(file_get_contents(__DIR__ . '/../../config/printers.json'), true);
        $printerConfig = $printerConfigs[$printer] ?? $printerConfigs['Default'];

        if (!isset($printerConfigs[$printer])) {
            return $this->json($response, ['error' => "Configuración de impresora no encontrada: $printer"], 400);
        }

        $files = $request->getUploadedFiles();

        $impType = $data['impType'] ?? '';
        $user = $data['user'] ?? '';

        // 📐 Parámetros de impresión (con override)
        $dpi = $data['dpi'] ?? $printerConfig['dpi'];
        $width = $data['width'] ?? $printerConfig['width'];
        $height = $data['height'] ?? $printerConfig['height'];
        $printSpeed = $data['speed'] ?? $printerConfig['speed'];
        $quality = $data['quality'] ?? $printerConfig['quality'];

        // ✅ Validación básica
        if (!$impType) {
            return $this->json($response, ['error' => 'impType requerido'], 400);
        }

        $uniqueName = uniqid('doc_');
        $uploadsDir = __DIR__ . '/../../public/uploads/';
        if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);

        switch ($impType) {
            case 'html':
                $html = $data['htmlToDraw'] ?? '';
                if (empty($html)) {
                    return $this->json($response, ['error' => 'htmlToDraw requerido'], 400);
                }

                $tmpHtml = sys_get_temp_dir() . '/' . $uniqueName . '.html';
                $wrappedHtml = "<html><body>$html</body></html>";
                file_put_contents($tmpHtml, $wrappedHtml);

                $outputPath = $uploadsDir . $uniqueName . '.png';
                $cmd = "wkhtmltoimage --width $width --quality $quality " .
                    escapeshellarg($tmpHtml) . " " . escapeshellarg($outputPath);

                exec($cmd, $out, $code);
                unlink($tmpHtml);
                if ($code !== 0) {
                    return $this->json($response, ['error' => 'Falló conversión HTML'], 500);
                }

                $ext = 'png';
                $filename = $uniqueName . '.png';
                break;

            case 'image':
                if (!isset($files['file'])) {
                    return $this->json($response, ['error' => 'Archivo no recibido'], 400);
                }

                $file = $files['file'];
                if ($file->getError() !== UPLOAD_ERR_OK) {
                    return $this->json($response, ['error' => 'Error al subir imagen'], 500);
                }

                $ext = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
                $filename = $uniqueName . '.' . $ext;

                try {
                    $file->moveTo($uploadsDir . $filename);
                    echo "✅ Imagen movida: $filename\n";
                } catch (\Throwable $e) {
                    error_log("❌ Error al mover archivo: " . $e->getMessage());
                    return $this->json($response, ['error' => 'Error al guardar archivo'], 500);
                }

                break;

            default:
                return $this->json($response, ['error' => "Tipo de impresión no soportado: $impType"], 400);
        }

        $job = [
            'filename' => $filename,
            'path' => 'uploads/' . $filename,
            'type' => $ext,
            'user' => $user,
            'printerType' => $printer,
            'impType' => $impType
        ];

        \App\Services\QueueService::enqueuePrintJob($job);

        return $this->json($response, ['message' => 'Trabajo agregado a la cola', 'file' => $job]);
    }
}
