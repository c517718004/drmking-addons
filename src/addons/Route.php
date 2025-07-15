<?php
/*
 * @Author: 常立超
 * @Date: 2025-07-06 08:51:21
 * @LastEditors: 常立超
 * @LastEditTime: 2025-07-11 16:44:05
 */
declare(strict_types=1);
namespace think\addons;
use think\helper\Str;
use think\facade\Event;
use think\facade\Config;
use think\exception\HttpException;
class Route
{
    /**
     * 插件路由请求
     * @return mixed
     */
    public static $addonPath;

    public static function execute()
    {
        $addon = app()->request->route('addon');
        $module = app()->request->route('module') ?? 'index';
        $controller = app()->request->route('controller');
        $action = app()->request->route('action') ?? 'index';
        Event::trigger('addons_begin', app()->request);
        if (empty($addon) || empty($controller) || empty($action)) {
            throw new HttpException(500, lang('addon can not be empty'));
        }
        self::$addonPath = Service::getAddonPath();
        app()->request->addon = $addon;
        // 设置当前请求的控制器、操作
        app()->request->setController("{$module}.{$controller}")->setAction($action);
        // 获取插件基础信息
        $info = app()->addons->getAddonInfo();
        if (!$info) {
            throw new HttpException(404, lang('addon %s not found', [$addon]));
        }
        if (!$info['status']) {
            throw new HttpException(500, lang('addon %s is disabled', [$addon]));
        }
        // 监听addon_module_init
        Event::trigger('addon_module_init', app()->request);
        $class = app()->addons->getAddonsControllerClass($controller);
        if (!$class) {
            throw new HttpException(404, lang('addon controller %s not found', [Str::studly($module . DIRECTORY_SEPARATOR . $controller)]));
        }
        // 重写视图基础路径
        $config = Config::get('view');
        $config['view_path'] = app()->addons->getAddonsPath() . $addon . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'view' . DIRECTORY_SEPARATOR;
        Config::set($config, 'view');
        if (is_file(self::$addonPath . 'app.php')) {
            $addonAppConfig = (require_once(self::$addonPath . 'app.php'));
            $deny = !empty($addonAppConfig['deny_app_list']) ? $addonAppConfig['deny_app_list'] : Config::get('app.deny_app_list');
            if ($module && $deny && in_array($module, $deny)) {
                throw new HttpException(404, lang('addon app %s is ', []));
            }
        }
        // 生成控制器对象
        try {
            $instance = new $class(app());
        } catch (\Exception $e) {
            throw new HttpException(404, lang('addon controller %s not found', [Str::studly($controller)]));
        }
        $vars = [];
        if (is_callable([$instance, $action])) {
            // 执行操作方法
            $call = [$instance, $action];
        } elseif (is_callable([$instance, '_empty'])) {
            // 空操作
            $call = [$instance, '_empty'];
            $vars = [$action];
        } elseif (is_callable([$instance, '__call'])) {
            $call = [$instance, '__call'];
            $vars = [$action];
        } else {
            // 操作不存在
            throw new HttpException(404, lang('addon action %s not found', [get_class($instance) . '->' . $action . '()']));
        }
        Event::trigger('addons_action_begin', $call);
        return call_user_func_array($call, $vars);
    }
}
