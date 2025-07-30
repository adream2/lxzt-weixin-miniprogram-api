<?php
/**
 * 分类控制器类文件
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP Mini Program 分类控制器类
 */
class WP_Mini_Program_Categories_Controller {
    
    /**
     * 注册路由
     */
    public function register_routes() {
        $namespace = WP_Mini_Program_API_Main::get_namespace();
        
        register_rest_route($namespace, '/categories', array(
            array(
                'methods'  => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_items'),
            )
        ));
        
        register_rest_route($namespace, '/categories/(?P<id>\d+)/posts', array(
            array(
                'methods'  => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_category_posts'),
                'args'     => array(
                    'id' => array(
                        'required' => true,
                        'validate_callback' => function($param, $request, $key) {
                            return is_numeric($param);
                        }
                    )
                )
            )
        ));
    }
    
    /**
     * 获取分类列表
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_items($request) {
        // 检查是否启用缓存
        $cache_enabled = get_option('wp_miniprogram_cache_enabled', 1);
        $cache_duration = get_option('wp_miniprogram_cache_duration', 300);
        
        if ($cache_enabled) {
            // 生成缓存键
            $cache_key = 'wp_miniprogram_categories';
            
            // 尝试从缓存获取数据
            $cached_data = get_transient($cache_key);
            if ($cached_data !== false) {
                $response = new WP_REST_Response($cached_data);
                $response->header('X-WP-Cache', 'HIT');
                return $response;
            }
        }
        
        $categories = get_categories(array(
            'hide_empty' => false,
        ));
        
        // 错误处理
        if (is_wp_error($categories)) {
            error_log('WP Mini Program: get_categories error - ' . $categories->get_error_message());
            return new WP_Error('categories_failed', __('Failed to retrieve categories', 'wp-mini-program'), array('status' => 500));
        }
        
        $data = array();
        
        foreach ($categories as $category) {
            $data[] = array(
                'id'          => $category->term_id,
                'name'        => $category->name,
                'slug'        => $category->slug,
                'description' => $category->description,
                'parent'      => $category->parent,
                'count'       => $category->count,
            );
        }
        
        // 如果启用缓存，则保存到缓存中
        if ($cache_enabled) {
            set_transient($cache_key, $data, $cache_duration);
            $response = new WP_REST_Response($data);
            $response->header('X-WP-Cache', 'MISS');
            return $response;
        }
        
        return new WP_REST_Response($data);
    }
    
    /**
     * 获取分类下的文章
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_category_posts($request) {
        // 检查是否启用缓存
        $cache_enabled = get_option('wp_miniprogram_cache_enabled', 1);
        $cache_duration = get_option('wp_miniprogram_cache_duration', 300);
        
        if ($cache_enabled) {
            // 生成缓存键
            $cache_key = 'wp_miniprogram_category_posts_' . $request->get_param('id');
            
            // 尝试从缓存获取数据
            $cached_data = get_transient($cache_key);
            if ($cached_data !== false) {
                $response = new WP_REST_Response($cached_data);
                $response->header('X-WP-Cache', 'HIT');
                return $response;
            }
        }
        
        $category_id = $request->get_param('id');
        $category = get_category($category_id);
        
        if (!$category || is_wp_error($category)) {
            return new WP_Error('category_not_found', __('Category not found', 'wp-mini-program'), array('status' => 404));
        }
        
        $args = array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $request->get_param('per_page') ?: get_option('wp_miniprogram_posts_per_page', 10),
            'paged'          => $request->get_param('page') ?: 1,
            'cat'            => $category_id,
        );
        
        // 保存全局变量
        global $post;
        $original_post = $post;
        
        $query = new WP_Query($args);
        $posts = array();
        
        // 错误处理
        if (is_wp_error($query)) {
            // 恢复全局变量
            $post = $original_post;
            error_log('WP Mini Program: WP_Query error in category posts - ' . $query->get_error_message());
            return new WP_Error('query_failed', __('Query failed', 'wp-mini-program'), array('status' => 500));
        }
        
        // 创建文章控制器实例以使用prepare_item_for_response方法
        $posts_controller = new WP_Mini_Program_Posts_Controller();
        
        // 直接处理查询结果，避免使用the_post()方法
        foreach ($query->posts as $post) {
            // 确保$post对象有效且已发布
            if ($post && $post->post_status === 'publish') {
                $posts[] = $posts_controller->prepare_item_for_response($post, $request);
            }
        }
        
        // 恢复全局变量
        $post = $original_post;
        
        // 如果启用缓存，则保存到缓存中
        if ($cache_enabled) {
            set_transient($cache_key, $posts, $cache_duration);
            $response = new WP_REST_Response($posts);
            $response->header('X-WP-Cache', 'MISS');
            return $response;
        }
        
        return new WP_REST_Response($posts);
    }
}