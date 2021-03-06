<?php
/**
     * The main template file.
     *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package Acme Themes
 * @subpackage Infinite Photography
 */
require_once "data/BestPosts.php";
get_header();
global $infinite_photography_customizer_all_values;
?>
	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">
            <p style="display: none">who is the best beauty asian girl,cute asian girl,hot asian girl ,where are is beauty asian girl cute asian girl hot asia girlBeauty Asian Girl Hot Asian girl Cute Asian Girl - welcome friends,there are many cute asian girl,beauty asian girl and hot asian girls here,so many beautiful photo,so many beauty asian girls womans,so cute and hot;</p>
		<?php if ( have_posts() ) : ?>

			<?php if ( is_home() && ! is_front_page() ) : ?>
				<header>
					<h1 class="page-title screen-reader-text"><?php single_post_title(); ?></h1>
				</header>
			<?php endif; ?>
			<?php
			/**
			 * infinite_photography_action_masonry_start hook
			 * @since Infinite Photography 1.0.0
			 *
			 * @hooked infinite_photography_masonry_start -  0
			 */
			do_action( 'infinite_photography_action_masonry_start' );

            $BestPosts = new BestPosts();


        if ($BestPosts->isHome()):
			while ( $BestPosts->have_posts() ) : $BestPosts->the_post();

					/*
					 * Include the Post-Format-specific template for the content.
					 * If you want to override this in a child theme, then include a file
					 * called content-___.php (where ___ is the Post Format name) and that will be used instead.
					 */
					if ( $infinite_photography_customizer_all_values['infinite-photography-blog-archive-layout'] == 'photography') {
						get_template_part( 'template-parts/content', 'photography' );
					}
					else{
						get_template_part( 'template-parts/content', get_post_format() );
					}

				endwhile;
					?>
            <header class="page-header">
                <h1 class="page-title"><?php esc_html_e( 'Newest pictures', 'infinite-photography' ); ?></h1>
            </header>
        <?php endif;
			while ( have_posts() ) : the_post();
					/*
					 * Include the Post-Format-specific template for the content.
					 * If you want to override this in a child theme, then include a file
					 * called content-___.php (where ___ is the Post Format name) and that will be used instead.
					 */
				if ( $infinite_photography_customizer_all_values['infinite-photography-blog-archive-layout'] == 'photography') {
					get_template_part( 'template-parts/content', 'photography' );
				}
				else{
					get_template_part( 'template-parts/content', get_post_format() );
				}

			endwhile;
			/**
			 * infinite_photography_action_masonry_end hook
			 * @since Infinite Photography 1.0.0
			 *
			 * @hooked infinite_photography_masonry_end -  0
			 */
			do_action( 'infinite_photography_action_masonry_end' );
			echo "<div class='clearfix'></div>";

			the_posts_navigation(array('prev_text'          => __( 'Older pictures' ),
			                           'next_text'          => __( 'Newer pictures' ),));
			else :
                get_template_part( 'template-parts/content', 'none' );
			endif;
			?>
		</main><!-- #main -->
	</div><!-- #primary -->
<?php
get_sidebar( 'left' );
get_sidebar();
get_footer();