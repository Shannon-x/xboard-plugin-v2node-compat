<?php

namespace Plugin\V2nodeCompat\Controllers;

use App\Http\Controllers\PluginController;
use App\Models\Server;
use Illuminate\Http\Request;
use Plugin\V2nodeCompat\Plugin;

class InstallCommandController extends PluginController
{
    /**
     * 获取单个节点的安装命令
     * GET /api/v2/{admin}/server/v2node/install-command?id=1
     */
    public function getInstallCommand(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        $server = Server::find($request->input('id'));
        if (!$server) abort(500, '节点不存在');

        return response()->json([
            'data' => [
                'id'              => $server->id,
                'name'            => $server->name,
                'type'            => $server->type,
                'install_command' => Plugin::generateInstallCommand($server->id, $server->code),
            ]
        ]);
    }

    /**
     * 获取所有节点的安装命令列表
     * GET /api/v2/{admin}/server/v2node/install-commands
     */
    public function getAllInstallCommands(Request $request)
    {
        $servers = Server::orderBy('sort', 'ASC')->get();
        $result = $servers->map(function ($server) {
            return [
                'id'              => $server->id,
                'name'            => $server->name,
                'type'            => $server->type,
                'host'            => $server->host,
                'port'            => $server->port,
                'code'            => $server->code,
                'show'            => $server->show,
                'install_command' => Plugin::generateInstallCommand($server->id, $server->code),
            ];
        });

        return response()->json(['data' => $result]);
    }

    /**
     * 覆盖 getNodes，在返回结果中追加 install_command 字段
     * GET /api/v2/{admin}/server/manage/getNodes
     */
    public function getNodesWithInstall(Request $request)
    {
        $servers = \App\Services\ServerService::getAllServers()->map(function ($item) {
            $item['groups'] = \App\Models\ServerGroup::whereIn('id', $item['group_ids'])->get(['name', 'id']);
            $item['parent'] = $item->parent;
            $item['install_command'] = Plugin::generateInstallCommand($item->id, $item->code);
            return $item;
        });

        return response()->json(['data' => $servers]);
    }
}
