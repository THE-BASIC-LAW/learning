<?php namespace app\controller\cp;

use app\controller\Cp;
use app\lib\Api;
use app\model\Menu;

class ItemCp extends Cp
{

    public function list()
    {
        if (isset($GLOBALS['do'])) {
            switch ($GLOBALS['do']) {

                case 'add':
                    if ($this->request->isPost()) {
                        Menu::create([
                            'subject' => $this->request->post('subject', null, 'trim'),
                            'price' => (double)$this->request->post('price', null, 'trim'),
                            'desc' => $this->request->post('desc', null, 'trim'),
                            'content' => ''
                        ]);
                        Api::output();
                    }
                    return view('cp/item_cp/add');
                    break;

                case 'edit':

                    $menuInfo = Menu::get($this->request->get('id'));
                    if ($this->request->isPost()) {
                        $menuInfo->subject = $this->request->post('subject', null, 'trim');
                        $menuInfo->price = (double)$this->request->post('price', null, 'trim');
                        $menuInfo->desc = $this->request->post('desc', null, 'trim');
                        $menuInfo->save();
                        Api::output();
                    }
                    return view('cp/item_cp/edit', compact(
                        'menuInfo'
                    ));
                    break;

                default:
            }
        }
        $menu = Menu::all();
        $this->assign('menu', $menu);
        return $this->fetch();
    }
}
