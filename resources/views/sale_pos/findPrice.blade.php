<!doctype html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" >

    <title>Find Product</title>
</head>
<body>
<div class="container">
    <!-- Content Header (Page header) -->
    <div class="row" style="margin-top: 80px">
        <div class="col-md-12 ">
            <div class="card">
                <div class="card-header">
                    <a href="{{url()->previous()}}" class="btn btn-primary">Back</a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8 offset-md-2 ">
                            <input type="text" placeholder="search product sku" class="form-control" id="product_find" autofocus>
                        </div>
                        <div class="col-md-8 offset-md-2 mt-2 ">
                            <div id="product_data">

                            </div>
                        </div>
                    </div>
                    <div class="row" style="margin-top: 20px">
                        <div class="col-md-8 offset-md-2 text-center ">
                            <a id="cancel" class="btn btn-primary text-white">Cancel</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /.content -->
</div>

<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>

<script src="https://code.jquery.com/jquery-3.6.0.js" integrity="sha256-H+K7U5CnXl1h5ywQfKtSj8PCmoN9aaq30gDh27Xc0jk=" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js" ></script>

<script>
    $( "#product_find" ).change(function() {
        const product = $(this).val();
        $.ajax({
            url:      "{{route('find_product')}}",
            type:     'get',
            dataType: 'json',
            data:     {
                'sku' : product
            },
            success: function(data) {
                $('#product_find').val('');
                if (data.error) {
                    toastr.error(data.message);
                } else {
                    $('#product_data').html(data.product)
                }
            },
        });
    });

    $(document).on("click","#cancel",function(e) {
        e.preventDefault();
        $('#product_data').html('');
    });

</script>
</body>
</html>