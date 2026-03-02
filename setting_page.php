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
<link rel='stylesheet'  href='<?php echo esc_url( plugin_dir_url( __FILE__ ) ); ?>layui/css/layui.css' />
<link rel='stylesheet'  href='<?php echo esc_url( plugin_dir_url( __FILE__ ) ); ?>layui/css/cnceb.css'/>
<script src='<?php echo esc_url( plugin_dir_url( __FILE__ ) ); ?>layui/layui.js'></script>
<style type="text/css">
.wpcosform .layui-form-label{width:120px;}
.wpcosform .layui-input{width: 350px;}
.wpcosform .layui-form-mid{margin-left:3.5%;}
.wpcosform .layui-form-mid p{padding: 3px 0;}
.cnceb-wp-hidden {position: relative;}
.cnceb-wp-hidden .cnceb-wp-eyes{padding: 5px;position:absolute;top:3px;z-index: 999;display: none;}
.cnceb-wp-hidden i{font-size:20px;}
.cnceb-wp-hidden i.dashicons-visibility{color:#009688 ;}
.cnceb-upload-folder-inline{display:flex;align-items:center;width:380px;max-width:100%;}
.cnceb-upload-folder-inline .layui-input{flex:1;width:auto;min-width:0;}
.cnceb-upload-folder-tip{display:inline-block;width:16px;height:16px;line-height:16px;text-align:center;border-radius:50%;background:#1e9fff;color:#fff;font-size:12px;cursor:pointer;margin-left:6px;vertical-align:middle;position:relative;top:-1px;}
@media screen and (max-width: 768px) {
	.wpsanyueimg-sidebar { display: none !important; }
}
</style>
<div class="container-cnceb-main">
	<div class="cnceb-wbs-header" style="margin-bottom: 15px;">
		<div class="cnceb-wbs-logo">
			<span class="wbs-span">CloudFlare-ImgBed 存储插件</span><span class="wbs-free">V1.0</span>
		</div>
		<div class="cnceb-wbs-btn">
			<a class="layui-btn layui-btn-primary" href="https://blog.imysen.com" target="_blank">
				<i class="layui-icon layui-icon-home"></i> 作者博客
			</a>
			<a class="layui-btn layui-btn-primary" href="https://github.com/imysen/wp-sanyueqicfimg" target="_blank">
				<i class="layui-icon layui-icon-release"></i> 开原仓库
			</a>
		</div>
	</div>
</div>
<!-- 内容 -->
<div class="container-cnceb-main">
	<div class="layui-container container-m">
		<div class="layui-row layui-col-space15">
			<!-- 左边 -->
			<div class="layui-col-md9">
				<div class="cnceb-panel">
					<div class="cnceb-controw">
						<fieldset class="layui-elem-field layui-field-title site-title">
							<legend>
								<a name="get">
									设置选项
								</a>
							</legend>
						</fieldset>
						<form class="layui-form wpcosform" action="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=' . WPSANYUEIMG_BASEFOLDER . '/actions.php' ) ) ); ?>" name="wpcosform" method="post" >
							<div class="layui-form-item">
								<label class="layui-form-label">ImgBed 地址</label>
								<div class="layui-input-block">
									<input class="layui-input" type="text" name="imgbed_base_url" value="<?php echo esc_url( $wpsanyueimg_options['imgbed_base_url'] ?? '' ); ?>" size="50" placeholder="https://your.domain"/>
									<div class="layui-form-mid layui-word-aux">
										CloudFlare ImgBed 服务根地址，不要以 / 结尾。示范：
										<code>
										https://imgbed.example.com
										</code>
									</div>
								</div>
							</div>
							<div class="layui-form-item">
								<label class="layui-form-label">API Token</label>
								<div class="layui-input-block">
									<div class="cnceb-wp-hidden">
										<input class="layui-input"  type="password" name="imgbed_api_token" value="<?php echo esc_attr( $wpsanyueimg_options['imgbed_api_token'] ?? '' ); ?>" size="50" placeholder="用于 upload/delete/list 的 API Token"/>
										<span class="cnceb-wp-eyes"><i class="dashicons dashicons-hidden"></i></span>
									</div>
								</div>
							</div>
							<div class="layui-form-item">
								<label class="layui-form-label">上传渠道</label>
								<div class="layui-input-block">
									<input class="layui-input" type="text" name="imgbed_upload_channel" value="<?php echo esc_attr( $wpsanyueimg_options['imgbed_upload_channel'] ?? '' ); ?>" size="50" placeholder="可选，默认为telegram"/>
								</div>
							</div>
							<div class="layui-form-item">
								<label class="layui-form-label">渠道名称</label>
								<div class="layui-input-block">
									<input class="layui-input" type="text" name="imgbed_channel_name" value="<?php echo esc_attr( $wpsanyueimg_options['imgbed_channel_name'] ?? '' ); ?>" size="50" placeholder="可选，多渠道时指定 channelName"/>
								</div>
							</div>
							<div class="layui-form-item">
								<label class="layui-form-label">上传目录</label>
								<div class="layui-input-block">
									<div class="cnceb-upload-folder-inline">
										<input class="layui-input" type="text" name="imgbed_upload_folder" value="<?php echo esc_attr( $wpsanyueimg_options['imgbed_upload_folder'] ?? '' ); ?>" size="50" placeholder="默认使用WordPress返回的目录（可选，如 img/test）"/>
										<span class="cnceb-upload-folder-tip" title="上传目录说明">?</span>
									</div>
								</div>
							</div>
							<div class="layui-form-item">
								<label class="layui-form-label">命名规则</label>
								<div class="layui-input-block">
									<input class="layui-input" type="text" name="imgbed_upload_name_type" value="<?php echo esc_attr( $wpsanyueimg_options['imgbed_upload_name_type'] ?? '' ); ?>" size="50" placeholder="default / index / origin / short"/>
								</div>
							</div>
							<div class="layui-form-item">
								<label class="layui-form-label">返回格式</label>
								<div class="layui-input-block">
									<input class="layui-input" type="text" name="imgbed_return_format" value="<?php echo esc_attr( $wpsanyueimg_options['imgbed_return_format'] ?? '' ); ?>" size="50" placeholder="default / full"/>
								</div>
							</div>
							<div class="layui-form-item">
								<label class="layui-form-label">自动重试</label>
								<div class="layui-input-inline" style="width:60px;">
									<input type="checkbox" name="imgbed_auto_retry"  title="设置"
									<?php if ( ! isset($wpsanyueimg_options['imgbed_auto_retry']) || ! empty( $wpsanyueimg_options['imgbed_auto_retry'] ) ) { echo 'checked="TRUE"'; } ?>
									>
								</div>
							</div>
							<div class="layui-form-item">
								<label class="layui-form-label">服务端压缩</label>
								<div class="layui-input-inline" style="width:60px;">
									<input type="checkbox" name="imgbed_server_compress"  title="设置"
									<?php if ( ! isset($wpsanyueimg_options['imgbed_server_compress']) || ! empty( $wpsanyueimg_options['imgbed_server_compress'] ) ) { echo 'checked="TRUE"'; } ?>
									>
								</div>
								<div class="layui-form-mid layui-word-aux">
									默认压缩（仅针对Telegram渠道的图片文件）
								</div>
							</div>
							<div class="layui-form-item">
								<label class="layui-form-label">调试日志</label>
								<div class="layui-input-inline" style="width:60px;">
									<input type="checkbox" name="enable_log"  title="设置"
									<?php if ( ! isset($wpsanyueimg_options['enable_log']) || ! empty( $wpsanyueimg_options['enable_log'] ) ) { echo 'checked="TRUE"'; } ?>
									>
								</div>
								<div class="layui-form-mid layui-word-aux">
									记录上传/删除请求结果到 uploads/wpsanyueqicfimg.log（建议排障时开启）
								</div>
							</div>
							<div class="layui-form-item">
								<label class="layui-form-label"> 自动重命名</label>
								<div class="layui-input-inline" style="width:60px;">
									<input type="checkbox" name="auto_rename"  title="设置"
									 <?php
										 if ( ! empty( $wpsanyueimg_options['opt']['auto_rename'] ) ) {
                                          echo 'checked="TRUE"';
                                         }
                                     ?>
									>
								</div>
								<div class="layui-form-mid layui-word-aux">
									上传文件自动重命名，解决中文文件名或者重复文件名问题
								</div>
							</div>
							<div class="layui-form-item">
								<label class="layui-form-label">不在本地保存</label>
								<div class="layui-input-inline" style="width:60px;">
									<input type="checkbox"  name="no_local_file"  title="设置"
									<?php
									if ( ! empty( $wpsanyueimg_options['no_local_file'] ) ) {
										echo 'checked="TRUE"';
									}
									?>
									>
								</div>
								<div class="layui-form-mid layui-word-aux">
									如不想在服务器中备份静态文件就 "勾选"。
								</div>
							</div>
							<div class="layui-form-item">
								<label class="layui-form-label">禁止缩略图</label>
								<div class="layui-input-inline" style="width:60px;">
									<input type="checkbox"  name="disable_thumb" title="禁止"
									<?php
									if (isset($wpsanyueimg_options['opt']['thumbsize'])) {
										echo 'checked="TRUE"';
									}
									?>
									>
								</div>
								<div class="layui-form-mid layui-word-aux">
									仅生成和上传主图，禁止缩略图裁剪。
								</div>
							</div>
					 </div>
					 <div class="layui-form-item">
					 	  <label class="layui-form-label"></label>
					 	  <div class="layui-input-block"><input type="submit" name="submit" value="保存设置" class=" layui-btn" lay-submit lay-filter="formDemo" /></div>
					 </div>
					 <input type="hidden" name="type" value="imgbed_info_set">
					</form>
				</div>
			</div>
		<!-- 左边 -->
		<!-- 右边 -->
		<div class="layui-col-md3 wpsanyueimg-sidebar">
			<div id="nav">
				 <div class="cnceb-panel">
							<div class="cnceb-panel-title">CloudFlare ImgBed</div>
                        <div class="cnceb-code">
								<p>建议 API Token 健权（upload/delete/list）。</p>
								<p><span class="layui-badge">安全提示</span> 请勿泄露 API Token。</p>
                        </div>
                    </div>                 
			</div>


		</div>
		<!-- 右边 -->
	</div>
</div>
</div>
<!-- 内容 -->
<!-- footer -->
<div class="container-cnceb-main">
	<div class="layui-container container-m">
		<div class="layui-row layui-col-space15">
			<div class="layui-col-md12">
				<div class="cnceb-links">
				<a href="https://github.com/imysen/wp-sanyueqicfimg"  target="_blank">项目主页</a>
				<a href="https://cfbed.sanyue.de/" target="_blank">CloudFlare-imgbed项目文档</a>
                </div>
			</div>
		</div>
	</div>
</div>
<!-- footer -->
<script>
layui.use(['form', 'element','jquery','layer'], function() {
var $ =layui.jquery;
var form = layui.form;
var layer = layui.layer;
function menuFixed(id) {
var obj = document.getElementById(id);
var _getHeight = obj.offsetTop;
var _Width= obj.offsetWidth
window.onscroll = function () {
changePos(id, _getHeight,_Width);
}
}
function changePos(id, height,width) {
var obj = document.getElementById(id);
obj.style.width = width+'px';
var scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
var _top = scrollTop-height;
if (_top < 150) {
var o = _top;
obj.style.position = 'relative';
o = o > 0 ? o : 0;
obj.style.top = o +'px';

} else {
obj.style.position = 'fixed';
obj.style.top = 50+'px';

}
}
menuFixed('nav');

var laobueys = $('.cnceb-wp-hidden')

laobueys.each(function(){

var inpu = $(this).find('.layui-input');
var eyes = $(this).find('.cnceb-wp-eyes')
var width = inpu.outerWidth(true);
eyes.css('left',width+'px').show();

eyes.click(function(){
if(inpu.attr('type') == "password"){
inpu.attr('type','text')
eyes.html('<i class="dashicons dashicons-visibility"></i>')
}else{
inpu.attr('type','password')
eyes.html('<i class="dashicons dashicons-hidden"></i>')
}
})
})

$('.cnceb-upload-folder-tip').each(function(){
	var tipIndex = null;
	$(this).on('mouseenter', function(){
		tipIndex = layer.tips('未填写上传目录时，会按 WordPress 默认机制使用日期作为路径（例如 2026/03）。', this, {
			tips: [1, '#1e9fff'],
			time: 0,
			maxWidth: 360
		});
	}).on('mouseleave', function(){
		if (tipIndex) {
			layer.close(tipIndex);
			tipIndex = null;
		}
	});
});

})
</script>
<?php
}
?>