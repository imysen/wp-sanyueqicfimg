<?php
/**
Plugin Name: WP-SanyueqiCfimg(CloudFlare-ImgBed 对接wordpress媒体库插件)
Plugin URI: https://blog.imysen.com
Description: WordPress 媒体库对接到 CloudFlare ImgBed，通过 API Token 接管媒体库上传与删除。
Version: 1.0
Author: 邹云森森
Author URI: https://blog.imysen.com
Requires PHP: 7.4
*/

require_once 'actions.php';

$current_wp_version = get_bloginfo('version');

# 插件 activation 函数当一个插件在 WordPress 中”activated(启用)”时被触发。
register_activation_hook(__FILE__, 'wpsanyueimg_set_options');
add_action('upgrader_process_complete', 'wpsanyueimg_upgrade_options', 10, 2);

# 自动重命名
add_filter( 'sanitize_file_name', 'wpsanyueimg_sanitize_file_name', 10, 1 );

# 避免上传插件/主题被同步到对象存储
if ( isset( $_SERVER['REQUEST_URI'] ) && substr_count( $_SERVER['REQUEST_URI'], '/update.php' ) <= 0 ) {
	add_filter('wp_handle_upload', 'wpsanyueimg_upload_attachments');
    if ( (float)$current_wp_version < 5.3 ){
		add_filter( 'wp_update_attachment_metadata', 'wpsanyueimg_upload_and_thumbs', 10, 2 );
    } else {
		add_filter( 'wp_generate_attachment_metadata', 'wpsanyueimg_upload_and_thumbs', 10, 2 );
		add_filter( 'wp_save_image_editor_file', 'wpsanyueimg_save_image_editor_file' );
    }
}

# 检测不重复的文件名
add_filter('wp_unique_filename', 'wpsanyueimg_unique_filename');

# 删除文件时触发删除远端文件，该删除会默认删除缩略图
add_action('delete_attachment', 'wpsanyueimg_delete_remote_attachment');
add_filter('wp_get_attachment_url', 'wpsanyueimg_filter_attachment_url', 10, 2);

# 添加插件设置菜单
add_action('admin_menu', 'wpsanyueimg_add_setting_page');
add_filter('plugin_action_links', 'wpsanyueimg_plugin_action_links', 10, 2);

// add_filter( 'big_image_size_threshold', '__return_false' );


function wpsanyueimg_save_image_editor_file($override){
    add_filter( 'wp_update_attachment_metadata', 'wpsanyueimg_image_editor_file_save', 10, 2 );
    return $override;
}

function wpsanyueimg_image_editor_file_save( $metadata, $attachment_id = 0 ){
	$wpsanyueimg_options = wpsanyueimg_get_options();
	if ( ! is_array( $wpsanyueimg_options ) || empty( $wpsanyueimg_options ) ) {
		return $metadata;
	}
	$no_local = ! empty( $wpsanyueimg_options['no_local_file'] );
	$wp_uploads = wp_upload_dir();

	if ( isset( $metadata['file'] ) ) {
		$attachment_key = '/' . $metadata['file'];
		$attachment_local_path = ( $wp_uploads['basedir'] ?? '' ) . $attachment_key;
		$main_upload_result = wpsanyueimg_file_upload( $attachment_key, $attachment_local_path, $no_local );
		wpsanyueimg_save_remote_mapping( $attachment_id, $attachment_key, $main_upload_result, true );
	}
	if ( isset( $metadata['sizes'] ) && count( $metadata['sizes'] ) > 0 ) {
		foreach ( $metadata['sizes'] as $val ) {
			$attachment_thumbs_key = '/' . dirname( $metadata['file'] ) . '/' . $val['file'];
			$attachment_thumbs_local_path = ( $wp_uploads['basedir'] ?? '' ) . $attachment_thumbs_key;
			$thumb_upload_result = wpsanyueimg_file_upload( $attachment_thumbs_key, $attachment_thumbs_local_path, $no_local );
			wpsanyueimg_save_remote_mapping( $attachment_id, $attachment_thumbs_key, $thumb_upload_result, false );
		}
	}
    remove_filter( 'wp_update_attachment_metadata', 'wpsanyueimg_image_editor_file_save' );
    return $metadata;
}

function wpsanyueimg_filter_attachment_url( $url, $post_id ) {
	$remote_url = (string) get_post_meta( $post_id, '_wpsanyueimg_remote_url', true );
	if ( '' !== $remote_url ) {
		return $remote_url;
	}
	return $url;
}
