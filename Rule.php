<?php

namespace app\api\controller;

use think\Session;
use think\Db;
use \think\Cache;
use \think\Controller;
use think\Loader;
use think\Request; 

/**
 * 规则
 * @package app\api\controller
 */
class Rule extends Controller
{

    /**
     * 充值说明
     * @return [type] [description]
     */
    public function recharge()
    {
        $data = Db::name('app_config')->where('id',1)->field('recharge')->find();
        return json_success(1, '充值说明', $data);
    }


    /**
     * 举报维权
     * @return [type] [description]
     */
    public function reward()
    {
        $data = Db::name('app_config')->where('id',1)->field('reward')->find();
        return json_success(1, '举报维权', $data);
    }


    /**
     * 用户隐私政策
     * @return [type] [description]
     */
    public function usersprivate()
    {
        $data = Db::name('app_config')->where('id',1)->field('usersprivate')->find();
        return json_success(1, '用户隐私政策', $data);
    }


    /**
     * 用户服务政策
     * @return [type] [description]
     */
    public function userservice()
    {
        $data = Db::name('app_config')->where('id',1)->field('userservice')->find();
        return json_success(1, '用户服务政策', $data);
    }

    /**
     * 邀请规则
     * @return [type] [description]
     */
    public function invited()
    {
        $data = Db::name('app_config')->where('id',1)->field('invited')->find();
        return json_success(1, '邀请规则', $data);
    }


    /**
     * 接单规则
     * @return [type] [description]
     */
    public function project()
    {
        $data = Db::name('order_rule')->where('id',1)->field('title,path,time,look,content')->find();
        $data['path'] = addavatarUrl($data['path']);
        return json_success(1, '接单规则', $data);
    }

    /**
     * 保证金设置
     * @return [type] [description]
     */
    public function bond()
    {
        $data = Db::name('app_config')->where('id',1)->field('low_bond,bond_rule')->find();
        return json_success(1, '保证金', $data);
    }

    /**
     * APP配置
     * @return [type] [description]
     */
    public function appconfig()
    {
        $data = Db::name('app_config')->where('id',1)->field('qq,Url,custom,wxqrcode,wxdesc,customdesc,backdesc')->find();
        return json_success(1, 'APP配置', $data);
    }

    /**
     * 首页banner
     * @return [type] [description]
     */
    public function homebanner()
    {
        $data = Db::name('app_config')->where('id',1)->field('activity_image,activity_url')->find();
        return json_success(1, '首页banner', $data);
    }
}