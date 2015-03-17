<?php namespace Devfactory\Imagecache;

use Illuminate\Support\Facades\Config;
use Intervention\Image\Facades\Image;
use File;

class Imagecache {

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
   * The base directory to look for files taken from config
   *
   * @var string
   **/
  protected $file_dir_default;

  /**
   * The directory in which to look for the file
   *
   * @var string
   **/
  protected $file_dir;

  /**
   * The directory name to story all the imagecaches
   *
   * @var string
   **/
  protected $ic_dir;

  /**
   * The filename of the file relative to the file storage directory ($this->file_dir)
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
   * __construct
   *
   * @return void
   */
  public function __construct()  {
    $this->file_dir_default = $this->sanitizeDirectoryName(config('imagecache.config.files_directory'), TRUE);
    $this->ic_dir = $this->sanitizeDirectoryName(config('imagecache.config.imagecache_directory'));
    $this->public_path = $this->sanitizeDirectoryName(config('imagecache.config.public_path'), TRUE);

    $this->filename_field = config('imagecache.config.filename_field');
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
  private function sanitizeDirectoryName($name, $keep_leading_slash = FALSE) {
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
  public function get($file, $preset, $args = NULL) {
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
  public function get_original($file, $args = NULL) {
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
  private function setPreset($preset) {
    if (!$this->validate_preset($preset)) {
      return FALSE;
    }

    $presets = $this->get_presets();
    $this->preset = (object) $presets[$preset];
    $this->preset->name = $preset;

    return TRUE;
  }

  /**
   * Take the passed file and check it to retrieve a filename
   *
   * @return bool
   *  TRUE if $this->filename set, otherwise FALSE
   */
  private function setFilename() {
    if (is_object($this->file)) {
      if (!isset($this->file->{$this->filename_field})) {
        return FALSE;
      }

      $this->file_name = $this->file->{$this->filename_field};
      return TRUE;
    }

    if (is_array($this->file)) {
      $this->file_name = $field[$this->filename_field];
      return TRUE;
    }

    if (is_string($this->file)) {
      $this->file_name = $this->file;
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Parse the passed arguments and set the instance variables
   *
   * @param $args array
   *  An array of optional parameters as a key => value pair
   *
   * @return void
   */
  private function setupArguments($args) {
    $this->file_dir = (isset($args['base_dir']) ? $args['base_dir'] : $this->file_dir_default);
    $this->class = (isset($args['class']) ? $args['class'] : NULL);
    $this->alt = isset($args['alt']) ? $args['alt'] : $this->parseAlt();
    $this->title = isset($args['title']) ? $args['title'] : $this->parseTitle();
  }

  /**
   * If the file received is an object or array, check if the 'alt' field is set
   *
   * @return string
   */
  private function parseAlt() {
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
  private function parseTitle() {
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
  public function delete($file_name) {
    $this->file_name = $file_name;

    $this->delete_image();
  }

  /**
   * Check that preset os valid and described in the config file
   *
   * @return bool
   */
  private function validate_preset($preset) {
    if (in_array($preset, array_keys($this->get_presets()))) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Check if the image given exists on the server
   *
   * @return bool
   */
  private function image_exists() {
    if (file_exists($this->file_dir . $this->file_name)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Checks if we have an SVG image.
   *
   * @return
   *   TRUE if SVG and FALSE otherwise
   */
  private function is_svg() {
    $finfo = new \finfo(FILEINFO_MIME);
    $type = $finfo->file($this->file_dir . $this->file_name);

    if (str_contains($type, 'image/svg+xml')) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Generate the imagecache if one doesn't already exist
   * Uses the MY_Image_lib library by Jens Segers:
   * * http://www.jenssegers.be
   *
   * @return bool
   */
  private function generate_cached_image() {
    $cached_image = $this->get_cached_image_path();

    if (file_exists($cached_image)) {
      return TRUE;
    }

    $path_info = pathinfo($cached_image);

    if (!is_dir($path_info['dirname'])) {
      mkdir($path_info['dirname'], 0777, TRUE);
    }

    if (!($image = $this->buildImage())) {
      return FALSE;
    }

    if ($image->save($cached_image)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Generates and calls the correct method for the generation method used in preset
   *
   * @return mixed
   *  FALSE if no matching method, otherwise the Image Object
   */
  private function buildImage () {
    $method = 'buildImage'. ucfirst($this->preset->method);
    if (method_exists($this, $method)) {
      return $this->{$method}();
    }

    return FALSE;
  }

  /**
   * Resize the image, contraining the aspect ratio and size
   *
   * @return Image Object
   */
  private function buildImageResize() {
    $image = Image::make($this->file_dir . $this->file_name)->orientate();

    $image->resize($this->preset->width, $this->preset->height , function ($constraint) {
      $constraint->aspectRatio();
      $constraint->upsize();
    });

    $image->resizeCanvas($this->preset->width, $this->preset->height, 'center', FALSE, isset($this->preset->background_color) ? $this->preset->background_color : '#000000');

    return $image;
  }

  /**
   * Crop the image to the given size and aspèect ration, ignoring upsize or aspect ratio constraints
   *
   * @return
   */
  private function buildImageCrop () {
    $image = Image::make($this->file_dir . $this->file_name)->orientate();

    if ($this->preset->width == 0) {
      $image->heighten($this->preset->height);
    }
    else if ($this->preset->height == 0) {
      $image->widen($this->preset->width);
    }
    else {
      $image->fit($this->preset->width, $this->preset->height);
    }

    return $image;
  }

  /**
   * Create the path of the imagecache for the given image and preset
   *
   * @return string
   */
  private function get_cached_image_path() {
    return $this->ic_dir . $this->preset->name .'/'. $this->file_name;
  }

  /**
   * Create the path of the imagecache for the given image and preset
   *
   * @return string
   */
  private function get_original_image_path() {
    return $this->file_dir . $this->file_name;
  }

  /**
   * The full path to the original image relative to the file system root
   *
   * @return string
   */
  private function get_full_path_to_original_image() {
    return $this->public_path . $this->file_dir . $this->file_name;
  }

  /**
   * The full path to the cached image relative to the file system root
   *
   * @return string
   */
  private function get_full_path_to_cached_image() {
    return $this->public_path . $this->get_cached_image_path();
  }

  /**
   * Extract the preset imformation for the given preset from the config file
   *
   * @return array
   */
  private function get_preset() {
    return $this->preset;
  }

  /**
   * Get all the presets
   *
   * @return array
   */
  private function get_presets() {
    return config('imagecache.presets');
  }

  /**
   * Get the class="" string of the image
   *
   * @return String
   */
  private function get_class()  {
    if ($this->class) {
      return ' class="'. $this->class .'"';
    }

    return '';
  }

  /**
   * Generate the image element and src to use in the calling script
   *
   * @return array
   */
  private function image_element() {
    $cached_image_path = $this->get_cached_image_path();

    $class = $this->get_class();

    $src = \URL::asset($cached_image_path);

    $data = array(
      'path' => $this->get_full_path_to_cached_image(),
      'src' => $src,
      'img' => '<img src="'. $src .'" width="'. $this->preset->width .'" height="'. $this->preset->height .'" class="" '. $class .' alt="'. $this->alt .'" title="'. $this->title .'"/>',
      'img_nosize' => '<img src="'. $src .'" class=""'. $class .' alt="'. $this->alt .'" title="'. $this->title .'"/>',
    );

    return $data;
  }

  /**
   * Generate the image element and src to use in the calling script
   *
   * @return array
   */
  private function image_element_empty() {
    $data = array(
      'path' => '',
      'src' => '',
      'img' => '',
      'img_nosize' => '',
    );

    return (object) $data;
  }

  /**
   * Generate the image element and src to use in the calling script
   *
   * @return array
   */
  private function image_element_original() {
    $path = $this->get_original_image_path();
    $class = $this->get_class();

    $data['src'] = \URL::asset($path);
    $data['img'] = '<img src="'. $data['src'] .'" class="" '. $class .' alt="'. $this->alt .'" title="'. $this->title .'"/>';
    $data['img_nosize'] = '<img src="'. $data['src'] .'" class=""'. $class .' alt="'. $this->alt .'" title="'. $this->title .'"/>';

    return (object) $data;
  }

  /**
   * Delete every image preset for one image
   *
   */
  private function delete_image() {
    $presets = $this->get_presets();

    foreach ($presets as $key => $preset) {
      $file_name = $this->public_path . $this->ic_dir . $key .'/'. $this->file_name;
      if (File::exists($file_name)) {
        File::delete($file_name);
      }
    }
  }
}
