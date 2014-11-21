<?php namespace Devfactory\Imagecache;

use Illuminate\Support\Facades\Config;
use Intervention\Image\Facades\Image;
use Str;

class Imagecache {
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
   * __construct
   *
   * @return void
   */
  public function __construct()  {
    $this->file_dir_default = $this->sanitizeDirectoryName(Config::get('imagecache::config.files_directory'), TRUE);
    $this->ic_dir = $this->sanitizeDirectoryName(Config::get('imagecache::config.imagecache_directory'));
    $this->public_path = $this->sanitizeDirectoryName(Config::get('imagecache::config.public_path'));

    $this->filename_field = Config::get('imagecache::config.filename_field');
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
    if (!$this->setFilename($file)) {
      return $this->image_element_empty();
    }

    $this->preset = $preset;

    $this->setupArguments($args);

    if (!$this->validate_preset()) {
      return FALSE;
    }

    if (!$this->image_exists()) {
      return FALSE;
    }

    if ($this->is_svg()) {
      return (object) $this->image_element_original();
    }

    if (!$this->generate_cached_image()) {
      return FALSE;
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
  public function get_original($file) {
    if (!$this->setFilename($file)) {
      return $this->image_element_empty();
    }

    return $this->image_element_original();
  }

  /**
   * Take the passed file and check it to retrieve a filename
   *
   * @param $file mixed
   *  Object/array/string to check for a filename
   *
   * @return bool
   *  TRUE if $this->filename set, otherwise FALSE
   */
  private function setFilename($file) {
    if (is_object($file)) {
      if (!isset($file->{$this->filename_field})) {
        return FALSE;
      }

      $this->file_name = $file->{$this->filename_field};
      return TRUE;
    }

    if (is_array($file)) {
      $this->file_name = $field[$this->filename_field];
      return TRUE;
    }

    if (is_string($file)) {
      $this->file_name = $file;
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
  private function validate_preset() {
    if (in_array($this->preset, array_keys($this->get_presets()))) {
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

    if (Str::contains($type, 'image/svg+xml')) {
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

    $preset = $this->get_preset();

    $path_info = pathinfo($cached_image);

    if (!is_dir($path_info['dirname'])) {
      mkdir($path_info['dirname'], 0777, TRUE);
    }

    $image = Image::make($this->file_dir . $this->file_name);

    if ($preset['width'] == 0) {
      $image->heighten($preset['height']);
    }
    else if ($preset['height'] == 0) {
      $image->widen($preset['width']);
    }
    else {
      $image->grab($preset['width'], $preset['height']);
    }

    $image->save($cached_image);

    if ($image->save($cached_image)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Create the path of the imagecache for the given image and preset
   *
   * @return string
   */
  private function get_cached_image_path() {
    return $this->ic_dir . $this->preset .'/'. $this->file_name;
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
    $presets = $this->get_presets();

    return $presets[$this->preset];
  }

  /**
   * Get all the presets
   *
   * @return array
   */
  private function get_presets() {
    return Config::get('imagecache::presets');
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

    $preset = $this->get_preset();
    $class = $this->get_class();

    $src = \URL::asset(ltrim($cached_image_path, '.'));

    $data = array(
      'path' => $this->get_full_path_to_cached_image(),
      'src' => $src,
      'img' => '<img src="'. $src .'" width="'. $preset['width'] .'" height="'. $preset['height'] .'" alt="" '. $class .'/>',
      'img_nosize' => '<img src="'. $src .'" alt=""'. $class .'/>',
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

    $data['src'] = ltrim($path, '.');
    $data['img'] = '<img src="'. $data['src'] .'" alt="" '. $class .'/>';
    $data['img_nosize'] = '<img src="'. $data['src'] .'" alt=""'. $class .'/>';

    return $data;
  }

  /**
   * Delete every image preset for one image
   *
   * @return
   */
  private function delete_image() {
    $presets = $this->get_presets();

    foreach ($presets as $key => $preset) {
      $file_name = $this->public_path . $this->ic_dir . $key .'/'. $this->file_name;
      if (file_exists($file_name)) {
        unlink($file_name);
      }
    }
  }
}
