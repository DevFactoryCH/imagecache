<?php namespace Devfactory\Imagecache;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use File;

class Imagecache
{

    /**
     * The passed file to create a cached image of.
     * Can be an object, array or string
     *
     * @var mixed
     */
    protected $file;

    /**
     * The image filename
     *
     * @var string
     **/
    protected $file_name;

    /**
     * The preset sent by the call
     *
     * @var string
     **/
    protected $preset;

    /**
     * The laravel public directory
     *
     * @var string
     */
    protected $public_path;

    /**
     * The URI relative to the public path where images are stored/uploaded
     *
     * @var string
     **/
    protected $upload_uri;

    /**
     * The absolute path where images are stored/uploaded
     *
     * @var string
     **/
    protected $upload_path;

    /**
     * The URI relative to the public path where cached images are to be stored
     *
     * @var string
     **/
    protected $imagecache_uri;

    /**
     * The absolute path where cached images are to be stored
     *
     * @var string
     **/
    protected $imagecache_path;

    /**
     * The filename of the file relative to the file storage directory ($this->upload_path)
     *
     * @var string
     */
    protected $filename_field;

    /**
     * The class to add to the image
     *
     * @var string
     */
    protected $class;

    /**
     * The alt text for the image
     *
     * @var string
     */
    protected $alt;

    /**
     * The title text for the image
     *
     * @var string
     */
    protected $title;

    /**
     * The quality for the generated image
     *
     * @var string
     */
    protected $quality;

    /**
     * Whether or not to use placeholders
     *
     * @var boolen
     */
    protected $use_placeholders;

    /**
     * The laravel request object
     *
     * @var Illuminate\Http\Request
     */
    protected $request;

    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        $this->public_path = $this->sanitizeDirectoryName(config('imagecache.config.public_path'), true);

        $this->upload_uri = $this->sanitizeDirectoryName(config('imagecache.config.files_directory'), true);
        $this->upload_path = $this->public_path . $this->upload_uri;

        $this->imagecache_uri = $this->sanitizeDirectoryName(config('imagecache.config.imagecache_directory'));
        $this->imagecache_path = $this->public_path . $this->imagecache_uri;

        $this->filename_field = config('imagecache.config.filename_field');
        $this->quality = config('imagecache.config.quality', 90);
        $this->use_placeholders = config('imagecache.config.use_placeholders', false);

        $this->request = request();
    }

    /**
     * Cleanup paths so that they all have a trailing slash, and
     * optional leading slash
     *
     * @param $name string
     *  The path to sanitize
     *
     * @param $keep_leading_slash bool
     *  TRUE to keep the leading /, otherwise FALSE
     *
     * @return string
     *  The sanitized path
     */
    protected function sanitizeDirectoryName($name, $keep_leading_slash = false)
    {
        if (!$keep_leading_slash) {
            $name = ltrim($name, '/\\');
        }

        return rtrim($name, '/\\') . '/';
    }

    /**
     * Called by script to get the image information, performs all required steps
     *
     * @param $file mixed
     *  Object/array/string to check for a filename
     *
     * @param $preset string
     *   The name of the preset, must be one of the presets in config/presets.php
     *
     * @return array
     *  Containing the cached image src, img, and others
     */
    public function get($file, $preset, $args = null)
    {
        $this->file = $file;

        if (!$this->setFilename()) {
            return $this->image_element_empty();
        }

        if (!$this->setPreset($preset)) {
            return $this->image_element_empty();
        }

        $this->setupArguments($args);

        if (!$this->image_exists()) {
            return $this->image_element_empty();
        }

        if (!$this->is_image()) {
            return $this->image_element_empty();
        }

        if ($this->is_svg()) {
            return (object) $this->image_element_original();
        }

        if (!$this->generate_cached_image()) {
            return $this->image_element_empty();
        }

        return (object) $this->image_element();
    }

    /**
     * Get the imagecache array for an empty image
     *
     * @param $file mixed
     *  Object/array/string to check for a filename
     *
     * @return array
     *  An array containing to different image setups
     */
    public function get_original($file, $args = null)
    {
        $this->file = $file;

        if (!$this->setFilename()) {
            return $this->image_element_empty();
        }

        $this->setupArguments($args);

        return $this->image_element_original();
    }

    /**
     * Extract preset information from config file according to that chosen by the user
     *
     * @param string $preset
     *  The preset string
     *
     * @return bool
     */
    protected function setPreset($preset)
    {
        if (!$this->validate_preset($preset)) {
            return false;
        }

        $presets = $this->get_presets();
        $this->preset = (object) $presets[$preset];
        $this->preset->name = $preset;

        return true;
    }

    /**
     * Take the passed file and check it to retrieve a filename
     *
     * @return bool
     *  TRUE if $this->filename set, otherwise FALSE
     */
    protected function setFilename()
    {
        if (is_object($this->file)) {
            if (!isset($this->file->{$this->filename_field})) {
                return false;
            }

            $this->file_name = $this->file->{$this->filename_field};
            return true;
        }

        if (is_array($this->file)) {
            $this->file_name = $field[$this->filename_field];
            return true;
        }

        if (is_string($this->file)) {
            $this->file_name = $this->file;
            return true;
        }

        return false;
    }

    /**
     * Parse the passed arguments and set the instance variables
     *
     * @param $args array
     *  An array of optional parameters as a key => value pair
     *
     * @return void
     */
    protected function setupArguments($args)
    {
        $this->upload_path = (isset($args['base_dir']) ? $args['base_dir'] : $this->public_path . $this->upload_uri);
        $this->class = (isset($args['class']) ? $args['class'] : null);
        $this->alt = isset($args['alt']) ? $args['alt'] : $this->parseAlt();
        $this->title = isset($args['title']) ? $args['title'] : $this->parseTitle();
    }

    /**
     * If the file received is an object or array, check if the 'alt' field is set
     *
     * @return string
     */
    protected function parseAlt()
    {
        if (is_object($this->file)) {
            if (isset($this->file->alt)) {
                return $this->file->alt;
            }
        }

        if (is_array($this->file)) {
            if (isset($this->file['alt'])) {
                return $this->file['alt'];
            }
        }

        return '';
    }

    /**
     * If the file received is an object or array, check if the 'title' field is set
     *
     * @return string
     */
    protected function parseTitle()
    {
        if (is_object($this->file)) {
            if (isset($this->file->title)) {
                return $this->file->title;
            }
        }

        if (is_array($this->file)) {
            if (isset($this->file['title'])) {
                return $this->file['title'];
            }
        }

        return '';
    }

    /**
     * Delete each imagecache for the given image
     *
     * @param file_name
     *
     * @return
     */
    public function delete($file_name)
    {
        $this->file_name = $file_name;

        $this->delete_image();
    }

    /**
     * Check that preset os valid and described in the config file
     *
     * @return bool
     */
    protected function validate_preset($preset)
    {
        if (in_array($preset, array_keys($this->get_presets()))) {
            return true;
        }

        return false;
    }

    /**
     * Check if the image given exists on the server
     *
     * @return bool
     */
    protected function image_exists()
    {
        try {
            if (Storage::exists($this->file_name)) {
                return true;
            }
        } catch (Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * Checks if we have an SVG image.
     *
     * @return
     *   TRUE if SVG and FALSE otherwise
     */
    protected function is_svg()
    {
        if (str_contains(Storage::mimeType($this->file_name), 'image/svg+xml')) {
            return true;
        }

        return false;
    }

    /**
     * Checks if we have an image.
     *
     * @return
     *   TRUE if Image and FALSE otherwise
     */
    protected function is_image()
    {
        if (str_contains(Storage::mimeType($this->file_name), 'image')) {
            return true;
        }

        return false;
    }

    /**
     * Generate the imagecache if one doesn't already exist
     * Uses the MY_Image_lib library by Jens Segers:
     * * http://www.jenssegers.be
     *
     * @return bool
     */
    protected function generate_cached_image()
    {
        $cached_image = $this->get_cached_image_path();

        if (Storage::exists($cached_image)) {
            return true;
        }

        // $path_info = pathinfo($cached_image);

        // if (!is_dir($path_info['dirname'])) {
        //     mkdir($path_info['dirname'], 0777, true);
        // }

        if (!($image = $this->buildImage())) {
            return false;
        }

        // $image->encode(null, $this->quality)
        if (Storage::put($cached_image, $image->stream()->__toString(), 'public')) {//$image->save($cached_image, $this->quality)) {
            return true;
        }

        return false;
    }

    /**
     * Generates and calls the correct method for the generation method used in preset
     *
     * @return mixed
     *  FALSE if no matching method, otherwise the Image Object
     */
    protected function buildImage()
    {
        $method = 'buildImage'. ucfirst($this->preset->method);
        if (method_exists($this, $method)) {
            return $this->{$method}();
        }

        return false;
    }

    /**
     * Resize the image, contraining the aspect ratio and size
     *
     * @return Image Object
     */
    protected function buildImageResize()
    {
        $image = Image::make(Storage::get($this->file_name))->orientate();

        $image->resize($this->preset->width, $this->preset->height, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });

        //if background_color is not set, do not resizeCanvas with black color, to avoid black zones in images.
        if (isset($this->preset->background_color)) {
            $image->resizeCanvas($this->preset->width, $this->preset->height, 'center', false, $this->preset->background_color);
        }

        return $image;
    }

    /**
     * Crop the image to the given size and aspèect ration, ignoring upsize or aspect ratio constraints
     *
     * @return
     */
    protected function buildImageCrop()
    {
        $image = Image::make(Storage::get($this->file_name))->orientate();

        if ($this->preset->width == 0) {
            $image->heighten($this->preset->height);
        } elseif ($this->preset->height == 0) {
            $image->widen($this->preset->width);
        } else {
            $image->fit($this->preset->width, $this->preset->height);
        }

        return $image;
    }

    /**
     * Create the path of the imagecache for the given image and preset
     *
     * @return string
     */
    protected function get_cached_image_path()
    {
        return $this->imagecache_uri . $this->preset->name .'/'. $this->file_name;
    }

    /**
     * Create the path of the imagecache for the given image and preset
     *
     * @return string
     */
    protected function get_original_image_path()
    {
        return $this->file_name;
    }

    /**
     * The full path to the cached image relative to the file system root
     *
     * @return string
     */
    protected function get_full_path_to_cached_image()
    {
        return $this->public_path . $this->get_cached_image_path();
    }

    /**
     * Extract the preset imformation for the given preset from the config file
     *
     * @return array
     */
    protected function get_preset()
    {
        return $this->preset;
    }

    /**
     * Get all the presets
     *
     * @return array
     */
    protected function get_presets()
    {
        return config('imagecache.presets');
    }

    /**
     * Get the class="" string of the image
     *
     * @return String
     */
    protected function get_class()
    {
        if ($this->class) {
            return ' class="'. $this->class .'"';
        }

        return '';
    }

    /**
     * Generate the URL taking into account HTTP and HTTPS of the server
     *
     * @return String
     */
    protected function generateUrl($image_path)
    {
        return Storage::url($image_path);
    }

    /**
     * Generate the image element and src to use in the calling script
     *
     * @return array
     */
    protected function image_element()
    {
        $cached_image_path = $this->get_cached_image_path();

        $class = $this->get_class();

        $src = $this->generateUrl($cached_image_path);

        $data = array(
            'path' => $this->get_full_path_to_cached_image(),
            'src' => $src,
            'img' => '<img src="'. $src .'" width="'. $this->preset->width .'" height="'. $this->preset->height .'"'. $class .' alt="'. $this->alt .'" title="'. $this->title .'"/>',
            'img_nosize' => '<img src="'. $src .'"'. $class .' alt="'. $this->alt .'" title="'. $this->title .'"/>',
        );

        return $data;
    }

    /**
     * Generate the image element and src to use in the calling script
     *
     * @return array
     */
    protected function image_element_empty()
    {
        if ($this->use_placeholders) {
            return $this->generate_placeholder();
        }

        $data = array(
            'path' => '',
            'src' => '',
            'img' => '',
            'img_nosize' => '',
        );

        return (object) $data;
    }

    protected function generate_placeholder()
    {
        $src = 'https://lorempixel.com/'. $this->preset->width .'/'. $this->preset->height;

        $class = $this->get_class();

        $data = array(
            'path' => '',
            'src' => $src,
            'img' => '<img src="'. $src .'" width="'. $this->preset->width .'" height="'. $this->preset->height .'"'. $class .' alt="'. $this->alt .'" title="'. $this->title .'"/>',
            'img_nosize' => '<img src="'. $src .'"'. $class .' alt="'. $this->alt .'" title="'. $this->title .'"/>',
        );

        return (object) $data;
    }


    /**
     * Generate the image element and src to use in the calling script
     *
     * @return array
     */
    protected function image_element_original()
    {
        $class = $this->get_class();

        $data['src'] = Storage::url($this->file_name);
        $data['img'] = '<img src="'. $data['src'] .'"'. $class .' alt="'. $this->alt .'" title="'. $this->title .'"/>';
        $data['img_nosize'] = '<img src="'. $data['src'] .'"'. $class .' alt="'. $this->alt .'" title="'. $this->title .'"/>';

        return (object) $data;
    }

    /**
     * Delete every image preset for one image
     *
     */
    protected function delete_image()
    {
        $presets = $this->get_presets();

        foreach ($presets as $key => $preset) {
            $file_name = $this->imagecache_path . $key .'/'. $this->file_name;
            if (File::exists($file_name)) {
                File::delete($file_name);
            }
        }
    }
}
