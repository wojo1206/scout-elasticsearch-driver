<?php

namespace ScoutElastic;

use InvalidArgumentException;
use Elasticsearch\ClientBuilder;
use Laravel\Scout\EngineManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use ScoutElastic\Console\ElasticMigrateCommand;
use ScoutElastic\Console\SearchRuleMakeCommand;
use ScoutElastic\Console\ElasticIndexDropCommand;
use ScoutElastic\Console\ElasticIndexCreateCommand;
use ScoutElastic\Console\ElasticIndexUpdateCommand;
use ScoutElastic\Console\SearchableModelMakeCommand;
use ScoutElastic\Console\ElasticUpdateMappingCommand;
use ScoutElastic\Console\IndexConfiguratorMakeCommand;

class ScoutElasticServiceProvider extends ServiceProvider
{
    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/scout_elastic.php' => config_path('scout_elastic.php'),
        ]);

        $this->commands([
            // make commands
            IndexConfiguratorMakeCommand::class,
            SearchableModelMakeCommand::class,
            SearchRuleMakeCommand::class,

            // elastic commands
            ElasticIndexCreateCommand::class,
            ElasticIndexUpdateCommand::class,
            ElasticIndexDropCommand::class,
            ElasticUpdateMappingCommand::class,
            ElasticMigrateCommand::class,
        ]);

        $this
            ->app
            ->make(EngineManager::class)
            ->extend('elastic', function () {
                $indexerType = config('scout_elastic.indexer', 'single');
                $updateMapping = config('scout_elastic.update_mapping', true);

                $indexerClass = '\\ScoutElastic\\Indexers\\'.ucfirst($indexerType).'Indexer';

                if (! class_exists($indexerClass)) {
                    throw new InvalidArgumentException(sprintf(
                        'The %s indexer doesn\'t exist.',
                        $indexerType
                    ));
                }

                return new ElasticEngine(new $indexerClass(), $updateMapping);
            });
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this
            ->app
            ->singleton('scout_elastic.client', function () {
                $config = Config::get('scout_elastic.client');

                $this->handleConfig($config);

                return ClientBuilder::fromConfig($config);
            });
    }

    /**
     * Undocumented function
     *
     * @param [type] $config
     * @return void
     */
    private function handleConfig(&$config)
    {
        foreach ($config['hosts'] as $host) {
            if (isset($host['aws']) && $host['aws']) {
                $handler = function(array $request) use ($host) {
                    $psr7Handler = \Aws\default_http_handler();
                    $signer = new \Aws\Signature\SignatureV4('es', $host['aws_region']);
                    $request['headers']['Host'][0] = parse_url($request['headers']['Host'][0])['host'];
                    // Create a PSR-7 request from the array passed to the handler
                    $psr7Request = new \GuzzleHttp\Psr7\Request(
                        $request['http_method'],
                        (new \GuzzleHttp\Psr7\Uri($request['uri']))
                            ->withScheme($request['scheme'])
                            ->withHost($request['headers']['Host'][0]),
                        $request['headers'],
                        $request['body']
                    );

                    // Sign the PSR-7 request with credentials from the environment
                    $signedRequest = $signer->signRequest(
                        $psr7Request,
                        new \Aws\Credentials\Credentials($host['aws_key'], $host['aws_secret'])
                    );

                    // Send the signed request to Amazon ES
                    /** @var \Psr\Http\Message\ResponseInterface $response */
                    $response = $psr7Handler($signedRequest)
                        ->then(function(\Psr\Http\Message\ResponseInterface $response) {
                            return $response;
                        }, function($error) {
                            return $error['response'];
                        })
                        ->wait();

                    // Convert the PSR-7 response to a RingPHP response
                    return new \GuzzleHttp\Ring\Future\CompletedFutureArray([
                        'status'  => $response->getStatusCode(),
                        'headers' => $response->getHeaders(),
                        'body' => $response->getBody()->detach(),
                        'transfer_stats' => ['total_time' => 0],
                        'effective_url'  => (string) $psr7Request->getUri(),
                    ]);
                };

                $config['handler'] = $handler;
            }
        }
    }
}
