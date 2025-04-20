# XrayR前置代理中继方案

## 方案概述

利用XrayR搭建前置代理服务器，接收用户连接请求并转发至原始订阅节点，通过会话复用和连接池管理绕过设备数量和IP限制。

## 技术原理

本方案基于以下技术原理：
1. **连接复用**：多个用户连接复用至少量对外连接
2. **会话池管理**：维护长连接池降低重连频率
3. **统一标识**：统一设备指纹和连接特征
4. **透明转发**：对用户无感知的协议转换

## 系统架构

```
用户 → [本地客户端] → [前置代理服务器(XrayR)] → [原始订阅节点]
```

## 实施步骤

### 1. 环境准备

1. 准备一台性能良好的VPS服务器（建议8核16G以上）
2. 安装最新版本的Linux系统（推荐Ubuntu 22.04/Debian 11）
3. 安装必要依赖：

```bash
apt update
apt install -y curl wget unzip git
```

### 2. 安装XrayR

```bash
bash <(curl -Ls https://raw.githubusercontent.com/XrayR-project/XrayR-install/master/install.sh)
```

### 3. 配置XrayR

创建基础配置文件`/etc/XrayR/config.yml`：

```yaml
Log:
  Level: warning
  AccessPath: /var/log/XrayR/access.log
  ErrorPath: /var/log/XrayR/error.log
DnsConfigPath: /etc/XrayR/dns.json
InboundConfigPath: /etc/XrayR/custom_inbound.json
RouteConfigPath: /etc/XrayR/route.json
OutboundConfigPath: /etc/XrayR/custom_outbound.json
ConnectionConfig:
  Handshake: 4
  ConnIdle: 300
  UplinkOnly: 5
  DownlinkOnly: 30
  BufferSize: 64
Nodes:
  - PanelType: "V2board"
    ApiConfig:
      ApiHost: "https://您的xboard面板地址"
      ApiKey: "您的API密钥"
      NodeID: 1 # 前置节点ID
      NodeType: V2ray
    ControllerConfig:
      DisableSniffing: true
      ListenIP: 0.0.0.0
      SendIP: 0.0.0.0
      EnableProxyProtocol: false
      EnableFallback: false
      FallBackConfigs: []
      CertConfig:
        CertMode: none
```

### 4. 配置入站连接

创建自定义入站配置`/etc/XrayR/custom_inbound.json`：

```json
{
  "inbounds": [
    {
      "listen": "0.0.0.0",
      "port": 10000,
      "protocol": "vmess",
      "settings": {
        "clients": []
      },
      "streamSettings": {
        "network": "tcp"
      },
      "tag": "proxy-inbound"
    }
  ]
}
```

### 5. 配置出站连接池

创建自定义出站配置`/etc/XrayR/custom_outbound.json`：

```json
{
  "outbounds": [
    {
      "protocol": "freedom",
      "settings": {},
      "tag": "direct"
    },
    {
      "protocol": "blackhole",
      "settings": {},
      "tag": "block"
    }
  ]
}
```

### 6. 配置会话管理

创建会话池管理配置`/etc/XrayR/session_pool.json`：

```json
{
  "pool": {
    "minConnections": 5,
    "maxConnections": 100,
    "keepAliveInterval": 30,
    "connectionTimeout": 300,
    "reuseConnection": true,
    "reusePort": true
  },
  "userAgent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
  "fingerprintHash": "random",
  "sourceIP": "固定的源IP地址"
}
```

### 7. 动态节点配置脚本

创建订阅处理脚本`/usr/local/bin/update-proxies.sh`：

```bash
#!/bin/bash

# 订阅地址
SUB_URL="原始订阅链接地址"
PROXY_CONFIG="/etc/XrayR/proxy_config.json"
OUTPUT_DIR="/etc/XrayR/proxies"

# 创建输出目录
mkdir -p $OUTPUT_DIR

# 下载订阅并解析
curl -s $SUB_URL | base64 -d > /tmp/sub_data.yaml

# 提取节点并转换为XrayR配置
python3 /usr/local/bin/sub-converter.py --input /tmp/sub_data.yaml --output $PROXY_CONFIG

# 重新加载XrayR配置
systemctl restart XrayR
```

### 8. 编写转换脚本

创建转换脚本`/usr/local/bin/sub-converter.py`：

```python
#!/usr/bin/env python3
import yaml
import json
import argparse
import hashlib

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--input', required=True, help='Input subscription YAML file')
    parser.add_argument('--output', required=True, help='Output proxy config JSON file')
    args = parser.parse_args()

    # 读取YAML订阅文件
    with open(args.input, 'r', encoding='utf-8') as f:
        sub_data = yaml.safe_load(f)
    
    proxies = sub_data.get('proxies', [])
    
    # 转换为XrayR可用的出站配置
    outbounds = []
    for i, proxy in enumerate(proxies):
        if proxy.get('type') == 'trojan':
            outbounds.append({
                "protocol": "trojan",
                "settings": {
                    "servers": [{
                        "address": proxy.get('server'),
                        "port": int(proxy.get('port')),
                        "password": proxy.get('password'),
                        "flow": ""
                    }]
                },
                "streamSettings": {
                    "network": "tcp",
                    "security": "tls",
                    "tlsSettings": {
                        "serverName": proxy.get('sni'),
                        "allowInsecure": proxy.get('skip-cert-verify', False)
                    }
                },
                "tag": f"proxy-{i}"
            })
        elif proxy.get('type') == 'vmess':
            outbounds.append({
                "protocol": "vmess",
                "settings": {
                    "vnext": [{
                        "address": proxy.get('server'),
                        "port": int(proxy.get('port')),
                        "users": [{
                            "id": proxy.get('uuid'),
                            "alterId": proxy.get('alterId', 0),
                            "security": proxy.get('cipher', 'auto')
                        }]
                    }]
                },
                "streamSettings": {
                    "network": proxy.get('network', 'tcp'),
                    "wsSettings": {
                        "path": proxy.get('ws-path', '/'),
                        "headers": {
                            "Host": proxy.get('ws-headers', {}).get('Host', '')
                        }
                    } if proxy.get('network') == 'ws' else None
                },
                "tag": f"proxy-{i}"
            })
    
    # 生成路由规则
    routing = {
        "domainStrategy": "AsIs",
        "rules": [
            {
                "type": "field",
                "inboundTag": ["proxy-inbound"],
                "balancerTag": "proxy-balancer"
            }
        ],
        "balancers": [
            {
                "tag": "proxy-balancer",
                "selector": [f"proxy-{i}" for i in range(len(outbounds))]
            }
        ]
    }
    
    config = {
        "outbounds": outbounds,
        "routing": routing
    }
    
    # 写入配置文件
    with open(args.output, 'w', encoding='utf-8') as f:
        json.dump(config, f, indent=2)

if __name__ == "__main__":
    main()
```

### 9. 设置定时更新任务

```bash
crontab -e
```

添加以下内容：

```
# 每6小时更新一次代理配置
0 */6 * * * /usr/local/bin/update-proxies.sh > /var/log/proxy-update.log 2>&1
```

### 10. 配置XrayR路由规则

创建`/etc/XrayR/route.json`：

```json
{
  "domainStrategy": "AsIs",
  "rules": [
    {
      "type": "field",
      "outboundTag": "direct",
      "domain": ["geosite:cn"]
    },
    {
      "type": "field",
      "outboundTag": "direct",
      "ip": ["geoip:cn"]
    },
    {
      "type": "field",
      "inboundTag": ["api"],
      "outboundTag": "api"
    },
    {
      "type": "field",
      "inboundTag": ["proxy-inbound"],
      "outboundTag": "proxy-balancer"
    }
  ],
  "balancers": [
    {
      "tag": "proxy-balancer",
      "selector": [],
      "strategy": {
        "type": "leastping"
      }
    }
  ]
}
```

### 11. 启动服务

```bash
systemctl enable XrayR
systemctl restart XrayR
```

### 12. 监控与维护

1. 检查XrayR日志：
```bash
tail -f /var/log/XrayR/error.log
```

2. 监控服务状态：
```bash
systemctl status XrayR
```

3. 性能监控：
```bash
apt install -y htop
htop
```

## 连接池优化策略

1. **会话复用**：启用HTTP/2和TLS会话复用
2. **负载均衡**：智能分配流量到多个出站连接
3. **连接保活**：定期发送心跳包保持连接活跃
4. **故障转移**：检测节点可用性，自动切换到备用节点
5. **资源控制**：限制单个用户的最大连接数和带宽

## 设备指纹统一方案

1. 修改TLS Client Hello指纹
2. 统一HTTP请求头
3. 使用固定的密码学参数
4. 保持相同的协议特征

## 安全性考虑

1. **加密通信**：前置服务器与用户间保持加密
2. **IP隐藏**：掩盖用户真实IP
3. **流量混淆**：防止特征识别
4. **异常监控**：检测并阻止异常连接模式

## 故障排除

1. **连接失败**：检查原始节点是否可用
2. **性能下降**：调整连接池大小和超时设置
3. **内存溢出**：检查并优化资源使用
4. **节点失效**：确保订阅更新机制正常工作

## 性能优化

1. 启用BBR拥塞控制算法
2. 优化系统网络参数
3. 使用合适的CPU核心绑定
4. 调整内存分配

## 注意事项

1. 定期更新XrayR以获取最新功能和安全修复
2. 监控服务器资源使用情况
3. 实施适当的访问控制和防火墙规则
4. 定期备份配置文件

## 节点连接管理

### 连接池设计

本方案采用按需连接与预先建立相结合的混合策略，无需为每个节点都预先建立持久连接。关键特性：

1. **智能连接管理**：
   - 热门节点维持多个并行连接提高吞吐量
   - 冷门节点采用按需创建连接策略
   - 长时间闲置的连接自动释放回收资源

2. **节点切换处理**：
   - 用户切换节点时自动检查连接池状态
   - 已有活跃连接则复用现有连接
   - 无现有连接则创建新连接并加入连接池
   - 对用户完全透明，无感知切换

3. **连接池关键参数**：
   - `minConnections`: 每个热门节点的最小连接数
   - `maxConnections`: 系统允许的最大连接总数
   - `keepAliveInterval`: 连接保活间隔(秒)
   - `connectionTimeout`: 空闲连接超时时间(秒)

4. **优化策略**：
   - 为每个区域/国家的节点保持少量活跃连接
   - 实现连接健康检查，定期验证连接有效性
   - 断线自动重连和智能故障转移机制
   - 连接使用频率和质量分析，优先保留高质量连接

5. **资源控制**：
   - 限制单一用户可使用的连接资源
   - 实现公平的连接分配算法
   - 动态调整连接池大小应对高峰期流量

### 实现原理

利用XrayR的mux多路复用功能结合自定义路由规则实现，使多个用户流量能共享少量到原始服务器的连接，在保持服务质量的同时有效规避设备数和IP限制检测。会话池配置通过脚本方式间接应用到XrayR出站连接配置中，实现连接的智能管理和复用。
