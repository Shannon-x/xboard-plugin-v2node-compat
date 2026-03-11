<?php

namespace Plugin\V2nodeCompat;

use App\Models\Plugin as PluginModel;
use App\Services\Plugin\AbstractPlugin;

class Plugin extends AbstractPlugin
{
    public function boot(): void
    {
        // 向前端配置注入安装命令生成所需的参数
        $this->filter('guest_comm_config', function ($config) {
            $config['v2node_install_enabled'] = true;
            return $config;
        });
    }

    /**
     * 为指定节点生成安装命令
     */
    public static function generateInstallCommand(int $nodeId, ?string $nodeCode = null): string
    {
        $config = self::loadConfig();

        $apiHost   = $config['custom_api_host'] ?: admin_setting('app_url', config('app.url', ''));
        $apiKey    = admin_setting('server_token', '');
        $scriptUrl = $config['install_script_url']
            ?: 'https://raw.githubusercontent.com/wyx2685/v2node/master/script/install.sh';

        $nodeIdentifier = $nodeCode ?: $nodeId;

        return "wget -N {$scriptUrl} && bash install.sh --api-host {$apiHost} --node-id {$nodeIdentifier} --api-key {$apiKey}";
    }

    /**
     * 从数据库读取插件配置
     */
    private static function loadConfig(): array
    {
        $plugin = PluginModel::where('code', 'v2node_compat')->first();
        if (!$plugin || empty($plugin->config)) {
            return ['install_script_url' => '', 'custom_api_host' => ''];
        }
        $config = is_string($plugin->config) ? json_decode($plugin->config, true) : $plugin->config;
        return $config ?: ['install_script_url' => '', 'custom_api_host' => ''];
    }
}
