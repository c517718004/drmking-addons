<?php
/*
 * @Author: 常立超
 * @Date: 2025-07-03 21:15:26
 * @LastEditors: 常立超
 * @LastEditTime: 2025-07-05 16:13:43
 */
/*
 * @Author: 常立超
 * @Date: 2025-07-01 11:53:20
 * @LastEditors: 常立超
 * @LastEditTime: 2025-07-04 11:39:42
 */
declare(strict_types=1);
namespace think\addons;
use think\Route;
use think\exception\HttpException;
use think\facade\Lang;
use think\facade\Cache;
use think\facade\Event;
use think\helper\Str;
use think\Service as BaseService;
use think\addons\middleware\Addons;
/**
 * 插件服务
 * Class Service
 * @package think\addons
 */
class Service extends BaseService
{
    // 插件所在目录
    protected static $addonsPath;
    // 插件名称
    protected static $addonName;
    // 插件目录
    protected static $addonPath;
    // 是否多应用
    protected static $multiApp = false;
    // 应用名称
    protected static $appName = '';
    // 是否是插件应用
    protected static $isAddonApp = false;

    // 插件Plugin路由

    protected static $addonPlugin = false;

    /**
     * 获取当前运行入口名称
     * @access protected
     * @codeCoverageIgnore
     * @return string
     */
    public static function getPathInfo(): string
    {
        if (isset($_SERVER['PATH_INFO']) && !empty($_SERVER['PATH_INFO'])) {
            return $_SERVER['PATH_INFO']; // 直接获取标准PATH_INFO变量 :ml-citation{ref="9,15" data="citationList"}
        }
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        // 移除查询字符串
        if (($pos = strpos($requestUri, '?')) !== false) {
            $requestUri = substr($requestUri, 0, $pos);
        }
        // 提取PATH_INFO
        if (str_starts_with($requestUri, $scriptName)) {
            $pathInfo = substr($requestUri, strlen($scriptName));
        } else {
            $pathInfo = preg_replace('/^' . preg_quote(dirname($scriptName), '/') . '/', '', $requestUri, 1);
        }
        return trim($pathInfo, '/') ?? '';
    }
    /**
     * 设置插件的名称
     * @return bool
     */
    public static function init(): bool
    {
        $pathinfo = self::getPathInfo();
        if (str_starts_with($pathinfo, 'addons/')) {
            self::setAddonsPath();
            $pathinfo_arr = explode('/', $pathinfo);
            $addon_list = self::getAddonList();
            if (isset($pathinfo_arr[1]) && array_key_exists($pathinfo_arr[1], $addon_list)) {
                self::setIsAddonApp(true);
                self::setAddonName($pathinfo_arr[1]);
                self::setAddonPath();
                if (isset($pathinfo_arr[1]) && strtolower($pathinfo_arr[2]) == 'plugin') {
                    $class = self::getAddonsPluginClass(self::getAddonName());
                    $methods = get_class_methods(new $class(app()));
                    $methods = array_merge($methods, ['init', 'initialize', 'install', 'uninstall', 'enabled', 'disabled']);
                    if (isset($pathinfo_arr[3]) && in_array($pathinfo_arr[3], $methods)) {
                        self::$addonPlugin = [$class, $pathinfo_arr[3]];
                        return true;
                    }
                } else {
                    // 单应用
                    if (self::isApp()) {
                        self::setMultiApp(false);
                        if (isset($pathinfo_arr[2])) {
                            $class = self::getAddonsControllerClass($pathinfo_arr[2]);
                            if (!empty($class)) {
                                return true;
                            }
                        }
                    } else {
                        self::setMultiApp(true);
                        if (isset($pathinfo_arr[2]) && isset($pathinfo_arr[3]) && self::isApp($pathinfo_arr[2])) {
                            self::setAppName($pathinfo_arr[2]);
                            $class = self::getAddonsControllerClass($pathinfo_arr[3]);
                            if (!empty($class)) {
                                return true;
                            }
                        }
                    }
                }
            }
        }
        return false;
    }
    /**
     * 获取插件类的类名
     * @param string $name 插件名
     * @param string $type 返回命名空间类型
     * @param string $class 当前类名
     * @return string
     */
    public static function getAddonsControllerClass($class_name)
    {
        $class_name = trim($class_name);
        // 处理多级控制器情况
        if (!empty($class_name) && strpos($class_name, '.')) {
            $class_name = explode('.', $class_name);
            $class_name[count($class_name) - 1] = Str::studly(end($class_name));
            $class_name = implode('\\', $class_name);
        } else {
            $class_name = Str::studly($class_name);
        }
        $paths = [
            'addons',
            self::getAddonName(),
        ];
        if (self::getMultiApp()) {
            array_push($paths, self::getAppName());
        }
        array_push($paths, 'controller');
        array_push($paths, $class_name);
        $namespace = '\\' . implode('\\', $paths) . 'Controller';
        return class_exists($namespace) ? $namespace : '';
    }
    /**
     * 获取插件类的类名
     * @param string $name 插件名
     * @param string $type 返回命名空间类型
     * @param string $class 当前类名
     * @return string
     */
    public static function getAddonsPluginClass($addon_name)
    {
        $addon_name = trim($addon_name);
        $paths = [
            'addons',
            $addon_name,
            'Plugin',
        ];
        $namespace = '\\' . implode('\\', $paths);
        return class_exists($namespace) ? $namespace : '';
    }
    /**
     * 判断是否应用目录
     * @param mixed $app_name
     * @return bool
     */
    public static function isApp($app_name = '')
    {
        if (empty($app_name)) {
            $controller_path = self::getAddonPath() . 'controller';
        } else {
            $controller_path = self::getAddonPath() . $app_name . DIRECTORY_SEPARATOR . 'controller';
        }
        return is_dir($controller_path);
    }
    /**
     * 设置 是否插件应用
     * @return void
     */
    public static function setIsAddonApp($is_addon_app = false): void
    {
        self::$isAddonApp = $is_addon_app;
    }
    /**
     * 获取 是否插件应用
     * @return bool
     */
    public static function getIsAddonApp(): bool
    {
        return self::$isAddonApp;
    }
    /**
     * 设置 是否多应用
     * @return void
     */
    public static function setMultiApp($multiapp): void
    {
        self::$multiApp = $multiapp;
    }
    /**
     * 获取 是否多应用
     * @return bool
     */
    public static function getMultiApp(): bool
    {
        return self::$multiApp;
    }
    /**
     * 设置 应用名称
     * @return void
     */
    public static function setAppName($app_name = ''): void
    {
        self::$appName = $app_name;
    }
    /**
     * 获取 应用名称
     * @return string
     */
    public static function getAppName(): string
    {
        return empty(self::$appName) ? '' : self::$appName;
    }
    /**
     * 设置 addons 路径
     * @return void
     */
    public static function setAddonsPath(): void
    {
        // 初始化插件目录
        $addons_path = app()->getRootPath() . 'addons' . DIRECTORY_SEPARATOR;
        // 如果插件目录不存在则创建
        if (!is_dir($addons_path)) {
            @mkdir($addons_path, 0755, true);
        }
        self::$addonsPath = $addons_path;
    }
    /**
     * 获取 addons 路径
     * @return string
     */
    public static function getAddonsPath(): string
    {
        if (empty(self::$addonsPath)) {
            self::setAddonsPath();
        }
        return self::$addonsPath;
    }

    /**
     * 设置插件路径
     * @return void
     */
    public static function setAddonPath($addon_name = ''): void
    {
        if (empty($addon_name)) {
            self::$addonPath = self::getAddonsPath() . self::getAddonName() . DIRECTORY_SEPARATOR;
        } else {
            self::$addonPath = self::getAddonsPath() . $addon_name . DIRECTORY_SEPARATOR;
        }
    }

    /**
     * 获取插件路径
     * @return string
     */
    public static function getAddonPath(): string
    {
        if (empty(self::$addonPath)) {
            self::setAddonPath();
        }
        return self::$addonPath;
    }
    /**
     * 设置插件名字
     * @param mixed $addon_name
     * @return void
     */
    public static function setAddonName($addon_name): void
    {
        self::$addonName = $addon_name;
    }
    /**
     * 获取插件名字
     */
    public static function getAddonName()
    {
        return self::$addonName;
    }
    public static function getAddonInfo()
    {
        $addon_list = self::getAddonList();
        return $addon_list[self::getAddonName()];
    }
    /**
     * 获取所有插件的数组
     */
    public static function getAddonList()
    {
        $cache_key = '__getAddonList__';
        $addon_list = Cache::get($cache_key);
        if (empty($addon_list)) {
            //配置
            $addons_path = scandir(self::getAddonsPath());
            $addon_arr = [];
            foreach ($addons_path as $name) {
                if (in_array($name, ['.', '..'])) {
                    continue;
                }
                $plugin_file = self::getAddonsPath() . $name . DIRECTORY_SEPARATOR . 'Plugin' . '.php';
                if (is_file($plugin_file)) {
                    $class = self::getAddonsPluginClass($name);
                    if (class_exists($class)) {
                        $classObject = new $class(app());
                        $addon_info = $classObject->getInfo();
                        $addon_arr[$addon_info['name']] = $addon_info;
                    }
                } else {
                    continue;
                }
            }
            $addon_list = $addon_arr;
            Cache::set($cache_key, $addon_list, random_int(1, 20));
        }
        return empty($addon_list) ? [] : $addon_list;
    }

    public function register()
    {
        if (self::init()) {
            //加载语言
            $this->loadLang();
            //挂载插件的自定义路由
            $this->loadRoutes();
            // 加载插件事件
            $this->loadEvent();
            // 加载插件系统服务
            $this->loadService();
            // 加载插件命令
            $this->loadCommand();
            //加载配置
            // $this->loadApp();
            $this->setApp();
            // 绑定插件容器
            $this->app->bind('addons', Service::class);
        }
    }
    public function boot()
    {
        if (self::$isAddonApp) {
            $this->registerRoutes(function (Route $route) {
                if (self::$addonPlugin !== false) {
                    $route->rule('addons/:addon/Plugin/[:action]', function () {
                        $request = $this->app->request;
                        $addon = $request->route('addon') ?? '';
                        $action = $request->route('action') ?? 'index';
                        return $this->executePlugin($addon, $action);
                    });
                    $route->rule('addons/:addon/plugin/[:action]', function () {
                        $request = $this->app->request;
                        $addon = $request->route('addon') ?? '';
                        $action = $request->route('action') ?? 'index';
                        return $this->executePlugin($addon, $action);
                    });
                } else {
                    // 路由脚本
                    $execute = '\\think\\addons\\Route::execute';
                    // 注册控制器路由
                    $route->rule('addons/:addon/[:module]/[:controller]/[:action]', $execute)
                        ->middleware(Addons::class);
                }
            });
        }
    }

    public function executePlugin($addon, $action)
    {
        $class = self::getAddonsPluginClass($addon);
        $instance = new $class($this->app);
        $call = [$instance, $action];
        $vars = [];
        if (is_callable($call)) {
            return call_user_func_array($call, $vars);
        }
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
    }
    private function loadLang()
    {
        // 加载系统语言包
        Lang::load([
            $this->app->getRootPath() . '/vendor/c517718004/drmking-addons/src/lang/zh-cn.php'
        ]);
        // 加载应用默认语言包
        $this->app->loadLangPack();
    }
    /**
     *  加载插件自定义路由文件
     */
    private function loadRoutes()
    {
        //配置
        $addons_dir = scandir(self::getAddonsPath());
        foreach ($addons_dir as $name) {
            if (in_array($name, ['.', '..'])) {
                continue;
            }
            if (!is_dir(self::getAddonsPath() . $name)) {
                continue;
            }
            $module_dir = self::getAddonsPath() . $name . DIRECTORY_SEPARATOR;
            foreach (scandir($module_dir) as $mdir) {
                if (in_array($mdir, ['.', '..'])) {
                    continue;
                }
                //路由配置文件
                if (is_file(self::getAddonsPath() . $name . DIRECTORY_SEPARATOR . $mdir)) {
                    continue;
                }
                $addons_route_dir = self::getAddonsPath() . $name . DIRECTORY_SEPARATOR . $mdir . DIRECTORY_SEPARATOR . 'route' . DIRECTORY_SEPARATOR;
                if (file_exists($addons_route_dir) && is_dir($addons_route_dir)) {
                    $files = glob($addons_route_dir . '*.php');
                    foreach ($files as $file) {
                        if (file_exists($file)) {
                            $this->loadRoutesFrom($file);
                            ;
                        }
                    }
                }
            }
        }
    }
    /**
     * 挂载插件服务
     */
    private function loadService()
    {
        $addons_dir = scandir(self::getAddonsPath());
        $bind = [];
        foreach ($addons_dir as $name) {
            if ($name === '.' or $name === '..') {
                continue;
            }
            if (is_file(self::getAddonsPath() . $name)) {
                continue;
            }
            $module_dir = self::getAddonsPath() . $name . DIRECTORY_SEPARATOR;
            if (!is_dir($module_dir)) {
                continue;
            }
            $service_file = $module_dir . 'service.php';
            if (is_file($service_file)) {
                $bind = array_merge($bind, include_once $service_file);
            }
        }
        if (!empty($bind)) {
            $this->app->bind($bind);
        }
    }
    /**
     * 加载插件命令
     */
    private function loadCommand()
    {
        $results = scandir(self::getAddonsPath());
        foreach ($results as $name) {
            if ($name === '.' or $name === '..') {
                continue;
            }
            if (is_file(self::getAddonsPath() . $name)) {
                continue;
            }
            $module_dir = self::getAddonsPath() . $name . DIRECTORY_SEPARATOR;
            if (!is_dir($module_dir)) {
                continue;
            }
            $command_file = $module_dir . 'command.php';
            if (is_file($command_file)) {
                $commands = include_once $command_file;
                if (is_array($commands)) {
                    $this->commands($commands);
                }
            }
        }
    }
    /**
     * 插件事件
     */
    private function loadEvent()
    {
        Event::trigger('AddonsInit', ['addon_name' => self::getAddonName(), 'addon_path' => self::getAddonPath(), 'app' => $this->app]);
    }

    /**
     * 设置应用
     */
    public function setApp(): void
    {
        if (self::getMultiApp()) {
            $app_name = self::getAddonName() . '-' . self::getAppName();
            $app_path = self::getAddonPath() . self::getAppName() . DIRECTORY_SEPARATOR;
        } else {
            $app_name = self::getAddonName();
            $app_path = self::getAddonPath();
        }
        $this->app->http->name($app_name);
        $this->app->setAppPath($app_path);
        if (is_dir($app_path)) {
            $this->app->setRuntimePath($this->app->getRuntimePath() . $app_name . DIRECTORY_SEPARATOR);
            //加载应用
            $this->loadApp();
        }
    }
    /**
     * 加载配置，路由，语言，中间件等
     */
    protected function loadApp()
    {
        $results = scandir(self::getAddonPath());
        foreach ($results as $childname) {
            if (in_array($childname, ['.', '..', 'public', 'view'])) {
                continue;
            }
            if (in_array($childname, ['vendor'])) {
                $autoload_file = self::getAddonPath() . $childname . DIRECTORY_SEPARATOR . 'autoload.php';
                if (file_exists($autoload_file)) {
                    require_once $autoload_file;
                }
            }
            // 中间件
            if (is_file(self::getAddonPath() . 'middleware.php')) {
                $this->app->middleware->import(include self::getAddonPath() . 'middleware.php', 'app');
            }
            if (is_file(self::getAddonPath() . 'common.php')) {
                include_once self::getAddonPath() . 'common.php';
            }
            if (is_file(self::getAddonPath() . 'provider.php')) {
                $this->app->bind(include self::getAddonPath() . 'provider.php');
            }
            //事件
            if (is_file(self::getAddonPath() . 'event.php')) {
                $this->app->loadEvent(include self::getAddonPath() . 'event.php');
            }
            if (self::getMultiApp()) {
                $module_dir = self::getAddonPath() . self::getAppName() . DIRECTORY_SEPARATOR;
                if (is_dir($module_dir)) {
                    foreach (scandir($module_dir) as $mdir) {
                        if (in_array($mdir, ['.', '..'])) {
                            continue;
                        }
                        if (is_file($module_dir . 'middleware.php')) {
                            $this->app->middleware->import(include $module_dir . 'middleware.php', 'app');
                        }
                        if (is_file($module_dir . 'common.php')) {
                            include_once $module_dir . 'common.php';
                        }
                        if (is_file($module_dir . 'provider.php')) {
                            $this->app->bind(include $module_dir . 'provider.php');
                        }
                        //事件
                        if (is_file($module_dir . 'event.php')) {
                            $this->app->loadEvent(include $module_dir . 'event.php');
                        }
                        $commands = [];
                        //配置文件
                        $app_config_dir = $module_dir . 'config' . DIRECTORY_SEPARATOR;
                        if (is_dir($app_config_dir)) {
                            $files = [];
                            $files = array_merge($files, glob($app_config_dir . '*' . $this->app->getConfigExt()));
                            if ($files) {
                                foreach ($files as $file) {
                                    if (file_exists($file)) {
                                        if (substr($file, -11) == 'console.php') {
                                            $commands_config = include_once $file;
                                            if (isset($commands_config['commands'])) {
                                                $commands = array_merge($commands, $commands_config['commands']);
                                            }
                                            if (!empty($commands)) {
                                                \think\Console::starting(function (\think\Console $console) use ($commands) {
                                                    $console->addCommands($commands);
                                                });
                                            }
                                        } else {
                                            $this->app->config->load($file, pathinfo($file, PATHINFO_FILENAME));
                                        }
                                    }
                                }
                            }
                        }
                        //语言文件
                        $app_lang_dir = $module_dir . 'lang' . DIRECTORY_SEPARATOR;
                        if (is_dir($app_lang_dir)) {
                            $files = glob($app_lang_dir . $this->app->lang->defaultLangSet() . '.php');
                            foreach ($files as $file) {
                                if (file_exists($file)) {
                                    Lang::load([$file]);
                                }
                            }
                        }
                    }
                }
            } else {
                $module_dir = self::getAddonPath();
                $commands = [];
                //配置文件
                $app_config_dir = $module_dir . 'config' . DIRECTORY_SEPARATOR;
                if (is_dir($app_config_dir)) {
                    $files = [];
                    $files = array_merge($files, glob($app_config_dir . '*' . $this->app->getConfigExt()));
                    if ($files) {
                        foreach ($files as $file) {
                            if (file_exists($file)) {
                                if (substr($file, -11) == 'console.php') {
                                    $commands_config = include_once $file;
                                    if (isset($commands_config['commands'])) {
                                        $commands = array_merge($commands, $commands_config['commands']);
                                    }
                                    if (!empty($commands)) {
                                        \think\Console::starting(function (\think\Console $console) use ($commands) {
                                            $console->addCommands($commands);
                                        });
                                    }
                                } else {
                                    $this->app->config->load($file, pathinfo($file, PATHINFO_FILENAME));
                                }
                            }
                        }
                    }
                }
                //语言文件
                $app_lang_dir = self::getAddonPath() . $childname . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR;
                if (is_dir($app_lang_dir)) {
                    $files = glob($app_lang_dir . $this->app->lang->defaultLangSet() . '.php');
                    foreach ($files as $file) {
                        if (file_exists($file)) {
                            Lang::load([$file]);
                        }
                    }
                }
            }
        }
    }
}
