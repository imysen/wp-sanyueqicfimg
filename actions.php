<?php
require_once 'api.php';
# SDK最低支持版本

define( 'WPSANYUEIMG_VERSION', 4.1 );  // 插件数据版本
define( 'WPSANYUEIMG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );  // 插件路径
define( 'WPSANYUEIMG_BASENAME', plugin_basename(__FILE__));
define( 'WPSANYUEIMG_BASEFOLDER', plugin_basename(dirname(__FILE__)));

function wpsanyueimg_get_options() {
	$options = get_option('wpsanyueimg_options');
	if ( is_array( $options ) ) {
		return $options;
	}
	return array();
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

    if ( ! empty( $deleteObjects ) ) {
        $wpsanyueimg_options = wpsanyueimg_get_options();
        if ( is_array( $wpsanyueimg_options ) && ! empty( $wpsanyueimg_options ) ) {
			try {
				$imgbed = new ImgBedApi( $wpsanyueimg_options );
				$imgbed->delete($deleteObjects);
			} catch ( Exception $e ) {
			}
        }
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
	add_options_page('WP ImgBed 设置', 'ImgBed 存储设置', 'manage_options', __FILE__, 'wpsanyueimg_setting_page');
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
