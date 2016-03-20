<?php

namespace Coyote\Job;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['job_id', 'tag_id', 'priority'];

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'job_tags';

    /**
     * @var array
     */
    public $timestamps = false;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function tag()
    {
        return $this->hasOne('Coyote\Tag');
    }
}