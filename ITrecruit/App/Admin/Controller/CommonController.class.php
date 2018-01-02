<?php
namespace Admin\Controller;
use Think\Controller;
/**
 * 公共Action
 *
 */
class CommonController extends Controller{
	
	//在Controller类中构造方法执行后则会自动调用的方法。
	public function _initialize(){
		//是否登录验证
		if(!isset($_SESSION[C('USER_AUTH_KEY')])){
			$this->redirect("Login/index");
			exit();
		}

		// RBAC认证
		if(C('USER_AUTH_ON')){
			$rbac = new \Org\Util\Rbac();
			if(!$rbac::AccessDecision()){
				$this->error("抱歉！您没有操作权限，请与管理员联系！");
			}
		}
        /*
        //判断是否拥有当前操作权限
        //获取当前被访问的控制器名和方法名
        $controller = strtolower(CONTROLLER_NAME);
        $action = strtolower(ACTION_NAME);
        $nodelist = $_SESSION['nodelist'];
        //判断
        if(empty($nodelist[$controller]) || !in_array($action,$nodelist[$controller])){
            $this->error("抱歉,你没有此操作权限！");
            exit();
        }
        */
	}


	
	public function index() {
		//列表过滤器，生成查询Map对象
		$map = $this->_search();
		if(method_exists($this, '_filter')) {
			$this->_filter($map);
		}
		//判断采用自定义的Model类
		if(!defined(CONTROLLER_NAME)){
		   $model = D(CONTROLLER_NAME);
		}
		
		if(!empty($model)) {
			$this->_list($model, $map);
		}
		$this->display();
		return;
	}
	
	public function add() {
		$this->display('add');
	}
	
	public function insert(){
		$model = D(CONTROLLER_NAME);
		unset( $_POST[$model->getPk()]);
		
		if(false === $model->create()) {
			$this->error($model->getError());
		}
		//保存当前数据对象
		if ($result = $model->add()) { //保存成功
			// 回调接口
			if (method_exists($this, '_tigger_insert')) {
				$model->id = $result;
				$this->_tigger_insert($model);
			}
			
			//成功提示
			$this->success(L('新增成功'));
		} else {
			//失败提示
			$this->error(L('新增失败').$model->getLastSql());
		}
		
	
	}
	
	public function edit() {
		$model = D(CONTROLLER_NAME);
		$id = $_REQUEST[$model->getPk()];
		$vo = $model->find($id);
		$this->assign('vo', $vo);
		$this->display('edit');
	}
	
	public function update() {
		$model = D(CONTROLLER_NAME);
	
		if(false === $model->create()) {
			$this->error($model->getError());
		}
		// 更新数据
		if(false !== $model->save()) {
			// 回调接口
			if (method_exists($this, '_tigger_update')) {
				$this->_tigger_update($model);
			}
			//成功提示
			$this->success(L('更新成功'));
		} else {
			//错误提示
			$this->error(L('更新失败'));
		}
	}
	
	public function del() {
		//删除指定记录
		$model = D(CONTROLLER_NAME);
		if (!empty($model)) {
			$pk = $model->getPk();
			$id = $_REQUEST[$pk];
			if (isset($id)) {
				//批量删除
				$condition = array($pk => array('in', explode(',', $id)));
				if (false !== $model->where($condition)->delete()) {
					$this->success(L('删除成功'));
				} else {
					$this->error(L('删除失败'));
				}
			} else {
				$this->error('非法操作');
			}
		}
	}
	
	//删除状态
	public function delete_tag(){
		//删除指定记录
		$model = M(CONTROLLER_NAME);
		if (!empty($model)) {
			$pk = $model->getPk();
			$id = $_REQUEST[$pk];
			if (isset($id)) {
				
				$condition = array($pk => array('in', explode(',', $id)));
				if (false !== $model->where($condition)->setField('is_delete',1)) {
					$this->success(L('删除成功'));
				} else {
					$this->error(L('删除失败'));
				}
			} else {
				$this->error('非法操作');
			}
		}
	}
	
	/**
	 * 根据表单生成查询条件
	 * 进行列表过滤
	 * @param string $name 数据对象名称
	 * @return HashMap
	 */
	protected function _search($name='') {
		//生成查询条件
		if (empty($name)) {
			$name = CONTROLLER_NAME;
		}
		$model = M($name);
		$map = array();
		foreach ($model->getDbFields() as $key => $val) {
			if (substr($key, 0, 1) == '_')
				continue;
			if (isset($_REQUEST[$val]) && $_REQUEST[$val] != '') {
				$map[$val] = $_REQUEST[$val];
			}
		}
		return $map;
	}
	
	/**
	 * 根据表单生成查询条件
	 * 进行列表过滤
	 * @param Model $model 数据对象
	 * @param HashMap $map 过滤条件
	 * @param string $sortBy 排序
	 * @param boolean $asc 是否正序
	 */
	protected function _list($model, $map = array(), $sortBy = '', $asc = false) {
		
		//排序字段 默认为主键名
		if (!empty($_REQUEST['_order'])) {
			$order = $_REQUEST['_order'];
		} else {
			$order = !empty($sortBy)?$sortBy:$model->getPk();
		}
		
		//排序方式默认按照倒序排列
		//接受 sort参数 0 表示倒序 非0都 表示正序
		if (!empty($_REQUEST['_sort'])) {
			$sort = $_REQUEST['_sort'] == 'asc'?'asc':'desc';
		} else {
			$sort = $asc ? 'asc' : 'desc';
		}
		
		//取得满足条件的记录数
		$count = $model->where($map)->count();
		
		//每页显示的记录数
		if (!empty($_REQUEST['numPerPage'])) {
			$listRows = $_REQUEST['numPerPage'];
		} else {
			$listRows = '10';
		}
		
		
		//设置当前页码
		if(!empty($_REQUEST['pageNum'])) {
			$nowPage=$_REQUEST['pageNum'];
		}else{
			$nowPage=1;
		}
		$_GET['p']=$nowPage;
		
		//创建分页对象
		$p = new \Think\Page($count, $listRows);
		
		
		//分页查询数据
		$list = $model->where($map)->order($order.' '.$sort)
						->limit($p->firstRow.','.$p->listRows)
						->select();
						
		//回调函数，用于数据加工，如将用户id，替换成用户名称
		if (method_exists($this, '_tigger_list')) {
			$this->_tigger_list($list);
		}
		
		
		//分页跳转的时候保证查询条件
		foreach ($map as $key => $val) {
			if (!is_array($val)) {
				$p->parameter .= "$key=" . urlencode($val) . "&";
			}
		}
	
		//分页显示
		$page = $p->show();
		
		//列表排序显示
		$sortImg = $sort;                                 //排序图标
		$sortAlt = $sort == 'desc' ? '升序排列' : '倒序排列';   //排序提示
		$sort = $sort == 'desc' ? 1 : 0;                  //排序方式
		
		
		//模板赋值显示
		$this->assign('list', $list);
		$this->assign('sort', $sort);
		$this->assign('order', $order);
		$this->assign('sortImg', $sortImg);
		$this->assign('sortType', $sortAlt);
		$this->assign("page", $page);
		
		$this->assign("search",			$search);			//搜索类型
		$this->assign("values",			$_POST['values']);	//搜索输入框内容
		$this->assign("totalCount",		$count);			//总条数
		$this->assign("numPerPage",		$p->listRows);		//每页显多少条
		$this->assign("currentPage",	$nowPage);			//当前页码
		
	}
}
?>