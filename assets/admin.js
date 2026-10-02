/**
 * Telegram 中文免费版 - 后台管理脚本
 *
 * @package WPTelegramFreeCN
 */

(function() {
    'use strict';

    /**
     * 测试机器人连接
     */
    function testBotConnection() {
        var btn = document.getElementById('wptg-test-connection');
        var result = document.getElementById('wptg-test-result');
        var token = document.getElementById('wptg-bot-token');

        if (!btn || !token) return;

        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var botToken = token.value.trim();
            if (!botToken) {
                showResult(result, 'error', '请先填写机器人令牌');
                return;
            }

            btn.disabled = true;
            btn.textContent = '正在测试...';
            result.style.display = 'none';

            var data = new FormData();
            data.append('action', 'wptg_free_cn_test_connection');
            data.append('bot_token', botToken);
            data.append('_wpnonce', wptgFreeCN.nonce);

            fetch(wptgFreeCN.ajaxUrl, {
                method: 'POST',
                body: data,
                credentials: 'same-origin'
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                btn.disabled = false;
                btn.textContent = '测试机器人连接';
                if (data.success) {
                    showResult(result, 'success', '✅ 连接成功！机器人名称：' + data.data.first_name + '（@' + data.data.username + '）');
                    var usernameField = document.getElementById('wptg-bot-username');
                    if (usernameField) {
                        usernameField.value = data.data.username;
                    }
                } else {
                    showResult(result, 'error', '❌ 连接失败：' + (data.data || '未知错误'));
                }
            })
            .catch(function(err) {
                btn.disabled = false;
                btn.textContent = '测试机器人连接';
                showResult(result, 'error', '❌ 请求失败：' + err.message);
            });
        });
    }

    /**
     * 发送测试消息
     */
    function testSendMessage() {
        var btn = document.getElementById('wptg-test-send');
        var result = document.getElementById('wptg-send-result');

        if (!btn) return;

        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var chatId = document.getElementById('wptg-test-chat-id');
            if (!chatId || !chatId.value.trim()) {
                showResult(result, 'error', '请填写测试目标聊天 ID 或频道用户名');
                return;
            }

            btn.disabled = true;
            btn.textContent = '正在发送...';
            result.style.display = 'none';

            var data = new FormData();
            data.append('action', 'wptg_free_cn_test_send');
            data.append('chat_id', chatId.value.trim());
            data.append('_wpnonce', wptgFreeCN.nonce);

            fetch(wptgFreeCN.ajaxUrl, {
                method: 'POST',
                body: data,
                credentials: 'same-origin'
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                btn.disabled = false;
                btn.textContent = '发送测试消息';
                if (data.success) {
                    showResult(result, 'success', '✅ 测试消息已发送到 ' + chatId.value.trim());
                } else {
                    showResult(result, 'error', '❌ 发送失败：' + (data.data || '未知错误'));
                }
            })
            .catch(function(err) {
                btn.disabled = false;
                btn.textContent = '发送测试消息';
                showResult(result, 'error', '❌ 请求失败：' + err.message);
            });
        });
    }

    /**
     * 代理方式切换
     */
    function proxyMethodSwitch() {
        var select = document.getElementById('wptg-proxy-method');
        if (!select) return;

        function updateVisibility() {
            var method = select.value;
            var allFields = document.querySelectorAll('.wptg-proxy-fields');
            allFields.forEach(function(el) {
                el.classList.remove('active');
            });
            var targetFields = document.querySelectorAll('.wptg-proxy-' + method);
            targetFields.forEach(function(el) {
                el.classList.add('active');
            });
        }

        select.addEventListener('change', updateVisibility);
        updateVisibility();
    }

    /**
     * 模板帮助折叠
     */
    function templateHelp() {
        var toggles = document.querySelectorAll('.wptg-template-help-toggle');
        toggles.forEach(function(toggle) {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                var target = document.getElementById(toggle.dataset.target);
                if (target) {
                    target.style.display = target.style.display === 'none' ? 'block' : 'none';
                }
            });
        });
    }

    /**
     * 导入旧配置
     */
    function importConfig() {
        var btn = document.getElementById('wptg-import-config');
        var result = document.getElementById('wptg-import-result');

        if (!btn) return;

        btn.addEventListener('click', function(e) {
            e.preventDefault();
            if (!confirm('确定要导入旧版 WP Telegram 配置吗？\n\n此操作会将旧配置导入新版，不会修改旧版数据。如果新版已有配置，将被覆盖。')) {
                return;
            }

            btn.disabled = true;
            btn.textContent = '正在导入...';

            var data = new FormData();
            data.append('action', 'wptg_free_cn_import_config');
            data.append('_wpnonce', wptgFreeCN.nonce);

            fetch(wptgFreeCN.ajaxUrl, {
                method: 'POST',
                body: data,
                credentials: 'same-origin'
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                btn.disabled = false;
                btn.textContent = '导入旧版配置';
                if (data.success) {
                    showResult(result, 'success', '✅ ' + data.data);
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    showResult(result, 'error', '❌ ' + (data.data || '导入失败'));
                }
            })
            .catch(function(err) {
                btn.disabled = false;
                btn.textContent = '导入旧版配置';
                showResult(result, 'error', '❌ 请求失败：' + err.message);
            });
        });
    }

    /**
     * 显示结果消息
     */
    function showResult(el, type, message) {
        if (!el) return;
        el.className = 'wptg-test-result ' + type;
        el.textContent = message;
        el.style.display = 'block';
    }

    /**
     * 初始化
     */
    document.addEventListener('DOMContentLoaded', function() {
        testBotConnection();
        testSendMessage();
        proxyMethodSwitch();
        templateHelp();
        importConfig();
    });

})();
