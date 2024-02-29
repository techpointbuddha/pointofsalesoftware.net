@extends('layouts.app')
@section('title', 'Package Update')

@section('content')

    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1>Package Update</h1>
    </section>

    <!-- Main content -->
    <section class="content">
        <form method="post" action="{{route('packageUpdate',$package)}}">
            @csrf
            <div class="box box-primary">
                <div class="box-body">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="name">Name</label>
                                <input type="text" name="name" class="form-control" id="name" placeholder="package name" value="{{$package->name}}" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                {!! Form::label('customer_id', __('contact.customer') . ':') !!}
                                <div class="input-group">
                            <span class="input-group-addon">
                                <i class="fa fa-user"></i>
                            </span>
                                    {!! Form::select('customer_id', $customers,$package->customer_id, ['class' => 'form-control select2','placeholder' => 'select customer', 'required']); !!}
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                {!! Form::label('sale_product','Sale Product') !!}
                                {!! Form::select('sale_product', $package_products,$package->sale_product, ['class' => 'form-control select2', 'required','Placeholder' => 'Select Product']); !!}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @component('components.widget', ['class' => 'box-solid'])
                <div class="col-sm-10 col-sm-offset-1">
                    <div class="form-group">
                        {!! Form::text('search_product', null, ['class' => 'form-control mousetrap', 'id' => 'search_product', 'placeholder' => __('lang_v1.search_product_placeholder'),
                        'autofocus' =>  true,
                        ]); !!}
                    </div>

                    <div class="table-responsive" style="margin-top: 10px">
                        <table class="table table-condensed table-bordered table-striped table-responsive" id="pos_table">
                            <thead>
                            <tr>
                                <th class="text-center">
                                    @lang('sale.product')
                                </th>
                                <th class="text-center">
                                    @lang('sale.qty')
                                </th>
                                <th class="text-center">
                                    Price
                                </th>
                                <th class="text-center"><i class="fas fa-times" aria-hidden="true"></i></th>
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
                                    <td class="text-center">
                                        <span >{{$price->sell_price_inc_tax}}</span>
                                        <input type="hidden"  class="form-control product_price" value="{{$price->sell_price_inc_tax}}" >
                                    </td>
                                    <td class="text-center v-center">
                                        <i class="fa fa-times text-danger pos_remove_row cursor-pointer" aria-hidden="true"></i>
                                    </td>
                                </tr>
                            @endforeach

                            </tbody>
                            <tfoot>
                            <tr class="">
                                <td colspan="2"></td>
                                <td colspan="2">
                                    Total : <span class="total_amount">{{number_format($total,2)}}</span>
                                </td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>

                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            @endcomponent

        </form>

    </section>
    <!-- /.content -->

@endsection
@section('javascript')
    <script>
        @if($message = Session::get("success"))
        toastr.success('{{ $message }}');
        @endif
        @if($message = Session::get("danger"))
        toastr.error('{{ $message }}');
        @endif

    </script>
    <script>
        if ($('#search_product').length) {
            //Add Product
            $('#search_product')
                .autocomplete({
                    delay: 1000,
                    source: function(request, response) {
                        var price_group = '';
                        if ($('#price_group').length > 0) {
                            price_group = $('#price_group').val();
                        }
                        $.getJSON(
                            '/products/list',
                            {
                                price_group: price_group,
                                term: request.term,
                                not_for_selling: 0,
                            },
                            response
                        );
                    },
                    minLength: 2,
                    response: function(event, ui) {
                        if (ui.content.length == 1) {
                            ui.item = ui.content[0];

                            var is_overselling_allowed = false;
                            if($('input#is_overselling_allowed').length) {
                                is_overselling_allowed = true;
                            }
                            var for_so = false;
                            if ($('#sale_type').length && $('#sale_type').val() == 'sales_order') {
                                for_so = true;
                            }

                            if ((ui.item.enable_stock == 1 && ui.item.qty_available > 0) ||
                                (ui.item.enable_stock == 0) || is_overselling_allowed || ui.item.qty_available >= 0 || for_so) {
                                $(this)
                                    .data('ui-autocomplete')
                                    ._trigger('select', 'autocompleteselect', ui);
                                $(this).autocomplete('close');
                            }
                        } else if (ui.content.length == 0) {
                            toastr.error(LANG.no_products_found);
                            $('input#search_product').select();
                        }
                    },
                    focus: function(event, ui) {
                        if (ui.item.qty_available <= 0) {
                            return false;
                        }
                    },
                    select: function(event, ui) {
                        var searched_term = $(this).val();
                        var is_overselling_allowed = false;
                        if($('input#is_overselling_allowed').length) {
                            is_overselling_allowed = true;
                        }
                        var for_so = false;
                        if ($('#sale_type').length && $('#sale_type').val() == 'sales_order') {
                            for_so = true;
                        }

                        if (ui.item.enable_stock != 1 || ui.item.qty_available > 0 || is_overselling_allowed || for_so) {
                            $(this).val(null);
                            pos_product_row(ui.item.variation_id);
                        } else {
                            alert(LANG.out_of_stock);
                        }
                    },
                })
                .autocomplete('instance')._renderItem = function(ul, item) {
                var is_overselling_allowed = false;
                if($('input#is_overselling_allowed').length) {
                    is_overselling_allowed = true;
                }

                var for_so = false;
                if ($('#sale_type').length && $('#sale_type').val() == 'sales_order') {
                    for_so = true;
                }

                if (item.enable_stock == 1 && item.qty_available <= 0 && !is_overselling_allowed && !for_so) {
                    var string = '<li class="ui-state-disabled">' + item.name;
                    if (item.type == 'variable') {
                        string += '-' + item.variation;
                    }
                    var selling_price = item.selling_price;
                    if (item.variation_group_price) {
                        selling_price = item.variation_group_price;
                    }
                    string +=
                        ' (' +
                        item.sub_sku +
                        ')' +
                        '<br> Price: ' +
                        selling_price +
                        ' (Out of stock) </li>';
                    return $(string).appendTo(ul);
                } else {
                    var string = '<div>' + item.name;
                    if (item.type == 'variable') {
                        string += '-' + item.variation;
                    }

                    var selling_price = item.selling_price;
                    if (item.variation_group_price) {
                        selling_price = item.variation_group_price;
                    }

                    string += ' (' + item.sub_sku + ')' + '<br> Price: ' + selling_price;
                    if (item.enable_stock == 1) {
                        var qty_available = __currency_trans_from_en(item.qty_available, false, false, __currency_precision, true);
                        string += ' - ' + qty_available + item.unit;
                    }
                    string += '</div>';

                    return $('<li>')
                        .append(string)
                        .appendTo(ul);
                }
            };
        }

        //variation_id is null when weighing_scale_barcode is used.
        function pos_product_row(variation_id,quantity) {
            $.ajax({
                method: 'GET',
                url: '/get-package-product/' + variation_id + '',
                async: false,
                dataType: 'json',
                success: function(result) {
                    if (result.success) {
                        $('table#pos_table tbody')
                            .append(result.html_content)
                            .find('input.pos_quantity');

                        $('input#search_product')
                            .focus()
                            .select();

                        //scroll bottom of items list
                        $(".pos_product_div").animate({ scrollTop: $('.pos_product_div').prop("scrollHeight")}, 1000);
                    } else {
                        toastr.error(result.msg);
                        $('input#search_product')
                            .focus()
                            .select();
                    }
                    calculate_total()
                },
            });
        }

        $('table#pos_table tbody').on('click', 'i.pos_remove_row', function() {
            $(this)
                .parents('tr')
                .remove();
            calculate_total();
        });

        $(".quantity").change(function(){
            const val = $(this).val();
            const max = $(this).attr('max');
            if(parseInt(val)  > parseInt(max) ){
                $(this).val(max)
            }
        });

        function calculate_total() {
            var total = 0;
            $('table#pos_table tbody tr').each(function() {
                const quantity =  __read_number($(this).find('input.quantity'));
                console.log(quantity);
                const price =  __read_number($(this).find('input.product_price'));
                total += quantity * price;

            });
            $('span.total_amount').html( __number_f(total));
        }

    </script>
@endsection
