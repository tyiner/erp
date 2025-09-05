<?php

namespace App\Http\Controllers;

use App\Traits\UserTrait;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

use App\Traits\ResourceTrait;
use Illuminate\Validation\ValidationException;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests, ResourceTrait, UserTrait;

    /**
     * 校验参数
     *
     * @param Request $request
     * @param array $rules
     * @param array $messages
     * @param array $customAttributes
     */
    protected function handleValidateRequest(
        Request $request,
        array $rules,
        array $messages = [],
        array $customAttributes = []
    ) {
        try {
            $this->validate($request, $rules, $messages, $customAttributes);
        } catch (ValidationException $validationException) {
            error($validationException->validator->getMessageBag()->first());
        }
    }

    /**
     * 获取文件中的Sn码信息
     *
     * @param  $filePath
     * @return array
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function getSerialsData($filePath): array
    {
        $extension = substr($filePath, strrpos($filePath, '.') + 1);
        if ('xlsx' == $extension) {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        } elseif ('xls' == $extension) {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xls');
        } elseif ('csv' == $extension) {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Csv');
        } else {
            error("不存在的excel类型");
        }
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow(); // 总行数
        $serials = [];
        for ($i = 1; $i <= $highestRow; $i++) {
            if (!empty($value = $worksheet->getCell('A' . $i)->getValue())
                && !in_array($value, $serials)
            ) {
                $serials[] = $value;
            }
        }
        if (empty($serials)) {
            error("上传文件内容不能为空");
        }
        return $serials;
    }

    /**
     * @param string $className
     * @param array $paramNames
     * @return string
     */
    public function getConstByName(string $className, array $paramNames): string
    {
        $reflect_obj = '';
        try {
            $reflect_obj = new \ReflectionClass($className);
        } catch (\ReflectionException $e) {
            error($className . '对应的类不存在');
        }
        $constants = $reflect_obj->getConstants();
        $info = [];
        foreach ($paramNames as $name) {
            if (data_get($constants, $name)) {
                $info[] = data_get($constants, $name);
            }
        }
        return implode(',', $info);
    }
}
