<?php

class ScriptCat
{
    private $clientId;
    private $clientSecret;
    private $host;

    public function __construct($clientId, $clientSecret, $host = "")
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->host = rtrim($host, '/');
    }

    /**
     * 获取 OAuth2 授权页面 URL
     */
    public function authorizeUrl($redirectUri, $scope = 'openid', $state = '')
    {
        $params = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $scope,
            'state' => $state,
        ]);
        return $this->host . '/oauth/authorize?' . $params;
    }

    /**
     * 用 authorization_code 换取 access_token
     */
    public function accessToken($code, $redirectUri)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->host . '/api/v2/oauth/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirectUri,
            ]),
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded'
            ),
        ));

        $response = curl_exec($curl);
        $errno = curl_errno($curl);
        curl_close($curl);
        if ($errno || $response === false) {
            return null;
        }
        $result = json_decode($response, true);
        if (isset($result['code']) && $result['code'] === 0 && isset($result['data'])) {
            return $result['data'];
        }
        return $result;
    }

    /**
     * 通过 access_token 获取用户信息
     * 返回: {uid, username, email, avatar, sub, name, picture}
     */
    public function userinfo($accessToken)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->host . '/api/v2/oauth/userinfo',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $accessToken,
            ),
        ));

        $response = curl_exec($curl);
        $errno = curl_errno($curl);
        curl_close($curl);
        if ($errno || $response === false) {
            return null;
        }
        $result = json_decode($response, true);
        if (isset($result['code']) && $result['code'] === 0 && isset($result['data'])) {
            return $result['data'];
        }
        return $result;
    }

}
