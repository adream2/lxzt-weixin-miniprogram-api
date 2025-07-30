<?php
/**
 * 页面控制器类文件
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP Mini Program 页面控制器类
 */
class WP_Mini_Program_Pages_Controller {
    
    /**
     * 注册路由
     */
    public function register_routes() {
        $namespace = WP_Mini_Program_API_Main::get_namespace();
        
        register_rest_route($namespace, '/pages', array(
            array(
                'methods'  => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_items'),
            )
        ));
        
        register_rest_route($namespace, '/pages/(?P<id>\d+)', array(
            array(
                'methods'  => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_item'),
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
     * 获取页面列表
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
            $cache_key = 'wp_miniprogram_pages';
            
            // 尝试从缓存获取数据
            $cached_data = get_transient($cache_key);
            if ($cached_data !== false) {
                $response = new WP_REST_Response($cached_data);
                $response->header('X-WP-Cache', 'HIT');
                return $response;
            }
        }
        
        $args = array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        );
        
        // 保存全局变量
        global $post;
        $original_post = $post;
        
        $query = new WP_Query($args);
        $pages = array();
        
        // 错误处理
        if (is_wp_error($query)) {
            // 恢复全局变量
            $post = $original_post;
            error_log('WP Mini Program: WP_Query error in pages - ' . $query->get_error_message());
            return new WP_Error('query_failed', __('Query failed', 'wp-mini-program'), array('status' => 500));
        }
        
        // 直接处理查询结果，避免使用the_post()方法
        foreach ($query->posts as $post) {
            // 确保$post对象有效
            if ($post && $post->post_status === 'publish') {
                $pages[] = $this->prepare_item_for_response($post, $request);
            }
        }
        
        // 恢复全局变量
        $post = $original_post;
        
        // 如果启用缓存，则保存到缓存中
        if ($cache_enabled) {
            set_transient($cache_key, $pages, $cache_duration);
            $response = new WP_REST_Response($pages);
            $response->header('X-WP-Cache', 'MISS');
            return $response;
        }
        
        return new WP_REST_Response($pages);
    }
    
    /**
     * 获取单个页面
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_item($request) {
        // 检查是否启用缓存
        $cache_enabled = get_option('wp_miniprogram_cache_enabled', 1);
        $cache_duration = get_option('wp_miniprogram_cache_duration', 300);
        
        if ($cache_enabled) {
            // 生成缓存键
            $cache_key = 'wp_miniprogram_page_' . $request->get_param('id');
            
            // 尝试从缓存获取数据
            $cached_data = get_transient($cache_key);
            if ($cached_data !== false) {
                $response = new WP_REST_Response($cached_data);
                $response->header('X-WP-Cache', 'HIT');
                return $response;
            }
        }
        
        // 保存全局变量
        global $post;
        $original_post = $post;
        
        $id = $request->get_param('id');
        $post = get_post($id);
        
        if (!$post || $post->post_type !== 'page' || $post->post_status !== 'publish') {
            // 恢复全局变量
            $post = $original_post;
            return new WP_Error('page_not_found', __('Page not found', 'wp-mini-program'), array('status' => 404));
        }
        
        $data = $this->prepare_item_for_response($post, $request);
        
        // 恢复全局变量
        $post = $original_post;
        
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
     * 准备页面数据
     *
     * @param WP_Post $post
     * @param WP_REST_Request $request
     * @return array
     */
    public function prepare_item_for_response($post, $request) {
        $data = array(
            'id'             => $post->ID,
            'date'           => $post->post_date,
            'date_gmt'       => $post->post_date_gmt,
            'modified'       => $post->post_modified,
            'modified_gmt'   => $post->post_modified_gmt,
            'slug'           => $post->post_name,
            'status'         => $post->post_status,
            'type'           => $post->post_type,
            'link'           => get_permalink($post->ID),
            'title'          => array(
                'rendered' => !empty($post->post_title) ? $post->post_title : '',
            ),
            'content'        => array(
                'rendered' => apply_filters('the_content', $post->post_content),
            ),
            'excerpt'        => array(
                'rendered' => apply_filters('the_excerpt', $post->post_excerpt),
            ),
            'author'         => (int) $post->post_author,
            'comment_status' => $post->comment_status,
            'ping_status'    => $post->ping_status,
            'template'       => get_page_template_slug($post->ID),
            'meta'           => array(
                'links' => get_post_meta($post->ID, 'links', true),
            ),
        );
        
        return apply_filters('wp_miniprogram_page_data', $data, $post, $request);
    }
}