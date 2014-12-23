#Imagecache

This package allows you to create image thumbnails according to predefined presets, and store them in your Laravel public folder to serve them up without generating them on each page load.

## Installation

Using Composer, edit your `composer.json` file to require `devfactory/imagecache`.

	"require": {
		"devfactory/imagecache": "2.1.*"
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
    'method' => 'crop',
  ),
  '465x320' => array(
    'width' => 465,
    'height' => 320,
    'method' => 'resize',
    'background_color' => '#FFFFFF',
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

## Presets

When defining your presets, these are the options you can set:

| Property || Description |
|--------|----|-------------|
|`width`|*required*|The width of the generated image in pixels.|
|`height`|*required*|The height of the generated image in pixels.|
|`method`|*required*|Defines the way the image will be transformed. See the table below for accepted methods|
|`background_color`|*optional*|The color of the canvas which will be used as a background when using the method `resize`. e.g. `'#FFFFFF'`.|

The `method` property accepts the following types of transformations:

|Method|Description|
|------|-----------|
|`crop`|Will smart crop an image to make it fit the desired dimensions. It will cut content of the image off the top/bottom and sides if required to preserve the aspect ratio.|
|`resize`|Will create a canvas of the desired dimensions and will then resize the image to fit within the bounds without cropping. Images will not be upscaled if they are smaller then the dimensions. The optional property `background_color`can be used here to define the color of the canvas which the image will be placed on.|
