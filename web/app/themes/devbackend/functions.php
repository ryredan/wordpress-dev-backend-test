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
    Container::make('post_meta', 'products', 'Detalhes' )
    ->where( 'post_type', '=', 'products' )
    ->add_fields( array(
        Field::make('image', 'product_image', __('Image')),
        Field::make('association', 'post_association','Associação')
        ->set_types( array(
            array(
                'type' => 'post',
                'post_type' => 'post'
            )
        ))
    ));

    Container::make('post_meta', 'Relacionamentos')
    ->where('post_type', '!=', 'products')
    ->add_fields( array(
        Field::make('association', 'product_association', 'Produto')
        ->set_types(array(
            array(
                'type' => 'post',
                'post_type' => 'products'
            )
        ))->set_max(1)
    ));
});

function products_post_type() {
    register_post_type('products', array(
        'label' => 'Produtos',
        'public' => true,
        'supports' => ['title'] //adicionei só o título para não inserir o editor inteiro por padrão
    ));

}
add_action('init', 'products_post_type');
add_action('carbon_fields_post_meta_container_saved', function (){
    $currentPost = get_post(get_the_ID());
    $posts = carbon_get_post_meta($currentPost->ID, 'post_association');
    foreach($posts as $post){
        carbon_set_post_meta($post['id'], 'product_association', array($currentPost->ID));
    }
});
