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


function showError($msg, $refreshtime = 3, $extra = [])
{
    return showmessage($msg, dreferer(), $extra, [
        'alert' => 'error',
        'refreshtime' => $refreshtime,
        'referer' => rawurlencode(dreferer())
    ]);
}

function openMessage($msg, $url, $alert = 'right', $refreshtime = 3)
{
    return showmessage($msg, $url, [], [
        'alert' => $alert,
        'refreshtime' => $refreshtime
    ]);
}

