<div class="main-sidebar">
        <!-- Inner sidebar -->
        <div class="sidebar">
          <!-- user panel (Optional) -->
          <div class="user-panel">
            <div class="pull-left image">
<img src="{{ asset("/bower_components/admin-lte/dist/img/user2-160x160.jpg") }}" class="img-circle" alt="User Image" />

            </div>
            <div class="pull-left info">
              <p>{{Session::get('user_first_name')}}</p>

              <a href="#"><i class="fa fa-circle text-success"></i> Online</a>
            </div>
          </div><!-- /.user-panel -->

          <!-- Search Form (Optional) -->
          <form action="#" method="get" class="sidebar-form">
            <div class="input-group">
              <input type="text" name="q" class="form-control" placeholder="Search...">
              <span class="input-group-btn">
                <button type="submit" name="search" id="search-btn" class="btn btn-flat"><i class="fa fa-search"></i></button>
              </span>
            </div>
          </form><!-- /.sidebar-form -->

          <!-- Sidebar Menu -->
	  <ul class="nav sidebar-menu" data-widget="tree">

            <li id="a" class="active" ><a href="{{url('dashboard')}}"  ><i class="fa fa-dashboard"></i> <span>Dashboard</span>
            <span class="pull-right-container">

            </span>
	 	</a>
	</li>

 
  <li id="a" class="active" ><a href="{{url('producer')}}"  ><i class="fa fa-circle-o"></i> <span>Artist</span>
            <span class="pull-right-container">

            </span>
	 	</a>
	</li>

      	
	
       <li class="treeview">
          <a href="#">
            <i class="fa fa-files-o"></i>
            <span>Reports</span>
            <span class="pull-right-container">
              <span class="label label-primary pull-right">1</span>
            </span>
          </a>
          <ul class="treeview-menu">
          <li><a href="{{route('admin.report.session')}}" ><i class="fa fa-circle-o"></i> Live Session Report </a></li>
             </ul>
        </li>
        <li>
        
        </ul><!-- /.sidebar-menu -->

        </div><!-- /.sidebar -->
      </div><!-- /.main-sidebar -->
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
<script>
 

$(function() {
  $('.nav a[href^="/' + location.pathname.split("/")[1] + '"]').addClass('active');

  
});

</script>
