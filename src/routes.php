<?php
use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Controllers\PrintController;
use App\Controllers\LogController;
use OpenApi\Generator as OA;

return function (App $app) {

    // Ruta para servir la especificación OpenAPI
   $app->get('/openapi.yaml', function($req,$res){
    $yaml = file_get_contents(__DIR__ . '/../openapi.yaml');
    // convertimos YAML a JSON:
    $data = \Symfony\Component\Yaml\Yaml::parse($yaml);
    $json = json_encode($data, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    $res->getBody()->write($json);
    return $res->withHeader('Content-Type','application/json');
    });

    // Ruta de prueba
    $app->get('/ping', function (Request $request, Response $response) {
        $response->getBody()->write(json_encode(['pong' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Todas tus demás rutas...
    $app->post('/print', [PrintController::class, 'processPrintJob']);
    $app->group('/print', function ($group) {
        $group->get('/queue/{status}',   [PrintController::class, 'getQueue']);
        $group->post('/queue',           [PrintController::class, 'addToQueue']);
        $group->delete('/queue/{jobName}', [PrintController::class, 'processDeleteJob']);
        $group->post('/update',          [PrintController::class, 'updateStatus']);
    });
    $app->group('/logs', function ($group) {
        $group->get('/impresiones',        [LogController::class, 'getPrintQueueLog']);
        $group->get('/eliminaciones',      [LogController::class, 'getDeleteQueueLog']);
        $group->get('/errores-impresion',  [LogController::class, 'getErrorQueueLog']);
        $group->get('/errores-eliminacion',[LogController::class, 'getErrorDeleteLog']);
    });
    $app->get('/logs/worker', [LogController::class, 'getWorkerLog']);
};
