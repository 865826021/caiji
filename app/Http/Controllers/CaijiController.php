<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Caiji;
use App\Models\CaijiMessage;
use Illuminate\Support\Facades\Log;
use \App\Services\TransferService;

class CaijiController extends Controller
{
    public function caiji(Request $request)
    {
        $data = $request->all();
        $messageType = $request->post('message_type');
        $postType = $request->post('post_type');
        $groupId = $request->post('group_id');
        $userId = $request->post('user_id');
        $message = html_entity_decode($request->post('message'));

        if (!($postType == 'message' && $messageType == 'group')) {
            return ['msg'=>'notGroupMessage'];
        }

        $messages = explode("\n", trim($message));

        if (strpos($message, '复制这条信息') || strpos($message, '淘口令') || strpos($message, '限时') || strpos($message, '★') || strpos($message, '手慢无') || strpos($message, '拍下发')) {
            Log::info("过滤");
            Log::info($message);
            return ['msg'=>'invalid'];
        }

        //匹配券
        if (!preg_match("/https?:\/\/(((market|shop)\.m)|(taoquan))\.taobao\.com\/[A-Za-z0-9&=_\?\.\/]+/", $message, $matchQuanUrl)) {
            Log::info("无券链接");
            Log::info($message);
            return ['msg'=>'notCouponUrl'];
        }
        $quanUrl = $matchQuanUrl[0];

        //匹配商品
        $goodsUrl = null;
        if (preg_match("/https?:\/\/((item\.taobao)|(detail\.tmall))\.com\/[A-Za-z0-9&=_\?\.\/]+/", $message, $matchGoodsUrl)) {
            $goodsUrl = $matchGoodsUrl[0];
        } else if (preg_match("/https?:\/\/s\.click\.taobao\.com\/[A-Za-z0-9&=_\?\.\/]+/", $message, $matchGoodsUrl)) {
            $goodsUrl = $matchGoodsUrl[0];
            $goodsUrl = (new TransferService())->getFinalUrl($goodsUrl);
        }

        if (!$goodsUrl) {
            Log::info("无商品地址");
            Log::info($message);
            return ['msg'=>'notGoodsUrl'];
        }

        //匹配图片
        $picField = $messages[0];
        if (!preg_match("/\[CQ:image.*?url=(.*?)\]/", $picField, $matchPic)) {
            Log::info("无主图");
            Log::info($messages);
            return ['msg'=>'notPic'];
        }
        $pic = urldecode($matchPic[1]);

        //匹配标题,描述
        $messageText = preg_replace("/^.*?http.*\n?/m", "", $message);
        $messageText = preg_replace("/^.*?(原价|抢券|领券|优惠券|佣金|秒过|卷后|券后|劵后|深夜福利|转发|分界线|---|===).*\n?/m", "", $messageText);
        $messageTextArray = explode("\n", $messageText);
        foreach ($messageTextArray as $key => $val) {
            //中文字数不够的行直接过滤
            preg_match_all("/[\x{4e00}-\x{9fa5}]/um", $val, $matchWord);
            if (count($matchWord[0]) < 5) {
                unset($messageTextArray[$key]);
            }
        }
        $messageTextArray = array_values($messageTextArray);

        $title = $messageTextArray[0];
        if(!empty($title)&&ctype_space($title)){
            Log::info("标题是空格");
            Log::info($goodsUrl);
            return ['msg'=>'titleIsSpace'];
        }
        $description = $messageTextArray[1];
        if (strpos($title, '夜猫子') || strpos($message, '爆款优品排行') || strpos($message, '秒杀排行') || strpos($message, '突袭秒杀') || strpos($message, '秒杀爆款')) {
            $title = $messageTextArray[1];
            $description = $messageTextArray[2];
        }

        //匹配商品ID
        try {
            parse_str(parse_url($goodsUrl)['query'], $query);
            $goodsId = $query['id'];
        } catch (\Exception $e) {
            Log::info("商品地址解析失败");
            Log::info($goodsUrl);
            return ['msg'=>'goodsUrlParseFailed'];
        }

        //匹配券ID,卖家ID
        parse_str(parse_url($quanUrl)['query'], $query);
        $couponId = isset($query['activityId']) ? $query['activityId'] : '';
        $couponId = isset($query['activity_id']) ? $query['activity_id'] : $couponId;
        if(!$couponId){
            Log::info("没有券ID");
            Log::info($message);
            return ['msg'=>'notCouponId'];
        }

        $sellerId = isset($query['sellerId']) ? $query['sellerId'] : '';
        $sellerId = isset($query['seller_id']) ? $query['seller_id'] : $sellerId;
        if(!$sellerId){
            Log::info("没有卖家ID");
            Log::info($message);
            return ['msg'=>'notSellerId'];
        }

        // 采集商品表字段.
        $param = [
            'title' => $title,
            'qqgroup_id' => $groupId,
            'qquser_id' => $userId,
            'description' => $description,
            'goods_id' => $goodsId,
            'coupon_id' => $couponId,
            'seller_id' => $sellerId,
            'add_time' => date('Y-m-d H:i:s'),
            'pic' => $pic
        ];

        $data = Caiji::where('coupon_id', $couponId)->first();

        // 判断商品表是否存在,不存在创建.
        if($data == NULL){
            $caiji = Caiji::create($param);
            $caijiId = $caiji->id;

            // 插入采集信息表.
            $msg = [
                'caiji_id' => $caijiId,
                'message' => $message,
                'add_time' => date('Y-m-d H:i:s')
            ];
            CaijiMessage::create($msg);

            return ['msg'=>'writeOk'];
        }

        // 存在则更新.
        $data->update($param);
        $caijiId = $data->id;

        // 更新采集信息表.
        $msg = [
            'caiji_id' => $caijiId,
            'message' => $message,
            'add_time' => date('Y-m-d H:i:s')
        ];
        CaijiMessage::where('caiji_id', $caijiId)->update($msg);

        return ['msg'=>'updateOk'];
    }
}
