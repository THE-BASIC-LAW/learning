<?php
$jjsan_nav_tree = [
	'admin'        => [
		'text' => '管理员中心',
		'sub_nav' => [
            'role' => [
                'opt' => '角色管理',
                'do' => [
                    'add' => '添加角色',
                    'edit' => '编辑角色'
                ]
            ],
            'adminManage' => [
                'opt' => '系统用户管理',
                'do' => [
                    'pass' => '通过',
                    'refuse' => '拒绝',
                    'search' => '搜索',
                    'delete' => '删除',
                    'lock'   => '锁定账户',
                    'unlock' => '解锁账户',
                    'resume' => '恢复账户',
                    'edit'   => '编辑用户',
                ]
            ],
            'installManManage' => [
                'opt' => '维护人员管理',
                'do'  => [
                    'add' => '添加维护人员',
                    'pass' => '通过审核',
                    'delete' => '删除角色',
                    'setCommon' => '普通',
                    'setInstall' => '维护',
                ]
            ],
            'accessVerify' => [
                'opt' => '区域权限审核',
                'do' => [
                    'pass' => '通过',
                    'search' => '搜索',
                ]
            ],
            'shopAccessVerify' => [
                'opt' => '商铺权限审核',
                'do' => [
                    'pass' => '通过',
                    'search' => '搜索',
                ]
            ],
            'accessApply' => [
                'opt' => '权限申请',
                'do' => [
                    'cityApply' => '申请城市',
                    'cityModify' => '修改申请',
                    'cityDelete' => '删除城市权限',
                    'shopApply' => '申请商铺',
                    'shopDelete' => '删除商铺权限',
                ]
            ],
            'pwd' => [
                'opt' => '修改密码'
            ],
		]
	],
    'settings'     => [
        'text' => '全局设置',
        'sub_nav' =>[
            'feeSettings' => [
                'opt' => '全局收费策略',
                'do'  => [
                    'strategy' => '调整收费策略',
                ]
            ],
            'localFeeSettings' => [
                'opt' => '局部收费策略',
                'do'  => [
                    'add'  => '添加策略',
                    'edit' => '编辑策略',
                    'delete' => '删除策略',
                ],
            ],
            'systemSettings' => [
                'opt' => '全局同步策略',
                'do'  => [
                    'set' => '配置同步参数',
                ]
            ],
            'stationSettingsStrategy' => [
                'opt' => '局部同步策略',
                'do'  => [
                    'add' => '添加策略',
                    'edit' => '编辑策略',
                    'delete' => '删除策略',
                ]

            ],
            'wechatSettings' => [
                'opt' => '微信配置'
            ],
            'wechatPictext' => [
                'opt' => '扫码推送配置',
                'do'  => [
                    'add'  => '添加配置',
                    'edit' => '编辑配置',
                    'delete' => '删除配置',
                ],
            ],
            'globalSettings' => [
                'opt' => '客服电话配置'
            ],
        ]
    ],
    'item'         => [
        'text' => '商品管理',
        'sub_nav' =>[
            'list' => [
                'opt' => '商品列表',
                'do'  => [
                    'add' => '添加商品',
                    'edit' => '编辑商品',
                ]
            ]
        ]
    ],
    'shop'         => [
        'text' => '商铺管理',
        'sub_nav' => [
            'lists' => [
                'opt' => '商铺列表',
                'do' => [
                    'editShopType' => '编辑商铺',
                    'updateShopPicture' => '更新商铺图片'
                ]
            ],
            'add' => [
                'opt' => '添加商铺'
            ],
            'shopTypeList' => [
                'opt' => '商铺类型列表'
            ],
            'addShopType' => [
                'opt' => '添加商铺类型'
            ]
        ]
    ],
    'shop_station' => [
        'text' => '商铺站点管理',
        'sub_nav' =>[
            'lists' => [
                'opt' => '商铺站点列表',
                'do' => [
                    'bind' => '绑定商铺',
                    'unbind' => '解绑商铺',
                    'settingStrategy' => '设置策略',
                    'shopStationRemove' => '撤机',
                    'shopStationReplace' => '换机',
                    'shopStationGoUp' => '上机',
                ],
            ],

        ]
    ],
    'station'      => [
        'text' => '站点管理',
        'sub_nav' =>[
            'lists' => [
                'opt' => '站点状态列表',
                'do' => [
                    'unlockDevice' => '开锁',
                    'settingStrategy' => '设置策略',
                    'slotAction' => '槽位操作',
                    'manuallyControl' => '人工控制操作',
                    'query' => '查询信息',
                    'slotLock' => '上锁',
                    'slotUnlock' => '解锁',
                    'lend' => '人工借出',
                    'syncUmbrella' => '同步雨伞信息',
                    'reboot' => '人工重启',
                    'moduleNum' => '模组数量',
                    'initSet' => '初始化设备',
                    'elementModuleOpen' => '开启模组功能',
                    'elementModuleClose' => '关闭模组功能',
                    'voiceModuleOpen' => '开启语音功能',
                    'voiceModuleClose' => '关闭语音功能',
                    'showMac' => '显示mac',
                    'showQrcode' => '二维码展示',
                    'export' => '导出',
                ]
            ],
//            'regionSettings' => [
//                'opt' => '区域设置'
//            ],
            'heartbeatLog' => [
                'opt' => '心跳日志'
            ],
            'stationLog' => [
                'opt' => '站点统计日志'
            ],
            'batchImport' => [
                'opt' => '批量导入'
            ],
            'umbrellaExport' => [
                'opt' => '导出雨伞'
            ],
            'umbrellaExport2' => [
                'opt' => '导出雨伞2'
            ],
        ]
    ],
    'order'        => [
        'text' => '订单管理',
        'sub_nav' =>[
            'lists' => [
                'opt' => '订单列表',
                'do' => [
                    'orderDetail' => '订单详情',
                    'buyerDetail' => '用户信息',
                    'returnDeposit' => '退押金',
                    'lostOrderFinish' => '雨伞遗失',
                ]
            ],
        ]
    ],
    'user'         => [
        'text' => '用户管理',
        'sub_nav' =>[
            'userList' => [
                'opt' => '用户列表',
                'do' => [
                    'search' => '搜索',
                ]
            ],
            'refundList' => [
                'opt' => '提现列表'
            ],
            'zeroFeeUserList' => [
                'opt' => '零收费人员列表',
                'do' => [
                    'add' => '添加',
                    'delete' => '删除',
                ]
            ],
        ]
    ],
    'data'         => [
        'text' => '经营数据',
        'sub_nav' =>[
            'order_analysis' => [
                'opt' => '商户订单分析',
                'do' => [
                    'check_all_status' => '查看所有状态',
                ]
            ],
            'user_data_count' => [
                'opt' => '总用户统计',
                'do' => [
                    'search' => '搜索',
                ]
            ],
            'new_user_list' => [
                'opt' => '新用户统计',
                'do' => [
                    'search' => '搜索',
                ]
            ],
            'old_user_list' => [
                'opt' => '老用户统计',
                'do' => [
                    'search' => '搜索',
                ]
            ],
        ]
    ],
];
