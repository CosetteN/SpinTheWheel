<?php
require_once realpath(__DIR__ . "/../vendor/autoload.php");

use Slim\Http\{Request, Response};

try {
    //dotenv loads .env file into server environmental variables
    $dotenv = new Dotenv\Dotenv(realpath(__DIR__ . "/../"));
    $dotenv->load();

    //load database connection
    $database = new mysqli(
        getenv("DB_HOST"),
        getenv("DB_USER"),
        getenv("DB_PASSWORD"),
        getenv("DB_NAME")
    );

    //load router
    $route = new League\Route\RouteCollection;

    $player = new SpinTheWheel\PlayerController($database);

    $route->group('/players', function($route) use ($database) {
        $player = new SpinTheWheel\PlayerController($database);
        $route->get('/{id:number}', [$player, 'read']);
        $route->put('/{id:number}/spin', [$player, 'spin']);
    })->setStrategy(new League\Route\Strategy\JsonStrategy);

    //Dispatch runs route group created above & returns response.  It actually "runs" the program.
    $request = Request::createFromGlobals($_SERVER);

    $response = $route->dispatch($request, new Response);


} catch (InvalidArgumentException $error) {
    $response = buildError(400, $error->getMessage());
    error_log($error);

} catch (mysqli_sql_exception $error) {
    $response = buildError(400, $error->getMessage());
    error_log($error);

} catch (SpinTheWheel\PlayerNotFound $error) {
    $response = buildError(404, $error->getMessage());
    error_log($error);

} catch (SpinTheWheel\AuthenticationFail $error) {
    $response = buildError(401, "Unauthorized");
    error_log($error);

} catch (Exception $error) {
    $response = buildError(500, $error->getMessage());
    error_log($error);

} finally {
    //Shut down the program if it is successful or not after sending (hopefully) a response.
    $emitter = new SpinTheWheel\Emitter;

    $emitter->send($response);

    // Close the database connection / clean up mysql
    if (isset($database)) {
        $database->close();
    }
}

function buildError(int $code, string $message) {
    $response = new Response;

    return $response->withStatus($code, $message);
}
