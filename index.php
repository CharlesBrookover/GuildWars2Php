<?php

declare(strict_types=1);

/**
 * A new PHP page
 *
 * Date: 2/18/2023
 *
 * @author  Charles Brookover
 * @license MIT
 * @version 0.0.1
 */

use App\Application\Handlers\HttpErrorHandler;
use App\Application\Handlers\ShutdownHandler;
use App\Application\Settings\SettingsInterface;
use DI\Bridge\Slim\Bridge;
use DI\ContainerBuilder;
use Slim\Factory\ServerRequestCreatorFactory;
use Slim\ResponseEmitter;

require_once __DIR__ . '/vendor/autoload.php';


$containerBuilder = new ContainerBuilder();
$container        = $containerBuilder->build();

// Set up settings
$settings = require __DIR__ . '/app/settings.php';
$settings($containerBuilder);

// Set up dependencies
$dependencies = require __DIR__ . '/app/dependencies.php';
$dependencies($containerBuilder);

// Set up repositories
$repositories = require __DIR__ . '/app/repositories.php';
$repositories($containerBuilder);

$app              = Bridge::create($container);
$callableResolver = $app->getCallableResolver();

// Register middleware
$middleware = require __DIR__ . '/app/middleware.php';
$middleware($app);

// Register routes
$routes = require __DIR__ . '/app/routes.php';
$routes($app);

/** @var SettingsInterface $settings */
$settings = $container->get(SettingsInterface::class);

$displayErrorDetails = $settings->get('displayErrorDetails');
$logError            = $settings->get('logError');
$logErrorDetails     = $settings->get('logErrorDetails');


// Create Request object from globals
$serverRequestCreator = ServerRequestCreatorFactory::create();
$request              = $serverRequestCreator->createServerRequestFromGlobals();

// Create Error Handler
$responseFactory = $app->getResponseFactory();
$errorHandler    = new HttpErrorHandler($callableResolver, $responseFactory);

// Create Shutdown Handler
$shutdownHandler = new ShutdownHandler($request, $errorHandler, $displayErrorDetails);
register_shutdown_function($shutdownHandler);


// Add Routing Middleware
$app->addRoutingMiddleware();

// Add Body Parsing Middleware
$app->addBodyParsingMiddleware();

// Add Error Middleware
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, $logError, $logErrorDetails);
$errorMiddleware->setDefaultErrorHandler($errorHandler);

// Run App & Emit Response
$response        = $app->handle($request);
$responseEmitter = new ResponseEmitter();
$responseEmitter->emit($response);