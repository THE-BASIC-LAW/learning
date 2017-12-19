<?php

/**
 * 只允许添加与框架有关的函数
 */


/**
 * @param string      $name
 * @param null|string $default
 * @return mixed
 */
function env($name, $default = null)
{
    return \think\Env::get($name, $default);
}