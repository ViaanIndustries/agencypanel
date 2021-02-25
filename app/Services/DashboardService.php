<?php

namespace App\Services;

use Input;
use Redirect;
use Config;
use Session;
use App\Repositories\Contracts\CustomerInterface;
use App\Repositories\Contracts\OrderInterface;
use App\Repositories\Contracts\ContentInterface;
use App\Repositories\Contracts\PassbookInterface;


class DashboardService
{
    protected $orderRep;
    protected $customerRep;
    protected $contentRep;
    protected $passbookRep;

    public function __construct(
        OrderInterface $orderRep,
        CustomerInterface $customerRep,
        ContentInterface $contentRep,
        PassbookInterface $passbookRep
    )
    {
        $this->orderRep = $orderRep;
        $this->customerRep = $customerRep;
        $this->contentRep = $contentRep;
        $this->passbookRep = $passbookRep;
    }


    public function usersStats($request)
    {
        $results = [];
        $requestData = $request->all();

        $customers = [];
        $artists = [];

        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';
        $created_at = (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '';
        $created_at_end = (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '';

        $appends_array = [
            'artist_id' => $artist_id
//            'created_at' => $created_at,
//            'created_at_end' => $created_at_end
        ];

        // Customers Info
        $customers['recent'] = $this->customerRep->getDashboardStatsQuery($requestData)->where('status', 'active')->take(5)->get(['picture', 'first_name', 'last_name', 'artists'])->toArray();
        $customers['acitve_count'] = $this->customerRep->getDashboardStatsQuery($requestData)->where('status', 'active')->count();
        $customers['banned_count'] = $this->customerRep->getDashboardStatsQuery($requestData)->where('status', 'banned')->count();

        $customers['identity_wise']['google'] = $this->customerRep->getDashboardStatsQuery($requestData)->where('status', 'active')->where('identity', 'google')->count();
        $customers['identity_wise']['facebook'] = $this->customerRep->getDashboardStatsQuery($requestData)->where('status', 'active')->where('identity', 'facebook')->count();
        $customers['identity_wise']['direct'] = $this->customerRep->getDashboardStatsQuery($requestData)->where('status', 'active')->where('identity', 'email')->count();

        $customers['platform_wise']['android'] = $this->customerRep->getDashboardStatsQuery($requestData)->where('status', 'active')->where('platforms', 'android')->count();
        $customers['platform_wise']['ios'] = $this->customerRep->getDashboardStatsQuery($requestData)->where('status', 'active')->where('platforms', 'ios')->count();
        $customers['platform_wise']['web'] = $this->customerRep->getDashboardStatsQuery($requestData)->where('status', 'active')->where('platforms', 'web')->count();

        $artistwise_customers = $this->customerRep->getArtistWiseCustomerStats($requestData);
        $customers['artist_wise'] = $artistwise_customers;
        $results['customers'] = $customers;
        $results['appends_array'] = $appends_array;

        // Artists Info
        $artists['acitve_count'] = count($artistwise_customers);
        $results['artists'] = $artists;

        return $results;
    }


    public function contentsStats($request)
    {
        $results = [];
        $requestData = $request->all();
        $contents = [];

        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : 'active';
        $created_at = (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '';
        $created_at_end = (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '';
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $appends_array = [
            'created_at' => $created_at,
            'created_at_end' => $created_at_end,
            'artist_id' => $artist_id,
        ];

        $contents['artist_wise'] = $this->contentRep->getArtistWiseContentStats($requestData);
        $contents['recent_comments'] = $this->contentRep->getRecentComments($requestData);
        $contents['recent_contents'] = $this->contentRep->getDashboardStatsQuery($requestData)->take(5)->get()->toArray();
        $contents['total']['likes'] = intval($this->contentRep->getDashboardStatsQuery($requestData)->sum('stats.likes'));
        $contents['total']['comments'] = intval($this->contentRep->getDashboardStatsQuery($requestData)->sum('stats.comments'));
        $contents['total']['photos'] = $this->contentRep->getDashboardStatsQuery($requestData)->where('type', 'photo')->count();
        $contents['total']['videos'] = $this->contentRep->getDashboardStatsQuery($requestData)->where('type', 'video')->count();
        $contents['total']['stickers'] = $this->contentRep->getDashboardStickersStatsQuery($requestData)->where('live_type', 'stickers')->count();
        $results['contents'] = $contents;
        $results['appends_array'] = $appends_array;


        return $results;
    }


    public function salesStats($request)
    {
        $requestData = $request->all();
        $results = $this->orderRep->getDashboardSalesStats($requestData);
        return $results;
    }

    public function overviewUserStats()
    {
        $results = [];
        $artistwise_customers = $this->customerRep->getArtistWiseCustomerStats($requestData = Null);
        $results['artistwise_acitve_count'] = count($artistwise_customers);

        $artistwise_contestants = $this->customerRep->getArtistWiseContestantStats($requestData = Null);
        $results['contestant_acitve_count'] = $artistwise_contestants;
        $results['banned_count'] = $this->customerRep->getDashboardStatsQuery('')->where('status', 'banned')->count();
        $results['acitve_count'] = $this->customerRep->getDashboardStatsQuery('')->where('status', 'active')->count();

        return $results;
    }

    public function overviewSalesStats()
    {
        $results = [];
        $results['orders_count'] = $this->orderRep->getOrderQuery('')->count();
        $results['coins'] = $this->orderRep->getOrderQuery('')->sum('package_coins');
        $results['prices'] = $this->orderRep->getOrderQuery('')->sum('package_price');
        $results['xp_earns'] = $this->orderRep->getOrderQuery('')->sum('package_xp');
        return $results;
    }

    public function overviewContentsStats()
    {
        $results = [];
        $results['total']['likes'] = intval($this->contentRep->getDashboardStatsQuery('')->sum('stats.likes'));
        $results['total']['comments'] = intval($this->contentRep->getDashboardStatsQuery('')->sum('stats.comments'));
        $results['total']['photos'] = $this->contentRep->getDashboardStatsQuery('')->where('type', 'photo')->count();
        $results['total']['videos'] = $this->contentRep->getDashboardStatsQuery('')->where('type', 'video')->count();
        $results['total']['stickers'] = $this->contentRep->getDashboardStickersStatsQuery('')->where('live_type', 'stickers')->count();

        return $results;
    }

    public function overviewPassbookSalesStats($requestData)
    {
        $results = [];
        $results['orders_count']= $this->passbookRep->getPassbookQuery($requestData)->count();
        $results['coins']       = $this->passbookRep->getPassbookQuery($requestData)->sum('total_coins');
        $results['prices']      = $this->passbookRep->getPassbookQuery($requestData)->sum('amount');
        $results['xp_earns']    = $this->passbookRep->getPassbookQuery($requestData)->sum('xp');
        return $results;
    }

}
