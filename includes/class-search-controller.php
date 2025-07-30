<?php
/**
 * 搜索控制器类文件
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP Mini Program 搜索控制器类
 */
class WP_Mini_Program_Search_Controller {
    
    /**
     * 注册路由
     */
    public function register_routes() {
        $namespace = WP_Mini_Program_API_Main::get_namespace();
        
        register_rest_route($namespace, '/search', array(
            array(
                'methods'  => WP_REST_Server::READABLE,
                'callback' => array($this, 'search'),
                'args'     => $this->get_collection_params(),
            )
        ));
        
        register_rest_route($namespace, '/search/hot', array(
            array(
                'methods'  => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_hot_searches'),
            )
        ));
        
        register_rest_route($namespace, '/search/history', array(
            array(
                'methods'  => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_search_history'),
            ),
            array(
                'methods'  => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'clear_search_history'),
            )
        ));
    }
    
    /**
     * 搜索文章
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function search($request) {
        $keyword = $request->get_param('keyword');
        
        if (empty($keyword)) {
            return new WP_Error('missing_keyword', __('Search keyword is required', 'wp-mini-program'), array('status' => 400));
        }
        
        // 记录搜索关键词
        $this->record_search_keyword($keyword);
        
        $args = array(
            's'              => $keyword,
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $request->get_param('per_page') ?: 10,
            'paged'          => $request->get_param('page') ?: 1,
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
            error_log('WP Mini Program: WP_Query error in search - ' . $query->get_error_message());
            return new WP_Error('query_failed', __('Query failed', 'wp-mini-program'), array('status' => 500));
        }
        
        // 直接处理查询结果，避免使用the_post()方法
        foreach ($query->posts as $post) {
            // 确保$post对象有效
            if ($post && $post->post_status === 'publish') {
                $posts[] = $this->prepare_post_for_response($post, $request);
            }
        }
        
        // 恢复全局变量
        $post = $original_post;
        
        $response = new WP_REST_Response($posts);
        $response->header('X-WP-Total', $query->found_posts);
        $response->header('X-WP-TotalPages', $query->max_num_pages);
        
        return $response;
    }
    
    /**
     * 获取热门搜索
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_hot_searches($request) {
        $limit = $request->get_param('limit') ?: 10;
        $hot_searches = get_option('wp_miniprogram_hot_searches', array());
        
        // 按搜索次数排序
        uasort($hot_searches, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        // 取前N个
        $hot_searches = array_slice($hot_searches, 0, $limit);
        
        return new WP_REST_Response(array_values($hot_searches));
    }
    
    /**
     * 获取搜索历史
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_search_history($request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_REST_Response(array());
        }
        
        $history = get_user_meta($user_id, 'wp_miniprogram_search_history', true);
        if (!$history) {
            $history = array();
        }
        
        return new WP_REST_Response($history);
    }
    
    /**
     * 清除搜索历史
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function clear_search_history($request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('unauthorized', __('You must be logged in to clear search history', 'wp-mini-program'), array('status' => 401));
        }
        
        delete_user_meta($user_id, 'wp_miniprogram_search_history');
        
        return new WP_REST_Response(array('message' => __('Search history cleared', 'wp-mini-program')));
    }
    
    /**
     * 记录搜索关键词
     *
     * @param string $keyword
     */
    private function record_search_keyword($keyword) {
        // 记录到热门搜索
        $hot_searches = get_option('wp_miniprogram_hot_searches', array());
        if (!isset($hot_searches[$keyword])) {
            $hot_searches[$keyword] = array(
                'keyword' => $keyword,
                'count'   => 1
            );
        } else {
            $hot_searches[$keyword]['count']++;
        }
        update_option('wp_miniprogram_hot_searches', $hot_searches);
        
        // 记录到用户搜索历史
        $user_id = get_current_user_id();
        if ($user_id) {
            $history = get_user_meta($user_id, 'wp_miniprogram_search_history', true);
            if (!$history) {
                $history = array();
            }
            
            // 如果关键词已存在，移到最前面
            foreach ($history as $key => $item) {
                if ($item['keyword'] === $keyword) {
                    unset($history[$key]);
                    break;
                }
            }
            
            // 添加新的搜索记录
            array_unshift($history, array(
                'keyword' => $keyword,
                'time'    => current_time('mysql')
            ));
            
            // 限制历史记录数量
            $history = array_slice($history, 0, 20);
            
            update_user_meta($user_id, 'wp_miniprogram_search_history', $history);
        }
    }
    
    /**
     * 准备文章数据
     *
     * @param WP_Post $post
     * @param WP_REST_Request $request
     * @return array
     */
    private function prepare_post_for_response($post, $request) {
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
        
        return array(
            'id'         => $post->ID,
            'date'       => $post->post_date,
            'slug'       => $post->post_name,
            'type'       => $post->post_type,
            'link'       => get_permalink($post->ID),
            'title'      => array(
                'rendered' => !empty($post->post_title) ? $post->post_title : '',
            ),
            'excerpt'    => array(
                'rendered' => apply_filters('the_excerpt', $post->post_excerpt),
            ),
            'author'     => (int) $post->post_author,
            'categories' => $categories,
            'tags'       => wp_get_post_tags($post->ID),
            'comment_count' => (int) $post->comment_count,
            'thumbnail'  => $thumbnail,
        );
    }
    
    /**
     * 获取集合参数
     *
     * @return array
     */
    public function get_collection_params() {
        return array(
            'keyword' => array(
                'description'       => __('Search keyword.', 'wp-mini-program'),
                'type'              => 'string',
                'required'          => true,
                'validate_callback' => 'rest_validate_request_arg',
            ),
            'page'     => array(
                'description'       => __('Current page of the collection.', 'wp-mini-program'),
                'type'              => 'integer',
                'default'           => 1,
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg',
                'minimum'           => 1,
            ),
            'per_page' => array(
                'description'       => __('Maximum number of items to be returned in result set.', 'wp-mini-program'),
                'type'              => 'integer',
                'default'           => 10,
                'minimum'           => 1,
                'maximum'           => 100,
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg',
            ),
        );
    }
}