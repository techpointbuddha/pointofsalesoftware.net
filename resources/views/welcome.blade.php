@extends('layouts.home')
@section('title', config('app.name', 'Online Office POS'))

@section('content')
    <style type="text/css">
        .flex-center {
                align-items: center;
                display: flex;
                justify-content: center;
                margin-top: 10%;
            }
        .title {
                font-size: 84px;
            }
        .tagline {
                font-size:25px;
                font-weight: 300;
                text-align: center;
            }

        @media only screen and (max-width: 600px) {
            .title{
                font-size: 38px;
            }
            .tagline {
                font-size:18px;
            }
        }
    </style>
   <center> <img src="uploads/logo.png" alt="Look POS" width="400" height="180"></center>
   <!-- <div class="title flex-center" style="font-weight: 600 !important;">
        {{ config('app.name', 'Online Office POS') }}
  
  </div> -->
	
    <p class="tagline">
        {{ env('APP_TITLE', '') }}
    </p>
    
	
@endsection
            