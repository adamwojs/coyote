<?php

namespace Coyote;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $type_id
 * @property int $user_id
 * @property string $subject
 * @property string $excerpt
 * @property string $url
 * @property string $guid
 * @property \Carbon\Carbon $read_at
 * @property bool $is_marked
 * @property Alert\Sender[] $senders
 */
class Alert extends Model
{
    const PM = 1;
    const TOPIC_SUBSCRIBER = 2;
    const TOPIC_MOVE = 3;
    const TOPIC_DELETE = 4;
    const POST_DELETE = 5;
    const POST_SUBSCRIBER = 6;
    const WIKI_SUBSCRIBER = 7;
    const WIKI_COMMENT = 8;
    const POST_EDIT = 10;
    const TOPIC_SUBJECT = 11;
    const POST_ACCEPT = 12;
    const POST_COMMENT_LOGIN = 13;
    const POST_LOGIN = 14;
    const MICROBLOG = 15;
    const MICROBLOG_LOGIN = 16;
    const POST_VOTE = 18;
    const MICROBLOG_VOTE = 19;
    const MICROBLOG_SUBSCRIBER = 20;
    const FLAG = 21;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['type_id', 'user_id', 'subject', 'excerpt', 'url', 'object_id', 'guid'];

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function type()
    {
        return $this->hasOne('Coyote\Alert\Type', 'id', 'type_id');
    }

    /**
     * @return mixed
     */
    public function senders()
    {
        // LEFT JOIN is on purpose. notification sender can be anonymous user (for example: post author)
        return $this->hasMany('Coyote\Alert\Sender')->leftJoin('users', 'users.id', '=', 'alert_senders.user_id');
    }
}
