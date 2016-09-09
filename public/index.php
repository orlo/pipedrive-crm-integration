<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

$app = new App();

$container = $app->getContainer();

$container['pipedrive'] = function() {

    $stack = new \GuzzleHttp\HandlerStack();
    $stack->setHandler(new \GuzzleHttp\Handler\CurlHandler());

    $stack->push(\GuzzleHttp\Middleware::mapRequest(function(\Psr\Http\Message\RequestInterface $request) {
        $uri = $request->getUri();

        $queryParams = [];
        parse_str($uri->getQuery(), $queryParams);
        $queryParams['api_token'] = getenv('PIPE_DRIVE_API_KEY');

        return $request->withUri($uri->withQuery(http_build_query($queryParams)));
    }));

    $client = new \GuzzleHttp\Client([
        'base_uri' => 'https://api.pipedrive.com/v1/',
        'timeout'  => 2.0,
        'handler' => $stack,
    ]);

    return $client;
};

$container['twig'] = function() {
    $loader = new Twig_Loader_Filesystem(__DIR__ . '/../template');
    return new Twig_Environment($loader, []);
};

$app->add(function(Request $request, Response $response, $next) {
    $sig = $request->getQueryParam('sig', null);
    $expires = $request->getQueryParam('expires', null);

    if ($sig === null || $expires === null || $expires < time()) {
        return $response->withStatus(401);
    }

    $params = $request->getQueryParams();
    unset($params['sig']);
    ksort($params);

    if (hash_hmac('sha256', join(':', array_values($params)), getenv('SECRET')) !== $sig) {
        return $response->withStatus(401);
    }

    return $next($request, $response);
});

$app->get('/iframe', function(Request $request, Response $response) use ($app) {

    $id = $request->getQueryParam('id', null);
    if (! isset($id) || empty($id)) {
        throw new \InvalidArgumentException('Missing required param: id');
    }

    /* @var $pipedrive \GuzzleHttp\Client */
    $pipedrive = $app->getContainer()->get('pipedrive');

    $pipedriveResponse = $pipedrive->get('persons/' . $id);
    $json = json_decode($pipedriveResponse->getBody()->getContents(), true);
    if (json_last_error_msg() != JSON_ERROR_NONE) {
        throw new \Exception(json_last_error_msg());
    }

    if (! isset($json['success']) || $json['success'] != true) {
        throw new \Exception('Pipe Drive response unsuccessful.');
    }

    if (! isset($json['data'])) {
        throw new \Exception('Pipe Drive bad response.');
    }

    $user = [
        'name' => (isset($json['data']['name']) ? $json['data']['name'] : 'unknown'),
        'email' => (isset($json['data']['email'][0]['value']) ? $json['data']['email'][0]['value'] : 'unknown'),
        'owner' => (isset($json['data']['owner_id']['name']) ? $json['data']['owner_id']['name'] : 'unknown'),
    ];

    $content = $app->getContainer()->get('twig')->render('iframe.twig', ['user' => $user]);
    $response->getBody()->write($content);
    return $response;
});

$app->get('/search', function(Request $request, Response $response) use ($app) {

    $query = $request->getQueryParam('q', null);
    if (! isset($query) || empty($query)) {
        throw new \InvalidArgumentException('Missing required param: q');
    }

    /* @var $pipedrive \GuzzleHttp\Client */
    $pipedrive = $app->getContainer()->get('pipedrive');

    $pipedriveResponse = $pipedrive->get('searchResults', ['query' => ['term' => $query, 'item_type' => 'person']]);

    $json = json_decode($pipedriveResponse->getBody()->getContents(), true);
    if (json_last_error_msg() != JSON_ERROR_NONE) {
        throw new \Exception(json_last_error_msg());
    }

    if (! isset($json['success']) || $json['success'] != true) {
        throw new \Exception('Pipe Drive response unsuccessful.');
    }

    if (! isset($json['data'])) {
        throw new \Exception('Pipe Drive bad response.');
    }

    $data = array_map(function($user) {
        if (! isset($user['id']) || ! isset($user['title'])) {
            throw new \Exception('Pipe Drive bad response.');
        }

        return [
            'id' => $user['id'],
            'name' => $user['title'],
        ];
    }, $json['data']);

    return $response->withJson(['results' => $data]);
});

$app->run();