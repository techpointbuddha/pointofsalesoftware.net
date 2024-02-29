@extends('layouts.app')
@section('title', 'Reset Database')

@section('content')

    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1>Reset Database</h1>
        <!-- <ol class="breadcrumb">
            <li><a href="#"><i class="fa fa-dashboard"></i> Level</a></li>
            <li class="active">Here</li>
        </ol> -->
    </section>

    <!-- Main content -->
    
<section class="content">
    <form method="post" action="{{route('reset_database_delete')}}" id="reset_password_form">
        @csrf
        <div class="row">
            <div class="col-md-12 text-center">
               <div class="col-md-6" id="location_filter">
                    <div class="form-group">
                      {!! Form::label('location_id',  __('purchase.business_location') . ':') !!}
                     {!! Form::select('location_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]); !!}
                   </div>
                </div>
                <div class="col-md-6" style="margin-top: 22px">
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary reset_database" >Reset </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="modal fade in" id="verify_password_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel" >
        <div class="modal-dialog " role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close no-print" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                    <h4 class="modal-title" id="modalTitle">Verify Your Identity</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input class="form-control" type="password" name="password" id="password" placeholder="password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary verify_password" >Verify</button>
                </div>
            </div>
        </div>
    </div>
    
</section>




@endsection
@section('javascript')
    <script>
        $(document).ready(function() {
            $(".reset_database").click(function(e){
                e.preventDefault();
                $('#verify_password_modal').modal('show');
            });
            $(".verify_password").click(function(e){
                e.preventDefault();
               const password =  $('#password').val()
                if(password){
                    if(password === "12345678"){
                        $('#reset_password_form').submit()
                    }else{
                        alert("Please enter valid password");
                    }
                }else{
                    alert("Please enter password");
                }
            })
        });
    </script>
@endsection
