# Project Setup

1. Install the dependencies via composer
2. Create a mysql database
3. Create a `.env` following the structure of the `.env.example`
4. Run the project with `composer run dev`, it will start the project at `localhost:8080`
5. Go to http://localhost:8080/wp/wp-admin/themes.php and activate the theme `devbackend`
6. Go to http://localhost:8080/wp/wp-admin/plugins.php and activate the graphql plugin
7. Always work inside of `web/app/theme/devbackend`
8. Please, do not install any other dependency.

# Challenge

1. Create a new Custom Post Type (https://wordpress.org/support/article/post-types/#custom-post-types) called `product`.
2. Create an `image` field in `product`: https://docs.carbonfields.net/learn/fields/image.html
3. Create a relationship where one product can have many posts and one post can be linked to one product. To do that, you should create fields using an Association Field (https://docs.carbonfields.net/learn/fields/association.html): `product->posts` and `post->product`.
4. Could you make it two-way data binding? It means: when I save a product with two posts, it should update the posts that are related to the product; and if I update a related post, it should update the product as well. 
5. Create the representation of a `product` in the graphql schema: https://www.wpgraphql.com/docs/custom-post-types/
6. In the graphql schema, create the field `product.image` that should be similar to `post.featuredImage` schema, using `MediaItem` type.
7. Create the field `product.posts` and the field `post.product` in the graphql schema: https://www.wpgraphql.com/docs/connections/ (documentation) and https://www.wpgraphql.com/recipes/popular-posts/ (example of similar usage)
8. Document your work in your preferred way.

# Challenge plus!

Find a way to auto activate the theme and the plugins after installing the dependencies.

# Delivering the results

Fork this repo and send us the link to your clone at GitHub. If your repo is private, give permissions to mmccpp@gmail.com.
