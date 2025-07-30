<?php
/**
 * 收藏控制器类文件
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP Mini Program 收藏控制器类
 */
class WP_Mini_Program_Favorites_Controller {
    
    /**
     * 注册路由
     */
    public function register_routes() {
        $namespace = WP_Mini_Program_API_Main::get_namespace();
        
        register_rest_route($namespace, '/favorites', array(
            array(
                'methods'  => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_items'),
            ),
            array(
                'methods'  => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_item'),
            ),
            array(
                'methods'  => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_item'),
            )
        ));
        
        // 检查文章是否已收藏
        register_rest_route($namespace, '/favorites/check', array(
            array(
                'methods'  => WP_REST_Server::READABLE,
                'callback' => array($this, 'check_favorite'),
            )
        ));
    }
    
    /**
     * 获取用户收藏列表
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_items($request) {
        $favorites = $this->get_user_favorites();
        
        $posts = array();
        foreach ($favorites as $post_id) {
            $post = get_post($post_id);
            if ($post && $post->post_status === 'publish') {
                $posts[] = $this->prepare_post_for_response($post, $request);
            }
        }
        
        return new WP_REST_Response($posts);
    }
    
    /**
     * 添加收藏
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
        
        $favorites = $this->get_user_favorites();
        
        // 检查是否已收藏
        if (in_array($post_id, $favorites)) {
            return new WP_Error('already_favorited', __('Post already favorited', 'wp-mini-program'), array('status' => 400));
        }
        
        // 添加到收藏
        $favorites[] = $post_id;
        $this->update_user_favorites($favorites);
        
        // 更新文章收藏数
        $favorite_count = get_post_meta($post_id, 'favorite_count', true) ?: 0;
        update_post_meta($post_id, 'favorite_count', $favorite_count + 1);
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Post added to favorites', 'wp-mini-program')
        ), 201);
    }
    
    /**
     * 删除收藏
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function delete_item($request) {
        $post_id = $request->get_param('post_id');
        
        if (!$post_id) {
            return new WP_Error('missing_post_id', __('Post ID is required', 'wp-mini-program'), array('status' => 400));
        }
        
        $favorites = $this->get_user_favorites();
        
        // 检查是否已收藏
        $index = array_search($post_id, $favorites);
        if ($index === false) {
            return new WP_Error('not_favorited', __('Post not favorited', 'wp-mini-program'), array('status' => 400));
        }
        
        // 从收藏中移除
        unset($favorites[$index]);
        $this->update_user_favorites(array_values($favorites));
        
        // 更新文章收藏数
        $favorite_count = get_post_meta($post_id, 'favorite_count', true) ?: 0;
        update_post_meta($post_id, 'favorite_count', max(0, $favorite_count - 1));
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Post removed from favorites', 'wp-mini-program')
        ));
    }
    
    /**
     * 检查文章是否已收藏
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function check_favorite($request) {
        $post_id = $request->get_param('post_id');
        
        if (!$post_id) {
            return new WP_Error('missing_post_id', __('Post ID is required', 'wp-mini-program'), array('status' => 400));
        }
        
        $favorites = $this->get_user_favorites();
        $is_favorited = in_array($post_id, $favorites);
        
        return new WP_REST_Response(array(
            'favorited' => $is_favorited
        ));
    }
    
    /**
     * 获取用户收藏列表
     *
     * @return array
     */
    protected function get_user_favorites() {
        // 使用用户IP作为标识（对于个人认证小程序）
        $user_id = $this->get_user_identifier();
        $favorites = get_option('favorites_' . $user_id, array());
        
        // 确保返回的是数组
        if (!is_array($favorites)) {
            $favorites = array();
        }
        
        return $favorites;
    }
    
    /**
     * 更新用户收藏列表
     *
     * @param array $favorites
     * @return bool
     */
    protected function update_user_favorites($favorites) {
        $user_id = $this->get_user_identifier();
        return update_option('favorites_' . $user_id, $favorites);
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
    
    /**
     * 准备文章数据
     *
     * @param WP_Post $post
     * @param WP_REST_Request $request
     * @return array
     */
    protected function prepare_post_for_response($post, $request) {
        $posts_controller = new WP_Mini_Program_Posts_Controller();
        return $posts_controller->prepare_item_for_response($post, $request);
    }
}