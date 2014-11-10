<?php namespace Devfactory\Imagecache\Facade;

use Illuminate\Support\Facades\Facade;
 
class Imagecache extends Facade {
 
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'imagecache'; }
 
}