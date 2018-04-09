<?php
/**
 * Library for accessing the Bugsnag API on WordPress
 *
 * @link https://bugsnagapiv2.docs.apiary.io/# API Documentation
 * @package WP-API-Libraries\WP-Bugsnag-API
 */

/*
 * Plugin Name: Bugsnag API
 * Plugin URI: https://wp-api-libraries.com/
 * Description: Perform API requests.
 * Author: WP API Libraries
 * Version: 1.0.0
 * Author URI: https://wp-api-libraries.com
 * GitHub Plugin URI: https://github.com/imforza
 * GitHub Branch: master
 */

/* Exit if accessed directly */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Basecamp3API' ) ) {

	/**
	 * A WordPress API library for accessing the Bugsnag API.
	 *
	 * @version 1.1.0
	 * @link https://bugsnagapiv2.docs.apiary.io/# API Documentation
	 * @package WP-API-Libraries\WP-Bugsnag-API
	 * @author Santiago Garza <https://github.com/sfgarza>
	 * @author imFORZA <https://github.com/imforza>
	 */
	class WPBugsnagAPI {

		/**
		 * API Key.
		 *
		 * @var string
		 */
		static protected $api_key;

		/**
		 * Bugsnag BaseAPI Endpoint
		 *
		 * @var string
		 * @access protected
		 */
		protected $base_uri = 'https://api.bugsnag.com/';

		/**
		 * Route being called.
		 *
		 * @var string
		 */
		protected $route = '';
		
		/**
		 * Pagination links.
		 *
		 * @var string
		 */
		public $links;
		
		/**
		 * total
		 * 
		 * @var mixed
		 * @access public
		 */
		public $total;
		
		/**
		 * is_next
		 * 
		 * (default value: false)
		 * 
		 * @var bool
		 * @access private
		 */
		private $is_next = false;


		/**
		 * Class constructor.
		 *
		 * @param string $api_key               Cloudflare API Key.
		 * @param string $auth_email            Email associated to the account.
		 * @param string $user_service_key      User Service key.
		 */
		public function __construct( $api_key ) {
			static::$api_key = trim( $api_key );
		}

		/**
		 * Prepares API request.
		 *
		 * @param  string $route   API route to make the call to.
		 * @param  array  $args    Arguments to pass into the API call.
		 * @param  array  $method  HTTP Method to use for request.
		 * @return self            Returns an instance of itself so it can be chained to the fetch method.
		 */
		protected function build_request( $route, $args = array(), $method = 'GET' ) {
			// Start building query.
			$this->set_headers();
			$this->args['method'] = $method;
			$this->route = $route;

			// Generate query string for GET requests.
			if ( 'GET' === $method ) {
				$this->route = add_query_arg( array_filter( $args ), $route );
			} elseif ( 'application/json' === $this->args['headers']['Content-Type'] ) {
				$this->args['body'] = wp_json_encode( $args );
			} else {
				$this->args['body'] = $args;
			}

			return $this;
		}


		/**
		 * Fetch the request from the API.
		 *
		 * @access private
		 * @return array|WP_Error Request results or WP_Error on request failure.
		 */
		protected function fetch() {
			// Make the request.
			$url = ( true === $this->is_next ) ?  $this->links['next'] : $this->base_uri . $this->route;
			$response = wp_remote_request( $url, $this->args );

			// Retrieve Status code & body.
			$code = wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ) );
			$this->set_links( $response );
      $this->get_total_count( $response );
			
			$this->clear();
			// Return WP_Error if request is not successful.
			if ( ! $this->is_status_ok( $code ) ) {
				return new WP_Error( 'response-error', sprintf( __( 'Status: %d', 'wp-postmark-api' ), $code ), $body );
			}

			return $body;
		}

		/**
		 * set_links function.
		 *
		 * @access protected
		 * @param mixed $response
		 * @return void
		 */
		protected function set_links( $response ){
			$this->links = array();

			// Get links from response header.
			$links = wp_remote_retrieve_header( $response, 'link' );

			// Parse the string into a convenient array.
			$links = explode( ',', $links );
			if( ! empty( $links ) ){

				foreach ( $links as $link ) {
					$tmp =  explode( ";", $link );
					$res = preg_match('~<(.*?)>~',$tmp[0], $match );

					if( ! empty( $res ) ){
						// Some string magic to set array key. Changes 'rel="next"' => 'next'.
						$key = str_replace( array( 'rel=', '"' ),'',trim($tmp[1]));
						$this->links[$key] = $match[1];

					}
				}
			}
		}
		
		/**
		 * get_total_count function.
		 * 
		 * @access protected
		 * @param mixed $response
		 * @return void
		 */
		protected function get_total_count( $response ){
			$this->total = wp_remote_retrieve_header( $response, 'x-total-count' );
		}


		/**
		 * Set request headers.
		 */
		protected function set_headers() {
			// Set request headers.
			$this->args['headers'] = array(
					'X-Version' => 2,
					'Content-Type' => 'application/json',
					'Authorization' => 'token ' . static::$api_key,
			);
		}

		/**
		 * Clear query data.
		 */
		protected function clear() {
			$this->args = array();
			$this->query_args = array();
		}

		/**
		 * Check if HTTP status code is a success.
		 *
		 * @param  int $code HTTP status code.
		 * @return boolean       True if status is within valid range.
		 */
		protected function is_status_ok( $code ) {
			return ( 200 <= $code && 300 > $code );
		}
		
		/**
		 * next function.
		 * 
		 * @access public
		 * @return void
		 */
		public function next(){
			
			if( $this->has_next() ){
				$this->is_next = true;
				
				$this->set_headers();
				$this->args['method'] = 'GET'; //Pagination is always a GET request.
				
				$response = $this->fetch();
				
				$this->is_next = false;
				return $response;
			}
			
			return false;
		}
		
		/**
		 * has_next function.
		 * 
		 * @access public
		 * @return void
		 */
		public function has_next(){
			return isset( $this->links['next'] );
		}

		/**
		 * Wrapper for $this->build_request()->fetch();, for brevity.
		 *
		 * @param  string $route  The route to hit.
		 * @param  array  $args   (Default: array()) arguments to pass.
		 * @param  string $method (Default: 'GET') the method of the call.
		 * @return mixed          The results of the call.
		 */
		protected function run( $route, $args = array(), $method = 'GET' ) {
			return $this->build_request( $route, $args, $method )->fetch();
		}

		/**
		 * Get Current User Organizations
		 *
		 * @api GET
		 * @see https://bugsnagapiv2.docs.apiary.io/#reference/current-user/organizations/list-the-current-user's-organizations Documentation.
		 * @access public
		 * @return array  User information.
		 */
		public function get_user_orgs( $args = array() ) {
			return $this->run( 'user/organizations', $args );
		}
		
		/**
		 * Get Current User Projects
		 *
		 * @api GET
		 * @see https://bugsnagapiv2.docs.apiary.io/#reference/current-user/organizations/list-the-current-user's-projects Documentation.
		 * @access public
		 * @return array  User information.
		 */
		public function get_user_projects( $org_id, $args = array() ) {
			return $this->run( "organizations/$org_id/projects", $args );
		}
		
		/**
		 * create_project_in_org function.
		 * 
		 * @access public
		 * @param mixed $org_id
		 * @param mixed $name
		 * @param string $type (default: 'wordpress')
		 * @param bool $ignore_old_browsers (default: true)
		 * @return void
		 */
		public function create_project_in_org( $org_id, $name, $type = 'wordpress', $ignore_old_browsers = true ){
			$args = compact ( 'name', 'type', 'ignore_old_browsers' );
			return $this->run( "organizations/$org_id/projects", $args, 'POST' );
		}
		
		/**
		 * Get Project Errors
		 *
		 * @api GET
		 * @see https://bugsnagapiv2.docs.apiary.io/#reference/errors/errors/list-the-errors-on-a-project Documentation.
		 * @access public
		 * @return array Project Errors.
		 */
		public function get_project_errors( $project_id, $args = array() ) {
			return $this->run( "projects/$project_id/errors", $args );
		}


	}
}
