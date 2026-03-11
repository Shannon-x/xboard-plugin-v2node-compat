<?php

namespace Plugin\V2nodeCompat\Controllers;

use App\Http\Controllers\PluginController;
use App\Models\Server;
use App\Utils\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ParagonIE\Sodium\Compat as SodiumCompat;

class V2nodeController extends PluginController
{
    /**
     * 创建/编辑节点
     * 接收 v2board V2node 格式参数，翻译后存入 Xboard v2_server 表
     */
    public function save(Request $request)
    {
        $params = $request->validate([
            'group_id'             => 'nullable|array',
            'route_id'             => 'nullable|array',
            'name'                 => 'required|string',
            'parent_id'            => 'nullable|integer',
            'host'                 => 'required',
            'listen_ip'            => 'nullable',
            'port'                 => 'required',
            'server_port'          => 'required',
            'protocol'             => 'required|in:shadowsocks,vmess,vless,trojan,tuic,hysteria2,anytls',
            'tls'                  => 'required|in:0,1,2',
            'tls_settings'         => 'nullable|array',
            'flow'                 => 'nullable|in:xtls-rprx-vision',
            'network'              => 'required|in:tcp,ws,grpc,http,httpupgrade,xhttp',
            'network_settings'     => 'nullable|array',
            'encryption'           => 'nullable',
            'encryption_settings'  => 'nullable|array',
            'disable_sni'          => 'required|in:0,1',
            'udp_relay_mode'       => 'nullable',
            'zero_rtt_handshake'   => 'required|in:0,1',
            'congestion_control'   => 'nullable',
            'cipher'               => 'nullable',
            'up_mbps'              => 'nullable|numeric',
            'down_mbps'            => 'nullable|numeric',
            'obfs'                 => 'nullable',
            'obfs_password'        => 'nullable',
            'padding_scheme'       => 'nullable',
            'tags'                 => 'nullable|array',
            'rate'                 => 'required|numeric',
            'show'                 => 'nullable|in:0,1',
            'sort'                 => 'nullable',
        ]);

        $this->autoFixParams($params, $request);
        $serverData = $this->translateToXboard($params);

        if ($request->input('id')) {
            $server = Server::find($request->input('id'));
            if (!$server) abort(500, '服务器不存在');
            try {
                $server->update($serverData);
            } catch (\Exception $e) {
                Log::error('[V2nodeCompat] save failed: ' . $e->getMessage());
                abort(500, '保存失败');
            }
            return response(['data' => true]);
        }

        if (!Server::create($serverData)) {
            abort(500, '创建失败');
        }
        return response(['data' => true]);
    }

    public function drop(Request $request)
    {
        $server = Server::find($request->input('id'));
        if (!$server) abort(500, '节点ID不存在');
        return response(['data' => $server->delete()]);
    }

    public function update(Request $request)
    {
        $params = $request->validate(['show' => 'nullable|in:0,1']);
        $server = Server::find($request->input('id'));
        if (!$server) abort(500, '该服务器不存在');
        try {
            $server->update($params);
        } catch (\Exception $e) {
            abort(500, '保存失败');
        }
        return response(['data' => true]);
    }

    public function copy(Request $request)
    {
        $server = Server::find($request->input('id'));
        if (!$server) abort(500, '服务器不存在');

        $newData = $server->toArray();
        unset($newData['id']);
        $newData['show'] = 0;
        $newData['code'] = null;

        if (!Server::create($newData)) abort(500, '复制失败');
        return response(['data' => true]);
    }

    // ═════════════════════════════════════════════════════════════
    //  V2board 参数自动修正（匹配 v2board V2nodeController 逻辑）
    // ═════════════════════════════════════════════════════════════

    private function autoFixParams(array &$params, Request $request): void
    {
        // anytls 强制 TLS
        if ($params['protocol'] === 'anytls' && (int) $params['tls'] === 0) {
            $params['tls'] = 1;
        }

        // hysteria2/trojan/tuic 强制 TLS
        if (in_array($params['protocol'], ['hysteria2', 'trojan', 'tuic'])) {
            $params['tls'] = 1;
        }

        // Reality 自动生成密钥对
        if ((int) $params['tls'] === 2) {
            $keyPair = SodiumCompat::crypto_box_keypair();
            $params['tls_settings'] = $params['tls_settings'] ?? [];
            if (!isset($params['tls_settings']['public_key'])) {
                $params['tls_settings']['public_key'] = Helper::base64EncodeUrlSafe(SodiumCompat::crypto_box_publickey($keyPair));
            }
            if (!isset($params['tls_settings']['private_key'])) {
                $params['tls_settings']['private_key'] = Helper::base64EncodeUrlSafe(SodiumCompat::crypto_box_secretkey($keyPair));
            }
            if (!isset($params['tls_settings']['short_id'])) {
                $params['tls_settings']['short_id'] = substr(sha1($params['tls_settings']['private_key']), 0, 8);
            }
            if (!isset($params['tls_settings']['server_port'])) {
                $params['tls_settings']['server_port'] = '443';
            }
        }

        // network_settings boolean 修正
        if (isset($params['network_settings']['acceptProxyProtocol'])) {
            $params['network_settings']['acceptProxyProtocol'] = filter_var(
                $params['network_settings']['acceptProxyProtocol'], FILTER_VALIDATE_BOOLEAN
            );
        }

        // 非 TCP + 非 mlkem 加密时清除 flow
        if ($params['network'] !== 'tcp' && (!isset($params['encryption']) || $params['encryption'] !== 'mlkem768x25519plus')) {
            $params['flow'] = null;
        }

        // xhttp extra 字段类型修正
        if ($params['network'] === 'xhttp' && isset($params['network_settings']['extra']) && is_array($params['network_settings']['extra'])) {
            $extra = &$params['network_settings']['extra'];
            foreach (['noGRPCHeader', 'noSSEHeader'] as $boolKey) {
                if (isset($extra[$boolKey])) $extra[$boolKey] = filter_var($extra[$boolKey], FILTER_VALIDATE_BOOLEAN);
            }
            if (isset($extra['scMaxBufferedPosts'])) $extra['scMaxBufferedPosts'] = (int) $extra['scMaxBufferedPosts'];
            if (isset($extra['xmux']['hKeepAlivePeriod'])) $extra['xmux']['hKeepAlivePeriod'] = (int) $extra['xmux']['hKeepAlivePeriod'];
            if (isset($extra['downloadSettings']['port'])) $extra['downloadSettings']['port'] = (int) $extra['downloadSettings']['port'];
        }

        // mlkem768x25519plus 加密密钥自动生成
        if (isset($params['encryption']) && $params['encryption'] === 'mlkem768x25519plus') {
            $keyPair = SodiumCompat::crypto_box_keypair();
            $params['encryption_settings'] = $params['encryption_settings'] ?? [];
            if (!isset($params['encryption_settings']['mode'])) $params['encryption_settings']['mode'] = 'native';
            if (isset($params['encryption_settings']['rtt']) && $params['encryption_settings']['rtt'] === '1rtt') {
                $params['encryption_settings']['ticket'] = '0s';
            } elseif (!isset($params['encryption_settings']['rtt'])) {
                $params['encryption_settings']['rtt'] = '0rtt';
                $params['encryption_settings']['ticket'] = '600s';
            }
            if (!isset($params['encryption_settings']['private_key'])) {
                $params['encryption_settings']['private_key'] = Helper::base64EncodeUrlSafe(SodiumCompat::crypto_box_secretkey($keyPair));
            }
            if (!isset($params['encryption_settings']['password'])) {
                $params['encryption_settings']['password'] = Helper::base64EncodeUrlSafe(SodiumCompat::crypto_box_publickey($keyPair));
            }
        }

        // padding_scheme JSON 解码
        if (isset($params['padding_scheme']) && is_string($params['padding_scheme'])) {
            $params['padding_scheme'] = json_decode($params['padding_scheme'], true);
        }

        // 默认值
        if (!isset($params['up_mbps']))   $params['up_mbps'] = 0;
        if (!isset($params['down_mbps'])) $params['down_mbps'] = 0;

        // obfs 密码自动生成
        if (isset($params['obfs']) && $params['obfs']) {
            if (!isset($params['obfs_password'])) {
                $params['obfs_password'] = Helper::getServerKey($request->input('created_at'), 16);
            }
        } else {
            $params['obfs_password'] = null;
        }

        // shadowsocks 默认 cipher
        if ($params['protocol'] === 'shadowsocks' && !isset($params['cipher'])) {
            $params['cipher'] = 'aes-128-gcm';
        }
    }

    // ═════════════════════════════════════════════════════════════
    //  V2board V2node 格式 → Xboard Server 格式
    // ═════════════════════════════════════════════════════════════

    private function translateToXboard(array $v2node): array
    {
        $protocol = $v2node['protocol'] === 'hysteria2' ? 'hysteria' : $v2node['protocol'];
        $protocolSettings = $this->buildProtocolSettings($v2node);

        return [
            'type'              => $protocol,
            'name'              => $v2node['name'],
            'host'              => $v2node['host'],
            'port'              => $v2node['port'],
            'server_port'       => $v2node['server_port'],
            'rate'              => $v2node['rate'],
            'show'              => $v2node['show'] ?? 0,
            'sort'              => $v2node['sort'] ?? null,
            'tags'              => $v2node['tags'] ?? [],
            'parent_id'         => $v2node['parent_id'] ?? null,
            'group_ids'         => $v2node['group_id'] ?? [],
            'route_ids'         => $v2node['route_id'] ?? [],
            'protocol_settings' => $protocolSettings,
        ];
    }

    private function buildProtocolSettings(array $v2node): array
    {
        $protocol = $v2node['protocol'];
        $tls = (int) $v2node['tls'];
        $settings = [];

        switch ($protocol) {
            case 'vless':
                $settings['tls'] = $tls;
                $settings['network'] = $v2node['network'];
                $settings['network_settings'] = $v2node['network_settings'] ?? null;
                $settings['flow'] = $v2node['flow'] ?? null;
                if ($tls === 2) {
                    $settings['reality_settings'] = $v2node['tls_settings'] ?? [];
                } else {
                    $settings['tls_settings'] = $v2node['tls_settings'] ?? null;
                }
                break;

            case 'vmess':
                $settings['tls'] = $tls;
                $settings['network'] = $v2node['network'];
                $settings['network_settings'] = $v2node['network_settings'] ?? null;
                $settings['tls_settings'] = $v2node['tls_settings'] ?? null;
                break;

            case 'trojan':
                $settings['network'] = $v2node['network'];
                $settings['network_settings'] = $v2node['network_settings'] ?? null;
                $settings['server_name'] = $v2node['tls_settings']['server_name'] ?? null;
                $settings['allow_insecure'] = $v2node['tls_settings']['allow_insecure'] ?? false;
                break;

            case 'shadowsocks':
                $settings['cipher'] = $v2node['cipher'] ?? 'aes-128-gcm';
                if (isset($v2node['obfs']) && $v2node['obfs']) {
                    $settings['obfs'] = $v2node['obfs'];
                    $settings['obfs_settings'] = [
                        'host' => $v2node['network_settings']['host'] ?? '',
                        'path' => $v2node['network_settings']['path'] ?? '',
                    ];
                }
                break;

            case 'hysteria2':
            case 'hysteria':
                $settings['version'] = 2;
                $settings['tls'] = [
                    'server_name' => $v2node['tls_settings']['server_name'] ?? null,
                    'allow_insecure' => $v2node['tls_settings']['allow_insecure'] ?? false,
                ];
                $settings['bandwidth'] = [
                    'up' => (int) ($v2node['up_mbps'] ?? 0),
                    'down' => (int) ($v2node['down_mbps'] ?? 0),
                ];
                if (isset($v2node['obfs']) && $v2node['obfs']) {
                    $settings['obfs'] = [
                        'open' => true,
                        'type' => $v2node['obfs'],
                        'password' => $v2node['obfs_password'] ?? '',
                    ];
                }
                break;

            case 'tuic':
                $settings['version'] = 5;
                $settings['congestion_control'] = $v2node['congestion_control'] ?? 'cubic';
                $settings['udp_relay_mode'] = $v2node['udp_relay_mode'] ?? 'native';
                $settings['tls'] = [
                    'server_name' => $v2node['tls_settings']['server_name'] ?? null,
                    'allow_insecure' => $v2node['tls_settings']['allow_insecure'] ?? false,
                ];
                break;

            case 'anytls':
                $settings['tls'] = [
                    'server_name' => $v2node['tls_settings']['server_name'] ?? null,
                    'allow_insecure' => $v2node['tls_settings']['allow_insecure'] ?? false,
                ];
                $settings['padding_scheme'] = $v2node['padding_scheme'] ?? null;
                break;
        }

        // 通用扩展字段存入 protocol_settings
        if (isset($v2node['listen_ip']) && $v2node['listen_ip']) {
            $settings['listen_ip'] = $v2node['listen_ip'];
        }
        if (isset($v2node['disable_sni'])) {
            $settings['disable_sni'] = (int) $v2node['disable_sni'];
        }
        if (isset($v2node['zero_rtt_handshake'])) {
            $settings['zero_rtt_handshake'] = (int) $v2node['zero_rtt_handshake'];
        }
        if (isset($v2node['encryption']) && $v2node['encryption']) {
            $settings['encryption'] = $v2node['encryption'];
            $settings['encryption_settings'] = $v2node['encryption_settings'] ?? null;
        }

        return $settings;
    }

    // ═════════════════════════════════════════════════════════════
    //  Xboard Server 格式 → V2board V2node 格式（静态方法供外部使用）
    // ═════════════════════════════════════════════════════════════

    public static function translateToV2node(array $server, ?string $installScriptUrl = null): array
    {
        $ps = $server['protocol_settings'] ?? [];
        $type = $server['type'] ?? '';

        $v2node = [
            'id'                  => $server['id'] ?? null,
            'type'                => 'v2node',
            'protocol'            => $type === 'hysteria' ? 'hysteria2' : $type,
            'name'                => $server['name'] ?? '',
            'host'                => $server['host'] ?? '',
            'port'                => $server['port'] ?? '',
            'server_port'         => $server['server_port'] ?? '',
            'rate'                => $server['rate'] ?? 1,
            'show'                => $server['show'] ?? 0,
            'sort'                => $server['sort'] ?? 0,
            'tags'                => $server['tags'] ?? [],
            'parent_id'           => $server['parent_id'] ?? null,
            'group_id'            => $server['group_ids'] ?? $server['group_id'] ?? [],
            'route_id'            => $server['route_ids'] ?? $server['route_id'] ?? [],
            'created_at'          => $server['created_at'] ?? null,
            'updated_at'          => $server['updated_at'] ?? null,
            'listen_ip'           => $ps['listen_ip'] ?? null,
            'disable_sni'         => $ps['disable_sni'] ?? 0,
            'zero_rtt_handshake'  => $ps['zero_rtt_handshake'] ?? 0,
            'encryption'          => $ps['encryption'] ?? null,
            'encryption_settings' => $ps['encryption_settings'] ?? null,
        ];

        // 按协议提取 TLS/网络/协议特定字段
        switch ($type) {
            case 'vless':
                $tls = (int) ($ps['tls'] ?? 0);
                $v2node['tls'] = $tls;
                $v2node['network'] = $ps['network'] ?? 'tcp';
                $v2node['network_settings'] = $ps['network_settings'] ?? null;
                $v2node['flow'] = $ps['flow'] ?? null;
                $v2node['tls_settings'] = $tls === 2
                    ? ($ps['reality_settings'] ?? [])
                    : ($ps['tls_settings'] ?? []);
                break;

            case 'vmess':
                $v2node['tls'] = (int) ($ps['tls'] ?? 0);
                $v2node['network'] = $ps['network'] ?? 'tcp';
                $v2node['network_settings'] = $ps['network_settings'] ?? null;
                $v2node['tls_settings'] = $ps['tls_settings'] ?? [];
                break;

            case 'trojan':
                $v2node['tls'] = 1;
                $v2node['network'] = $ps['network'] ?? 'tcp';
                $v2node['network_settings'] = $ps['network_settings'] ?? null;
                $v2node['tls_settings'] = [
                    'server_name'    => $ps['server_name'] ?? null,
                    'allow_insecure' => $ps['allow_insecure'] ?? false,
                ];
                break;

            case 'shadowsocks':
                $v2node['tls'] = 0;
                $v2node['network'] = 'tcp';
                $v2node['cipher'] = $ps['cipher'] ?? 'aes-128-gcm';
                $v2node['obfs'] = $ps['obfs'] ?? null;
                $v2node['network_settings'] = $ps['obfs_settings'] ?? null;
                $v2node['tls_settings'] = [];
                break;

            case 'hysteria':
                $v2node['tls'] = 1;
                $v2node['network'] = 'tcp';
                $tlsBlock = $ps['tls'] ?? [];
                $v2node['tls_settings'] = is_array($tlsBlock) ? $tlsBlock : [];
                $bw = $ps['bandwidth'] ?? [];
                $v2node['up_mbps']   = $bw['up'] ?? 0;
                $v2node['down_mbps'] = $bw['down'] ?? 0;
                $obfsBlock = $ps['obfs'] ?? [];
                $v2node['obfs']          = ($obfsBlock['open'] ?? false) ? ($obfsBlock['type'] ?? 'salamander') : null;
                $v2node['obfs_password'] = $obfsBlock['password'] ?? null;
                break;

            case 'tuic':
                $v2node['tls'] = 1;
                $v2node['network'] = 'tcp';
                $tlsBlock = $ps['tls'] ?? [];
                $v2node['tls_settings'] = is_array($tlsBlock) ? $tlsBlock : [];
                $v2node['congestion_control'] = $ps['congestion_control'] ?? 'cubic';
                $v2node['udp_relay_mode']     = $ps['udp_relay_mode'] ?? 'native';
                break;

            case 'anytls':
                $v2node['tls'] = 1;
                $v2node['network'] = 'tcp';
                $tlsBlock = $ps['tls'] ?? [];
                $v2node['tls_settings'] = is_array($tlsBlock) ? $tlsBlock : [];
                $paddingScheme = $ps['padding_scheme'] ?? null;
                $v2node['padding_scheme'] = $paddingScheme ? json_encode($paddingScheme) : null;
                break;

            default:
                $v2node['tls'] = 0;
                $v2node['network'] = 'tcp';
                $v2node['tls_settings'] = [];
                break;
        }

        // 生成一键安装命令
        $apiHost = admin_setting('app_url', config('app.url', ''));
        $apiKey  = admin_setting('server_token', '');
        $scriptUrl = $installScriptUrl ?: 'https://raw.githubusercontent.com/wyx2685/v2node/master/script/install.sh';

        $v2node['install_command'] = "wget -N {$scriptUrl} && bash install.sh --api-host {$apiHost} --node-id {$v2node['id']} --api-key {$apiKey}";

        return $v2node;
    }
}
