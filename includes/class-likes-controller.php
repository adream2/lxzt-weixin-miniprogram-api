<?php
/**
 * 点赞控制器类文件
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP Mini Program 点赞控制器类
 */
class WP_Mini_Program_Likes_Controller {
    
    /**
     * 注册路由
     */
    public function register_routes() {
        $namespace = WP_Mini_Program_API_Main::get_namespace();
        
        register_rest_route($namespace, '/likes', array(
            array(
                'methods'  => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_item'),
            ),
            array(
                'methods'  => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_item'),
            )
        ));
        
        // 检查文章是否已点赞
        register_rest_route($namespace, '/likes/check', array(
            array(
                'methods'  => WP_REST_Server::READABLE,
                'callback' => array($this, 'check_like'),
            )
        ));
    }
    
    /**
     * 添加点赞
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function create_item($request) {
        $post_id = $request->get_param('post_id');
        
        if (!$post_id) {
            return new WP_Error('missing_post_id', __('Post ID is required', 'wp-mini-program'), array('status' => 400));
        }
        
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return new WP_Error('post_not_found', __('Post not found', 'wp-mini-program'), array('status' => 404));
        }
        
        $user_id = $this->get_user_identifier();
        
        // 检查是否已点赞
        $liked_posts = get_option('liked_posts_' . $user_id, array());
        if (in_array($post_id, $liked_posts)) {
            return new WP_Error('already_liked', __('Post already liked', 'wp-mini-program'), array('status' => 400));
        }
        
        // 添加到点赞列表
        $liked_posts[] = $post_id;
        update_option('liked_posts_' . $user_id, $liked_posts);
        
        // 更新文章点赞数
        $like_count = get_post_meta($post_id, 'like_count', true) ?: 0;
        update_post_meta($post_id, 'like_count', $like_count + 1);
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Post liked', 'wp-mini-program'),
            'like_count' => $like_count + 1
        ), 201);
    }
    
    /**
     * 取消点赞
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function delete_item($request) {
        $post_id = $request->get_param('post_id');
        
        if (!$post_id) {
            return new WP_Error('missing_post_id', __('Post ID is required', 'wp-mini-program'), array('status' => 400));
        }
        
        $user_id = $this->get_user_identifier();
        
        // 检查是否已点赞
        $liked_posts = get_option('liked_posts_' . $user_id, array());
        $index = array_search($post_id, $liked_posts);
        
        if ($index === false) {
            return new WP_Error('not_liked', __('Post not liked', 'wp-mini-program'), array('status' => 400));
        }
        
        // 从点赞列表中移除
        unset($liked_posts[$index]);
        update_option('liked_posts_' . $user_id, array_values($liked_posts));
        
        // 更新文章点赞数
        $like_count = get_post_meta($post_id, 'like_count', true) ?: 0;
        update_post_meta($post_id, 'like_count', max(0, $like_count - 1));
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Post unliked', 'wp-mini-program'),
            'like_count' => max(0, $like_count - 1)
        ));
    }
    
    /**
     * 检查文章是否已点赞
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function check_like($request) {
        $post_id = $request->get_param('post_id');
        
        if (!$post_id) {
            return new WP_Error('missing_post_id', __('Post ID is required', 'wp-mini-program'), array('status' => 400));
        }
        
        $user_id = $this->get_user_identifier();
        $liked_posts = get_option('liked_posts_' . $user_id, array());
        $is_liked = in_array($post_id, $liked_posts);
        
        return new WP_REST_Response(array(
            'liked' => $is_liked
        ));
    }
    
    /**
     * 获取用户标识符
     *
     * @return string
     */
    protected function get_user_identifier() {
        // 对于个人认证小程序，使用IP地址作为用户标识
        return md5($_SERVER['REMOTE_ADDR']);
    }
}