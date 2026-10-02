/**
 * Telegram 中文免费版 - Gutenberg 编辑器侧栏
 *
 * 在古腾堡编辑器中显示 Telegram 发送面板。
 * 使用 wp.element.createElement（无需 JSX 编译）。
 *
 * @package WPTelegramFreeCN
 */

(function(wp) {
    'use strict';

    if (!wp || !wp.plugins || !wp.editPost || !wp.element || !wp.components || !wp.data) {
        return;
    }

    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
    var CheckboxControl = wp.components.CheckboxControl;
    var TextareaControl = wp.components.TextareaControl;
    var TextControl = wp.components.TextControl;
    var registerPlugin = wp.plugins.registerPlugin;
    var useSelect = wp.data.useSelect;
    var useDispatch = wp.data.useDispatch;

    // 插件数据 key 前缀
    var PREFIX = '_wptg_free_cn_p2tg_';

    // 模块级单例状态容器，供 apiFetch 中间件实时读取最新界面状态
    var currentState = {
        send2tg: false,
        overrideSwitch: false,
        channels: undefined,
        message_template: undefined,
        delay: undefined,
        disable_notification: undefined,
        send_featured_image: undefined,
        postId: null,
        restBase: 'posts',
        postStatus: ''
    };

    var lastEditorMetaMarker = '';

    var apiFetchRegistered = false;
    function registerApiFetchMiddleware() {
        if (apiFetchRegistered || !wp.apiFetch || !wp.apiFetch.use) {
            return;
        }
        apiFetchRegistered = true;
        wp.apiFetch.use(function(options, next) {
            if (options && (options.method === 'PUT' || options.method === 'POST')) {
                var path = options.path || '';
                var base = currentState.restBase || 'posts';
                var id = currentState.postId;
                var exactPost = id ? new RegExp('^\\/wp\\/v2\\/' + base + '\\/' + String(id) + '\\/?$') : null;
                var newPost = ( !id || currentState.postStatus === 'auto-draft' ) && new RegExp('^\\/wp\\/v2\\/' + base + '\\/?$');
                var fallbackPost = !id && new RegExp('^\\/wp\\/v2\\/' + base + '\\/\\d+\\/?$');
                // 只处理当前文章，避免覆盖其他文章、媒体和评论请求。
                var isPostSave = ( exactPost && exactPost.test(path) ) || ( newPost && newPost.test(path) ) || ( fallbackPost && fallbackPost.test(path) );
                if (isPostSave) {
                    if (!options.data) {
                        options.data = {};
                    }
                    options.data[PREFIX] = {
                        send2tg: currentState.send2tg,
                        override_switch: currentState.overrideSwitch,
                        channels: currentState.channels,
                        message_template: currentState.message_template,
                        delay: currentState.delay,
                        disable_notification: currentState.disable_notification,
                        send_featured_image: currentState.send_featured_image
                    };
                }
            }
            return next(options);
        });
    }

    // 尽早注册中间件（仅注册一次，避免每次 render 重复叠加）
    registerApiFetchMiddleware();

    /**
     * Telegram 发送面板组件
     */
    function TelegramSendPanel() {
        registerApiFetchMiddleware();

        var defaults = window.wptgFreeCNEditor || {};

        var editorData = useSelect(function(select) {
            var editor = select('core/editor');
            return {
                meta: editor.getEditedPostAttribute('meta') || {},
                id: typeof editor.getCurrentPostId === 'function' ? editor.getCurrentPostId() : null,
                type: typeof editor.getCurrentPostType === 'function' ? editor.getCurrentPostType() : '',
                status: editor.getEditedPostAttribute('status') || ''
            };
        }, []);
        var postMeta = editorData.meta || {};
        var currentPostId = editorData.id || null;

        var savedSend2tg = postMeta[PREFIX + 'send2tg'] || '';
        var defaultSend = defaults.defaultSend2tg || 'yes';

        var savedOptions = postMeta[PREFIX + 'options'] || {};
        if (typeof savedOptions === 'string') {
            try {
                savedOptions = JSON.parse(savedOptions);
            } catch (e) {
                savedOptions = {};
            }
        }

        var _s1 = useState(savedSend2tg || defaultSend);
        var send2tg = _s1[0];
        var setSend2tg = _s1[1];

        var _s2 = useState(Boolean(savedOptions.override_switch));
        var overrideSwitch = _s2[0];
        var setOverrideSwitch = _s2[1];

        var _s3 = useState(Array.isArray(savedOptions.channels) ? savedOptions.channels.join('\n') : (savedOptions.channels || ''));
        var overrideChannels = _s3[0];
        var setOverrideChannels = _s3[1];

        var _s4 = useState(savedOptions.message_template || '');
        var overrideTemplate = _s4[0];
        var setOverrideTemplate = _s4[1];

        var _s5 = useState(savedOptions.delay !== undefined ? String(savedOptions.delay) : '');
        var overrideDelay = _s5[0];
        var setOverrideDelay = _s5[1];

        var _s6 = useState(Boolean(savedOptions.disable_notification));
        var overrideDisableNotification = _s6[0];
        var setOverrideDisableNotification = _s6[1];

        var _s7 = useState(savedOptions.send_featured_image !== undefined ? Boolean(savedOptions.send_featured_image) : true);
        var overrideSendImage = _s7[0];
        var setOverrideSendImage = _s7[1];

        // REST meta 可能在组件首次渲染后才返回；文章切换时同步一次已保存值。
        var metaMarker = String(currentPostId || '') + '|' + String(savedSend2tg || '') + '|' + JSON.stringify(savedOptions);
        useEffect(function() {
            if ( metaMarker === lastEditorMetaMarker ) {
                return;
            }
            lastEditorMetaMarker = metaMarker;
            if ( savedSend2tg ) setSend2tg(savedSend2tg);
            if ( savedOptions && Object.keys(savedOptions).length ) {
                setOverrideSwitch(Boolean(savedOptions.override_switch));
                setOverrideChannels(Array.isArray(savedOptions.channels) ? savedOptions.channels.join('\n') : (savedOptions.channels || ''));
                setOverrideTemplate(savedOptions.message_template || '');
                setOverrideDelay(savedOptions.delay !== undefined ? String(savedOptions.delay) : '');
                setOverrideDisableNotification(Boolean(savedOptions.disable_notification));
                if ( savedOptions.send_featured_image !== undefined ) setOverrideSendImage(Boolean(savedOptions.send_featured_image));
            }
        }, [metaMarker]);

        // 实时同步最新状态到中间件容器
        currentState.send2tg = (send2tg === 'yes');
        currentState.overrideSwitch = overrideSwitch;
        currentState.channels = overrideSwitch ? overrideChannels.split('\n').filter(Boolean) : undefined;
        currentState.message_template = overrideSwitch ? overrideTemplate : undefined;
        currentState.delay = overrideSwitch && overrideDelay !== '' ? parseFloat(overrideDelay) : undefined;
        currentState.disable_notification = overrideSwitch ? overrideDisableNotification : undefined;
        currentState.send_featured_image = overrideSwitch ? overrideSendImage : undefined;
        currentState.postId = currentPostId;
        currentState.restBase = defaults.restBase || (editorData.type === 'post' ? 'posts' : (editorData.type === 'page' ? 'pages' : (editorData.type || 'posts')));
        currentState.postStatus = editorData.status;

        // 在保存时注入数据到 meta
        var editPost = useDispatch('core/editor').editPost;

        useEffect(function() {
            var meta = {};
            meta[PREFIX + 'send2tg'] = send2tg;
            editPost({ meta: meta });
        }, [send2tg]);

        return el(PluginDocumentSettingPanel, {
            name: 'wptg-free-cn-panel',
            title: 'Telegram 推送',
            icon: 'share'
        },
            el(CheckboxControl, {
                label: '本次发送到 Telegram',
                help: send2tg === 'yes' ? '发布时将推送到 Telegram' : '本次不推送到 Telegram',
                checked: send2tg === 'yes',
                onChange: function(checked) {
                    setSend2tg(checked ? 'yes' : 'no');
                }
            }),

            el(CheckboxControl, {
                label: '使用独立配置（覆盖全局设置）',
                help: overrideSwitch ? '当前使用本篇文章的独立推送配置' : '当前使用全局推送配置',
                checked: overrideSwitch,
                onChange: function(checked) {
                    setOverrideSwitch(checked);
                }
            }),

            overrideSwitch ? el(Fragment, null,
                el(TextareaControl, {
                    label: '接收目标（每行一个）',
                    help: '覆盖全局目标。格式：@频道名 或 聊天ID:话题ID | 备注',
                    value: overrideChannels,
                    onChange: setOverrideChannels,
                    rows: 3
                }),
                el(TextareaControl, {
                    label: '消息模板',
                    help: '覆盖全局模板。留空使用全局模板。',
                    value: overrideTemplate,
                    onChange: setOverrideTemplate,
                    rows: 4
                }),
                el(TextControl, {
                    label: '延迟发送（分钟）',
                    help: '覆盖全局延迟时间。0 表示立即发送。',
                    type: 'number',
                    value: overrideDelay,
                    onChange: setOverrideDelay
                }),
                el(CheckboxControl, {
                    label: '静默发送',
                    checked: overrideDisableNotification,
                    onChange: setOverrideDisableNotification
                }),
                el(CheckboxControl, {
                    label: '发送特色图片',
                    checked: overrideSendImage,
                    onChange: setOverrideSendImage
                })
            ) : null
        );
    }

    // 注册插件
    registerPlugin('wptg-free-cn', {
        render: TelegramSendPanel,
        icon: 'share'
    });

})(window.wp);
