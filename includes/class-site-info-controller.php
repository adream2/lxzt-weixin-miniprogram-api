<?php
/**
 * 网站信息控制器类文件
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP Mini Program 网站信息控制器类
 */
class WP_Mini_Program_Site_Info_Controller {
    
    /**
     * 注册路由
     */
    public function register_routes() {
        $namespace = WP_Mini_Program_API_Main::get_namespace();
        
        register_rest_route($namespace, '/site-info', array(
            array(
                'methods'  => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_site_info'),
            )
        ));
    }
    
    /**
     * 获取网站信息
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_site_info($request) {
        // 检查是否启用缓存
        $cache_enabled = get_option('wp_miniprogram_cache_enabled', 1);
        $cache_duration = get_option('wp_miniprogram_cache_duration', 300);
        
        if ($cache_enabled) {
            // 生成缓存键
            $cache_key = 'wp_miniprogram_site_info';
            
            // 尝试从缓存获取数据
            $cached_data = get_transient($cache_key);
            if ($cached_data !== false) {
                $response = new WP_REST_Response($cached_data);
                $response->header('X-WP-Cache', 'HIT');
                return $response;
            }
        }
        
        // 获取网站信息时添加错误处理
        $site_name = get_bloginfo('name');
        $site_description = get_bloginfo('description');
        $site_url = get_bloginfo('url');
        $admin_email = get_bloginfo('admin_email');
        
        $site_info = array(
            'name' => $site_name ?: 'Unknown Site',
            'description' => $site_description ?: '',
            'url' => $site_url ?: '',
            'admin_email' => $admin_email ?: '',
            'cache_enabled' => $cache_enabled,
            'cache_duration' => $cache_duration,
        );
        
        // 如果启用缓存，则保存到缓存中
        if ($cache_enabled) {
            set_transient($cache_key, $site_info, $cache_duration);
            $response = new WP_REST_Response($site_info);
            $response->header('X-WP-Cache', 'MISS');
            return $response;
        }
        
        return new WP_REST_Response($site_info);
    }
}