<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $repositories = config('repository.repositories', []);
 
        // var_dump($repositories);exit;
        foreach ($repositories as $contract => $repository) {
            $this->app->bind($contract, $repository);
        }
        // var_dump($repositories);exit;
        
    }

}
