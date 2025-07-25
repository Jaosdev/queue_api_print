<?php
use Slim\Factory\AppFactory;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpMethodNotAllowedException;

//Cargamos el autoload de composer
require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

// Se agregan las funciones de routeamiento
$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// Handler para rutas no encontradas (404)
$errorMiddleware->setErrorHandler(HttpNotFoundException::class, function (
    Psr\Http\Message\ServerRequestInterface $request,
    Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails
) use ($app) {
    $response = $app->getResponseFactory()->createResponse();
    $response->getBody()->write(json_encode([
        "error" => "Bad request, ruta no encontrada"
    ]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
});

// Handler para método no permitido (405)
$errorMiddleware->setErrorHandler(HttpMethodNotAllowedException::class, function (
    Psr\Http\Message\ServerRequestInterface $request,
    Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails
) use ($app) {
    $response = $app->getResponseFactory()->createResponse();
    $response->getBody()->write(json_encode([
        "error" => "Bad request, método no permitido"
    ]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(405);
});

// Cargamos las rutas..
(require __DIR__ . '/../src/routes.php')($app);

$app->run();
