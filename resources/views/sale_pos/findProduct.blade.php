{{--<div class="col-md-8 offset-md-2 ">--}}
    <div class="row">
        <div class="col-md-6 ">
            @if($find_product->image)
              <img src="  {{asset('uploads/img/'.$find_product->image)}}" class="thumbnail" width="70%">
            @else
                <h2>
                    {{$find_product->product_name}}
                </h2>
            @endif
        </div>
        <div class="col-md-6 ">
            <h2>
                Product Price : {{number_format($find_product->sell_price_inc_tax,2)}}
            </h2>
        </div>
    </div>
{{--</div>--}}