<?php
/*
 * @Author: 常立超
 * @Date: 2025-07-01 11:53:20
 * @LastEditors: 常立超
 * @LastEditTime: 2025-07-04 15:20:59
 */
declare(strict_types=1);
namespace think\addons\middleware;
use think\App;
use think\facade\Event;
class Addons
{
    protected $app;
    public function __construct(App $app)
    {
        $this->app  = $app;
    }
    /**
     * 插件中间件
     * @param $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        Event::trigger('addon_middleware', $request);
        return $next($request);
    }
}