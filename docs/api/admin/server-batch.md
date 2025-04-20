# XBoard 批量节点管理 API

本文档介绍了XBoard中用于批量管理节点的API接口。

## 认证

所有请求需要管理员权限，并携带管理员登录后的认证Cookie。

## API 端点

以下是批量节点管理相关的API端点：

### 1. 批量创建节点

```
POST /admin/server/batch/create
```

**请求参数规范**:

```json
{
  "nodes": [
    {
      "name": "节点名称",                   // 必填，字符串，最大长度255
      "type": "节点类型",                   // 必填，字符串，见支持的节点类型列表
      "group_ids": [1, 2],                // 必填，数组，包含分组ID
      "host": "节点域名或IP",               // 必填，字符串，最大长度255
      "port": "端口范围",                   // 可选，字符串，默认使用server_port值
      "server_port": 10086,               // 必填，整数，范围1-65535
      "protocol_settings": {              // 必填，对象，根据节点类型不同而不同
        // 协议相关设置
      },
      "show": true,                       // 可选，布尔值或整数，默认为1
      "sort": 0,                          // 可选，整数，默认为0
      "tags": ["tag1", "tag2"],           // 可选，数组，默认为[]
      "parent_id": 0,                     // 可选，整数，默认为0
      "rate": 1.0,                        // 可选，数字，默认为1.0
      "route_ids": [1, 2],                // 可选，数组，默认为[]
      "code": 123456                      // 可选，整数，默认为随机6位数
    },
    // ... 更多节点
  ]
}
```

**重要说明**:
1. 所有ID字段（`group_ids`、`route_ids`等）在数据库中都以字符串形式存储，系统会自动转换整数为字符串
2. `protocol_settings`对象结构因节点类型而异，详见协议设置示例
3. 即使标记为可选的字段也会使用默认值写入数据库
4. `tags`和`route_ids`如果不提供，默认为空数组
5. `code`字段如不提供，系统将自动生成6位随机数（100000-999999）
6. `code`字段可用于后续管理操作中快速识别节点，尤其是在批量删除时作为标识符使用
7. `parent_id`用于建立节点间的父子关系，如果没有父节点，使用默认值0
8. `port`字段支持端口范围表示法（如"20000-50000"），对于动态端口很有用

**支持的节点类型**:
- `shadowsocks`: 影梭节点
- `vmess`: V2Ray VMess节点
- `trojan`: Trojan节点
- `hysteria`: Hysteria节点
- `vless`: VLESS节点
- `tuic`: TUIC节点
- `socks`: SOCKS节点
- `http`: HTTP节点
- `naive`: NaiveProxy节点
- `mieru`: Mieru节点

**响应**:

```json
{
  "data": {
    "success": [
      {
        "id": 1,         // 数据库分配的记录ID
        "name": "节点名称"
      }
    ],
    "failed": {
      "0": "失败原因"     // 键为数组中的索引，值为失败的详细原因
    }
  }
}
```

**请求示例**:

```bash
curl -X POST \
  https://your-domain.com/api/v2/<admin_secure_path>/server/batch/create \
  -H 'Content-Type: application/json' \
  -H 'Cookie: <session_cookie>' \
  -d '{
    "nodes": [
      {
        "name": "美国节点01",
        "type": "vmess",
        "group_ids": [1],
        "host": "us01.example.com",
        "port": "443",
        "server_port": 10086,
        "protocol_settings": {
          "tls": 1,
          "network": "ws",
          "network_settings": {
            "path": "/ws",
            "headers": {
              "Host": "us01.example.com"
            }
          }
        },
        "code": 123456,
        "rate": 1.0
      }
    ]
  }'
```

### 2. 批量更新节点

```
PUT /admin/server/batch/update
```

**请求参数**:

```json
{
  "nodes": [
    {
      "id": 1,                           // 必填，整数，节点ID
      "name": "新节点名称",                // 可选，字符串
      "type": "节点类型",                  // 可选，字符串
      "group_ids": [1, 2],               // 可选，数组
      "host": "新节点域名或IP",            // 可选，字符串
      "port": "新端口范围",                // 可选，字符串
      "server_port": 10087,              // 可选，整数
      "protocol_settings": {             // 可选，对象
        // 新的协议设置
      },
      "show": false,                     // 可选，布尔值或整数
      "sort": 1,                         // 可选，整数
      "tags": ["tag1", "tag2"],          // 可选，数组，不提供则使用空数组
      "route_ids": [1, 2],               // 可选，数组
      "parent_id": 1,                    // 可选，整数
      "rate": 1.5                        // 可选，数字
    },
    // ... 更多节点
  ]
}
```

**说明**:
- `id` 字段为必填项，用于标识要更新的节点
- 其他字段为可选项，只更新提供的字段
- 不提供的字段保持原值不变

**响应**:

```json
{
  "data": {
    "success": [
      {
        "id": 1,
        "name": "新节点名称"
      }
    ],
    "failed": {
      "1": "失败原因"
    }
  }
}
```

### 3. 批量删除节点

```
DELETE /admin/server/batch/delete
```

**请求参数**:

```json
{
  "node_ids": [1, 2, "123456"]  // 数组，可以包含ID或code值
}
```

**重要说明**:
1. `node_ids` 数组可以同时包含节点的 `id` 值和 `code` 值
2. 整数值如 `1` 将首先尝试作为 `id` 查找节点
3. 如果通过 `id` 未找到节点，系统会尝试将其作为 `code` 查找
4. 字符串值如 `"123456"` 也会经过相同的查找逻辑
5. 这种设计使得您可以使用更直观的自定义 `code` 值来删除节点，特别是在处理大量节点时
6. 响应中的 `success` 数组包含成功删除的节点的原始值（`id` 或 `code`）

**响应**:

```json
{
  "data": {
    "success": [1, "123456"],       // 成功删除的节点标识符（原始值）
    "failed": {                     // 删除失败的节点及原因
      "3": "节点 ID/Code: 3 不存在"
    }
  }
}
```

**请求示例**:

```bash
curl -X DELETE \
  https://your-domain.com/api/v2/<admin_secure_path>/server/batch/delete \
  -H 'Content-Type: application/json' \
  -H 'Cookie: <session_cookie>' \
  -d '{
    "node_ids": [34, "123456"]
  }'
```

**与管理面板删除的区别**:
管理面板中的单个节点删除功能调用的是 `server/manage/drop` API（POST方法），只支持通过ID删除单个节点。而本批量删除API支持同时删除多个节点，并且可以使用ID或code作为标识符。

### 4. 获取节点模板

```
GET /admin/server/batch/templates
```

**响应**:

```json
{
  "data": [
    {
      "name": "Shadowsocks TCP模板",
      "type": "shadowsocks",
      "protocol_settings": {
        "cipher": "aes-256-gcm",
        "obfs": "plain"
      }
    },
    {
      "name": "VMess WebSocket模板",
      "type": "vmess",
      "protocol_settings": {
        "network": "ws",
        "tls": 1,
        "network_settings": {
          "path": "/ws",
          "headers": {
            "Host": ""
          }
        },
        "tls_settings": {
          "server_name": "",
          "allow_insecure": 0
        }
      }
    },
    // ... 更多模板
  ]
}
```

## 协议设置示例

不同类型的节点对应的`protocol_settings`字段有不同的结构：

### Shadowsocks

```json
{
  "cipher": "aes-256-gcm",
  "obfs": "plain",
  "obfs_settings": {}
}
```

### VMess

```json
{
  "network": "tcp",
  "tls": 0,
  "network_settings": {
    "path": "/",
    "headers": {
      "Host": "example.com"
    }
  },
  "tls_settings": {
    "server_name": "example.com",
    "allow_insecure": 0
  }
}
```

### Trojan

```json
{
  "server_name": "example.com",
  "network": "tcp"
}
```

### VLESS Reality

```json
{
  "network": "tcp",
  "tls": 2,
  "flow": "xtls-rprx-vision",
  "reality_settings": {
    "public_key": "公钥",
    "short_id": "短ID",
    "server_name": "www.microsoft.com"
  }
}
```

### Hysteria

```json
{
  "version": 2,
  "network": "udp",
  "bandwidth": {
    "up": 100,
    "down": 100
  },
  "obfs": {
    "open": false,
    "type": "",
    "password": ""
  },
  "tls": {
    "server_name": "example.com"
  }
}
```

## 完整请求示例

### 批量创建节点示例

```json
{
  "nodes": [
    {
      "name": "美国节点01",
      "type": "vmess",
      "group_ids": [1],
      "host": "us01.example.com",
      "port": "443",
      "server_port": 10086,
      "protocol_settings": {
        "tls": 1,
        "network": "ws",
        "network_settings": {
          "path": "/ws",
          "headers": {
            "Host": "us01.example.com"
          }
        },
        "tls_settings": {
          "server_name": "us01.example.com",
          "allow_insecure": 0
        }
      },
      "show": 1,
      "sort": 0,
      "rate": 1.0,
      "tags": ["美国", "直连"],
      "route_ids": [],
      "code": 100001   // 自定义节点ID
    },
    {
      "name": "香港节点01",
      "type": "trojan",
      "group_ids": [2],
      "host": "hk01.example.com",
      "port": "443",
      "server_port": 443,
      "protocol_settings": {
        "server_name": "hk01.example.com",
        "network": "tcp",
        "allow_insecure": false
      },
      "rate": 1.0,
      "tags": ["香港", "IEPL"],
      "code": 100002   // 自定义节点ID
    }
  ]
}
```

### 批量删除节点示例

```json
{
  "node_ids": [1, 2, "100001", "100002"]
}
```

## 错误处理

对于批量操作，即使部分节点处理失败，API也会返回200状态码，但在响应的`failed`字段中列出失败的节点及原因。

常见错误原因：
- 节点ID不存在
- 分组ID不存在
- 协议配置格式错误
- 端口已被占用
- 数据库操作失败
- 字段格式不符合要求
- 必填字段缺失
