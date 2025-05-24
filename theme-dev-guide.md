# Xboard 主题开发与嵌入指引

本指南详细介绍如何为 Xboard 开发自定义主题插件，并正确接入系统。请在开发前仔细阅读，确保主题符合平台要求。

---

## 1. 主题文件结构要求

每个 Xboard 主题必须包含以下文件：

- `config.json`：主题配置文件，定义主题元信息及可配置项。
- `dashboard.blade.php`：主题主视图，采用 Laravel Blade 模板语法。
- （可选）`assets/` 目录：存放 CSS、JS、图片等静态资源。

**示例结构：**

```
YourThemeName/
├── config.json
├── dashboard.blade.php
└── assets/
    ├── css/
    ├── js/
    └── images/
```

---

## 2. `config.json` 配置说明

`config.json` 是主题的核心配置文件，必须包含如下字段：

- `name`（必填）：主题名称，不能与系统主题（Xboard、v2board）同名。
- `description`（建议）：主题描述。
- `version`（建议）：主题版本号。
- `images`（建议）：主题预览图数组。
- `configs`（可选）：主题可配置项数组。

**示例：**

```json
{
  "name": "my-cool-theme",
  "description": "一个简洁美观的自定义主题",
  "version": "1.0.0",
  "images": ["/assets/images/preview1.png"],
  "configs": [
    {
      "label": "主色调",
      "field_name": "primary_color",
      "field_type": "input",
      "default": "#409EFF"
    },
    {
      "label": "布局风格",
      "field_name": "layout_style",
      "field_type": "select",
      "options": [
        {"label": "经典", "value": "classic"},
        {"label": "紧凑", "value": "compact"}
      ],
      "default": "classic"
    }
  ]
}
```

### 配置项字段说明
- `label`：配置项显示名称
- `field_name`：配置项字段名（唯一）
- `field_type`：类型（支持 `input`、`select`、`textarea` 等）
- `options`：下拉选项（仅 `select` 类型需要）
- `default`：默认值

---

## 3. `dashboard.blade.php` 视图开发

- 使用 Laravel Blade 模板语法开发主题主界面。
- 可通过配置项变量（如 `$configs['primary_color']`）动态渲染主题。
- 建议结构清晰、样式分离，便于维护。
- 可引用 `assets/` 目录下的静态资源。

**示例片段：**

```blade
<div class="dashboard" style="--primary-color: {{ $configs['primary_color'] ?? '#409EFF' }};">
    <!-- 主题内容 -->
</div>
<link rel="stylesheet" href="/theme/YourThemeName/assets/css/style.css">
```

---

## 4. 资源管理

- 所有静态资源建议放在 `assets/` 目录下，结构自定义。
- 主题启用后，资源会被复制到 `public/theme/YourThemeName/`，通过 `/theme/YourThemeName/assets/...` 访问。
- 避免与系统资源命名冲突。

---

## 5. 主题打包与上传

1. 将主题目录（如 `YourThemeName/`）打包为 ZIP 文件。
2. ZIP 文件名仅可包含字母、数字、下划线、中划线和点，大小不超过 10MB。
3. 通过管理后台上传主题，系统会自动解压并校验必要文件。
4. 校验通过后，主题会被安装到 `/storage/theme/YourThemeName/`。

---

## 6. 主题配置项开发

- 在 `config.json` 的 `configs` 数组中定义配置项。
- 支持类型：`input`（输入框）、`select`（下拉）、`textarea`（多行文本）。
- 配置项会在后台主题设置界面自动生成表单。
- 在 `dashboard.blade.php` 中通过 `$configs` 变量获取配置值。

---

## 7. 主题切换与管理

- 启用主题时，系统会将主题文件复制到 `public/theme/`，并清理旧主题文件。
- 系统主题（Xboard、v2board）不可删除。
- 当前正在使用的主题不可删除。

---

## 8. 开发建议

- 建议参考现有系统主题（如 `theme/Xboard`）的实现方式。
- 保持视图与样式分离，便于主题维护和升级。
- 充分利用配置项，提升主题的灵活性和可定制性。
- 遵循 Laravel 和 Blade 的最佳实践。

---

## 9. 常见问题

- **缺少必要文件**：确保 `config.json` 和 `dashboard.blade.php` 均存在。
- **主题名称冲突**：自定义主题名称不能为 `Xboard` 或 `v2board`。
- **资源加载异常**：检查资源路径是否正确，建议使用绝对路径 `/theme/YourThemeName/assets/...`。
- **配置项未生效**：确认 `config.json` 配置项格式正确，并在视图中正确引用。
