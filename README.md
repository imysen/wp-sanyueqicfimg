# WP-SanyueqiCfimg

WordPress 媒体库对接 [CloudFlare ImgBed](https://cfbed.sanyue.de/) 的插件。

## 功能

- 媒体上传后自动同步到 ImgBed
- 删除媒体时自动删除图床对应文件、
- 支持图床API提供的上传目录、命名规则、自动重试、服务端压缩等功能
- 支持调试日志（用于排查上传/删除问题）

## 安装

1. 将插件目录放到 `wp-content/plugins/` 下。
2. 在 WordPress 后台启用插件。
3. 进入：`设置 -> ImgBed 存储设置`。

## 基础配置

至少需要配置：

- `ImgBed 地址`（例如 `https://imgbed.example.com`）
- `API Token`（建议具备 `upload/delete/list` 权限）

可选配置：

- 上传渠道 `uploadChannel`
- 渠道名称 `channelName`
- 上传目录 `uploadFolder`
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
