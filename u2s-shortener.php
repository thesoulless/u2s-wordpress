<?php
	/*
	Plugin Name: U2S.ir Shortener
	Plugin URI: http://u2s.ir/wordpress
	Description: U2S.ir URL Shortener Plugin For Wordpress
	Version: 1.0
	Author: Hamed Nemati
	Author URI: http://soulless.ir/
	License: GPLv3
	*/

	class U2S {
		private $_api;

		public function __construct(){
			$this->_api = 'http://u2s.ir/?api=1&return_text=1';
			
			if ( function_exists( 'wpme_shortlink_header' ) )
			{
				remove_action( 'wp',      'wpme_shortlink_header' );
				remove_action( 'wp_head', 'wpme_shortlink_wp_head' );
			}

			add_action( 'save_post', array($this, 'generate_shortlink'), 10, 1 );
			add_filter( 'get_shortlink', array($this, 'get_shortlink'), 10, 3 );
			add_shortcode('u2s', array($this, 'shortcode'));
		}

		/**
		 * Return the get_shortlink method to the built in WordPress pre_get_shortlink
		 * filter for internal use.
		 */
		function get_shortlink( $shortlink, $id, $context )
		{

			// Look for the post ID passed by wp_get_shortlink() first
			if ( empty( $id ) )
			{
				global $post;
				$id = ( isset( $post ) ? $post->ID : null );
			}

			// Fall back in case we still don't have a post ID
			if ( empty( $id ) )
			{
				// Maybe we got passed a shortlink already? Better to return something than nothing.
				// Some wacky test cases might help us polish this up.
				if ( !empty( $shortlink ) )
					return $shortlink;

				return false;

			}

			$shortlink = get_post_meta( $id, 'short_url', true );

			if ( $shortlink == false )
			{
				$this->generate_shortlink( $id );
				$shortlink = get_post_meta( $id, 'short_url', true );
			}

			return $shortlink;

		}

		/**
		 * Generate and set the short url for post
		 */
		public function generate_shortlink( $post_id )
		{	

			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
				return false;

			// Do we need to generate a shortlink for this post? (save_post is fired when revisions, auto-drafts, et al are saved)
			if ( $parent = wp_is_post_revision( $post_id ) )
			{
				$post_id = $parent;
			}

			$post = get_post( $post_id );

			if ( 'publish' != $post->post_status && 'future' != $post->post_status )
				return false;

			// Link to be generated
			$permalink = get_permalink( $post_id );
			$link = get_post_meta( $post_id, 'short_url', true );

			if ( $link != false )
			{
				$args = array( 'return_long' => '1', 'url' => urlencode( $link ));
				$arguments[] = '';
				
				foreach($args as $each=>$value){
					$arguments[] = $each.'='.$value;
				}

				$api_return = '';

				$api_response = wp_remote_get($this->_api . (implode('&',$arguments)));

				// check if the shortlink realy points to this post
				if ( is_array( $response ) && $response['status_code'] == 200 && $api_response['body'] == $permalink )
					return false;

				// The expanded URLs don't match, so we can delete and regenerate
				delete_post_meta( $post_id, 'short_url' );
			}
			
			$args = array('url' => urlencode( $permalink ));
			$arguments[] = '';
			
			foreach($args as $each=>$value){
				$arguments[] = $each.'='.$value;
			}

			$api_return = '';

			$api_response = wp_remote_get($this->_api . (implode('&',$arguments)));
			
			if(!is_wp_error($api_response)){
				if (intval($api_response['response']['code']) == 200) {	
					$response = $api_response['body'];
					if(strlen($response) > 2){
						$api_return = $response;
					} else {
						return false;
					}
				} else {
					return false;
				}
			} else {
				return false;
			}

			update_post_meta( $post_id, 'short_url',  $api_return );		
		}

		/**
		 * Return short links for lins on post content
		 */
		private function create($url, $type, $custom=null){
			$url = urlencode($url);		

			if( $type === 'custom' ){
				if(strlen($custom) >= 3 && strlen($custom) <= 10){
					$args = array('url' => $url , 'custom_data' => $custom);
				}
				else{
					return 'ERROR';
				}
			} else {
				$args = array('url' => $url);
			}
			
			$arguments[] = '';
			foreach($args as $each=>$value){
				$arguments[] = $each.'='.$value;
			}

			$api_return = '';

			$api_response = wp_remote_get($this->_api . (implode('&',$arguments)));
			
			if ( !is_wp_error($api_response) && is_array( $response ) && $response['status_code'] == 200 ) {
				$response = $api_response['body'];
					if ( strlen($response) > 2 )
						$api_return = $response;
			} else {
				$api_return =  'ERROR';
			}		

			return $api_return;
		}

		public function shortcode($attr){
			global $post;
			if(get_post_meta($post->ID, 'short_url', true) != ""){
				$short_url = get_post_meta($post->ID, 'short_url', true);
			}else{
				extract(shortcode_atts(array(
					'type' => '', // custom
					'custom' => null,
				), $attr));

				$full_url = get_permalink();
				$short_url = $this->create($full_url, $type, $custom);

				if($short_url != 'ERROR'){
					add_post_meta($post->ID, 'short_url', $short_url, true);
				}
				else{
					$short_url = $full_url;
				}
			}
			if($link == 'true'){
				$short_url = '<a href="'.$short_url.'">لینک کوتاه</a>';
			}
			return $short_url;
		}

	}

	$u2s = new U2S();

?>