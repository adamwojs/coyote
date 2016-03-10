<?php

namespace Coyote\Http\Controllers\Job;

use Carbon\Carbon;
use Coyote\Country;
use Coyote\Currency;
use Coyote\Events\JobWasSaved;
use Coyote\Firm;
use Coyote\Firm\Benefit;
use Coyote\Job;
use Coyote\Http\Controllers\Controller;
use Coyote\Repositories\Contracts\FirmRepositoryInterface;
use Coyote\Repositories\Contracts\JobRepositoryInterface;
use Illuminate\Http\Request;

class SubmitController extends Controller
{
    /**
     * @var JobRepositoryInterface
     */
    private $job;

    /**
     * @var FirmRepositoryInterface
     */
    private $firm;

    /**
     * SubmitController constructor.
     * @param JobRepositoryInterface $job
     * @param FirmRepositoryInterface $firm
     */
    public function __construct(JobRepositoryInterface $job, FirmRepositoryInterface $firm)
    {
        parent::__construct();
        $this->middleware('job.session', ['except' => ['getIndex', 'postIndex']]);

        $this->breadcrumb->push('Praca', route('job.home'));

        $this->job = $job;
        $this->firm = $firm;
    }

    /**
     * @param Request $request
     * @param int $id
     * @return \Illuminate\View\View
     */
    public function getIndex(Request $request, $id = 0)
    {
        $job = $this->job->findOrNew($id);

        if ($job->id) {
            $this->authorize('update', $job);

            $job->city = $job->locations()->get()->implode('name', ', ');
            $job->deadline = (new Carbon($job->deadline_at))->diff(Carbon::now())->days;
        }

        if ($request->session()->has('job') && !$request->has('revalidate')) {
            $job->forceFill($request->session()->get('job'));
        }

        $this->breadcrumb($job);
        $countryList = Country::lists('name', 'id');

        // @todo Uzyc mechanizmu geolokalizacji
        $defaultCountryId = array_search('Polska', $countryList->toArray());

        return $this->view('job.submit.home', [
            'countryList'       => $countryList,
            'defaultCountryId'  => $defaultCountryId,
            'currencyList'      => Currency::lists('name', 'id'),
            'employmentList'    => Job::getEmploymentList(),
            'rateList'          => Job::getRatesList()
        ])->with(
            compact('job')
        );
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postIndex(Request $request)
    {
        $this->validate($request, [
            'title'         => 'required|max:60',
            'country_id'    => 'required|integer',
            'currency_id'   => 'required|integer',
            'rate_id'       => 'required|integer',
            'employment_id' => 'required|integer',
            'city'          => 'string',
            'salary_from'   => 'integer',
            'salary_to'     => 'integer',
            'deadline'      => 'integer|min:1|max:365',
            'requirements'  => 'string',
            'recruitment'   => 'sometimes|required|string',
            'enable_apply'  => 'boolean',
            'email'         => 'sometimes|required|email'
        ]);

        $request->session()->put('job', $request->all());

        return redirect()->route('job.submit.firm');
    }

    /**
     * @param Request $request
     * @return $this
     */
    public function getFirm(Request $request)
    {
        // get all user firms...
        $firms = $this->firm->findAllBy('user_id', $this->userId);

        $job = $request->session()->get('job');
        // it must be firm_id from "job" array (in case we are editing an offer)
        $firm = $this->firm->findOrNew((int) $job['firm_id']);

        // get firm benefits only if firm really exists. that's why we need to check if ID is really set
        if (!empty($firm->id)) {
            $this->authorize('update', $firm);
        } elseif ($firms->count()) {
            $firm = $firms->first();
            $job['firm_id'] = $firm->id; // it's really important to assign default firm id to job offer
        }

        $firm->benefits = $firm->benefits()->lists('name')->toArray();

        if ($request->session()->has('firm')) {
            $firm->forceFill($request->session()->get('firm'));
        }

        $this->breadcrumb($job);

        return $this->view('job.submit.firm', [
            'employeesList'     => Firm::getEmployeesList(),
            'foundedList'       => Firm::getFoundedList(),
            'benefitsList'      => Benefit::getBenefitsList(), // default benefits,
        ])->with(
            compact('job', 'firm', 'firms')
        );
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postFirm(Request $request)
    {
        if (!$request->get('private')) {
            $this->validate($request, [
                'name' => 'required|max:60',
                'is_agency' => 'boolean',
                'logo' => 'string',
                'website' => 'url',
                'employees' => 'integer',
                'founded' => 'integer',
                'headline' => 'string|max:100',
                'description' => 'string',
                'latitude' => 'numeric',
                'longitude' => 'numeric',
                'street' => 'string|max:255',
                'city' => 'string|max:255',
                'house' => 'string|max:50',
                'postcode' => 'string|max:50'
            ], [
                'name.required' => 'Nazwa firmy jest wymagana'
            ]);

            $request->session()->put('firm', $request->all());
        }

        return redirect()->route('job.submit.preview');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getPreview(Request $request)
    {
        $job = $request->session()->get('job');
        $this->breadcrumb($job);

        return $this->view('job.submit.preview');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function save(Request $request)
    {
        $data = $request->session()->get('job');

        $job = $this->job->findOrNew((int) $data['id']);
        if (!$job->id) {
            $job->user_id = $this->userId;
        }

        $this->authorize('update', $job);

        $locations = array_filter(array_unique(array_map('trim', preg_split('/[\/,]/', $data['city']))));
        $job->fill($data);

        $job->deadline_at = Carbon::now()->addDay($data['deadline']);
        $job->path = str_slug($data['title'], '_');

        \DB::transaction(function () use (&$job, $request, $locations) {
            if ($request->session()->has('firm')) {
                $data = $request->session()->get('firm');
                $firm = $this->firm->findOrNew((int) $data['id']);

                if (empty($firm->id)) {
                    $firm->user_id = $this->userId;
                }

                $this->authorize('update', $firm);

                $firm->fill($data)->save();
                $job->firm_id = $firm->id;

                $firm->benefits()->delete();
                $benefits = array_filter(array_unique(array_map('trim', $data['benefits'])));

                foreach ($benefits as $benefit) {
                    $firm->benefits()->create([
                        'name' => $benefit
                    ]);
                }
            }

            $job->save();
            $job->locations()->delete();

            foreach ($locations as $location) {
                $job->locations()->create([
                    'name' => $location
                ]);
            }

            event(new JobWasSaved($job));
            $request->session()->forget(['job', 'firm']);
        });

        return redirect()->route('job.offer', [$job->id, $job->path])->with('success', 'Oferta została prawidłowo dodana.');
    }

    /**
     * @param $job
     */
    private function breadcrumb($job)
    {
        if (empty($job['id'])) {
            $this->breadcrumb->push('Wystaw ofertę pracy', route('job.submit'));
        } else {
            $this->breadcrumb->push($job['title'], route('job.offer', [$job['id'], $job['path']]));
            $this->breadcrumb->push('Edycja oferty', route('job.submit'));
        }
    }
}