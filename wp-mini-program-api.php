<?php
/**
 * Plugin Name: WP Mini Program API
 * Description: 为微信小程序提供 WordPress REST API 扩展
 * Version: 1.0.0
 * Author: 理想状态
 * Text Domain: lxzt.fun
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 定义插件常量
define('WP_MINI_PROGRAM_VERSION', '1.0.0');
define('WP_MINI_PROGRAM_PATH', plugin_dir_path(__FILE__));
define('WP_MINI_PROGRAM_URL', plugin_dir_url(__FILE__));

/**
 * WP Mini Program API 插件主类
 */
class WP_Mini_Program_API {

    /**
     * 插件实例
     *
     * @var WP_Mini_Program_API
     */
    private static $instance = null;

    /**
     * 获取插件实例
     *
     * @return WP_Mini_Program_API
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 构造函数
     */
    private function __construct() {
        $this->init();
    }

    /**
     * 初始化插件
     */
    private function init() {
        // 加载插件文本域
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // 插件激活钩子
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // 插件停用钩子
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // 初始化设置
        add_action('init', array($this, 'init_hooks'));
    }

    /**
     * 加载插件文本域
     */
    public function load_textdomain() {
        load_plugin_textdomain('wp-mini-program', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * 插件激活回调
     */
    public function activate() {
        // 设置默认选项
        add_option('wp_miniprogram_allow_comments', 1);
        add_option('wp_miniprogram_comment_interval', 5);
        add_option('wp_miniprogram_posts_per_page', 10);
        add_option('wp_miniprogram_cache_enabled', 1);
        add_option('wp_miniprogram_cache_duration', 300);
    }

    /**
     * 插件停用回调
     */
    public function deactivate() {
        // 清除所有缓存
        if (class_exists('WP_Mini_Program_Cache_Manager')) {
            WP_Mini_Program_Cache_Manager::clear_all_cache();
        }
    }

    /**
     * 初始化钩子
     */
    public function init_hooks() {
        // 加载必要的类文件
        $this->load_dependencies();
        
        // 初始化缓存管理器
        WP_Mini_Program_Cache_Manager::init();
        
        // 注册 REST API 端点
        add_action('rest_api_init', array($this, 'register_routes'));
        
        // 添加设置菜单
        add_action('admin_menu', array('WP_Mini_Program_Settings', 'add_menu_page'));
        add_action('admin_init', array('WP_Mini_Program_Settings', 'init_settings'));
    }

    /**
     * 加载依赖文件
     */
    private function load_dependencies() {
        require_once WP_MINI_PROGRAM_PATH . 'includes/class-settings.php';
        require_once WP_MINI_PROGRAM_PATH . 'includes/class-endpoints.php';
        require_once WP_MINI_PROGRAM_PATH . 'includes/class-api.php';
        require_once WP_MINI_PROGRAM_PATH . 'includes/class-posts-controller.php';
        require_once WP_MINI_PROGRAM_PATH . 'includes/class-categories-controller.php';
        require_once WP_MINI_PROGRAM_PATH . 'includes/class-comments-controller.php';
        require_once WP_MINI_PROGRAM_PATH . 'includes/class-pages-controller.php';
        require_once WP_MINI_PROGRAM_PATH . 'includes/class-search-controller.php';
        require_once WP_MINI_PROGRAM_PATH . 'includes/class-likes-controller.php';
        require_once WP_MINI_PROGRAM_PATH . 'includes/class-favorites-controller.php';
        require_once WP_MINI_PROGRAM_PATH . 'includes/class-cache-manager.php';
        require_once WP_MINI_PROGRAM_PATH . 'includes/class-site-info-controller.php';
    }

    /**
     * 注册 REST API 路由
     */
    public function register_routes() {
        $controllers = array(
            'WP_Mini_Program_Posts_Controller',
            'WP_Mini_Program_Categories_Controller',
            'WP_Mini_Program_Comments_Controller',
            'WP_Mini_Program_Pages_Controller',
            'WP_Mini_Program_Search_Controller',
            'WP_Mini_Program_Likes_Controller',
            'WP_Mini_Program_Favorites_Controller',
            'WP_Mini_Program_Site_Info_Controller',
        );

        foreach ($controllers as $controller) {
            if (class_exists($controller)) {
                $instance = new $controller();
                $instance->register_routes();
            }
        }
    }
    
    /**
     * 获取API基础URL
     */
    public static function get_api_base_url() {
        return rest_url(WP_Mini_Program_API_Main::get_namespace());
    }
}

// 启动插件
WP_Mini_Program_API::get_instance();