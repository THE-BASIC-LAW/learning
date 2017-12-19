<?php

namespace app\third\alipay;

require_once "aop/AopClient.php";
require_once "aop/BaseRequest.php";

class AlipayAPI
{
    public static  $msgData      = [];
    private static $aop          = null;
    private static $access_token = '';

    private static $appId;
    private static $windowPublicKeyFile;
    private static $merchantPublicKeyFile;
    private static $merchantPrivateFile;

    public static function initialize()
    {
        self::$appId                 = env('alipay.app_id', '2017113000260606');
        self::$windowPublicKeyFile   = ROOT_PATH . env('alipay.window_public_key_file', 'application/cert/development/alipay/alipay_public_key.pem');
        self::$merchantPublicKeyFile = ROOT_PATH . env('alipay.merchant_public_key_file', 'application/cert/development/alipay/app_public_key.pem');
        self::$merchantPrivateFile   = ROOT_PATH . env('alipay.merchant_private_key_file', 'application/cert/development/alipay/app_private_key.pem');

        //支付宝使用了新的加密算法RSA2
        self::$aop = new \AopClient(
            self::$appId,
            self::$merchantPrivateFile,
            self::$merchantPublicKeyFile,
            self::$windowPublicKeyFile,
            '1.0',
            'RSA2'
        );
    }

    public static function getResponseNodeName($request)
    {
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        return $responseNode;
    }

    public static function verifyGateway()
    {
        // @todo 支付宝网关地址检查

        // 非空验证
        if (empty($_POST["sign"]) || empty($_POST["sign_type"]) || empty($_POST["biz_content"]) || empty($_POST["service"]) || empty($_POST["charset"])) {
            return false;
        }

        // 内容验证
        self::$aop->verifyGateway();
        exit;
    }

    public static function verifyMessage()
    {
        // 非空验证
        if (empty($_POST["sign"]) || empty($_POST["sign_type"]) || empty($_POST["biz_content"]) || empty($_POST["service"]) || empty($_POST["charset"])) {
            return false;
        }
        // 内容验证
        return self::$aop->verifyMessage();
    }

    public static function getMsg()
    {
        $biz_content = $_POST["biz_content"];

        if (empty($biz_content)) {
            return false;
        }

        self::$msgData = [
            'userinfo'   => self::_getNode($biz_content, "UserInfo"),
            'client'     => self::_getNode($biz_content, "FromAlipayUserId"),
            'me'         => self::_getNode($biz_content, "AppId"),
            'createtime' => self::_getNode($biz_content, "CreateTime"),
            'type'       => self::_getNode($biz_content, "MsgType"),
            'event'      => self::_getNode($biz_content, "EventType"),
        ];

        switch (self::$msgData['type']) {
            case 'text':
                self::$msgData['content'] = self::_getNode($biz_content, "Text");
                break;

            case 'image':
                self::$msgData['mediaid'] = self::_getNode($biz_content, "MediaId");
                self::$msgData['format']  = self::_getNode($biz_content, "Format");
                break;

            case 'event':
                switch (self::$msgData['event']) {
                    case 'follow':
                    case 'enter':
                        // 二维码进入
                        $actionParam               = self::_getNode($biz_content, "ActionParam");
                        $arr                       = json_decode($actionParam);
                        $sceneId                   = $arr->scene->sceneId;
                        self::$msgData['eventkey'] = $sceneId;
                        break;
                    case 'click':
                        self::$msgData['eventkey'] = self::_getNode($biz_content, "ActionParam");
                        break;

                    case 'unfollow':
                        # code...
                        break;
                    default:
                        # code...
                        break;
                }

                break;

            default:
                self::$msgData['type'] = 'unknown';
                break;
        }

        return true;
    }

    /**
     * 直接获取xml中某个结点的内容
     *
     * @param string $xml
     * @param string $node
     * @return string
     */
    private static function _getNode($xml, $node)
    {
        $xml = "<?xml version=\"1.0\" encoding=\"GBK\"?>" . $xml;
        $dom = new \DOMDocument ("1.0", "GBK");
        $dom->loadXML($xml);
        $event_type = $dom->getElementsByTagName($node);

        return $event_type->item(0)->nodeValue;
    }

    public static function replyTextMsg($replyMsg, $chat = 0)
    {
        $replyMsg = $replyMsg ?: 'Nice to meet you, What can I do for you?';

        $biz_content = [
            'to_user_id' => self::$msgData['client'],
            'msg_type'   => 'text',
            'text'       => ['content' => $replyMsg],
            'chat'       => $chat,
        ];
        $biz_content = self::$aop->JSON($biz_content);
        $request     = new \BaseRequest('alipay.open.public.message.custom.send');
        $request->setBizContent($biz_content);
        self::$aop->execute($request);
    }

    public static function replyPicTextMsg($replyMsg, $chat = 0)
    {
        $articles = [];
        foreach ($replyMsg as $item) {
            $articles[] = [
                'title'       => $item['title'],
                'desc'        => $item['description'],
                'image_url'   => $item['picurl'],
                'url'         => $item['url'],
                'action_name' => $item['action_name'],
            ];
        }

        $biz_content = [
            'to_user_id' => self::$msgData['client'],
            'msg_type'   => 'image-text',
            'articles'   => $articles,
            'chat'       => $chat,
        ];
        $biz_content = self::$aop->JSON($biz_content);
        $request     = new \BaseRequest('alipay.open.public.message.custom.send');
        $request->setBizContent($biz_content);
        self::$aop->execute($request);
    }

    public static function createMenu($biz)
    {
        $biz     = json_encode($biz);
        $request = new \BaseRequest('alipay.open.public.menu.create');
        $request->setBizContent($biz);
        $result = self::$aop->execute($request);
        return $result->{$request->getResponseNode()}->msg;
    }

    public static function updateMenu($biz)
    {
        $biz     = json_encode($biz);
        $request = new \BaseRequest('alipay.open.public.menu.modify');
        $request->setBizContent($biz);
        $result = self::$aop->execute($request);

        return $result->{$request->getResponseNode()}->msg;
    }

    public static function createQrcode($sceneId, $expireTime = 1800)
    {
        $qrBiz   = [
            'code_info'     => ['scene' => ['scene_id' => $sceneId]],
            'expire_second' => $expireTime,
            'show_logo'     => 'Y',
        ];
        $qrBiz   = json_encode($qrBiz);
        $request = new \BaseRequest('alipay.open.public.qrcode.create');
        $request->setBizContent($qrBiz);
        $result = self::$aop->execute($request);

        if ($result->{$request->getResponseNode()}->code == 10000) {
            return $result->{$request->getResponseNode()}->code_img;
        }

        return false;
    }

    public static function createQrcodeUnLimit($sceneId)
    {
        $qrBiz   = [
            'code_info' => ['scene' => ['scene_id' => "$sceneId"]],
            'code_type' => 'PERM',
            'show_logo' => 'Y',
        ];
        $qrBiz   = json_encode($qrBiz);
        $request = new \BaseRequest('alipay.open.public.qrcode.create');
        $request->setBizContent($qrBiz);
        $result = self::$aop->execute($request);

        if ($result->{$request->getResponseNode()}->code == 10000) {
            return $result->{$request->getResponseNode()}->code_img;
        }

        return false;
    }

    public static function getOpenid($scope = "auth_base")
    {
        $result = AlipayAPI::getOAuthToken($scope);

        return empty($result) ? null : $result->user_id;
    }

    /**
     * url : https://doc.open.alipay.com/docs/doc.htm?treeId=289&articleId=105656&docType=1
     *
     * @param string $scope
     * auth_base：以auth_base为scope发起的网页授权，是用来获取进入页面的用户的userId的，
     * 并且是静默授权并自动跳转到回调页的。用户感知的就是直接进入了回调页（通常是业务页面）。
     * auth_user：以auth_userinfo为scope发起的网页授权，是用来获取用户的基本信息的（比如头像、昵称等）。
     * 这种授权需要用户手动同意，用户同意后，就可在授权后获取到该用户的基本信息。
     * auth_zhima: 以auth_zhima为scope发起的网页授权，是用来获取用户的芝麻信用评分及相关信用信息。
     * 这种授权需要用户手动同意，用户同意后，就可在授权后获取到该用户的基本信息。
     * auth_ecard: 以auth_ecard为scope发起的网页授权，应用于商户会员卡开卡接口用户授权。
     * 这种授权需要用户手动同意，用户同意后，商户就可在授权后帮助用户开通会员卡。
     * @return null
     */
    public static function getOAuthToken($scope = "auth_base")
    {
        if (!isset($_GET['auth_code'])) {
            //触发支付宝返回code码
            $baseUrl = urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING']);
            $url     = "https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=" . self::$appId . "&scope=" . $scope . "&redirect_uri=" . $baseUrl;
            Header("Location: $url");
            exit();
        } else {
            //获取auth_code码，以获取openid
            $auth_code = $_GET['auth_code'];
            $request   = new \BaseRequest('alipay.system.oauth.token');
            $request->setParas(['code' => $auth_code, 'grant_type' => 'authorization_code']);
            $result = self::$aop->execute($request);
            return $result->{$request->getResponseNode()};
        }
    }

    public static function getOpenidFromMp($code)
    {
        //获取auth_code码，以获取openid
        $request = new \BaseRequest('alipay.system.oauth.token');
        $request->setCode($code);
        $request->setGrantType("authorization_code");
        $result             = self::$aop->execute($request);
        self::$access_token = $result->{$request->getResponseNode()}->access_token;
        return $result->{$request->getResponseNode()}->user_id;
    }

    // 这个方法要用到getOpenidFromMp方法，不然access_token为空
    public static function getUserInfoAfterGetOpenid()
    {
        $request = new \BaseRequest('alipay.user.info.share');
        $result  = self::$aop->execute($request, self::$access_token);
        return $result->{$request->getResponseNode()};
    }

    public static function getUserInfo()
    {

        $authUser = AlipayAPI::getOAuthToken("auth_user");
        $request  = new \BaseRequest('alipay.user.info.share');
        $result   = self::$aop->execute($request, $authUser->access_token);
        return $result->{$request->getResponseNode()};
    }

    public static function sendTemplateMsg($biz)
    {
        $biz = json_encode($biz);

        //https://doc.open.alipay.com/docs/doc.htm?spm=a219a.7629140.0.0.ucODFF&treeId=53&articleId=103463&docType=1
        $request = new \BaseRequest('alipay.open.public.message.single.send');
        $request->setBizContent($biz);
        $result = self::$aop->execute($request);
        return $result->{$request->getResponseNode()};
    }

    public function getPrivateKeyStr($pub_pem_path)
    {
        $content = file_get_contents($pub_pem_path);
        $content = str_replace("-----BEGIN RSA PRIVATE KEY-----", "", $content);
        $content = str_replace("-----END RSA PRIVATE KEY-----", "", $content);
        $content = str_replace("\r", "", $content);
        $content = str_replace("\n", "", $content);

        return $content;
    }

    public static function refund($biz)
    {
        $biz = json_encode($biz);

        //https://doc.open.alipay.com/docs/api.htm?spm=a219a.7386797.0.0.WOZM8z&docType=4&apiId=759
        $request = new \BaseRequest('alipay.trade.refund');
        $request->setBizContent($biz);
        $result = self::$aop->execute($request);

        return $result->{$request->getResponseNode()};
    }

    public static function buildZhimaRentOrderSubmitForm($biz)
    {
        $requestUrl = self::getZhimaRentOrderUrl($biz);
        $sHtml      = "<form id='zhimasubmit' name='zhimasubmit' action='" . $requestUrl . "' method='post'>";
        $sHtml      = $sHtml . "<script>document.forms['zhimasubmit'].submit();</script>";
        return $sHtml;
    }

    public static function getZhimaRentOrderUrl($biz)
    {
        $biz     = self::$aop->JSON($biz);
        $request = new \BaseRequest('zhima.merchant.order.rent.create');
        $request->setBizContent($biz);
        $requestUrl = self::$aop->execute($request, null, null, true);
        return $requestUrl;
    }

    public static function zhimaOrderRentComplete($biz)
    {
        $biz     = json_encode($biz);
        $request = new \BaseRequest('zhima.merchant.order.rent.complete');
        $request->setBizContent($biz);
        $result = self::$aop->execute($request);

        return $result->{$request->getResponseNode()};
    }

    public static function zhimaOrderRentQuery($biz)
    {
        $biz     = self::$aop->JSON($biz);
        $request = new \BaseRequest('zhima.merchant.order.rent.query');
        $request->setBizContent($biz);
        $result = self::$aop->execute($request);

        return $result->{$request->getResponseNode()};
    }

    public static function zhimaBorrowEntityUpload($biz)
    {
        $biz     = self::$aop->JSON($biz);
        $request = new \BaseRequest('zhima.merchant.borrow.entity.upload');
        $request->setBizContent($biz);
        $result = self::$aop->execute($request);

        return $result->{$request->getResponseNode()};
    }

    public static function AlipayDataDataserviceBillDownloadurlQuery($bill_date)
    {
        $requestDataArray = ['bill_type' => 'trade', 'bill_date' => $bill_date];
        $biz              = self::$aop->JSON($requestDataArray);
        $request          = new \BaseRequest('alipay.data.dataservice.bill.downloadurl.query');
        $request->setBizContent($biz);
        $result = self::$aop->execute($request);
        return $result->{$request->getResponseNode()};
    }

    // 手机网站支付2.0版本
    public static function zhimaOrderRentCancel($biz)
    {
        $biz     = self::$aop->JSON($biz);
        $request = new \BaseRequest('zhima.merchant.order.rent.cancel');
        $request->setBizContent($biz);
        $result = self::$aop->execute($request);

        return $result->{$request->getResponseNode()};
    }

    public static function mkAckMsg()
    {
        $response_xml = "<XML><ToUserId><![CDATA[" . self::$msgData['client'] . "]]></ToUserId><AppId><![CDATA[" . self::$appId . "]]></AppId><CreateTime>" . time() . "</CreateTime><MsgType><![CDATA[ack]]></MsgType></XML>";
        $return_xml   = self::$aop->signResponse($response_xml, "UTF-8");

        return $return_xml;
    }

    public static function verifyPayNotify()
    {
        return self::$aop->verifyPayNotify();
    }

    public function getPublicKeyStr($pub_pem_path)
    {
        $content = file_get_contents($pub_pem_path);
        $content = str_replace("-----BEGIN PUBLIC KEY-----", "", $content);
        $content = str_replace("-----END PUBLIC KEY-----", "", $content);
        $content = str_replace("\r", "", $content);
        $content = str_replace("\n", "", $content);

        return $content;
    }

    /**
     * @param array $requestParams
     * @return mixed 返回表单字符串
     */
    public static function buildAlipaySubmitForm(array $requestParams)
    {
        // 文档https://docs.open.alipay.com/203/107090/
        $request = new \BaseRequest('alipay.trade.wap.pay');
        $request->setReturnUrl($requestParams['return_url']);
        $request->setNotifyUrl($requestParams['notify_url']);
        $bizContentArray = [
            'subject'      => $requestParams['subject'],
            'out_trade_no' => $requestParams['orderid'],
            'total_amount' => $requestParams['price'],
            'productCode'  => 'QUICK_WAP_PAY', // 固定值
        ];
        $request->setBizContent(json_encode($bizContentArray, JSON_UNESCAPED_UNICODE));
        return self::$aop->pageExecute($request, "post");
    }
}