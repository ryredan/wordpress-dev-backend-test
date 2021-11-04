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

/* Products Custom Post Type BEGIN */
function products_post_type() {
    register_post_type('products', array(
        'label' => 'Produtos',
        'public' => true,
        'supports' => ['title'],
        'show_in_graphql' => true,
        'hierarchical' => true,
        'graphql_single_name' => 'product',
        'graphql_plural_name' => 'products',
    ));

}

add_action('init', 'products_post_type');

function make_product_custom_fields(){
    Container::make('post_meta', 'products', 'Detalhes' )
    ->where( 'post_type', '=', 'products' )
    ->add_fields( array(
        Field::make('image', 'product_image', __('Image')),
        Field::make('association', 'post_association', 'Associação')
        ->set_types( array(
            array(
                'type' => 'post',
                'post_type' => 'post'
            )
        ))
    ));
}
add_action('carbon_fields_register_fields', 'make_product_custom_fields');


function associate_posts($post_id)
{
    if(get_post_type($post_id) == 'products')
    {
        $posts = carbon_get_post_meta($post_id, 'post_association');
        foreach($posts as $post)
        {
            $currentProductAssociation = carbon_get_post_meta($post['id'], 'product_association[0]');
            if($currentProductAssociation != null)
            {
                $productData = carbon_get_post_meta($currentProductAssociation, 'post_association');
                carbon_set_post_meta($currentProductAssociation, 'post_association', array_diff($productData, array($post['id'])));
            }
            carbon_set_post_meta($post['id'], 'product_association', array($post_id));
        }
        //Cleanup

        $query_args = array(
            'post_type' => 'post',
            'meta_query' => array(
                array(
                    'key' => 'carbon_fields:_product_association|||%|id',
                    'value' => $post_id,
                ),
            ),
            'fields' => 'ids'
        );

        $query = new WP_Query($query_args);
        foreach($query->get_posts() as $p)
        {
            if(!in_array($p, array_column($posts, 'id')))
            {
                carbon_set_post_meta($p, 'product_association', array());
            }
        }

    }
}
add_action('carbon_fields_post_meta_container_saved', 'associate_posts');


function delete_product_association($post_id)
{
    if(get_post_type($post_id) == 'products')
    {
        $posts = carbon_get_post_meta($post_id, 'post_association');
        foreach($posts as $post)
        {
            carbon_set_post_meta($post['id'], 'product_association', array());
        }
    }
}

add_action('before_delete_post', 'delete_product_association');

/* Products Custom Post Type END */






/* General Post Configuration BEGIN */

function add_product_association_fields()
{
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
}    
add_action('carbon_fields_register_fields', 'add_product_association_fields');

function update_product_associated_posts($post_id)
{
    if(get_post_type($post_id) != 'products')
    {
        $productId = carbon_get_post_meta($post_id, 'product_association')[0]['id'];
        $productPosts = carbon_get_post_meta($productId, 'post_association');
        $allPosts = [];
        foreach($productPosts as $post)
        {
            $allPosts[] = $post['id'];
        }
        if(!in_array($post_id, $allPosts))
        {
            $allPosts[] = $post_id;
        }
        carbon_set_post_meta($productId, 'post_association', $allPosts);
    }
}

add_action('carbon_fields_post_meta_container_saved', 'update_product_associated_posts');

function delete_post_association($post_id)
{
    if(get_post_type($post_id) != 'products')
    {
        $productId = carbon_get_post_meta($post_id, 'product_association')[0]['id'];
        $productPosts = carbon_get_post_meta($productId, 'post_association');
        $allPosts = [];
        foreach($productPosts as $post)
        {
            if($post['id'] != $post_id)
            {
                $allPosts[] = $post['id'];
            }
        }
        carbon_set_post_meta($productId, 'post_association', $allPosts);
    }
}

add_action('before_delete_post', 'delete_post_association');
/* General Post Configuration END */

/* GraphQL bindings */
add_action( 'graphql_register_types', function() {

    register_graphql_connection([
		'fromType' => 'product',
		'toType' => 'MediaItem',
		'fromFieldName' => 'image',
		'resolve' => function( \WPGraphQL\Model\Post $source, $args, $context, $info ) {
			$resolver = new \WPGraphQL\Data\Connection\PostObjectConnectionResolver( $source, $args, $context, $info, 'attachment' );
            //$resolver->set_query_arg('post_type', 'attachment');
			$resolver->set_query_arg('post__in', array(get_post_meta($source->ID, '_product_image', true)));
            //$resolver->set_query_arg('post_status', 'inherit');
			return $resolver->get_connection();
		}
	]);
    
    /* products->posts */
	register_graphql_connection([
		'fromType' => 'product',
		'toType' => 'Post',
		'fromFieldName' => 'posts',
		'connectionArgs' => \WPGraphQL\Connection\PostObjects::get_connection_args(),
		'resolve' => function( \WPGraphQL\Model\Post $source, $args, $context, $info ) {
			$resolver = new \WPGraphQL\Data\Connection\PostObjectConnectionResolver( $source, $args, $context, $info, 'post' );
			$resolver->set_query_arg('post__in', get_post_meta($source->ID, 'post_association'));
            $resolver->set_query_arg('meta_value', $source->ID); //maybe delete
			return $resolver->get_connection();
		}
	]);

    /* post->product */
    register_graphql_connection([
		'fromType' => 'post',
		'toType' => 'Post',
		'fromFieldName' => 'product',
		'connectionArgs' => \WPGraphQL\Connection\PostObjects::get_connection_args(),
		'resolve' => function( \WPGraphQL\Model\Post $source, $args, $context, $info ) {
			$resolver = new \WPGraphQL\Data\Connection\PostObjectConnectionResolver( $source, $args, $context, $info, 'post' );
			$resolver->set_query_arg('post__in', get_post_meta($source->ID, 'product_association', true));
            $resolver->set_query_arg('meta_value', $source->ID);
			return $resolver->get_connection();
		}
	]);

} );