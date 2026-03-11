<?php

use Illuminate\Support\Facades\Route;
use Plugin\V2nodeCompat\Controllers\InstallCommandController;

/*
|--------------------------------------------------------------------------
| V2Node 安装助手路由
|--------------------------------------------------------------------------
|
| 1. 覆盖 getNodes 接口，在每个节点上追加 install_command 字段
| 2. 提供单独的安装命令查询接口
|
| V2Node 后端程序和 Xboard 的通信走的是 Xboard 原生 UniProxy API：
|   GET  /api/v1/server/UniProxy/config   (节点拉取自身配置)
|   GET  /api/v1/server/UniProxy/user     (节点拉取用户列表)
|   POST /api/v1/server/UniProxy/push     (节点上报流量数据)
|   POST /api/v1/server/UniProxy/alive    (节点上报在线状态)
|
| 这些接口已经由 Xboard 核心提供，本插件不需要也不应该干预。
| 本插件仅在管理后台层面增加"安装命令"展示功能。
|
*/

$adminPath = admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key'))));

Route::prefix("api/v2/{$adminPath}")->middleware(['admin', 'log'])->group(function () {

    // 覆盖核心 getNodes，追加 install_command 字段
    Route::get('server/manage/getNodes', [InstallCommandController::class, 'getNodesWithInstall']);

    // 安装命令专用接口
    Route::get('server/v2node/install-command',  [InstallCommandController::class, 'getInstallCommand']);
    Route::get('server/v2node/install-commands',  [InstallCommandController::class, 'getAllInstallCommands']);
});
