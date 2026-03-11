# V2Node 兼容插件 (V2nodeCompat)

为 [Xboard](https://github.com/cedar2025/Xboard) 增加 **V2Node 万能节点管理** 兼容 API，完全兼容 [wyx2685/v2board](https://github.com/wyx2685/v2board) 前端的节点创建/编辑界面。

## 功能特性

- ✅ 兼容 v2board 前端的 V2Node 节点管理 API
- ✅ 支持所有协议：Shadowsocks、VMess、VLess、Trojan、TUIC、Hysteria2、AnyTLS
- ✅ 支持所有传输层：TCP、WebSocket、gRPC、HTTP、HTTP Upgrade、XHTTP
- ✅ 支持所有安全层：无TLS、TLS、Reality（自动生成密钥对）
- ✅ 自动生成 V2bX 一键安装命令
- ✅ 数据存储在 Xboard 原生 `v2_server` 表，不创建额外表
- ✅ v2board 格式与 Xboard 格式自动双向翻译

## 工作原理

```
v2board 前端
     ↓
POST /api/v1/{admin}/server/v2node/save
     ↓
V2nodeController（格式翻译层）
     ↓
┌─ protocol=vless → type=vless + protocol_settings={tls,network,reality_settings,...}
├─ protocol=trojan → type=trojan + protocol_settings={network,server_name,...}
├─ protocol=hysteria2 → type=hysteria + protocol_settings={version:2,bandwidth,...}
└─ ... 所有协议都支持
     ↓
Xboard v2_server 表（统一存储）
```

**关键翻译逻辑：**

| v2board V2node 字段 | Xboard Server 字段 |
|---------------------|-------------------|
| `protocol` | `type` |
| `group_id` (array) | `group_ids` (array) |
| `route_id` (array) | `route_ids` (array) |
| `tls` (0/1/2) | `protocol_settings.tls` |
| `tls_settings` | `protocol_settings.tls_settings` 或 `reality_settings` |
| `network` | `protocol_settings.network` |
| `network_settings` | `protocol_settings.network_settings` |
| `flow` | `protocol_settings.flow` |
| `cipher` | `protocol_settings.cipher` |
| `obfs`/`obfs_password` | `protocol_settings.obfs` |
| `up_mbps`/`down_mbps` | `protocol_settings.bandwidth` |

## 安装

```bash
cd /path/to/xboard
git clone https://github.com/Shannon-x/xboard-plugin-v2node-compat.git plugins/V2nodeCompat
```

然后在管理后台 → **插件管理** → 安装 → 启用。

## 注册的 API 路由

| 路径 | 方法 | 说明 |
|------|------|------|
| `/api/v1/{admin}/server/v2node/save` | POST | 创建/编辑节点 |
| `/api/v1/{admin}/server/v2node/drop` | POST | 删除节点 |
| `/api/v1/{admin}/server/v2node/update` | POST | 更新显隐 |
| `/api/v1/{admin}/server/v2node/copy` | POST | 复制节点 |
| `/api/v1/{admin}/server/manage/getNodes` | GET | 节点列表（含安装命令） |
| `/api/v1/{admin}/server/manage/sort` | POST | 节点排序 |

## 一键安装命令

每个节点自动生成 V2bX 安装命令：

```bash
wget -N https://raw.githubusercontent.com/wyx2685/v2node/master/script/install.sh && bash install.sh --api-host https://your-domain.com --node-id 1 --api-key your-server-token
```

- `api-host`：自动读取 Xboard 的 `app_url` 设置
- `node-id`：当前节点 ID
- `api-key`：自动读取 Xboard 的 `server_token` 设置
- 安装脚本地址可在插件配置中自定义

## 协议支持详情

### VLess
- TLS 模式：无 / TLS / Reality
- 传输层：TCP / WS / gRPC / HTTP / HTTP Upgrade / XHTTP
- Flow：xtls-rprx-vision
- 加密层：mlkem768x25519plus（自动生成密钥）

### VMess
- TLS 模式：无 / TLS
- 传输层：TCP / WS / gRPC / HTTP / HTTP Upgrade / XHTTP

### Trojan
- TLS：强制启用
- 传输层：TCP / WS / gRPC

### Hysteria2
- TLS：强制启用
- 带宽限制（上行/下行 Mbps）
- 混淆（Salamander）

### TUIC
- TLS：强制启用
- 拥塞控制：cubic / bbr / new_reno
- UDP 中继模式：native / quic

### AnyTLS
- TLS：强制启用
- Padding Scheme 配置

### Shadowsocks
- 加密算法选择
- 混淆支持

## 系统要求

- Xboard >= 1.0.0
- PHP >= 8.2（需要 sodium 扩展）

## 开源协议

MIT License
