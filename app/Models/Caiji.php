<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 采集商品表
 * Class Caiji
 * @package App\Models
 */
class Caiji extends Model
{
    protected $table = "xmt_caiji";
    protected $guarded = ['id'];
    public $timestamps = false;

}
