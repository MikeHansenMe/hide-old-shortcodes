<?php
/*
Plugin Name: Hide Old Shortcodes
Description: This plugin hides old/non registered shortcodes on the front end from users. It also helps you locate them so you can remove them.
Version: 0.3
Author: Mike Hansen
Author URI: http://mikehansen.me
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
GitHub Plugin URI: https://github.com/MikeHansenMe/hide-old-shortcodes
GitHub Branch: master
*/

function hos_return_all_shortcodes_regex() {
	return
		  '\\['									// Opening bracket
		. '(\\[?)'								// 1: Optional second opening bracket for escaping shortcodes: [[tag]]
		. "([\w-]+)"							// 2: Shortcode name
		. '(?![\\w-])'							// Not followed by word character or hyphen
		. '('									// 3: Unroll the loop: Inside the opening shortcode tag
		.     '[^\\]\\/]*'						// Not a closing bracket or forward slash
		.     '(?:'
		.         '\\/(?!\\])'					// A forward slash not followed by a closing bracket
		.         '[^\\]\\/]*'					// Not a closing bracket or forward slash
		.     ')*?'
		. ')'
		. '(?:'
		.     '(\\/)'							// 4: Self closing tag ...
		.     '\\]'								// ... and closing bracket
		. '|'
		.     '\\]'								// Closing bracket
		.     '(?:'
		.         '('							// 5: Unroll the loop: Optionally, anything between the opening and closing shortcode tags
		.             '[^\\[]*+'				// Not an opening bracket
		.             '(?:'
		.                 '\\[(?!\\/\\2\\])'	// An opening bracket not followed by the closing shortcode tag
		.                 '[^\\[]*+'			// Not an opening bracket
		.             ')*+'
		.         ')'
		.         '\\[\\/\\2\\]'				// Closing shortcode tag
		.     ')?'
		. ')'
		. '(\\]?)';								// 6: Optional second closing bracket for escaping shortcodes: [[tag]]
}

function hos_replace_old_shortcodes( $content ) {
	global $shortcode_tags;
	global $post;
	$post_type = get_post_type( $post );											//used later to help locate the usage
	$pattern = hos_return_all_shortcodes_regex();									//returns our modified regex to return ALL shortcodes
	preg_match_all( '/' . $pattern . '/s', $content, $matches, PREG_SET_ORDER );
	foreach ( $matches as $match ) {
		$raw_match = $match;														//store the raw match to show usage
		$match = $match[2];															//just the name of the shortcode
		$ignored_shortcodes = get_option( 'hos_ignored_shortcodes', array() );
		if( array_key_exists( 'hos_' . md5( $raw_match[0] . "-" . $post->ID ), $ignored_shortcodes ) AND $ignored_shortcodes[ 'hos_' . md5( $raw_match[0] . "-" . $post->ID ) ] == $raw_match[0] ) {
				continue; //do not add the shortcode back to the log
		}
		if( ! array_key_exists( $match, $shortcode_tags ) ) { 						//check that it is not a active shortcode
			$hidden_shortcodes = get_option( 'hos_hidden_shortcodes', array() );
			if( ! array_key_exists( 'hos_' . md5( $raw_match[0] . "-" . $post->ID ), $hidden_shortcodes ) AND strpos( $raw_match[0],'[[') === false ) {	//check if we already logged this, if so save time and skip
				
				$hidden_shortcodes[ 'hos_' . md5( $raw_match[0] . "-" . $post->ID ) ] = array(
					'shortcode' => $match,
					'raw' => $raw_match[0],
					'post_type' => $post_type,
					'post_id' => $post->ID
					);
				update_option( 'hos_hidden_shortcodes', $hidden_shortcodes );		//save usage for log
			}
			$shortcode_tags[ $match ] = '__return_false';							//make the shortcode do nothing(hide)
		}
	}
	return $content;
}
add_filter( 'the_content', 'hos_replace_old_shortcodes' );

function hos_add_page() {
	add_management_page( 'Hide Old Shortcodes', 'Hide Old Shortcodes', 'edit_posts', 'hide-old-shortcodes', 'hos_page_content' );
}
add_action( 'admin_menu', 'hos_add_page' );

function hos_page_content() {
	$message = array();
	if( isset( $_GET['clear'] ) AND $_GET['clear'] == true ) {
		update_option( 'hos_hidden_shortcodes', array() );
	}
	
	$hidden_shortcodes = get_option( 'hos_hidden_shortcodes', array() );
	
	if( isset( $_GET['action'] ) AND isset( $hidden_shortcodes[ esc_attr( $_GET['key'] ) ] ) ) {
		switch ( $_GET['action'] ) {
			case 'escape':
				$display_shortcode = $hidden_shortcodes[ esc_attr( $_GET['key'] ) ];
				hos_escape_shortcode( $display_shortcode['post_id'], esc_attr( $_GET['key'] ), $display_shortcode['raw'] );
				$message[] = array( 'type' => 'updated', 'message' => 'Shortcode ' . $display_shortcode['raw'] . ' will now show on the frontend.' );
				unset( $hidden_shortcodes[ esc_attr( $_GET['key'] ) ] );
				break;
			
			case 'remove':
				$remove_shortcode = $hidden_shortcodes[ esc_attr( $_GET['key'] ) ];
				hos_remove_shortcode( $remove_shortcode['post_id'], esc_attr( $_GET['key'] ), $remove_shortcode['raw'] );
				$message[] = array( 'type' => 'updated', 'message' => 'Shortcode "' . $remove_shortcode['raw'] . '" has been removed.' );
				unset( $hidden_shortcodes[ esc_attr( $_GET['key'] ) ] );
				break;
			
			case 'ignore':
				$ignore_shortcode = $hidden_shortcodes[ esc_attr( $_GET['key'] ) ];
				hos_ignore_shortcode( $ignore_shortcode['post_id'], esc_attr( $_GET['key'] ), $ignore_shortcode['raw'] );
				$message[] = array( 'type' => 'updated', 'message' => 'Shortcode "' . $ignore_shortcode['raw'] . '" will be ignored.' );
				unset( $hidden_shortcodes[ esc_attr( $_GET['key'] ) ] );
				break;
		}
	}

	?>
	<div class="wrap">
	<h2>Hide Old Shortcodes</h2>
	<h4>Here is a list of shortcodes that have been blocked from being viewed in the content.</h4>
	<?php
	if( count( $message ) > 0 ){
		for ( $i=0;  $i < count( $message );  $i++ ) { 
			echo "<div class='" . $message[ $i ]['type'] . "'><p>" . $message[ $i ]['message'] . "</p></div>";
		}
	}
	?>
	<table class="widefat">
		<thead>
			<tr>
				<th>Shortcode</th>
				<th>Raw Usage</th>
				<th>Actions</th>
			</tr>
		</thead>
	<?php
	foreach ( $hidden_shortcodes as $hidden_shortcode_k => $hidden_shortcode_v ) {
		echo "<tr>
				<td>" . $hidden_shortcode_v['shortcode'] . "</td>
				<td>" . $hidden_shortcode_v['raw'] . "</td>
				<td>
					<a href='post.php?post=" . $hidden_shortcode_v['post_id'] . "&action=edit'>Edit " . $hidden_shortcode_v['post_type'] . "</a> |
					<a href='tools.php?page=hide-old-shortcodes&action=escape&key=" . $hidden_shortcode_k . "'>Escape/Allow</a> |
					<a href='tools.php?page=hide-old-shortcodes&action=ignore&key=" . $hidden_shortcode_k . "'>Ignore</a> |
					<a href='tools.php?page=hide-old-shortcodes&action=remove&key=" . $hidden_shortcode_k . "'>Remove</a>
				</td>
			</tr>";
	}
	?>
		<tr>
			<td><p><a href="tools.php?page=hide-old-shortcodes&clear=true">Clear Log</a></p></td>
			<td></td>
			<td></td>
		</tr>

	</table>
	</div>
	<?php
}

function hos_escape_shortcode( $id, $key, $raw ) {
	$post = get_post( $id, ARRAY_A );
	$post['post_content'] = str_replace( $raw, '[' . $raw . ']', $post['post_content'] );
	wp_update_post( $post );
	$shortcode_log = get_option( 'hos_hidden_shortcodes', array() );
	unset( $shortcode_log[ $key ] );
	update_option( 'hos_hidden_shortcodes', $shortcode_log );
}

function hos_remove_shortcode( $id, $key, $raw ) {
	$post = get_post( $id, ARRAY_A );
	$post['post_content'] = str_replace( $raw, '', $post['post_content'] );
	wp_update_post( $post );
	$shortcode_log = get_option( 'hos_hidden_shortcodes', array() );
	unset( $shortcode_log[ $key ] );
	update_option( 'hos_hidden_shortcodes', $shortcode_log );
}

function hos_ignore_shortcode( $id, $key, $raw  ) {
	$ignored_shortcodes = get_option( 'hos_ignored_shortcodes', array() );
	$ignored_shortcodes[ $key ] = $raw;
	update_option( 'hos_ignored_shortcodes',  $ignored_shortcodes );
	$shortcode_log = get_option( 'hos_hidden_shortcodes', array() );
	unset( $shortcode_log[ $key ] );
	update_option( 'hos_hidden_shortcodes', $shortcode_log );
}

// Load base classes for github updater
if ( is_admin() ) {
	/**
	 * Check class_exist because this could be loaded in a different plugin
	 */
	if( ! class_exists( 'GitHub_Updater' ) ) { 
		require_once( plugin_dir_path( __FILE__ ) . 'updater/class-github-updater.php' );
	}
	if( ! class_exists( 'GitHub_Updater_GitHub_API' ) ) {
		require_once( plugin_dir_path( __FILE__ ) . 'updater/class-github-api.php' );
	}
	if( ! class_exists( 'GitHub_Plugin_Updater' ) ) {
		require_once( plugin_dir_path( __FILE__ ) . 'updater/class-plugin-updater.php' );
	}
	new GitHub_Plugin_Updater;
}