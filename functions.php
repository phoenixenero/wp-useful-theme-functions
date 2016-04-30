<?php
/**
 * These are a couple of loosely-related functions for WordPress theme
 * development.
 *
 * @package wp-useful-theme-functions
 */

/**
 * Checks whether post content is empty.
 *
 * Strips HTML tags, replaces `&nbsp;` characters, and trims the resulting
 * string to REALLY check if the content is empty.
 *
 * @param string $content Post content.
 * @return boolean Whether content is empty.
 */
function _s_is_content_empty( $content ) {
	return '' === trim( str_replace( '&nbsp;', '', strip_tags( $content ) ) );
}

/**
 * Check if the post content contains a gallery shortcode.
 *
 * @param int|WP_Post $post Optional. A valid post ID or object. Defaults to
 *                          global `$post`.
 * @return boolean Whether post contains a gallery shortcode.
 */
function _s_has_gallery( $post = null ) {
	if ( ! $post = get_post( $post ) ) {
		return false;
	}
	return has_shortcode( $post->post_content, 'gallery' );
}

/**
 * Get the aspect ratio of an image attachment.
 *
 * @param int          $attachment_id Image attachment ID.
 * @param string|array $size          Optional. Image size. Accepts any valid
 *                                    image size, or an array of width and
 *                                    height values in pixels (in that order).
 *                                    Default is 'thumbnail'.
 * @return int|false Aspect ratio of the attachment image, or false if no image
 *                   is available.
 */
function _s_get_attachment_aspect_ratio( $attachment_id, $size = 'thumbnail' ) {
	if ( ! $image = wp_get_attachment_image_src( $attachment_id, $size ) ) {
		return false;
	}
	$width  = absint( $image[1] );
	$height = absint( $image[2] );

	return $width / $height;
}

/**
 * Retrieves the first url from the current post.
 *
 * @param int|WP_Post $post Optional. A valid post ID or object. Defaults to
 *                          global `$post`.
 * @return string|false The first URL. Returns `false` if it doesn't exist.
 */
function _s_get_first_url( $post = null ) {
	if ( ! $post = get_post() ) {
		return false;
	}
	if ( ! preg_match( '/href\s*=\s*[\"\']([^\"\']+)/i', $post->post_content, $links ) ) {
		return false;
	}
	return esc_url_raw( $links[1][0] );
}

/**
 * Returns the first category of a post.
 *
 * @param int $id Optional. The post id. Default is global `$post`.
 * @return array|false The first category object. Returns false if first
 *                     category doesn't exist.
 */
function _s_get_the_first_category( $id = false ) {
	$categories = get_the_category( $id );
	return isset( $categories[0] ) ? $categories[0] : false;
}

/**
 * Check if current author has populated the contact methods fields.
 *
 * Caches the results.
 *
 * @param int $user_id Optional. User ID.
 * @return bool Whether the current author populated the contact methods fields.
 */
function _s_has_contact_methods( $user_id = null ) {
	if ( null === $user_id ) {
		global $authordata;
		$user_id = isset( $authordata->ID ) ? $authordata->ID : 0;
	}
	$transient = md5( '_s_has_contact_methods_' . $user_id );

	if ( false === ( $has_contact_methods = get_transient( $transient ) ) ) {
		$contactmethods = wp_get_user_contact_methods();
		$has_contact_methods = 0;

		foreach ( $contactmethods as $key => $value ) {
			if ( ! empty( get_the_author_meta( $key, $user_id ) ) ) {
				set_transient( $transient, 1, WEEK_IN_SECONDS );
				return true;
			}
		}
	} else {
		return ( 1 === intval( $has_contact_methods ) ) ? true : false;
	}
	set_transient( $transient, 0, WEEK_IN_SECONDS );

	return false;
}

/**
 * Invalidate cache for `has_contact_methods` on user profile update.
 *
 * @param int $user_id User ID of the profile being updated.
 */
function _s_refresh_contact_methods_transient( $user_id ) {
	delete_transient( md5( '_s_has_contact_methods_' . $user_id ) );
}
add_action( 'profile_update', '_s_refresh_contact_methods_transient' );

/**
 * Get the post thumbnail URL of a post.
 *
 * Compatible since WordPress 2.5.
 *
 * @param int|WP_Post  $post Optional. Post ID or WP_Post object. Default is
 *                           global `$post`.
 * @param string|array $size Optional. Image size. Accepts any valid image size,
 *                           or an array of width and height values in pixels
 *                           (in that order). Default is 'post-thumbnail'.
 *
 * @return string|false Attachment URL or false if no image is available.
 */
function _s_get_post_thumbnail_url( $post = null, $size = 'post-thumbnail' ) {
	$attachment_id = get_post_thumbnail_id( $post );
	$image = wp_get_attachment_image_src( $attachment_id, $size );

	return isset( $image['0'] ) ? $image['0'] : false;
}

/**
 * Get the first image attachment in the post.
 *
 * Compatible since WordPress 2.5.
 *
 * @param string $size Optional. The image size. Default is 'thumbnail'.
 * @param array  $args {
 *     Optional. Array of arguments. Default is empty array.
 *
 *     @param bool         $icon Optional. Whether the image should be
 *                               treated as an icon. Default is `false`.
 *     @param string|array $attr Optional. Attributes for the image markup.
 *                               Default is empty string.
 * }
 *
 * @return string The image element HTML. Empty string on error.
 */
function _s_the_first_image( $size = 'thumbnail', $args = array() ) {
	if ( ! $post = get_post() ) {
		return '';
	}

	$args = wp_parse_args( $args, array(
		'icon' => false,
		'attr' => '',
	) );

	$images = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_parent'    => $post->ID,
			'posts_per_page' => 1,
		)
	);

	if ( ! $images || empty( $images ) ) {
		return '';
	}

	return wp_get_attachment_image(
		$images[0]->ID,
		$size,
		$args['icon'],
		$args['attr']
	);
}

/**
 * Get the comment excerpt.
 *
 * Comments from password-protected posts are hidden by default.
 *
 * @uses wp_trim_words()
 *
 * @param WP_Comment|string|int $comment_id           Optional. Comment to retrieve. Default is global `$comment`.
 * @param int                   $num_words            Optional. Number of words. Default is 20.
 * @param string|null           $more                 Optional. More text. Default is '&hellip;'.
 * @param bool                  $show_hidden_comments Optional. Whether to show comments from password-protected posts. Default is `false`.
 * @return string The comment excerpt.
 */
function _s_get_comment_excerpt( $comment_id = null, $num_words = 20, $more = null, $show_hidden_comments = false ) {
	if ( ! $comment = get_comment( $comment_id ) ) {
		return '';
	}

	if ( ! $show_hidden_comments && post_password_required( $comment->comment_post_ID ) ) {
		return esc_html__( 'This comment is hidden.', '_s' );
	}

	$excerpt = wp_trim_words( $comment->comment_content, $num_words, $more );

	return apply_filters( '_s_get_comment_excerpt', $excerpt );
}
