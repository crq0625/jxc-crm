<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2017 http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2017/9/12
// +----------------------------------------------------------------------
// | Title:  Product.php
// +----------------------------------------------------------------------
namespace app\index\controller;

use app\index\model\Bancai;
use app\index\model\ProductNumber;
use app\index\model\ProductColor;
use app\index\model\FittingsLock;
use app\index\model\Stockpile;
use app\index\model\StockpileLock;
use think\Request;
use think\Db;
use think\Session;
use think\Url;

class Product extends Common_base
{
    public function _initialize()
    {
        // 是否有权限
        IS_ROOT([1])  ? true : $this->error('没有权限');
        return parent::_initialize(); // TODO: Change the autogenerated stub
    }

    //产品颜色
    public function color() {
        $Request = Request::instance();
        $query = $Request->param(); // 分页查询传参数
        $status = $Request->param('status'); // 状态查询
        $q = $Request->param('q'); // 名称查询

        $model = new ProductColor();
        if (empty($q)) {
            //默认搜索
            $list = $model->where('status', '>=',0)->order('update_time', 'desc')->paginate(20); // 默认查询
        } else {
            $list = $model->where('pc_name','like', '%'.$q.'%')->where('status', 1)->order('update_time', 'desc')->paginate('20', false, ['query' => $query ]); // 默认查询
        }

        // 获取分页显示
        $page = $list->render();
        $assign = [
            'title' => '颜色系列',
            'page'  => $page,
            'list'  => $list,
            'empty' => '<tr><td colspan="9" align="center">当前条件没有查到数据</td></tr>',
        ];
        $this->assign($assign);
        return $this->fetch();
    }

    //查询颜色名称
    public function check_color() {
        $Request = Request::instance();
        if ($Request->isPost()) {
            $name = $Request->param('name');
            $result = ProductColor::getByPcName($name);
            if ($result) {
                return false;
            } else {
                return true;
            }
        }
    }

    //添加产品颜色
    public function color_add() {
        $Request = Request::instance();
        $handle = $Request->param('handle');
        if (empty($handle)) {
            //默认
            return $this->fetch();
        } elseif ($handle == 'add') {
            //添加动作
            if ($Request->isPost()) {
                //$data = Request::instance()->param(); // 获取POST参数
                $file = request()->file('pc_img'); // 获取上传文件
                if (!empty($file)) {
                    // 上传图片 移动 用 rule('uniqid') 无序
                    $info = $file->validate([
                        'type' => 'image/jpeg,image/gif,image/png', // 上传文件头
                        'ext' => 'jpeg,jpg,png', // 上传类型
                        'size'=> 0.5 * 1024 * 1024, // 上传大小
                    ])->move(ROOT_PATH . 'public' . DS . 'uploads'. DS .'images');
                    // 判断信息
                    if ($info) {
                        $pc_img = DS . 'uploads'. DS .'images'. DS . $info->getSaveName();
                        //替换windows 下的 \
                        $data['pc_img'] = str_replace("\\",'/',$pc_img);
                    } else {
                        // 上传失败获取错误信息
                        $this->error($file->getError());
                    }
                }
                $data['pc_user_nick'] = Session::get('user_id');
                $data['pc_name'] = strFilter(cutstr_html($Request->param('pc_name')));
                $data['pc_address'] = strFilter(cutstr_html($Request->param('pc_address')));
                $data['pc_description'] = strFilter(cutstr_html($Request->param('pc_description')));
                $model = new ProductColor();
                $model->data($data);
                $result = $model->save();

                if ($result) {
                    $pcid = $model->pc_id; //$model->pc_id; 获取自增ID
                    //新增库存
                    $storage = Db::name('storage_charge')->field('lxid')->select();
                    $bancailist = Db::name('bancai_list')->field('blid')->select();
                    //更新关联铝材库存
                    if (!empty($storage)) {
                        $Stockpile = new Stockpile();
                        foreach ($storage AS $ky=>$vy) {
                            $list[] = [
                                'sp_pcid'    => $pcid,
                                'sp_lxid'    => $vy['lxid'],
                                'sp_quantity'=> '0',
                            ];
                        }
                        if (!$Stockpile->saveAll($list)) {
                            $this->error('更新关联铝材库存错误，请通知管理员');
                        };
                    }
                    //更新关联板材库存
                    if (!empty($bancailist)) {
                        $BancaiModel = new Bancai();
                        foreach ($bancailist AS $ky=>$vy) {
                            $ban_list[] = [
                                'bpcid'    => $pcid,
                                'bplid'    => $vy['blid'],
                                'bquantity'=> '0',
                            ];
                        }
                        if (!$BancaiModel->saveAll($ban_list)) {
                            $this->error('更新关联板材库存错误，请通知管理员');
                        };
                    }
                    $this->success('添加成功',Url::build('product/color'));

                } else {
                    $this->error('服务器错误，请通知管理员');
                }
            } else {
                $this->error('提交错误');
            }
        } else {
            $this->error('提交错误');
        }
    }

    //修改产品颜色
    public function color_edit() {
        $Request = Request::instance();
        $handle = $Request->param('handle');
        if (empty($handle)) {
            //默认
            $pid = $Request->param('pid');
            if (empty($pid)) {
                $this->error('参数错误');
            }
            if (!ProductColor::get($pid)) {
                $this->error('参数错误');
            }
            $data = Db::name('product_color')->where('pc_id',$pid)->find();
            $assign = [
                'title' => '产品颜色修改',
                'data'  => $data,
            ];
            $this->assign($assign);
            return $this->fetch();
        } elseif ($handle == 'edit') {
            //添加动作
            if ($Request->isPost()) {
                $pid = $Request->param('pc_id');
                if (!ProductColor::get($pid)) {
                    $this->error('参数错误');
                }
                $file = request()->file('pc_img'); // 获取上传文件
                if (!empty($file)) {
                    // 上传图片 移动 用 rule('uniqid') 无序
                    $info = $file->validate([
                        'type' => 'image/jpeg,image/gif,image/png', // 上传文件头
                        'ext' => 'jpeg,jpg,png', // 上传类型
                        'size'=> 0.5 * 1024 * 1024, // 上传大小
                    ])->move(ROOT_PATH . 'public' . DS . 'uploads'. DS .'images');
                    // 判断信息
                    if ($info) {
                        $image = DS . 'uploads'. DS .'images'. DS . $info->getSaveName();
                        //替换windows 下的 \
                        $data['pc_img'] = str_replace("\\",'/',$image);
                        unset($file); //清空上传
                    } else {
                        // 上传失败获取错误信息
                        $this->error($file->getError());
                    }
                }

                $data['pc_address'] = strFilter(cutstr_html($Request->param('pc_address')));
                $data['pc_description'] = strFilter(cutstr_html($Request->param('pc_description')));
                $data['status'] = strFilter(cutstr_html($Request->param('status')));

                $model = new ProductColor();
                $result = $model->save($data, ['pc_id'=>$pid]);
                if ($result) {
                    $this->success('修改成功',Url::build('product/color'));
                }
            }
        } else {
            $this->error('提交错误');
        }
    }

    //删除产品颜色操作
    public function color_delete() {
        $Request = Request::instance();
        if ($Request->isPost()) {
            $pid = $Request->param('pid');
            $name = $Request->param("name");
            if (empty($pid)) {
                $this->error('传入参数错误');
            }
            if ($name == 'delone') {
                // 单条删除操作
                ProductColor::where('pc_id', $pid)->delete();
                //删除关联库存 铝材
                Stockpile::where('sp_pcid', $pid)->delete();
                //删除关联库存 板材
                Bancai::where('bpcid', $pid)->delete();

                $this->success('删除成功', Url::build('product/color'));
            } elseif ($name == 'delallattr') {
                // 多条删除操作
                $arrUid = explode(",",$pid);
                if (!empty($arrUid)) {
                    $i=0;
                    foreach ($arrUid as $key=>$val) {
                        Db::name('product_color')->where('pc_id', $val)->update(['status'=>'-1']);
                        $i++;
                    }
                    $this->success($i.' 条记录删除成功', Url::build('product/color'));
                }
            } else {
                // 不执行操作
                $this->error('传入参数错误');
            }
        }
    }

    //产品序列
    public function number() {
        $Request = Request::instance();
        $query = $Request->param(); // 分页查询传参数
        $status = $Request->param('status'); // 状态查询
        $q = $Request->param('q'); // 名称查询

        $model = new ProductNumber();
        if (empty($q)) {
            //默认搜索
            $list = $model->where('status','>=', 0)->order('update_time', 'desc')->paginate(); // 默认查询
        } else {
            $list = $model->where('pn_name', 'like', '%'.$q.'%')->where('status','>=', 0)->order('update_time', 'desc')->paginate('', false, ['query' => $query ]); // 默认查询
        }

        // 获取分页显示
        $page = $list->render();
        $assign = [
            'title' => '产品系列',
            'page'  => $page,
            'list'  => $list,
            'empty' => '<tr><td colspan="11" align="center">当前条件没有查到数据</td></tr>',
        ];
        $this->assign($assign);
        return $this->fetch();
    }

    //查询序列名称
    public function check_number() {
        $Request = Request::instance();
        if ($Request->isPost()) {
            $name = $Request->param('name');
            $result = ProductNumber::getByPnName($name);
            if ($result) {
                return false;
            } else {
                return true;
            }
        }
    }

    //添加产品序列
    public function number_add() {
        $Request = Request::instance();
        $handle = $Request->param('handle');
        if (empty($handle)) {

            //默认
            $baobian = Db::name('others_baobian')->group('bname')->field('bname')->select();
            $assign = [
                'baobian' => $baobian,
            ];
            $this->assign($assign);
            return $this->fetch();

        } elseif ($handle == 'add') {
            //添加动作
            if ($Request->isPost()) {
                //$data = Request::instance()->param(); // 获取POST参数
                $file = request()->file('pn_img'); // 获取上传文件
                if (!empty($file)) {
                    // 上传图片 移动 用 rule('uniqid') 无序
                    $info = $file->validate([
                        'type' => 'image/jpeg,image/gif,image/png', // 上传文件头
                        'ext' => 'jpeg,jpg,png', // 上传类型
                        'size'=> 0.5 * 1024 * 1024, // 上传大小
                    ])->move(ROOT_PATH . 'public' . DS . 'uploads'. DS .'images');
                    // 判断信息
                    if ($info) {
                        $image = DS . 'uploads'. DS .'images'. DS . $info->getSaveName();
                        //替换windows 下的 \
                        $data['pn_img'] = str_replace("\\",'/',$image);
                    } else {
                        // 上传失败获取错误信息
                        $this->error($file->getError());
                    }
                }

                $data['pn_description'] = strFilter(cutstr_html($Request->param('pn_description')));
                $data['pn_name'] = strFilter(cutstr_html($Request->param('pn_name')));
                $data['pn_price'] = get_numeric($Request->param('pn_price'));
                $data['pn_baobian'] = $Request->param('pn_baobian');
                $data['pn_user_nick'] = Session::get('user_id');
                $model = new ProductNumber($_POST);
                $model->data($data);
                $result = $model->allowField(true)->save();
                if ($result) {
                    $this->success('添加成功',Url::build('product/number'));
                } else {
                    $this->error('服务器错误，请通知管理员');
                }
            } else {
                $this->error('提交错误');
            }
        } else {
            $this->error('提交错误');
        }
    }

    //修改产品序列
    public function number_edit() {
        $Request = Request::instance();
        $handle = $Request->param('handle');
        if (empty($handle)) {

            //默认
            $pid = $Request->param('pid');
            if (empty($pid)) {
                $this->error('参数错误');
            }
            if (!ProductNumber::get($pid)) {
                $this->error('参数错误');
            }
            $data = Db::name('product_number')->where('pn_id',$pid)->find();
            $baobian = Db::name('others_baobian')->group('bname')->select();
            $assign = [
                'title' => '产品序列修改',
                'data'  => $data,
                'baobian'  => $baobian,
            ];
            $this->assign($assign);
            return $this->fetch();

        } elseif ($handle == 'edit') {
            //添加动作
            if ($Request->isPost()) {
                //$date = Request::instance()->param(); // 获取POST参数
                $pid = $Request->param('pn_id');
                if (!ProductNumber::get($pid)) {
                    $this->error('参数错误');
                }
                $file = request()->file('pn_img'); // 获取上传文件
                if (!empty($file)) {
                    // 上传图片 移动 用 rule('uniqid') 无序
                    $info = $file->validate([
                        'type' => 'image/jpeg,image/gif,image/png', // 上传文件头
                        'ext' => 'jpeg,jpg,png', // 上传类型
                        'size'=> 0.5 * 1024 * 1024, // 上传大小
                    ])->move(ROOT_PATH . 'public' . DS . 'uploads'. DS .'images');
                    // 判断信息
                    if ($info) {
                        $image = DS . 'uploads'. DS .'images'. DS . $info->getSaveName();
                        //替换windows 下的 \
                        $data['pn_img'] = str_replace("\\",'/',$image);
                        unset($file); //清空上传
                    } else {
                        // 上传失败获取错误信息
                        $this->error($file->getError());
                    }
                }

                $data['pn_price'] = get_numeric($Request->param('pn_price'));
                $data['pn_baobian'] = $Request->param('pn_baobian');
                $data['pn_description'] = strFilter(cutstr_html($Request->param('pn_description')));
                $data['status'] = strFilter(cutstr_html($Request->param('status')));
                //p('dd');
                //exit();
                $model = new ProductNumber();
                $result = $model->save($data, ['pn_id'=>$pid]);
                if ($result) {
                    $this->success('修改成功',Url::build('product/number'));
                }
            }
        } else {
            $this->error('提交错误');
        }
    }

    //删除产品序列操作
    public function number_delete() {
        $Request = Request::instance();
        if ($Request->isPost()) {
            $pid = $Request->param('pid');
            $name = $Request->param("name");
            if (empty($pid)) {
                $this->error('传入参数错误');
            }
            if ($name == 'delone') {
                // 单条删除操作
                Db::name('product_number')->where('pn_id', $pid)->update(['status'=>'-1']);
                $this->success('删除成功', Url::build('product/number'));
            } elseif ($name == 'delallattr') {
                // 多条删除操作
                $arrUid = explode(",",$pid);
                if (!empty($arrUid)) {
                    $i=0;
                    foreach ($arrUid as $key=>$val) {
                        Db::name('product_number')->where('pn_id', $val)->update(['status'=>'-1']);
                        $i++;
                    }
                    $this->success($i.' 条记录删除成功', Url::build('product/number'));
                }
            } else {
                // 不执行操作
                $this->error('传入参数错误');
            }
        }
    }
    
    //锁具配件
    public function suoju()
    {
        $FittingsLock = new FittingsLock();
        $list = $FittingsLock->order('lprice','asc')->paginate();
        // 获取分页显示
        $page = $list->render();
        $assign = [
            'title' => '锁具配件',
            'page'  => $page,
            'list'  => $list,
            'empty' => '<tr><td colspan="10" align="center">当前条件没有查到数据</td></tr>',
        ];
        $this->assign($assign);
        return $this->fetch();
    }

    //新增锁具配件
    public function suoju_add()
    {
        $Request = Request::instance();
        $handle = $Request->param('handle');
        if (empty($handle)) {
            //默认
            return $this->fetch();
        } elseif ($handle == 'add') {
            //添加动作
            if ($Request->isPost()) {
                //验证数据
                $validate = $this->validate(
                    ['__token__' => $Request->param('__token__')],
                    ['__token__' => 'token']
                );
                if(true !== $validate){
                    $this->error('数据提交错误，请返回刷新');
                }

                $file = request()->file('limg'); // 获取上传文件
                if (!empty($file)) {
                    // 上传图片 移动 用 rule('uniqid') 无序
                    $info = $file->validate([
                        'type' => 'image/jpeg,image/gif,image/png', // 上传文件头
                        'ext' => 'jpeg,jpg,png', // 上传类型
                        'size'=> 0.5 * 1024 * 1024, // 上传大小
                    ])->move(ROOT_PATH . 'public' . DS . 'uploads'. DS .'images');
                    // 判断信息
                    if ($info) {
                        $image = DS . 'uploads'. DS .'images'. DS . $info->getSaveName();
                        //替换windows 下的 \
                        $data['limg'] = str_replace("\\",'/',$image);
                    } else {
                        // 上传失败获取错误信息
                        $this->error($file->getError());
                    }
                }

                $data['ldescription'] = strFilter(cutstr_html($Request->param('ldescription')));
                $data['laddress'] = strFilter(cutstr_html($Request->param('laddress')));
                $data['lname'] = strFilter(cutstr_html($Request->param('lname')));
                $data['lprice'] = get_numeric($Request->param('lprice'));
                $data['luser_nick'] = Session::get('user_id');
                $model = new FittingsLock($_POST);
                $model->data($data);
                $result = $model->allowField(true)->save();
                if ($result) {
                    //$model->lid;
                    //写入库存数量
                    $StockpileLock = new StockpileLock();
                    $StockpileLock->data([
                        'st_lid' => $model->lid,
                        'st_quantity' => '0',
                    ]);
                    if (!$StockpileLock->save()) {
                        $this->error('更新关锁具库存错误，请通知管理员');
                    };
                    $this->success('添加成功',Url::build('product/suoju'));
                } else {
                    $this->error('服务器错误，请通知管理员');
                }
            } else {
                $this->error('提交错误');
            }
        } else {
            $this->error('提交错误');
        }
    }

    //查询锁具名称
    public function check_suoju()
    {
        $Request = Request::instance();
        if ($Request->isPost()) {
            $name = $Request->param('name');
            $result = FittingsLock::getByLname($name);
            if ($result) {
                return false;
            } else {
                return true;
            }
        }
    }

    //修改锁具配件
    public function suoju_edit()
    {
        $Request = Request::instance();
        $handle = $Request->param('handle');
        if (empty($handle)) {
            //默认
            $pid = $Request->param('pid');
            if (empty($pid)) {
                $this->error('参数错误0');
            }
            if (!FittingsLock::get($pid)) {
                $this->error('参数错误1');
            }
            $data = Db::name('fittings_lock')->where('lid',$pid)->find();
            $assign = [
                'title' => '锁具修改',
                'data'  => $data,
            ];
            $this->assign($assign);
            return $this->fetch();

        }  elseif ($handle == 'edit') {
            //添加动作
            if ($Request->isPost()) {
                //验证数据
                $validate = $this->validate(
                    ['__token__' => $Request->param('__token__')],
                    ['__token__' => 'token']
                );
                if(true !== $validate){
                    //$this->error('数据提交错误，请返回刷新');
                }

                $pid = $Request->param('pid');
                if (!FittingsLock::get($pid)) {
                    $this->error('参数错误2');
                }
                $file = request()->file('limg'); // 获取上传文件
                if (!empty($file)) {
                    // 上传图片 移动 用 rule('uniqid') 无序
                    $info = $file->validate([
                        'type' => 'image/jpeg,image/gif,image/png', // 上传文件头
                        'ext' => 'jpeg,jpg,png', // 上传类型
                        'size'=> 0.5 * 1024 * 1024, // 上传大小
                    ])->move(ROOT_PATH . 'public' . DS . 'uploads'. DS .'images');
                    // 判断信息
                    if ($info) {
                        $image = DS . 'uploads'. DS .'images'. DS . $info->getSaveName();
                        //替换windows 下的 \
                        $data['limg'] = str_replace("\\",'/',$image);
                        unset($file); //清空上传
                    } else {
                        // 上传失败获取错误信息
                        $this->error($file->getError());
                    }
                }

                $data['ldescription'] = strFilter(cutstr_html($Request->param('ldescription')));
                $data['laddress'] = strFilter(cutstr_html($Request->param('laddress')));
                $data['lprice'] = get_numeric($Request->param('lprice'));
                $data['status'] = $Request->param('status');

                $model = new FittingsLock();
                $result = $model->save($data, ['lid'=>$pid]);
                if ($result) {
                    $this->success('修改成功',Url::build('product/suoju'));
                } else {
                    $this->error('提交错误');
                }
            } else {
                $this->error('提交错误');
            }
        } else {
            $this->error('提交错误');
        }
    }

    //删除锁具配件
    public function suoju_delete() {
        $Request = Request::instance();
        if ($Request->isPost()) {
            $pid = $Request->param('pid');
            $name = $Request->param("name");
            if (empty($pid)) {
                $this->error('传入参数错误');
            }
            if ($name == 'delone') {
                // 单条删除操作
                Db::name('fittings_lock')->where('lid', $pid)->delete();
                //删除库存
                StockpileLock::destroy(['st_lid' => $pid]);

                $this->success('删除成功', Url::build('product/suoju'));
            } elseif ($name == 'delallattr') {
                // 多条删除操作
                $arrUid = explode(",",$pid);
                if (!empty($arrUid)) {
                    $i=0;
                    foreach ($arrUid as $key=>$val) {
                        Db::name('fittings_lock')->where('lid', $val)->delete();
                        //删除库存
                        StockpileLock::destroy(['st_lid' => $val]);

                        $i++;
                    }
                    $this->success($i.' 条记录删除成功', Url::build('product/suoju'));
                }
            } else {
                // 不执行操作
                $this->error('传入参数错误');
            }
        }
    }

    //其他属性
    public function others()
    {
        $baobian = Db::name('others_baobian')->select();
        $assign = [
            'title' => '其他属性',
            'baobian' => $baobian,
            'empty' => '<tr><td colspan="10" align="center">当前条件没有查到数据</td></tr>',
        ];
        $this->assign($assign);
        return $this->fetch();
    }

    //修改金额
    public function others_edit() {
        // {pid:e,bamo:bamo,qhjc:qhjc,qhdz:qhdz,qhdzamo:qhdzamo}
        $Request = Request::instance();
        $pid = $Request->param('pid');
        $bamo = $Request->param('bamo');
        $qhjc = $Request->param('qhjc');
        $qhdz = $Request->param('qhdz');
        $qhdzamo = $Request->param('qhdzamo');

        if ($pid <= '0') {
            $this->error('参数错误');
        }

        if ($Request->isPost()) {
            if (is_numeric($bamo)==false || is_numeric($qhjc)==false || is_numeric($qhdz)==false || is_numeric($qhdzamo)==false) {
                $this->error('输入错误');
            }
            Db::name('others_baobian')->where('bid', $pid)->update([
                'bamo' => $bamo,
                'qhjc' => $qhjc,
                'qhdz' => $qhdz,
                'qhdzamo' => $qhdzamo,
            ]);
            $this->success('修改成功', Url::build('product/others'));
        } else {
            $this->error('参数错误');
        }
    }
}