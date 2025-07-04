<?php
/*
 * @Author: 常立超
 * @Date: 2025-07-03 21:15:26
 * @LastEditors: 常立超
 * @LastEditTime: 2025-07-04 15:30:59
 */
namespace think\addons\command;
use think\console\Command;
use think\console\Input;
use think\console\Output;
class SendConfig extends Command
{
    public function configure()
    {
        $this->setName('addons:config')
            ->setDescription('send config to config folder');
    }
    public function execute(Input $input, Output $output)
    {
        //获取默认配置文件
        $content = file_get_contents(root_path() . 'vendor/c517718004/drmking-addons/src/config.php');
        $configPath = config_path() . '/';
        $configFile = $configPath . 'addons.php';
        //判断目录是否存在
        if (!file_exists($configPath)) {
            mkdir($configPath, 0755, true);
        }
        //判断文件是否存在
        if (is_file($configFile)) {
            throw new \InvalidArgumentException(sprintf('The config file "%s" already exists', $configFile));
        }
        if (false === file_put_contents($configFile, $content)) {
            throw new \RuntimeException(sprintf('The config file "%s" could not be written to "%s"', $configFile, $configPath));
        }
        $output->writeln('create addons config ok');
    }
}