<?php

return array(
  /**
   * The location of the directory where you store uploaded files on the
   * site relative to the laravel root
   */
  'files_directory' => './files/',

  /**
   * The location of the directory where you would like to store
   * the rendered image cache files
   */
  'imagecache_directory' => './files/imagecache/',

  /**
   * Key value pair of presets with the name and dimensions to be used
   *
   * 'PRESET_NAME' => array(
   *   'width'  => INT, // in pixels
   *   'height' => INT, // in pixels
   *   'aspect_ratio' => width/height,
   * )
   *
   * eg   'presets' => array(
   *        '800x600' => array(
   *          'width' => 800,
   *          'height' => 600,
   *          'aspect_ratio' => 800/600,
   *        )
   *      ),
   *
   */
  'presets' => array(
    '100x100' => array(
      'width' => 100,
      'height' => 100,
      'aspect_ratio' => 1,
    )
  ),
);