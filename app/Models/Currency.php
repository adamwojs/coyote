<?php

namespace Coyote;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name'];

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @return array
     */
    public static function getCurrenciesList()
    {
        return self::pluck('name', 'id')->toArray();
    }
}
