<?php

namespace Coyote\Http\Controllers\Profile;

use Coyote\Http\Controllers\Controller;
use Coyote\Repositories\Eloquent\UserRepository;
use Illuminate\Contracts\View\View;

/**
 * ListController
 */
class ListController extends Controller
{
    /**
     * @var UserRepository
     */
    private $user;

    /**
     * @param UserRepository $user
     */
    public function __construct(UserRepository $user)
    {
        parent::__construct();

        $this->user = $user;
    }

    /**
     * @return View
     */
    public function index()
    {
        return $this->view('profile.list', [
            'users' => [],
        ]);
    }
}
