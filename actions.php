<?php
require_once 'api.php';
# SDK最低支持版本

define( 'WPSANYUEIMG_VERSION', 4.1 );  // 插件数据版本
define( 'WPSANYUEIMG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );  // 插件路径
define( 'WPSANYUEIMG_BASENAME', plugin_basename(__FILE__));
define( 'WPSANYUEIMG_BASEFOLDER', plugin_basename(dirname(__FILE__)));
define( 'WPSANYUEIMG_PLUGIN_FILE', plugin_basename( WPSANYUEIMG_PLUGIN_DIR . 'index.php' ) );
define( 'WPSANYUEIMG_DEFAULT_UPDATE_SOURCE_URL', 'https://github-updateapi.112601.xyz/wp-sanyueqicfimg/update.json' );

function wpsanyueimg_get_options() {
	$options = get_option('wpsanyueimg_options');
	if ( is_array( $options ) ) {
		return $options;
	}
	return array();
}

function wpsanyueimg_get_plugin_version() {
	if ( ! function_exists( 'get_file_data' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$data = get_file_data( WPSANYUEIMG_PLUGIN_DIR . 'index.php', array( 'Version' => 'Version' ), 'plugin' );
	$version = isset( $data['Version'] ) ? trim( (string) $data['Version'] ) : '';
	return '' !== $version ? $version : '0.0.0';
}

function wpsanyueimg_normalize_version( $version ) {
	$version = trim( (string) $version );
	$version = preg_replace( '/^v/i', '', $version );
	return $version;
}

function wpsanyueimg_get_update_source_url() {
	$options = wpsanyueimg_get_options();
	$url = is_array( $options ) ? (string) ( $options['update_source_url'] ?? '' ) : '';
	if ( '' === trim( $url ) ) {
		$url = WPSANYUEIMG_DEFAULT_UPDATE_SOURCE_URL;
	}
	$url = esc_url_raw( trim( $url ) );
	if ( '' === $url ) {
		return '';
	}
	if ( 0 !== strpos( $url, 'https://' ) ) {
		return '';
	}
	return $url;
}

function wpsanyueimg_validate_update_meta( $meta ) {
	if ( ! is_array( $meta ) ) {
		return array();
	}

	$new_version = wpsanyueimg_normalize_version( $meta['new_version'] ?? '' );
	$download_url = esc_url_raw( (string) ( $meta['download_url'] ?? '' ) );
	if ( '' === $new_version || '' === $download_url ) {
		return array();
	}

	return array(
		'plugin'       => sanitize_text_field( (string) ( $meta['plugin'] ?? WPSANYUEIMG_PLUGIN_FILE ) ),
		'new_version'  => $new_version,
		'download_url' => $download_url,
		'requires'     => sanitize_text_field( (string) ( $meta['requires'] ?? '' ) ),
		'requires_php' => sanitize_text_field( (string) ( $meta['requires_php'] ?? '' ) ),
		'tested'       => sanitize_text_field( (string) ( $meta['tested'] ?? '' ) ),
		'changelog'    => wp_kses_post( (string) ( $meta['changelog'] ?? '' ) ),
		'homepage'     => esc_url_raw( (string) ( $meta['homepage'] ?? '' ) ),
		'last_updated' => sanitize_text_field( (string) ( $meta['last_updated'] ?? '' ) ),
	);
}

function wpsanyueimg_fetch_update_meta( $force = false ) {
	$cache_key = 'wpsanyueimg_update_meta';
	if ( ! $force ) {
		$cached = get_site_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
	}

	$source_url = wpsanyueimg_get_update_source_url();
	if ( '' === $source_url ) {
		return array();
	}

	$response = wp_remote_get(
		$source_url,
		array(
			'timeout' => 15,
			'headers' => array(
				'Accept' => 'application/json',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		wpsanyueimg_log(
			'update_meta_fetch_error',
			array(
				'url' => $source_url,
				'error' => $response->get_error_message(),
			)
		);
		return array();
	}

	$status = (int) wp_remote_retrieve_response_code( $response );
	$body = (string) wp_remote_retrieve_body( $response );
	if ( $status < 200 || $status >= 300 ) {
		wpsanyueimg_log(
			'update_meta_fetch_http_error',
			array(
				'url' => $source_url,
				'status' => $status,
				'body' => mb_substr( $body, 0, 300 ),
			)
		);
		return array();
	}

	$decoded = json_decode( $body, true );
	$meta = wpsanyueimg_validate_update_meta( $decoded );
	if ( empty( $meta ) ) {
		wpsanyueimg_log(
			'update_meta_invalid',
			array(
				'url' => $source_url,
				'body' => mb_substr( $body, 0, 300 ),
			)
		);
		return array();
	}

	set_site_transient( $cache_key, $meta, 6 * HOUR_IN_SECONDS );
	return $meta;
}

function wpsanyueimg_check_plugin_update( $transient ) {
	if ( ! is_object( $transient ) ) {
		$transient = new stdClass();
	}

	$meta = wpsanyueimg_fetch_update_meta( false );
	if ( empty( $meta ) ) {
		return $transient;
	}

	$current_version = wpsanyueimg_normalize_version( wpsanyueimg_get_plugin_version() );
	$remote_version = wpsanyueimg_normalize_version( $meta['new_version'] ?? '' );
	if ( '' === $remote_version || version_compare( $remote_version, $current_version, '<=' ) ) {
		return $transient;
	}

	$transient->response[ WPSANYUEIMG_PLUGIN_FILE ] = (object) array(
		'id'           => WPSANYUEIMG_PLUGIN_FILE,
		'slug'         => WPSANYUEIMG_BASEFOLDER,
		'plugin'       => WPSANYUEIMG_PLUGIN_FILE,
		'new_version'  => $remote_version,
		'url'          => ! empty( $meta['homepage'] ) ? $meta['homepage'] : 'https://github.com/imysen/wp-sanyueqicfimg',
		'package'      => $meta['download_url'],
		'tested'       => $meta['tested'] ?? '',
		'requires'     => $meta['requires'] ?? '',
		'requires_php' => $meta['requires_php'] ?? '',
	);

	return $transient;
}

function wpsanyueimg_plugins_api( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || ! isset( $args->slug ) || WPSANYUEIMG_BASEFOLDER !== $args->slug ) {
		return $result;
	}

	$meta = wpsanyueimg_fetch_update_meta( false );
	if ( empty( $meta ) ) {
		return $result;
	}

	$sections = array(
		'description' => 'CloudFlare ImgBed 媒体存储插件。',
		'changelog'   => ! empty( $meta['changelog'] ) ? $meta['changelog'] : '暂无更新日志。',
	);

	return (object) array(
		'name'          => 'WP-SanyueqiCfimg',
		'slug'          => WPSANYUEIMG_BASEFOLDER,
		'version'       => $meta['new_version'],
		'author'        => '<a href="https://blog.imysen.com">邹云森森</a>',
		'homepage'      => ! empty( $meta['homepage'] ) ? $meta['homepage'] : 'https://github.com/imysen/wp-sanyueqicfimg',
		'download_link' => $meta['download_url'],
		'requires'      => $meta['requires'] ?? '',
		'requires_php'  => $meta['requires_php'] ?? '',
		'tested'        => $meta['tested'] ?? '',
		'last_updated'  => $meta['last_updated'] ?? '',
		'sections'      => $sections,
	);
}

function wpsanyueimg_auto_update_plugin( $update, $item ) {
	if ( ! is_object( $item ) || empty( $item->plugin ) || WPSANYUEIMG_PLUGIN_FILE !== $item->plugin ) {
		return $update;
	}

	$options = wpsanyueimg_get_options();
	if ( is_array( $options ) && ! empty( $options['enable_auto_update'] ) ) {
		return true;
	}

	return $update;
}

function wpsanyueimg_force_check_update_now() {
	delete_site_transient( 'wpsanyueimg_update_meta' );
	delete_site_transient( 'update_plugins' );
	$meta = wpsanyueimg_fetch_update_meta( true );
	if ( function_exists( 'wp_update_plugins' ) ) {
		wp_update_plugins();
	}
	return ! empty( $meta );
}

function wpsanyueimg_get_update_summary() {
	$meta = wpsanyueimg_fetch_update_meta( true );
	$current_version = wpsanyueimg_normalize_version( wpsanyueimg_get_plugin_version() );
	$remote_version = wpsanyueimg_normalize_version( $meta['new_version'] ?? '' );
	$has_update = '' !== $remote_version && version_compare( $remote_version, $current_version, '>' );

	$homepage = ! empty( $meta['homepage'] ) ? esc_url_raw( (string) $meta['homepage'] ) : 'https://github.com/imysen/wp-sanyueqicfimg';
	$release_url = '';
	if ( '' !== $homepage ) {
		$release_url = trailingslashit( untrailingslashit( $homepage ) ) . 'releases';
	}

	return array(
		'has_update' => $has_update,
		'current_version' => $current_version,
		'new_version' => $remote_version,
		'changelog' => (string) ( $meta['changelog'] ?? '' ),
		'homepage' => $homepage,
		'github_release_url' => $release_url,
		'download_url' => (string) ( $meta['download_url'] ?? '' ),
	);
}

function wpsanyueimg_collect_files_recursive( $base_dir, $start_time, $timeout_seconds ) {
	$files = array();
	$base_dir = rtrim( (string) $base_dir, '/' );
	if ( '' === $base_dir || ! is_dir( $base_dir ) ) {
		return $files;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $base_dir, RecursiveDirectoryIterator::SKIP_DOTS )
	);

	foreach ( $iterator as $item ) {
		if ( microtime( true ) - $start_time > $timeout_seconds ) {
			throw new RuntimeException( '更新超时（超过60秒）。' );
		}
		if ( ! $item->isFile() ) {
			continue;
		}

		$full_path = (string) $item->getPathname();
		$relative = ltrim( substr( $full_path, strlen( $base_dir ) ), '/' );
		if ( '' === $relative ) {
			continue;
		}
		if ( 0 === strpos( $relative, '.git/' ) || '.git' === $relative ) {
			continue;
		}

		$files[ $relative ] = $full_path;
	}

	return $files;
}

function wpsanyueimg_find_extracted_root( $extract_dir ) {
	$extract_dir = rtrim( (string) $extract_dir, '/' );
	$index_file = $extract_dir . '/index.php';
	if ( file_exists( $index_file ) ) {
		return $extract_dir;
	}

	$children = glob( $extract_dir . '/*', GLOB_ONLYDIR );
	if ( ! is_array( $children ) ) {
		return '';
	}

	foreach ( $children as $child ) {
		if ( file_exists( rtrim( $child, '/' ) . '/index.php' ) ) {
			return rtrim( $child, '/' );
		}
	}

	return '';
}

function wpsanyueimg_perform_safe_update( $meta ) {
	$start_time = microtime( true );
	$timeout_seconds = 60;

	$download_url = esc_url_raw( (string) ( $meta['download_url'] ?? '' ) );
	if ( '' === $download_url ) {
		throw new RuntimeException( '缺少更新包下载地址。' );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';

	$upload_dir = wp_upload_dir();
	$base_dir = is_array( $upload_dir ) && ! empty( $upload_dir['basedir'] ) ? $upload_dir['basedir'] : WP_CONTENT_DIR;
	$update_base_dir = trailingslashit( $base_dir ) . 'wpsanyueqicfimg-update';
	if ( ! file_exists( $update_base_dir ) && ! wp_mkdir_p( $update_base_dir ) ) {
		throw new RuntimeException( '无法创建更新目录。' );
	}

	$job_dir = trailingslashit( $update_base_dir ) . 'job-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false, false );
	if ( ! wp_mkdir_p( $job_dir ) ) {
		throw new RuntimeException( '无法创建更新任务目录。' );
	}

	$tmp_zip = download_url( $download_url, $timeout_seconds );
	if ( is_wp_error( $tmp_zip ) ) {
		throw new RuntimeException( '下载更新包失败：' . $tmp_zip->get_error_message() );
	}

	$extract_result = unzip_file( $tmp_zip, $job_dir );
	@unlink( $tmp_zip );
	if ( is_wp_error( $extract_result ) ) {
		throw new RuntimeException( '解压更新包失败：' . $extract_result->get_error_message() );
	}

	if ( microtime( true ) - $start_time > $timeout_seconds ) {
		throw new RuntimeException( '更新超时（超过60秒）。' );
	}

	$source_root = wpsanyueimg_find_extracted_root( $job_dir );
	if ( '' === $source_root ) {
		throw new RuntimeException( '无法定位更新包根目录。' );
	}

	$target_root = rtrim( WPSANYUEIMG_PLUGIN_DIR, '/' );
	$source_files = wpsanyueimg_collect_files_recursive( $source_root, $start_time, $timeout_seconds );
	$target_files = wpsanyueimg_collect_files_recursive( $target_root, $start_time, $timeout_seconds );

	$changed = array();
	foreach ( $source_files as $relative => $source_file ) {
		if ( microtime( true ) - $start_time > $timeout_seconds ) {
			throw new RuntimeException( '更新超时（超过60秒）。' );
		}
		$target_file = $target_root . '/' . $relative;
		if ( ! isset( $target_files[ $relative ] ) ) {
			$changed[] = $relative;
			continue;
		}
		$src_hash = @md5_file( $source_file );
		$dst_hash = @md5_file( $target_file );
		if ( false === $src_hash || false === $dst_hash || $src_hash !== $dst_hash ) {
			$changed[] = $relative;
		}
	}

	$replaced = 0;
	foreach ( $changed as $relative ) {
		if ( microtime( true ) - $start_time > $timeout_seconds ) {
			throw new RuntimeException( '更新超时（超过60秒）。' );
		}
		$source_file = $source_root . '/' . $relative;
		$target_file = $target_root . '/' . $relative;
		$target_dir = dirname( $target_file );
		if ( ! file_exists( $target_dir ) && ! wp_mkdir_p( $target_dir ) ) {
			throw new RuntimeException( '创建目录失败：' . $target_dir );
		}
		if ( ! @copy( $source_file, $target_file ) ) {
			throw new RuntimeException( '替换文件失败：' . $relative );
		}
		$replaced++;
	}

	if ( function_exists( 'wp_opcache_invalidate_directory' ) ) {
		wp_opcache_invalidate_directory( $target_root );
	}

	return array(
		'update_dir' => $job_dir,
		'changed_count' => count( $changed ),
		'replaced_count' => $replaced,
		'time_cost' => round( microtime( true ) - $start_time, 3 ),
	);
}

function wpsanyueimg_ajax_check_update_popup() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => '权限不足。' ), 403 );
	}
	check_ajax_referer( 'wpsanyueimg_update_nonce', 'nonce' );
	$options = wpsanyueimg_get_options();
	$manual = isset( $_POST['manual'] ) && '1' === (string) $_POST['manual'];
	if ( ! $manual && ( ! is_array( $options ) || empty( $options['enable_auto_update'] ) ) ) {
		wp_send_json_error( array( 'message' => '未开启自动更新，已跳过弹窗检测。' ), 400 );
	}

	$summary = wpsanyueimg_get_update_summary();
	if ( '' === (string) ( $summary['new_version'] ?? '' ) || '' === (string) ( $summary['download_url'] ?? '' ) ) {
		wp_send_json_error( array( 'message' => '更新信息获取失败，请检查更新源地址或网络。' ), 500 );
	}
	wp_send_json_success( $summary );
}

function wpsanyueimg_ajax_apply_update() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => '权限不足。' ), 403 );
	}
	check_ajax_referer( 'wpsanyueimg_update_nonce', 'nonce' );
	$options = wpsanyueimg_get_options();
	if ( ! is_array( $options ) || empty( $options['enable_auto_update'] ) ) {
		wp_send_json_error( array( 'message' => '未开启自动更新，已禁止执行文件替换。' ), 400 );
	}

	$summary = wpsanyueimg_get_update_summary();
	if ( '' === (string) ( $summary['new_version'] ?? '' ) || '' === (string) ( $summary['download_url'] ?? '' ) ) {
		wp_send_json_error( array( 'message' => '更新信息获取失败，请检查更新源地址或网络。' ), 500 );
	}
	if ( empty( $summary['has_update'] ) ) {
		wp_send_json_error( array( 'message' => '当前已是最新版本，无需更新。' ), 400 );
	}

	try {
		$result = wpsanyueimg_perform_safe_update(
			array(
				'download_url' => $summary['download_url'],
			)
		);
		wpsanyueimg_log(
			'plugin_safe_update_success',
			array(
				'summary' => $summary,
				'result' => $result,
			)
		);
		wp_send_json_success(
			array(
				'message' => '更新完成。功能仍处于测试阶段，建议你手动下载压缩包再次核对。',
				'result' => $result,
			)
		);
	} catch ( Throwable $e ) {
		wpsanyueimg_log(
			'plugin_safe_update_failed',
			array(
				'error' => $e->getMessage(),
				'summary' => $summary,
			)
		);
		wp_send_json_error( array( 'message' => '更新失败：' . $e->getMessage() ), 500 );
	}
}

function wpsanyueimg_is_log_enabled() {
	$options = wpsanyueimg_get_options();
	if ( is_array( $options ) && array_key_exists( 'enable_log', $options ) ) {
		return ! empty( $options['enable_log'] );
	}
	return true;
}

function wpsanyueimg_log( $message, $context = array() ) {
	if ( ! wpsanyueimg_is_log_enabled() ) {
		return;
	}

	$upload_dir = wp_upload_dir();
	$base_dir = is_array( $upload_dir ) && ! empty( $upload_dir['basedir'] ) ? $upload_dir['basedir'] : WP_CONTENT_DIR;
	$log_file = trailingslashit( $base_dir ) . 'wpsanyueqicfimg.log';

	$line = '[' . current_time( 'mysql' ) . '] ' . (string) $message;
	if ( ! empty( $context ) ) {
		$context_json = wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( is_string( $context_json ) && '' !== $context_json ) {
			$line .= ' | ' . $context_json;
		}
	}

	error_log( $line . PHP_EOL, 3, $log_file );
}

function wpsanyueimg_build_delete_candidates( $keys, $options = array() ) {
	if ( ! is_array( $keys ) ) {
		return array();
	}

	$upload_folder = trim( (string) ( $options['imgbed_upload_folder'] ?? '' ), '/' );
	$candidates = array();

	foreach ( $keys as $key ) {
		$key = ltrim( (string) $key, '/' );
		if ( '' === $key ) {
			continue;
		}
		$candidates[] = $key;

		if ( '' === $upload_folder ) {
			continue;
		}

		$prefix = $upload_folder . '/';
		if ( 0 === strpos( $key, $prefix ) ) {
			$stripped_key = ltrim( substr( $key, strlen( $prefix ) ), '/' );
			if ( '' !== $stripped_key ) {
				$candidates[] = $stripped_key;
			}
		} else {
			$candidates[] = $prefix . $key;
		}
	}

	return array_values( array_unique( array_filter( $candidates ) ) );
}

// 初始化选项
function wpsanyueimg_set_options() {
    $options = array(
	    'version' => WPSANYUEIMG_VERSION,  # 用于以后当有数据结构升级时初始化数据
	    'imgbed_base_url' => "",
		'imgbed_api_token' => "",
		'imgbed_upload_channel' => "",
		'imgbed_channel_name' => "",
		'imgbed_upload_name_type' => "",
		'imgbed_return_format' => "",
		'imgbed_upload_folder' => "",
		'imgbed_auto_retry' => true,
		'imgbed_server_compress' => true,
		'update_source_url' => WPSANYUEIMG_DEFAULT_UPDATE_SOURCE_URL,
		'enable_auto_update' => true,
		'enable_log' => true,
	    'no_local_file' => false,  # 不在本地保留备份
        'opt' => array(
            'auto_rename' => false,
        ),  # 此行往下为2.0版本新增选项
	);
	$wpsanyueimg_options = wpsanyueimg_get_options();
	if ( empty( $wpsanyueimg_options ) ) {
        add_option('wpsanyueimg_options', $options, '', 'yes');
		$wpsanyueimg_options = $options;
	} elseif ( is_array( $wpsanyueimg_options ) ) {
		$wpsanyueimg_options = array_merge( $options, $wpsanyueimg_options );
		update_option('wpsanyueimg_options', $wpsanyueimg_options);
	};
}

// 升级选项内容
function wpsanyueimg_upgrade_options( $upgrader_object, $options ) {
    if ( isset( $options['action'] ) && $options['action'] === 'update'
        && isset( $options['type'] ) && $options['type'] === 'plugin'
        && isset( $options['plugins'] ) && is_array( $options['plugins'] )
		&& in_array( plugin_basename( WPSANYUEIMG_PLUGIN_DIR . 'index.php' ), $options['plugins'], true ) ) {
		$wpsanyueimg_options = wpsanyueimg_get_options();
		if ( $wpsanyueimg_options && isset( $wpsanyueimg_options['version'] ) && $wpsanyueimg_options['version'] == 1.0 ) {
			$wpsanyueimg_options['opt'] = array(
                'auto_rename' => false,
            );  // 自动重命名开关
			$wpsanyueimg_options['version'] = WPSANYUEIMG_VERSION;
			update_option('wpsanyueimg_options', $wpsanyueimg_options);
        }
    }
}

/**
 * 删除本地文件
 * @param $file_path : 文件路径
 * @return bool
 */
function wpsanyueimg_delete_local_file($file_path) {
	try {
		# 文件不存在
		if (!@file_exists($file_path)) {
			return TRUE;
		}
		# 删除文件
		if (!@unlink($file_path)) {
			return FALSE;
		}
		return TRUE;
	} catch (Exception $ex) {
		return FALSE;
	}
}


/**
 * 文件上传功能基础函数，被其它需要进行文件上传的模块调用
 * @param $key  : 远端需要的Key值[包含路径]
 * @param $file_local_path : 文件在本地的路径。
 * @param bool $no_local_file : 如果为真，则不在本地保留附件
 *
// * @return array|false
*/
function wpsanyueimg_file_upload($key, $file_local_path, $no_local_file = false) {
	if ( ! is_readable( $file_local_path ) ) {
		return false;
	}
	$wpsanyueimg_options = wpsanyueimg_get_options();
	if ( ! is_array( $wpsanyueimg_options ) || empty( $wpsanyueimg_options ) ) {
		return false;
	}
	try {
		$imgbed = new ImgBedApi( $wpsanyueimg_options );
	} catch ( Exception $e ) {
		return false;
	}

	try {
		$upload_result = $imgbed->upload( $key, $file_local_path );
	} catch ( Exception $e ) {
		return false;
	}

	// 如果上传成功，且不再本地保存，在此删除本地文件
	if ( $no_local_file ) {
		wpsanyueimg_delete_local_file( $file_local_path );
	}

	return is_array( $upload_result ) ? $upload_result : false;
}

function wpsanyueimg_extract_remote_url( $upload_result ) {
	if ( ! is_array( $upload_result ) ) {
		return '';
	}
	$wpsanyueimg_options = wpsanyueimg_get_options();
	$base_url = '';
	if ( is_array( $wpsanyueimg_options ) && ! empty( $wpsanyueimg_options['imgbed_base_url'] ) ) {
		$base_url = rtrim( esc_url_raw( (string) $wpsanyueimg_options['imgbed_base_url'] ), '/' );
	}

	$candidates = array();
	if ( ! empty( $upload_result['url'] ) ) {
		$candidates[] = $upload_result['url'];
	}
	if ( isset( $upload_result['data'] ) && is_array( $upload_result['data'] ) ) {
		$data = $upload_result['data'];
		if ( ! empty( $data['url'] ) ) {
			$candidates[] = $data['url'];
		}
		if ( ! empty( $data['fileUrl'] ) ) {
			$candidates[] = $data['fileUrl'];
		}
		if ( ! empty( $data['src'] ) ) {
			$candidates[] = $data['src'];
		}
	}
	if ( ! empty( $upload_result['fileUrl'] ) ) {
		$candidates[] = $upload_result['fileUrl'];
	}
	if ( ! empty( $upload_result['src'] ) ) {
		$candidates[] = $upload_result['src'];
	}

	foreach ( $candidates as $candidate ) {
		$remote_url = wpsanyueimg_normalize_remote_url( $candidate, $base_url );
		if ( '' !== $remote_url ) {
			return $remote_url;
		}
	}

	$stack = array( $upload_result );
	while ( ! empty( $stack ) ) {
		$current = array_pop( $stack );
		if ( ! is_array( $current ) ) {
			continue;
		}
		foreach ( $current as $value ) {
			if ( is_array( $value ) ) {
				$stack[] = $value;
				continue;
			}
			if ( ! is_string( $value ) ) {
				continue;
			}
			$remote_url = wpsanyueimg_normalize_remote_url( $value, $base_url );
			if ( '' !== $remote_url ) {
				return $remote_url;
			}
		}
	}

	return '';
}

function wpsanyueimg_normalize_remote_url( $candidate, $base_url = '' ) {
	$value = trim( (string) $candidate );
	if ( '' === $value ) {
		return '';
	}

	if ( 0 === strpos( $value, 'http://' ) || 0 === strpos( $value, 'https://' ) ) {
		$absolute = esc_url_raw( $value );
		return '' !== $absolute ? $absolute : '';
	}

	if ( '' !== $base_url ) {
		if ( 0 === strpos( $value, '/file/' ) || 0 === strpos( $value, 'file/' ) ) {
			$relative = '/' . ltrim( $value, '/' );
			$absolute = esc_url_raw( $base_url . $relative );
			return '' !== $absolute ? $absolute : '';
		}
	}

	return '';
}

function wpsanyueimg_remote_key_from_url( $remote_url ) {
	$remote_url = (string) $remote_url;
	if ( '' === $remote_url ) {
		return '';
	}
	$url_path = wp_parse_url( $remote_url, PHP_URL_PATH );
	if ( ! is_string( $url_path ) || '' === $url_path ) {
		return '';
	}
	$path = ltrim( $url_path, '/' );
	if ( 0 === strpos( $path, 'file/' ) ) {
		$path = substr( $path, 5 );
	}
	return ltrim( (string) $path, '/' );
}

function wpsanyueimg_save_remote_mapping( $attachment_id, $local_key, $upload_result, $is_main = false ) {
	$attachment_id = (int) $attachment_id;
	if ( $attachment_id <= 0 || ! is_array( $upload_result ) ) {
		return;
	}
	$remote_url = wpsanyueimg_extract_remote_url( $upload_result );
	if ( '' === $remote_url ) {
		return;
	}

	$local_key = ltrim( (string) $local_key, '/' );
	$remote_key = wpsanyueimg_remote_key_from_url( $remote_url );
	if ( '' !== $remote_key && '' !== $local_key ) {
		$remote_map = get_post_meta( $attachment_id, '_wpsanyueimg_remote_map', true );
		if ( ! is_array( $remote_map ) ) {
			$remote_map = array();
		}
		$remote_map[ $local_key ] = $remote_key;
		update_post_meta( $attachment_id, '_wpsanyueimg_remote_map', $remote_map );
	}

	if ( $is_main ) {
		update_post_meta( $attachment_id, '_wpsanyueimg_remote_url', $remote_url );
		wp_update_post(
			array(
				'ID'   => $attachment_id,
				'guid' => $remote_url,
			)
		);
	}
}


/**
 * 删除远程附件（包括图片的原图）
 * @param $post_id
 */
function wpsanyueimg_delete_remote_attachment($post_id) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return;
	}

	// 获取要删除的对象Key的数组
	$deleteObjects = array();
	$meta = wp_get_attachment_metadata( $post_id );
	$remote_map = get_post_meta( $post_id, '_wpsanyueimg_remote_map', true );
	if ( ! is_array( $remote_map ) ) {
		$remote_map = array();
	}
	$remote_main_url = (string) get_post_meta( $post_id, '_wpsanyueimg_remote_url', true );
	$remote_main_key = wpsanyueimg_remote_key_from_url( $remote_main_url );
	if ( '' !== $remote_main_key ) {
		$deleteObjects[] = ltrim( $remote_main_key, '/' );
	}

	if (isset($meta['file'])) {
		$attachment_key = $meta['file'];
		if ( isset( $remote_map[ $attachment_key ] ) && '' !== $remote_map[ $attachment_key ] ) {
			array_push($deleteObjects, ltrim($remote_map[ $attachment_key ], '/'));
		} elseif ( '' === $remote_main_key ) {
			array_push($deleteObjects, ltrim($attachment_key, '/'));
		}
	} else {
		$file = get_attached_file( $post_id );
		$wp_uploads = wp_upload_dir();
		$attached_key = str_replace( ( $wp_uploads['basedir'] ?? '' ) . '/', '', $file );  # 不能以/开头
		if ( isset( $remote_map[ $attached_key ] ) && '' !== $remote_map[ $attached_key ] ) {
			$deleteObjects[] = ltrim($remote_map[ $attached_key ], '/');
		} elseif ( '' === $remote_main_key ) {
			$deleteObjects[] = ltrim($attached_key, '/');
		}
	}

	if (isset($meta['sizes']) && count($meta['sizes']) > 0) {
		foreach ($meta['sizes'] as $val) {
			$attachment_thumbs_key = dirname($meta['file']) . '/' . $val['file'];
			if ( isset( $remote_map[ $attachment_thumbs_key ] ) && '' !== $remote_map[ $attachment_thumbs_key ] ) {
				$deleteObjects[] = ltrim($remote_map[ $attachment_thumbs_key ], '/');
			} else {
				$deleteObjects[] = ltrim($attachment_thumbs_key, '/');
			}
		}
	}

	$deleteObjects = array_values( array_unique( array_filter( $deleteObjects ) ) );
	wpsanyueimg_log(
		'remote_delete_prepare',
		array(
			'post_id' => $post_id,
			'delete_objects' => $deleteObjects,
			'remote_main_url' => $remote_main_url,
		)
	);

    if ( ! empty( $deleteObjects ) ) {
        $wpsanyueimg_options = wpsanyueimg_get_options();
        if ( is_array( $wpsanyueimg_options ) && ! empty( $wpsanyueimg_options ) ) {
			$delete_candidates = wpsanyueimg_build_delete_candidates( $deleteObjects, $wpsanyueimg_options );
			wpsanyueimg_log(
				'remote_delete_candidates',
				array(
					'post_id' => $post_id,
					'candidates' => $delete_candidates,
				)
			);
			try {
				$imgbed = new ImgBedApi( $wpsanyueimg_options );
				$delete_results = $imgbed->delete( $delete_candidates );
				wpsanyueimg_log(
					'remote_delete_results',
					array(
						'post_id' => $post_id,
						'results' => $delete_results,
					)
				);
			} catch ( Exception $e ) {
				wpsanyueimg_log(
					'remote_delete_exception',
					array(
						'post_id' => $post_id,
						'message' => $e->getMessage(),
					)
				);
			}
        } else {
			wpsanyueimg_log(
				'remote_delete_skip_invalid_options',
				array(
					'post_id' => $post_id,
				)
			);
        }
    } else {
		wpsanyueimg_log(
			'remote_delete_skip_empty_keys',
			array(
				'post_id' => $post_id,
			)
		);
    }
}


/**
 * 上传图片及缩略图
 * @param $metadata: 附件元数据
 * @return array $metadata: 附件元数据
 * 官方的钩子文档上写了可以添加 $attachment_id 参数，但实际测试过程中部分wp接收到不存在的参数时会报错，上传失败，返回报错为“HTTP错误”
 */
function wpsanyueimg_upload_and_thumbs( $metadata, $attachment_id = 0 ) {
	$wpsanyueimg_options = wpsanyueimg_get_options();
	if ( ! is_array( $wpsanyueimg_options ) || empty( $wpsanyueimg_options ) ) {
		return $metadata;
	}
	$wp_uploads = wp_upload_dir();  # 获取上传路径

	if ( isset( $metadata['file'] ) ) {
		# 1.先上传主图
		// wp_upload_path['base_dir'] + metadata['file']
		$attachment_key = $metadata['file'];  // 远程key路径
		$attachment_local_path = ( $wp_uploads['basedir'] ?? '' ) . '/' . $attachment_key;  # 在本地的存储路径
		$main_upload_result = wpsanyueimg_file_upload($attachment_key, $attachment_local_path, $wpsanyueimg_options['no_local_file']);  # 调用上传函数
		wpsanyueimg_save_remote_mapping( $attachment_id, $attachment_key, $main_upload_result, true );
	}

	# 如果存在缩略图则上传缩略图
	if (isset($metadata['sizes']) && count($metadata['sizes']) > 0) {

		// 文件名可能相同，上传操作时会判断是否存在，如果存在则不会执行上传。
		foreach ($metadata['sizes'] as $val) {
			$attachment_thumbs_key = dirname($metadata['file']) . '/' . $val['file'];  // 生成object在对象存储的 key
			$attachment_thumbs_local_path = ( $wp_uploads['basedir'] ?? '' ) . '/' . $attachment_thumbs_key;  // 本地存储路径
			$thumb_upload_result = wpsanyueimg_file_upload($attachment_thumbs_key, $attachment_thumbs_local_path, $wpsanyueimg_options['no_local_file']);  //调用上传函数
			wpsanyueimg_save_remote_mapping( $attachment_id, $attachment_thumbs_key, $thumb_upload_result, false );
		}
	}

	return $metadata;
}

/**
 * @param array  $upload {
 *     Array of upload data.
 *
 *     @type string $file Filename of the newly-uploaded file.
 *     @type string $url  URL of the uploaded file.
 *     @type string $type File type.
 * @return array  $upload
 */
function wpsanyueimg_upload_attachments ($upload) {
	$mime_types       = get_allowed_mime_types();
	$image_mime_types = array(
		// Image formats.
		$mime_types['jpg|jpeg|jpe'],
		$mime_types['gif'],
		$mime_types['png'],
        // 默认图片编辑支持以上3种格式
		$mime_types['bmp'],
		$mime_types['tiff|tif'],
		$mime_types['ico'],
	);
	if ( ! in_array( $upload['type'], $image_mime_types ) ) {
		$wp_uploads  = wp_upload_dir();
		$key         = str_replace( ( $wp_uploads['basedir'] ?? '' ) . '/', '', $upload['file'] );
		$local_path  = $upload['file'];
		$wpsanyueimg_opts = wpsanyueimg_get_options();
		$no_local    = is_array( $wpsanyueimg_opts ) && ! empty( $wpsanyueimg_opts['no_local_file'] );
		$upload_result = wpsanyueimg_file_upload( $key, $local_path, $no_local );
		$remote_url = wpsanyueimg_extract_remote_url( $upload_result );
		if ( '' !== $remote_url ) {
			$upload['url'] = $remote_url;
		}
	}

	return $upload;
}


/**
 * Filters the result when generating a unique file name.
 *
 * @since 4.5.0
 *
 * @param string        $filename                 Unique file name.

 * @return string New filename, if given wasn't unique
 *
 * 参数 $ext 在官方钩子文档中可以使用，部分 WP 版本因为多了这个参数就会报错。 返回“HTTP错误”
 */
function wpsanyueimg_unique_filename( $filename ) {
	$wpsanyueimg_options = wpsanyueimg_get_options();
	if ( ! is_array( $wpsanyueimg_options ) || empty( $wpsanyueimg_options ) ) {
		return $filename;
	}
	$ext = pathinfo( $filename, PATHINFO_EXTENSION );
	$ext = ( '' !== $ext ) ? '.' . $ext : '';
	$number = '';
	try {
		$imgbed = new ImgBedApi( $wpsanyueimg_options );
	} catch ( Exception $e ) {
		return $filename;
	}
	$wp_uploads = wp_upload_dir();
	$subdir = $wp_uploads['subdir'] ?? '';
	while ( $imgbed->has_exist( ltrim( $subdir . '/' . $filename, '/' ) ) ) {
		$new_number = (int) $number + 1;
		if ( '' === (string) $number . $ext ) {
			$filename = $filename . '-' . $new_number;
		} else {
			$filename = str_replace( array( '-' . $number . $ext, $number . $ext ), '-' . $new_number . $ext, $filename );
		}
		$number = $new_number;
	}
	return $filename;
}

// 自动重命名
function wpsanyueimg_sanitize_file_name( $filename ) {
	$wpsanyueimg_options = wpsanyueimg_get_options();
	if ( ! is_array( $wpsanyueimg_options ) || empty( $wpsanyueimg_options['opt']['auto_rename'] ) ) {
		return $filename;
	}
	$ext = pathinfo( $filename, PATHINFO_EXTENSION );
	return date( 'YmdHis' ) . mt_rand( 100, 999 ) . ( '' !== $ext ? '.' . $ext : '' );
}


// 在导航栏“设置”中添加条目
function wpsanyueimg_add_setting_page() {
	if (!function_exists('wpsanyueimg_setting_page')) {
		require_once 'setting_page.php';
	}
	add_options_page('WP ImgBed 设置', 'cloudflare-ImgBed 存储设置', 'manage_options', __FILE__, 'wpsanyueimg_setting_page');
}

// 在插件列表页添加设置按钮
function wpsanyueimg_plugin_action_links($links, $file) {
	if ($file == plugin_basename(dirname(__FILE__) . '/index.php')) {
		$links[] = '<a href="admin.php?page=' . WPSANYUEIMG_BASEFOLDER . '/actions.php">设置</a>';
	}
	return $links;
}

function wpsanyueimg_set_thumbsize($wpsanyueimg_options, $set_thumb){
    if($set_thumb) {
		$wpsanyueimg_options['opt']['thumbsize'] = array(
            'thumbnail_size_w' => get_option('thumbnail_size_w'),
            'thumbnail_size_h' => get_option('thumbnail_size_h'),
            'medium_size_w'    => get_option('medium_size_w'),
            'medium_size_h'    => get_option('medium_size_h'),
            'large_size_w'     => get_option('large_size_w'),
            'large_size_h'     => get_option('large_size_h'),
            'medium_large_size_w' => get_option('medium_large_size_w'),
            'medium_large_size_h' => get_option('medium_large_size_h'),
        );
        update_option('thumbnail_size_w', 0);
        update_option('thumbnail_size_h', 0);
        update_option('medium_size_w', 0);
        update_option('medium_size_h', 0);
        update_option('large_size_w', 0);
        update_option('large_size_h', 0);
        update_option('medium_large_size_w', 0);
        update_option('medium_large_size_h', 0);
		update_option('wpsanyueimg_options', $wpsanyueimg_options);
    } else {
		if(isset($wpsanyueimg_options['opt']['thumbsize'])) {
			update_option('thumbnail_size_w', $wpsanyueimg_options['opt']['thumbsize']['thumbnail_size_w']);
			update_option('thumbnail_size_h', $wpsanyueimg_options['opt']['thumbsize']['thumbnail_size_h']);
			update_option('medium_size_w', $wpsanyueimg_options['opt']['thumbsize']['medium_size_w']);
			update_option('medium_size_h', $wpsanyueimg_options['opt']['thumbsize']['medium_size_h']);
			update_option('large_size_w', $wpsanyueimg_options['opt']['thumbsize']['large_size_w']);
			update_option('large_size_h', $wpsanyueimg_options['opt']['thumbsize']['large_size_h']);
			update_option('medium_large_size_w', $wpsanyueimg_options['opt']['thumbsize']['medium_large_size_w']);
			update_option('medium_large_size_h', $wpsanyueimg_options['opt']['thumbsize']['medium_large_size_h']);
			unset($wpsanyueimg_options['opt']['thumbsize']);
			update_option('wpsanyueimg_options', $wpsanyueimg_options);
        }
    }
	return $wpsanyueimg_options;
}
