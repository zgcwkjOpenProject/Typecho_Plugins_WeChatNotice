<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
* 将信息推送到微信公众号
*
* @package WeChatNotice
* @author zgcwkj
* @version 1.0.3
* @link http://zgcwkj.cn
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
    public static function activate() {
        //创建路由
        Helper::addRoute('_WeChat', '/WeChat', 'WeChatNotice_Action', 'service');
        Helper::addRoute('_WeChatNoticeApi', '/WeChatNoticeApi', 'WeChatNotice_Action', 'noticeService');
        Helper::addRoute('_WeChatNoticeSimpleApi', '/wxn', 'WeChatNotice_Action', 'noticeSimpleService');
        //监听事件
        Typecho_Plugin::factory('Widget_Feedback')->finishComment = array('WeChatNotice_Plugin', 'pushMessage');//评论完成
        // Typecho_Plugin::factory('Widget_Feedback')->comment = array('WeChatNotice_Plugin', 'pushMessage');//评论
        // Typecho_Plugin::factory('Widget_Feedback')->trackback = array('WeChatNotice_Plugin', 'pushMessage');//引用
        // Typecho_Plugin::factory('Widget_XmlRpc')->pingback = array('WeChatNotice_Plugin', 'pushMessage');//回复
        //返回
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
    public static function deactivate() {
        Helper::removeRoute("_WeChat");
        Helper::removeRoute("_WeChatNoticeApi");
        Helper::removeRoute("_WeChatNoticeSimpleApi");
    }

    /**
    * 获取插件配置面板
    *
    * @access public
    * @param Typecho_Widget_Helper_Form $form 配置面板
    * @return void
    */
    public static function config(Typecho_Widget_Helper_Form $form) {
        $options = Helper::options();
        echo '<h4>作者：<a href="http://zgcwkj.cn" target="_blank">zgcwkj</a> 2024年11月25日</h4>';
        echo '<h4>源码：<a href="http://github.com/zgcwkjOpenProject/Typecho_Plugins_WeChatNotice" target="_blank">WeChatNotice</a></h4>';
        echo '<h4>微信公众号：<a href="https://mp.weixin.qq.com/debug/cgi-bin/sandbox?t=sandbox/login" target="_blank">申请测试</a></h4>';
        echo '<hr>';
        echo '<h2>评论通知配置说明</h2>';
        echo '<b>服务接口：</b><span style="color:red;">' . $options->index . '/WeChat</span></br>';
        echo '<b>消息模版标题：</b></br>';
        echo '<textarea style="height:40px" id="WXN_TextTile" readonly></textarea></br>';
        echo '<script>document.getElementById("WXN_TextTile").value="文章评论"</script>';
        echo '<b>消息模版内容：</b></br>';
        echo '<textarea style="height:120px" id="WXN_TextContent" readonly></textarea></br>';
        echo '<script>document.getElementById("WXN_TextContent").value="文章：{{title.DATA}}\r\n用户：{{user.DATA}}\r\n位置：{{ip.DATA}}\r\n内容：{{content.DATA}}"</script>';
        echo '<hr>';
        echo '<h2>外部接口配置说明</h2>';
        echo '<b>通用接口：</b><span style="color:red;font-size:13px;">' . $options->index . '/WeChatNoticeApi?apiToken=token&title=TestA&content=TestB&openID=id&openUrl=zgcwkj.cn</span></br>';
        echo '<b>简易接口：</b><span style="color:red;">' . $options->index . '/wxn?apiToken=token&t=TestA&c=TestB&o=id&u=zgcwkj.cn</span></br>';
        echo '<b>参数说明：</b><span style="color:red;">apiToken</span>凭据，<span style="color:red;">title</span> 标题，<span style="color:red;">content</span> 内容，<span style="color:red;">openID</span> 接收者ID，<span style="color:red;">openUrl</span> 打开的网址</br>';
        echo '<b>消息模版标题：</b></br>';
        echo '<textarea style="height:40px" id="WXN_ApiTile" readonly></textarea></br>';
        echo '<script>document.getElementById("WXN_ApiTile").value="消息通知"</script>';
        echo '<b>消息模版内容：</b></br>';
        echo '<textarea style="height:80px" id="WXN_ApiContent" readonly></textarea></br>';
        echo '<script>document.getElementById("WXN_ApiContent").value="标题：{{title.DATA}}\r\n内容：{{content.DATA}}"</script>';
        echo '<hr>';

        //微信公众号 appID
        $appID = new Typecho_Widget_Helper_Form_Element_Text('appID', null, null, _t('appID'), '微信公众号 appID');
        $form->addInput($appID);

        //微信公众号 appSecret
        $appsecret = new Typecho_Widget_Helper_Form_Element_Text('appsecret', null, null, _t('appSecret'), '微信公众号 appsecret');
        $form->addInput($appsecret);

        //接口配置信息对接 开关
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

        //评论通知 开关
        $template_state = new Typecho_Widget_Helper_Form_Element_Radio(
            'template_state', array(
                '1' => '启用',
                '0' => '禁用',
            ), '0', '评论通知', '启用或禁用评论通知推送');
        $form->addInput($template_state);

        //消息模版 template_id
        $template_id = new Typecho_Widget_Helper_Form_Element_Text('template_id', null, null, _t('评论通知模板ID'), '模版ID template_id');
        $form->addInput($template_id);

        //接收信息的微信号 openid
        $openid = new Typecho_Widget_Helper_Form_Element_Text('openid', null, null, _t('评论通知 OpenID'), '接收评论通知的 openid');
        $form->addInput($openid);

        //外部接口 开关
        $api_server_state = new Typecho_Widget_Helper_Form_Element_Radio(
            'api_server_state', array(
                '1' => '启用',
                '0' => '禁用',
            ), '0', '外部接口', '启用或禁用外部接口推送');
        $form->addInput($api_server_state);

        //消息模版 api_server_template_id
        $api_server_template_id = new Typecho_Widget_Helper_Form_Element_Text('api_server_template_id', null, null, _t('接口推送通知模板ID'), '模版ID api_server_template_id');
        $form->addInput($api_server_template_id);

        //外部接口凭据 api_server_token
        $api_server_token = new Typecho_Widget_Helper_Form_Element_Text('api_server_token', null, null, _t('外部接口凭据'), '外部接口凭据 api_server_token');
        $form->addInput($api_server_token);
    }

    /**
    * 个人用户的配置面板
    *
    * @access public
    * @param Typecho_Widget_Helper_Form $form
    * @return void
    */
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    /**
    * 微信推送
    *
    * @access public
    * @param array $comment 评论结构
    * @return void
    */
    public static function pushMessage($comment) {
        $options = Helper::options();
        $template_state = $options->plugin('WeChatNotice')->template_state;
        if ($template_state) {
            //维护 accessToken
            self::accessToken();
            //从存储中获取出来使用 access_token
            $access_token = self::$arrayToken["access_token"];
            //从存储中获取出来使用 expires_in
            $expires_in = self::$arrayToken["expires_in"];
            //从存储中获取出来使用 record_time
            $record_time = self::$arrayToken["record_time"];
            //调用推送接口
            $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $access_token;
            $openid = $options->plugin('WeChatNotice')->openid;
            $template_id = $options->plugin('WeChatNotice')->template_id;
            //标题：文章评论
            //文章：{{title.DATA}}
            //用户：{{user.DATA}}
            //位置：{{ip.DATA}}
            //内容：
            //{{content.DATA}}
            $data = array(
                'touser' => $openid, //用户openid
                'template_id' => $template_id, //模板id
                'url' => $comment->permalink, //自己网站链接url
                'data' => array(
                    'title' => array(
                        'value' => $comment->title,
                        'color' => "#173177"
                    ),
                    'user' => array(
                        'value' => $comment->author,
                        'color' => "#F00"
                    ),
                    'ip' => array(
                        'value' => $comment->ip,
                        'color' => "#173177"
                    ),
                    'content' => array(
                        'value' => $comment->text,
                        'color' => "#3D3D3D"
                    ),
                )
            );
            $jdata = json_encode($data); //转化成json数组让微信可以接收
            $res = self::https_request($url, urldecode($jdata)); //请求开始
            $res = json_decode($res, true);
            $message = "失败";
            if ($res['errcode'] == 0) {
                $message = "成功";
            }
        }
        return;
    }

    //=> 微信公众号相关

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
    public static function accessToken() {
        $options = Helper::options();
        //从存储中获取出来使用 access_token
        $access_token = self::$arrayToken["access_token"];
        //从存储中获取出来使用 expires_in
        $expires_in = self::$arrayToken["expires_in"];
        //从存储中获取出来使用 record_time
        $record_time = self::$arrayToken["record_time"];
        //记录凭据时的时间 加 记录凭据的有效时间
        $validTime = $record_time + $expires_in;
        //如果小于的话需要重新获取 AccessToken
        if ($validTime <= time()) {
            $appID = $options->plugin('WeChatNotice')->appID;
            $appsecret = $options->plugin('WeChatNotice')->appsecret;
            $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $appID . '&secret=' . $appsecret;
            $rdata = self::https_request($url); //调用网络请求
            $jdata = json_decode($rdata); //数据转JSON
            if ($jdata->access_token) {
                self::$arrayToken["access_token"] = $jdata->access_token;
                self::$arrayToken["expires_in"] = $jdata->expires_in;
                self::$arrayToken["record_time"] = time();
                //$token_file = fopen("_Test.txt","w") or die("Unable to open file!");
                //fwrite($token_file, "文本输出达到调试效果");
                //fclose($token_file);
            } else {
                echo $jdata->errmsg;
            }
        }
    }

    /**
    * 网络请求函数
    *
    * @access public
    * @return void
    */
    public static function https_request($url, $data = null) {
        $curl = curl_init(); //初始化一个CURL对象
        curl_setopt($curl, CURLOPT_URL, $url); //设置你所需要抓取的URL
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE); //跳过证书验证
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE); //从证书中检查SSL加密算法是否存在
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1); //设置curl参数，要求结果是否输出到屏幕上，为true的时候是不返回到网页中
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data); //提交数据
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); //设置curl参数，要求结果是否输出到屏幕上，为true的时候是不返回到网页中
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }
}
