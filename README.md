#Imagecache

This package allows you to create image thumbnails according to predefined presets, and store them in your Laravel public folder to serve them up without generating them on each page load.

## Installation

Using Composer, edit your `composer.json` file to require `devfactory/imagecache`.

	"require": {
		"devfactory/imagecache": "2.0.*"
	}

Then from the terminal run

    composer update

Then in your `app/config/app.php` file register the following service providers:

    'Intervention\Image\ImageServiceProvider',
    'Devfactory\Imagecache\ImagecacheServiceProvider',

And the Facade:

    'Imagecache'      => 'Devfactory\Imagecache\Facades\ImagecacheFacade',

Publish the config:

    php artisan config:publish devfactory/imagecache

## Usage

Define some presets in `app/config/packages/devfactory/imagecache/presets.php`

```php
<?php
return array(
  'teaser' => array(
    'width' => 150,
    'height' => 100,
    'aspect_ratio' => 1.5,
  ),
  '465x320' => array(
    'width' => 465,
    'height' => 320,
    'aspect_ratio' => 465/320,
  ),
);
```

Then call the `get(FILENAME, PRESET)` method:

```php
<?php
$image = Imagecache::get('uploads/images/sunset.jpg', 'teaser');
```

`$image` will now contain an stdClass with the following properties:

 - `src`
The URL to the image to be used inside the `<img src="">` attribute
 - `img`
The full `<img>` tag to display the image
 - `path`
The full path to the image on storage
