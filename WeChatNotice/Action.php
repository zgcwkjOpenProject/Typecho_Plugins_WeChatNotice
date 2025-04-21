<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class WeChatNotice_Action extends Typecho_Widget
{
    /**
     * 插件服务
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public function service()
    {
        //获取插件内部的参数
        $options = Helper::options();
        //读取配置文件的 ConfigSwitch 信息
        $ConfigSwitch = $options->plugin('WeChatNotice')->ConfigSwitch;
        if ($ConfigSwitch) {
            //读取配置文件的 Token 信息
            $Token = $options->plugin('WeChatNotice')->Token;
            if (!$Token) {
                echo '插件Token未配置';
                return;
            }
            //获取微信发送过来的参数
            $signature = isset($_GET['signature']) ? $_GET['signature'] : '';
            $timestamp = isset($_GET['timestamp']) ? $_GET['timestamp'] : '';
            $nonce = isset($_GET['nonce']) ? $_GET['nonce'] : '';
            $echostr = isset($_GET['echostr']) ? $_GET['echostr'] : '';
            //把这三个参数存到一个数组里面
            $tmpArr = array($timestamp, $nonce, $Token);
            //进行字典排序
            sort($tmpArr);
            //把数组中的元素合并成字符串，impode()函数是用来将一个数组合并成字符串的
            $tmpStr = implode($tmpArr);
            //sha1加密，调用sha1函数
            $tmpStr = sha1($tmpStr);
            //判断加密后的字符串是否和signature相等
            if ($tmpStr == $signature) {
                echo $echostr;
            } else {
                echo '验证不匹配';
            }
        } else {
            //配置完成后接收到用户发送的消息时
            $message = $options->plugin('WeChatNotice')->message;
            if (!$message) {
                echo "";
                return;
            }
            //微信发来的信息
            $postStr = file_get_contents('php://input');
            if (empty($postStr)) {
                echo "";
                return;
            }
            //将xml解析成对象
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            //用于构建下面的发送方和接收方
            $fromUsername = (string) $postObj->FromUserName;
            $toUsername = (string) $postObj->ToUserName;
            $mType = (string) $postObj->MsgType;
            $content = (string) $postObj->Content;
            header('Content-Type: application/xml');
            echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
            echo "<xml>\n";
            echo "<ToUserName><![CDATA[" . $fromUsername . "]]></ToUserName>\n";
            echo "<FromUserName><![CDATA[" . $toUsername . "]]></FromUserName>\n";
            echo "<CreateTime><![CDATA[" . time() . "]]></CreateTime>\n";
            echo "<MsgType><![CDATA[" . $mType . "]]></MsgType>\n";
            if ($content == 'id') {
                echo "<Content><![CDATA[" . $fromUsername . "]]></Content>\n";
            } else {
                echo "<Content><![CDATA[" . $message . $content . "]]></Content>\n";
            }
            echo "</xml>\n";
        }
    }

    /**
     * 外部消息推送服务
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public function noticeService()
    {
        $options = Helper::options();
        $api_server_state = $options->plugin('WeChatNotice')->api_server_state;
        if (!$api_server_state) {
            echo "未启用";
            return;
        }
        $api_server_token = $options->plugin('WeChatNotice')->api_server_token;
        if (!$api_server_token) {
            echo "内置参数不能为空";
            return;
        }
        $apiToken = isset($_GET['apiToken']) ? $_GET['apiToken'] : (isset($_POST['apiToken']) ? $_POST['apiToken'] : '');
        if (!$apiToken) {
            echo "Not Null";
            return;
        }
        if ($apiToken != $api_server_token) {
            echo "凭据不匹配";
            return;
        }
        //维护 accessToken
        WeChatNotice_Plugin::accessToken();
        //从存储中获取出来使用 access_token
        $access_token = WeChatNotice_Plugin::$arrayToken["access_token"];
        //调用推送接口
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $access_token;
        $openid = $options->plugin('WeChatNotice')->openid;
        $api_server_template_id = $options->plugin('WeChatNotice')->api_server_template_id;
        //标题：消息推送
        //标题：{{title.DATA}}
        //内容：{{content.DATA}}
        $title = isset($_GET['title']) ? $_GET['title'] : (isset($_POST['title']) ? $_POST['title'] : '');
        $content = isset($_GET['content']) ? $_GET['content'] : (isset($_POST['content']) ? $_POST['content'] : '');
        $openID = isset($_GET['openID']) ? $_GET['openID'] : (isset($_POST['openID']) ? $_POST['openID'] : '');
        $openUrl = isset($_GET['openUrl']) ? $_GET['openUrl'] : (isset($_POST['openUrl']) ? $_POST['openUrl'] : 'blog.zgcwkj.cn');
        if(!$openID){
            $openID = $openid;
        }
        $data = array(
            'touser' => $openID, //用户openid
            'template_id' => $api_server_template_id, //模板id
            'url' => $openUrl,
            'data' => array(
                'title' => array(
                    'value' => $title,
                    'color' => "#173177"
                ),
                'content' => array(
                    'value' => $content,
                    'color' => "#3D3D3D"
                ),
            )
        );
        $jdata = json_encode($data); //转化成json数组让微信可以接收
        $res = WeChatNotice_Plugin::https_request($url, urldecode($jdata)); //请求开始
        $res = json_decode($res, true);
        $message = "失败";
        if ($res['errcode'] == 0) {
            $message = "成功";
        }
        echo $message;
    }

    /**
     * 外部消息推送简单服务
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public function noticeSimpleService()
    {
        $options = Helper::options();
        $api_server_state = $options->plugin('WeChatNotice')->api_server_state;
        if (!$api_server_state) {
            echo "未启用";
            return;
        }
        $api_server_token = $options->plugin('WeChatNotice')->api_server_token;
        if (!$api_server_token) {
            echo "内置参数不能为空";
            return;
        }
        $apiToken = isset($_GET['apiToken']) ? $_GET['apiToken'] : (isset($_POST['apiToken']) ? $_POST['apiToken'] : '');
        if (!$apiToken) {
            echo "Not Null";
            return;
        }
        if ($apiToken != $api_server_token) {
            echo "凭据不匹配";
            return;
        }
        //维护 accessToken
        WeChatNotice_Plugin::accessToken();
        //从存储中获取出来使用 access_token
        $access_token = WeChatNotice_Plugin::$arrayToken["access_token"];
        //调用推送接口
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $access_token;
        $openid = $options->plugin('WeChatNotice')->openid;
        $api_server_template_id = $options->plugin('WeChatNotice')->api_server_template_id;
        //标题：消息推送
        //标题：{{title.DATA}}
        //内容：{{content.DATA}}
        $title = isset($_GET['t']) ? $_GET['t'] : (isset($_POST['t']) ? $_POST['t'] : '');
        $content = isset($_GET['c']) ? $_GET['c'] : (isset($_POST['c']) ? $_POST['c'] : '');
        $openID = isset($_GET['o']) ? $_GET['o'] : (isset($_POST['o']) ? $_POST['o'] : '');
        $openUrl = isset($_GET['u']) ? $_GET['u'] : (isset($_POST['u']) ? $_POST['u'] : 'blog.zgcwkj.cn');
        if(!$openID){
            $openID = $openid;
        }
        $data = array(
            'touser' => $openID, //用户openid
            'template_id' => $api_server_template_id, //模板id
            'url' => $openUrl,
            'data' => array(
                'title' => array(
                    'value' => $title,
                    'color' => "#173177"
                ),
                'content' => array(
                    'value' => $content,
                    'color' => "#3D3D3D"
                ),
            )
        );
        $jdata = json_encode($data); //转化成json数组让微信可以接收
        $res = WeChatNotice_Plugin::https_request($url, urldecode($jdata)); //请求开始
        $res = json_decode($res, true);
        $message = "失败";
        if ($res['errcode'] == 0) {
            $message = "成功";
        }
        echo $message;
    }
}
