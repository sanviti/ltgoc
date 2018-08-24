<?php
namespace Common\Lib;
/**
 * 全局常量
 */
class Constants{

    # AUTH_TOKEN 登录令牌
    # USER_TOKEN 用户令牌
    const PUB_SALT = 'ol$eX5z7JzTd5jHxO!dRSODjF@rZGo'; //公共盐
    const SIGN_SALT = 'ZcXY#z$1qhYj!xCcpKyKaNdQ3CVKPP'; //签名公共盐
    
    const BASE_URL = 'http://goc.xinpinhui888.com';
    const PUB_CACHE_TIME = 300; //缓存时间 秒

    const AUTH_TOKEN_TIME = 604800; //session token过期时间
    const AUTH_TOKEN_PREFIX = 'usession.'; //用户登录SESSION前缀
    
    const SMS_INTERVAL_TIME = 80; //短信发送间隔 单位秒
    const SMS_EXPIRE_TIME = 300; //验证码有效期

    const IMAGE_CODE_EXPIRE_TIME = 300; //图形验证码有效期
    #短信类型
    const SMS_REGISTER_CODE = 1; //注册验证码
    const SMS_APPLYCASH_CODE = 2; //申请提现验证码
    const SMS_FINDPWD_CODE = 3; //找回密码验证码
    const SMS_RESETPAYPWD_CODE = 4; //找回密码验证码
    const SMS_UPDLOGIN_CODE = 6; //修改登录密码验证码
    const SMS_UPDPAY_CODE = 7; //修改支付密码验证码
    #短信模板ID
    const SMSTEMPLATE_REG_VCODE = 256088;
    const SMSTEMPLATE_FINDPWD_VCODE = 256088;
    #错误代码
    const ERRCODE_AUTHTOKEN_VOID = 10000; //登录失效
    const ERRCODE_MEMBER_VOID = 10001; //用户不存在
    const ERRCODE_MEMBER_LOCK = 10002; //用户锁定
    const ERRCODE_SIGNTRUE_VOID = 10003; //签名失效


    #交易中心挂单状态1交易中 2交易成功 3已撤单
    const ORDER_INTRADING = 1;
    const ORDER_SUCCESS = 2;
    const ORDER_CANCEL = 3;

    #交易时间 每周一至周日10:00—16:30
    const ORDER_TRANDING_STIME=36000;
    const ORDER_TRANDING_ETIME=57600;

    #C2C时间 每周一至周日9:00—17:00
    const C2C_TRANDING_STIME=32400;
    const C2C_TRANDING_ETIME=61200;
    
    
    #卖出手续费
    const SCORE_SELL_FEE = 0.1; //10%
    #买入手续费
    const SCORE_BUY_FEE = 0.03; //3%
    #c2c提现手续费
    const SCORE_APPLY_FEE = 0.05; //5%
    #顶一顶消耗乐享积分
    const SHARE_SCORE_EXPEND = 100;
    #余额直充链通积分 
    const BALANCE_TO_SCORE = 1.05; //105%
    #链积分兑换乐享积分
    const CHAIN_SCORE_EXPEND=5; //1:5
    #推荐奖励200乐享积分
    const REGISTER_RECOMMEND=200;
    #推荐直推奖励3%链通积分
    // const REGISTER_RECOMMEND_CHAIN=0.03;

    #加密
    const ENCRYP_TEXT_KEYNAME = 'entext'; //密文POST字段
    
    #计划任务
    // const PLAN_MEMBER_LEVEL = 'PLAN_MEMBER_LEVEL'; //大小区评级
    const PLAN_AWARD_TEAM = 'PLAN_AWARD_TEAM'; //奖励社区

    
    #系统注册用户id
    const APPLY_UID = 105; //提现手续费插入ID
    
    #系统分红奖励比率 手续费的
    const AWARD_BIG_TEAM = 0.02; //大区
    const AWARD_SMALL_TEAM = 0.01; //小区


    #账户日志类型
    // 1余额提现 2提现失败 3 兑换USDC 4 顶一顶 5充值GOC 6 兑换USDC撤回  7充值链通积分 8 兑入乐享积分 9推荐奖励
    
    #类型 
    // 1 余额  11卖出收入 12 c2c交易 13兑换链通积分 14直推奖励 15社区奖励 16社区分红
    // 2 usdc  21链通积分兑入 22买入GOC
    // 3 链通积分 31充值 32兑换USDC 33兑换乐享积分
    // 4 乐享积分 41链通积分兑入 42 推荐用户奖励
    // 5 GOC 51买入GOC 52卖出
}