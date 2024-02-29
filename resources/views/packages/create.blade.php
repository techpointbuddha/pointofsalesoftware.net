@extends('layouts.app')
@section('title', 'Package Create')

@section('content')

    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1>Package Create</h1>
    </section>

    <!-- Main content -->
    <section class="content">
        <form method="post" action="{{route('packageSave')}}">
            @csrf
            <div class="box box-primary">
                <div class="box-body">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="name">Name</label>
                                <input type="text" name="name" class="form-control" id="name" placeholder="package name" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                {!! Form::label('customer_id', __('contact.customer') . ':') !!}
                                <div class="input-group">
                            <span class="input-group-addon">
                                <i class="fa fa-user"></i>
                            </span>
                                    @php
                                        $default_customer = null;
                                        if(count($customers) > 1){
                                          $default_customer = array_key_first($customers->toArray());
                                        }
                                    @endphp
                                    {!! Form::select('customer_id[]', $customers, $default_customer, ['class' => 'form-control select2','multiple', 'required']); !!}
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                {!! Form::label('sale_product','Sale Product') !!}
                                {!! Form::select('sale_product', $products,false, ['class' => 'form-control select2', 'required','Placeholder' => 'Select Product']); !!}
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
                            <tbody></tbody>
                            <tfoot>
                               <tr class="">
                                   <td colspan="2"></td>
                                   <td colspan="2">
                                       Total : <span class="total_amount">0</span>
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

        $(document).on('change', '.quantity', function() {
            calculate_total();
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
