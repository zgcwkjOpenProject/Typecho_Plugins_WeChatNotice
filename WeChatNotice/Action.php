<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class WeChatNotice_Action extends Typecho_Widget {

    public function action(){
    }
    
    /**
     * 插件服务
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public function service(){
        //获取插件内部的参数
        $options = Helper::options();
        //读取配置文件的 ConfigSwitch 信息
        $ConfigSwitch = $options->plugin('WeChatNotice')->ConfigSwitch;
        if($ConfigSwitch){
            //读取配置文件的 Token 信息
            $Token = $options->plugin('WeChatNotice')->Token;
            if(!$Token){
                echo '插件Token未设置';
                return;
            }
            //获取微信发送过来的参数
            $signature = isset($_GET['signature']) ? $_GET['signature'] : '';
            $timestamp = isset($_GET['timestamp']) ? $_GET['timestamp'] : '';
            $nonce = isset($_GET['nonce']) ? $_GET['nonce'] : '';
            $echostr = isset($_GET['echostr']) ? $_GET['echostr'] : '';
            //把这三个参数存到一个数组里面
            $tmpArr = array($timestamp,$nonce,$Token); 
            //进行字典排序
            sort($tmpArr);
            //把数组中的元素合并成字符串，impode()函数是用来将一个数组合并成字符串的
            $tmpStr = implode($tmpArr);  
            //sha1加密，调用sha1函数
            $tmpStr = sha1($tmpStr);
            //判断加密后的字符串是否和signature相等
            if($tmpStr == $signature) 
            {
                echo $echostr;
            }else{
                echo '验证不匹配';
            }
        }else{
            $message = $options->plugin('WeChatNotice')->message;
            if(!$message){
                echo "";
                return;
            }
            //微信发来的信息
            $postStr = file_get_contents('php://input');
            if(empty($postStr)){
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
            echo "<ToUserName><![CDATA[".$fromUsername."]]></ToUserName>\n";
            echo "<FromUserName><![CDATA[".$toUsername."]]></FromUserName>\n";
            echo "<CreateTime><![CDATA[".time()."]]></CreateTime>\n";
            echo "<MsgType><![CDATA[".$mType."]]></MsgType>\n";
            echo "<Content><![CDATA[".$message.$content."]]></Content>\n";
            echo "</xml>\n";
        }
        $debug = $options->plugin('WeChatNotice')->debug;
        if($debug){
            echo '调试信息';
        }
    }
}