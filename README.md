# V2Node 安装助手插件

为 [Xboard](https://github.com/cedar2025/Xboard) 管理后台增加 **V2Node/V2bX 一键安装命令生成** 功能。

## 它解决什么问题

Xboard 本身已经完整支持 V2Node 后端程序的通信（UniProxy API），节点创建也已经支持所有协议。**唯一缺少的是**：在后台创建节点后，管理员需要手动拼接安装命令来部署 V2Node 后端。

本插件自动为每个节点生成安装命令，直接在节点列表中展示，一键复制即可在服务器上部署。

## Xboard 与 V2Node 的通信架构

```
┌──────────────────────────┐         ┌──────────────────────────┐
│     Xboard (面板端)       │         │   V2Node (节点端)         │
│                          │         │                          │
│  v2_server 表 (所有节点)  │◄───────►│  定时拉取配置和用户列表    │
│                          │  API    │  上报流量和在线数据        │
│  UniProxy API:           │         │                          │
│  GET  /config  ──────────┼────────►│  获取自身协议配置          │
│  GET  /user    ──────────┼────────►│  获取可用用户列表          │
│  POST /push    ◄─────────┼─────────│  上报用户流量数据          │
│  POST /alive   ◄─────────┼─────────│  上报在线用户              │
│  POST /status  ◄─────────┼─────────│  上报节点负载状态          │
└──────────────────────────┘         └──────────────────────────┘
```

**这些通信接口已经由 Xboard 核心提供**（`/api/v1/server/UniProxy/*`），本插件不需要干预。

V2Node 后端连接时需要 3 个参数：
- `--api-host`：Xboard 面板地址
- `--node-id`：节点 ID（对应 v2_server 表的 id 或 code）
- `--api-key`：通讯密钥（Xboard 系统配置中的 `server_token`）

## 安装

```bash
cd /path/to/xboard
git clone https://github.com/Shannon-x/xboard-plugin-v2node-compat.git plugins/V2nodeCompat
```

管理后台 → 插件管理 → 安装 → 启用。

## 使用

### 1. 在 Xboard 后台创建节点

正常使用 Xboard 后台的"节点管理"创建节点。支持的协议：

| 协议 | TLS 模式 | 传输层 |
|------|---------|--------|
| VLess | 无/TLS/Reality | TCP/WS/gRPC/HTTP/HTTPUpgrade/XHTTP |
| VMess | 无/TLS | TCP/WS/gRPC/HTTP/HTTPUpgrade/XHTTP |
| Trojan | TLS | TCP/WS/gRPC |
| Hysteria2 | TLS | QUIC |
| TUIC | TLS | QUIC |
| AnyTLS | TLS | TCP |
| Shadowsocks | 无 | TCP |

### 2. 获取安装命令

节点创建后，在节点列表中每个节点会自动显示 `install_command` 字段。

也可以通过 API 获取：

```bash
# 获取单个节点安装命令
GET /api/v2/{admin_path}/server/v2node/install-command?id=1

# 获取所有节点安装命令
GET /api/v2/{admin_path}/server/v2node/install-commands
```

### 3. 在服务器上执行安装命令

```bash
wget -N https://raw.githubusercontent.com/wyx2685/v2node/master/script/install.sh && bash install.sh --api-host https://your-panel.com --node-id 1 --api-key your-server-token
```

安装完成后 V2Node 会自动：
- 从 Xboard 拉取节点配置（协议、端口、TLS 等）
- 拉取可用用户列表
- 启动代理服务
- 定时上报流量和在线数据

### 4. 确认通信正常

在 Xboard 后台节点列表中检查：
- **在线状态**：显示"在线"
- **最后检查时间**：有更新
- **在线用户数**：正常显示

## 配置

| 配置项 | 说明 | 默认值 |
|--------|------|--------|
| 安装脚本地址 | V2Node 安装脚本的 URL | GitHub 官方地址 |
| 自定义 API 地址 | 留空则用系统 app_url | 空 |

## 系统要求

- Xboard >= 1.0.0
- PHP >= 8.2
- 系统配置中已设置 `server_token`（通讯密钥）
- 系统配置中已设置 `app_url`（面板访问地址）

## 开源协议

MIT License
