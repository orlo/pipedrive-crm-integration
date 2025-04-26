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

$container_config = [
    'pipedrive' => function (): Client {

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
    },

    'twig' => function (): Twig_Environment {
        $loader = new Twig_Loader_Filesystem(__DIR__ . '/../template');
        return new Twig_Environment($loader, []);
    },

    'logger' => function (): LoggerInterface {
        return new Logger('app', [new StreamHandler(__DIR__ . '/../var/log.txt')]);
    }
];

$app = new App($container_config);

$secret = getenv('SECRET');
if (!is_string($secret) || empty($secret)) {
    throw new \InvalidArgumentException("SECRET empty or not defined");
}

$app->add(new \SocialSignIn\PipeDriveIntegration\SignatureAuthentication($secret));

$app->get('/iframe', function (Request $request, Response $response) use ($app) {


    $id = $request->getQueryParam('id', null);
    if (!isset($id) || empty($id) || !is_numeric($id)) {
        throw new \InvalidArgumentException('Missing required param: id');
    }

    /**
     * @var Client $pipedrive
     */
    $pipedrive = $app->getContainer()->get('pipedrive');

    $pipedriveResponse = $pipedrive->get("persons/$id");
    $json = json_decode($pipedriveResponse->getBody()->getContents(), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception(json_last_error_msg());
    }


    if (!is_array($json)) {
        throw new \InvalidArgumentException("Garbage from pipedrive?");
    }

    $json = new \Zakirullin\Mess\Mess($json);

    if ($json['success']->findAsBool()) {
        throw new \Exception('Pipe Drive response unsuccessful.');
    }

    if (!$json->offsetExists('data')) {
        throw new \Exception('Pipe Drive bad response.');
    }

    $data = $json['data'];

    $user = [
        'name' => $data['name']?->findAsString() ?? 'unknown',
        'email' => $data['email'][0]['value']?->findAsString() ?? 'unknown',
        'owner' => $data['owner_id']['name']?->findAsString() ?? 'unknown',
    ];

    /**
     * @var Twig_Environment $twig
     */
    $twig = $app->getContainer()->get('twig');
    $content = $twig->render('iframe.twig', ['user' => $user]);
    $response->getBody()->write($content);
    return $response;
});

$app->get('/search', function (Request $request, Response $response) use ($app) {

    /**
     * @var string|null $query
     */
    $query = $request->getQueryParam('q', null);
    if (!isset($query) || empty($query)) {
        throw new \InvalidArgumentException('Missing required param: q');
    }

    /**
     * @var Client $pipedrive
     */
    $pipedrive = $app->getContainer()->get('pipedrive');

    $pipedriveResponse = $pipedrive->get('searchResults', ['query' => ['term' => $query, 'item_type' => 'person']]);

    $json = json_decode($pipedriveResponse->getBody()->getContents(), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception(json_last_error_msg());
    }

    if (!is_array($json)) {
        throw new \Exception("JSON parse error");
    }

    $json = new \Zakirullin\Mess\Mess($json);

    if (!$json['success']->findAsBool()) {
        throw new \Exception('Pipe Drive response unsuccessful.');
    }

    if (!$json->offsetExists('data')) {
        throw new \Exception('Pipe Drive bad response.');
    }

    if ($json['data']->findArray() === null) {
        return $response->withJson(['results' => []]);
    }

    $data = array_map(function (array $user): array {
        if (!isset($user['id']) || !isset($user['title'])) {
            throw new \Exception('Pipe Drive bad response.');
        }

        return [
            'id' => $user['id'],
            'name' => $user['title'],
        ];
    }, $json['data']->getArray());

    return $response->withJson(['results' => $data]);
});

$app->post('/webhook', function (Request $request, Response $response) use ($app) {

    /**
     * @var LoggerInterface $logger
     */
    $logger = $app->getContainer()->get('logger');

    $body = $request->getParsedBody();

    $logger->error("/webhook does nothing; deprecated?", ['body' => $body]);

    foreach (['type', 'external_id', 'text', 'social_network', 'activity_id'] as $param) {
        if (!isset($body[$param])) {
            $logger->error('Got invalid message', ['body' => $body]);
            throw new \InvalidArgumentException('Missing required param: ' . $param);
        }
    }

    $logger->debug('Got message', ['body' => $body]);

    $body = new \Zakirullin\Mess\Mess($body);

    $data = [
        'subject' => (($body['type']->findAsString() == 'incoming') ? 'Received' : 'sent') . ' message from ' . ($body['social_network']?->findAsString() ?? 'unknown'),
        'done' => 1,
        'type' => 'social',
        'person_id' => $body['external_id']?->findAsString() ?? 'unknown',
        'note' => 'Message: ' . ($body['text']?->findAsString() ?? 'unknown'),
    ];

    $logger->info('Publishing update to PipeDrive', ['data' => $data]);

    try {
        /**
         * @var Client $pipedrive
         */
        // $pipedrive = $app->getContainer()->get('pipedrive');
        // $pipedrive->post('activities', ['body' => json_encode($data)]);
    } catch (\Exception $e) {
        $logger->error("Error on POST to pipedrive->activities", ['exception' => $e, 'body' => $body]);
    }

    return $response->withJson(['success' => true]);
});

$app->run();
