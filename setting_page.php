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
			'update_source_url' => WPSANYUEIMG_DEFAULT_UPDATE_SOURCE_URL,
			'enable_auto_update' => true,
			'enable_log' => true,
			'no_local_file' => false,
			'opt'           => array( 'auto_rename' => false, 'thumbsize' => null ),
		);
	}
	$notice_text = '';
	$need_frontend_retry = false;
	$manual_check_requested = false;
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
	            $wpsanyueimg_options['update_source_url'] = isset($_POST['update_source_url']) ? esc_url_raw(trim(stripslashes($_POST['update_source_url']))) : '';
	            $wpsanyueimg_options['enable_auto_update'] = isset($_POST['enable_auto_update']);
	            $wpsanyueimg_options['enable_log'] = isset($_POST['enable_log']);
			$wpsanyueimg_options['opt']['auto_rename'] = isset($_POST['auto_rename']);

			$wpsanyueimg_options = wpsanyueimg_set_thumbsize($wpsanyueimg_options, isset($_POST['disable_thumb']));
            // 不管结果变没变，有提交则直接以提交的数据 更新wpsanyueimg_options
            update_option('wpsanyueimg_options', $wpsanyueimg_options);
			delete_option('upload_url_path');
			if ( isset( $_POST['check_update_now'] ) ) {
				$manual_check_requested = true;
				$check_ok = wpsanyueimg_force_check_update_now();
				if ( $check_ok ) {
					$notice_text = '设置已保存，且已触发一次更新检查。';
				} else {
					$notice_text = '设置已保存，但后端检查更新失败，正在使用前端重试一次。';
					$need_frontend_retry = true;
				}
			} else {
				$notice_text = '设置已保存。';
			}

            ?>
            <div class="notice notice-success settings-error is-dismissible"><p><strong><?php echo esc_html( $notice_text ); ?></strong></p></div>

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
				<th scope="row"><label for="update_source_url">更新源地址</label></th>
				<td>
					<input name="update_source_url" type="url" id="update_source_url" value="<?php echo esc_attr( $wpsanyueimg_options['update_source_url'] ?? '' ); ?>" class="regular-text" placeholder="https://github-updateapi.112601.xyz/wp-sanyueqicfimg/update.json" />
					<p class="description">建议填写 Worker 返回 update.json 的 HTTPS 地址。</p>
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
						<label for="enable_auto_update">
							<input name="enable_auto_update" type="checkbox" id="enable_auto_update" value="1" <?php checked( ! empty( $wpsanyueimg_options['enable_auto_update'] ) ); ?> />
							启用插件自动更新
						</label>
						<p id="auto-update-warning" class="description" style="color:#d63638;<?php echo ! empty( $wpsanyueimg_options['enable_auto_update'] ) ? 'display:none;' : ''; ?>">强烈建议开启自动更新，及时获取安全修复与兼容性更新。</p>
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
		<?php submit_button( '保存并立即检查更新', 'secondary', 'check_update_now', false ); ?>
	</form>
</div>
<script>
(function(){
	var autoUpdateCheckbox = document.getElementById('enable_auto_update');
	var warningLine = document.getElementById('auto-update-warning');
	if (autoUpdateCheckbox && warningLine) {
		var toggleWarning = function() {
			warningLine.style.display = autoUpdateCheckbox.checked ? 'none' : 'block';
		};
		autoUpdateCheckbox.addEventListener('change', toggleWarning);
		toggleWarning();
	}

	var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var nonce = <?php echo wp_json_encode( wp_create_nonce( 'wpsanyueimg_update_nonce' ) ); ?>;
	var autoUpdateEnabled = <?php echo ! empty( $wpsanyueimg_options['enable_auto_update'] ) ? 'true' : 'false'; ?>;
	var manualCheckRequested = <?php echo $manual_check_requested ? 'true' : 'false'; ?>;

	function createPopup(options) {
		var popup = document.createElement('div');
		popup.style.position = 'fixed';
		popup.style.top = '20px';
		popup.style.right = '20px';
		popup.style.zIndex = '99999';
		popup.style.maxWidth = '420px';
		popup.style.background = '#fff';
		popup.style.border = '1px solid #ccd0d4';
		popup.style.boxShadow = '0 2px 8px rgba(0,0,0,.12)';
		popup.style.padding = '14px';
		popup.style.borderRadius = '6px';

		var title = document.createElement('div');
		title.style.fontWeight = '600';
		title.style.marginBottom = '8px';
		title.textContent = options.title || '更新提示';
		popup.appendChild(title);

		var content = document.createElement('div');
		content.style.fontSize = '13px';
		content.style.lineHeight = '1.6';
		content.innerHTML = options.html || '';
		popup.appendChild(content);

		var actions = document.createElement('div');
		actions.style.marginTop = '12px';
		actions.style.display = 'flex';
		actions.style.gap = '8px';

		if (options.primaryText && typeof options.onPrimary === 'function') {
			var primary = document.createElement('button');
			primary.type = 'button';
			primary.className = 'button button-primary';
			primary.textContent = options.primaryText;
			primary.addEventListener('click', function(){ options.onPrimary(popup, primary); });
			actions.appendChild(primary);
		}

		var close = document.createElement('button');
		close.type = 'button';
		close.className = 'button';
		close.textContent = options.closeText || '关闭';
		close.addEventListener('click', function(){
			if (popup && popup.parentNode) {
				popup.parentNode.removeChild(popup);
			}
		});
		actions.appendChild(close);

		popup.appendChild(actions);
		document.body.appendChild(popup);
		return popup;
	}

	function postAjax(actionName, timeoutMs, extraData) {
		var controller = window.AbortController ? new AbortController() : null;
		var timer = null;
		if (controller && timeoutMs > 0) {
			timer = setTimeout(function(){ controller.abort(); }, timeoutMs);
		}

		var body = new URLSearchParams();
		body.append('action', actionName);
		body.append('nonce', nonce);
		if (extraData && typeof extraData === 'object') {
			Object.keys(extraData).forEach(function(key){
				body.append(key, String(extraData[key]));
			});
		}

		return fetch(ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString(),
			signal: controller ? controller.signal : undefined
		}).then(function(resp){
			if (timer) {
				clearTimeout(timer);
			}
			return resp.json();
		});
	}

	function showLatestPopup(currentVersion) {
		createPopup({
			title: '更新检查',
			html: '当前是最新版本：<strong>' + String(currentVersion || '-') + '</strong>'
		});
	}

	function showUpdatePopup(data, allowApply) {
		var changelog = data.changelog ? String(data.changelog).replace(/</g, '&lt;').replace(/>/g, '&gt;') : '暂无更新说明';
		var releaseUrl = data.github_release_url || data.homepage || 'https://github.com/imysen/wp-sanyueqicfimg/releases';
		var askLine = allowApply ? '是否立即更新？' : '自动更新未开启，当前仅展示版本信息。';
		var html = '' +
			'<div>检测到新版本：<strong>' + String(data.current_version || '-') + '</strong> → <strong>' + String(data.new_version || '-') + '</strong></div>' +
			'<div style="margin-top:8px;"><strong>更新内容：</strong><br><pre style="white-space:pre-wrap;max-height:120px;overflow:auto;margin:6px 0 0;">' + changelog + '</pre></div>' +
			'<div style="margin-top:8px;">GitHub发布地址：<a href="' + String(releaseUrl) + '" target="_blank" rel="noopener noreferrer">' + String(releaseUrl) + '</a></div>' +
			'<div style="margin-top:8px;color:#d63638;">提示：目前更新功能处于测试阶段，建议手动下载压缩包更新。</div>' +
			'<div style="margin-top:8px;">' + askLine + '</div>';

		createPopup({
			title: '存在新版本',
			html: html,
			primaryText: allowApply ? '是，立即更新' : '',
			closeText: '否，稍后再说',
			onPrimary: function(popup, button) {
				var ok = window.confirm('请不要动当前插件目录。系统将创建更新目录，对比差异后替换到生产目录。继续吗？');
				if (!ok) {
					return;
				}

				button.disabled = true;
				button.textContent = '更新中，请勿操作...';

				postAjax('wpsanyueimg_apply_update', 61000)
					.then(function(result){
						if (!result || !result.success) {
							throw new Error(result && result.data && result.data.message ? result.data.message : '更新失败');
						}
						var data = result.data || {};
						var info = data.result || {};
						var msg = (data.message || '更新完成') + '（替换文件：' + String(info.replaced_count || 0) + '，耗时：' + String(info.time_cost || '-') + '秒）';
						popup.querySelector('div:nth-child(2)').innerHTML = '<div style="color:#00a32a;">' + msg + '</div>';
						button.remove();
					})
					.catch(function(error){
						popup.querySelector('div:nth-child(2)').innerHTML = '<div style="color:#d63638;">更新失败：' + String(error.message || error) + '</div>';
						button.disabled = false;
						button.textContent = '是，立即更新';
					});
			}
		});
	}

	if (autoUpdateEnabled || manualCheckRequested) {
		postAjax('wpsanyueimg_check_update_popup', 15000, { manual: manualCheckRequested ? '1' : '0' })
			.then(function(result){
				if (!result || !result.success) {
					throw new Error(result && result.data && result.data.message ? result.data.message : '更新检查失败');
				}
				var data = result.data || {};
				if (data.has_update) {
					showUpdatePopup(data, autoUpdateEnabled);
				} else {
					showLatestPopup(data.current_version);
				}
			})
			.catch(function(error){
				createPopup({
					title: '更新检查失败',
					html: '<div style="color:#d63638;">' + String(error.message || error) + '</div><div style="margin-top:8px;">目前更新功能处于测试阶段，建议手动下载压缩包更新。</div>'
				});
			});
	}

	var needRetry = <?php echo $need_frontend_retry ? 'true' : 'false'; ?>;
	if (!needRetry) {
		return;
	}

	var retryUrl = <?php echo wp_json_encode( wpsanyueimg_get_update_source_url() ); ?>;
	if (!retryUrl) {
		return;
	}

	fetch(retryUrl, { cache: 'no-store' })
		.then(function(resp){
			if (!resp.ok) {
				throw new Error('HTTP ' + resp.status);
			}
			return resp.json();
		})
		.then(function(data){
			if (!data || !data.new_version || !data.download_url) {
				throw new Error('invalid payload');
			}
			var ok = document.createElement('div');
			ok.className = 'notice notice-success is-dismissible';
			ok.innerHTML = '<p><strong>前端重试更新接口成功：' + String(data.new_version) + '。请点击“保存并立即检查更新”完成后端刷新。</strong></p>';
			document.querySelector('.wrap').insertBefore(ok, document.querySelector('.wrap').children[1]);
		})
		.catch(function(error){
			var fail = document.createElement('div');
			fail.className = 'notice notice-warning is-dismissible';
			fail.innerHTML = '<p><strong>前端重试也失败：' + String(error.message || error) + '。请稍后重试或检查 Worker Token。</strong></p>';
			document.querySelector('.wrap').insertBefore(fail, document.querySelector('.wrap').children[1]);
		});
})();
</script>
<?php
}
?>