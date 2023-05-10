<?php

class ScriptCat
{
    private $clientId;
    private $clientSecret;

    public function __construct($clientId, $clientSecret, $host = "https://sct.icodef.com")
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->host = $host;
    }

    public function accessToken($code, $op = 'bind')
    {
        global $_G;
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->host . '/api/v1/oauth2/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => "client_id={$this->clientId}&client_secret={$this->clientSecret}&code=$code&grant_type=authorization_code&redirect_uri=" . $_G['siteurl'] . "plugin.php?id=codfrm_oauth2:bind%26p=scriptcat%26op=" . $op,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return json_decode($response, JSON_UNESCAPED_UNICODE);
    }

    function userinfo($accessToken)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->host . '/api/v1/oauth2/user',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $accessToken,
                'User-Agent: github oauth'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return json_decode($response, JSON_UNESCAPED_UNICODE);
    }

    public function getAccessToken()
    {
        global $_G;
        // 缓存
        $cacheKey = "scriptcat_access_token";
        $cache = loadcache($cacheKey);
        if ($cache && $_G['cache'][$cacheKey]['time'] > time() - 3600) {
            return $_G['cache'][$cacheKey]['access_key'];
        }
        // 通过client_id和client_secret获取access_token
        // oauth2 客户端模式
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->host . '/api/v1/oauth2/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => "client_id={$this->clientId}&client_secret={$this->clientSecret}&grant_type=client_credentials",
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded'
            ),
        ));

        $response = curl_exec($curl);

        $response = json_decode($response, JSON_UNESCAPED_UNICODE);

        curl_close($curl);

        if (isset($response['access_token'])) {
            savecache($cacheKey, ['access_key' => $response['access_token'], 'time' => time()]);
            return $response['access_token'];
        } else {
            return null;
        }
    }

    public function send($userIds, $title, $content, $parameters = [])
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return null;
        }

        $url = $this->host . "/api/v1/oauth2/message/send?access_token=" . $accessToken;
        $data = [
            'target' => [
                'user_ids' => $userIds,
            ],
            'title' => $title,
            'content' => $content,
            'parameters' => $parameters,
        ];
        // 输出错误
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}
