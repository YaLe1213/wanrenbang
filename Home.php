<?php

namespace app\api\controller;

use think\Session;
use think\Db;
use \think\Cache;
use \think\Controller;
use think\Loader;
use think\Request; 

/**
 * 首页信息
 * @package app\api\controller
 */
class Home extends Controller
{

	/**
	 * 意见反馈类型
	 * @return [type] [description]
	 */
	public function feedbackcate()
	{
		$data = Db::name('feedback_cate')->where(['is_show'=>'true'])->order('sort desc')->field('id,name')->select();
		return json_success(0, '意见反馈类型', $data);
	}
/*******************************************帮助******************************************/
	/**
	 * 帮助类型
	 * @return [type] [description]
	 */
	public function helpcate()
	{
		$data = Db::name('help_cate')->where(['is_show'=>'true'])->order('sort desc')->field('id,name,image')->select();
		foreach ($data as $key => $value) {
			$data[$key]['list'] = Db::name('help')->where('help_cate_id',$value['id'])->where('is_show','true')->order('sort desc')->limit(0,4)->field('id,title')->select();
		}
		return json_success(0, '帮助类型', $data);
	}

	/**
	 * 帮助详情
	 * @return [type] [description]
	 */
	public function helpdetails()
	{
		$id = $this->request->has('id') ? $this->request->param('id') : 0;
		if ($id > 0) {
			$res = Db::name('help')->alias('h')->join('help_cate hc','hc.id=h.help_cate_id')->where('h.id',$id)->field('hc.name,h.title,h.content,h.create_time')->find();
			if ($res) {

				$res['create_time'] = date('Y-m-d H:i',$res['create_time']);
				return json_success(1,$res['title'], $res);
			} else {
				return json_error(0, '数据错误！');
			}
		} else {
			return json_error(0, '数据错误！');
		}
	}

	/**
	 * 帮助底部信息
	 * @return [type] [description]
	 */
	public function helpbot()
	{
		$data = Db::name('help_bot')->field('image,name,msg,url')->select();
		foreach ($data as $key => $value) {
			$data[$key]['image'] = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'].$value['image'];
		}
		return json_success(1, '帮助底部信息', $data);
	}


/*******************************************轮播图BANNER******************************************/

	/**
	 * 轮播图 banner
	 * @return [type] [description]
	 */
	public function banner()
	{
		$data = Db::name('lbt')->where('is_show','true')->field('id,image,path,create_time')->order('sort desc')->select();
		foreach ($data as $key => $value) {
			$data[$key]['create_time'] = date("Y-m-d H:i",$value['create_time']);
		}
		return json_success(1, 'banner', $data);
	}

/*******************************************任务******************************************/
	/**
	 * 任务类型
	 * @return [type] [description]
	 */
    public function cate()
    {
    	$data = Db::name('project_cate')->field('id,name,price,num,share')->select();

    	return json_success(1, '任务类型', $data);
    }

    /**
     * 任务类型
     * @return [type] [description]
     */
    public function projectcate()
    {
        $data = Db::name('project_cate')->field('id,name,price,num,share')->select();
        $data = array_unshift($data, [
            'id'=>0,
            'name'=>'综合任务',
            'price'=>'',
            'num'=>'',
            'share'=>'',
        ]);

        return json_success(1, '任务类型', $data);
    }

/*******************************************规则******************************************/

	/**
	 * 规则列表
	 * @return [type] [description]
	 */
	public function ruleList()
	{
		$data = Db::name('rule')->field('id,title,update_time')->select();
		foreach ($data as $key => $value) {
			$data[$key]['update_time'] = date("Y-m-d H:i", $value['update_time']);
		}
		return json_success(1, '规则列表', $data);
	}

	/**
	 * 获取规则详情
	 * @return [type] [description]
	 */
	public function getRuleDetails()
	{
		$id = $this->request->param('id');
		$info = Db::name('rule')->where('id',$id)->field('title,content')->find();
		if ($info) {
			return json_success(1, $info['title'], $info);
		} else {
			return json_error(0, '数据错误！');
		}
	}


/*******************************************APP_CONFIG******************************************/

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




/*******************************************公告噶******************************************/

	/**
	 * 公告
	 * @return [type] [description]
	 */
	public function notice()
	{
		$data = Db::name('notice_board')->where('is_show','true')->field('id,title,path')->order('sort desc')->select();
		return json_success(1, '公告',$data);
	}




	public function test()
	{
		dump($_SERVER);
	}


	/**
	 * 每日任务剩余时间戳
	 */
	public function getdatetime()
	{
		$end = strtotime(date("Y-m-d", time())."23:59:59");
		return json_success(1, 'success', ['datetime'=>$end-time()]);
	}









}