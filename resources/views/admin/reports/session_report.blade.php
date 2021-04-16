@extends('main')
@section('content')
    <meta name="csrf-token" content="{{ Session::token() }}">
    <style>
        .card {
            position: relative;
            display: -ms-flexbox;
            display: flex;
            -ms-flex-direction: column;
            flex-direction: column;
            min-width: 0;
            word-wrap: break-word;
            background-color: #fff;
            background-clip: border-box;
            border: 0 solid rgba(0, 0, 0, .125);
            border-radius: .25rem;
            box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
            margin-bottom: 1rem !important;
        }

        .card-warning:not(.card-outline) > .card-header {
            background-color: #ffc107;
            border-bottom: 0;
        }

        .card-header {
            background-color: transparent;
            border-bottom: 1px solid rgba(0, 0, 0, .125);
            position: relative;
            padding: .75rem 1.25rem;
            border-top-left-radius: .25rem;
            border-top-right-radius: .25rem;
            padding: .75rem 1.25rem;
            margin-bottom: 0;
            background-color: rgba(0, 0, 0, .03);
            border-bottom: 0 solid rgba(0, 0, 0, .125);
        }

        .card-body {
            -ms-flex: 1 1 auto;
            flex: 1 1 auto;
            padding: 1.25rem;
        }

        .card-title {
            float: left;
            font-weight: 400;
            margin: 0;
        }

        .custom-button-width {
            width: 150px;
        }

        .custom-input-width {
            width: 150px;
            margin-right: 3px;
        }

        .box-warning:not(.box-outline) > .box-header {
            background-color: #ffc107;
            border-bottom: 0;
        }
    </style>



    @if (count($errors) > 0)
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if ($message = Session::get('message'))
        <div class="alert alert-success alert-block">
            <button type="button" class="close" data-dismiss="alert">×</button>
            <strong>{{ $message }}</strong>
        </div>
    @endif


    <div class="row">
        <div class="col-md-12">
            <!-- State data start here-->
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Filter Artist List</h3>
                         <div class="p-y text-right text-sm-right">
                            <a href="#" class="inline p-x text-center">
                                <span class="tooltiptext">Total Live Session</span>
                                <span class="h4 block m-a-0">@if(isset($lives) && $lives) {!! $lives->total() !!} @endif</span>

                            </a>
                         </div>
                    <div class="p-y text-right text-sm-right">

                    <a href="#" class="inline p-x text-center">
                               <span class="tooltiptext">Total Coins Earned</span>
                        <span class="h4 block m-a-0">@if(isset($coins)) {!! $coins !!} @endif</span>

                            </a>
                    </div>
                    <div class="p-y text-right text-sm-right">

                    <a href="#" class="inline p-x text-center">
                                 <span class="tooltiptext">Total Revenue</span>
                        <span class="h4 block m-a-0">@if(isset($total_earning_doller)) {!! $total_earning_doller !!} @endif</span>

                             </a>

                        </div>
                    </div>
                 <!-- /.box-header -->
                <!-- form start -->
                <div class="box-body">

                    {!! Form::open(array('url' => 'session-report', 'class'=>'', 'session-report'=>'form', 'files'=>true, 'method' => 'get') ) !!}

                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="title">Your Artist List</label>
                                <td>{!! Form::select('artist_id',array('' => 'All Artist') + $artists->toArray(), $appends_array['artist_id'], ['class'=>'form-control select2',"ui-jp" => "select2",'ui-options' => "{theme: 'bootstrap'}"]) !!}</td>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="title">Start Date</label>
                                <input type='text' name="created_at" class='form-control only_date'
                                       value="{!! $appends_array['created_at'] !!}" ui-jp="datetimepicker"
                                       ui-options="{
                                        format: 'DD/MM/YYYY',
                                        icons: {
                                          time: 'fa fa-clock-o',
                                          date: 'fa fa-calendar',
                                          up: 'fa fa-chevron-up',
                                          down: 'fa fa-chevron-down',
                                          previous: 'fa fa-chevron-left',
                                          next: 'fa fa-chevron-right',
                                          today: 'fa fa-screenshot',
                                          clear: 'fa fa-trash',
                                          close: 'fa fa-remove'
                                        }
                                      }"
                                /></div>
                        </div>

                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="title">End Date</label>
                                <input type='text' name="created_at_end" class='form-control only_date'
                                       value="{!! $appends_array['created_at_end'] !!}" ui-jp="datetimepicker"
                                       ui-options="{
                                        format: 'DD/MM/YYYY',
                                        icons: {
                                          time: 'fa fa-clock-o',
                                          date: 'fa fa-calendar',
                                          up: 'fa fa-chevron-up',
                                          down: 'fa fa-chevron-down',
                                          previous: 'fa fa-chevron-left',
                                          next: 'fa fa-chevron-right',
                                          today: 'fa fa-screenshot',
                                          clear: 'fa fa-trash',
                                          close: 'fa fa-remove'
                                        }
                                      }"
                                /></div>
                        </div>

                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="title">Sort Live</label>
                                <td>
                                    {!! Form::select('sort', array('' => 'All', 'coins' => 'Highest Coins Earned', 'views'=>'Highest Views','gifts'=>'Highest Gifts'), $appends_array['sort'], array('class' => 'form-control select2 ' , "ui-jp" => "select2", 'ui-options' => "{theme: 'bootstrap'}" )) !!}

                                </td>
                            </div>
                        </div>


                        <div class="col-md-2">
                            <td>

                                {!! Form::submit( 'Search', ['class' => 'btn btn-primary', 'name' => 'actionbutton', 'value' => 'search'])!!}
            {!! link_to('agency/session/report','Reset', array('class' => 'btn btn-warning ')) !!}

          {!! Form::submit( 'Export', ['class' => 'btn btn-danger', 'name' => 'actionbutton', 'value' => 'export'])!!}






                            </td>
                        </div>
                    </div>
                    <!-- /.box-body -->
                </div>
            </div>
            <!-- State data end here -->
        </div>

        <div id="listbox" class="col-md-12">
            <div class="card card-warning">
                <div class="card-header">
                    <h3 class="card-title">Live List</h3>
                </div>
                <!-- /.box-header -->
                <div class="card-body">
                    <div id="example2_wrapper" class="dataTables_wrapper form-inline dt-bootstrap">
                        <div class="row">
                            <div class="col-sm-6"></div>
                            <div class="col-sm-6"></div>
                        </div>
                        <div class="row">
                            <div class="col-sm-12" style="overflow-x:auto;">
                                <table id="usertable" class="table table-bordered table-hover dataTable" role="grid"
                                       aria-describedby="example2_info">
                                    <thead>
                                    <tr class="active-heading">
                                        <th>Artist Pic</th>
                                        <th>Basic Info</th>
                                        <th>Live Entry Fees</th>
                                        <th>Live  Start At</th>
                                        <th>Live End At</th>
                                         <th>Session Statistics</th>
                                        {{--                                        <th>Status</th>--}}
                                        {{--                                        <th>Started At</th>--}}
                                        {{--                                        <th>Ended At</th>--}}
                                        {{--                                        <th>Action</th>--}}
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse ($lives as $value)
                                    <?php
                                      

                                        ?>
                                        <tr>
                                            <td><img style="height:100px; width:100px"
                                                     src= <?php echo apply_cloudfront_url($value['artist']['picture']) ?> >
                                            </td>
                                            <td>
                                                <b> Name :</b></br>
                                                @if(isset($value['artist']['first_name'])) {!! $value['artist']['first_name'] !!} @endif
                                                @if(isset($value['artist']['last_name'])) {!! $value['artist']['last_name'] !!} @endif
                                                </br>
                                                <b> Email / Mobile :</b></br>
                                                @if(isset($value['artist']['email'])) {!! $value['artist']['email'] !!} @endif
                                                @if(isset($value['artist']['mobile'])) {!! $value['artist']['mobile'] !!} @endif
                                                </br>
                                                <b> DOB :</b></br>
                                                {!! $value['artist']['dob'] !!}</br>
                                                <b> City :</b></br>
                                                {!! $value['artist']['city'] !!}</br>
                                            </td>
                                            <td> {!! $value['artist']['coins'] !!}</br> </td>
                                            <td> {!! $value['start_at'] !!}</br> </td>
                                            <td> {!! $value['end_at'] !!}</br> </td>

                                            <td>
                                                <b> Views :</b></br>
                                                @if(isset($value['stats']['views'])) {!! $value['stats']['views'] !!} @endif
                                                </br>
                                                <b>  Gifts Count :</b></br>
                                                @if(isset($value['stats']['gifts'])) {!! $value['stats']['gifts'] !!} @endif
                                                </br>
                                                <b> Coins Earned  :</b></br>
                                                @if(isset($value['stats']['coin_spent'])) {!! $value['stats']['coin_spent'] !!} @endif
                                                </br>
                                                <b> Dollar Rate  :</b></br>
                                                @if(isset($value['doller_rate'])) {!! $value['doller_rate'] !!} @endif
                                                </br>
                                                <b> Total Earning :</b></br>
                                                @if(isset($value['total_earning_doller'])) {!! $value['total_earning_doller'] !!} @endif
                                                </br>
                                            </td>

                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center">There are no customers yet.</td>
                                        </tr>
                                    @endforelse
                                    </tbody>


                                </table>
                                <footer class="dker p-a">
                                    <div class="row" style="text-align: center !important;">
                                        <div class="col-md-2 text-center"></div>
                                        <div class="col-md-6 text-center"
                                             style="text-align: center !important;">{!!  $lives->appends(@$appends_array)->render() !!}</div>
                                        <div class="col-md-2 text-center"></div>
                                    </div>
                                </footer>
                            </div>
                            <!-- /.box-body -->
                        </div>
                    </div>
                </div>
            </div>
        </div>


@endsection