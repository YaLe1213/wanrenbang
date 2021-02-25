<?php

namespace app\api\controller;

use think\Session;
use think\Db;
use \think\Cache;
use \think\Controller;
use think\Loader;
use think\Request; 

/**
 * 任务
 * @package app\api\controller
 */
class Project extends Controller
{
    public $uid='';
    protected function _initialize()
    {
        // 解密token
        $arr=Request::instance()->header('token');

			 if($arr==null){
			 	http_response_code(401);
			 	exit(json_error(2,'未登录',[]));
			 }

			 $token = json_decode(checkToken($arr),true);
			 if($token['code']!=='200'){
			 	http_response_code(401);
			 	exit(json_error(2,'登录信息过期',$token));
			 }

         $tokens=check($arr);
         $this->uid=json_decode(json_encode($tokens),true)['uid'];
//        $this->uid=1;
                // 进行中 状态的订单   并且超时的任务
        $ordering_id = Db::name('project_order')->where(['status'=>1, 'end_time'=>['<=', time()]])->column('id');
        Db::name('project_order')->where('id', 'in', $ordering_id)->update(['status'=>4, 'ended_time'=>time()]);  // 更改订单状态

    }

    /**
     * 我的浏览记录
     * @return [type] [description]
     */
    public function browse()
    {
    	$data = Db::name('project_browse')->where('users_id',$this->uid)->select();
    	return json_success(1,'浏览记录');
    }
    /**
     * 任务 数量
     * @return [type] [description]
     */
    public function projectnum()
    {
        $data = [
            'ing'=>Db::name('project_order')->where('users_id',$this->uid)->where('status', 'in', '1,2')->count(),
            'share'=>Db::name('share')->where('users_id',$this->uid)->count(),
            'project'=>Db::name('project')->where('users_id',$this->uid)->count(),
         ];
         return json_success(1, 'success',$data);
    }



    /**
     * 模板列表
     * @return [type] [description]
     */
    public function projectsave()
    {
        $data = Db::name('project_save')->where('users_id',$this->uid)->field('id,title,create_time')->select();
        foreach ($data as $key => $value) {
            $data[$key]['create_time'] = date("Y-m-d",$value['create_time']);
        }
        return json_success(1, '模板列表', $data);
    }

    /**
     * 删除模板
     * @return [type] [description]
     */
    public function savedelete()
    {
        $id = $this->request->param('id',1);
        if (false == Db::name('project_save')->where('id',$id)->field('id')->find()) {
            return json_error(0, '数据错误！');
        }
        $res = Db::name('project_save')->where('id',$id)->delete();
        if ($res) {
            return json_success(1, '删除成功');
        } else {
            return json_error(0, '删除失败');
        }
    }
    /**
     * 模板详情
     * @return [type] [description]
     */
    public function saveinfo()
    {
        $id = $this->request->param('id',1);

        $info = Db::name('project_save')->where('id',$id)->find();
        $info['project_flow'] = json_decode($info['project_flow'], true);
        $info['up_flow'] = json_decode($info['up_flow'], true);
        return json_success(1, $info['title'], $info);
    }


    /**
     * 发布任务
     * @return [type] [description]
     */
    public function releaseproject()
    {
        $post = $this->request->param();
        $validate = new \think\Validate([
            ['project_cate_id', 'require', '请选择标签'],
            ['title', 'require', '请输入标签'],
            ['tag', 'require', '请输入标签'],
            ['price', 'require', '请输入单价'],
            ['num', 'require', '请输入数量'],
            ['share_num', 'require', '请输入分享次数'],
            ['limit_end_time', 'require', '请选择限时交单时间'],
            ['limit_review_time', 'require', '请选择限时审核时间'],
            ['users_limit', 'require', '请选择限制次数'],
            ['end_time', 'require', '请输入任务截止时间'],
            ['equipment', 'require', '请选择设备限制'],
            ['description', 'require', '请输入任务说明'],
            ['project_flow', 'require', '请添加步骤'],
            ['up_flow', 'require', '请添加上传验证'],
            ['agree_rule', 'require', '请勾选同意发布规则'],
        ]);
        if (!$validate->check($post)) {
            return json_error(0,$validate->getError());
        }
        $post['create_time'] = time();
        $post['users_id'] = $this->uid;
        $post['status'] = 0;
        $post['is_stop'] = 'false';

            // 获取发布任务手续费
            $config = Db::name('project_parameter')->where('id',1)->find();
            // 计算手续费
            if (!empty($this->level['release'])) { // 当前等级下的减免发布手续不为空
                            // 换算百分比 * 原手续费
                $shouxu = bcmul($config['release'], bcmul($this->level['release'], 100), 2);    // 当前等级折扣后
            } else {
                $shouxu = $config['release'];
            }
            $use_balance = Db::name('users')->where('id',$this->uid)->value('use_balance');  // 会员可用金额
            // 检测账户余额
            if (bcsub($use_balance, bcmul($post['price'], $post['num'], 2), 2) < 0) {
                return json_error(0, '账户余额不足，请先充值');
            }
            // 检测手续费
            // if (bcsub(bcsub($use_balance, bcmul($post['price'], $post['num'], 2), 2), $shouxu, 2)) {
            //     return json_error(0, '账户余额不足，请先充值2');
            // }
            $post['project_flow'] = json_encode($post['project_flow']);
            $post['up_flow'] = json_encode($post['up_flow']);

        // 保存模板
        if ($post['save'] == 'true') {
            Db::name('project_save')->insert($post);
        }

        // 启动事务
        Db::startTrans();
        try{
            // 添加任务
            model('project')->save($post); 
            // 修改余额  减去任务金额  和 手续费
            Db::name('users')->where('id',$this->uid)->update(['use_balance'=>bcsub(bcsub($use_balance, bcmul($post['price'], $post['num'], 2), 2), $shouxu, 2)]);

            // 增加冻结金额
            Db::name('users')->where('id',$this->uid)->setInc('frozen_balance', bcmul($post['price'], $post['num'], 2));
            // 添加金额记录
            Db::name('users_trend')->insert(['users_id'=>$this->uid, 'price'=>bcmul($post['price'], $post['num'], 2),'cate'=>3,'status'=>2,'code'=>time().mt_rand(100000,999999),'create_time'=>time(),'cate2'=>0]); // 冻结记录

            Db::name('users_trend')->insert(['users_id'=>$this->uid, 'price'=>$shouxu,'cate'=>6,'status'=>2,'code'=>time().mt_rand(100000,999999),'create_time'=>time(),'cate2'=>0]); // 手续费记录

            // 提交事务
            Db::commit();    
            return json_success(1, '发布成功');
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return json_error(0, '发布失败:'.$e->getMessage());
        }
    }

    /**
     * 任务详情
     * @return 任务标题  会员id 任务id 任务类型 任务标签 剩余次数 会员限制 赏金 显示完成 限时审核 已完成人数 托管赏金 会员头像 会员id 会员实名 会员等级 会员保证金 任务说明 设备限制 任务流程  上传验证
     * @return [type] [description]
     */
    public function projectDetails()
    {
        $id = $this->request->has('id') ? $this->request->param('id') : 0 ;
        if ($id > 0) {

            // 添加浏览记录
            Db::name('project_browse')->insert(['project_id'=>$id,'users_id'=>$this->uid,'create_time'=>time()]);

            $data = Db::name('project')
                    ->alias('p')
                    ->join('users u', 'p.users_id=u.id')
                    ->join('project_cate pc', 'pc.id=p.project_cate_id')
                    ->where('p.id',$id)
                    ->field('p.id,p.title,u.UID,pc.name,p.users_id,p.tag,p.price,p.num,p.share_num,p.limit_end_time,p.limit_review_time,p.users_limit,p.end_time,p.equipment,p.description,p.project_flow,p.up_flow,p.create_time,p.end_users_num,u.avatarUrl,u.nickName,u.UID')
                    ->find();
                    $data['project_flow'] = json_decode($data['project_flow'],true);
                    $data['up_flow'] = json_decode($data['up_flow'],true);
            if (!empty($post['share_id'])) {
                $data['help_id'] = Db::name('share_help')->insertGetId(['users_id'=>$this->uid, 'share_id'=>$post['share_id'], 'is_help'=>0, 'create_time'=>time(),'update_time'=>'', 'price'=>'']);
            } else {
                $data['help_id'] ='';
            }

            $data['avatarUrl'] = addavatarUrl($data['avatarUrl']);   // 会员头像添加域名
            $data['end_users_num'] = Db::name('project_order')->where('project_id',$id)->where('status','in','1,2,3,5')->count();  // 任务已完成数量
            $data['yunum'] = $data['num'] - $data['end_users_num'] > 0 ?$data['num'] - $data['end_users_num'] : 0;  // 剩余次数
            $data['sumprice'] = $data['num'] * $data['price'];
            return json_success(1, $data['title'], $data);


        } else {
            return json_error(0, '数据错误');
        }
    }

    /**
     * 修改任务
     * @return [type] [description]
     */
    public function editproject()
    {
        $id = $this->request->param('id', 0);
        $info = Db::name('project')->where(['id'=>$id, 'is_stop'=>'true'])->find();
        if ($info) {
            // 手续参数
            $config = Db::name('project_parameter')->where('id',1)->find();
            // 任务修改次数
            $editnum = DB::name('project_editlog')->where(['project_id'=>$id,'cate'=>3])->count();   
            $post = $this->request->post();
            if (!empty($post['project_flow'])) {
                $data['project_flow'] = json_encode($post['project_flow']);
            } else {
                return json_error(0, '请填写任务流程');
            }
            if (!empty($post['up_flow'])) {
                $data['up_flow'] = json_encode($post['up_flow']);
            } else {
                return json_error(0, '请填写上传验证');
            }
            // 判断修改次数
            if ($editnum >0 ) {   // 不是第一次修改  
                // 检测修改手续费
                $editproject = Db::name('project_parameter')->where('id',1)->value('editproject');
                $use_balance = Db::name('users')->where('id',$this->uid)->value('use_balance');
                if (bcsub($use_balance, $editproject, 2) < 0 ) {
                    return json_error(0, '账户余额不足，请先充值');
                }
            }
                // 启动事务
            Db::startTrans();
            try{
                // 修改
                Db::name('project')->where('id',$id)->update($data);
                if ($editnum > 0) {  // 减手续
                    Db::name('users')->where('id',$this->uid)->setDec('use_balance',$editproject);
                    // 添加记录
                    Db::name('users_trend')->insert(['users_id'=>$this->uid, 'price'=>$editproject, 'cate'=>6, 'status'=>2, 'code'=>time()+mt_rand(100000,999999), 'create_time'=>time(), 'cate2'=>0]);
                }
                // 添加修改记录
                Db::name('project_editlog')->insert(['project_id'=>$id, 'users_id'=>$this->uid, 'cate'=>3, 'num'=>0, 'create_time'=>time()]);
                // 提交事务
                Db::commit();    
                return json_success(1, '修改成功');
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return json_error(0, '修改失败');
            }
        } else {
            return json_error(0, '数据错误！');
        }
    }
    /**
     * 获取任务数据
     * @return [type] [description]
     */
    public function getproject()
    {
        $id = $this->request->param('id', 0);
        $info =Db::name('project')->where(['id'=>$id, 'is_stop'=>'true'])->field('id,project_cate_id,title,tag,price,num,share_num,limit_end_time,limit_review_time,users_limit,end_time,equipment,description,project_flow,up_flow')->find();
        if ($info) {
            $info['project_flow'] = json_decode($info['project_flow'], true);     
            $info['up_flow'] = json_decode($info['up_flow'], true);    
            return json_success(1, $info['title'], $info); 
        } else {
            return json_error(0, '数据错误！');
        }
    }


/////////////////////////////////////////////////
///
///                 任务大厅
///                 
/////////////////////////////////////////////////



    /**
     * 首页推荐任务
     * @return [type] [description]
     */
    public function projecthall()
    {
        // 分页条件
        $page = $this->request->param('page', 1);

        // 数据筛选条件
        $where = [
            'p.is_stop'=>'false',    // 没有暂停
            'p.status'=>1,     // 进行中
            'p.is_tui'=>'true',     // 推荐
        ];
        // 获取的字段
        $field = [
            'p.id',   // 任务id
            'u.avatarUrl',   // 会员头像
            'p.users_id',   // 会员id
            'p.title',      // 任务头像
            'p.price',      // 任务赏金
            'p.num',        // 任务中数量
            'pc.name',      // 任务类型名称
            'p.tag',        // 标签
            'p.redpacked',     // 红包图标  1 显示 0 不显示
            'ul.image',     // 会员等级图标
            'p.create_time'
        ];
        $order = 'create_time desc';
        $data = Db::name('project')->alias('p')->join('users u', 'u.id=p.users_id')->join('project_cate pc', 'p.project_cate_id=pc.id')->join('users_level ul', 'ul.id=u.users_level_id')->field($field)->where($where)->order($order)->select();
        foreach ($data as $key => $value) {
            $data[$key]['avatarUrl'] = addavatarUrl($value['avatarUrl']);
            $count = Db::name('project_order')->where('status', 'in', '1,2,3,5')->where('project_id',$value['id'])->count();
            $data[$key]['usenum']= $count;
            $data[$key]['yunum'] =  $value['num'] - $count;       // 剩余数量
            if ($value['redpacked'] > 0) {
                $data[$key]['hongbao'] = 1;
            } else {
                $data[$key]['hongbao'] = 0;
            }
            unset($data[$key]['num']);  // 删除任务总数
            unset($data[$key]['redpacked']);  // 删除红包金额
            unset($data[$key]['create_time']);  // 删除发布时间
        }
        return json_success(1, '推荐任务',$data);
    }

    /**
     * 获取我关注的会员id
     * @return [type] [description]
     */
    public function getfollowids()
    {
        return Db::name('users_follow')->where('users_id1',$this->uid)->column('id');
    }

    /**
     * 任务大厅
     * @return [type] [description]
     */
    public function projecthalls()
    {
        $cate = $this->request->param('cate');  // 任务分类
        $sort = $this->request->param('sort');  // 排序
        // 分页条件
        $page = $this->request->param('page', 1);
        $num = 30;
        // 数据筛选条件
        $where = [
            'p.is_stop'=>0,    // 没有暂停
            'p.status'=>1,     // 进行中
            'p.project_cate_id'=>$cate,
        ];
        if (isset($cate) && $cate == 0) {
            unset($where['p.project_cate_id']);
        }
        // 排序
        if (!empty($sort) && $sort == 1) {   //最新
        
        } else if (!empty($sort) && $sort == 2) {    // 置顶
            $where['p.is_top'] = 'true';
        } else if (!empty($sort) && $sort == 3) {    // 高价
            $where['p.price'] = ['>=', 10];
        } else if (!empty($sort) && $sort == 4) {    // 关注
            $ids = $this->getfollowids();
            $where['u.id'] = ['in', $ids];
        } else {
            return json_error(0, '数据错误！');
        }
        // 获取的字段
        $field = [
            'p.id',   // 任务id
            'u.avatarUrl',   // 会员头像
            'p.users_id',   // 会员id
            'p.title',      // 任务头像
            'p.price',      // 任务赏金
            'p.num',        // 任务中数量
            'pc.name',      // 任务类型名称
            'p.tag',        // 标签
            'p.redpacked',     // 红包图标  1 显示 0 不显示
            'ul.image',     // 会员等级图标
            'p.create_time',    // 发布时间
            'p.is_top',     // 是否置顶
        ];
        $data = Db::name('project')->alias('p')->join('users u', 'u.id=p.users_id')->join('project_cate pc', 'p.project_cate_id=pc.id')->join('users_level ul', 'ul.id=u.users_level_id')->field($field)->where($where)->order('create_time desc')->select();
        foreach ($data as $key => $value) {
            $data[$key]['avatarUrl'] = addavatarUrl($value['avatarUrl']);
            $count = Db::name('project_order')->where('status', 'in', '1,2,3,5')->where('project_id',$value['id'])->count();
            $data[$key]['usenum']= Db::name('project_order')->where('status', 'in', '1,2,3,5')->where('project_id',$value['id'])->count();
            $data[$key]['yunum'] =  $value['num'] - $count;       // 剩余数量
            if ($value['redpacked'] > 0) {
                $data[$key]['hongbao'] = 1;
            } else {
                $data[$key]['hongbao'] = 0;
            }
            unset($data[$key]['num']);  // 删除任务总数
            unset($data[$key]['redpacked']);  // 删除红包金额
            unset($data[$key]['create_time']);  // 删除红包金额
        }
        return json_success(1, '推荐任务',$data);
    }

    /**
     * 任务--头条
     * @return [type] [description]
     */
    public function projectou()
    {
        // 分页
        $page = $this->request->param('page');
        if (empty($page)) {
            $page = 1;
        }
        $num = 20;
        // 数据筛选条件
        $where = [
            'is_stop'=>'false',    // 没有暂停
            'status'=>1,     // 进行中
            'is_tou'=>'true',     // 头条
        ];
        $data = Db::name('project')->where($where)->field('id,title,price')->page($page, $num)->order('create_time desc')->select();
        return json_success(1, '头条', $data);
    }

    /**
     * 立即赚钱
     * @return [type] [description]
     */
    public function makemoneynew()
    {
        $page = $this->request->param('page', 1);

        $num = 1;
        
        $data = Db::name('project')->alias('p')->join('users u', 'u.id=p.users_id')->where([ 'p.is_stop'=>'false', 'p.status'=>1,  'p.is_tui'=>'true', ])->field('p.price,p.id,p.users_id,p.tag,u.avatarUrl')->page($page, $num)->order('p.create_time desc')->select();
        foreach ($data as $key => $value) {
            $data[$key]['avatarUrl'] = addavatarUrl($value['avatarUrl']);
        }
        return json_success(1, '立即赚钱', $data);
    }







}