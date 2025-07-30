<?php
/**
 * API主类文件
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP Mini Program API 主类
 */
class WP_Mini_Program_API_Main {
    
    /**
     * 插件版本
     *
     * @var string
     */
    const VERSION = '1.0.0';
    
    /**
     * API 命名空间
     *
     * @var string
     */
    const NAMESPACE = 'wp-mini-program/v1';
    
    /**
     * 插件实例
     *
     * @var WP_Mini_Program_API_Main
     */
    private static $instance = null;
    
    /**
     * 获取插件实例（单例模式）
     *
     * @return WP_Mini_Program_API_Main
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 构造函数
     */
    private function __construct() {
        // 初始化
    }
    
    /**
     * 获取API命名空间
     *
     * @return string
     */
    public static function get_namespace() {
        return self::NAMESPACE;
    }
    
    /**
     * 获取插件版本
     *
     * @return string
     */
    public static function get_version() {
        return self::VERSION;
    }
}