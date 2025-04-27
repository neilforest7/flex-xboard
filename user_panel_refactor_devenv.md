# XBoard用户面板重构开发环境配置

## 1. 本地开发环境搭建

### 1.1 复制线上主题文件
- 从服务器下载`/www/wwwroot/your-domain/public/theme/aurora`目录（假设使用Aurora主题）
- 将其保存到本地工作目录，如`~/xboard-theme-dev/aurora-custom`

### 1.2 建立前端开发环境
```bash
# 进入项目目录
cd ~/xboard-theme-dev/aurora-custom

# 初始化npm项目
npm init -y

# 安装开发依赖
npm install --save-dev vite sass npm-watch browser-sync gulp gulp-sass gulp-postcss autoprefixer cssnano
```

### 1.3 配置构建脚本
在`package.json`中添加以下构建脚本：
```json
"scripts": {
  "dev": "vite",
  "build": "gulp build",
  "watch": "npm-watch"
},
"watch": {
  "build": {
    "patterns": ["src/**/*"],
    "extensions": "js,scss,css,html"
  }
}
```

### 1.4 创建文件夹结构
```
aurora-custom/
├── src/
│   ├── scss/
│   │   ├── _variables.scss
│   │   └── custom.scss
│   ├── js/
│   │   └── custom.js
│   └── components/
├── static/
│   ├── css/
│   ├── js/
│   └── img/
├── gulpfile.js
├── vite.config.js
└── package.json
```

### 1.5 配置Vite开发服务器
创建`vite.config.js`文件：
```js
export default {
  root: 'src',
  publicDir: 'static',
  server: {
    host: '0.0.0.0',
    port: 3000,
    open: true,
    proxy: {
      '/api': {
        target: 'https://你的线上XBoard域名',
        changeOrigin: true
      }
    }
  },
  build: {
    outDir: '../static',
    emptyOutDir: false
  }
}
```

### 1.6 设置Gulp构建任务
创建`gulpfile.js`文件：
```js
const gulp = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const postcss = require('gulp-postcss');
const autoprefixer = require('autoprefixer');
const cssnano = require('cssnano');

gulp.task('styles', function() {
  return gulp.src('./src/scss/custom.scss')
    .pipe(sass().on('error', sass.logError))
    .pipe(postcss([autoprefixer(), cssnano()]))
    .pipe(gulp.dest('./static/css'));
});

gulp.task('scripts', function() {
  return gulp.src('./src/js/custom.js')
    .pipe(gulp.dest('./static/js'));
});

gulp.task('build', gulp.series('styles', 'scripts'));
```

## 2. 本地测试环境配置

### 2.1 配置本地代理服务器（可选）
- 安装本地Apache/Nginx和PHP环境（XAMPP/WAMP等）
- 配置虚拟主机指向你的本地XBoard安装
- 将开发中的主题链接到XBoard主题目录

### 2.2 使用BrowserSync直接代理线上站点
创建`browser-sync.js`文件：
```js
const browserSync = require('browser-sync').create();

browserSync.init({
  proxy: "https://你的线上XBoard域名",
  files: ["static/css/*.css", "static/js/*.js"],
  serveStatic: ["static"],
  rewriteRules: [
    {
      match: /<link rel="stylesheet" href="(.+?)aurora\/static\/css\/app\.(.+?)\.css">/g,
      replace: '<link rel="stylesheet" href="/css/custom.css">'
    },
    {
      match: /<script src="(.+?)aurora\/static\/js\/custom\.js">/g,
      replace: '<script src="/js/custom.js">'
    }
  ]
});
```

## 3. 版本控制设置

```bash
git init
echo "node_modules/" > .gitignore
git add .
git commit -m "初始化主题开发环境"
```

## 4. 开发流程

```bash
# 启动开发服务器
npm run dev

# 或使用文件监控自动构建
npm run watch
```

## 5. SCSS开发最佳实践

### 5.1 主题变量结构
在`_variables.scss`中定义主题变量：

```scss
// 颜色系统
$primary-colors: (
  50: hsl(var(--primary-hue), 90%, 95%),
  100: hsl(var(--primary-hue), 85%, 90%),
  500: hsl(var(--primary-hue), 80%, 50%),
  600: hsl(var(--primary-hue), 90%, 45%),
  700: hsl(var(--primary-hue), 95%, 40%)
);

// 尺寸变量
$radius: (
  small: 4px,
  medium: 8px,
  large: 16px
);

// 阴影变量
$shadows: (
  sm: 0 1px 2px rgba(0, 0, 0, 0.05),
  md: 0 4px 6px rgba(0, 0, 0, 0.1),
  lg: 0 10px 15px rgba(0, 0, 0, 0.1)
);

// 间距变量
$spacing: (
  xs: 0.25rem,
  sm: 0.5rem,
  md: 1rem,
  lg: 1.5rem,
  xl: 2rem
);

// 动画时间
$transitions: (
  fast: 150ms,
  normal: 250ms,
  slow: 350ms
);
```

### 5.2 组织SCSS文件
建议按以下结构组织SCSS文件：

```
scss/
├── abstracts/
│   ├── _variables.scss    // 变量定义
│   ├── _functions.scss    // SCSS函数
│   ├── _mixins.scss       // 混合宏
│   └── _placeholders.scss // 占位符选择器
├── base/
│   ├── _reset.scss        // CSS重置
│   ├── _typography.scss   // 字体排版
│   └── _animations.scss   // 动画定义
├── components/
│   ├── _buttons.scss      // 按钮样式
│   ├── _cards.scss        // 卡片组件
│   └── _tables.scss       // 表格样式
├── layout/
│   ├── _header.scss       // 头部布局
│   ├── _sidebar.scss      // 侧边栏
│   └── _dashboard.scss    // 仪表盘布局
├── pages/
│   ├── _home.scss         // 首页特定样式
│   ├── _servers.scss      // 服务器页面
│   └── _profile.scss      // 用户资料页面
├── themes/
│   ├── _default.scss      // 默认主题
│   ├── _dark.scss         // 暗色主题
│   └── _custom.scss       // 自定义主题
└── main.scss              // 主SCSS文件
```

### 5.3 使用CSS变量导出主题变量
在`custom.scss`中：

```scss
@import 'abstracts/variables';

:root {
  // 导出颜色变量
  @each $name, $value in $primary-colors {
    --primary-#{$name}: #{$value};
  }
  
  // 导出圆角变量
  @each $name, $value in $radius {
    --radius-#{$name}: #{$value};
  }
  
  // 导出阴影变量
  @each $name, $value in $shadows {
    --shadow-#{$name}: #{$value};
  }
  
  // 导出动画变量
  @each $name, $value in $transitions {
    --transition-#{$name}: #{$value};
  }
}
```

这种设置可以让您在本地快速开发XBoard主题，同时保持与系统的兼容性。根据项目规模和需求，您可以进一步调整工具链配置。 