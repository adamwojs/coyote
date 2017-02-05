<?php

namespace Coyote\Http\Controllers\Profile;

use Coyote\Http\Controllers\Controller;
use Coyote\Http\Controllers\User\UserMenuTrait;
use Coyote\Http\Forms\User\SkillsForm;
use Coyote\Repositories\Contracts\PostRepositoryInterface as PostRepository;
use Coyote\Repositories\Contracts\ReputationRepositoryInterface as ReputationRepository;
use Coyote\Repositories\Contracts\UserRepositoryInterface as UserRepository;
use Coyote\Repositories\Criteria\Forum\OnlyThoseWithAccess;
use Coyote\Repositories\Criteria\Microblog\OnlyMine;
use Coyote\Repositories\Eloquent\MicroblogRepository;
use Coyote\User;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    use UserMenuTrait;

    /**
     * @var UserRepository
     */
    private $user;

    /**
     * @var ReputationRepository
     */
    private $reputation;

    /**
     * @var PostRepository
     */
    private $post;

    /**
     * @var MicroblogRepository
     */
    private $microblog;

    /**
     * @param UserRepository $user
     * @param ReputationRepository $reputation
     * @param PostRepository $post
     * @param MicroblogRepository $microblog
     */
    public function __construct(
        UserRepository $user,
        ReputationRepository $reputation,
        PostRepository $post,
        MicroblogRepository $microblog
    ) {
        parent::__construct();

        $this->user = $user;
        $this->reputation = $reputation;
        $this->post = $post;
        $this->microblog = $microblog;

        $this->breadcrumb->push('Użytkownicy', route('profile.list'));
    }

    /**
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $this->user->resetCriteria();

        $users = $this->user->paginate();

        return $this->view('profile.list', [
            'users'         => $users->items(),
            'pagination'    => $users->render()
        ]);
    }

    /**
     * @param \Coyote\User $user
     * @param string $tab
     * @return \Illuminate\View\View
     */
    public function show($user, $tab = 'reputation')
    {
        $this->breadcrumb->push($user->name, route('profile', ['user' => $user->id]));
        $this->public['reputation_url'] = route('profile.history', [$user->id]);

        $menu = $this->getUserMenu();

        if ($menu->get('profile')) {
            // activate "Profile" tab no matter what.
            $menu->get('profile')->activate();
        }

        return $this->view('profile.home')->with([
            'top_menu'      => $menu,
            'user'          => $user,
            'skills'        => $user->skills()->orderBy('order')->get(),
            'rate_labels'   => SkillsForm::RATE_LABELS,
            'tab'           => strtolower($tab),
            'module'        => $this->$tab($user)
        ]);
    }

    /**
     * @param $user
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function history($user, Request $request)
    {
        return view('profile.partials.reputation_list', [
            'reputation' => $this->reputation->history($user->id, $request->input('offset'))
        ]);
    }

    /**
     * @param User $user
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    private function reputation(User $user)
    {
        return view('profile.partials.reputation', [
            'user'          => $user,
            'rank'          => $this->user->rank($user->id),
            'total_users'   => $this->user->countUsersWithReputation(),
            'reputation'    => $this->reputation->history($user->id),
            'chart'         => $this->reputation->chart($user->id),
        ]);
    }

    /**
     * Singular name of method because of backward compatibility.
     *
     * @param User $user
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    private function post(User $user)
    {
        $this->post->pushCriteria(new OnlyThoseWithAccess(auth()->user()));

        return view('profile.partials.posts', [
            'user'          => $user,
            'pie'           => $this->post->pieChart($user->id),
            'line'          => $this->post->lineChart($user->id),
            'comments'      => $this->post->countComments($user->id),
            'given_votes'   => $this->post->countGivenVotes($user->id),
            'received_votes'=> $this->post->countReceivedVotes($user->id),
        ]);
    }

    /**
     * @param User $user
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    private function microblog(User $user)
    {
        $this->microblog->resetCriteria();
        $this->microblog->pushCriteria(new OnlyMine($user->id));

        $microblogs = $this->microblog->paginate(10);

        return view('profile.partials.microblog', [
            'user'          => $user,
            'microblogs'    => $microblogs->items(),
            'pagination'    => $microblogs->render()
        ]);
    }
}
