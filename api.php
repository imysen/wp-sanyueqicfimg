<?php

class ImgBedApi {
	private $base_url;
	private $api_token;
	private $upload_channel;
	private $channel_name;
	private $upload_name_type;
	private $return_format;
	private $upload_folder;
	private $auto_retry;
	private $server_compress;

	public function __construct( $options ) {
		if ( ! is_array( $options ) ) {
			throw new \InvalidArgumentException( 'Invalid ImgBed options' );
		}
		$this->base_url        = rtrim( (string) ( $options['imgbed_base_url'] ?? '' ), '/' );
		$this->api_token       = trim( (string) ( $options['imgbed_api_token'] ?? '' ) );
		$this->upload_channel  = trim( (string) ( $options['imgbed_upload_channel'] ?? '' ) );
		$this->channel_name    = trim( (string) ( $options['imgbed_channel_name'] ?? '' ) );
		$this->upload_name_type = trim( (string) ( $options['imgbed_upload_name_type'] ?? '' ) );
		$this->return_format   = trim( (string) ( $options['imgbed_return_format'] ?? '' ) );
		$this->upload_folder   = trim( (string) ( $options['imgbed_upload_folder'] ?? '' ) );
		$this->auto_retry      = isset( $options['imgbed_auto_retry'] ) ? (bool) $options['imgbed_auto_retry'] : true;
		$this->server_compress = isset( $options['imgbed_server_compress'] ) ? (bool) $options['imgbed_server_compress'] : true;

		if ( '' === $this->base_url ) {
			throw new \InvalidArgumentException( 'CloudFlare ImgBed base URL is required' );
		}
	}

	public function upload( $key, $file_local_path ) {
		if ( ! is_readable( $file_local_path ) ) {
			throw new \RuntimeException( 'Local file is not readable: ' . $file_local_path );
		}
		if ( ! function_exists( 'curl_init' ) ) {
			throw new \RuntimeException( 'cURL extension is required for multipart upload' );
		}

		$query = array();
		if ( '' !== $this->upload_channel ) {
			$query['uploadChannel'] = $this->upload_channel;
		}
		if ( '' !== $this->channel_name ) {
			$query['channelName'] = $this->channel_name;
		}
		if ( '' !== $this->upload_name_type ) {
			$query['uploadNameType'] = $this->upload_name_type;
		}
		if ( '' !== $this->return_format ) {
			$query['returnFormat'] = $this->return_format;
		}
		$upload_folder = $this->resolve_upload_folder( $key );
		if ( '' !== $upload_folder ) {
			$query['uploadFolder'] = $upload_folder;
		}
		$query['autoRetry'] = $this->auto_retry ? 'true' : 'false';
		$query['serverCompress'] = $this->server_compress ? 'true' : 'false';

		$url = $this->build_url( '/upload', $query );

		$mime_type = function_exists( 'mime_content_type' ) ? mime_content_type( $file_local_path ) : '';
		$curl_file = curl_file_create( $file_local_path, $mime_type ?: 'application/octet-stream', basename( $file_local_path ) );

		$headers = array();
		$auth_header = $this->build_auth_header();
		if ( '' !== $auth_header ) {
			$headers[] = 'Authorization: ' . $auth_header;
		}

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, array( 'file' => $curl_file ) );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 120 );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 15 );
		if ( ! empty( $headers ) ) {
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		}

		$response_body = curl_exec( $ch );
		$status_code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$curl_error = curl_error( $ch );
		curl_close( $ch );

		if ( '' !== $curl_error ) {
			throw new \RuntimeException( 'ImgBed upload failed: ' . $curl_error );
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			throw new \RuntimeException( 'ImgBed upload failed with HTTP ' . $status_code . ': ' . (string) $response_body );
		}

		$decoded = json_decode( (string) $response_body, true );
		if ( ! is_array( $decoded ) ) {
			throw new \RuntimeException( 'ImgBed upload response is invalid JSON' );
		}
		return $decoded;
	}

	public function delete( $keys ) {
		if ( ! is_array( $keys ) || empty( $keys ) ) {
			return;
		}
		foreach ( $keys as $key ) {
			$path = ltrim( (string) $key, '/' );
			if ( '' === $path ) {
				continue;
			}
			$encoded_path = $this->encode_path_for_url( $path );
			$url = $this->build_url( '/api/manage/delete/' . $encoded_path );
			$response = wp_remote_request(
				$url,
				array(
					'method'  => 'DELETE',
					'headers' => $this->build_headers( true ),
					'timeout' => 30,
				)
			);
			if ( is_wp_error( $response ) ) {
				continue;
			}
			$status_code = (int) wp_remote_retrieve_response_code( $response );
			if ( $status_code < 200 || $status_code >= 300 ) {
				continue;
			}
		}
	}

	public function has_exist( $key ) {
		$key = ltrim( (string) $key, '/' );
		if ( '' === $key ) {
			return false;
		}
		$dir = trim( dirname( $key ), '.' );
		if ( '.' === $dir ) {
			$dir = '';
		}
		$base_name = basename( $key );

		$query = array(
			'dir'       => $dir,
			'search'    => $base_name,
			'count'     => 200,
			'recursive' => 'false',
		);

		$response = $this->list_files( $query );
		if ( ! is_array( $response ) || empty( $response['files'] ) || ! is_array( $response['files'] ) ) {
			return false;
		}
		foreach ( $response['files'] as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['name'] ) ) {
				continue;
			}
			$remote_name = ltrim( (string) $item['name'], '/' );
			if ( $remote_name === $key || basename( $remote_name ) === $base_name ) {
				return true;
			}
		}
		return false;
	}

	public function list_files( $query = array() ) {
		$response = wp_remote_get(
			$this->build_url( '/api/manage/list', $query ),
			array(
				'headers' => $this->build_headers( true ),
				'timeout' => 30,
			)
		);
		if ( is_wp_error( $response ) ) {
			return array();
		}
		$body = wp_remote_retrieve_body( $response );
		$decoded = json_decode( (string) $body, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	public function random_file() {
		$response = wp_remote_get(
			$this->build_url( '/random' ),
			array(
				'timeout' => 15,
			)
		);
		if ( is_wp_error( $response ) ) {
			return '';
		}
		return (string) wp_remote_retrieve_body( $response );
	}

	private function resolve_upload_folder( $key ) {
		$key_dir = trim( dirname( ltrim( (string) $key, '/' ) ), '.' );
		if ( '.' === $key_dir ) {
			$key_dir = '';
		}
		$custom_folder = trim( $this->upload_folder, '/' );
		if ( '' === $custom_folder ) {
			return $key_dir;
		}
		if ( '' === $key_dir ) {
			return $custom_folder;
		}
		return $custom_folder . '/' . $key_dir;
	}

	private function build_url( $path, $query = array() ) {
		$path = '/' . ltrim( (string) $path, '/' );
		$url = $this->base_url . $path;
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}
		return $url;
	}

	private function build_headers( $require_auth = false ) {
		$headers = array();
		$auth_header = $this->build_auth_header();
		if ( '' !== $auth_header ) {
			$headers['Authorization'] = $auth_header;
		}
		return $headers;
	}

	private function build_auth_header() {
		if ( '' === $this->api_token ) {
			return '';
		}
		return 'Bearer ' . $this->api_token;
	}

	private function encode_path_for_url( $path ) {
		$segments = array_filter( explode( '/', str_replace( '\\', '/', $path ) ), 'strlen' );
		$encoded = array_map( 'rawurlencode', $segments );
		return implode( '/', $encoded );
	}
}
