<?php
/**
 * 设置注册与渲染 (Settings API & Sanitization)
 *
 * @package WPTelegramFreeCN
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 注册设置项
 */
function wptg_free_cn_register_settings() {
    $tabs = array( 'basic', 'p2tg', 'notify', 'proxy', 'advanced' );
    foreach ( $tabs as $tab ) {
        register_setting(
            'wptg_free_cn_' . $tab,
            'wptg_free_cn_options',
            array(
                'sanitize_callback' => 'wptg_free_cn_sanitize_options',
            )
        );
    }

    // ==========================================
    // 1. 基础设置 (Basic)
    // ==========================================
    add_settings_section( 'wptg_basic_section', '机器人凭据与连接', 'wptg_free_cn_section_basic_desc', 'wptg_free_cn_basic' );

    add_settings_field(
        'bot_token',
        '机器人令牌 (Bot Token)',
        'wptg_free_cn_render_field_text',
        'wptg_free_cn_basic',
        'wptg_basic_section',
        array(
            'path'        => 'bot_token',
            'id'          => 'wptg-bot-token',
            'placeholder' => '123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ',
            'desc'        => '在 Telegram 中联系官方 @BotFather 发送 /newbot 获取。格式为“一串数字:字母数字组合”，如 123456789:ABCdef...。此令牌仅保存在本站数据库中，绝不对外展示。',
        )
    );

    add_settings_field(
        'bot_username',
        '机器人用户名 (Bot Username)',
        'wptg_free_cn_render_field_text',
        'wptg_free_cn_basic',
        'wptg_basic_section',
        array(
            'path'        => 'bot_username',
            'id'          => 'wptg-bot-username',
            'placeholder' => 'my_telegram_bot',
            'desc'        => '机器人的 Telegram 用户名（不包含 @ 符号）。点击下方“测试机器人连接”后可自动核对并填写。',
        )
    );

    // ==========================================
    // 2. 文章推送 (P2TG)
    // ==========================================
    add_settings_section( 'wptg_p2tg_section', '文章自动推送设置', 'wptg_free_cn_section_p2tg_desc', 'wptg_free_cn_p2tg' );

    add_settings_field(
        'p2tg_active',
        '启用文章自动推送',
        'wptg_free_cn_render_field_checkbox',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path' => 'p2tg.active',
            'desc' => '开启：当网站发布或更新符合条件的文章时，自动推送到配置的目标频道或群组。<br>关闭：停止文章推送，但保留下方所有已配置的规则与模板。',
        )
    );

    add_settings_field(
        'p2tg_channels',
        '接收频道或群组',
        'wptg_free_cn_render_field_textarea',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path'        => 'p2tg.channels',
            'is_array'    => true,
            'placeholder' => "@my_channel\n-1001234567890\n-1001234567890:123 | 新闻话题",
            'desc'        => '推送目标，每行一个。支持：<br>' .
                             '• 公开频道/群组用户名：<code>@channel_username</code>（机器人必须已加入且拥有管理员发帖权限）<br>' .
                             '• 私有频道/群组数字 ID：<code>-1001234567890</code><br>' .
                             '• 论坛话题 (Topics)：<code>-1001234567890:123</code>（数字ID后接冒号和话题ID）<br>' .
                             '• 自定义备注：<code>-1001234567890 | 中文新闻群</code>（竖线后的内容仅供管理识别，不会推送到消息中）',
        )
    );

    add_settings_field(
        'p2tg_send_when',
        '自动发送时机',
        'wptg_free_cn_render_field_checkbox_group',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path'    => 'p2tg.send_when',
            'options' => array(
                'new'      => '新文章首次发布（文章首次公开且发布时间在 24 小时内）',
                'existing' => '已有文章更新（已发布过的文章修改保存后推送新消息）',
            ),
            'desc'    => '勾选触发自动推送的生命周期节点。至少勾选一项才能生效。',
        )
    );

    add_settings_field(
        'p2tg_post_types',
        '允许发送的内容类型',
        'wptg_free_cn_render_field_post_types',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path' => 'p2tg.post_types',
            'desc' => '选择允许推送到 Telegram 的文章类型。系统自动列出当前站点所有公开类型（如文章、页面、WooCommerce 产品等）。',
        )
    );

    add_settings_field(
        'p2tg_rules',
        '内容筛选规则 (可选)',
        'wptg_free_cn_render_field_rules',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path' => 'p2tg.rules',
            'desc' => '按分类、标签、作者或自定义分类法精细化筛选文章。留空表示不对文章做额外筛选。JSON 规则格式：组间为“或 (OR)”关系，组内为“且 (AND)”关系。',
        )
    );

    add_settings_field(
        'p2tg_message_template',
        '文章消息模板',
        'wptg_free_cn_render_field_textarea',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path'        => 'p2tg.message_template',
            'rows'        => 8,
            'placeholder' => "{post_title}\n\n{post_excerpt}\n\n{full_url}",
            'desc'        => '定义发送到 Telegram 的消息正文格式。支持变量宏（如 <code>{post_title}</code>、<code>{post_excerpt}</code>、<code>{full_url}</code> 等）和条件语法。详见页面下方的“模板变量使用帮助”。',
        )
    );

    add_settings_field(
        'p2tg_excerpt_source',
        '摘要内容来源',
        'wptg_free_cn_render_field_select',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path'    => 'p2tg.excerpt_source',
            'options' => array(
                'post_content' => '文章正文 (自动截取正文前部作为摘要)',
                'before_more'  => '<!--more--> 标记之前的内容',
                'post_excerpt' => '文章手动摘要 (若文章摘要为空则回退到正文截取)',
            ),
            'desc'    => '设置 <code>{post_excerpt}</code> 宏的内容提取来源。',
        )
    );

    add_settings_field(
        'p2tg_excerpt_length',
        '摘要长度',
        'wptg_free_cn_render_field_number',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path' => 'p2tg.excerpt_length',
            'min'  => 1,
            'max'  => 300,
            'step' => 1,
            'desc' => '截取摘要的长度限制（默认 55）。按 WordPress 原生文本词数/字数规则计算，超出部分将以省略号截断。',
        )
    );

    add_settings_field(
        'p2tg_excerpt_preserve_eol',
        '保留摘要换行',
        'wptg_free_cn_render_field_checkbox',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path' => 'p2tg.excerpt_preserve_eol',
            'desc' => '开启：摘要中保留文章原有的段落换行。<br>关闭：将摘要中所有换行替换为空格，合并为连续单行文本。',
        )
    );

    add_settings_field(
        'p2tg_send_featured_image',
        '发送特色图片',
        'wptg_free_cn_render_field_checkbox',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path' => 'p2tg.send_featured_image',
            'desc' => '开启：如果文章设置了特色图片（缩略图），则将图片一同发送。<br>关闭：仅发送纯文字消息，忽略特色图片。若文章无特色图片，自动降级为纯文字发送。',
        )
    );

    add_settings_field(
        'p2tg_image_position',
        '图片显示位置',
        'wptg_free_cn_render_field_select',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path'    => 'p2tg.image_position',
            'options' => array(
                'before' => '文字之前 (作为图片附带说明 Caption 发送)',
                'after'  => '文字之后 (通过 HTML 隐藏链接预览在消息末尾显示图片)',
            ),
            'desc'    => '控制图片在 Telegram 消息中的展示逻辑。',
        )
    );

    add_settings_field(
        'p2tg_single_message',
        '尽量合并为一条消息',
        'wptg_free_cn_render_field_checkbox',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path' => 'p2tg.single_message',
            'desc' => '开启：将特色图片和消息正文合并为一条单条图文消息发送（Caption 限 1024 字符以内）。<br>关闭：分开发送，先发一张独立图片，随后紧跟一条正文文字消息。',
        )
    );

    add_settings_field(
        'p2tg_cats_as_tags',
        '分类显示为话题标签',
        'wptg_free_cn_render_field_checkbox',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path' => 'p2tg.cats_as_tags',
            'desc' => '开启：将 <code>{categories}</code> 和 <code>{terms:category}</code> 的分类名称自动转换为 Telegram 话题标签（如 <code>#科技 #资讯</code>）。<br>关闭：使用竖线分隔分类名称（如 <code>科技 | 资讯</code>）。',
        )
    );

    add_settings_field(
        'p2tg_parse_mode',
        '消息格式 (Parse Mode)',
        'wptg_free_cn_render_field_select',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path'    => 'p2tg.parse_mode',
            'options' => array(
                'none' => '纯文本 (None - 不解析任何标签，保留原样)',
                'HTML' => 'HTML (支持 <b>、<i>、<a>、<code> 等 Telegram 支持的 HTML 标签)',
            ),
            'desc'    => '选择消息的解析模式。若使用 <code>&lt;b&gt;</code> 加粗或超链接，请务必选择 HTML。',
        )
    );

    add_settings_field(
        'p2tg_link_preview_disabled',
        '关闭网页链接预览',
        'wptg_free_cn_render_field_checkbox',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path' => 'p2tg.link_preview_disabled',
            'desc' => '开启：禁用消息中包含的超链接的底部网页卡片预览。<br>关闭：允许 Telegram 客户端自动提取并展示网页标题和缩略图。',
        )
    );

    add_settings_field(
        'p2tg_link_preview_url',
        '指定链接预览 URL',
        'wptg_free_cn_render_field_text',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path'        => 'p2tg.link_preview_url',
            'placeholder' => '{full_url}',
            'desc'        => '显式指定用于生成网页预览卡片的 URL（例如 {full_url}）。留空则由 Telegram 自动从消息正文中识别。',
        )
    );

    add_settings_field(
        'p2tg_link_preview_above_text',
        '预览显示在文字上方',
        'wptg_free_cn_render_field_checkbox',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path' => 'p2tg.link_preview_above_text',
            'desc' => '开启：网页预览卡片展示在消息正文的上方；关闭：网页预览卡片展示在正文下方（默认）。',
        )
    );

    add_settings_field(
        'p2tg_plugin_posts',
        '支持其他插件发布的文章',
        'wptg_free_cn_render_field_checkbox',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path' => 'p2tg.plugin_posts',
            'desc' => '开启：放宽权限检查，允许其他插件在非后台页面、前端投稿或外部触发时推送文章到 Telegram。',
        )
    );

    add_settings_field(
        'p2tg_inline_url_button',
        '添加阅读链接按钮',
        'wptg_free_cn_render_field_checkbox',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path' => 'p2tg.inline_url_button',
            'desc' => '开启：在发送的消息底部附加一个 Telegram 内联按钮 (Inline Keyboard Button)，点击可直达文章页面。',
        )
    );

    add_settings_field(
        'p2tg_inline_button_text',
        '阅读按钮文字',
        'wptg_free_cn_render_field_text',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path'        => 'p2tg.inline_button_text',
            'placeholder' => '🔗 查看文章',
            'desc'        => '显示在内联按钮上的文字。支持表情符号与变量宏。默认为“🔗 查看文章”。',
        )
    );

    add_settings_field(
        'p2tg_inline_button_url',
        '阅读按钮地址',
        'wptg_free_cn_render_field_text',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path'        => 'p2tg.inline_button_url',
            'placeholder' => '{full_url}',
            'desc'        => '点击按钮跳转的目标 URL。支持 <code>{full_url}</code>、<code>{short_url}</code> 或自定义字段 <code>{cf:my_custom_url}</code>。',
        )
    );

    add_settings_field(
        'p2tg_plugin_posts',
        '允许程序生成的文章推送',
        'wptg_free_cn_render_field_checkbox',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path' => 'p2tg.plugin_posts',
            'desc' => '开启：由其他插件、采集工具、前台投稿或 API 自动插入的文章，也可以触发 Telegram 推送（跳过当前登录用户编辑权限检查）。<br>关闭：仅在站点管理员或有权限的用户手动在后台发布时触发。',
        )
    );

    add_settings_field(
        'p2tg_post_edit_switch',
        '文章编辑页面显示发送开关',
        'wptg_free_cn_render_field_checkbox',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path' => 'p2tg.post_edit_switch',
            'desc' => '开启：在古腾堡编辑器侧栏及经典编辑器“发布”面板中，显示“本次发送到 Telegram”勾选框及独立配置覆盖面板。<br>关闭：隐藏编辑器面板，所有文章完全按全局规则静默执行。',
        )
    );

    add_settings_field(
        'p2tg_delay',
        '延迟发送 (分钟)',
        'wptg_free_cn_render_field_number',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path' => 'p2tg.delay',
            'min'  => 0,
            'max'  => 1440,
            'step' => 0.1,
            'desc' => '文章发布后延迟推送到 Telegram 的等待时间（单位：分钟）。输入 <code>0</code> 为即时发送，<code>0.5</code> 为延迟 30 秒。用于等待文章缩略图生成或留出临时编辑缓冲时间。依赖 WP-Cron 或 Action Scheduler。',
        )
    );

    add_settings_field(
        'p2tg_disable_notification',
        '静默发送 (不发通知提示音)',
        'wptg_free_cn_render_field_checkbox',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path' => 'p2tg.disable_notification',
            'desc' => '开启：Telegram 频道或群组收到消息时不产生声音提示或振动（静默发送）。<br>关闭：正常触发声音通知。',
        )
    );

    add_settings_field(
        'p2tg_protect_content',
        '保护消息内容 (禁止转发与保存)',
        'wptg_free_cn_render_field_checkbox',
        'wptg_free_cn_p2tg',
        'wptg_p2tg_section',
        array(
            'path' => 'p2tg.protect_content',
            'desc' => '开启：通知 Telegram 客户端限制用户转发或复制该消息。<br>关闭：允许订阅者正常转发和保存消息内容。',
        )
    );

    // ==========================================
    // 3. 邮件通知 (Notify)
    // ==========================================
    add_settings_section( 'wptg_notify_section', '邮件通知转发设置', 'wptg_free_cn_section_notify_desc', 'wptg_free_cn_notify' );

    add_settings_field(
        'notify_active',
        '启用邮件通知转发',
        'wptg_free_cn_render_field_checkbox',
        'wptg_free_cn_notify',
        'wptg_notify_section',
        array(
            'path' => 'notify.active',
            'desc' => '开启：当 WordPress 通过 <code>wp_mail()</code> 发送邮件时，同步将通知转发到指定的 Telegram 目标。<br>关闭：不转发任何邮件。注意：原站点的电子邮件将始终保持正常发送。',
        )
    );

    add_settings_field(
        'notify_watch_emails',
        '需要转发的收件邮箱',
        'wptg_free_cn_render_field_text',
        'wptg_free_cn_notify',
        'wptg_notify_section',
        array(
            'path'        => 'notify.watch_emails',
            'placeholder' => 'admin@example.com, notify@example.com',
            'desc'        => '需要匹配的邮件收件人地址（To 地址）。多个邮箱用英文逗号隔开。填写 <code>any</code> 则转发站点发出的所有邮件。默认为网站管理员邮箱。',
        )
    );

    add_settings_field(
        'notify_chat_ids',
        '通知接收目标 (Chat IDs)',
        'wptg_free_cn_render_field_textarea',
        'wptg_free_cn_notify',
        'wptg_notify_section',
        array(
            'path'        => 'notify.chat_ids',
            'is_array'    => true,
            'placeholder' => "123456789\n-1001234567890\n-1001234567890:123",
            'desc'        => '接收邮件通知的目标聊天 ID，每行一个。可以是您的个人 Telegram ID（需先在 Telegram 中向机器人发送 <code>/start</code>），也可以是管理员群组或话题。',
        )
    );

    add_settings_field(
        'notify_user_notifications',
        '向已绑定用户发送通知',
        'wptg_free_cn_render_field_checkbox',
        'wptg_free_cn_notify',
        'wptg_notify_section',
        array(
            'path' => 'notify.user_notifications',
            'desc' => '开启：如果邮件收件人是站内注册用户，且该用户已绑定了 Telegram Chat ID（存储于用户 Meta <code>wptelegram_user_id</code>），则优先直接转发给该用户个人。<br>关闭：仅转发到上方“通知接收目标”中配置的管理员目标。',
        )
    );

    add_settings_field(
        'notify_message_template',
        '通知消息模板',
        'wptg_free_cn_render_field_textarea',
        'wptg_free_cn_notify',
        'wptg_notify_section',
        array(
            'path'        => 'notify.message_template',
            'rows'        => 6,
            'placeholder' => "🔔‌<b>{email_subject}</b>🔔\n\n{email_message}",
            'desc'        => '邮件通知消息的排版格式。可用宏：<code>{email_subject}</code>（邮件主题）、<code>{email_message}</code>（邮件正文内容）。',
        )
    );

    add_settings_field(
        'notify_parse_mode',
        '通知消息格式',
        'wptg_free_cn_render_field_select',
        'wptg_free_cn_notify',
        'wptg_notify_section',
        array(
            'path'    => 'notify.parse_mode',
            'options' => array(
                'HTML' => 'HTML (推荐 - 格式化加粗、排版良好)',
                'none' => '纯文本 (None)',
            ),
            'desc'    => '邮件通知转发的消息解析模式。',
        )
    );

    // ==========================================
    // 4. 连接代理 (Proxy)
    // ==========================================
    add_settings_section( 'wptg_proxy_section', 'Telegram API 连接代理设置', 'wptg_free_cn_section_proxy_desc', 'wptg_free_cn_proxy' );

    add_settings_field(
        'proxy_active',
        '启用代理连接',
        'wptg_free_cn_render_field_checkbox',
        'wptg_free_cn_proxy',
        'wptg_proxy_section',
        array(
            'path' => 'proxy.active',
            'desc' => '开启：如果您的主机位于中国大陆等无法直接连通 Telegram API (<code>api.telegram.org</code>) 的网络环境，请启用代理。<br>关闭：站点服务器直接请求 Telegram 官方接口。',
        )
    );

    add_settings_field(
        'proxy_method',
        '代理连接方式',
        'wptg_free_cn_render_field_select',
        'wptg_free_cn_proxy',
        'wptg_proxy_section',
        array(
            'path'    => 'proxy.proxy_method',
            'id'      => 'wptg-proxy-method',
            'options' => array(
                'cf_worker'     => 'Cloudflare Worker 反向代理 (推荐 - 稳定、免费、响应快)',
                'google_script' => 'Google Apps Script (网页脚本转发)',
                'php_proxy'     => 'HTTP / SOCKS 本地代理服务器 (通过 cURL 代理转发)',
            ),
            'desc'    => '根据您的基础设施选择最合适的代理协议或服务。',
        )
    );

    add_settings_field(
        'proxy_cf_worker_url',
        'Cloudflare Worker 服务地址',
        'wptg_free_cn_render_field_text',
        'wptg_free_cn_proxy',
        'wptg_proxy_section',
        array(
            'path'        => 'proxy.cf_worker_url',
            'row_class'   => 'wptg-proxy-fields wptg-proxy-cf_worker',
            'placeholder' => 'https://your-worker-subdomain.workers.dev',
            'desc'        => '您在 Cloudflare 上部署的 Telegram 反向代理 Worker 完整地址。插件发起请求时会自动追加 <code>/bot</code> 路径。',
        )
    );

    add_settings_field(
        'proxy_google_script_url',
        'Google Apps Script 部署地址',
        'wptg_free_cn_render_field_text',
        'wptg_free_cn_proxy',
        'wptg_proxy_section',
        array(
            'path'        => 'proxy.google_script_url',
            'row_class'   => 'wptg-proxy-fields wptg-proxy-google_script',
            'placeholder' => 'https://script.google.com/macros/s/XXXXXX/exec',
            'desc'        => '部署为 Web 应用的 Google Apps Script 完整 URL。',
        )
    );

    add_settings_field(
        'proxy_host',
        '代理服务器地址 (Host)',
        'wptg_free_cn_render_field_text',
        'wptg_free_cn_proxy',
        'wptg_proxy_section',
        array(
            'path'        => 'proxy.proxy_host',
            'row_class'   => 'wptg-proxy-fields wptg-proxy-php_proxy',
            'placeholder' => '127.0.0.1 或 proxy.example.com',
            'desc'        => '代理服务器主机名或 IP 地址（请勿包含 http:// 或端口）。',
        )
    );

    add_settings_field(
        'proxy_port',
        '代理服务器端口 (Port)',
        'wptg_free_cn_render_field_number',
        'wptg_free_cn_proxy',
        'wptg_proxy_section',
        array(
            'path'      => 'proxy.proxy_port',
            'row_class' => 'wptg-proxy-fields wptg-proxy-php_proxy',
            'min'       => 1,
            'max'       => 65535,
            'desc'      => '代理服务器端口号，范围 1–65535（如 7890、1080 等）。',
        )
    );

    add_settings_field(
        'proxy_type',
        '代理协议类型',
        'wptg_free_cn_render_field_select',
        'wptg_free_cn_proxy',
        'wptg_proxy_section',
        array(
            'path'      => 'proxy.proxy_type',
            'row_class' => 'wptg-proxy-fields wptg-proxy-php_proxy',
            'options'   => array(
                'CURLPROXY_HTTP'   => 'HTTP 代理 (CURLPROXY_HTTP)',
                'CURLPROXY_HTTPS'  => 'HTTPS 代理 (CURLPROXY_HTTPS)',
                'CURLPROXY_SOCKS4' => 'SOCKS4 (CURLPROXY_SOCKS4)',
                'CURLPROXY_SOCKS5' => 'SOCKS5 (CURLPROXY_SOCKS5)',
                'CURLPROXY_SOCKS5_HOSTNAME' => 'SOCKS5 代理解析域名 (CURLPROXY_SOCKS5_HOSTNAME)',
            ),
            'desc'      => '选择代理协议。若使用 Clash / V2Ray 等工具的本地端口，通常选择 HTTP 或 SOCKS5。',
        )
    );

    add_settings_field(
        'proxy_username',
        '代理用户名 (可选)',
        'wptg_free_cn_render_field_text',
        'wptg_free_cn_proxy',
        'wptg_proxy_section',
        array(
            'path'      => 'proxy.proxy_username',
            'row_class' => 'wptg-proxy-fields wptg-proxy-php_proxy',
            'desc'      => '仅当代理服务器需要用户认证时填写。若无需认证请留空。',
        )
    );

    add_settings_field(
        'proxy_password',
        '代理密码 (可选)',
        'wptg_free_cn_render_field_password',
        'wptg_free_cn_proxy',
        'wptg_proxy_section',
        array(
            'path'      => 'proxy.proxy_password',
            'row_class' => 'wptg-proxy-fields wptg-proxy-php_proxy',
            'desc'      => '代理服务器认证密码。保存后不会在页面明文回显。如不修改密码请留空。',
        )
    );

    // ==========================================
    // 5. 高级设置 (Advanced)
    // ==========================================
    add_settings_section( 'wptg_advanced_section', '高级与系统设置', 'wptg_free_cn_section_advanced_desc', 'wptg_free_cn_advanced' );

    add_settings_field(
        'advanced_send_files_by_url',
        '通过图片链接发送文件',
        'wptg_free_cn_render_field_checkbox',
        'wptg_free_cn_advanced',
        'wptg_advanced_section',
        array(
            'path' => 'advanced.send_files_by_url',
            'desc' => '开启（推荐）：向 Telegram 发送特色图片或附件的公开 URL 地址，由 Telegram 官方服务器自动下载展示。无需消耗本站服务器的上传流量与 cURL 资源。<br>关闭：由本站服务器将本地图片文件以 multipart/form-data 格式上传到 Telegram。当站点处于内网或图片外链被防盗链拦截时可尝试关闭。',
        )
    );

    add_settings_field(
        'advanced_enable_logs',
        '调试日志记录',
        'wptg_free_cn_render_field_checkbox_group',
        'wptg_free_cn_advanced',
        'wptg_advanced_section',
        array(
            'path'    => 'advanced.enable_logs',
            'options' => array(
                'bot_api' => '机器人接口日志 (记录所有向 api.telegram.org 发出的请求、响应和网络错误)',
                'p2tg'    => '文章推送日志 (记录文章发布、规则判定、延迟调度和发送细节)',
            ),
            'desc'    => '开启后日志将写入 <code>wp-content/wptg-logs/</code> 目录，单文件上限 1MB 并自动循环滚动。令牌已做脱敏处理。建议仅在排查问题时开启。',
        )
    );

    add_settings_field(
        'advanced_clean_uninstall',
        '卸载时清理新版数据',
        'wptg_free_cn_render_field_checkbox',
        'wptg_free_cn_advanced',
        'wptg_advanced_section',
        array(
            'path' => 'advanced.clean_uninstall',
            'desc' => '开启：在 WordPress 后台“删除插件”时，自动清除本插件的所有配置项 (<code>wptg_free_cn_options</code>) 及日志文件。<br>关闭：删除插件时保留设置，方便后续重新安装恢复。注意：停用插件不会清理任何数据。旧版 WPTelegram 的原始数据将始终保留，不会被删除。',
        )
    );
}

/**
 * 分区说明回调
 */
function wptg_free_cn_section_basic_desc() {
    echo '<p>配置用于与 Telegram 通信的 Bot 令牌。您可以通过测试按钮验证连通性。</p>';
}
function wptg_free_cn_section_p2tg_desc() {
    echo '<p>配置 WordPress 文章发布或更新时自动推送到 Telegram 频道或群组的规则与模板。</p>';
}
function wptg_free_cn_section_notify_desc() {
    echo '<p>将站点的重要系统邮件（如新用户注册、密码找回、待审核评论等）实时转发到您的 Telegram。</p>';
}
function wptg_free_cn_section_proxy_desc() {
    echo '<p>当您的服务器在中国大陆或受限制网络中无法直连 Telegram 时，可通过代理服务进行转发。</p>';
}
function wptg_free_cn_section_advanced_desc() {
    echo '<p>系统性能、文件传输方式、调试日志及卸载清理选项。</p>';
}

/**
 * 字段渲染回调函数
 */
function wptg_free_cn_render_field_text( $args ) {
    $val = wptg_free_cn_get_option( $args['path'], '' );
    $id  = isset( $args['id'] ) ? $args['id'] : '';
    $placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
    echo '<input type="text" name="wptg_free_cn_options[' . esc_attr( $args['path'] ) . ']" id="' . esc_attr( $id ) . '" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr( $placeholder ) . '" class="regular-text" />';
    if ( ! empty( $args['desc'] ) ) {
        echo '<p class="wptg-help">' . wp_kses_post( $args['desc'] ) . '</p>';
    }
}

function wptg_free_cn_render_field_password( $args ) {
    $val = wptg_free_cn_get_option( $args['path'], '' );
    $has_val = ! empty( $val );
    echo '<input type="password" name="wptg_free_cn_options[' . esc_attr( $args['path'] ) . ']" value="" placeholder="' . ( $has_val ? '•••••••• (已保存，留空则不修改)' : '输入密码' ) . '" class="regular-text" autocomplete="new-password" />';
    if ( ! empty( $args['desc'] ) ) {
        echo '<p class="wptg-help">' . wp_kses_post( $args['desc'] ) . '</p>';
    }
}

function wptg_free_cn_render_field_number( $args ) {
    $val  = wptg_free_cn_get_option( $args['path'], 0 );
    $min  = isset( $args['min'] ) ? $args['min'] : 0;
    $max  = isset( $args['max'] ) ? $args['max'] : 999999;
    $step = isset( $args['step'] ) ? $args['step'] : 1;
    echo '<input type="number" name="wptg_free_cn_options[' . esc_attr( $args['path'] ) . ']" value="' . esc_attr( $val ) . '" min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '" step="' . esc_attr( $step ) . '" class="small-text" />';
    if ( ! empty( $args['desc'] ) ) {
        echo '<p class="wptg-help">' . wp_kses_post( $args['desc'] ) . '</p>';
    }
}

function wptg_free_cn_render_field_checkbox( $args ) {
    $val = (bool) wptg_free_cn_get_option( $args['path'], false );
    echo '<label><input type="checkbox" name="wptg_free_cn_options[' . esc_attr( $args['path'] ) . ']" value="1" ' . checked( true, $val, false ) . ' /> ' . ( isset( $args['label'] ) ? esc_html( $args['label'] ) : '启用' ) . '</label>';
    if ( ! empty( $args['desc'] ) ) {
        echo '<p class="wptg-help">' . wp_kses_post( $args['desc'] ) . '</p>';
    }
}

function wptg_free_cn_render_field_checkbox_group( $args ) {
    $current = (array) wptg_free_cn_get_option( $args['path'], array() );
    foreach ( $args['options'] as $key => $label ) {
        $checked = in_array( $key, $current, true );
        echo '<p style="margin: 4px 0;"><label><input type="checkbox" name="wptg_free_cn_options[' . esc_attr( $args['path'] ) . '][]" value="' . esc_attr( $key ) . '" ' . checked( true, $checked, false ) . ' /> ' . esc_html( $label ) . '</label></p>';
    }
    if ( ! empty( $args['desc'] ) ) {
        echo '<p class="wptg-help">' . wp_kses_post( $args['desc'] ) . '</p>';
    }
}

function wptg_free_cn_render_field_select( $args ) {
    $val = wptg_free_cn_get_option( $args['path'], '' );
    $id  = isset( $args['id'] ) ? $args['id'] : '';
    echo '<select name="wptg_free_cn_options[' . esc_attr( $args['path'] ) . ']" id="' . esc_attr( $id ) . '">';
    foreach ( $args['options'] as $key => $label ) {
        echo '<option value="' . esc_attr( $key ) . '" ' . selected( $val, $key, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select>';
    if ( ! empty( $args['desc'] ) ) {
        echo '<p class="wptg-help">' . wp_kses_post( $args['desc'] ) . '</p>';
    }
}

function wptg_free_cn_render_field_textarea( $args ) {
    $val = wptg_free_cn_get_option( $args['path'], '' );
    if ( ! empty( $args['is_array'] ) && is_array( $val ) ) {
        $val = implode( "\n", $val );
    }
    $rows = isset( $args['rows'] ) ? intval( $args['rows'] ) : 4;
    $placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
    echo '<textarea name="wptg_free_cn_options[' . esc_attr( $args['path'] ) . ']" rows="' . esc_attr( $rows ) . '" placeholder="' . esc_attr( $placeholder ) . '" class="large-text code" style="font-family: monospace;">' . esc_textarea( $val ) . '</textarea>';
    if ( ! empty( $args['desc'] ) ) {
        echo '<p class="wptg-help">' . wp_kses_post( $args['desc'] ) . '</p>';
    }
}

function wptg_free_cn_render_field_post_types( $args ) {
    $post_types = get_post_types( array( 'public' => true ), 'objects' );
    $current    = (array) wptg_free_cn_get_option( $args['path'], array( 'post' ) );
    
    foreach ( $post_types as $pt ) {
        if ( in_array( $pt->name, array( 'attachment' ), true ) ) {
            continue;
        }
        $checked = in_array( $pt->name, $current, true );
        echo '<label style="margin-right: 15px; display: inline-block;"><input type="checkbox" name="wptg_free_cn_options[' . esc_attr( $args['path'] ) . '][]" value="' . esc_attr( $pt->name ) . '" ' . checked( true, $checked, false ) . ' /> ' . esc_html( $pt->labels->singular_name . ' (' . $pt->name . ')' ) . '</label>';
    }
    if ( ! empty( $args['desc'] ) ) {
        echo '<p class="wptg-help">' . wp_kses_post( $args['desc'] ) . '</p>';
    }
}

function wptg_free_cn_render_field_rules( $args ) {
    $rules = wptg_free_cn_get_option( $args['path'], array() );
    $json  = ! empty( $rules ) ? wp_json_encode( $rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) : '';
    echo '<textarea name="wptg_free_cn_options[' . esc_attr( $args['path'] ) . ']" rows="5" class="large-text code" style="font-family: monospace;" placeholder="[]">' . esc_textarea( $json ) . '</textarea>';
    if ( ! empty( $args['desc'] ) ) {
        echo '<p class="wptg-help">' . wp_kses_post( $args['desc'] ) . '</p>';
    }
}

/**
 * 展平/反展平点分路径辅助函数
 */
function wptg_free_cn_set_dot_path( &$array, $path, $value ) {
    $keys = explode( '.', $path );
    while ( count( $keys ) > 1 ) {
        $key = array_shift( $keys );
        if ( ! isset( $array[ $key ] ) || ! is_array( $array[ $key ] ) ) {
            $array[ $key ] = array();
        }
        $array = &$array[ $key ];
    }
    $array[ array_shift( $keys ) ] = $value;
}

/**
 * 通用标签页提交清洗与深度合并
 */
/**
 * 统一的主设置净化与深度合并函数（具备幂等性）
 */
function wptg_free_cn_sanitize_options( $input ) {
    if ( ! is_array( $input ) ) {
        return get_option( 'wptg_free_cn_options', wptg_free_cn_get_default_values() );
    }

    $defaults = wptg_free_cn_get_default_values();
    $current  = get_option( 'wptg_free_cn_options', array() );
    if ( ! is_array( $current ) || empty( $current ) ) {
        $current = $defaults;
    } else {
        $current = array_replace_recursive( $defaults, $current );
    }

    // 判断 $input 是否包含点分键（如 p2tg.active）
    $has_dot_keys = false;
    foreach ( array_keys( $input ) as $k ) {
        if ( strpos( (string) $k, '.' ) !== false ) {
            $has_dot_keys = true;
            break;
        }
    }

    // 幂等性检查：若 $input 已经是一个完整的选项结构且没有点分键，直接原样返回
    if ( ! $has_dot_keys && isset( $input['bot_token'] ) && isset( $input['p2tg'] ) && is_array( $input['p2tg'] ) && isset( $input['notify'] ) && is_array( $input['notify'] ) && isset( $input['proxy'] ) && is_array( $input['proxy'] ) && isset( $input['advanced'] ) && is_array( $input['advanced'] ) ) {
        return $input;
    }

    // 判定本次表单提交涉及哪些模块
    $is_basic_submitted    = false;
    $is_p2tg_submitted     = false;
    $is_notify_submitted   = false;
    $is_proxy_submitted    = false;
    $is_advanced_submitted = false;

    foreach ( array_keys( $input ) as $k ) {
        $k_str = (string) $k;
        if ( strpos( $k_str, 'p2tg.' ) === 0 || $k_str === 'p2tg' ) {
            $is_p2tg_submitted = true;
        } elseif ( strpos( $k_str, 'notify.' ) === 0 || $k_str === 'notify' ) {
            $is_notify_submitted = true;
        } elseif ( strpos( $k_str, 'proxy.' ) === 0 || $k_str === 'proxy' ) {
            $is_proxy_submitted = true;
        } elseif ( strpos( $k_str, 'advanced.' ) === 0 || $k_str === 'advanced' ) {
            $is_advanced_submitted = true;
        } elseif ( $k_str === 'bot_token' || $k_str === 'bot_username' || strpos( $k_str, 'basic.' ) === 0 ) {
            $is_basic_submitted = true;
        }
    }

    // 1. 处理 Basic 模块
    if ( $is_basic_submitted ) {
        $token_val = null;
        if ( isset( $input['bot_token'] ) ) {
            $token_val = trim( $input['bot_token'] );
        } elseif ( isset( $input['basic.bot_token'] ) ) {
            $token_val = trim( $input['basic.bot_token'] );
        }
        if ( null !== $token_val ) {
            $raw_token = sanitize_text_field( $token_val );
            if ( '' !== $raw_token && ! preg_match( '/^\d+:[A-Za-z0-9_-]{35,}$/', $raw_token ) ) {
                add_settings_error( 'wptg_free_cn_options', 'bot_token_invalid', '机器人 Token 格式不正确，标准格式为“数字:字母和符号”，例如 123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ。' );
            } else {
                $current['bot_token'] = $raw_token;
            }
        }
        if ( isset( $input['bot_username'] ) ) {
            $current['bot_username'] = sanitize_text_field( ltrim( trim( $input['bot_username'] ), '@' ) );
        } elseif ( isset( $input['basic.bot_username'] ) ) {
            $current['bot_username'] = sanitize_text_field( ltrim( trim( $input['basic.bot_username'] ), '@' ) );
        }
    }

    // 2. 处理 P2TG 模块
    if ( $is_p2tg_submitted ) {
        $p2tg_in = isset( $input['p2tg'] ) && is_array( $input['p2tg'] ) ? $input['p2tg'] : array();

        $p2tg_bools = array(
            'active', 'excerpt_preserve_eol', 'send_featured_image',
            'single_message', 'cats_as_tags', 'link_preview_disabled',
            'link_preview_above_text', 'inline_url_button', 'plugin_posts',
            'post_edit_switch', 'disable_notification', 'protect_content',
        );
        foreach ( $p2tg_bools as $bool_key ) {
            if ( isset( $input[ 'p2tg.' . $bool_key ] ) ) {
                $current['p2tg'][ $bool_key ] = ! empty( $input[ 'p2tg.' . $bool_key ] );
            } elseif ( isset( $p2tg_in[ $bool_key ] ) ) {
                $current['p2tg'][ $bool_key ] = ! empty( $p2tg_in[ $bool_key ] );
            } else {
                $current['p2tg'][ $bool_key ] = false;
            }
        }

        // channels
        if ( isset( $input['p2tg.channels'] ) || isset( $p2tg_in['channels'] ) ) {
            $raw_channels = isset( $input['p2tg.channels'] ) ? $input['p2tg.channels'] : $p2tg_in['channels'];
            if ( is_array( $raw_channels ) ) {
                $current['p2tg']['channels'] = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $raw_channels ) ) ) );
            } else {
                $lines = explode( "\n", (string) $raw_channels );
                $channels = array();
                foreach ( $lines as $line ) {
                    $line = trim( $line );
                    if ( '' !== $line ) {
                        $channels[] = sanitize_text_field( $line );
                    }
                }
                $current['p2tg']['channels'] = array_values( array_unique( $channels ) );
            }
        }

        // send_when
        if ( isset( $input['p2tg.send_when'] ) || isset( $p2tg_in['send_when'] ) ) {
            $raw_send_when = isset( $input['p2tg.send_when'] ) ? $input['p2tg.send_when'] : $p2tg_in['send_when'];
            $current['p2tg']['send_when'] = array_map( 'sanitize_text_field', (array) $raw_send_when );
        } else {
            $current['p2tg']['send_when'] = array();
        }

        // post_types
        if ( isset( $input['p2tg.post_types'] ) || isset( $p2tg_in['post_types'] ) ) {
            $raw_post_types = isset( $input['p2tg.post_types'] ) ? $input['p2tg.post_types'] : $p2tg_in['post_types'];
            $current['p2tg']['post_types'] = array_map( 'sanitize_text_field', (array) $raw_post_types );
        } else {
            $current['p2tg']['post_types'] = array();
        }

        // rules
        if ( isset( $input['p2tg.rules'] ) || isset( $p2tg_in['rules'] ) ) {
            $raw_rules = isset( $input['p2tg.rules'] ) ? $input['p2tg.rules'] : $p2tg_in['rules'];
            if ( is_array( $raw_rules ) ) {
                $current['p2tg']['rules'] = $raw_rules;
            } else {
                $trimmed = trim( (string) $raw_rules );
                if ( '' === $trimmed ) {
                    $current['p2tg']['rules'] = array();
                } else {
                    $decoded = json_decode( $trimmed, true );
                    if ( is_array( $decoded ) ) {
                        $current['p2tg']['rules'] = $decoded;
                    } else {
                        add_settings_error( 'wptg_free_cn_options', 'p2tg_rules_invalid', '规则 JSON 格式不正确，已保留原规则。' );
                    }
                }
            }
        }

        // message_template
        if ( isset( $input['p2tg.message_template'] ) || isset( $p2tg_in['message_template'] ) ) {
            $raw_tpl = isset( $input['p2tg.message_template'] ) ? $input['p2tg.message_template'] : $p2tg_in['message_template'];
            $current['p2tg']['message_template'] = wptg_free_cn_sanitize_message_template( $raw_tpl );
        }

        // excerpt_source
        if ( isset( $input['p2tg.excerpt_source'] ) || isset( $p2tg_in['excerpt_source'] ) ) {
            $raw_src = isset( $input['p2tg.excerpt_source'] ) ? $input['p2tg.excerpt_source'] : $p2tg_in['excerpt_source'];
            $current['p2tg']['excerpt_source'] = sanitize_text_field( $raw_src );
        }

        // excerpt_length
        if ( isset( $input['p2tg.excerpt_length'] ) || isset( $p2tg_in['excerpt_length'] ) ) {
            $raw_len = isset( $input['p2tg.excerpt_length'] ) ? $input['p2tg.excerpt_length'] : $p2tg_in['excerpt_length'];
            $current['p2tg']['excerpt_length'] = max( 1, min( 1000, intval( $raw_len ) ) );
        }

        // image_position
        if ( isset( $input['p2tg.image_position'] ) || isset( $p2tg_in['image_position'] ) ) {
            $raw_pos = isset( $input['p2tg.image_position'] ) ? $input['p2tg.image_position'] : $p2tg_in['image_position'];
            $current['p2tg']['image_position'] = in_array( $raw_pos, array( 'before', 'after' ), true ) ? $raw_pos : 'before';
        }

        // parse_mode
        if ( isset( $input['p2tg.parse_mode'] ) || isset( $p2tg_in['parse_mode'] ) ) {
            $raw_mode = isset( $input['p2tg.parse_mode'] ) ? $input['p2tg.parse_mode'] : $p2tg_in['parse_mode'];
            $current['p2tg']['parse_mode'] = ( 'HTML' === $raw_mode ) ? 'HTML' : 'none';
        }

        // inline_button_text / url
        if ( isset( $input['p2tg.inline_button_text'] ) || isset( $p2tg_in['inline_button_text'] ) ) {
            $raw_bt = isset( $input['p2tg.inline_button_text'] ) ? $input['p2tg.inline_button_text'] : $p2tg_in['inline_button_text'];
            $current['p2tg']['inline_button_text'] = sanitize_text_field( $raw_bt );
        }
        if ( isset( $input['p2tg.inline_button_url'] ) || isset( $p2tg_in['inline_button_url'] ) ) {
            $raw_bu = isset( $input['p2tg.inline_button_url'] ) ? $input['p2tg.inline_button_url'] : $p2tg_in['inline_button_url'];
            $current['p2tg']['inline_button_url'] = sanitize_text_field( $raw_bu );
        }

        // link_preview_url
        if ( isset( $input['p2tg.link_preview_url'] ) || isset( $p2tg_in['link_preview_url'] ) ) {
            $raw_lu = isset( $input['p2tg.link_preview_url'] ) ? $input['p2tg.link_preview_url'] : $p2tg_in['link_preview_url'];
            $current['p2tg']['link_preview_url'] = sanitize_text_field( $raw_lu );
        }

        // delay
        if ( isset( $input['p2tg.delay'] ) || isset( $p2tg_in['delay'] ) ) {
            $raw_delay = isset( $input['p2tg.delay'] ) ? $input['p2tg.delay'] : $p2tg_in['delay'];
            $current['p2tg']['delay'] = max( 0, floatval( $raw_delay ) );
        }
    }

    // 3. 处理 Notify 模块
    if ( $is_notify_submitted ) {
        $notify_in = isset( $input['notify'] ) && is_array( $input['notify'] ) ? $input['notify'] : array();

        $current['notify']['active'] = isset( $input['notify.active'] ) ? ! empty( $input['notify.active'] ) : ( isset( $notify_in['active'] ) && ! empty( $notify_in['active'] ) );
        $current['notify']['user_notifications'] = isset( $input['notify.user_notifications'] ) ? ! empty( $input['notify.user_notifications'] ) : ( isset( $notify_in['user_notifications'] ) && ! empty( $notify_in['user_notifications'] ) );

        if ( isset( $input['notify.watch_emails'] ) || isset( $notify_in['watch_emails'] ) ) {
            $raw_we = isset( $input['notify.watch_emails'] ) ? $input['notify.watch_emails'] : $notify_in['watch_emails'];
            $current['notify']['watch_emails'] = sanitize_text_field( trim( (string) $raw_we ) );
        }

        if ( isset( $input['notify.chat_ids'] ) || isset( $notify_in['chat_ids'] ) ) {
            $raw_chats = isset( $input['notify.chat_ids'] ) ? $input['notify.chat_ids'] : $notify_in['chat_ids'];
            if ( is_array( $raw_chats ) ) {
                $current['notify']['chat_ids'] = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $raw_chats ) ) ) );
            } else {
                $lines = explode( "\n", (string) $raw_chats );
                $chats = array();
                foreach ( $lines as $line ) {
                    $line = trim( $line );
                    if ( '' !== $line ) {
                        $chats[] = sanitize_text_field( $line );
                    }
                }
                $current['notify']['chat_ids'] = array_values( array_unique( $chats ) );
            }
        }

        if ( isset( $input['notify.message_template'] ) || isset( $notify_in['message_template'] ) ) {
            $raw_mt = isset( $input['notify.message_template'] ) ? $input['notify.message_template'] : $notify_in['message_template'];
            $current['notify']['message_template'] = wptg_free_cn_sanitize_message_template( $raw_mt );
        }

        if ( isset( $input['notify.parse_mode'] ) || isset( $notify_in['parse_mode'] ) ) {
            $raw_npm = isset( $input['notify.parse_mode'] ) ? $input['notify.parse_mode'] : $notify_in['parse_mode'];
            $current['notify']['parse_mode'] = ( 'HTML' === $raw_npm ) ? 'HTML' : 'none';
        }
    }

    // 4. 处理 Proxy 模块
    if ( $is_proxy_submitted ) {
        $proxy_in = isset( $input['proxy'] ) && is_array( $input['proxy'] ) ? $input['proxy'] : array();

        $current['proxy']['active'] = isset( $input['proxy.active'] ) ? ! empty( $input['proxy.active'] ) : ( isset( $proxy_in['active'] ) && ! empty( $proxy_in['active'] ) );

        if ( isset( $input['proxy.proxy_method'] ) || isset( $proxy_in['proxy_method'] ) ) {
            $current['proxy']['proxy_method'] = sanitize_text_field( isset( $input['proxy.proxy_method'] ) ? $input['proxy.proxy_method'] : $proxy_in['proxy_method'] );
        }
        if ( isset( $input['proxy.cf_worker_url'] ) || isset( $proxy_in['cf_worker_url'] ) ) {
            $current['proxy']['cf_worker_url'] = esc_url_raw( trim( isset( $input['proxy.cf_worker_url'] ) ? $input['proxy.cf_worker_url'] : $proxy_in['cf_worker_url'] ) );
        }
        if ( isset( $input['proxy.google_script_url'] ) || isset( $proxy_in['google_script_url'] ) ) {
            $current['proxy']['google_script_url'] = esc_url_raw( trim( isset( $input['proxy.google_script_url'] ) ? $input['proxy.google_script_url'] : $proxy_in['google_script_url'] ) );
        }
        if ( isset( $input['proxy.proxy_host'] ) || isset( $proxy_in['proxy_host'] ) ) {
            $current['proxy']['proxy_host'] = sanitize_text_field( trim( isset( $input['proxy.proxy_host'] ) ? $input['proxy.proxy_host'] : $proxy_in['proxy_host'] ) );
        }
        if ( isset( $input['proxy.proxy_port'] ) || isset( $proxy_in['proxy_port'] ) ) {
            $current['proxy']['proxy_port'] = max( 1, min( 65535, intval( isset( $input['proxy.proxy_port'] ) ? $input['proxy.proxy_port'] : $proxy_in['proxy_port'] ) ) );
        }
        if ( isset( $input['proxy.proxy_type'] ) || isset( $proxy_in['proxy_type'] ) ) {
            $current['proxy']['proxy_type'] = sanitize_text_field( isset( $input['proxy.proxy_type'] ) ? $input['proxy.proxy_type'] : $proxy_in['proxy_type'] );
        }
        if ( isset( $input['proxy.proxy_username'] ) || isset( $proxy_in['proxy_username'] ) ) {
            $current['proxy']['proxy_username'] = sanitize_text_field( trim( isset( $input['proxy.proxy_username'] ) ? $input['proxy.proxy_username'] : $proxy_in['proxy_username'] ) );
        }
        if ( isset( $input['proxy.proxy_password'] ) || isset( $proxy_in['proxy_password'] ) ) {
            $pwd = trim( isset( $input['proxy.proxy_password'] ) ? $input['proxy.proxy_password'] : $proxy_in['proxy_password'] );
            if ( '' !== $pwd ) {
                $current['proxy']['proxy_password'] = $pwd;
            }
        }
    }

    // 5. 处理 Advanced 模块
    if ( $is_advanced_submitted ) {
        $adv_in = isset( $input['advanced'] ) && is_array( $input['advanced'] ) ? $input['advanced'] : array();

        $current['advanced']['send_files_by_url'] = isset( $input['advanced.send_files_by_url'] ) ? ! empty( $input['advanced.send_files_by_url'] ) : ( isset( $adv_in['send_files_by_url'] ) && ! empty( $adv_in['send_files_by_url'] ) );
        $current['advanced']['clean_uninstall']   = isset( $input['advanced.clean_uninstall'] ) ? ! empty( $input['advanced.clean_uninstall'] ) : ( isset( $adv_in['clean_uninstall'] ) && ! empty( $adv_in['clean_uninstall'] ) );

        $raw_logs = isset( $input['advanced.enable_logs'] ) ? $input['advanced.enable_logs'] : ( isset( $adv_in['enable_logs'] ) ? $adv_in['enable_logs'] : array() );
        $current['advanced']['enable_logs'] = array_map( 'sanitize_text_field', (array) $raw_logs );
    }

    return $current;
}

function wptg_free_cn_sanitize_basic( $input ) { return wptg_free_cn_sanitize_options( $input ); }
function wptg_free_cn_sanitize_p2tg( $input ) { return wptg_free_cn_sanitize_options( $input ); }
function wptg_free_cn_sanitize_notify( $input ) { return wptg_free_cn_sanitize_options( $input ); }
function wptg_free_cn_sanitize_proxy( $input ) { return wptg_free_cn_sanitize_options( $input ); }
function wptg_free_cn_sanitize_advanced( $input ) { return wptg_free_cn_sanitize_options( $input ); }

/**
 * AJAX 处理：测试机器人连接 (getMe)
 */
function wptg_free_cn_handle_test_connection() {
    check_ajax_referer( 'wptg_free_cn_nonce', '_wpnonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( '无权执行此操作。' );
    }

    $bot_token = isset( $_POST['bot_token'] ) ? sanitize_text_field( trim( $_POST['bot_token'] ) ) : '';
    if ( empty( $bot_token ) ) {
        $bot_token = wptg_free_cn_get_option( 'bot_token' );
    }

    if ( empty( $bot_token ) ) {
        wp_send_json_error( '请先输入机器人令牌 (Bot Token)。' );
    }

    if ( ! class_exists( '\WPTelegram\BotAPI\API' ) ) {
        wp_send_json_error( '无法加载 Telegram Bot API 类库，请检查插件 vendor 依赖完整性。' );
    }

    try {
        $api = new \WPTelegram\BotAPI\API( $bot_token );
        $response = $api->getMe();

        if ( $api->is_success( $response ) ) {
            $decoded = is_object( $response ) && method_exists( $response, 'get_decoded_body' ) ? $response->get_decoded_body() : array();
            $result  = isset( $decoded['result'] ) ? $decoded['result'] : array();
            wp_send_json_success( array(
                'first_name' => isset( $result['first_name'] ) ? $result['first_name'] : '',
                'username'   => isset( $result['username'] ) ? $result['username'] : '',
                'id'         => isset( $result['id'] ) ? $result['id'] : '',
            ) );
        } else {
            $error_message = is_wp_error( $response ) ? $response->get_error_message() : ( is_object( $response ) && method_exists( $response, 'get_response_message' ) ? $response->get_response_message() : '连接失败' );
            wp_send_json_error( $error_message );
        }
    } catch ( \Throwable $e ) {
        wp_send_json_error( $e->getMessage() );
    }
}

/**
 * AJAX 处理：发送测试消息
 */
function wptg_free_cn_handle_test_send() {
    check_ajax_referer( 'wptg_free_cn_nonce', '_wpnonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( '无权执行此操作。' );
    }

    $bot_token = wptg_free_cn_get_option( 'bot_token' );
    if ( empty( $bot_token ) ) {
        wp_send_json_error( '请先保存有效的机器人令牌。' );
    }

    $raw_chat_id = isset( $_POST['chat_id'] ) ? sanitize_text_field( trim( $_POST['chat_id'] ) ) : '';
    if ( empty( $raw_chat_id ) ) {
        wp_send_json_error( '请提供测试目标聊天 ID 或频道用户名。' );
    }

    // 解析 chat_id 和 thread_id
    $chat_id   = $raw_chat_id;
    $thread_id = null;
    $parts     = explode( '|', $raw_chat_id );
    $channel_id = trim( $parts[0] );

    if ( strpos( $channel_id, ':' ) !== false && strpos( $channel_id, '@' ) !== 0 ) {
        $id_parts  = explode( ':', $channel_id );
        $chat_id   = $id_parts[0];
        $thread_id = $id_parts[1];
    } else {
        $chat_id = $channel_id;
    }

    try {
        $api = new \WPTelegram\BotAPI\API( $bot_token );
        $params = array(
            'chat_id' => $chat_id,
            'text'    => "🎉 <b>Telegram 中文免费版 - 测试消息</b>\n\n您的站点配置已成功连通！消息推送链路运行正常。\n\n发送时间：" . current_time( 'mysql' ),
            'parse_mode' => 'HTML',
        );
        if ( ! empty( $thread_id ) ) {
            $params['message_thread_id'] = $thread_id;
        }

        $result = $api->sendMessage( $params );

        if ( $api->is_success( $result ) ) {
            wp_send_json_success( '测试消息已成功送达！' );
        } else {
            $msg = is_wp_error( $result ) ? $result->get_error_message() : ( is_object( $result ) && method_exists( $result, 'get_response_message' ) ? $result->get_response_message() : '发送失败' );
            wp_send_json_error( $msg );
        }
    } catch ( \Throwable $e ) {
        wp_send_json_error( $e->getMessage() );
    }
}
