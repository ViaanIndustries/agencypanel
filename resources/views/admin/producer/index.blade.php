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
   border: 0 solid rgba(0,0,0,.125);
   border-radius: .25rem;
   box-shadow: 0 0 1px rgba(0,0,0,.125), 0 1px 3px rgba(0,0,0,.2);
   margin-bottom: 1rem!important;
   }
   .card-warning:not(.card-outline)>.card-header {
   background-color: #ffc107;
   border-bottom: 0;
   }
   .card-header {
   background-color: transparent;
   border-bottom: 1px solid rgba(0,0,0,.125);
   position: relative;
   padding: .75rem 1.25rem;
   border-top-left-radius: .25rem;
   border-top-right-radius: .25rem;
   padding: .75rem 1.25rem;
   margin-bottom: 0;
   background-color: rgba(0,0,0,.03);
   border-bottom: 0 solid rgba(0,0,0,.125);
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
   width:150px;
   }
   .custom-input-width {
   width:150px;
   margin-right:3px;
   }
   .box-warning:not(.box-outline)>.box-header {
   background-color: #ffc107;
   border-bottom: 0;
   }
</style>



@if (count($errors) > 0)
   <div class = "alert alert-danger">
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
      </div>
      <!-- /.box-header -->
      <!-- form start -->
      <div class="box-body">

      {!! Form::open(array('url' => 'producer', 'class'=>'', 'cmsuser'=>'form', 'files'=>true, 'method' => 'get') ) !!}

         <div class="row">
            <div class="col-md-2">
               <div class="form-group">
                  <label for="title">Name / Mobile/ Email</label>
                  <input class="form-control" id="" placeholder="Artist by name" name="name" type="text" value="">
               </div>
            </div>
            <div class="col-md-2">
               <div class="form-group">
                  <label for="title">Status</label>
                  {!! Form::select('status', array('' => 'Select Status', 'active' => 'Active', 'inactive' => 'Inactive'), $appends_array['status'], array('class' => 'form-control select2 ' , "ui-jp" => "select2", 'ui-options' => "{theme: 'bootstrap'}" )) !!}
               </div>
            </div>
            <div class="col-md-2">
               <div class="form-group">
                  <label for="title">Sort Artist</label>
                  {!! Form::select('sort', array('' => 'Select Sort', 'coins' => 'Highest Coins', 'name'=>'Sory By Name','followers'=>'Highest Followers'), $appends_array['sort'], array('class' => 'form-control select2 ' , "ui-jp" => "select2", 'ui-options' => "{theme: 'bootstrap'}" )) !!}
               </div>
            </div>
            <div class="col-md-2">
            <td>

            {!! Form::submit( 'Search', ['class' => 'btn btn-primary', 'name' => 'actionbutton', 'value' => 'search'])!!}
            {!! link_to('producer','Reset', array('class' => 'btn btn-warning ')) !!}

            {!! link_to('/producer/create','Create Artist', array('class' => 'btn btn-warning ')) !!}
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
         <h3 class="card-title">Artist List</h3>
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
                  <table id="usertable" class="table table-bordered table-hover dataTable" role="grid" aria-describedby="example2_info">
                     <thead>
                        <tr class="active-heading">
                           <th>Artist Pic</th>
                           <th>Basic Info</th>
                           <th>Statistics</th>
                           <th>Status</th>
                           <th>Created At</th>

                           <th>Action</th>
                        </tr>
                     </thead>
                     <tbody>
                        @forelse ($artists as $value)
                        <tr>
                           <td> <img style="height:100px; width:100px" src= <?php echo apply_cloudfront_url($value['picture']) ?> > </td>
                           <td>
                              <b> Name :</b></br>
                              @if(isset($value['first_name'])) {!! $value['first_name'] !!} @endif
                              @if(isset($value['last_name'])) {!! $value['last_name'] !!} @endif
                              </br>
                              <b> Email / Mobile :</b></br>
                              @if(isset($value['email'])) {!! $value['email'] !!} @endif
                              @if(isset($value['mobile'])) {!! $value['mobile'] !!} @endif
                              </br>
                              <b> DOB :</b></br>
                              {!! $value->dob !!}</br>
                              <b> City :</b></br>
                              {!! $value->city !!}</br>
                           </td>
                           <td>
                              <b> Coins :</b></br>
                              @if(isset($value['stats']['coins'])) {!! $value['stats']['coins'] !!} @endif
                               </br>
                              <b>  Followers :</b></br>
                              @if(isset($value['stats']['followers'])) {!! $value['stats']['followers'] !!} @endif
                               </br>
                              <b> Sessions :</b></br>
                              @if(isset($value['stats']['sessions'])) {!! $value['stats']['sessions'] !!} @endif
                              
                           </td>
                           <td>  {!! $value->status !!}</br>
                           <td>  {!! $value->created_at !!}</br>

                           </td>
                           <td class="align-middle">
                              <a class="btn btn-block btn-primary" href="producer/{{$value->id}}/edit"  >Edit</a>
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
                           style="text-align: center !important;">{!!  $artists->appends(@$appends_array)->render() !!}</div>
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