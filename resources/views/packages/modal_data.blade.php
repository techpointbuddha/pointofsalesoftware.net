@foreach($packages as $package)
    <div class="row">
       @php
           $products = \App\PackageProducts::where('package_id',$package->id)->get();
       @endphp
        <div class="col-md-12">
            <form method="post" action="{{route('updatePackageRows',$package)}}">
                @csrf
                <h5>{{ucfirst($package->name)}}</h5>
                <table class="table table-condensed table-bordered table-striped table-responsive" id="pos_table">
                    <thead>
                    <tr>
                        <th class="text-center">
                            @lang('sale.product')
                        </th>
                        <th class="text-center">
                            @lang('sale.qty')
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    @php
                        $total = 0
                    @endphp
                    @foreach($products as $product)
                        <tr>
                            <td class="text-center">
                                @php
                                    $business_id = request()->session()->get('user.business_id');
                                      $pr = \App\Product::where('id',$product->product_id)->first();
                                      $product_name = $pr->name;
                                          $query = \App\Variation::join('products AS p', 'variations.product_id', '=', 'p.id')
                                              ->join('product_variations AS pv', 'variations.product_variation_id', '=', 'pv.id')
                                              ->leftjoin('variation_location_details AS vld', 'variations.id', '=', 'vld.variation_id')
                                              ->leftjoin('units', 'p.unit_id', '=', 'units.id')
                                              ->leftjoin('units as u', 'p.secondary_unit_id', '=', 'u.id')
                                              ->leftjoin('brands', function ($join) {
                                                  $join->on('p.brand_id', '=', 'brands.id')
                                                      ->whereNull('brands.deleted_at');
                                              })
                                              ->where('p.business_id', $business_id)
                                              ->where('variations.id', $product->variation_id);

                                             $price = $query->select(
                                                        'variations.sell_price_inc_tax'
                                                    )
                                                   ->firstOrFail();
                                             $total += $price->sell_price_inc_tax * $product->quantity;
                                @endphp
                                {{$product_name}}
                                <input type="hidden" name="variation[]" value="{{$product->variation_id}}">
                                <input type="hidden" name="product[]" value="{{$product->product_id}}">
                            </td>
                            <td>
                                <input type="number" name="quantity[]" class="form-control quantity" value="{{$product->remaining_quantity}}" max="{{$product->remaining_quantity}}">
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <button type="submit" class="btn btn-primary">Update</button>
            </form>
        </div>
    </div>
@endforeach

<script>
    $(".quantity").change(function(){
        const val = $(this).val();
        const max = $(this).attr('max');
        if(parseInt(val)  > parseInt(max) ){
            $(this).val(max)
        }
    });
</script>