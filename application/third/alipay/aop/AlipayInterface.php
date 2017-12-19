<?php

interface AliPayInterface
{
    public function setBizContent($bizContent);
    public function getBizContent();
    public function getApiMethodName();
    public function setNotifyUrl($notifyUrl);
    public function getNotifyUrl();
    public function setReturnUrl($returnUrl);
    public function getReturnUrl();
    public function getApiParas();
    public function getTerminalType();
    public function setTerminalType($terminalType);
    public function getTerminalInfo();
    public function setTerminalInfo($terminalInfo);
    public function getProdCode();
    public function setProdCode($prodCode);
    public function setApiVersion($apiVersion);
    public function getApiVersion();
    public function setNeedEncrypt($needEncrypt);
    public function getNeedEncrypt();
}