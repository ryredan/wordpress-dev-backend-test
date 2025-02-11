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

/*
* Registro do novo tipo de post
* com os bindings com o graphQL
*/
function product_post_type() {
    register_post_type('product', array(
        'label' => 'Produtos',
        'public' => true,
        'supports' => ['title'],
        'show_in_graphql' => true,
        'hierarchical' => true,
        'graphql_single_name' => 'product',
        'graphql_plural_name' => 'products',
    ));

}

add_action('init', 'product_post_type');

/*
* Campos customizados de imagem e associação com outros posts
*/
function make_product_custom_fields(){
    Container::make('post_meta', 'product', 'Detalhes' )
    ->where( 'post_type', '=', 'product' )
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

/*
* Função responsável pela manipulação dos posts associados ao produto.
* Após fazer a atualização dos posts associados, uma busca é feita para
* remover todas as associações não recíprocas
*/
function associate_posts($post_id)
{
    if(get_post_type($post_id) == 'product')
    {
        $posts = carbon_get_post_meta($post_id, 'post_association');
        foreach($posts as $post)
        {
            $associated_product = carbon_get_post_meta($post['id'], 'product_association')[0]['id'];
            if(($associated_product != null) && $associated_product != $post_id)
            {
                $product_posts = carbon_get_post_meta($associated_product, 'post_association');
                carbon_set_post_meta($associated_product, 'post_association', array_diff(array_column($product_posts, 'id'), array($post['id'])));
            }
            carbon_set_post_meta($post['id'], 'product_association', array($post_id));
        }
        
        // busca e remoção de associações não recíprocas
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

/*
* Antes de deletar o post permanentemente,
* todas os relacionamentos são removidos a
* fim de evitar referencias a posts inexistentes.
*/
function delete_product_association($post_id)
{
    if(get_post_type($post_id) == 'product')
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

/*
* Campo de associação adicionado aos posts
*/
function add_product_association_fields()
{
    Container::make('post_meta', 'Relacionamentos')
    ->where('post_type', '=', 'post')
    ->add_fields( array(
        Field::make('association', 'product_association', 'Produto')
        ->set_types(array(
            array(
                'type' => 'post',
                'post_type' => 'product'
            )
        ))->set_max(1)
    ));
}   

add_action('carbon_fields_register_fields', 'add_product_association_fields');

/*
* Ao atualizar um post, o produto associado é
* atualizado, em seguida qualquer outro produto
* que ainda faça referencia ao post é atualizado
* a fim de remover referencias não reciprocas
*/
function update_product_associated_posts($post_id)
{
    if(get_post_type($post_id) == 'post')
    {
        $product_id = carbon_get_post_meta($post_id, 'product_association')[0]['id'];
        $product_posts = carbon_get_post_meta($product_id, 'post_association');
        $all_posts = array_column($product_posts, 'id');
        if(!in_array($post_id, $all_posts))
        {
            $all_posts[] = $post_id;
        }
        carbon_set_post_meta($product_id, 'post_association', $all_posts);

        $query_args = array(
            'post_type' => 'product',
            'meta_query' => array(
                array(
                    'key' => 'carbon_fields:_post_association|||%|id',
                    'value' => $post_id,
                ),
            ),
            'fields' => 'ids'
        );

        $query = new WP_Query($query_args);
        foreach($query->get_posts() as $p)
        {
            if($p != $product_id)
            {
                $target_product = array_column(carbon_get_post_meta($p, 'post_association'), 'id');
                carbon_set_post_meta($p, 'post_association', array_diff($target_product, array($post_id)));
            }
        }
    }
}

add_action('carbon_fields_post_meta_container_saved', 'update_product_associated_posts');

/*
* Ao apagar um post, todos os produtos que guardam
* uma referencia aquele post devem ser atualizados
* a evitar referencias a posts inexistentes.
*/
function delete_post_association($post_id)
{
    if(get_post_type($post_id) == 'post')
    {
        $product_id = carbon_get_post_meta($post_id, 'product_association')[0]['id'];
        $product_posts = carbon_get_post_meta($product_id, 'post_association');
        $all_posts = array_column($product_posts, 'id');
        carbon_set_post_meta($product_id, 'post_association', array_diff($all_posts, array($post_id)));
    }
}

add_action('before_delete_post', 'delete_post_association');

/* General Post Configuration END */

/* GraphQL bindings */

add_action( 'graphql_register_types', function() {


    /*
    * Conexão com a imagem
    */
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
    
    /*
    * product->posts
    */
	register_graphql_connection([
		'fromType' => 'product',
		'toType' => 'Post',
		'fromFieldName' => 'posts',
		'connectionArgs' => \WPGraphQL\Connection\PostObjects::get_connection_args(),
		'resolve' => function( \WPGraphQL\Model\Post $source, $args, $context, $info ) {
			$resolver = new \WPGraphQL\Data\Connection\PostObjectConnectionResolver( $source, $args, $context, $info, 'post' );
			//$resolver->set_query_arg('post__in', get_post_meta($source->ID, 'post_association'));
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
			$resolver = new \WPGraphQL\Data\Connection\PostObjectConnectionResolver( $source, $args, $context, $info, 'product' );
			// $resolver->set_query_arg('meta_query', array(
            //     array(
            //         'key' => 'carbon_fields:_product_association|||%|id',
            //         'value' => $source->ID,
            //     ),
            // ));
            $resolver->set_query_arg('meta_value', $source->ID);
			return $resolver->get_connection();
		}
	]);

} );

// Em config/application.php, o tema é ativado por padrão atribuindo 'devbackend' a 'WP_DEFAULT_THEME'