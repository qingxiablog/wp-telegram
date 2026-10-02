<?php
/**
 * 后台管理菜单与页面渲染 (Admin Menu & Page Rendering)
 *
 * @package WPTelegramFreeCN
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 注册后台菜单项
 */
function wptg_free_cn_admin_menu() {
    add_options_page(
        '文章推送到 Telegram - 设置',
        'Telegram 推送',
        'manage_options',
        'wptg-free-cn',
        'wptg_free_cn_admin_page'
    );
}

/**
 * 加载后台样式与脚本
 */
function wptg_free_cn_admin_enqueue( $hook ) {
    if ( 'settings_page_wptg-free-cn' !== $hook ) {
        return;
    }

    $plugin_url = WPTG_FREE_CN_URL;

    wp_enqueue_style(
        'wptg-free-cn-admin',
        $plugin_url . '/assets/admin.css',
        array(),
        WPTG_FREE_CN_VER
    );

    wp_enqueue_script(
        'wptg-free-cn-admin',
        $plugin_url . '/assets/admin.js',
        array(),
        WPTG_FREE_CN_VER,
        true
    );

    wp_localize_script(
        'wptg-free-cn-admin',
        'wptgFreeCN',
        array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'wptg_free_cn_nonce' ),
        )
    );
}

/**
 * 后台管理员提示
 */
function wptg_free_cn_admin_notices() {
    $screen = get_current_screen();
    if ( ! $screen || 'settings_page_wptg-free-cn' !== $screen->id ) {
        return;
    }

    // 检查原版 WPTelegram 是否同时激活
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
    if ( is_plugin_active( 'wptelegram/wptelegram.php' ) ) {
        echo '<div class="notice notice-error"><p><strong>冲突警告：</strong>检测到原版 WPTelegram 插件处于激活状态。为防止重复推送消息或定时任务冲突，建议您先前往“插件”列表停用原版 WPTelegram。</p></div>';
    }

    // 检查是否检测到旧版配置且未导入
    $old_data = get_option( 'wptelegram' );
    $schema_ver = get_option( 'wptg_free_cn_schema_version', 0 );
    if ( ! empty( $old_data ) && $schema_ver < 1 ) {
        echo '<div class="notice notice-info is-dismissible"><p><strong>检测到旧版 WP Telegram (4.2.15) 配置数据！</strong>您可以在“基础设置”页面底部点击“导入旧版配置”按钮，一键迁移机器人令牌、目标频道、消息模板及待执行的延迟任务。</p></div>';
    }
}

/**
 * 渲染主设置页面
 */
function wptg_free_cn_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( '无权访问此页面。' );
    }

    $default_tab = 'basic';
    $tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : $default_tab;

    $tabs = array(
        'basic'    => '基础设置',
        'p2tg'     => '文章推送',
        'notify'   => '邮件通知',
        'proxy'    => '连接代理',
        'advanced' => '高级设置',
    );

    if ( ! array_key_exists( $tab, $tabs ) ) {
        $tab = $default_tab;
    }

    ?>
    <div class="wrap">
        <h1>文章推送到 Telegram - 设置</h1>

        <h2 class="nav-tab-wrapper wptg-tabs">
            <?php foreach ( $tabs as $tab_slug => $tab_name ) : ?>
                <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wptg-free-cn', 'tab' => $tab_slug ), admin_url( 'options-general.php' ) ) ); ?>" class="nav-tab <?php echo $tab === $tab_slug ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html( $tab_name ); ?>
                </a>
            <?php endforeach; ?>
        </h2>

        <div class="wptg-settings-container" style="max-width: 900px; margin-top: 15px;">
            <form action="options.php" method="post" class="wptg-settings-form">
                <?php
                settings_fields( 'wptg_free_cn_' . $tab );
                do_settings_sections( 'wptg_free_cn_' . $tab );
                submit_button( '保存当前页面设置' );
                ?>
            </form>

            <?php if ( 'basic' === $tab ) : ?>
                <!-- 基础设置附加工具卡片 -->
                <hr style="margin: 30px 0 20px;">
                <h3>🔧 连通性测试与旧版迁移</h3>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">测试机器人连接</th>
                        <td>
                            <button type="button" id="wptg-test-connection" class="button button-secondary">测试机器人连接 (getMe)</button>
                            <div id="wptg-test-result" class="wptg-test-result"></div>
                            <p class="wptg-help">无需保存即可测试上方输入的机器人令牌。成功连通后将显示机器人的昵称和用户名，并自动填充用户名输入框。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">发送测试消息</th>
                        <td>
                            <input type="text" id="wptg-test-chat-id" class="regular-text" placeholder="@your_channel 或 123456789" />
                            <button type="button" id="wptg-test-send" class="button button-secondary">发送测试消息</button>
                            <div id="wptg-send-result" class="wptg-test-result"></div>
                            <p class="wptg-help">向指定目标发送一条排版良好的验证测试消息。请先确保在上方保存了有效的令牌，且机器人具备发送权限。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">旧版数据迁移</th>
                        <td>
                            <?php
                            $old_data   = get_option( 'wptelegram' );
                            $schema_ver = get_option( 'wptg_free_cn_schema_version', 0 );
                            ?>
                            <?php if ( ! empty( $old_data ) ) : ?>
                                <p><strong>状态：</strong>检测到旧版配置数据（包含旧版机器人配置、规则及目标）。已导入版本：<code><?php echo esc_html( $schema_ver ); ?></code>。</p>
                                <button type="button" id="wptg-import-config" class="button button-primary">导入 / 重新同步旧版配置</button>
                                <div id="wptg-import-result" class="wptg-test-result"></div>
                                <p class="wptg-help">一键将旧版 WP Telegram (4.2.15) 的所有配置与待发定时任务安全迁移至新版。原版数据绝不会被删除或修改。</p>
                            <?php else : ?>
                                <p class="wptg-help">当前数据库中未检测到原版 WP Telegram 的配置记录，无需执行迁移。</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

            <?php elseif ( 'p2tg' === $tab ) : ?>
                <!-- 模板帮助说明折叠区 -->
                <div class="wptg-template-help" style="margin-top: 25px;">
                    <h4>📖 模板变量与格式配置帮助</h4>
                    <p>在“文章消息模板”和“阅读按钮文字/地址”中，可以使用大括号宏动态输出文章信息：</p>
                    
                    <table class="widefat striped" style="margin-bottom: 15px;">
                        <thead>
                            <tr>
                                <th style="width: 200px;">变量标签</th>
                                <th>说明与示例</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>{post_title}</code> 或 <code>{title}</code></td>
                                <td>文章标题</td>
                            </tr>
                            <tr>
                                <td><code>{post_excerpt}</code> 或 <code>{excerpt}</code></td>
                                <td>文章摘要（根据上方配置截取指定字数）</td>
                            </tr>
                            <tr>
                                <td><code>{post_content}</code> 或 <code>{content}</code></td>
                                <td>文章完整正文内容</td>
                            </tr>
                            <tr>
                                <td><code>{full_url}</code></td>
                                <td>文章完整永久链接 (Permalink)</td>
                            </tr>
                            <tr>
                                <td><code>{short_url}</code></td>
                                <td>WordPress 生成的文章短链接</td>
                            </tr>
                            <tr>
                                <td><code>{post_author}</code> 或 <code>{author}</code></td>
                                <td>文章作者的公开显示名称 (Display Name)</td>
                            </tr>
                            <tr>
                                <td><code>{post_date}</code> / <code>{post_time}</code></td>
                                <td>本地发布日期与时间</td>
                            </tr>
                            <tr>
                                <td><code>{post_id}</code> 或 <code>{id}</code></td>
                                <td>文章在 WordPress 中的数字 ID</td>
                            </tr>
                            <tr>
                                <td><code>{categories}</code> / <code>{tags}</code></td>
                                <td>文章分类与标签（若开启“分类显示为话题标签”，则自动转为 #话题 格式）</td>
                            </tr>
                            <tr>
                                <td><code>{terms:taxonomy_slug}</code></td>
                                <td>自定义分类法的项目，如 <code>{terms:genre}</code></td>
                            </tr>
                            <tr>
                                <td><code>{cf:custom_field_key}</code></td>
                                <td>自定义字段 (Post Meta)，例如 <code>{cf:price}</code>、<code>{cf:source_url}</code></td>
                            </tr>
                        </tbody>
                    </table>

                    <h4>条件渲染语法 [if]</h4>
                    <p>当某个变量非空时才输出相关内容，语法格式：<br>
                    <code>[if {macro}][如果非空输出此内容][如果为空输出此可选内容]</code></p>
                    <p>示例：<code>[if {tags}][标签：{tags}][标签：无]</code></p>
                </div>

            <?php elseif ( 'advanced' === $tab ) : ?>
                <!-- 日志快速查看链接 -->
                <?php
                $active_logs = (array) wptg_free_cn_get_option( 'advanced.enable_logs', array() );
                ?>
                <?php if ( ! empty( $active_logs ) ) : ?>
                    <hr style="margin: 30px 0 20px;">
                    <h3>📋 调试日志文件</h3>
                    <p>当前已启用的日志类型：</p>
                    <ul>
                        <?php if ( in_array( 'bot_api', $active_logs, true ) ) : ?>
                            <li><strong>接口日志 (bot_api)：</strong><a href="<?php echo esc_url( wptg_free_cn_log_get_url( 'bot_api' ) ); ?>" target="_blank" class="button button-small">查看/下载接口日志</a></li>
                        <?php endif; ?>
                        <?php if ( in_array( 'p2tg', $active_logs, true ) ) : ?>
                            <li><strong>推送日志 (p2tg)：</strong><a href="<?php echo esc_url( wptg_free_cn_log_get_url( 'p2tg' ) ); ?>" target="_blank" class="button button-small">查看/下载推送日志</a></li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
    <?php
}
