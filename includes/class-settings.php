<?php
/**
 * 设置类文件
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP Mini Program 设置类
 */
class WP_Mini_Program_Settings {
    
    /**
     * 添加菜单页面
     */
    public static function add_menu_page() {
        add_options_page(
            __('微信小程序设置', 'wp-mini-program'),
            __('微信小程序', 'wp-mini-program'),
            'manage_options',
            'wp-mini-program-settings',
            array(__CLASS__, 'settings_page')
        );
    }
    
    /**
     * 初始化设置
     */
    public static function init_settings() {
        register_setting(
            'wp_miniprogram_settings_group',
            'wp_miniprogram_allow_comments'
        );
        
        register_setting(
            'wp_miniprogram_settings_group',
            'wp_miniprogram_default_thumbnail'
        );
        
        register_setting(
            'wp_miniprogram_settings_group',
            'wp_miniprogram_comment_interval'
        );
        
        register_setting(
            'wp_miniprogram_settings_group',
            'wp_miniprogram_cache_enabled'
        );
        
        register_setting(
            'wp_miniprogram_settings_group',
            'wp_miniprogram_cache_duration'
        );
        
        // 添加设置区域
        add_settings_section(
            'wp_miniprogram_settings_section',
            __('微信小程序 API 设置', 'wp-mini-program'),
            array(__CLASS__, 'settings_section_callback'),
            'wp-mini-program-settings'
        );
        
        // 添加API接口信息区域
        add_settings_section(
            'wp_miniprogram_api_info_section',
            __('API 接口信息', 'wp-mini-program'),
            array(__CLASS__, 'api_info_section_callback'),
            'wp-mini-program-settings'
        );
        
        // 添加允许评论设置字段
        add_settings_field(
            'wp_miniprogram_allow_comments_field',
            __('允许小程序端评论', 'wp-mini-program'),
            array(__CLASS__, 'allow_comments_callback'),
            'wp-mini-program-settings',
            'wp_miniprogram_settings_section'
        );
        
        // 添加默认缩略图设置字段
        add_settings_field(
            'wp_miniprogram_default_thumbnail_field',
            __('默认缩略图 URL', 'wp-mini-program'),
            array(__CLASS__, 'default_thumbnail_callback'),
            'wp-mini-program-settings',
            'wp_miniprogram_settings_section'
        );
        
        // 添加评论间隔时间设置字段
        add_settings_field(
            'wp_miniprogram_comment_interval_field',
            __('评论间隔时间（分钟）', 'wp-mini-program'),
            array(__CLASS__, 'comment_interval_callback'),
            'wp-mini-program-settings',
            'wp_miniprogram_settings_section'
        );
        
        // 添加启用缓存设置字段
        add_settings_field(
            'wp_miniprogram_cache_enabled_field',
            __('启用API缓存', 'wp-mini-program'),
            array(__CLASS__, 'cache_enabled_callback'),
            'wp-mini-program-settings',
            'wp_miniprogram_settings_section'
        );
        
        // 添加缓存时长设置字段
        add_settings_field(
            'wp_miniprogram_cache_duration_field',
            __('缓存时长（秒）', 'wp-mini-program'),
            array(__CLASS__, 'cache_duration_callback'),
            'wp-mini-program-settings',
            'wp_miniprogram_settings_section'
        );
    }
    
    /**
     * 设置区域回调
     */
    public static function settings_section_callback() {
        echo '<p>' . __('配置微信小程序 API 的相关设置。', 'wp-mini-program') . '</p>';
    }
    
    /**
     * API信息区域回调
     */
    public static function api_info_section_callback() {
        $api_base_url = WP_Mini_Program_API::get_api_base_url();
        echo '<p>' . __('以下是为微信小程序提供的 REST API 接口信息，可用于小程序开发和调试。', 'wp-mini-program') . '</p>';
        
        echo '<h3>' . __('API 接口列表', 'wp-mini-program') . '</h3>';
        echo '<table class="widefat">';
        echo '<thead><tr><th>' . __('接口路径', 'wp-mini-program') . '</th><th>' . __('请求方法', 'wp-mini-program') . '</th><th>' . __('功能说明', 'wp-mini-program') . '</th></tr></thead>';
        echo '<tbody>';
        echo '<tr><td><code>' . esc_html($api_base_url) . '/posts</code></td><td>GET</td><td>' . __('获取文章列表', 'wp-mini-program') . '</td></tr>';
        echo '<tr><td><code>' . esc_html($api_base_url) . '/posts/&lt;id&gt;</code></td><td>GET</td><td>' . __('获取文章详情', 'wp-mini-program') . '</td></tr>';
        echo '<tr><td><code>' . esc_html($api_base_url) . '/categories</code></td><td>GET</td><td>' . __('获取分类列表', 'wp-mini-program') . '</td></tr>';
        echo '<tr><td><code>' . esc_html($api_base_url) . '/comments</code></td><td>GET/POST</td><td>' . __('获取/提交评论', 'wp-mini-program') . '</td></tr>';
        echo '<tr><td><code>' . esc_html($api_base_url) . '/pages/&lt;id&gt;</code></td><td>GET</td><td>' . __('获取页面详情', 'wp-mini-program') . '</td></tr>';
        echo '<tr><td><code>' . esc_html($api_base_url) . '/search</code></td><td>GET</td><td>' . __('搜索文章', 'wp-mini-program') . '</td></tr>';
        echo '<tr><td><code>' . esc_html($api_base_url) . '/likes/&lt;id&gt;</code></td><td>POST</td><td>' . __('点赞文章', 'wp-mini-program') . '</td></tr>';
        echo '<tr><td><code>' . esc_html($api_base_url) . '/favorites/&lt;id&gt;</code></td><td>POST</td><td>' . __('收藏文章', 'wp-mini-program') . '</td></tr>';
        echo '<tr><td><code>' . esc_html($api_base_url) . '/site-info</code></td><td>GET</td><td>' . __('获取网站信息', 'wp-mini-program') . '</td></tr>';
        echo '</tbody></table>';
        
        echo '<h3>' . __('调试说明', 'wp-mini-program') . '</h3>';
        echo '<ol>';
        echo '<li>' . __('可以直接在浏览器中访问以上API接口进行测试', 'wp-mini-program') . '</li>';
        echo '<li>' . __('建议使用 Postman 或其他API测试工具进行调试', 'wp-mini-program') . '</li>';
        echo '<li>' . __('启用缓存后，接口响应头会包含 X-WP-Cache 标识（HIT表示缓存命中，MISS表示缓存未命中）', 'wp-mini-program') . '</li>';
        echo '<li>' . __('如需测试提交评论、点赞、收藏等功能，请使用 POST 方法并提供相应参数', 'wp-mini-program') . '</li>';
        echo '</ol>';
        
        echo '<h3>' . __('示例请求', 'wp-mini-program') . '</h3>';
        echo '<p><strong>' . __('获取文章列表:', 'wp-mini-program') . '</strong></p>';
        echo '<pre>GET ' . esc_html($api_base_url) . '/posts?page=1&per_page=10</pre>';
        echo '<p><strong>' . __('获取文章详情:', 'wp-mini-program') . '</strong></p>';
        echo '<pre>GET ' . esc_html($api_base_url) . '/posts/1</pre>';
        echo '<p><strong>' . __('提交评论:', 'wp-mini-program') . '</strong></p>';
        echo '<pre>POST ' . esc_html($api_base_url) . '/comments
Content-Type: application/json

{
  "post": 1,
  "content": "评论内容",
  "author_name": "评论者姓名"
}</pre>';
    }
    
    /**
     * 评论间隔时间设置字段回调
     */
    public static function comment_interval_callback() {
        $setting = get_option('wp_miniprogram_comment_interval', 5);
        ?>
        <label>
            <input type="number" name="wp_miniprogram_comment_interval" value="<?php echo esc_attr($setting); ?>" class="small-text" min="1">
            <?php _e('分钟', 'wp-mini-program'); ?>
            <p class="description"><?php _e('同一用户发表评论的最小间隔时间，默认为5分钟', 'wp-mini-program'); ?></p>
        </label>
        <?php
    }
    
    /**
     * 默认缩略图设置字段回调
     */
    public static function default_thumbnail_callback() {
        $setting = get_option('wp_miniprogram_default_thumbnail', '');
        ?>
        <label>
            <input type="url" name="wp_miniprogram_default_thumbnail" value="<?php echo esc_attr($setting); ?>" class="regular-text">
            <p class="description"><?php _e('设置默认缩略图的URL地址，留空则使用插件内置默认图片', 'wp-mini-program'); ?></p>
        </label>
        <?php
    }
    
    /**
     * 允许评论设置字段回调
     */
    public static function allow_comments_callback() {
        $setting = get_option('wp_miniprogram_allow_comments', 1);
        ?>
        <label>
            <input type="checkbox" name="wp_miniprogram_allow_comments" value="1" <?php checked(1, $setting); ?>>
            <?php _e('允许用户在小程序端发表评论', 'wp-mini-program'); ?>
        </label>
        <?php
    }
    
    /**
     * 启用缓存设置字段回调
     */
    public static function cache_enabled_callback() {
        $setting = get_option('wp_miniprogram_cache_enabled', 1);
        ?>
        <label>
            <input type="checkbox" name="wp_miniprogram_cache_enabled" value="1" <?php checked(1, $setting); ?>>
            <?php _e('启用API响应缓存功能', 'wp-mini-program'); ?>
            <p class="description"><?php _e('启用后可以减轻服务器压力，提高响应速度', 'wp-mini-program'); ?></p>
        </label>
        <?php
    }
    
    /**
     * 缓存时长设置字段回调
     */
    public static function cache_duration_callback() {
        $setting = get_option('wp_miniprogram_cache_duration', 300);
        ?>
        <label>
            <input type="number" name="wp_miniprogram_cache_duration" value="<?php echo esc_attr($setting); ?>" class="small-text" min="1">
            <?php _e('秒', 'wp-mini-program'); ?>
            <p class="description"><?php _e('API响应缓存的有效时长，默认为300秒（5分钟）', 'wp-mini-program'); ?></p>
        </label>
        <?php
    }
    
    /**
     * 设置页面
     */
    public static function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('微信小程序设置', 'wp-mini-program'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wp_miniprogram_settings_group');
                do_settings_sections('wp-mini-program-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}