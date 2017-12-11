<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 采集信息表
 * Class CaijiMessage
 * @package App\Models
 */
class CaijiMessage extends Model
{
    protected $table = "xmt_caiji_message";
    protected $primaryKey = 'id';
    protected $guarded = ['id'];
    public $timestamps = false;
}
