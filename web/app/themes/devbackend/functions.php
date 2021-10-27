<?php

use Carbon_Fields\Container;
use Carbon_Fields\Field;

add_action('after_setup_theme', function () {
  define('Carbon_Fields\URL', home_url('/vendor/htmlburger/carbon-fields'));
  \Carbon_Fields\Carbon_Fields::boot();
});

// This is an example of how to create a new field.
// See more in the documentation: https://docs.carbonfields.net/
add_action('carbon_fields_register_fields', function () {
  Container::make('theme_options', __('Theme Options'))
    ->add_fields([
      Field::make('text', 'crb_text', 'Text Field'),
    ]);
});