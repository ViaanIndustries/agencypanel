<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Class Prefix
    |--------------------------------------------------------------------------
    |
    | This is the default prefix to prepend to the class name when creating
    | contract for the repository at the same time.
    |
    */
    'prefix'    => 'Db',

    /*
    |--------------------------------------------------------------------------
    | Default Class Suffix
    |--------------------------------------------------------------------------
    |
    | This is the default suffix to append to the class name of the
    | repository, for example: User would become UserRepository.
    |
    */
    'suffix'    => 'Repository',

    /*
    |--------------------------------------------------------------------------
    | Default Contracts Namespace
    |--------------------------------------------------------------------------
    |
    | This is the default namespace for the contracts of the repositories.
    |
    */
    'contract'  => 'Repositories\Contracts',

    /*
    |--------------------------------------------------------------------------
    | Default Namespace
    |--------------------------------------------------------------------------
    |
    | This is the default namespace after the application namespace for the
    | repositories classes, for example: App\[Repositories]\DbUserRepository.
    |
    */
    'namespace' => 'Repositories',

    /*
    |--------------------------------------------------------------------------
    | Repositories Bindings
    |--------------------------------------------------------------------------
    |
    | This is the array containing the bindings, contract to concrete class.
    |
    */
    'repositories' => [

        App\Repositories\Contracts\RoleInterface::class => App\Repositories\Mongo\RoleRepository::class,
          App\Repositories\Contracts\CmsuserInterface::class => App\Repositories\Mongo\CmsuserRepository::class,
          App\Repositories\Contracts\ArtistInterface::class => App\Repositories\Mongo\ArtistRepository::class,
        // App\Repositories\Contracts\BucketcodeInterface::class => App\Repositories\Mongo\BucketcodeRepository::class,

        // App\Repositories\Contracts\BucketInterface::class => App\Repositories\Mongo\BucketRepository::class,
        // App\Repositories\Contracts\CustomerDeviceInterface::class => App\Repositories\Mongo\CustomerDeviceRepository::class,

        // App\Repositories\Contracts\ContentInterface::class => App\Repositories\Mongo\ContentRepository::class,

        // App\Repositories\Contracts\GoliveInterface::class => App\Repositories\Mongo\GoliveRepository::class,
        // App\Repositories\Contracts\LivecommentInterface::class => App\Repositories\Mongo\LivecommentRepository::class,
        // App\Repositories\Contracts\CustomerInterface::class => App\Repositories\Mongo\CustomerRepository::class,
        // App\Repositories\Contracts\TemplateInterface::class => App\Repositories\Mongo\TemplateRepository::class,

        // App\Repositories\Contracts\CampaignInterface::class => App\Repositories\Mongo\CampaignRepository::class,
        // App\Repositories\Contracts\BadgeInterface::class => App\Repositories\Mongo\BadgeRepository::class,
        // App\Repositories\Contracts\BannerInterface::class => App\Repositories\Mongo\BannerRepository::class,
        // App\Repositories\Contracts\FanInterface::class => App\Repositories\Mongo\FanRepository::class,
        // App\Repositories\Contracts\PollInterface::class => App\Repositories\Mongo\PollRepository::class,
        // App\Repositories\Contracts\PolloptionInterface::class => App\Repositories\Mongo\PolloptionRepository::class,
        // App\Repositories\Contracts\ActivityInterface::class => App\Repositories\Mongo\ActivityRepository::class,

        // App\Repositories\Contracts\PackageInterface::class => App\Repositories\Mongo\PackageRepository::class,
        // App\Repositories\Contracts\GiftInterface::class => App\Repositories\Mongo\GiftRepository::class,
        // App\Repositories\Contracts\OrderInterface::class => App\Repositories\Mongo\OrderRepository::class,
        // App\Repositories\Contracts\PassbookInterface::class => App\Repositories\Mongo\PassbookRepository::class,
        // App\Repositories\Contracts\CustomerActivityInterface::class => App\Repositories\Mongo\CustomerActivityRepository::class,
        // App\Repositories\Contracts\CaptureInterface::class => App\Repositories\Mongo\CaptureRepository::class,
        // App\Repositories\Contracts\FeedbackInterface::class => App\Repositories\Mongo\FeedbackRepository::class,
        // App\Repositories\Contracts\PageInterface::class => App\Repositories\Mongo\PageRepository::class,
        // App\Repositories\Contracts\RewardInterface::class => App\Repositories\Mongo\RewardRepository::class,
        // App\Repositories\Contracts\PurchaseInterface::class => App\Repositories\Mongo\PurchaseRepository::class,
        // App\Repositories\Contracts\SettingInterface::class => App\Repositories\Mongo\SettingRepository::class,
        // App\Repositories\Contracts\AuctionproductInterface::class => App\Repositories\Mongo\AuctionproductRepository::class,

        // App\Repositories\Contracts\ContestantInterface::class => App\Repositories\Mongo\ContestantRepository::class,
        // App\Repositories\Contracts\CastInterface::class => App\Repositories\Mongo\CastRepository::class,
        // App\Repositories\Contracts\GenreInterface::class => App\Repositories\Mongo\GenreRepository::class,
          App\Repositories\Contracts\LanguageInterface::class => App\Repositories\Mongo\LanguageRepository::class,
        // App\Repositories\Contracts\LiveInterface::class => App\Repositories\Mongo\LiveRepository::class,
        // App\Repositories\Contracts\BucketlangInterface::class => App\Repositories\Mongo\BucketlangRepository::class,
        // App\Repositories\Contracts\RewardProgramInterface::class => App\Repositories\Mongo\RewardProgramRepository::class,
         App\Repositories\Contracts\AgencyInterface::class => App\Repositories\Mongo\AgencyRepository::class,

    ],

];
