<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2017 http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2017/9/12
// +----------------------------------------------------------------------
// | Title:  CustomersPremises.php
// +----------------------------------------------------------------------
namespace app\index\model;

use think\Model;

class CustomersPremises extends Model
{
    protected function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub
    }

    //获取物流名称
    public function getPreLogIdAttr($vaule) {
        $model = new Logistics();
        $result = $model::get($vaule);
        return $result;
    }
}