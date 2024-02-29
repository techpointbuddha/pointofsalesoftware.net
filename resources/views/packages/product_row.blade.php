<tr>
    <td class="text-center">
        {{$product->product_name}}
        <input type="hidden" name="variation[]" value="{{$product->variation_id}}">
        <input type="hidden" name="product[]" value="{{$product->product_id}}">
    </td>
    <td>
        <input type="number" name="quantity[]" class="form-control quantity" value="1" >
    </td>
    <td class="text-center">
        <span >{{$product->sell_price_inc_tax}}</span>
        <input type="hidden"  class="form-control product_price" value="{{$product->sell_price_inc_tax}}" >
    </td>
    <td class="text-center v-center">
        <i class="fa fa-times text-danger pos_remove_row cursor-pointer" aria-hidden="true"></i>
    </td>
</tr>