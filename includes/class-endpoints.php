<?php
/**
 * 端点管理类文件
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP Mini Program 端点管理类
 */
class WP_Mini_Program_Endpoints {
    
    /**
     * 初始化端点
     */
    public static function init() {
        // 初始化文章控制器
        $posts_controller = new WP_Mini_Program_Posts_Controller();
        $posts_controller->register_routes();
        
        // 初始化分类控制器
        $categories_controller = new WP_Mini_Program_Categories_Controller();
        $categories_controller->register_routes();
        
        // 初始化评论控制器
        $comments_controller = new WP_Mini_Program_Comments_Controller();
        $comments_controller->register_routes();
        
        // 初始化页面控制器
        $pages_controller = new WP_Mini_Program_Pages_Controller();
        $pages_controller->register_routes();
        
        // 初始化搜索控制器
        $search_controller = new WP_Mini_Program_Search_Controller();
        $search_controller->register_routes();
        
        // 初始化点赞控制器
        $likes_controller = new WP_Mini_Program_Likes_Controller();
        $likes_controller->register_routes();
        
        // 初始化收藏控制器
        $favorites_controller = new WP_Mini_Program_Favorites_Controller();
        $favorites_controller->register_routes();
        
        // 初始化网站信息控制器
        $site_info_controller = new WP_Mini_Program_Site_Info_Controller();
        $site_info_controller->register_routes();
    }
}