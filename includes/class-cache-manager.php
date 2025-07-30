<?php
/**
 * 缓存管理类文件
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP Mini Program 缓存管理类
 */
class WP_Mini_Program_Cache_Manager {
    
    /**
     * 初始化缓存管理
     */
    public static function init() {
        // 文章保存时清除相关缓存
        add_action('save_post', array(__CLASS__, 'clear_post_cache'));
        add_action('deleted_post', array(__CLASS__, 'clear_post_cache'));
        
        // 评论相关操作时清除评论缓存
        add_action('wp_insert_comment', array(__CLASS__, 'clear_comment_cache'));
        add_action('edit_comment', array(__CLASS__, 'clear_comment_cache'));
        add_action('delete_comment', array(__CLASS__, 'clear_comment_cache'));
        
        // 分类相关操作时清除分类缓存
        add_action('created_category', array(__CLASS__, 'clear_category_cache'));
        add_action('edited_category', array(__CLASS__, 'clear_category_cache'));
        add_action('delete_category', array(__CLASS__, 'clear_category_cache'));
        
        // 页面保存时清除相关缓存
        add_action('save_page', array(__CLASS__, 'clear_page_cache'));
        add_action('deleted_page', array(__CLASS__, 'clear_page_cache'));
        
        // 选项更新时清除网站信息缓存
        add_action('update_option_wp_miniprogram_cache_enabled', array(__CLASS__, 'clear_site_info_cache'));
        add_action('update_option_wp_miniprogram_cache_duration', array(__CLASS__, 'clear_site_info_cache'));
    }
    
    /**
     * 清除文章相关缓存
     *
     * @param int $post_id
     */
    public static function clear_post_cache($post_id) {
        // 清除单篇文章缓存
        delete_transient('wp_miniprogram_post_' . $post_id);
        
        // 清除文章列表缓存（所有可能的组合）
        global $wpdb;
        $cache_keys = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s",
            '_transient_wp_miniprogram_posts_%'
        ));
        
        foreach ($cache_keys as $cache_key) {
            delete_transient(str_replace('_transient_', '', $cache_key));
        }
        
        // 清除分类下的文章缓存
        $categories = wp_get_post_categories($post_id);
        foreach ($categories as $category_id) {
            delete_transient('wp_miniprogram_category_posts_' . $category_id);
        }
    }
    
    /**
     * 清除评论相关缓存
     *
     * @param int $comment_id
     */
    public static function clear_comment_cache($comment_id) {
        // 获取评论信息
        $comment = get_comment($comment_id);
        if ($comment) {
            // 清除该文章的评论缓存
            global $wpdb;
            $cache_keys = $wpdb->get_col($wpdb->prepare(
                "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s",
                '_transient_wp_miniprogram_comments_%post=' . $comment->comment_post_ID . '%'
            ));
            
            foreach ($cache_keys as $cache_key) {
                delete_transient(str_replace('_transient_', '', $cache_key));
            }
        }
    }
    
    /**
     * 清除分类相关缓存
     *
     * @param int $category_id
     */
    public static function clear_category_cache($category_id) {
        // 清除分类列表缓存
        delete_transient('wp_miniprogram_categories');
        
        // 清除特定分类的文章缓存
        delete_transient('wp_miniprogram_category_posts_' . $category_id);
    }
    
    /**
     * 清除页面相关缓存
     *
     * @param int $post_id
     */
    public static function clear_page_cache($post_id) {
        // 清除单个页面缓存
        delete_transient('wp_miniprogram_page_' . $post_id);
        
        // 清除页面列表缓存
        delete_transient('wp_miniprogram_pages');
    }
    
    /**
     * 清除网站信息缓存
     */
    public static function clear_site_info_cache() {
        // 清除网站信息缓存
        delete_transient('wp_miniprogram_site_info');
    }
    
    /**
     * 清除所有缓存
     */
    public static function clear_all_cache() {
        global $wpdb;
        
        // 删除所有与小程序相关的缓存
        $cache_keys = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s",
            '_transient_wp_miniprogram_%'
        ));
        
        foreach ($cache_keys as $cache_key) {
            delete_transient(str_replace('_transient_', '', $cache_key));
        }
    }
}