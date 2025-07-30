<?php
/**
 * 文章控制器类文件
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP Mini Program 文章控制器类
 */
class WP_Mini_Program_Posts_Controller {
    
    /**
     * 注册路由
     */
    public function register_routes() {
        $namespace = WP_Mini_Program_API_Main::get_namespace();
        
        register_rest_route($namespace, '/posts', array(
            array(
                'methods'  => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_items'),
                'args'     => $this->get_collection_params(),
            )
        ));
        
        register_rest_route($namespace, '/posts/(?P<id>\d+)', array(
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
     * 获取文章列表
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
            $cache_key = 'wp_miniprogram_posts_' . md5(serialize($request->get_params()));
            
            // 尝试从缓存获取数据
            $cached_data = get_transient($cache_key);
            if ($cached_data !== false) {
                $response = new WP_REST_Response($cached_data['data']);
                $response->header('X-WP-Total', $cached_data['total']);
                $response->header('X-WP-TotalPages', $cached_data['total_pages']);
                $response->header('X-WP-Cache', 'HIT');
                return $response;
            }
        }
        
        $args = array();
        
        // 处理参数
        $args['post_type'] = 'post';
        $args['post_status'] = 'publish';
        $args['posts_per_page'] = $request->get_param('per_page') ?: get_option('wp_miniprogram_posts_per_page', 10);
        $args['paged'] = $request->get_param('page') ?: 1;
        
        // 分类筛选
        if ($request->get_param('categories')) {
            $args['cat'] = $request->get_param('categories');
        }
        
        // 搜索
        if ($request->get_param('search')) {
            $args['s'] = $request->get_param('search');
        }
        
        // 保存全局变量
        global $post;
        $original_post = $post;
        
        $query = new WP_Query($args);
        $posts = array();
        
        // 错误处理
        if (is_wp_error($query)) {
            // 恢复全局变量
            $post = $original_post;
            error_log('WP Mini Program: WP_Query error - ' . $query->get_error_message());
            return new WP_Error('query_failed', __('Query failed', 'wp-mini-program'), array('status' => 500));
        }
        
        // 直接处理查询结果，避免使用the_post()方法
        foreach ($query->posts as $post) {
            // 确保$post对象有效
            if ($post && $post->post_status === 'publish') {
                $posts[] = $this->prepare_item_for_response($post, $request);
            }
        }
        
        // 恢复全局变量
        $post = $original_post;
        
        $response = new WP_REST_Response($posts);
        $response->header('X-WP-Total', $query->found_posts);
        $response->header('X-WP-TotalPages', $query->max_num_pages);
        
        // 如果启用缓存，则保存到缓存中
        if ($cache_enabled) {
            $cache_data = array(
                'data' => $posts,
                'total' => $query->found_posts,
                'total_pages' => $query->max_num_pages
            );
            set_transient($cache_key, $cache_data, $cache_duration);
            $response->header('X-WP-Cache', 'MISS');
        }
        
        return $response;
    }
    
    /**
     * 获取单个文章
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_item($request) {
        // 检查是否启用缓存
        $cache_enabled = get_option('wp_miniprogram_cache_enabled', 1);
        $cache_duration = get_option('wp_miniprogram_cache_duration', 300);
        
        if ($cache_enabled) {
            // 生成缓存键
            $cache_key = 'wp_miniprogram_post_' . $request->get_param('id');
            
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
        
        if (!$post || $post->post_status !== 'publish') {
            // 恢复全局变量
            $post = $original_post;
            return new WP_Error('post_not_found', __('Post not found', 'wp-mini-program'), array('status' => 404));
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
     * 准备文章数据
     *
     * @param WP_Post $post
     * @param WP_REST_Request $request
     * @return array
     */
    public function prepare_item_for_response($post, $request) {
        // 检查$post是否有效
        if (!$post || !is_a($post, 'WP_Post')) {
            error_log('WP Mini Program: Invalid post object passed to prepare_item_for_response');
            return array();
        }
        
        // 获取默认缩略图
        $default_thumbnail = get_option('wp_miniprogram_default_thumbnail', '');
        
        // 获取特色图像
        $thumbnail = '';
        if (has_post_thumbnail($post->ID)) {
            $thumbnail = get_the_post_thumbnail_url($post->ID, 'full');
        } elseif (!empty($default_thumbnail)) {
            $thumbnail = $default_thumbnail;
        }
        
        // 获取分类信息
        $categories = get_the_category($post->ID);
        
        // 获取自定义字段
        $view_count = get_post_meta($post->ID, 'views', true) ?: 0;
        $like_count = get_post_meta($post->ID, 'like_count', true) ?: 0;
        $favorite_count = get_post_meta($post->ID, 'favorite_count', true) ?: 0;
        
        // 处理文章内容，但不再添加代码行号
        $content = apply_filters('the_content', $post->post_content);
        // 移除添加行号的处理，直接返回原始内容
        // 为代码块添加特殊包装器
        $content = $this->wrap_code_blocks($content);
        // 为内联代码添加特殊类名
        $content = $this->process_inline_code($content);
        
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
                'rendered' => $content,
            ),
            'excerpt'        => array(
                'rendered' => apply_filters('the_excerpt', $post->post_excerpt),
            ),
            'author'         => (int) $post->post_author,
            'featured_media' => (int) get_post_thumbnail_id($post->ID),
            'comment_status' => $post->comment_status,
            'ping_status'    => $post->ping_status,
            'sticky'         => is_sticky($post->ID),
            'template'       => get_page_template_slug($post->ID),
            'format'         => get_post_format($post->ID),
            'meta'           => array(
                'links' => get_post_meta($post->ID, 'links', true),
            ),
            'categories'     => $categories,
            'tags'           => wp_get_post_tags($post->ID),
            'view_count'     => (int) $view_count,
            'like_count'     => (int) $like_count,
            'favorite_count' => (int) $favorite_count,
            'comment_count'  => (int) $post->comment_count,
            'thumbnail'      => $thumbnail,
        );
        
        return apply_filters('wp_miniprogram_post_data', $data, $post, $request);
    }
    
    /**
     * 为代码块添加行号
     *
     * @param string $content
     * @return string
     */
    private function add_line_numbers_to_code_blocks($content) {
        // 不再添加行号，直接返回原始内容
        return $content;
    }
    
    /**
     * 为代码块添加特殊包装器
     *
     * @param string $content
     * @return string
     */
    private function wrap_code_blocks($content) {
        // 为代码块添加特殊包装器，以便在小程序端更好地处理
        $content = preg_replace(
            '/(<pre[^>]*>.*?<\/pre>)/is', 
            '<div class="code-block-wrapper">$1</div>', 
            $content
        );
        
        return $content;
    }
    
    /**
     * 为内联代码添加特殊类名
     *
     * @param string $content
     * @return string
     */
    private function process_inline_code($content) {
        // 直接为所有code标签添加inline-code类名
        // 然后移除pre标签内code标签的类名
        $content = preg_replace('/<code>/i', '<code class="inline-code">', $content);
        
        // 移除pre标签内code标签的类名
        $content = preg_replace(
            '/(<pre[^>]*>.*?<)code class="inline-code"(>.*?<\/pre>)/is', 
            '${1}code${2}', 
            $content
        );
        
        return $content;
    }
    
    /**
     * 获取集合参数
     *
     * @return array
     */
    public function get_collection_params() {
        return array(
            'context'   => array(
                'default' => 'view',
            ),
            'page'      => array(
                'description'       => __('Current page of the collection.', 'wp-mini-program'),
                'type'              => 'integer',
                'default'           => 1,
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg',
                'minimum'           => 1,
            ),
            'per_page'  => array(
                'description'       => __('Maximum number of items to be returned in result set.', 'wp-mini-program'),
                'type'              => 'integer',
                'default'           => get_option('wp_miniprogram_posts_per_page', 10),
                'minimum'           => 1,
                'maximum'           => 100,
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg',
            ),
            'search'    => array(
                'description'       => __('Limit results to those matching a string.', 'wp-mini-program'),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => 'rest_validate_request_arg',
            ),
            'categories' => array(
                'description'       => __('Limit result set to posts assigned to specific categories.', 'wp-mini-program'),
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg',
            ),
        );
    }
}