<?php
/**
 *  插件设置页面
 */
function wpsanyueimg_setting_page() {
// 如果当前用户权限不足
	if (!current_user_can('manage_options')) {
		wp_die('Insufficient privileges!');
	}

	$wpsanyueimg_options = wpsanyueimg_get_options();
	if ( ! is_array( $wpsanyueimg_options ) || empty( $wpsanyueimg_options ) ) {
		$wpsanyueimg_options = array(
			'imgbed_base_url'   => '',
			'imgbed_api_token'  => '',
			'imgbed_upload_channel' => '',
			'imgbed_channel_name' => '',
			'imgbed_upload_name_type' => '',
			'imgbed_return_format' => '',
			'imgbed_upload_folder' => '',
			'imgbed_auto_retry' => true,
			'imgbed_server_compress' => true,
			'enable_log' => true,
			'no_local_file' => false,
			'opt'           => array( 'auto_rename' => false, 'thumbsize' => null ),
		);
	}
	if ( ! empty( $_POST ) && isset( $_POST['type'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'] ?? '', -1 ) ) {
		if ( $_POST['type'] === 'imgbed_info_set' ) {

		    $wpsanyueimg_options['no_local_file'] = isset($_POST['no_local_file']);
	            $wpsanyueimg_options['imgbed_base_url'] = isset($_POST['imgbed_base_url']) ? esc_url_raw(trim(stripslashes($_POST['imgbed_base_url']))) : '';
	            $wpsanyueimg_options['imgbed_api_token'] = isset($_POST['imgbed_api_token']) ? sanitize_text_field(trim(stripslashes($_POST['imgbed_api_token']))) : '';
	            $wpsanyueimg_options['imgbed_upload_channel'] = isset($_POST['imgbed_upload_channel']) ? sanitize_text_field(trim(stripslashes($_POST['imgbed_upload_channel']))) : '';
	            $wpsanyueimg_options['imgbed_channel_name'] = isset($_POST['imgbed_channel_name']) ? sanitize_text_field(trim(stripslashes($_POST['imgbed_channel_name']))) : '';
	            $wpsanyueimg_options['imgbed_upload_name_type'] = isset($_POST['imgbed_upload_name_type']) ? sanitize_text_field(trim(stripslashes($_POST['imgbed_upload_name_type']))) : '';
	            $wpsanyueimg_options['imgbed_return_format'] = isset($_POST['imgbed_return_format']) ? sanitize_text_field(trim(stripslashes($_POST['imgbed_return_format']))) : '';
	            $wpsanyueimg_options['imgbed_upload_folder'] = isset($_POST['imgbed_upload_folder']) ? sanitize_text_field(trim(stripslashes($_POST['imgbed_upload_folder']))) : '';
	            $wpsanyueimg_options['imgbed_auto_retry'] = isset($_POST['imgbed_auto_retry']);
	            $wpsanyueimg_options['imgbed_server_compress'] = isset($_POST['imgbed_server_compress']);
	            $wpsanyueimg_options['enable_log'] = isset($_POST['enable_log']);
			$wpsanyueimg_options['opt']['auto_rename'] = isset($_POST['auto_rename']);

			$wpsanyueimg_options = wpsanyueimg_set_thumbsize($wpsanyueimg_options, isset($_POST['disable_thumb']));
            // 不管结果变没变，有提交则直接以提交的数据 更新wpsanyueimg_options
            update_option('wpsanyueimg_options', $wpsanyueimg_options);
			delete_option('upload_url_path');

            ?>
            <div class="notice notice-success settings-error is-dismissible"><p><strong>设置已保存。</strong></p></div>

            <?php }
	}
	?>
<div class="wrap">
	<h1>cloudflare-ImgBed 存储设置</h1>
	<p>
		<a href="https://github.com/imysen/wp-sanyueqicfimg" target="_blank">项目主页</a> |
		<a href="https://cfbed.sanyue.de/" target="_blank">CloudFlare-imgbed 项目文档</a>
	</p>

	<form action="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=' . WPSANYUEIMG_BASEFOLDER . '/actions.php' ) ) ); ?>" method="post">
		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row"><label for="imgbed_base_url">ImgBed 地址</label></th>
				<td>
					<input name="imgbed_base_url" type="text" id="imgbed_base_url" value="<?php echo esc_url( $wpsanyueimg_options['imgbed_base_url'] ?? '' ); ?>" class="regular-text" placeholder="https://imgbed.example.com" />
					<p class="description">CloudFlare ImgBed 服务根地址，不要以 / 结尾。</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="imgbed_api_token">API Token</label></th>
				<td>
					<input name="imgbed_api_token" type="text" id="imgbed_api_token" value="<?php echo esc_attr( $wpsanyueimg_options['imgbed_api_token'] ?? '' ); ?>" class="regular-text" />
					<p class="description">建议具备 upload / delete / list 权限，请妥善保管。</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="imgbed_upload_channel">上传渠道</label></th>
				<td>
					<input name="imgbed_upload_channel" type="text" id="imgbed_upload_channel" value="<?php echo esc_attr( $wpsanyueimg_options['imgbed_upload_channel'] ?? '' ); ?>" class="regular-text" placeholder="telegram" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="imgbed_channel_name">渠道名称</label></th>
				<td>
					<input name="imgbed_channel_name" type="text" id="imgbed_channel_name" value="<?php echo esc_attr( $wpsanyueimg_options['imgbed_channel_name'] ?? '' ); ?>" class="regular-text" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="imgbed_upload_folder">上传目录</label></th>
				<td>
					<input name="imgbed_upload_folder" type="text" id="imgbed_upload_folder" value="<?php echo esc_attr( $wpsanyueimg_options['imgbed_upload_folder'] ?? '' ); ?>" class="regular-text" placeholder="如 img/test（可选）" />
					<p class="description">未填写时，按 WordPress 默认日期目录（例如 2026/03）。</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="imgbed_upload_name_type">命名规则</label></th>
				<td>
					<input name="imgbed_upload_name_type" type="text" id="imgbed_upload_name_type" value="<?php echo esc_attr( $wpsanyueimg_options['imgbed_upload_name_type'] ?? '' ); ?>" class="regular-text" placeholder="default / index / origin / short" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="imgbed_return_format">返回格式</label></th>
				<td>
					<input name="imgbed_return_format" type="text" id="imgbed_return_format" value="<?php echo esc_attr( $wpsanyueimg_options['imgbed_return_format'] ?? '' ); ?>" class="regular-text" placeholder="default / full" />
				</td>
			</tr>
			<tr>
				<th scope="row">选项</th>
				<td>
					<fieldset>
						<label for="imgbed_auto_retry">
							<input name="imgbed_auto_retry" type="checkbox" id="imgbed_auto_retry" value="1" <?php checked( ! isset( $wpsanyueimg_options['imgbed_auto_retry'] ) || ! empty( $wpsanyueimg_options['imgbed_auto_retry'] ) ); ?> />
							自动重试
						</label>
						<br>
						<label for="imgbed_server_compress">
							<input name="imgbed_server_compress" type="checkbox" id="imgbed_server_compress" value="1" <?php checked( ! isset( $wpsanyueimg_options['imgbed_server_compress'] ) || ! empty( $wpsanyueimg_options['imgbed_server_compress'] ) ); ?> />
							服务端压缩（仅针对 Telegram 渠道图片）
						</label>
						<br>
						<label for="enable_log">
							<input name="enable_log" type="checkbox" id="enable_log" value="1" <?php checked( ! isset( $wpsanyueimg_options['enable_log'] ) || ! empty( $wpsanyueimg_options['enable_log'] ) ); ?> />
							调试日志（写入 uploads/wpsanyueqicfimg.log）
						</label>
						<br>
						<label for="auto_rename">
							<input name="auto_rename" type="checkbox" id="auto_rename" value="1" <?php checked( ! empty( $wpsanyueimg_options['opt']['auto_rename'] ) ); ?> />
							自动重命名
						</label>
						<br>
						<label for="no_local_file">
							<input name="no_local_file" type="checkbox" id="no_local_file" value="1" <?php checked( ! empty( $wpsanyueimg_options['no_local_file'] ) ); ?> />
							不在本地保存
						</label>
						<br>
						<label for="disable_thumb">
							<input name="disable_thumb" type="checkbox" id="disable_thumb" value="1" <?php checked( isset( $wpsanyueimg_options['opt']['thumbsize'] ) ); ?> />
							禁止缩略图
						</label>
					</fieldset>
				</td>
			</tr>
			</tbody>
		</table>

		<input type="hidden" name="type" value="imgbed_info_set">
		<?php submit_button( '保存设置' ); ?>
	</form>
</div>
<?php
}
?>