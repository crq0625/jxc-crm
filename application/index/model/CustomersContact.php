<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2017 http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2017/9/11
// +----------------------------------------------------------------------
// | Title:  CustomersContact.php
// +----------------------------------------------------------------------
namespace app\index\model;

use think\Model;

class CustomersContact extends Model
{
    protected function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub
    }
    protected $auto = []; // 自动完成
    protected $insert = ['status'=>1]; // 新增数据自动添加字段
    protected $update = []; // 更新数据自动修改字段
    protected $autoWriteTimestamp = true; // 自动写入时间戳
}