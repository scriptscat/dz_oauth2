<?php

function generateRandomString($length = 10)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

function isPost()
{
    return strtoupper($_SERVER['REQUEST_METHOD']) == 'POST';
}


function showError($msg, $refreshtime = 3, $extra = [], $referer = "")
{
    showmessage($msg, $referer ?? dreferer(), $extra, [
        'alert' => 'error',
        'refreshtime' => $refreshtime,
    ]);
    exit();
}

function openMessage($msg, $url = '', $alert = 'right', $refreshtime = 3)
{
    showmessage($msg, $url, [], [
        'alert' => $alert,
        'refreshtime' => $refreshtime
    ]);
    exit();
}

/**
 * 确保 session 已启动
 */
function ensureSession()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * 校验 URL 是否为站内地址，防止 Open Redirect
 */
function sanitizeReferer($referer)
{
    global $_G;
    if (!$referer) {
        return $_G['siteurl'];
    }
    $parsed = parse_url($referer);
    // 拦截 javascript:、data: 等危险协议
    if (isset($parsed['scheme']) && !in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
        return $_G['siteurl'];
    }
    // 相对路径视为站内
    if (!isset($parsed['host'])) {
        return $referer;
    }
    $siteHost = parse_url($_G['siteurl'], PHP_URL_HOST);
    if ($siteHost && strcasecmp($parsed['host'], $siteHost) === 0) {
        return $referer;
    }
    return $_G['siteurl'];
}

