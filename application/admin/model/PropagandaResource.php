<?php

namespace app\admin\model;

use think\Model;

class PropagandaResource extends Model
{
    // 表名
    protected $name = 'propaganda_resource';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function propaganda_custom(){
        return $this->hasMany('propaganda_custom', 'id', 'id');
    }

}
