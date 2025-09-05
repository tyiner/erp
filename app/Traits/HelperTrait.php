<?php

namespace App\Traits;

use Endroid\QrCode\QrCode;

trait HelperTrait
{
    /**
     * 获取图片的缩略图
     * @param $path
     * @param string $size
     * @return string
     */
    public function thumb($path, $size = '300')
    {
        if (empty($path)) return $path;
        $len = strripos($path, '.');
        $ext = substr($path, $len);
        $name = substr($path, 0, $len);
        return $name . '_' . $size . $ext;
    }

    /**
     * 获取图片的缩略图
     * @param $arr
     * @param string $size
     * @return array
     */
    public function thumb_array($arr, $size = '150')
    {
        $data = [];
        foreach ($arr as $path) {
            $len = strripos($path, '.');
            $ext = substr($path, $len);
            $name = substr($path, 0, $len);
            $data[] = $name . '_' . $size . $ext;
        }
        return $data;

    }

    /**
     * 实现HTTP请求，支持POST方式和HTTPs协议
     * （需要开启cURL扩展库）
     *
     * @param string $url
     *            请求地址
     * @param mixed $data
     *            请求数据 例如：字符串?var1=value1&var2=value2...或者数组array('var1'=>'value1', 'var2'=>'value2'...)，如果POST是文件就必须要用数组且文件名格式为“@绝对路径”array('file'=>'@/path/to/myfile.jpg')
     * @param string $method
     *            请求方式（GET、POST）
     * @param array $header
     *            请求头部信息 array('Content-Type: application/json', 'Accept: application/json', 'Content-Length: ' . strlen($data))
     * @param string $httpCode
     *            请求状态码
     * @param string $error
     *            错误消息
     * @param number $timeout
     *            等待时间
     * @return boolean mixed
     */
    protected function request($url, $data = null, $method = 'GET', $header = array(), &$httpCode = null, &$error = null, $timeout = 30)
    {
        if (empty($header)) {
            $header = [
                'Content-Type: application/json',
                'Accept: application/json',
                'Content-Length: ' . strlen($data),
            ];
        }

        $method = strtoupper($method);
        if ('' == trim($url) || !in_array($method, array(
                'GET',
                'POST',
                'DELETE',
            ))) {
            return false;
        }

        $ch = curl_init(); // 启动一个 cURL 句柄
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 禁用后cURL将终止从服务端进行验证。(HTTPs)
        curl_setopt($ch, CURLOPT_HEADER, 0); // 启用时会将头文件的信息作为数据流输出。
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // 将curl_exec()获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout); // 设置cURL允许执行的最长秒数。
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout); // 在发起连接前等待的时间，如果设置为0，则无限等待。

        if (is_array($header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }

        switch ($method) {
            case 'GET':
                if (is_array($data)) {
                    $str = '?';
                    foreach ($data as $k => $v) {
                        $str .= $k . '=' . $v . '&';
                    }
                    $str = substr($str, 0, -1);
                    $url .= $str;
                } elseif (is_string($data)) {
                    $url .= $data;
                }
                curl_setopt($ch, CURLOPT_URL, $url);
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_URL, $url); // 要访问的地址
                curl_setopt($ch, CURLOPT_POST, 1); // 启用时会发送一个常规的POST请求，类型为：application/x-www-form-urlencoded，就像表单提交的一样。
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data); // 提交的表单数据，这个参数可以通过urlencoded后的字符串类似'para1=val1&para2=val2&...'或使用一个以字段名为键值，字段数据为值的数组。如果value是一个数组，Content-Type头将会被设置成multipart/form-data。如果POST是文件就必须要用数组且文件名格式为“@绝对路径”array('file'=>'@/path/to/myfile.jpg')。
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                break;
            default:
                curl_setopt($ch, CURLOPT_URL, $url);
        }

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            // echo 'Errno: '.curl_errno($ch);
            $error = curl_error($ch); // 捕抓异常
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch); // 关闭CURL会话

        return $result;
    }

    /**
     * POST请求，支持HTTPs协议
     * （需要开启cURL扩展库）
     *
     * @param string $url
     *            请求地址
     * @param string $data
     *            请求数据 例如：字符串?var1=value1&var2=value2...或者数组array('var1'=>'value1', 'var2'=>'value2'...)，如果POST是文件就必须要用数组且文件名格式为“@绝对路径”array('file'=>'@/path/to/myfile.jpg')
     * @param array $header
     *            请求头部信息 array('Content-Type: application/json', 'Accept: application/json', 'Content-Length: ' . strlen($data))
     * @param string $httpCode
     *            请求状态码
     * @param string $error
     *            错误消息
     * @param number $timeout
     *            等待时间
     * @return Ambigous <boolean, mixed>
     */
    protected function postRequest($url, $data = null, $header = array(), &$httpCode = null, &$error = null, $timeout = 30)
    {
        return self::request($url, $data, 'POST', $header, $httpCode, $error, $timeout);
    }

    /**
     * 生成订单号（YYYYMMDDHHIISSNNNNCC）
     * @param $prefix
     * @param $suffix
     * @return string
     */
    protected function orderNo($prefix = '', $suffix = '')
    {
        // return date('YmdHis').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 4);
        // 生成24位唯一订单号码，格式：YYYY-MMDD-HHII-SS-NNNN,NNNN-CC，其中：YYYY=年份，MM=月份，DD=日期，HH=24格式小时，II=分，SS=秒，NNNNNNNN=随机数，CC=检查码
        @date_default_timezone_set("PRC");
        while (true) {

            // 订单号码主体（YYYYMMDDHHIISSNNNN）
            $order_id_main = date('YmdHis') . rand(1000, 9999);

            // 订单号码主体长度
            $order_id_len = strlen($order_id_main);

            $order_id_sum = 0;
            for ($i = 0; $i < $order_id_len; $i++) {
                $order_id_sum += (int)(substr($order_id_main, $i, 1));
            }

            // 唯一订单号码（YYYYMMDDHHIISSNNNNCC）
            $order_id = $order_id_main . str_pad((100 - $order_id_sum % 100) % 100, 2, '0', STR_PAD_LEFT);

            return $prefix . $order_id . $suffix;
        }
    }

    /**
     * 生成二维码
     * @param string $str
     * @return string
     */
    protected function qrCode($str = '') {
        $qrCode = new QrCode($str);
        return $qrCode->writeDataUri();
    }
}
