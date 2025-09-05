<?php

use Illuminate\Support\Facades\DB;

if (!function_exists('generateRandomCode')) {
    /**
     * 生成验证码
     *
     * @param int $len
     * @param string $format
     *
     * @return string
     */
    function generateRandomCode($format = '', $len = 6)
    {
        switch ($format) {
            case 'CHAR':
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

                break;
            case 'NUMBER':
                $chars = '0123456789';

                break;
            default:
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

                break;
        }
        mt_srand((float)microtime() * 1000000 * getmypid());
        $code = '';
        while (strlen($code) < $len) {
            $code .= substr($chars, (mt_rand() % strlen($chars)), 1);
        }

        return $code;
    }
}

if (!function_exists('success')) {
    /**
     * 接口请求成功返回
     *
     * @param int $code
     * @param string $msg
     * @param mixed $data
     */
    function success($data = '', $code = SUCCESS_CODE, $msg = 'success')
    {
        header('Content-Type:application/json; charset=utf-8');
        header('Access-Control-Allow-Origin:*');
        $info = [
            'status' => $code,
            'msg' => $msg,
            'data' => $data,
        ];
        return exit(json_encode($info));
    }
}
if (!function_exists('http_build_query')) {
    /**
     * 获取请求参数连接
     *
     * @param array $data
     * @return string
     */
    function http_build_query(array $data): string
    {
        if (empty($data)) {
            return '';
        }
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $str .= $key . '=' . $value . '&';
        }
        return rtrim('&', $str);
    }
}

if (!function_exists('error')) {
    /**
     * 接口请求错误返回
     *
     * @param mixed $data
     * @param int $code
     * @param string $msg
     */
    function error($msg = 'error', $data = '', $code = COMMON_ERROR_CODE)
    {
        success($data, $code, $msg);
    }
}

if (!function_exists('orderNo')) {
    /**
     * 获取订单号
     *
     * @param string $str
     * @return string
     */
    function orderNo($str = ''): string
    {
        return $str . date('YmdHis');
    }
}

if (!function_exists('str_random')) {
    /**
     * 获取随机字符串
     *
     * @param  $len
     * @param string $chars
     * @return string
     */
    function str_random($len, $chars = 'ABCDEFJHIJKMNOPQRSTUVWSYZ'): string
    {
        $str = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $len; ++$i) {
            $str .= $chars[mt_rand(0, $max)];
        }
        return $str;
    }
}

if (!function_exists('isAdmin')) {
    /**
     * 判断当前账户是否为管理员账户
     *
     * @return bool
     */
    function isAdmin(): bool
    {
        $user = auth('admin')->user();
        if (ADMIN_NAME == data_get($user, 'username')) {
            return true;
        }
        return false;
    }
}

if (!function_exists("getUsableCompany")) {
    /**
     * 获取公司权限
     *
     * @param array $companyIds
     * @return array
     */
    function getUsableCompany(array $companyIds = []): array
    {
        $id = auth('admin')->id();
        $result = DB::table('admins')->select('company_id')->where('id', $id)->first();
        if (empty($result)) {
            error("不存在公司权限");
        }
        return data_get($result, 'company_id');
    }
}

if (!function_exists("getUsableLocation")) {
    /**
     * 获取仓库权限
     *
     * @param array $locationIds
     * @return array
     */
    function getUsableLocation(array $locationIds = []): array
    {
        if (isAdmin()) {
            $locations = DB::table('locations')->whereNull('deleted_at')
                ->select('id')->get()->pluck('id')->toArray();
            return $locations;
        }
        $id = auth('admin')->id();
        $result = DB::table('admin_location')->where(
            'admin_id',
            $id
        )->select('location_id')->get()->pluck('location_id')->toArray();
        $user = DB::table('admins')->where('id', $id)->first();
        $companyId = data_get($user, 'company_id');
        $locations = DB::table('locations')->where(
            'company_id',
            $companyId
        )->select('id')->get()->pluck('id')->toArray();
        $result = array_merge_recursive_distinct($result, $locations);
        if (empty($result)) {
            error("不存在仓库权限");
        }
        if (empty($locationIds)) {
            return $result;
        } else {
            $list = [];
            foreach ($locationIds as $value) {
                if (in_array($value, $result)) {
                    $list[] = $value;
                }
            }
            if (empty($list)) {
                error("不存在仓库权限");
            }
            return $list;
        }
    }
}

/**
 * 使用fsockopen发送URL请求
 * @param $url
 * @param $method : GET、POST等
 * @param array $params
 * @param array $header
 * @param int $timeout
 * @return array|bool
 */
if (!function_exists('sendHttpRequest')) {
    function sendHttpRequest($url, $method = 'GET', $params = [], $type = false, $header = [], $timeout = 30)
    {
        ignore_user_abort(true);
        set_time_limit(30);
        $urlInfo = parse_url($url);

        if (isset($urlInfo['scheme']) && 0 === strcasecmp($urlInfo['scheme'], 'https')) { //HTTPS
            $prefix = 'ssl://';
            $port = 443;
        } else {  //HTTP
            $prefix = 'tcp://';
            $port = isset($urlInfo['port']) ? $urlInfo['port'] : 80;
        }

        $host = $urlInfo['host'];
        $path = isset($urlInfo['path']) ? $urlInfo['path'] : '/';

        if (!empty($params) && is_array($params)) {
            $params = http_build_query($params);
        }

        $contentType = '';
        $contentLength = '';
        $requestBody = '';
        if ('GET' === $method) {
            $params = $params ? '?' . $params : '';
            $path .= $params;
        } else {
            $requestBody = $params;
            $contentType = "Content-Type: application/x-www-form-urlencoded\r\n";
            $contentLength = 'Content-Length: ' . strlen($requestBody) . "\r\n";
        }

        $auth = '';
        if (!empty($urlInfo['user'])) {
            $auth = 'Authorization: Basic ' . base64_encode($urlInfo['user'] . ':' . $urlInfo['pass']) . "\r\n";
        }

        if ($header && is_array($header)) {
            $tmpString = '';
            foreach ($header as $key => $value) {
                $tmpString .= $key . ': ' . $value . "\r\n";
            }
            $header = $tmpString;
        } else {
            $header = '';
        }

        $out = "{$method} {$path} HTTP/1.1\r\n";
        $out .= "Host: {$host}\r\n";
        $out .= $auth;
        $out .= $header;
        $out .= $contentType;
        $out .= $contentLength;
        $out .= "Connection: Close\r\n\r\n";
        $out .= $requestBody; //post发送数据前必须要有两个换行符\r\n

        $fp = fsockopen($prefix . $host, $port, $errno, $errstr, $timeout);
        if (!$fp) {
            return false;
        }
        if (!$type) {
            fwrite($fp, $out);
            usleep(20000);
            $ret = fclose($fp);
            if ($ret) {
                return true;
            }
        }

        if (!fwrite($fp, $out)) {
            return false;
        }

        $response = '';
        while (!feof($fp)) {
            $response .= fread($fp, 1024);
        }

        if (!$response) {
            return false;
        }

        fclose($fp);

        $separator = '/\r\n\r\n|\n\n|\r\r/';

        list($responseHeader, $responseBody) = preg_split($separator, $response, 2);

        return [
            'header' => $responseHeader,
            'body' => $responseBody,
        ];
    }
}

if (!function_exists('sendSnStatus')) {
    /**
     * Sn码推送溯源系统
     * @param $datas
     * @param string $process
     */
    function sendSnStatus($datas, $process = '未知操作')
    {
        foreach ($datas as $data) {
            $params['number'] = isset($data['serialno']) ? $data['serialno'] : '';
            $params['process'] = isset($data['process']) ? $data['process'] : $process;
            $params['agent'] = isset($data['agent']) ? $data['agent'] : '';
            $params['lcid'] = isset($data['lcid']) ? $data['lcid'] : '';
            //异步发送请求
            sendHttpRequest(SU_YUAN_PUSH_URL, 'POST', $params);
        }
    }
}
