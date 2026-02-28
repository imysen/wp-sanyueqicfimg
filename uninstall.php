<?php
if(!defined('WP_UNINSTALL_PLUGIN')){
	// 如果 uninstall 不是从 WordPress 调用，则退出
	exit();
}

// 从 options 表删除选项
delete_option('wpsanyueimg_options');
