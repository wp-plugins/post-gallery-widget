<?php
/*
Plugin Name: Rotating Post Gallery
Plugin URI: http://wpmututorials.com/plugins/post-gallery-widget/
Description: A Rotating Gallery Widget using a custom post type to create Gallery Posts.
Author: Ron Rennick
Version: 0.2
Author URI: http://ronandandrea.com/

This plugin is a collaboration project with contributions from the CUNY Acedemic Commons (http://dev.commons.gc.cuny.edu/)
*/
/* Copyright:   (C) 2010 Ron Rennick, All rights reserved.

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/
// @todo - add text domain and .po file
/*
 * Set values for post type
 */
class PGW_Post_Type {
	var $post_type_name = 'pgw_post';
	var $handle = 'pgw-meta-box';
	var $attachments = null;

	var $post_type = array(
		'label' => 'Gallery Posts',
		'singular_label' => 'Gallery Post',
		'menu_position' => '1',
		'taxonomies' => array(),
		'public' => true,
		'show_ui' => true,
		'rewrite' => true,
		'supports' => array( 'title', 'editor', 'author' )
		);


	function PGW_Post_Type() {
		return $this->__construct();
	}

	function  __construct() {
		add_action( 'init', array( &$this, 'init' ) );

		$this->post_type['description'] = $this->post_type['singular_label'];
		$this->post_type['labels'] = array(
			'name' => $this->post_type['label'],
			'singular_name' => $this->post_type["singular_label"],
			'add_new' => 'Add ' . $this->post_type["singular_label"],
			'add_new_item' => 'Add New ' . $this->post_type["singular_label"],
			'edit' => 'Edit',
			'edit_item' => 'Edit ' . $this->post_type["singular_label"],
			'new_item' => 'New ' . $this->post_type["singular_label"],
			'view' => 'View ' . $this->post_type["singular_label"],
			'view_item' => 'View ' . $this->post_type["singular_label"],
			'search_items' => 'Search ' . $this->post_type["singular_label"],
			'not_found' => 'No ' . $this->post_type["singular_label"] . ' Found',
			'not_found_in_trash' => 'No ' . $this->post_type["singular_label"] . ' Found in Trash'
			);
	}

	function init() {
		register_post_type( $this->post_type_name, $this->post_type );
		add_action('admin_menu', array( &$this, 'admin_menu' ), 20);
	}

	function query_posts( $num_posts = -1, $size = 'full' ) {
		$query = sprintf( 'showposts=%d&post_type=%s&orderby=none', $num_posts, $this->post_type_name );
		$posts = new WP_Query( $query );
		$gallery = array();
		$child = array( 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => 'none' );
		while( $posts->have_posts() ) {
			$posts->the_post();
			$child['post_parent'] = get_the_ID();
			$attachments = get_children( $child );
			if( empty( $attachments ) )
				continue;

			$p = new stdClass();
			$p->post_title = get_the_title();
			$p->post_excerpt = get_the_content();
			if( ( $c = count( $attachments ) ) > 1 ) {
				$x = rand( 1, $c );
				while( $c > $x++ )
					next( $attachments );
			}
			$p->tag = wp_get_attachment_image( key( $attachments ), $size, false );
			$gallery[] = $p;
		}
		wp_reset_query();
		return $gallery;
	}
	function admin_menu() {
		add_action( 'do_meta_boxes', array( &$this, 'add_metabox' ), 9 );
	}
	function add_metabox() {
		global $post;
		if( empty( $post ) || $this->post_type_name != $post->post_type )
			return;
		$child = array( 'post_parent' => $post->ID, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => 'none' );
		$this->attachments = get_children( $child );
		if( !empty( $this->attachments ) )
			add_meta_box( $this->handle, 'Attached Images', array( &$this, 'image_metabox' ), $this->post_type_name, 'normal' );
	}
	function image_metabox() {
		echo '<p>';
		foreach( (array) $this->attachments as $k => $v )
			echo '<span style="padding:3px;">' . wp_get_attachment_image( $k, 'thumbnail', false ) . '</span>';
		echo '</p>';
	}
}

$pgw_post_type = new PGW_Post_Type();

class Rotating_Post_Widget extends WP_Widget {
	// Note: these strings match strings in WP exactly. If changed the gettext domain will need to be added
	var $sizes = array( 'full' => 'Full Size', 'medium' => 'Medium', 'large' => 'Large' );
	var $id = 'post_gallery_widget';
	var $queued = false;

	function Rotating_Post_Widget() {
		$widget_ops = array( 'description' => __( 'Rotating Post Gallery Widget' ) );
		$this->WP_Widget( $this->id, __('Rotating Post Gallery Widget'), $widget_ops );
		add_action( 'wp_head', array( &$this, 'wp_head' ), 1 );
		add_action( 'wp_footer', array( &$this, 'wp_footer' ), 2 );
	}

	function widget( $args, $instance ) {
		global $pgw_post_type;
		extract( $args );
		echo $before_widget; ?>
			<div id="pgw-gallery<?php echo ( $instance['size'] ? '-' . $instance['size'] : '' ); ?>">
				<div class="slideshow">
<?php		$first = true;
		$num_posts = -1;
		if( $instance['how_many'] > 0 )
			$num_posts = $instance['how_many'];
		if( !empty( $pgw_post_type ) ) {
			$posts = $pgw_post_type->query_posts( $num_posts, $instance['size'] );
			foreach( $posts as $p ) { ?>
		<div class="slide<?php if( $first ) { echo ' first_slide'; } ?>">
<?php				echo $p->tag; ?>
			<span><h2><?php echo $p->post_title; ?></h2>
				<p><?php echo $p->post_excerpt; ?><br /></p>
			</span>
		</div>
<?php				$first = false;
			}
		}
?>
				</div>
				<a id="pgw-prev" href="#">Previous</a>
				<a id="pgw-next" href="#">Next</a>
				<div style="clear:both;"></div>
			</div>
<?php 		echo $after_widget;
		if( $this->queued )
			$this->queued = false;
	}

 	function update( $new_instance, $old_instance ) {
		$new_instance['how_many'] = intval( $new_instance['how_many'] );
		if( !in_array( $new_instance['size'], array_keys( $this->sizes ) ) )
			$new_instance['size'] = 'full';

		return $new_instance;
	}

	function form( $instance ) { ?>
		<p><label for="<?php echo $this->get_field_id('how_many'); ?>"><?php _e('How many gallery posts:') ?></label>
		<input type="text" id="<?php echo $this->get_field_id('how_many'); ?>" name="<?php echo $this->get_field_name('how_many'); ?>" value="<?php echo ( $instance['how_many'] > 0 ? esc_attr( $instance['how_many'] ) : '' ); ?>" /></p>
		<p>
			<label for="<?php echo $this->get_field_id('size'); ?>"><?php _e( 'Image Size:' ); ?></label>
			<select name="<?php echo $this->get_field_name('size'); ?>" id="<?php echo $this->get_field_id('size'); ?>" class="widefat">
<?php		foreach( $this->sizes as $k => $v ) { ?>
				<option value="<?php echo $k; ?>"<?php selected( $instance['size'], $k ); ?>><?php _e( $v ); ?></option>
<?php		} ?>
			</select>
		</p>
<?php	}

	function wp_head() {
		if( !is_admin() ) {
			$this->queued = true;
			$url = plugin_dir_url( __FILE__ );

			wp_enqueue_style( 'pgw-cycle', $url . 'css/style.css' );
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'pgw-cycle-js', $url . 'js/jquery.cycle.lite.min.js', array( 'jquery' ), '1.4', true );
			wp_enqueue_script( 'pgw-cycle-slide-js', $url . 'js/pgw-slide.js', false, false, true );
		}
	}

	function wp_footer() {
		if( $this->queued ) {
			wp_deregister_script( 'pgw-cycle-js' );
			wp_deregister_script( 'pgw-cycle-slide-js' );
		}
	}
}

function register_rotating_post_widget() {
	register_widget( 'Rotating_Post_Widget' );
}
add_action( 'widgets_init', 'register_rotating_post_widget' );
