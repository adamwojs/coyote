<?php

namespace Coyote;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Topic extends Model
{
    use SoftDeletes, Sortable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['subject', 'path', 'forum_id', 'is_sticky', 'is_announcement'];

    /**
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:se';

    /**
     * @param $query
     * @param $userId
     * @return mixed
     */
    public function scopeForUser($query, $userId)
    {
        return $query->whereIn('topics.id', function ($sub) use ($userId) {
            return $sub->select('topic_id')
                ->from('topic_users')
                ->where('user_id', $userId);
        });
    }

    /**
     * @param $query
     * @param $userId
     * @return mixed
     */
    public function scopeSubscribes($query, $userId)
    {
        return $query->whereIn('topics.id', function ($sub) use ($userId) {
            return $sub->select('topic_id')
                ->from('topic_subscribers')
                ->where('user_id', $userId);
        });
    }

    /**
     * @param $subject
     */
    public function setSubjectAttribute($subject)
    {
        $this->attributes['subject'] = $subject;
        $this->attributes['path'] = str_slug($subject, '_');
    }

    /**
     * @return mixed
     */
    public function tags()
    {
        return $this->hasMany('Coyote\Topic\Tag')->join('tags', 'tags.id', '=', 'tag_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscribers()
    {
        return $this->hasMany('Coyote\Topic\Subscriber');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function users()
    {
        return $this->hasMany('Coyote\Topic\User');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function forum()
    {
        return $this->belongsTo('Coyote\Forum');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function page()
    {
        return $this->morphOne('Coyote\Page', 'content');
    }

    /**
     * Subscribe/unsubscribe to topic
     *
     * @param int $userId
     * @param bool $flag
     */
    public function subscribe($userId, $flag)
    {
        if (!$flag) {
            $this->subscribers()->where('user_id', $userId)->delete();
        } else {
            $this->subscribers()->firstOrCreate(['topic_id' => $this->id, 'user_id' => $userId]);
        }
    }
}
