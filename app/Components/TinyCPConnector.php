<?php
namespace App\Components;

class TinyCPConnector
{
    //region Members
    private $host;
    private $port;
    private $login;
    private $password;

    private $privateKey;
    private $encryptionKey;
    //endregion

    //region Constructor
    function __construct(string $host, int $port) {

        if (strpos($host, 'http') === 0)
            $this->host = $host;
        else
            $this->host = 'http://' . $host;
        $this->port = $port;
    }
    //endregion

    //region Public Methods
    public function Auth($login, $password, $tfaCode = '') {

        $this->login = $login;
        $this->password = $password;

        $params = [];
            if ($tfaCode)
            $params['tfa_code'] = $tfaCode;

        $udt = $this->CallMethod('app/udt', $params, false);

        if ($udt)
        {
            $this->privateKey = sha1($udt . '|' . $login . '|' . sha1($password));
            $this->encryptionKey = str_pad(base64_encode(sha1($this->privateKey, true)), 32, '=');

            $resutl = $this->CallMethod('app/info', [], true);
            if(!$resutl['auth_status'])
                return false;

            return true;
        }
        else
        {
            return false;
        }
    }

    public function CallMethod(string $method, array $params = [], bool $encrypt = true) {

        $headers = [];
        if ($this->login && $this->privateKey)
            {
            if ($encrypt)
                    $headers[] = 'Content-Type: text/plain;charset=UTF-8';
                $headers[] = 'X-TINYCP-LOGIN: '. $this->login;
                $headers[] = 'X-TINYCP-SIGN: '.base64_encode($this->encrypt($this->login));
        }

        $body = null;
        if ($params) {
                $body = json_encode($params);

            if ($encrypt)
                    $body = utf8_encode($this->encrypt($body));
        }

        $url = $this->host . ':'. $this->port . "/api/$method";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);

        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);

        //region Parse headers into readable format
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers_string = substr($response, 0, $header_size);
        $header_arr = array_filter(explode("\r\n", $headers_string));

        $status_header = array_shift($header_arr);
        if (!preg_match('/^HTTP\/\d(\.\d)? [0-9]{3}/', $status_header))
        {
            throw new Exception('Invalid response header:'. $status_header);
        }
        list(, , $response_msg) = explode(' ', $status_header, 3);

        $response_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $response_headers = [];

        foreach ($header_arr as $h)
        {
            if (!$h)
                continue;

            if (strpos($h, ':') === false)
                continue;

            list($key, $value) = explode(':', $h, 2);
            $response_headers[trim($key)] = trim($value);
        }
        //endregion

        $result = substr($response, $header_size);
        curl_close($ch);

        switch ($response_status)
        {
            case 200:
                if (array_key_exists('x-tinycp-encryption', $response_headers))
                    $result = $this->decrypt($result);

                $result = json_decode($result, true);
                return $result;

            case 401:
                $error = json_decode($response_msg, true);
                if ($error === false)
                    $error = ['msg' => 'Unauthorized', 'details' => ''];

                $msg = $error['msg'];
                if ($error['details'])
                    $msg.= ': ' . $error['details'];

                throw new Exception($msg);
            case 404:
                throw new Exception("Unknown route: $method");
            case 500:
                $error = json_decode($response_msg, true);
                if ($error === false)
                    $error = ['msg' => 'Internal server error', 'details' => ''];

                $msg = $error['msg'];
                if ($error['details'])
                    $msg.= ': ' . $error['details'];
                if ($error['unhandled'])
                    $msg .= '-- UNHANDLED';

                throw new Exception($msg);
            default:
                break;
        }

        return null;
    }
    //endregion

    //region Private Methods
    private function encrypt(string $text) {
        $encrypted = openssl_encrypt($text, 'AES-256-CBC', $this->encryptionKey, OPENSSL_RAW_DATA, '4863134076325584');
        return $encrypted;
    }

    private function decrypt(string $data) {
        $decrypted = openssl_decrypt($data, 'AES-256-CBC', $this->encryptionKey, OPENSSL_RAW_DATA, '4863134076325584');
        return $decrypted;
    }

    // web/http/domain_add
    public function web___http___domain_add(string $domain_name) {
        $params = ['domain_name' => $domain_name];
        return $this->CallMethod('web/http/domain_add', $params, true);
    }
}

