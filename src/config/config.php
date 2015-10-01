<?php

return array(
  /**
   * The location of the public folder for your laravel installation
   */
  'public_path'  => public_path(),

  /**
   * The location of the directory where you keep your uploaded images on the
   * site relative to the public_path option
   */
  'files_directory' => 'uploads/',

  /**
   * The location of the directory where you would like to store
   * the rendered Imagecache files relative to the public_path option
   */
  'imagecache_directory' => 'uploads/imagecache/',

  /**
   * The name of the field to check for a filename if passing an array
   * or an object instead of a string to the get() method
   */
  'filename_field' => 'filename',

  /**
   * The quality of the generated image. Possbile values are
   * 0 (worst) - 100 (best). Only applies for JPEG images.
   */
  'quality' => 90,

  /**
   * If you don't have any images to use, or want to save time by not
   * uploading an image. Setting this to true will generate you images
   * matching your height and width settings.
   *
   * Served through www.placeholdr.pics
   */
  'use_placeholders' => FALSE,
);
