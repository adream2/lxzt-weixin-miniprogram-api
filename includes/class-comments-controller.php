<?php
/**
 * 评论控制器类文件
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP Mini Program 评论控制器类
 */
class WP_Mini_Program_Comments_Controller {
    
    /**
     * 注册路由
     */
    public function register_routes() {
        $namespace = WP_Mini_Program_API_Main::get_namespace();
        
        register_rest_route($namespace, '/comments', array(
            array(
                'methods'  => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_items'),
                'args'     => $this->get_collection_params(),
            ),
            array(
                'methods'  => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_item'),
                'args'     => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
            )
        ));
        
        register_rest_route($namespace, '/comments/(?P<id>\d+)', array(
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
     * 获取评论列表
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
            $cache_key = 'wp_miniprogram_comments_' . md5(serialize($request->get_params()));
            
            // 尝试从缓存获取数据
            $cached_data = get_transient($cache_key);
            if ($cached_data !== false) {
                $response = new WP_REST_Response($cached_data);
                $response->header('X-WP-Cache', 'HIT');
                return $response;
            }
        }
        
        $args = array(
            'status'   => 'approve',
            'order'    => 'ASC',
            'number'   => $request->get_param('per_page') ?: 100,
            'post_id'  => $request->get_param('post') ?: 0,
        );
        
        // 处理分页参数
        $page = $request->get_param('page') ?: 1;
        if ($page > 1) {
            $args['offset'] = ($page - 1) * $args['number'];
        }
        
        $comments = get_comments($args);
        $data = array();
        
        foreach ($comments as $comment) {
            $data[] = $this->prepare_item_for_response($comment, $request);
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
     * 清除评论相关缓存
     *
     * @param int $post_id
     */
    protected function clear_comment_cache($post_id) {
        // 删除与该文章相关的评论缓存
        global $wpdb;
        $cache_keys = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s",
            '_transient_wp_miniprogram_comments_%' . $post_id . '%'
        ));
        
        foreach ($cache_keys as $cache_key) {
            delete_transient(str_replace('_transient_', '', $cache_key));
        }
    }
    
    /**
     * 获取单个评论
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_item($request) {
        $id = $request->get_param('id');
        $comment = get_comment($id);
        
        if (!$comment || $comment->comment_approved !== '1') {
            return new WP_Error('comment_not_found', __('Comment not found', 'wp-mini-program'), array('status' => 404));
        }
        
        return new WP_REST_Response($this->prepare_item_for_response($comment, $request));
    }
    
    /**
     * 创建评论
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function create_item($request) {
        // 检查是否允许小程序评论
        if (!get_option('wp_miniprogram_allow_comments', 1)) {
            return new WP_Error('comments_disabled', __('Comments are disabled for the Mini Program', 'wp-mini-program'), array('status' => 403));
        }
        
        $prepared_comment = array(
            'comment_post_ID'      => $request->get_param('post'),
            'comment_author'       => $request->get_param('author_name'),
            'comment_author_email' => $request->get_param('author_email'),
            'comment_content'      => $request->get_param('content'),
            'comment_author_url'   => $request->get_param('author_url') ?: '',
            'comment_parent'       => $request->get_param('parent') ?: 0,
            'comment_approved'     => 0, // 默认设置为待审核状态
        );
        
        // 检查必需字段
        if (empty($prepared_comment['comment_post_ID']) || empty($prepared_comment['comment_content'])) {
            return new WP_Error('missing_fields', __('Missing required fields', 'wp-mini-program'), array('status' => 400));
        }
        
        // 检查文章是否存在
        $post = get_post($prepared_comment['comment_post_ID']);
        if (!$post || $post->post_status !== 'publish') {
            return new WP_Error('post_not_found', __('Post not found', 'wp-mini-program'), array('status' => 404));
        }
        
        // 检查文章是否允许评论
        if ($post->comment_status !== 'open') {
            return new WP_Error('comments_closed', __('Comments are closed for this post', 'wp-mini-program'), array('status' => 403));
        }
        
        // 检查全局评论频率限制（避免短时间内大量评论）
        $global_limit = $this->check_global_comment_flood();
        if ($global_limit) {
            return new WP_Error('comment_flood', __('Too many comments are being posted. Please try again later.', 'wp-mini-program'), array('status' => 429));
        }
        
        // 检查基于IP的评论频率限制
        $ip = $this->get_client_ip();
        $ip_limit = $this->check_ip_comment_flood($ip);
        if ($ip_limit) {
            return new WP_Error('comment_flood', sprintf(__('You can only post a comment once every %d minutes.', 'wp-mini-program'), get_option('wp_miniprogram_comment_interval', 5)), array('status' => 429));
        }
        
        // 添加评论
        $comment_id = wp_insert_comment($prepared_comment);
        
        if (is_wp_error($comment_id)) {
            return $comment_id;
        }
        
        // 记录评论IP地址
        update_comment_meta($comment_id, 'comment_ip', $ip);
        
        // 获取创建的评论
        $comment = get_comment($comment_id);
        
        // 返回评论数据
        $response = new WP_REST_Response($this->prepare_item_for_response($comment, $request));
        $response->set_status(201);
        
        return $response;
    }
    
    /**
     * 获取客户端真实IP地址
     *
     * @return string 客户端IP地址
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // 如果是通过代理的IP列表，取第一个
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // 验证IP地址格式
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
    
    /**
     * 检查全局评论洪流（防止短时间内大量评论）
     *
     * @return bool 是否触发全局限制
     */
    private function check_global_comment_flood() {
        // 检查最近1分钟内是否已有过多评论（超过10条）
        $recent_comments = get_comments(array(
            'status' => 'all',
            'number' => 10,
            'date_query' => array(
                array(
                    'after' => '1 minute ago'
                )
            )
        ));
        
        return count($recent_comments) >= 10;
    }
    
    /**
     * 检查基于IP的评论频率限制
     *
     * @param string $ip_address 用户IP地址
     * @return bool 是否触发IP限制
     */
    private function check_ip_comment_flood($ip_address) {
        $interval = get_option('wp_miniprogram_comment_interval', 5); // 默认5分钟
        
        // 获取该IP最近的评论
        $last_comment = get_comments(array(
            'meta_query' => array(
                array(
                    'key' => 'comment_ip',
                    'value' => $ip_address,
                )
            ),
            'number' => 1,
            'order' => 'DESC'
        ));
        
        if (!empty($last_comment)) {
            $time_diff = time() - strtotime($last_comment[0]->comment_date);
            if ($time_diff < ($interval * 60)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 准备评论数据
     *
     * @param WP_Comment $comment
     * @param WP_REST_Request $request
     * @return array
     */
    public function prepare_item_for_response($comment, $request) {
        $data = array(
            'id'           => (int) $comment->comment_ID,
            'post'         => (int) $comment->comment_post_ID,
            'parent'       => (int) $comment->comment_parent,
            'author'       => (int) $comment->user_id,
            'author_name'  => $comment->comment_author,
            'author_email' => $comment->comment_author_email,
            'author_url'   => $comment->comment_author_url,
            'date'         => $comment->comment_date,
            'date_gmt'     => $comment->comment_date_gmt,
            'content'      => array(
                'rendered' => apply_filters('comment_text', $comment->comment_content, $comment),
            ),
            'status'       => $comment->comment_approved,
            'type'         => $comment->comment_type,
        );
        
        return apply_filters('wp_miniprogram_comment_data', $data, $comment, $request);
    }
    
    /**
     * 获取集合参数
     *
     * @return array
     */
    public function get_collection_params() {
        return array(
            'context'  => array(
                'default' => 'view',
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
                'default'           => 100,
                'minimum'           => 1,
                'maximum'           => 100,
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg',
            ),
            'post'     => array(
                'description'       => __('Limit result set to comments assigned to specific post.', 'wp-mini-program'),
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg',
            ),
        );
    }
    
    /**
     * 获取项目模式
     *
     * @param string $method
     * @return array
     */
    public function get_endpoint_args_for_item_schema($method) {
        $args = array();
        
        if ($method === WP_REST_Server::CREATABLE) {
            $args['post'] = array(
                'required'          => true,
                'description'       => __('The ID of the associated post object.', 'wp-mini-program'),
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg',
            );
            
            $args['author_name'] = array(
                'required'          => true,
                'description'       => __('Display name for the object author.', 'wp-mini-program'),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => 'rest_validate_request_arg',
            );
            
            $args['author_email'] = array(
                'required'          => false,
                'description'       => __('Email address for the object author.', 'wp-mini-program'),
                'type'              => 'string',
                'format'            => 'email',
                'sanitize_callback' => 'sanitize_email',
                'validate_callback' => 'rest_validate_request_arg',
            );
            
            $args['content'] = array(
                'required'          => true,
                'description'       => __('The content for the object.', 'wp-mini-program'),
                'type'              => 'string',
                'sanitize_callback' => 'wp_filter_post_kses',
                'validate_callback' => 'rest_validate_request_arg',
            );
            
            $args['parent'] = array(
                'required'          => false,
                'description'       => __('The ID for the parent of the object.', 'wp-mini-program'),
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg',
            );
        }
        
        return $args;
    }
}