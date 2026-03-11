<?php

namespace Plugin\V2nodeCompat\Controllers;

use App\Http\Controllers\PluginController;
use App\Models\Server;
use App\Models\ServerGroup;
use App\Services\Plugin\PluginConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompatManageController extends PluginController
{
    /**
     * 返回 v2board 兼容格式的节点列表
     * 所有 Xboard 节点都翻译为 V2node 格式返回，包含 install_command
     */
    public function getNodes(Request $request)
    {
        $scriptUrl = $this->loadInstallScriptUrl();

        $servers = Server::orderBy('sort', 'ASC')->get();

        $result = $servers->map(function ($server) use ($scriptUrl) {
            $arr = $server->toArray();

            // 补充运行时状态
            $typeKey = strtoupper($server->type);
            $checkId = $server->parent_id ?? $server->id;
            $arr['online']          = Cache::get("SERVER_{$typeKey}_ONLINE_USER:{$checkId}");
            $arr['last_check_at']   = Cache::get("SERVER_{$typeKey}_LAST_CHECK_AT:{$checkId}");
            $arr['last_push_at']    = Cache::get("SERVER_{$typeKey}_LAST_PUSH_AT:{$checkId}");

            $lastCheck = $arr['last_check_at'] ?? 0;
            $lastPush  = $arr['last_push_at'] ?? 0;
            if ((time() - 300) >= $lastCheck) {
                $arr['available_status'] = 0;
            } elseif ((time() - 300) >= $lastPush) {
                $arr['available_status'] = 1;
            } else {
                $arr['available_status'] = 2;
            }

            // 补充权限组信息
            $groupIds = $server->group_ids ?? [];
            $arr['groups'] = $groupIds
                ? ServerGroup::whereIn('id', $groupIds)->get(['name', 'id'])->toArray()
                : [];

            // 翻译为 V2node 格式
            return V2nodeController::translateToV2node($arr, $scriptUrl);
        })->values()->all();

        return response(['data' => $result]);
    }

    /**
     * v2board 格式排序
     * v2board 发送 { "v2node": { "id1": sort1, "id2": sort2 } }
     * Xboard 需要转换为对 v2_server 表的 sort 更新
     */
    public function sort(Request $request)
    {
        $allParams = $request->all();

        DB::beginTransaction();
        try {
            foreach ($allParams as $type => $sortMap) {
                if (!is_array($sortMap)) continue;
                foreach ($sortMap as $id => $sortValue) {
                    $server = Server::find($id);
                    if ($server) {
                        $server->update(['sort' => (int) $sortValue]);
                    }
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[V2nodeCompat] sort failed: ' . $e->getMessage());
            abort(500, '保存失败');
        }

        return response(['data' => true]);
    }

    private function loadInstallScriptUrl(): string
    {
        try {
            $configService = app(PluginConfigService::class);
            $dbConfig = $configService->getDbConfig('v2node_compat');
            return $dbConfig['install_script_url']
                ?? 'https://raw.githubusercontent.com/wyx2685/v2node/master/script/install.sh';
        } catch (\Exception $e) {
            return 'https://raw.githubusercontent.com/wyx2685/v2node/master/script/install.sh';
        }
    }
}
