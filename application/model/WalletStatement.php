<?php

namespace app\model;

use think\Model;

class WalletStatement extends Model
{
    public function getStatement($uid, $start, $offset)
    {
        return $this->field('type,amount,time')
                    ->where('uid', $uid)
                    ->order('time', 'desc')
                    ->limit($start, $offset)
                    ->select();
    }

    public function updateTypeByRelatedId($relatedId, $type)
    {
        return $this->execute('UPDATE '.$this->getTable().' SET type=?,time=? WHERE related_id=?', [
            $type,
            date('Y-m-d H:i:s'),
            $relatedId
        ]);
    }
}
