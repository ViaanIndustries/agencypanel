@extends('dashboard')
@section('content')

<div class="row">
   <div class="col-md-6">
      <!-- State data start here-->
      <div class="box box-primary">
         <div class="box-header with-border">
            <h3 class="box-title">Banner Data</h3>
         </div>
         <!-- /.box-header -->
         <!-- form start -->
         <form role="form">
            <div class="box-body">
               <div class="form-group">
                  <label for="title">Title </label>
                  <input type="text" class="form-control" id="title" >
               </div>
               <div class="form-group">
                  <label for="desc">Desc</label>
                  <input type="text" class="form-control" id="desc"  >
               </div>
            <div class="form-group">
                  <label for="banner">Banner Images</label>
                  <input type="file" id="banner">

                 </div>   
            </div>
            <!-- /.box-body -->
            <div class="box-footer">
               <button type="submit" class="btn btn-primary">Submit</button>
            </div>
         </form>
      </div>
          </div>
      <!-- State data end here -->
        </div>
@endsection
