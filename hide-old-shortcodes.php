<?php
/*
Plugin Name: Hide Old Shortcodes
Description: This plugin hides old/non registered shortcodes on the front end from users. It also helps you locate them so you can remove them.
Version: 0.1
Author: Mike Hansen
Author URI: http://mikehansen.me
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

function hos_return_all_shortcodes_regex() {
	return
		  '\\['                              // Opening bracket
		. '(\\[?)'                           // 1: Optional second opening bracket for escaping shortcodes: [[tag]]
		. "([\w-]+)"                     	 // 2: Shortcode name
		. '(?![\\w-])'                       // Not followed by word character or hyphen
		. '('                                // 3: Unroll the loop: Inside the opening shortcode tag
		.     '[^\\]\\/]*'                   // Not a closing bracket or forward slash
		.     '(?:'
		.         '\\/(?!\\])'               // A forward slash not followed by a closing bracket
		.         '[^\\]\\/]*'               // Not a closing bracket or forward slash
		.     ')*?'
		. ')'
		. '(?:'
		.     '(\\/)'                        // 4: Self closing tag ...
		.     '\\]'                          // ... and closing bracket
		. '|'
		.     '\\]'                          // Closing bracket
		.     '(?:'
		.         '('                        // 5: Unroll the loop: Optionally, anything between the opening and closing shortcode tags
		.             '[^\\[]*+'             // Not an opening bracket
		.             '(?:'
		.                 '\\[(?!\\/\\2\\])' // An opening bracket not followed by the closing shortcode tag
		.                 '[^\\[]*+'         // Not an opening bracket
		.             ')*+'
		.         ')'
		.         '\\[\\/\\2\\]'             // Closing shortcode tag
		.     ')?'
		. ')'
		. '(\\]?)';                          // 6: Optional second closing bracket for escaping shortcodes: [[tag]]
}

function hos_replace_old_shortcodes( $content ) {
	global $shortcode_tags;
	global $post;
	$post_type = get_post_type( $post ); 											//used later to help locate the usage
	$pattern = hos_return_all_shortcodes_regex(); 									//returns our modified regex to return ALL shortcodes
	preg_match_all( '/' . $pattern . '/s', $content, $matches, PREG_SET_ORDER );
	foreach ( $matches as $match ) {
		$raw_match = $match;														//store the raw match to show usage
		$match = $match[2];															//just the name of the shortcode
		if( ! array_key_exists( $match, $shortcode_tags ) ) { 						//check that it is not a active shortcode
			$hidden_shortcodes = get_option( 'hos_hidden_shortcodes', array() );
			if( ! array_key_exists( 'hos_' . md5( $raw_match[0] . "-" . $post_type ), $hidden_shortcodes ) ) {	//check if we already logged this, if so save time and skip
				$hidden_shortcodes[ 'hos_' . md5( $raw_match[0] . "-" . $post_type ) ] = array(
					'shortcode' => $match,
					'raw' => $raw_match[0],
					'post_type' => $post_type
					);
				update_option( 'hos_hidden_shortcodes', $hidden_shortcodes ); 		//save usage for log
			}
			$shortcode_tags[ $match ] = '__return_false'; 							//make the shortcode do nothing(hide)
		}
	}
	return $content;
}
add_filter( 'the_content', 'hos_replace_old_shortcodes' );

function hos_add_page() {
	add_management_page( 'Hide Old Shortcodes', 'Hide Old Shortcodes', 'publish_posts', 'hide-old-shortcodes', 'hos_page_content' );
}
add_action( 'admin_menu', 'hos_add_page' );

function hos_page_content() {
	if( isset( $_GET['clear'] ) AND $_GET['clear'] == true ){
		update_option( 'hos_hidden_shortcodes', array() );
	}
	$hidden_shortcodes = get_option( 'hos_hidden_shortcodes', array() );
	?>
	<div class="wrap">
	<h2>Hide Old Shortcodes</h2>
	<h4>Here is a list of shortcodes that have been blocked from being seen on the front end.</h4>
	<table class="widefat">
		<thead>
			<tr>
				<th>Shortcode</th>
				<th>Raw Usage</th>
				<th>Locate</th>					
			</tr>
		</thead>
	<?php
	foreach ( $hidden_shortcodes as $hidden_shortcode_k => $hidden_shortcode_v ) {
		echo "<tr>
				<td>" . $hidden_shortcode_v['shortcode'] . "</td>
				<td>" . $hidden_shortcode_v['raw'] . "</td>
				<td><a href='edit.php?s=" . $hidden_shortcode_v['shortcode'] . "&post_type=" . $hidden_shortcode_v['post_type'] . "'>" . $hidden_shortcode_v['post_type'] . "</a></td>
			</tr>";
	}
	?>
		<tr>
			<td><p><a href="tools.php?page=hide-old-shortcodes&clear=true">Clear Log</a></p></td>
			<td></td>
			<td></td>
		</tr>

	</table>
	<div class="error">
		<p>
		Use of this plugin should be temporary since parsing content can be expensive without caching and cause your site to load slow. 
		You should use this log to help locate and remove the shortcode if you no longer plan to use it.
		</p>
	</div>
	</div>
	<?php
}