<?php

namespace app\model;

use think\Model;

class User extends Model
{
    // 设置只读字段
    protected $readonly = ['id', 'openid', 'platform'];

    /**
     * 用户归还雨伞后，扣除费用，退还押金
     *
     * @param $id
     * @param $refund
     * @param $deposit
     * @return $this
     */
    public function returnBack($id, $refund, $deposit)
    {
        return $this
            ->field('deposit, usablemoney, id')
            ->where(['deposit' => ['>=', $deposit], 'id' => $id])
            ->inc('usablemoney', $refund)
            ->dec('deposit', $deposit)
            ->update();
    }

    public function reduceDeposit($id, $deposit)
    {
        return $this->where(['deposit' => ['>=', $deposit], 'id' => $id])
                    ->setDec('deposit', $deposit);
    }

    public function payDeposit($id, $money)
    {
        return $this->where(['usablemoney' => ['>=', $money], 'id' => $id])
                    ->inc('deposit', $money)
                    ->setDec('usablemoney', $money);

    }

    public function payMore($id, $payWithUsableMoney, $deposit)
    {
        return $this->where(['usablemoney' => ['>=', $payWithUsableMoney], 'id' => $id])
                    ->dec('usablemoney', $payWithUsableMoney)
                    ->setInc('deposit', $deposit);
    }

    /**
     * 更新用户提现列表（减少）
     * @param $id
     * @param $refund
     * @return int
     */
    public function refund($id, $refund)
    {
        return $this->where('refund', '>=', $refund)
                    ->where('id', $id)
                    ->setDec('refund', $refund);
    }
}
