<?php

require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

$app = new App();

$container = $app->getContainer();

$container['pipedrive'] = function () {

    $stack = new HandlerStack();
    $stack->setHandler(new CurlHandler());

    $stack->push(Middleware::mapRequest(function (RequestInterface $request) {
        $uri = $request->getUri();

        $queryParams = [];
        parse_str($uri->getQuery(), $queryParams);
        $queryParams['api_token'] = getenv('PIPE_DRIVE_API_KEY');

        return $request->withUri($uri->withQuery(http_build_query($queryParams)));
    }));

    $client = new Client([
        'base_uri' => 'https://api.pipedrive.com/v1/',
        'timeout' => 2.0,
        'handler' => $stack,
    ]);

    return $client;
};

$container['twig'] = function () {
    $loader = new Twig_Loader_Filesystem(__DIR__ . '/../template');
    return new Twig_Environment($loader, []);
};

$container['logger'] = function () {
    return new Logger('app', [new StreamHandler(__DIR__ . '/../var/log.txt')]);
};

$app->add(new \SocialSignIn\PipeDriveIntegration\SignatureAuthentication(getenv('SECRET')));

$app->get('/iframe', function (Request $request, Response $response) use ($app) {

    $id = $request->getQueryParam('id', null);
    if (!isset($id) || empty($id)) {
        throw new \InvalidArgumentException('Missing required param: id');
    }

    /* @var $pipedrive Client */
    $pipedrive = $app->getContainer()->get('pipedrive');

    $pipedriveResponse = $pipedrive->get('persons/' . $id);
    $json = json_decode($pipedriveResponse->getBody()->getContents(), true);
    if (json_last_error_msg() != JSON_ERROR_NONE) {
        throw new \Exception(json_last_error_msg());
    }

    if (!isset($json['success']) || $json['success'] != true) {
        throw new \Exception('Pipe Drive response unsuccessful.');
    }

    if (!isset($json['data'])) {
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

$app->get('/search', function (Request $request, Response $response) use ($app) {

    $query = $request->getQueryParam('q', null);
    if (!isset($query) || empty($query)) {
        throw new \InvalidArgumentException('Missing required param: q');
    }

    /* @var $pipedrive Client */
    $pipedrive = $app->getContainer()->get('pipedrive');

    $pipedriveResponse = $pipedrive->get('searchResults', ['query' => ['term' => $query, 'item_type' => 'person']]);

    $json = json_decode($pipedriveResponse->getBody()->getContents(), true);
    if (json_last_error_msg() != JSON_ERROR_NONE) {
        throw new \Exception(json_last_error_msg());
    }

    if (!isset($json['success']) || $json['success'] != true) {
        throw new \Exception('Pipe Drive response unsuccessful.');
    }

    if (!array_key_exists('data', $json)) {
        throw new \Exception('Pipe Drive bad response.');
    }

    if ($json['data'] === null) {
        return $response->withJson(['results' => []]);
    }

    $data = array_map(function ($user) {
        if (!isset($user['id']) || !isset($user['title'])) {
            throw new \Exception('Pipe Drive bad response.');
        }

        return [
            'id' => $user['id'],
            'name' => $user['title'],
        ];
    }, $json['data']);

    return $response->withJson(['results' => $data]);
});

$app->post('/webhook', function (Request $request, Response $response) use ($app) {

    /* @var $logger LoggerInterface */
    $logger = $app->getContainer()->get('logger');

    $body = $request->getParsedBody();

    $logger->error("/webhook does nothing; deprecated?", [$body]);

    foreach (['type', 'external_id', 'text', 'social_network', 'activity_id'] as $param) {
        if (!isset($body[$param])) {
            $logger->error('Got invalid message', $body);
            throw new \InvalidArgumentException('Missing required param: ' . $param);
        }
    }

    $logger->debug('Got message', $body);

    $data = [
        'subject' => (($body['type'] == 'incoming' ? 'Received' : 'sent') . ' message from ' . $body['social_network']),
        'done' => 1,
        'type' => 'social',
        'person_id' => $body['external_id'],
        'note' => 'Message: ' . $body['text'],
    ];

    $logger->info('Publishing update to PipeDrive', $data);

    /* @var $pipedrive Client */
    $pipedrive = $app->getContainer()->get('pipedrive');

    try {
        # $pipedrive->post('activities', ['body' => json_encode($data)]);
    } catch (\Exception $e) {
        $logger->error($e, $body);
    }

    return $response->withJson(['success' => true]);
});

$app->run();
