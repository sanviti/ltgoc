<?php
return array(
        //'SHOW_PAGE_TRACE' =>true,
        //'配置项'=>'配置值'
        /* 调试配置 */
        'SHOW_PAGE_TRACE' => false,
        'MODULE_ALLOW_LIST'  => array('Wap','Api','DataAdmin'),
        'URL_MODEL'                 =>2,    //2为去掉url中的index.php

        'DEFAULT_MODULE'        =>  'Wap',  // 默认模块
        'DEFAULT_CONTROLLER'    =>  'Index', // 默认控制器名称
        'DEFAULT_ACTION'        =>  'index', // 默认操作名称

        /* 测试服务器 */
        'DB_TYPE'   => 'mysql', // 数据库类型
        'DB_HOST'   => '', // 服务器地址
        'DB_NAME'   => '', // 数据库名
        'DB_USER'   => '', // 用户名
        'DB_PWD'    => '',  // 密码
        'DB_PORT'   => '3306', // 端口
        'DB_PREFIX' => 'lt_', // 数据库表前缀


        /* 分页COUNT */
        'PAGE_SIZE'   => '10',

        //模版设置相关
        'TMPL_ACTION_SUCCESS'   => 'Public/dispatch_jump',
        'TMPL_ACTION_ERROR'     => 'Public/dispatch_jump',

        //加载配置文件
        'LOAD_EXT_CONFIG' => 'site,banks',


        'LOG_RECORD' => true, // 开启日志记录
        'LOG_LEVEL'  =>'EMERG,ALERT,CRIT,ERR', // 只记录EMERG ALERT CRIT ERR 错误
        'LOG_TYPE'      =>  'File', // 日志记录类型 默认为文件方式

        //redis配置
        'REDIS_HOST' => '127.0.0.1',
        'REDIS_PORT' => 6379,
        'REDIS_AUTH'=>'',
);
