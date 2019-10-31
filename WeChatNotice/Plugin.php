<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 将信息往微信公众号推送
 * 
 * @package WeChatNotice
 * @author zgcwkj
 * @version 1.0.0
 * @link http://blog.zgcwkj.top
 */
class WeChatNotice_Plugin implements Typecho_Plugin_Interface
{   
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        // 创建路由
        Helper::addRoute('_WeChat','/WeChat','WeChatNotice_Action','service');
        //监听收发信息
        Typecho_Plugin::factory('Widget_Feedback')->comment = array('WeChatNotice_Plugin', 'pushMessage');
        Typecho_Plugin::factory('Widget_Feedback')->trackback = array('WeChatNotice_Plugin', 'pushMessage');
        Typecho_Plugin::factory('Widget_XmlRpc')->pingback = array('WeChatNotice_Plugin', 'pushMessage');
        
        return _t('请进入设置填写微信推送参数');
    }
    
    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
        Helper::removeRoute("_WeChat");
    }
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        echo '<h4>作者：<a href="http://blog.zgcwkj.top" target="_blank">zgcwkj</a> 2018年12月20日</h4>';
        echo '<h4>微信公众号：<a href="https://mp.weixin.qq.com/debug/cgi-bin/sandbox?t=sandbox/login" target="_blank">申请测试</a></h4>';
        echo '<hr>';
        echo '<b>接口配置信息 URL：</b>';
        echo '</br>';
        echo '<b>开启伪静态的URL：</b><span style="color:#FF0000;">你的网址/WeChat</span>';
        echo '</br>';
        echo '<b>没有开启伪静态的URL：</b><span style="color:#FF0000;">你的网址/index.php/WeChat</span>';
        echo '<hr>';
        echo '<b>消息模版标题：</b>';
        echo '<p>文章评论</p>';
        echo '<b>消息模版内容：</b>';
        echo '<p>文章：{{title.DATA}}</p>';
        echo '<p>用户：{{user.DATA}}</p>';
        echo '<p>位置：{{ip.DATA}}</p>';
        echo '<p>内容：</p>';
        echo '<p>{{content.DATA}}</p>';
        echo '<hr>';
        //开代表 > 接口配置信息对接 <
        //关代表 > 发送消息立刻回复信息或信息推送 <
        $ConfigSwitch = new Typecho_Widget_Helper_Form_Element_Radio(
            'ConfigSwitch', array(
                '1' => '开',
                '0' => '关',
            ), '1', '接口配置信息对接', '开（接口配置信息对接）关（发送消息立刻回复信息或信息推送）');
        $form->addInput($ConfigSwitch);
        
        //接口配置信息 > Token <
        $Token = new Typecho_Widget_Helper_Form_Element_Text('Token', null, 'WeChatVerification_zgcwkj', _t('接口配置信息 Token'), '接口配置信息 Token');
        $form->addInput($Token);
        
        //用户发送消息时 默认回复的内容、留空则不回复
        $message = new Typecho_Widget_Helper_Form_Element_Text('message', null, '服务正常使用', _t('默认回复的内容'), '微信公众号 默认回复的内容、留空则不回复');
        $form->addInput($message);
        
        //开代表 > 开启Debug <
        //关代表 > 关闭启Debug <
        $debug = new Typecho_Widget_Helper_Form_Element_Radio(
            'debug', array(
                '1' => '开',
                '0' => '关',
            ), '0', 'DeBug模式', '开（开启Debug）关（关闭启Debug）');
        $form->addInput($debug);
        
        //微信公众号 appID
        $appID = new Typecho_Widget_Helper_Form_Element_Text('appID', null, null, _t('appID'), '微信公众号 appID');
        $form->addInput($appID);
        
        //微信公众号 appsecret
        $appsecret = new Typecho_Widget_Helper_Form_Element_Text('appsecret', null, null, _t('appsecret'), '微信公众号 appsecret');
        $form->addInput($appsecret);
        
        //接收信息的微信号 openid
        $openid = new Typecho_Widget_Helper_Form_Element_Text('openid', null, null, _t('openid'), '接收信息的微信号 openid');
        $form->addInput($openid);
        
        //消息模版 template_id
        $template_id = new Typecho_Widget_Helper_Form_Element_Text('template_id', null, null, _t('template_id'), '消息模版 template_id');
        $form->addInput($template_id);
    }
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
    
    /**
     * 微信推送
     * 
     * @access public
     * @param array $comment 评论结构
     * @param Typecho_Widget $post 被评论的文章
     * @return void
     */
    public static function pushMessage($comment, $post)
    {
        $options = Helper::options();
        $openid = $options->plugin('WeChatNotice')->openid;
        $template_id = $options->plugin('WeChatNotice')->template_id;
        //从存储中获取出来使用 access_token
        $access_token = self::$arrayToken["access_token"];
        //从存储中获取出来使用 expires_in
        $expires_in = self::$arrayToken["expires_in"];
        //从存储中获取出来使用 record_time
        $record_time = self::$arrayToken["record_time"];
        //记录凭据时的时间 加 记录凭据的有效时间 
        $validTime = $record_time + $expires_in;
        //如果小于的话需要重新获取 AccessToken
        if($validTime <= time()){
            //==>重新获取 AccessToken
            $accessToken = self::accessToken();
            self::$arrayToken["access_token"] = $accessToken["access_token"];
            self::$arrayToken["expires_in"] = $accessToken["expires_in"];
            self::$arrayToken["record_time"] = $accessToken["record_time"];
            //==>重新赋值
            //从存储中获取出来使用 access_token
            $access_token = self::$arrayToken["access_token"];
            //从存储中获取出来使用 expires_in
            $expires_in = self::$arrayToken["expires_in"];
            //从存储中获取出来使用 record_time
            $record_time = self::$arrayToken["record_time"];
        }
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=".$access_token;
        //标题：文章评论
        //文章：{{title.DATA}}
        //用户：{{user.DATA}}
        //位置：{{ip.DATA}}
        //内容：
        //{{content.DATA}}
        $data = array(
            'touser' => $openid,//要发送给用户的openid
            'template_id' => $template_id,//改成自己的模板id，在微信后台模板消息里查看
            'url' => $post->permalink,//自己网站链接url
            'data' => array(
                'title' => array(
                    'value' => $post->title,
                    'color' => "#173177"
                ),
                'user' => array(
                    'value' => $comment['author'],
                    'color' => "#F00"
                ),
                'ip' => array(
                    'value' => $comment['ip'],
                    'color' => "#173177"
                ),
                'content' => array(
                    'value' => $comment['text'],
                    'color' => "#3D3D3D"
                ),
            )
        );
        $jdata = json_encode($data);//转化成json数组让微信可以接收
        $res = self::https_request($url, urldecode($jdata));//请求开始
        $res = json_decode($res, true);
        $message = "失败";
        if ($res['errcode'] == 0 && $res['errcode'] == "ok") {
            $message = "成功";
        }
        $debug = $options->plugin('WeChatNotice')->debug;
        if($debug){
            echo "已经阻止本次评论，微信信息推送".$message."！";
            echo "调试完成的话记得关闭DeBug模式！";
            return;
        }
        return  $comment;
    }
    
    //存储 AccessToken 的数据（凭据）
    public static $arrayToken = array(
        "access_token" => "token",
        "expires_in" => "7200",
        "record_time" => "0",
    );
    
    /**
     * 微信 accessToken
     * 
     * @access public
     * @return void
     */
    public static function accessToken()
    {
        //准备要返回的数据
        $ReturnArrayToken = array(
            "access_token" => "token",
            "expires_in" => "7200",
            "record_time" => "0",
        );
        $options = Helper::options();
        $appID = $options->plugin('WeChatNotice')->appID;
        $appsecret = $options->plugin('WeChatNotice')->appsecret;
        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$appID.'&secret='.$appsecret;
        $rdata = self::https_request($url);//调用网络请求
        $jdata = json_decode($rdata);//数据转JSON
        if($jdata->access_token){
            $ReturnArrayToken["access_token"] = $jdata->access_token;
            $ReturnArrayToken["expires_in"] = $jdata->expires_in;
            $ReturnArrayToken["record_time"] = time();
        }else{
            echo $jdata->errmsg;
        }
        return $ReturnArrayToken;
    }
    
    /**
     * 网络请求函数
     * 
     * @access public
     * @return void
     */
    public static function https_request($url, $data = null)
    {
        $curl = curl_init();//初始化一个CURL对象
        curl_setopt($curl, CURLOPT_URL, $url);//设置你所需要抓取的URL
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);//跳过证书验证
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);//从证书中检查SSL加密算法是否存在
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);//设置curl参数，要求结果是否输出到屏幕上，为true的时候是不返回到网页中
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);//提交数据
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);//设置curl参数，要求结果是否输出到屏幕上，为true的时候是不返回到网页中
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }
}
