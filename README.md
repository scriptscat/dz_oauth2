# dz_oauth2

Discuz! X3.5 插件，为油猴中文网论坛提供 ScriptCat 脚本站 OAuth2.0 第三方登录与账号绑定功能。

插件标识符：`codfrm_oauth2`

## 安装

### 1. 复制插件文件

将 `src/` 目录复制到 Discuz 插件目录：

```bash
cp -r src/ /path/to/discuz/source/plugin/codfrm_oauth2/
```

### 2. 替换 connect.php

将根目录下的 `connect.php` 复制到 Discuz 根目录，替换原有的 QQ OAuth2 登录文件：

```bash
cp connect.php /path/to/discuz/connect.php
```

替换前请先编辑 `connect.php` 顶部的配置项：

```php
define('QQ_APP_ID',         '');              // QQ 互联 APP ID
define('QQ_APP_KEY',        '');              // QQ 互联 APP Key
define('CLIENT_SECRET',     '');              // 与脚本站共享的签名密钥
define('FORUM_HOST',        'bbs.tampermonkey.net.cn'); // 论坛域名
define('ALLOWED_CALLBACKS', [                 // 允许跳回的域名白名单
    'https://scriptcat.org/api/v2/auth/qq-migrate/callback',
]);
```

### 3. 安装并启用插件

1. 登录 Discuz 管理后台 → **应用** → **插件**
2. 找到 **ScriptCat OAuth 登录**，点击 **安装**（会自动建表和迁移数据）
3. 安装完成后点击 **启用**

### 4. 配置 OAuth 参数

在插件设置中填写：

- **ScriptCat OAuth client_id** — 在脚本站后台创建的 OAuth 应用 client_id
- **ScriptCat OAuth client_secret** — 在脚本站后台创建的 OAuth 应用 client_secret
- **ScriptCat 脚本站地址** — 如 `https://scriptcat.org`

脚本站 OAuth 应用的回调地址设置为：

```
https://你的论坛地址/plugin.php?id=codfrm_oauth2:bind
```
