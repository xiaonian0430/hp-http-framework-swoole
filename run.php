<?php
/**
 * 启动文件
 * @author: Xiao Nian
 * @contact: xiaonian030@163.com
 * @datetime: 2021-09-14 10:00
 */
use Swoole\Http\Server as HttpServer;
use Swoole\Http\Request;
use Swoole\Http\Response;

//初始化
ini_set('display_errors', 'on');
defined('IN_PHAR') or define('IN_PHAR', boolval(\Phar::running(false)));
defined('SERVER_ROOT') or define('SERVER_ROOT', IN_PHAR ? \Phar::running() : realpath(getcwd()));
defined('PUBLIC_ROOT') or define('PUBLIC_ROOT', SERVER_ROOT.'/public'); //不能加后缀

//创建临时目录
$temp_path=SERVER_ROOT.'/temp';
$log_path=SERVER_ROOT.'/temp/log';
if(!is_dir($log_path)){
    mkdir($log_path, 0777, true);
}
defined('TEMP_ROOT') or define('TEMP_ROOT', $temp_path);
defined('LOG_ROOT') or define('LOG_ROOT', $log_path);

// 检查扩展或环境
if(strpos(strtolower(PHP_OS), 'win') === 0) {
    exit("run.php not support windows.\n");
}

//导入配置文件
$mode='produce';
foreach ($argv as $item){
    $item_val=explode('=', $item);
    if(count($item_val)==2 && $item_val[0]=='-mode'){
        $mode=$item_val[1];
    }
}
$config_path=SERVER_ROOT . '/config/'.$mode.'.php';
if (file_exists($config_path)) {
    $conf = require_once $config_path;
}else{
    exit($config_path." is not exist\n");
}
defined('CONFIG') or define('CONFIG', $conf);

//自动加载文件
require_once SERVER_ROOT . '/core/autoload.php';

//初始化服务
$http = new HttpServer(CONFIG['HTTP_FRAMEWORK']['LISTEN_ADDRESS'], CONFIG['HTTP_FRAMEWORK']['PORT']);

// 接收请求
$http->on('Request', function (Request $request, Response $response) {
    //路由分发: 模块=module 类=class 方法=function
    $path=trim($request->server['request_uri'],'/');
    $dot=strpos($path, '.');
    if($dot===false){
        $paths=explode('/',$path);
        if(isset($paths[0])){
            $module=ucwords($paths[0]);
        }else{
            $module='Index';
        }
        if(isset($paths[1])){
            $class_name=ucwords($paths[1]);
        }else{
            $class_name='Index';
        }
        $function = $paths[2] ?? 'index';
        $class='App\HttpController\\'.$module.'\\'.$class_name;
        if(class_exists($class)){
            $instance=new $class($response, $request);
            if(method_exists($instance, $function)){
                $instance->$function();
            }else{
                $instance=new App\HttpController\Controller($response, $request);
                $instance->writeJsonNoFound();
            }
        }else{
            $instance=new App\HttpController\Controller($response, $request);
            $instance->writeJsonNoFound();
        }
    }else{
        $instance=new App\HttpController\Controller($response, $request);
        $instance->writeFile($path);
    }
});

// 启动服务
$http->start();