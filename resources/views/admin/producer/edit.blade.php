@extends('main')
@section('content') 
  


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


 <div class="box box-primary">
            <div class="box-header with-border">
              <h3 class="box-title">Fill Artist Form</h3>
            </div>
            <!-- /.box-header -->
            <!-- form start -->
            {!! Form::model($cmsuser, array('route' => array('admin.cmsusers.update', $cmsuser->_id), 'method' => 'PUT', 'role'=>'form', 'files'=>true)) !!}
              <div class="box-body">
              <div class="form-group">
                  <label for="exampleInputEmail1">First Name</label>
                   {!! Form::text('first_name', old('first_name'), array('class' => 'form-control', 'id'=> 'name', 'placeholder' => 'Enter first name' )) !!}

                </div>
                <div class="form-group">
                  <label for="exampleInputEmail1">Last Name </label>
                   {!! Form::text('last_name', old('last_name'), array('class' => 'form-control', 'id'=> 'name', 'placeholder' => 'Enter last name' )) !!}

                </div>
                <div class="form-group">
                  <label for="exampleInputEmail1">Profile Desc</label>
 
                  {!! Form::textarea('about_us', old('about_us'), array('class' => 'form-control', 'id'=> 'name', 'placeholder' => 'About Artist Desc' )) !!}


                </div>
                <div class="form-group">
                  <label for="exampleInputEmail1">DOB </label>
                   {!! Form::text('dob', old('dob'), array('class' => 'form-control', 'id'=> 'name', 'placeholder' => 'Enter DOB' )) !!}

                </div>

                <div class="form-group">
                  <label for="exampleInputEmail1">Email </label>
                   {!! Form::text('email', old('email'), array('class' => 'form-control', 'id'=> 'name', 'placeholder' => 'Enter email' )) !!}

                </div>
                <div class="form-group">
                  <label for="exampleInputEmail1">City </label>
                   {!! Form::text('city', old('city'), array('class' => 'form-control', 'id'=> 'name', 'placeholder' => 'Enter city' )) !!}

                </div>
                <div class="form-group">
                  <label for="exampleInputPassword1">Thank you message after live session end</label>
                   {!! Form::textarea('signature_msg', old('signature_msg'), array('class' => 'form-control', 'id'=> 'name', 'placeholder' => 'Thank you message after live session end' )) !!}

                </div>
                <div class="form-group">
                  <label for="exampleInputPassword1">Mobile</label>
                   {!! Form::text('mobile', old('mobile'), array('class' => 'form-control', 'id'=> 'name', 'placeholder' => 'Enter mobile' )) !!}

                </div>

                <div class="form-group">
                  <label for="exampleInputPassword1">Coins</label>
                   {!! Form::text('coins', old('coins'), array('class' => 'form-control', 'id'=> 'name', 'placeholder' => 'Enter session entry fee' )) !!}

                </div>
                <div class="form-group">
                  <label for="title">Status</label>
                  {!! Form::select('status', array('active' => 'Active', 'inactive' => 'Inactive'), old('status'), array('class' => 'form-control select2 ' , "ui-jp" => "select2", 'ui-options' => "{theme: 'bootstrap'}" )) !!}
               </div>

               <div class="form-group">
                  <label for="title">Platform</label>
                  {!! Form::select('platform[]', array('android' => 'Android', 'ios' => 'IOS'), old('platform'), array('multiple'=>true, 'class' => 'form-control select2 ' , "ui-jp" => "select2", 'ui-options' => "{theme: 'bootstrap'}" )) !!}
               </div>

               <div class="form-group">
                  <label for="title">Allow Packages</label>
                  {!! Form::select('allow_packages[]', array('gift' => 'Gifts', 'comment' => 'Comments'), old('allow_packages'), array('class' => 'form-control select2-multiple' ,"multiple" => "multiple" , "ui-jp" => "select2", 'ui-options' => "{theme: 'bootstrap'}" )) !!}

               </div>
             
             

               <div class="form-group">
                  <label for="title">Is featured?</label>
                  {!! Form::select('is_featured', array(true => 'Yes', false => 'No'), old('is_featured'), array('class' => 'form-control select2 ' , "ui-jp" => "select2", 'ui-options' => "{theme: 'bootstrap'}" )) !!}
               </div>



               <div class="form-group">
                  <label for="title">Is Beneficial?</label>
                  {!! Form::select('is_beneficial', array(true => 'Yes', false => 'No'), old('is_beneficial'), array('class' => 'form-control select2 ' , "ui-jp" => "select2", 'ui-options' => "{theme: 'bootstrap'}" )) !!}
               </div>

             
             
               
                <div class="form-group">
                  <label for="exampleInputFile">Artist Photo</label>
                  {!! Form::file('picture', old('picture'), array('class' => 'form-control', 'id'=> 'photo' )) !!}
                  <img style="height:100px; width:100px" src= <?php echo apply_cloudfront_url($cmsuser['picture']) ?> >
                 </div>
                 
              </div>
              <!-- /.box-body -->

              <div class="box-footer">
                <button type="submit" class="btn btn-primary">Submit</button>
              </div>
            </form>
          </div>
         

@endsection