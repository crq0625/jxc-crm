<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2017 http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2017/9/12
// +----------------------------------------------------------------------
// | Title:  ProductColor.php
// +----------------------------------------------------------------------
namespace app\index\model;

use think\Model;

class ProductColor extends Model
{
    protected function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub
    }

    protected $readonly = ['pc_name']; // 锁定名为只读
    protected $auto = []; // 自动完成
    protected $insert = ['status'=>1]; // 新增数据自动添加字段
    protected $autoWriteTimestamp = true; // 自动写入时间戳

    // 状态获取器
    public function getStatusAttr($value)
    {
        $status = [
            -1=> '<span class="label label-sm label-danger">删除</span>',
            0 => '<span class="label label-sm label-warning">禁用</span>',
            1 => '<span class="label label-sm label-success">正常</span>',
            2 => '<span class="label label-sm label-info">审核</span>'
        ];
        return $status[$value];
    }

    //获取账户名称
    public function getPcUserNickAttr($value) {
        $user = new Users();
        $model = $user::get($value);
        return $model;
    }
}