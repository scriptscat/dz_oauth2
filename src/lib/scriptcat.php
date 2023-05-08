<?php

class ScriptCat
{
    private $clientId;
    private $clientSecret;

    public function __construct($clientId, $clientSecret, $host = "https://sct.icodef.com/")
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->host = $host;
    }

    public function accessToken($code)
    {
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
            CURLOPT_POSTFIELDS => "client_id={$this->clientId}&client_secret={$this->clientSecret}&code=$code",
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


    public function send($title, $content, $target = null, $options = null)
    {
        if (isset($options['method']) && $options['method'] === "GET") {
            $searchParams = http_build_query([
                'access_key' => $this->accessKey,
                'title' => $title,
                'content' => $content,
                'parameters' => isset($options['parameters']) ? json_encode($options['parameters']) : null,
                'tags' => isset($target['tags']) ? implode(',', $target['tags']) : null,
                'devices' => isset($target['devices']) ? implode(',', $target['devices']) : null,
            ]);
            $url = $this->host . "openapi/v1/message/send?" . $searchParams;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);
            return $response;
        }
        $url = $this->host . "openapi/v1/message/send?access_key=" . $this->accessKey;
        $data = [
            'title' => $title,
            'content' => $content,
            'device_names' => isset($target['devices']) ? $target['devices'] : null,
            'tags' => isset($target['tags']) ? $target['tags'] : null,
            'parameters' => isset($options['parameters']) ? $options['parameters'] : null,
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}
