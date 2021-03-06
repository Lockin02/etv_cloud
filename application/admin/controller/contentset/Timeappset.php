<?php

namespace app\admin\controller\contentset;

use app\admin\model\DeviceBasics;
use app\common\controller\Backend;
use app\admin\controller\contentset\Customlist;
use think\Cache;
use think\Db;

/**
 * APP定时启动管理
 *
 * @icon fa fa-circle-o
 */
class Timeappset extends Backend
{
    
    /**
     * TimingAppSetting模型对象
     */
    protected $model = null;

	protected $modelValidate = true;

	protected $modelSceneValidate = true;

	protected $admin_id = null;

	protected $noNeedLogin = [];
	protected $noNeedRight = [];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('TimingAppSetting');

        $this->admin_id = $this->auth->id;
    }

    public function index()
    {
	    //设置过滤方法
	    $this->request->filter(['strip_tags']);
	    if ($this->request->isAjax())
	    {
		    $this->relationSearch = true;
		    $this->searchFields = "custom.custom_id";

		    $Customlist_class = new Customlist();
		    $where_customid['zxt_timing_app_setting.custom_id'] = ['in', $Customlist_class->custom_id($this->admin_id)];

		    list($where, $sort, $order, $offset, $limit) = $this->buildparams();
		    $total = $this->model
			    ->where($where)
			    ->with('custom')
			    ->order($sort, $order)
			    ->count();

		    $list = $this->model
			    ->where($where)
			    ->with('custom')
			    ->with('app')
			    ->order($sort, $order)
			    ->limit($offset, $limit)
			    ->select();

		    $list = collection($list)->toArray();
		    $result = array("total" => $total, "rows" => $list);

		    return json($result);
	    }
	    return $this->view->fetch();
    }

	/**
	 * 添加
	 */
	public function add()
	{
		if ($this->request->isPost())
		{
			$params = $this->request->post("row/a");
			if ($params)
			{
				try
				{
					//是否采用模型验证
					if ($this->modelValidate)
					{
						if(!$params['mac_ids']){
							$this->error(__('Mac ids set option error'));
						}
						$name = basename(str_replace('\\', '/', get_class($this->model)));
						$validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : true) : $this->modelValidate;
						$this->model->validate($validate);
					}
					//数据处理
					$params = $this->params_handle($params);
					$result = $this->model->allowField(true)->save($params);
					if ($result !== false)
					{
						$this->success();
					}
					else
					{
						$this->error($this->model->getError());
					}
				}
				catch (\think\exception\PDOException $e)
				{
					$this->error($e->getMessage());
				}
			}
			$this->error(__('Parameter %s can not be empty', ''));
		}

		// 获取账号绑定的客户列表
		$Customlist_class = new Customlist();
		$get_custom_list = $Customlist_class->custom_list($this->admin_id);
		if(empty($get_custom_list))
			$this->error(__('You have no permission'));
		Cache::set($this->admin_id.'-message-customlist', array_column($get_custom_list, 'id'), 36000); //设置缓存10小时
		foreach ($get_custom_list as $key=>$value){
			$custom_list[$value['id']] = $value['custom_name'];
		}
		// 获取选项列表
		$this->get_option();

		//获取APP列表
		$first_custom_id = $get_custom_list[0]['id'];
		$bind_app_custom_list = Db::name('timing_app_custom')->where("find_in_set($first_custom_id,custom_id)")->field('time_app_id')->select();
		if(!empty($bind_app_custom_list)){
			$app_ids = array_column($bind_app_custom_list, 'time_app_id');
			$app_resource_list = Db::name('timing_app_resource')->where('id','in',$app_ids)->field('id,title')->select();
			foreach ($app_resource_list as $key=>$value){
				$app_list[$value['id']] = $value['title'];
			}
		}else{
			$app_list = [];
		}

		$this->view->assign('app_list', $app_list);
		$this->view->assign('custom_list', $custom_list);
		return $this->view->fetch();
	}

	/**
	 * 修改
	 */
	public function edit($ids = NULL)
	{
		$row = $this->model->get($ids);
		if (!$row)
			$this->error(__('No Results were found'));

		if ($this->request->isPost())
		{
			$params = $this->request->post("row/a");
			if ($params)
			{
				try
				{
					//是否采用模型验证
					if ($this->modelValidate)
					{
						if(!$params['mac_ids']){
							$this->error(__('Mac ids set option error'));
						}
						$name = basename(str_replace('\\', '/', get_class($this->model)));
						$validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : true) : $this->modelValidate;
						$row->validate($validate);
					}
					//数据处理
					$params = $this->params_handle($params);
					$result = $row->allowField(true)->save($params);
					if ($result !== false)
					{
						$this->success();
					}
					else
					{
						$this->error($row->getError());
					}
				}
				catch (\think\exception\PDOException $e)
				{
					$this->error($e->getMessage());
				}
			}
			$this->error(__('Parameter %s can not be empty', ''));
		}

		//获取设备列表
		$where['custom_id'] = $row['custom_id'];
		$selected = explode(",", $row['mac_ids']);
		$nodeList = DeviceBasics::getDeviceTreeList($where, $selected);

		// 获取选项列表
		$this->get_option();
		$this->view->assign("row", $row);
		$this->view->assign("nodeList", $nodeList);
		return $this->view->fetch();
	}

	/**
	 * 获取选项列表
	 */
	public function get_option(){

		//重复设置
		$repeat_set_info = config('repeat_set');
		foreach($repeat_set_info as &$repeat_set_value){
			$repeat_set_value = __($repeat_set_value);
		}
		//周期,星期几
		$weekday_info = config('weekday');
		foreach($weekday_info as &$wv){
			$wv = __($wv);
		}
		//跳转至
		$out_to_info = config('app_break_out_to');
		foreach ($out_to_info as &$ov){
			$ov = __($ov);
		}

		$this->view->assign('repeat_set_info', $repeat_set_info);
		$this->view->assign('weekday_info', $weekday_info);
		$this->view->assign('out_to_info', $out_to_info);
	}

	public function get_app_list(){
		$params = $this->request->get();
		$custom_id = $params['custom_id'];
		$bind_list = Db::name('timing_app_custom')->where("find_in_set($custom_id, custom_id)")->field('time_app_id')->select();
		$time_app_ids = array_column($bind_list, 'time_app_id');
		$time_app_list = Db::name('timing_app_resource')->where('id','in',$time_app_ids)->field('id,title')->select();
		echo json_encode($time_app_list);
	}

	/**
	 * 参数处理
	 */
	private function params_handle($params){
		//重复设置 / 时间设置
		if($params['repeat_set'] == 'no-repeat'){
			$params['weekday'] = 0;
		}elseif($params['repeat_set'] == 'everyday'){
			$params['no_repeat_date'] = date("Y-m-d");
			$params['weekday'] = '1,2,3,4,5,6,7';
		}elseif($params['repeat_set'] == 'm-f'){
			$params['no_repeat_date'] = date("Y-m-d");
			$params['weekday'] = '1,2,3,4,5';
		}elseif($params['repeat_set'] == 'user-defined'){
			$params['no_repeat_date'] = date("Y-m-d");
			$params['weekday'] = implode(",", $params['weekday']);
		}

		return $params;
	}
    

}
