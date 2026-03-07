<?php
/**
 * QQ 登录迁移中转 — 替换论坛根目录的 connect.php
 *
 * 流程：脚本站发起 → 本文件中转 QQ 登录 → HMAC 签名跳回脚本站完成登录
 * 迁移期结束后废弃。
 */

// ========== 配置 ==========
define('QQ_APP_ID',         '');              // QQ 互联 APP ID
define('QQ_APP_KEY',        '');              // QQ 互联 APP Key
define('CLIENT_SECRET',     '');              // 与脚本站共享的签名密钥（复用 dz_oauth2 的）
define('FORUM_HOST',        'bbs.tampermonkey.net.cn'); // 论坛域名，用于构造 QQ redirect_uri
define('ALLOWED_CALLBACKS', [                 // 允许跳回的域名白名单
    'https://scriptcat.org/api/v2/auth/qq-migrate/callback',
]);

// ========== Discuz 初始化 ==========
error_reporting(0);
define('APPTYPEID', 0);
define('CURSCRIPT', 'connect');
require './source/class/class_core.php';
\C::app()->init();

// 确保原生 PHP session 可用（Discuz 使用自己的 session 机制，
// 原生 $_SESSION 需要显式启动）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ========== QQ OAuth 端点 ==========
define('QQ_AUTH_URL',  'https://graph.qq.com/oauth2.0/authorize');
define('QQ_TOKEN_URL', 'https://graph.qq.com/oauth2.0/token');
define('QQ_ME_URL',    'https://graph.qq.com/oauth2.0/me');

// ========== 路由 ==========
$act  = isset($_GET['act']) ? $_GET['act'] : '';
$code = isset($_GET['code']) ? $_GET['code'] : '';

if ($act === 'migrate') {
    handleMigrate();
} elseif ($code !== '') {
    handleQQCallback();
} else {
    http_response_code(400);
    exit('Bad Request');
}

// ========== 脚本站发起入口 ==========
function handleMigrate() {
    $callback = isset($_GET['callback']) ? $_GET['callback'] : '';
    $state    = isset($_GET['state']) ? $_GET['state'] : '';

    // 校验 callback 白名单
    if (!in_array($callback, ALLOWED_CALLBACKS, true)) {
        http_response_code(403);
        exit('Callback not allowed');
    }

    if (empty($state)) {
        http_response_code(400);
        exit('Missing state');
    }

    // 生成 QQ OAuth state
    $qqState = bin2hex(random_bytes(16));

    // 存 session
    $_SESSION['qq_migrate_callback'] = $callback;
    $_SESSION['qq_migrate_state']    = $state;
    $_SESSION['qq_migrate_qq_state'] = $qqState;

    // 当前论坛 URL 作为 QQ 回调地址
    $redirectUri = currentUrl();

    $params = http_build_query([
        'response_type' => 'code',
        'client_id'     => QQ_APP_ID,
        'redirect_uri'  => $redirectUri,
        'state'         => $qqState,
        'scope'         => 'get_user_info',
    ]);

    header('Location: ' . QQ_AUTH_URL . '?' . $params, true, 302);
    exit;
}

// ========== QQ 回调 ==========
function handleQQCallback() {
    $code    = $_GET['code'];
    $qqState = isset($_GET['state']) ? $_GET['state'] : '';

    $callback = isset($_SESSION['qq_migrate_callback']) ? $_SESSION['qq_migrate_callback'] : '';
    $state    = isset($_SESSION['qq_migrate_state']) ? $_SESSION['qq_migrate_state'] : '';
    $savedQQState = isset($_SESSION['qq_migrate_qq_state']) ? $_SESSION['qq_migrate_qq_state'] : '';

    // 清除 session
    unset($_SESSION['qq_migrate_callback'], $_SESSION['qq_migrate_state'], $_SESSION['qq_migrate_qq_state']);

    if (empty($callback) || empty($state) || empty($savedQQState)) {
        http_response_code(400);
        exit('Session expired');
    }

    // 二次校验 callback 白名单（防止 session fixation 攻击）
    if (!in_array($callback, ALLOWED_CALLBACKS, true)) {
        http_response_code(403);
        exit('Callback not allowed');
    }

    // 校验 QQ state
    if (!hash_equals($savedQQState, $qqState)) {
        http_response_code(400);
        exit('Invalid QQ state');
    }

    $redirectUri = currentUrl();

    // 1. 用 code 换 access_token
    $tokenUrl = QQ_TOKEN_URL . '?' . http_build_query([
        'grant_type'    => 'authorization_code',
        'client_id'     => QQ_APP_ID,
        'client_secret' => QQ_APP_KEY,
        'code'          => $code,
        'redirect_uri'  => $redirectUri,
    ]);
    $tokenResp = curlGet($tokenUrl);
    parse_str($tokenResp, $tokenData);
    $accessToken = isset($tokenData['access_token']) ? $tokenData['access_token'] : '';
    if (empty($accessToken)) {
        redirectError($callback, $state, 'token_failed');
        return;
    }

    // 2. 用 access_token 换 openid
    $meUrl  = QQ_ME_URL . '?' . http_build_query(['access_token' => $accessToken]);
    $meResp = curlGet($meUrl);
    // QQ 返回格式: callback( {"client_id":"...","openid":"..."} );
    if (preg_match('/\{.*\}/s', $meResp, $matches)) {
        $meData = json_decode($matches[0], true);
    } else {
        $meData = [];
    }
    $openid = isset($meData['openid']) ? $meData['openid'] : '';
    if (empty($openid)) {
        redirectError($callback, $state, 'openid_failed');
        return;
    }

    // 3. 在论坛 DB 查询 QQ 绑定
    $uid = queryForumQQBinding($openid);
    if ($uid <= 0) {
        redirectError($callback, $state, 'no_binding');
        return;
    }

    // 4. 生成签名跳回脚本站
    $nonce = bin2hex(random_bytes(16));
    $ts    = time();

    // 签名参数按字母序排列
    $signPayload = "nonce={$nonce}&state={$state}&ts={$ts}&uid={$uid}";
    $sig = hash_hmac('sha256', $signPayload, CLIENT_SECRET);

    $params = http_build_query([
        'uid'   => $uid,
        'ts'    => $ts,
        'nonce' => $nonce,
        'state' => $state,
        'sig'   => $sig,
    ]);

    header('Location: ' . $callback . '?' . $params, true, 302);
    exit;
}

// ========== 辅助函数 ==========

/**
 * 查询论坛 pre_common_member_connect 表，通过 QQ openid 找到论坛 uid
 */
function queryForumQQBinding($openid) {
    $row = DB::fetch_first(
        'SELECT uid FROM %t WHERE conuintoken=%s LIMIT 1',
        array('common_member_connect', $openid)
    );
    return $row ? intval($row['uid']) : 0;
}

/**
 * 跳回 callback 并附带错误参数
 */
function redirectError($callback, $state, $error) {
    $params = http_build_query([
        'error' => $error,
        'state' => $state,
    ]);
    header('Location: ' . $callback . '?' . $params, true, 302);
    exit;
}

/**
 * 获取当前脚本的完整 URL（不含 query string），用作 QQ 的 redirect_uri
 * 使用硬编码的 FORUM_HOST 防止 Host header 伪造
 */
function currentUrl() {
    // 硬编码路径，防止 REQUEST_URI 被攻击者控制
    return 'https://' . FORUM_HOST . '/connect.php';
}

/**
 * cURL GET 请求
 */
function curlGet($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}
