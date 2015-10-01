<?php namespace Devfactory\Imagecache;

use Illuminate\Support\ServiceProvider;

class ImagecacheServiceProvider extends ServiceProvider {

  /**
   * Indicates if loading of the provider is deferred.
   *
   * @var bool
   */
  protected $defer = false;

  /**
   * Bootstrap the application events.
   *
   * @return void
   */
  public function boot() {
    $this->publishConfig();
  }

  /**
   * Register the service provider.
   *
   * @return void
   */
  public function register() {
    $this->registerServices();
  }

  /**
   * Get the services provided by the provider.
   *
   * @return array
   */
  public function provides() {
    return ['imagecache'];
  }

	/**
   * Register the package services.
   *
   * @return void
   */
  protected function registerServices() {
    $this->app->bindShared('imagecache', function ($app) {
      return new Imagecache();
    });
  }

  /**
   * Publish the package configuration
   */
  protected function publishConfig() {
    $this->publishes([
      __DIR__ . '/config/config.php' => config_path('imagecache.config.php'),
      __DIR__ . '/config/presets.php' => config_path('imagecache.presets.php'),
    ]);
  }

}
