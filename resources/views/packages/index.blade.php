@extends('layouts.app')
@section('title', 'Packages')

@section('content')

    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1>Packages
            <small>Manage your customers from here</small>
        </h1>
    </section>

    <!-- Main content -->
    <section class="content">
        @component('components.widget', ['class' => 'box-primary', 'title' => 'All Packages'])
            @slot('tool')
                <div class="box-tools">
                    <a href="{{route('packageCreate')}}" class="btn btn-block btn-primary">
                        <i class="fa fa-plus"></i>
                        @lang( 'messages.add' )
                    </a>
                </div>
            @endslot
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="packages_table">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Customer</th>
                        <th>Products in Package</th>
                        <th>Remaining Products</th>
                        <th>@lang( 'messages.action' )</th>
                    </tr>
                    </thead>
                </table>
            </div>
        @endcomponent

    </section>
    <!-- /.content -->

@endsection

@section('javascript')
<script>
    var packages_table = $('#packages_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '/packages',
        columnDefs: [
            {
                targets: 3,
                orderable: false,
                searchable: false,
            },
        ],
        columns: [
            { data: 'name', name: 'name'},
            { data: 'customer_name', name: 'customer.name'},
            { data: 'total_products', name: 'total_products'},
            { data: 'remaining_products', name: 'remaining_products'},
            { data: 'action', name: 'action'},
        ],
    });
</script>
@endsection
