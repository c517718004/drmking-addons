<?php
/*
 * @Author: 常立超
 * @Date: 2025-07-03 21:15:26
 * @LastEditors: 常立超
 * @LastEditTime: 2025-09-14 10:39:42
 */
declare(strict_types=1);

// 插件类库自动载入
spl_autoload_register(function ($class) {
    $class = ltrim($class, '\\');
    if (function_exists('app')) {
        $dir = app()->getRootPath();
    } else {
        $dir = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/';
    }
    $namespace = 'addons';
    if (strpos($class, $namespace) === 0) {
        $class = substr($class, strlen($namespace));
        $path = '';
        if (($pos = strripos($class, '\\')) !== false) {
            $path = str_replace('\\', '/', substr($class, 0, $pos)) . '/';
            $class = substr($class, $pos + 1);
        }
        $path .= str_replace('_', '/', $class) . '.php';
        $dir .= $namespace . $path;
        if (file_exists($dir)) {
            include $dir;
            return true;
        }
        return false;
    }
    return false;
});