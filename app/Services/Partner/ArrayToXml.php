<?php

namespace App\Services\Partner;

use XMLWriter;

/**
 * Class ArrayToXml
 * @package App\Services\Partner
 */
class ArrayToXml
{
    private $version = '1.0';
    private $encoding = 'UTF-8';
    private $root = 'request';
    private $xml;
    private $name;

    public function __construct()
    {
        $this->xml = new XmlWriter();
    }

    /**
     * 数组转XML
     * @param $data
     * @param false $eIsArray
     * @return mixed
     */
    public function toXml($data, $eIsArray = false, $root = '')
    {
        if (!$eIsArray) {
            $this->xml->openMemory();
            //$this->xml->startDocument($this->version, $this->encoding);
            if (empty($root)) {
                $this->xml->startElement($this->root);
            } else {
                $this->xml->startElement($root);
            }
        }

        foreach ($data as $key => $value) {
            if (!is_numeric($key) && is_array($value) && count($value) > 1) {
                $this->name = $key;
            }
            if (is_array($value)) {
                if (is_numeric($key)) {
                    if (0 != $key) {
                        $this->xml->startElement($this->name);
                    }
                } else {
                    $this->xml->startElement($key);
                }
                $this->toXml($value, true);
                $this->xml->endElement();
                continue;
            }
            $this->xml->writeElement($key, $value);
        }
        if (!$eIsArray) {
            $this->xml->endElement();
            return $this->xml->outputMemory(true);
        }
    }
}
