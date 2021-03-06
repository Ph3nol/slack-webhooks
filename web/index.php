<?php

require_once __DIR__."/../vendor/autoload.php";

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Guzzle\Http\Client;

$app = new Silex\Application();
$app->register(
    new DerAlex\Silex\YamlConfigServiceProvider(
        __DIR__ . '/../app/config/parameters.yml'
    )
);

$app->post('/slack-proxy/code-push', function(Request $request) use ($app) {
    $slackUrl = sprintf('https://%s.slack.com', $app['config']['slack']['team']);
    $content  = json_decode($request->getContent());
    $fields   = array();
    foreach ($content->commits as $key => $commit) {
        $fields[] = [
            'title' => $commit->message,
            'value' => sprintf(
                '<%s|%s> - %s',
                $commit->url,
                substr($commit->id, 0, 9),
                $commit->author->name
            ),
        ];
    }

    $message = sprintf(
        $app['config']['slack']['push_message'],
        $content->repository->homepage,
        $content->repository->name,
        $content->total_commits_count
    );

    $params = [
        'channel'  => '#'.$app['config']['slack']['channel'],
        'username' => $content->user_name,
        "fallback" => $message,
        'text'     => $message,
        'fields'   => $fields,
        'color'    => $app['config']['slack']['color'],
    ];

    $client  = new Client($slackUrl);
    $request = $client->post(
        '/services/hooks/incoming-webhook?token='.$app['config']['gitlab']['token']
    );
    $request->setBody(json_encode($params), 'application/json');

    $response = $request->send();

    return $response;
});

$app->run();
