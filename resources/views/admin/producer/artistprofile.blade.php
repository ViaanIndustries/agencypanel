@extends('admin.layouts.master')

@section('css')
    <style>

    </style>
@stop
@section('content')

<div class="padding">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/producer">Back</a></li>
            <li class="breadcrumb-item active" aria-current="page"> Artist Name</li>
        </ol>
    </nav>

    <div class="artist-profile-details-container">
        <div class="box-body">
            <div class="row">
                <div class="col-sm-4"> 
                    <div class="box artist-profile-details-left">
                        <div class="widget-user">
                            <div class="widget-user-header green-bg">
                                    <div class="edit-icon"><a href="/producer/edit/{{$cmsuser['_id']}}"><img src="/admin/producer/images/edit-icon.svg" alt="user-icon"></a></div>

                                    
                                    </div> 
                                    <div class="box-profile">
                                        <div class="widget-user-image">
                                            <img class="profile-user-img img-responsive img-circle" src={{ apply_cloudfront_url($cmsuser['picture'])}} alt="User profile picture">
                                        </div>
                                        <div class="box-footer">
                                            <h3 class="profile-username text-center">{{$cmsuser['first_name']}} {{$cmsuser['last_name']}}</h3>
                                            <p class="text-muted text-center">{{$cmsuser['about_us']}}</p>
                                        </div>
                                    </div>
                            </div>

                            <div class="box-body profile-widget">
                                <div class="profile-part-one">
                                    <p><i class="fa fa-envelope"></i> <a href="">{{$cmsuser['email']}}</a> </p>

                                    <ul class="profile-d">
                                        <li><i class="fa fa-birthday-cake"></i> {{$cmsuser['dob']}}</li>
                                        <li><i class="fa fa-map-marker"></i>{{$cmsuser['city']}}</li>
                                    </ul>

                                </div>

                                <div class="box-divider mb-2"></div>

                                <div class="profile-part-two">
                                    <div class="form-group">
                                        <div class="input-group">
                                            <span class="input-group-addon"><i class="fa fa-user"></i></span>
                                            <input type="text" class="form-control" value="+91 {{$cmsuser['mobile']}}">
                                        </div>
                                    </div>
                                    <!-- <div class="form-group">
                                        <div class="input-group">
                                            <span class="input-group-addon"><i class="fa fa-lock"></i></span>
                                            <input type="password" class="form-control" name="password" id="password-field" value={{$cmsuser['password']}}>
                                            <span toggle="#password-field" class="fa fa-fw fa-eye field-icon toggle-password"></span> 

                                        </div>
                                    </div> -->
                                </div>

                                <div class="box-divider mb-2"></div>

                                <div class="profile-part-three">
                                    <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="text-muted">Coins per live session<sup>*<sup></label>
                                            <div class="input-group">
                                                <img src="/admin/producer/images/coin-icon.svg" alt="coin-icon"> &nbsp; {{$cmsuser['coins']}}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group platform-list">
                                                <label class="text-muted"> Allow Platforms<sup>*<sup></label>
                                                <div class="input-group">
                                                    @if(isset($cmsuser['platform']))
                                                    @if(in_array('android',$cmsuser['platform']))
                                                    <div class="btn-group">
                                                        <a href="" class="active-platform platform-icon"><i class="fa fa-android" aria-hidden="true"></i></a>
                                                    </div>
                                                    @elseif(in_array('ios',$cmsuser['platform']))
                                                    <div class="btn-group">
                                                        <a href="" class="active-platform platform-icon"><i class="fa fa-apple" aria-hidden="true"></i></a>
                                                    </div>
                                                    @else
                                                    <div class="btn-group">
                                                        <a href="" class="active-platform platform-icon"><i class="fa fa-chrome" aria-hidden="true"></i></a>
                                                    </div>
                                                    @endif
                                                    @endif
                                                </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="box-divider mb-2"></div>

                                <div class="profile-part-three">
                                    <label>Thank you message after live session end:</label>
                                    <p class="text-muted">{{$cmsuser['signature_msg']}}</p>
                                </div>


                                </div>

                                </div>
                            </div>  
                        </div>         
                
                        <div class="col-sm-8">
                            <div class="box artist-profile-details-right">
                                <div class="box-body">
                                {!! Form::open(array('url' => 'producer/artistprofile/', 'class'=>'', 'pages'=>'form', 'files'=>true, 'method' => 'get') ) !!}
                                    <div class="row">
                                        <div class="col-md-4 col-sm-4 align-self-center">
                                            <p class="text-muted "><strong>Live Overview (Till now so far)</strong></p>
                                        </div>
                                        <div class="col-md-8 col-sm-8">
                                                <div class="row border-bottom-red">
                                                    <div class="col-md-4 col-sm-4">
                                                        <div class="form-group">
                                                            <label>From</label>
                                                            <div class="input-group date">
                                                            <div class="input-group-addon">
                                                                <i class="fa fa-calendar"></i>
                                                            </div>
                                                            <!-- <input type="text" placeholder="DD/MM/YY" class="form-control pull-right" id="datepicker"> -->

                                                            {!! Form::text('startdate', Input::old('startdate'), array('class' => 'form-control', 'id'=> 'startdate', 'placeholder' => 'Enter start date', 'ui-jp' => "datetimepicker", 'ui-options' => "{format: '" . $date_format . "'}" )) !!}  


                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4 col-sm-4">
                                                        <div class="form-group">
                                                            <label>To</label>
                                                            <div class="input-group date">
                                                            <div class="input-group-addon">
                                                                <i class="fa fa-calendar"></i>
                                                            </div>
                                                            {!! Form::text('enddate', Input::old('enddate'), array('class' => 'form-control', 'id'=> 'enddate', 'placeholder' => 'Enter end date', 'ui-jp' => "datetimepicker", 'ui-options' => "{format: '" . $date_format . "'}" )) !!}  


                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-3 col-sm-3 align-self-center">

                                                    
                                                    {!! Form::hidden('artist_id', $cmsuser['_id']) !!}
                                                    

                                                    {!! Form::submit('Apply', array('class' => 'btn btn-lg btn-block success ')) !!}
                                                    </div>
                                                    
                                                </div>
                                        </div>
                                        
                                    </div>
                                    {!! Form::close() !!}
                                    <div class="row pt-4">
                                        <div class="col-md-6 col-sm-6">
                                            <div class="box-body artist-profile-details-blocks block-gray">
                                                <div class="info-box">
                                                    <div class="info-box-head">
                                                        <span class="info-box-icon"> <img src="/admin/producer/images/lo-livesession-icon.svg" alt="icon"></span>
                                                        <span class="info-box-text">Live Sessions</span>
                                                    </div>
                                                    <div class="info-box-content">
                                                        <span class="info-box-number">1,410</span>
                                                        <span class="info-box-subtext">&nbsp;</span>
                                                    </div>
                                                </div>
                                                <div class="stats-footer">
                                                    <ul class="stats-details">
                                                        <li><span class="stats-android"><i class="fa fa-android" aria-hidden="true"></i> 12.78K</span></li>
                                                        <li><span class="stats-apple"><i class="fa fa-apple" aria-hidden="true"></i> 23.56K</span></li>
                                                        <li><span class="stats-chrome"><i class="fa fa-chrome" aria-hidden="true"></i> 10.67K</span></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-sm-6">
                                            <div class="box-body artist-profile-details-blocks block-green">
                                                <div class="info-box">
                                                    <div class="info-box-head">
                                                        <span class="info-box-icon"> <img src="/admin/producer/images/lo-revenue-icon.svg" alt="icon"></span>
                                                        <span class="info-box-text">Revenue</span>
                                                    </div>
                                                    <div class="info-box-content">
                                                        <span class="info-box-number"><img src="/admin/producer/images/coin-icon.svg" alt="icon"> 20.25m</span>
                                                        <span class="info-box-subtext">₹ 40,00,000.67</span>
                                                    </div>
                                                </div>
                                                <div class="stats-footer">
                                                    <ul class="stats-details">
                                                        <li><span class="stats-android"><i class="fa fa-android" aria-hidden="true"></i> <img src="/admin/producer/images/coin-icon.svg" alt="icon"> 12.78K</span></li>
                                                        <li><span class="stats-apple"><i class="fa fa-apple" aria-hidden="true"></i> <img src="/admin/producer/images/coin-icon.svg" alt="icon"> 23.56K</span></li>
                                                        <li><span class="stats-chrome"><i class="fa fa-chrome" aria-hidden="true"></i> <img src="/admin/producer/images/coin-icon.svg" alt="icon"> 10.67K</span></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-sm-6">
                                            <div class="box-body artist-profile-details-blocks block-blue">
                                                <div class="info-box">
                                                    <div class="info-box-head">
                                                        <span class="info-box-icon"> <img src="/admin/producer/images/lo-followers-icon.svg" alt="icon"></span>
                                                        <span class="info-box-text">Followers</span>
                                                    </div>
                                                    <div class="info-box-content">
                                                        <span class="info-box-number">+2,672</span>
                                                        <span class="info-box-subtext">239 Unfollow</span>
                                                    </div>
                                                </div>
                                                <div class="stats-footer">
                                                    <ul class="stats-details">
                                                        <li><span class="stats-android"><i class="fa fa-android" aria-hidden="true"></i> 12.78K</span></li>
                                                        <li><span class="stats-apple"><i class="fa fa-apple" aria-hidden="true"></i>  23.56K</span></li>
                                                        <li><span class="stats-chrome"><i class="fa fa-chrome" aria-hidden="true"></i> 10.67K</span></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-sm-6">
                                            <div class="box-body artist-profile-details-blocks block-red">
                                                <div class="info-box">
                                                    <div class="info-box-head">
                                                        <span class="info-box-icon"> <img src="/admin/producer/images/lo-reports-icon.svg" alt="icon"></span>
                                                        <span class="info-box-text">Reports</span>
                                                    </div>
                                                    <div class="info-box-content">
                                                        <span class="info-box-number">1,726</span>
                                                        <span class="info-box-subtext">&nbsp;</span>
                                                    </div>
                                                </div>
                                                <div class="stats-footer">
                                                    <ul class="stats-details">
                                                        <li><span class="stats-android"><i class="fa fa-android" aria-hidden="true"></i> 12.78K</span></li>
                                                        <li><span class="stats-apple"><i class="fa fa-apple" aria-hidden="true"></i> 23.56K</span></li>
                                                        <li><span class="stats-chrome"><i class="fa fa-chrome" aria-hidden="true"></i> 10.67K</span></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>          
                        </div>
            </div>
        </div>
    </div>       
</div>

@stop