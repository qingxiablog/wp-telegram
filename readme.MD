=== Telegram 中文免费版 ===
Contributors: WPTelegramFreeCN
Tags: telegram, post to telegram, telegram bot, notifications, wechat, broadcast, channel, group
Requires at least: 6.6
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later

Telegram 中文免费版是一款无广告、纯净的 WordPress 文章推送到 Telegram 插件，提供全简体中文界面与配置说明。

== 描述 ==

“Telegram 中文免费版”致力于为中文站长与社区提供极致稳定、纯净的 Telegram 自动化集成方案。

核心特性：
* **文章自动推送**：新文章发布或更新时自动推送到 Telegram 频道、超级群组或论坛话题 (Topics)。
* **纯净无广告**：完全移除商业推广、Pro 购买按钮和无关广告，专注核心免费功能。
* **原生简体中文**：所有设置项、开关、提示和说明均采用简体中文，提供详尽的作用解释与格式范例。
* **丰富消息模板**：支持文章标题、摘要、作者、分类话题标签、特色图片以及自定义字段 (Post Meta)。
* **条件判断渲染**：支持 `[if {macro}][成立输出][可选替代]` 条件模板语法。
* **邮件通知转发**：将 WordPress 系统邮件（新用户注册、密码找回、待审核评论等）实时推送到 Telegram。
* **多样连接代理**：支持 Cloudflare Worker 反向代理、Google Apps Script 与 HTTP/SOCKS 本地代理，轻松解决网络连通难题。
* **平滑旧版迁移**：一键安全导入旧版 WP Telegram (4.2.15) 的配置、发送记录与待执行定时任务。

== 安装 ==

1. 下载 `wptelegram-free-cn-1.0.0.zip`。
2. 登录 WordPress 网站后台，进入“插件” -> “安装插件” -> “上传插件”。
3. 选择 ZIP 文件并点击“现在安装”，安装完毕后启用插件。
4. 进入后台“设置” -> “Telegram 推送”，填写从 @BotFather 获取的机器人令牌并保存。

== 常见问题 ==

= 如何获取 Bot Token（机器人令牌）？ =
在 Telegram 中搜索官方机器人 @BotFather，发送 `/newbot`，按照提示输入机器人名称与用户名即可获得形如 `123456789:ABCdef...` 的令牌。

= 如何推送到指定频道？ =
将您的机器人添加为频道的管理员（赋予发帖权限），然后在插件的“文章推送”设置中填入频道公开用户名（如 `@my_channel`）或私有频道 ID。

= 服务器在中国大陆无法连接 Telegram API 怎么办？ =
在插件后台的“连接代理”选项卡中启用代理，推荐使用 Cloudflare Worker 方式，稳定快捷。

== 更新日志 ==

= 1.0.0 =
* 初始发布。
* 全功能简体中文化与无广告纯净版。
* 完整兼容旧版配置与待发定时任务接管。
