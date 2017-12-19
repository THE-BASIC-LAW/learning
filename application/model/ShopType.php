<?php

namespace app\model;

use think\Model;

class ShopType extends Model
{

    public function getIdAndType($id = null)
    {
        if (is_null($id)) {
            return $this->field('id,type')->select();
        } else {
            return $this->field('id,type')->select($id);
        }
    }
}
