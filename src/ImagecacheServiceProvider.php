<?php namespace Devfactory\Imagecache;

use Illuminate\Support\ServiceProvider;

class ImagecacheServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;


	public function boot()
	{
		$this->package('devfactory/imagecache', 'imagecache', __DIR__);
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app['imagecache'] = $this->app->share(function($app)
    {
        return new Imagecache;
    });
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array('imagecache');
	}

}
