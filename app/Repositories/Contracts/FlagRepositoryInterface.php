<?php

namespace Coyote\Repositories\Contracts;

interface FlagRepositoryInterface extends RepositoryInterface
{
    /**
     * @param array $topicsId
     * @return mixed
     */
    public function takeForTopics(array $topicsId);

    /**
     * @param array $postsId
     * @return mixed
     */
    public function takeForPosts(array $postsId);

    /**
     * @param int $jobId
     * @return mixed
     */
    public function takeForJob($jobId);

    /**
     * @param $key
     * @param $value
     */
    public function deleteBy($key, $value);
}