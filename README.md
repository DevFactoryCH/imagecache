[![Laravel](https://img.shields.io/badge/Laravel-4.0-orange.svg?style=flat-square)](http://laravel.com)
[![Laravel](https://img.shields.io/badge/Laravel-5.0-orange.svg?style=flat-square)](http://laravel.com)
[![License](http://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](https://tldrlegal.com/license/mit-license)

# Imagecache

Laravel 4/5 package that allows you to create image thumbnails according to predefined presets, and store them in your Laravel public folder to serve them up without generating them on each page load.

## Installation

Using Composer, edit your `composer.json` file to require `devfactory/imagecache`.

##### Laravel 5

	"require": {
		"devfactory/imagecache": "3.0.*"
	}

##### Laravel 4

	"require": {
		"devfactory/imagecache": "2.1.*"
	}

Then from the terminal run

    composer update

Then in your `app/config/app.php` file register the following service providers:

```php
'Intervention\Image\ImageServiceProvider',
'Devfactory\Imagecache\ImagecacheServiceProvider',
```

And the Facade:

```php
'Imagecache'      => 'Devfactory\Imagecache\Facades\ImagecacheFacade',
```
Publish the config:

##### Laravel  5

    php artisan vendor:publish

##### Laravel 4

    php artisan config:publish devfactory/imagecache

## Usage

Define some presets in:

##### Laravel  5
`config/imagecache.presets.php`

##### Laravel  4
`app/config/packages/devfactory/imagecache/presets.php`

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

### get($filename, $preset, $args = NULL)

|Parameter|Description|
|------|-----------|
|`$filename`|The FILENAME passed to the `get()` method is relative to the `files_directory` that you set in the config file (which itself is relative to the `public_path` config). So if you pass `images/sunset.jpg`, it will actually look for `PUBLIC_PATH/uplaods/images/sunset.jpg` with the default config. I haven't tried, but if you set `public_path` and `files_directory` to `''`, you should be able to pass aa absolute path to the file.|
|`$preset`|One of the presets defined in the presets.php config file|
|`$args` *(optional)*|An array of additional properties.|

#### $args properties

|Property|Description|
|------|-----------|
|`base_dir`|If you don't want to use the base folder you have setup in the config file because you are referencing a file that might be stored in your assets, you can pass an absolute path here to override the defaults|
|`class`|A string containing any classes you want the `<img>` tag to contain|
|`alt`|The `alt` text for the image|
|`title`|The `title` text for the image|

#### Return Value:

|Property|Description|
|------|-----------|
|`src`|The URL to the image to be used inside the `<img src="">` attribute|
|`img`|The full `<img>` tag to display the image|
|`img_nosize`|The full `<img>` tag without *width* and *height* attributes for use with responsive themes|
|`path`|The full path to the image on storage|

Example usages:

```php
$image = Imagecache::get('images/sunset.jpg', 'teaser');
echo $image->img;
echo '<img src="'. $image->src .'">

// Directly in a blade template
{{ Imagecache::get('uploads/images/sunset.jpg', 'teaser')->img }}
```

You can also directly access one of the properties as such without needing to if gate the call to `get()`. If using *Laravel 5* you'll need to use the new raw notation instead of the double curly braces `{{ ... }}`.

```php
{!! Imagecache::get('uploads/images/sunset.jpg', 'teaser')->img !!}
```

If you set the `use_placeholders` variable to `TRUE` in the `imagecache.config.php` file, and your image doesn't exist or doesn't generate a cached version. Where you would normally receive an array with empty values, you can get a placeholder image matching the preset that you requested. This is very useful during developement when you might not have images for all your content.

### get_original($filename, $args = NULL)

If you don't want to apply any preset to the image, but still want to use the call to generate the `<img>` tag, accepts same parameters and works the same way as `get()`, just without `$preset`

### delete($filename)

Deletes all the cached images for each preset for the given filename.

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
