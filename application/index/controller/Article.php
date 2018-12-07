<?php
namespace app\index\controller;
use think\Controller;
use think\Request;
use think\Cookie;
use app\babysbreath\model\Operation;

class Article extends Comm{
    public function article_detail(){
        $aid = input('aid');

        db('article') -> where('articleId',$aid) -> setInc('articleClicks');

        $comment = db('comment') ->alias('c') -> where(['c.status' => 1,'c.commentArticleid' => $aid]) -> order('c.addTime desc') -> field('c.*') -> select();
        foreach($comment as $k => $v){
            $ip = Request::instance() -> ip();
            $ip = explode('.',$ip);
            $ip = implode($ip);

            $comment[$k]['addTime'] = date('Y年m月d日 H:i:s',strtotime($comment[$k]['addTime']));
            $comment[$k]['reply'] = db('replycomment') -> where(['replyCid' => $comment[$k]['commentId']]) -> order('addTime desc') -> select();
            foreach($comment[$k]['reply'] as $k2 => $v2){

                if(isset($_COOKIE['pstate_'.$ip.'_replycomment_'.$comment[$k]['reply'][$k2]['replyId']])){
                    $comment[$k]['reply'][$k2]['praiseState'] = 1;
                }else{
                    $comment[$k]['reply'][$k2]['praiseState'] = 0;
                }

            }

            if(isset($_COOKIE['pstate_'.$ip.'_comment_'.$comment[$k]['commentId']])){
                $comment[$k]['praiseState'] = 1;
            }else{
                $comment[$k]['praiseState'] = 0;
            }
        }

        //文章评论
        $this->assign('comment',$comment);

        //查询指定ID的文章详情数据
        $adata = db('article') -> alias('a') -> join('cate c','a.articleCate = c.cateId') -> where('articleId',$aid) -> field('a.*,c.cateName,c.cateId') -> find();

        if($adata !== null){
            $about = db('aboutme') -> where('meId',$adata['ator']) -> find();

            //strtotime() 函数将任何英文文本的日期或时间描述解析为 Unix 时间戳（自 January 1 1970 00:00:00 GMT 起的秒数）
            $date= strtotime($adata['addTime']);
            $adata['addTime'] = date("Y-m-d",$date);

            $adata['articleKeywords'] = explode('|',$adata['articleKeywords']);

            $adata['articleTags'] = explode('|', $adata['articleTags']);

            //下一篇
            $down = db('article') -> where('status',1) -> where('articleId','>',$aid) -> order('articleId asc') -> field('articleId,articleTitle') -> find();

            //上一篇
            $up = db('article') -> where('status',1) -> where('articleId','<',$aid) -> order('articleId desc') -> field('articleId,articleTitle') -> find();

            $click = input('clickAid');
            if($click){
                db('article') -> where('articleId',$click) -> setInc('articleClicks');
            }

            $this->assign([
                'a'   => $adata,
                'am'   => $about,
                'up'   => $up,
                'down' => $down,
                'aid'  => $aid
            ]);

        }

        return view();
    }

    //栏目文章
    public function article(){
        if(input('cid') !== null){
            //栏目文章列表
            $cateId = input('cid');
            $cName = db('cate') -> where('cateId',$cateId) -> field('cateName') -> find();

            $articles = db('article') -> alias('a') -> join('cate c','a.articleCate = c.cateId') -> where(['a.articleCate' => $cateId,'a.status' => 1]) -> field('a.*,c.cateName') -> order('addtime desc') -> paginate(13,false,['query' => Request::instance() -> param()]);//'query' => Request::instance() -> param() ::: 保留原有的参数

            $this -> assign([
                'cateName' => $cName['cateName'],
                'n'   => '1',
                'art' => $articles,
            ]);

        }else{
            $articles = db('article') -> alias('a') -> join('cate c','a.articleCate = c.cateId') -> where('a.status',1) -> field('a.*,c.cateName') -> order('rand()') -> paginate(13);
            $this -> assign('art',$articles);
        }
        return view();
    }

    public function comment(){
        if(request()->isPost()){

            $comment['commentArticleid'] = input('aid');
            $comment['commentContent'] = strip_tags(input('content'),'<img>');
            $comment['atorIP'] = Request::instance() -> ip(); // 获取用户IP地址

            $getAtorName = new randName();
            $comment['randAtorName'] = $getAtorName -> getName(2);

            if($comment){
                if($comment['commentContent'] == ''){
                    $info = array('code' => '-1','message' => '请输入评论内容');
                    echo json_encode($info);die;
                }else{
                    $insert = db('comment') -> insert($comment);
                    $newComment = db('comment') -> where('status',1) -> order('addTime desc') -> find();
                    if($insert){
                        $info = array('code' => '1','message' => '评论成功','data' => $newComment);

                        //操作记录
                        $op = new Operation();
                        $op -> op('add','评论','访问者：'.$comment['randAtorName'],'评论ID：'.$newComment['commentId']);
                    }else{
                        $info = array('code' => '0','message' => '评论失败');
                    }
                    echo json_encode($info);die;
                }
            }
        }

        return view();
    }

    public function reply(){
        if(request() -> isPost()){
            $reply = input('post.');
            $reply['atorIP'] = Request::instance() -> ip();

            if($reply){
                if($reply['replyContent'] == ''){
                    $info = array('code' => '-1','message' => '请输入回复内容');
                    echo json_encode($info);die;
                }else{
                    $insResult = db('replycomment') -> insert($reply);
                    $showReply = db('replycomment') -> where('status',1) -> order('addTime desc') -> find();
                    if($insResult){
                        $info = array('code' => 1,'message' => '回复成功','data' => $showReply);

                        //操作记录
                        $op = new Operation();
                        $op_admin = session('adminName');
                        $op -> op('replyComment','回复评论',$op_admin,'回复评论ID：'.$showReply['replyCid']);
                    }else{
                        $info = array('code' => 0,'message' => '回复失败');
                    }
                }
                echo json_encode($info);die;
            }
        }
    }

    public function praise(){
        $id = input('id');
        $type = input('type');
        if( $type == 'c' ){

            $t_id = 'comment_'.$id;
            $field = 'comment';
            $where = 'commentId';
            $cPraise = db('comment') -> where('commentId',$id) -> field('praise') -> find();
            $pnum = $cPraise['praise'];

        }else if( $type == 'r' ){

            $t_id = 'replycomment_'.$id;
            $field = 'replycomment';
            $where = 'replyId';
            $rPraise = db('replycomment') -> where('replyId',$id) -> field('praise') -> find();
            $pnum = $rPraise['praise'];

        }
        $ip = Request::instance() -> ip();

        $ip = explode('.',$ip);
        $ip = implode($ip);

        $cpinfo = $ip."_".$t_id;

        if( !isset($_COOKIE['pstate_'.$ip.'_'.$t_id]) ){

            $pnum = $pnum + 1;

            db($field) -> where($where,$id) -> update(['praise' => $pnum]);
            $info = array('code' => 1, 'message' => '点赞','pnum' => $pnum);
            Cookie::set('pstate_'.$ip.'_'.$t_id,$cpinfo);//将用户id存放入cookie里，用来验证用户登录点赞的唯一性

        }else if( $_COOKIE['pstate_'.$ip.'_'.$t_id] == $cpinfo ){

            $pnum = $pnum - 1;

            db($field) -> where($where,$id) -> update(['praise' => $pnum]);
            $info = array('code' => 0, 'message' => '取消','pnum' => $pnum);
            Cookie::delete( 'pstate_'.$ip.'_'.$t_id );//取消赞后，将此用户id从cookie中删除

        }else{
            $info = array('code' => -100, 'message' => '未知错误');
        }
        echo json_encode($info);die;
    }
}

//随机获取姓名
class randName{
    function rndChinaName(){
        $this->getXingList();
        $this->getMingList();
    }

    /* 获取姓列表 */
    private function getXingList(){
        $Xing=array(
            '赵','钱','孙','李','周','吴','郑','王','冯','陈','褚','卫','蒋',
            '沈','韩','杨','朱','秦','尤','许','何','吕','施','张','孔','曹','严','华','金','魏',
            '陶','姜','戚','谢','邹','喻','柏','水','窦','章','云','苏','潘','葛','奚','范','彭',
            '郎','鲁','韦','昌','马','苗','凤','花','方','任','袁','柳','鲍','史','唐','费','薛',
            '雷','贺','倪','汤','滕','殷','罗','毕','郝','安','常','傅','卞','齐','元','顾','孟',
            '平','黄','穆','萧','尹','姚','邵','湛','汪','祁','毛','狄','米','伏','成','戴','谈',
            '宋','茅','庞','熊','纪','舒','屈','项','祝','董','梁','杜','阮','蓝','闵','季','贾',
            '路','娄','江','童','颜','郭','梅','盛','林','钟','徐','邱','骆','高','夏','蔡','田',
            '樊','胡','凌','霍','虞','万','支','柯','管','卢','莫','柯','房','裘','缪','解','应',
            '宗','丁','宣','邓','单','杭','洪','包','诸','左','石','崔','吉','龚','程','嵇','邢',
            '裴','陆','荣','翁','荀','于','惠','甄','曲','封','储','仲','伊','宁','仇','甘','武',
            '符','刘','景','詹','龙','叶','幸','司','黎','溥','印','怀','蒲','邰','从','索','赖',
            '卓','屠','池','乔','胥','闻','莘','党','翟','谭','贡','劳','逄','姬','申','扶','堵',
            '冉','宰','雍','桑','寿','通','燕','浦','尚','农','温','别','庄','晏','柴','瞿','阎',
            '连','习','容','向','古','易','廖','庾','终','步','都','耿','满','弘','匡','国','文',
            '寇','广','禄','阙','东','欧','利','师','巩','聂','关','荆','司马','上官','欧阳','夏侯',
            '诸葛','闻人','东方','赫连','皇甫','尉迟','公羊','澹台','公冶','宗政','濮阳','淳于','单于',
            '太叔','申屠','公孙','仲孙','轩辕','令狐','徐离','宇文','长孙','慕容','司徒','司空');
        return $Xing;

    }

    /* 获取名列表 */
    private function getMingList()
    {
        $Ming=array(
            '伟','刚','勇','毅','俊','峰','强','军','平','保','东','文','辉','力','明','永','健','世','广','志','义',
            '兴','良','海','山','仁','波','宁','贵','福','生','龙','元','全','国','胜','学','祥','才','发','武','新',
            '利','清','飞','彬','富','顺','信','子','杰','涛','昌','成','康','星','光','天','达','安','岩','中','茂',
            '进','林','有','坚','和','彪','博','诚','先','敬','震','振','壮','会','思','群','豪','心','邦','承','乐',
            '绍','功','松','善','厚','庆','磊','民','友','裕','河','哲','江','超','浩','亮','政','谦','亨','奇','固',
            '之','轮','翰','朗','伯','宏','言','若','鸣','朋','斌','梁','栋','维','启','克','伦','翔','旭','鹏','泽',
            '晨','辰','士','以','建','家','致','树','炎','德','行','时','泰','盛','雄','琛','钧','冠','策','腾','楠',
            '榕','风','航','弘','秀','娟','英','华','慧','巧','美','娜','静','淑','惠','珠','翠','雅','芝','玉','萍',
            '红','娥','玲','芬','芳','燕','彩','春','菊','兰','凤','洁','梅','琳','素','云','莲','真','环','雪','荣',
            '爱','妹','霞','香','月','莺','媛','艳','瑞','凡','佳','嘉','琼','勤','珍','贞','莉','桂','娣','叶','璧',
            '璐','娅','琦','晶','妍','茜','秋','珊','莎','锦','黛','青','倩','婷','姣','婉','娴','瑾','颖','露','瑶',
            '怡','婵','雁','蓓','纨','仪','荷','丹','蓉','眉','君','琴','蕊','薇','菁','梦','岚','苑','婕','馨','瑗',
            '琰','韵','融','园','艺','咏','卿','聪','澜','纯','毓','悦','昭','冰','爽','琬','茗','羽','希','欣','飘',
            '育','滢','馥','筠','柔','竹','霭','凝','晓','欢','霄','枫','芸','菲','寒','伊','亚','宜','可','姬','舒',
            '影','荔','枝','丽','阳','妮','宝','贝','初','程','梵','罡','恒','鸿','桦','骅','剑','娇','纪','宽','苛',
            '灵','玛','媚','琪','晴','容','睿','烁','堂','唯','威','韦','雯','苇','萱','阅','彦','宇','雨','洋','忠',
            '宗','曼','紫','逸','贤','蝶','菡','绿','蓝','儿','翠','烟','小','轩','坤');
        return $Ming;
    }

    // 获取姓
    private function getXing(){
        // mt_rand() 比rand()方法快四倍，而且生成的随机数比rand()生成的伪随机数无规律。

        $getXL = $this -> getXingList();
        $countX = count($getXL);
        $x = mt_rand(0,$countX-1);
        $nameX = $getXL[$x];
        return $nameX;
    }

    // 获取名字
    private function getMing(){
        $getML = $this -> getMingList();
        $countM = count($getML);
        $m = mt_rand(0,$countM-1);
        $nameM = $getML[$m];
        return $nameM;
    }

    // 获取名字
    public function getName($type=0){
        $name = '' ;
        switch($type){
            case 1:    //2字
                $name = $this->getXing().$this->getMing();
                break;
            case 2:    //随机2、3个字
                $name = $this->getXing().$this->getMing();
                if(mt_rand(0,100) > 50)$name .= $this->getMing();
                break;
            case 3: //只取姓
                $name = $this->getXing();
                break;
            case 4: //只取名
                $name = $this->getMing();
                break;
            case 0:
            default: //默认情况 1姓+2名
                $name = $this->getXing().$this->getMing().$this->getMing();
        }
        return $name;
    }

}