<?php namespace app\controller\cp;

use app\lib\Api;
use app\controller\Cp;
use think\Request;
use think\Session;

class ShopCp extends Cp
{
    // 关联的Shop模型
    protected $shop;

    /**
     * 构造函数
     * @access public
     */
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->shop = model('Shop');
    }

    // 商铺信息列表
    public function lists(){
        $access_shops  = null;
        $access_cities = null;
        extract(input());
        $admin_id = $this->admin->adminInfo['id'];
        if (!$this->auth->globalSearch) {
            $access_shops  = $this->auth->getAccessShops();
            $access_cities = $this->auth->getAccessCities();
        }

        $shops = $this->shop->searchShop($_GET, RECORD_LIMIT_PER_PAGE, $access_cities, $access_shops);

        // 获取商铺类型
        $shop_types = model('ShopType')->all();
        foreach ($shop_types as $type) {
            $shopTypes[$type['id']] = $type['type'];
        }

        foreach ($shops as $key => $shop){
            $shops[$key]['logo'] = json_decode($shops[$key]['logo']);
            $shops[$key]['default'] = false;
            if(!$shops[$key]['logo']){
                $type = $shops[$key]['type'];
                $shop_type = $shopTypes[$type];
                $logo = json_decode($shop_type['logo']);
                $shops[$key]['logo'] = $logo;
                $shops[$key]['default'] = true;
            }
            $shops[$key]['carousel'] = json_decode($shops[$key]['carousel']) ? json_decode($shops[$key]['carousel']) : [];
            $shops[$key]['shoptype'] = $shopTypes[$shops[$key]['type']] ? : '无';
        }

        $this->assign([
            'shops'    => $shops,
            'admin_id' => $admin_id,
        ]);
        return $this->fetch();
    }

    // 更新商铺
    public function update(){
        if (!$this->auth->globalSearch && !$this->auth->checkShopIdIsAuthorized($shop_id)) {
            echo 'unauthorized shop';
            exit;
        }
        $shop_id = $_GET['shop_id'];
        $shop    = model('Shop')->get($shop_id);
        // 更新操作
        if(isset($_GET['submit'])){
            if(!input('param.__token__') == Session::get('__token__')){
                Api::output([], 3, '非法操作');
                exit;
            }
            unset($_POST['__token__']);
            $res = $this->admin->shopEdit($shop_id);
            if ($res) {
                Api::output([], 0, '更新成功');
            } else {
                Api::output([], 1, '更新失败');
            }
            exit;
        }
        // 显示编辑界面
        // 获取商铺类型
        $this->assign([
            'shop_types' => model('ShopType')->all(),
            'province'   => $shop['province'],
            'city'       => $shop['city'],
            'area'       => $shop['area'],
            'shop'       => $shop,
        ]);
        return $this->fetch();
    }

    public function updateLogo(){
        if (!$this->auth->globalSearch && !$this->auth->checkShopIdIsAuthorized($shop_id)) {
            echo 'unauthorized shop';
            exit;
        }
        extract(input());
        //  更新logo
        if(isset($submit) && $type=='shop'){
            if(!input('param.__token__') == Session::get('__token__')){
                Api::output([], 3, '非法操作');
                exit;
            }
            $res = $this->admin->updateLogo($shop_id, $data_url);
            if ($res) {
                Api::output([], 0, 'logo更新成功');
            } else {
                Api::output([], 1, 'logo更新失败');
            }
            exit;
        } elseif (isset($submit) && $type=='shop_type'){
            if(!input('param.__token__') == Session::get('__token__')){
                Api::output([], 3, '非法操作');
                exit;
            }
            $files = FileUpload::img(UPLOAD_IMAGE_ROOT_DIR.'logo', UPLOAD_FILE_RELATIVE_DIR_CONTAIN_DOMAIN.'/logo');
            $res = model('ShopType')->get($id)->save(['logo' => json_encode($files)]);
            if ($res) {
                Api::output([], 0, 'logo更新成功');
            } else {
                Api::output([], 1, 'logo更新失败');
            }
            exit;
        }

        // 显示更新文件页面
        $action = "/$mod/$act/$opt?type=$type&page=$page&shop_id=$shop_id&submit=1";
        $this->assign('action', $action);
        return $this->fetch();
    }

    public function updateCarousel(){
        if (!$this->auth->globalSearch && !$this->auth->checkShopIdIsAuthorized($shopid)) {
            echo 'unauthorized shop';
            exit;
        }
        extract(input());
        // 更新操作
        if($submit){
            if(!input('param.__token__') == Session::get('__token__')){
                Api::output([], 3, '非法操作');
                exit;
            }
            $res = $this->admin->carouselUpdate($shop_id, $mats);
            if ($res) {
                Api::output([], 0, '轮播图更新成功');
            } else {
                Api::output([], 1, '轮播图更新失败');
            }
            exit;
        }

        $shop = model('Shop')->get($shop_id);
        // 轮播图
        $action = "/$mod/$act/$opt?submit=1&page=$page&shop_id=$shop_id";
        $this->assign([
            'imgs'   => json_decode($shop['carousel']),
            'action' => $action
        ]);
        return $this->fetch();
    }

    public function add(){
        extract(input());
        if(isset($submit)){
            if(!input('param.__token__') == Session::get('__token__')){
                Api::output([], 3, '非法操作');
            }
            unset($_POST['__token__']);
            $res = $this->admin->shopAdd();
            if($res){
                $url = "/$mod/$act/lists?rst=success";
                $this->redirect($url);
            }
        }
        // 获取商铺类型
        $shop_types = model('ShopType')->all();

        // 所有省份
        $provinces = array_map(function($v){
            return $v['province'];
        }, $GLOBALS['area_nav_tree']);

        $this->assign([
            'provinces'  => $provinces,
            'shop_types' => $shop_types,
        ]);
        return $this->fetch();
    }

    public function shopTypeList(){
        extract(input());
        $where = [];
        if (isset($keyword) && !empty($keyword)) {
            $where['type'] = ['like', '%' . $keyword . '%'];
        }
        $order = 'id DESC';
        $shop_types = model('ShopType')->where($where)->order($order)->paginate(RECORD_LIMIT_PER_PAGE, false, ['query'=>$_GET])->each(function($item){
            $item->logo = json_decode($item->logo);
        });

        $this->assign('shop_types', $shop_types);
        return $this->fetch();
    }

    public function addShopType(){
        extract(input());
        if ($_POST) {
            if($type){
                if(!input('param.__token__') == Session::get('__token__')){
                    Api::output([], 3, '非法操作');
                    exit;
                }
                $res = $this->admin->shopTypeAdd($type);
                if ($res) {
                    Api::output([], 0, '添加成功');
                } else {
                    Api::output([], 1, '添加失败');
                }
            }
            exit;
        }
        return $this->fetch();
    }
}
