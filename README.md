# WP-SanyueqiCfimg

WordPress 媒体库对接 [CloudFlare ImgBed](https://cfbed.sanyue.de/) 的插件。
![alt text](https://cfimgbed-cncdn.236668.xyz/file/1772471761538.webp)

> [!WARNING]
> 受上游图床与 TG 制，TG 通道仅可传不超过 **20MB** 的文件。  
> 如需上传更大文件，请调整默认上传渠道，详见 CloudFlare ImgBed 文档。

## 功能

- 媒体上传后自动同步到您的 CloudFlare ImgBed
- 删除媒体时自动删除图床对应文件、
- 支持图床API提供的上传目录、命名规则、自动重试、服务端压缩等功能
- 支持调试日志（用于排查上传/删除问题）

## 安装

1. 下载仓库的.zip压缩包
![alt text](https://cfimgbed-cncdn.236668.xyz/file/1772471821628.webp)
2. 在 WordPress 后台上传并启动插件。
3. 进入：`设置 -> CloudFlare-ImgBed 存储设置`，完成基础配置。

## 基础配置

必须配置：

- `Cloudflare-ImgBed项目地址`（例如 `https://imgbed.example.com`，建议填写源站地址）
- `API Token`（必须具备 `upload/delete/list` 权限）
![alt text](https://cfimgbed-cncdn.236668.xyz/file/1772471930611.webp)

可选配置（详情请见图床文档）：

- 上传渠道 `uploadChannel`（默认为你在图床后台设置的，后台默认设置为TG）
- 渠道名称 `channelName`（多个通道需填写）
- 上传目录 `uploadFolder`（未填写时，使用 WordPress 相关钩子返回的默认目录，通常为 `年/月`）
- 命名规则 `uploadNameType`
- 返回格式 `returnFormat`
- 自动重试 / 服务端压缩

## 工作流程

- **上传**：由 WordPress 后端钩子触发，PHP 端调用 ImgBed API 上传。
- **删除**：由 `delete_attachment` 钩子触发，PHP 端调用 ImgBed API 删除。
- 前端页面仅用于设置项展示和保存，不直接请求图床 API。

## 日志与排障

在设置页开启“调试日志”后，插件会写入日志：

- `wp-content/uploads/wpsanyueqicfimg.log`

常见排查点：

- `remote_delete_prepare`：准备删除的对象 key
- `remote_delete_candidates`：实际尝试删除的候选 key
- `remote_delete_results`：每个 key 的删除结果（状态码/错误信息）
- `remote_delete_exception`：删除过程异常

## 卸载

卸载插件时会删除 `wpsanyueimg_options` 配置项。

## 环境要求

- PHP 7.4+
- WordPress（建议 5.3+）
- 可访问 ImgBed 服务地址
- PHP cURL 扩展（上传需要）

## 鸣谢

感谢所有参与反馈与贡献的社区开发者。  
感谢 [CloudFlare ImgBed](https://github.com/MarSeventh/CloudFlare-ImgBed) 作者[叁月柒](https://github.com/MarSeventh)提供的优秀项目与支持。  
感谢 Cloudflare 与 Telegram 提供稳定的基础服务。   
感谢 [WPUPYUN](https://cn.wordpress.org/plugins/wpupyun) 插件带来的设计灵感，本项目逻辑处理部分参考了[UPYUN](https://cn.wordpress.org/plugins/wpupyun) 插件  