<?php

// 创建容器, 并设置 basePath
$api = new Mine\Application(realpath(__DIR__.'/../../../../'));

// 注册门面（ 使用 MineDB 代替 DB ）
$api->withFacades();
// 使用ORM
$api->withEloquent();


echo $api->basePath();




// 启动路由
//$api::run();