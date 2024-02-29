<?php

namespace App\Http\Controllers;

use App\Brands;
use App\BusinessLocation;

use App\Category;
use App\Charts\CommonChart;
use App\Contact;
use App\Currency;
use App\CustomPackage;
use App\PackageProducts;
use App\Product;
use App\PurchaseLine;
use App\Transaction;
use App\TransactionSellLine;
use App\TransactionSellLinesPurchaseLines;
use App\Utils\BusinessUtil;

use App\Utils\ModuleUtil;
use App\Utils\TransactionUtil;
use App\Variation;
use App\VariationLocationDetails;
use Illuminate\Http\Request;
use App\Utils\Util;
use App\Utils\RestaurantUtil;
use App\User;
use Illuminate\Notifications\DatabaseNotification;
use App\Media;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class HomeController extends Controller
{
    /**
     * All Utils instance.
     *
     */
    protected $businessUtil;
    protected $transactionUtil;
    protected $moduleUtil;
    protected $commonUtil;
    protected $restUtil;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        BusinessUtil $businessUtil,
        TransactionUtil $transactionUtil,
        ModuleUtil $moduleUtil,
        Util $commonUtil,
        RestaurantUtil $restUtil
    ) {
        $this->businessUtil = $businessUtil;
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
        $this->commonUtil = $commonUtil;
        $this->restUtil = $restUtil;
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        $is_admin = $this->businessUtil->is_admin(auth()->user());

        if (!auth()->user()->can('dashboard.data')) {
            return view('home.index');
        }

        $fy = $this->businessUtil->getCurrentFinancialYear($business_id);

        $currency = Currency::where('id', request()->session()->get('business.currency_id'))->first();
        //ensure start date starts from at least 30 days before to get sells last 30 days
        $least_30_days = \Carbon::parse($fy['start'])->subDays(30)->format('Y-m-d');

        //get all sells
        $sells_this_fy = $this->transactionUtil->getSellsCurrentFy($business_id, $least_30_days, $fy['end']);

        $all_locations = BusinessLocation::forDropdown($business_id)->toArray();

        //Chart for sells last 30 days
        $labels = [];
        $all_sell_values = [];
        $dates = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = \Carbon::now()->subDays($i)->format('Y-m-d');
            $dates[] = $date;

            $labels[] = date('j M Y', strtotime($date));

            $total_sell_on_date = $sells_this_fy->where('date', $date)->sum('total_sells');

            if (!empty($total_sell_on_date)) {
                $all_sell_values[] = (float) $total_sell_on_date;
            } else {
                $all_sell_values[] = 0;
            }
        }

        //Group sells by location
        $location_sells = [];
        foreach ($all_locations as $loc_id => $loc_name) {
            $values = [];
            foreach ($dates as $date) {
                $total_sell_on_date_location = $sells_this_fy->where('date', $date)->where('location_id', $loc_id)->sum('total_sells');

                if (!empty($total_sell_on_date_location)) {
                    $values[] = (float) $total_sell_on_date_location;
                } else {
                    $values[] = 0;
                }
            }
            $location_sells[$loc_id]['loc_label'] = $loc_name;
            $location_sells[$loc_id]['values'] = $values;
        }

        $sells_chart_1 = new CommonChart;

        $sells_chart_1->labels($labels)
            ->options($this->__chartOptions(__(
                'home.total_sells',
                ['currency' => $currency->code]
            )));

        if (!empty($location_sells)) {
            foreach ($location_sells as $location_sell) {
                $sells_chart_1->dataset($location_sell['loc_label'], 'line', $location_sell['values']);
            }
        }

        if (count($all_locations) > 1) {
            $sells_chart_1->dataset(__('report.all_locations'), 'line', $all_sell_values);
        }

        $labels = [];
        $values = [];
        $date = strtotime($fy['start']);
        $last   = date('m-Y', strtotime($fy['end']));
        $fy_months = [];
        do {
            $month_year = date('m-Y', $date);
            $fy_months[] = $month_year;

            $labels[] = \Carbon::createFromFormat('m-Y', $month_year)
                ->format('M-Y');
            $date = strtotime('+1 month', $date);

            $total_sell_in_month_year = $sells_this_fy->where('yearmonth', $month_year)->sum('total_sells');

            if (!empty($total_sell_in_month_year)) {
                $values[] = (float) $total_sell_in_month_year;
            } else {
                $values[] = 0;
            }
        } while ($month_year != $last);

        $fy_sells_by_location_data = [];

        foreach ($all_locations as $loc_id => $loc_name) {
            $values_data = [];
            foreach ($fy_months as $month) {
                $total_sell_in_month_year_location = $sells_this_fy->where('yearmonth', $month)->where('location_id', $loc_id)->sum('total_sells');

                if (!empty($total_sell_in_month_year_location)) {
                    $values_data[] = (float) $total_sell_in_month_year_location;
                } else {
                    $values_data[] = 0;
                }
            }
            $fy_sells_by_location_data[$loc_id]['loc_label'] = $loc_name;
            $fy_sells_by_location_data[$loc_id]['values'] = $values_data;
        }

        $sells_chart_2 = new CommonChart;
        $sells_chart_2->labels($labels)
            ->options($this->__chartOptions(__(
                'home.total_sells',
                ['currency' => $currency->code]
            )));
        if (!empty($fy_sells_by_location_data)) {
            foreach ($fy_sells_by_location_data as $location_sell) {
                $sells_chart_2->dataset($location_sell['loc_label'], 'line', $location_sell['values']);
            }
        }
        if (count($all_locations) > 1) {
            $sells_chart_2->dataset(__('report.all_locations'), 'line', $values);
        }

        //Get Dashboard widgets from module
        $module_widgets = $this->moduleUtil->getModuleData('dashboard_widget');

        $widgets = [];

        foreach ($module_widgets as $widget_array) {
            if (!empty($widget_array['position'])) {
                $widgets[$widget_array['position']][] = $widget_array['widget'];
            }
        }

        $common_settings = !empty(session('business.common_settings')) ? session('business.common_settings') : [];
        $categories = Category::forDropdown($business_id, 'product');

        return view('home.index', compact('sells_chart_1', 'sells_chart_2', 'widgets', 'all_locations',
            'common_settings', 'is_admin','categories'));
    }

    /**
     * Retrieves purchase and sell details for a given time period.
     *
     * @return array
     */
    public function getTotals()
    {
        if (request()->ajax()) {
            $start = request()->start;
            $end = request()->end;
            $location_id = request()->location_id;
            $business_id = request()->session()->get('user.business_id');

            $purchase_details = $this->transactionUtil->getPurchaseTotals($business_id, $start, $end, $location_id);

            $sell_details = $this->transactionUtil->getSellTotals($business_id, $start, $end, $location_id);

            $total_ledger_discount = $this->transactionUtil->getTotalLedgerDiscount($business_id, $start, $end);

            $purchase_details['purchase_due'] = $purchase_details['purchase_due'] - $total_ledger_discount['total_purchase_discount'];

            $transaction_types = [
                'purchase_return', 'sell_return', 'expense'
            ];

            $transaction_totals = $this->transactionUtil->getTransactionTotals(
                $business_id,
                $transaction_types,
                $start,
                $end,
                $location_id
            );

            $total_purchase_inc_tax = !empty($purchase_details['total_purchase_inc_tax']) ? $purchase_details['total_purchase_inc_tax'] : 0;
            $total_purchase_return_inc_tax = $transaction_totals['total_purchase_return_inc_tax'];

            $output = $purchase_details;
            $output['total_purchase'] = $total_purchase_inc_tax;
            $output['total_purchase_return'] = $total_purchase_return_inc_tax;
            $output['total_purchase_return_paid'] = $this->transactionUtil->getTotalPurchaseReturnPaid($business_id, $start, $end, $location_id);

            $total_sell_inc_tax = !empty($sell_details['total_sell_inc_tax']) ? $sell_details['total_sell_inc_tax'] : 0;
            $total_sell_return_inc_tax = !empty($transaction_totals['total_sell_return_inc_tax']) ? $transaction_totals['total_sell_return_inc_tax'] : 0;
            $output['total_sell_return_paid'] = $this->transactionUtil->getTotalSellReturnPaid($business_id, $start, $end, $location_id);

            $output['total_sell'] = $total_sell_inc_tax;
            $output['total_sell_return'] = $total_sell_return_inc_tax;

            $output['invoice_due'] = $sell_details['invoice_due'] - $total_ledger_discount['total_sell_discount'];
            $output['total_expense'] = $transaction_totals['total_expense'];

            //NET = TOTAL SALES - INVOICE DUE - EXPENSE
            $output['net'] = $output['total_sell'] - $output['invoice_due'] - $output['total_expense'] - $output['total_sell_return'];

            $output['total_receivable'] = $this->get_total_receiveable($business_id);
            $output['total_payable'] = $this->get_total_payable($business_id);
            $output['stock_value'] = $this->get_stock_value($business_id, $start, $end);
            $output['total_suppliers'] = $this->get_total_suppliers($business_id);
            $output['gross_profit'] = $this->get_gross_profit($business_id, $start, $end);

            return $output;
        }
    }

    /**
     * Retrieves sell products whose available quntity is less than alert quntity.
     *
     * @return \Illuminate\Http\Response
     */
    public function getProductStockAlert()
    {
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

            $query = VariationLocationDetails::join(
                'product_variations as pv',
                'variation_location_details.product_variation_id',
                '=',
                'pv.id'
            )
                ->join(
                    'variations as v',
                    'variation_location_details.variation_id',
                    '=',
                    'v.id'
                )
                ->join(
                    'products as p',
                    'variation_location_details.product_id',
                    '=',
                    'p.id'
                )
                ->leftjoin(
                    'categories as category',
                    'p.category_id',
                    '=',
                    'category.id'
                )
                ->leftjoin(
                    'categories as subcategory',
                    'p.sub_category_id',
                    '=',
                    'subcategory.id'
                )
                ->leftjoin(
                    'business_locations as l',
                    'variation_location_details.location_id',
                    '=',
                    'l.id'
                )
                ->leftjoin('units as u', 'p.unit_id', '=', 'u.id')
                ->where('p.business_id', $business_id)
                ->where('p.enable_stock', 1)
                ->where('p.is_inactive', 0)
                ->whereNull('v.deleted_at')
                ->whereNotNull('p.alert_quantity')
                ->whereRaw('variation_location_details.qty_available <= p.alert_quantity');

            //Check for permitted locations of a user
            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $query->whereIn('variation_location_details.location_id', $permitted_locations);
            }

            if (!empty(request()->input('location_id'))) {
                $query->where('variation_location_details.location_id', request()->input('location_id'));
            }

            $category_id = request()->get('category_id', null);
            $sub_category_id = request()->get('sub_category_id', null);


            if (!empty($category_id)) {
                $query->where('p.category_id', $category_id);
            }

            if (!empty($sub_category_id)) {
                $query->where('p.sub_category_id', $sub_category_id);
            }

            $products = $query->select(
                'p.name as product',
                'p.type',
                'p.sku',
                'pv.name as product_variation',
                'v.name as variation',
                'v.sub_sku',
                'l.name as location',
                'category.name as categoryName',
                'subcategory.name as subCategoryName',
                'variation_location_details.qty_available as stock',
                'u.short_name as unit'
            )
                ->groupBy('variation_location_details.id')
                ->orderBy('stock', 'asc');

            return Datatables::of($products)
                ->editColumn('product', function ($row) {
                    if ($row->type == 'single') {
                        return $row->product . ' (' . $row->sku . ')';
                    } else {
                        return $row->product . ' - ' . $row->product_variation . ' - ' . $row->variation . ' (' . $row->sub_sku . ')';
                    }
                })
                ->editColumn('stock', function ($row) {
                    $stock = $row->stock ? $row->stock : 0 ;
                    return '<span data-is_quantity="true" class="display_currency" data-currency_symbol=false>'. (float)$stock . '</span> ' . $row->unit;
                })
                ->removeColumn('sku')
                ->removeColumn('sub_sku')
                ->removeColumn('unit')
                ->removeColumn('type')
                ->removeColumn('product_variation')
                ->removeColumn('variation')
                ->rawColumns([4])
                ->make(false);
        }
    }

    /**
     * Retrieves payment dues for the purchases.
     *
     * @return \Illuminate\Http\Response
     */
    public function getPurchasePaymentDues()
    {
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $today = \Carbon::now()->format("Y-m-d H:i:s");

            $query = Transaction::join(
                'contacts as c',
                'transactions.contact_id',
                '=',
                'c.id'
            )
                ->leftJoin(
                    'transaction_payments as tp',
                    'transactions.id',
                    '=',
                    'tp.transaction_id'
                )
                ->where('transactions.business_id', $business_id)
                ->where('transactions.type', 'purchase')
                ->where('transactions.payment_status', '!=', 'paid')
                ->whereRaw("DATEDIFF( DATE_ADD( transaction_date, INTERVAL IF(transactions.pay_term_type = 'days', transactions.pay_term_number, 30 * transactions.pay_term_number) DAY), '$today') <= 7");

            //Check for permitted locations of a user
            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $query->whereIn('transactions.location_id', $permitted_locations);
            }

            if (!empty(request()->input('location_id'))) {
                $query->where('transactions.location_id', request()->input('location_id'));
            }

            $dues =  $query->select(
                'transactions.id as id',
                'c.name as supplier',
                'c.supplier_business_name',
                'ref_no',
                'final_total',
                DB::raw('SUM(tp.amount) as total_paid')
            )
                ->groupBy('transactions.id');

            return Datatables::of($dues)
                ->addColumn('due', function ($row) {
                    $total_paid = !empty($row->total_paid) ? $row->total_paid : 0;
                    $due = $row->final_total - $total_paid;
                    return '<span class="display_currency" data-currency_symbol="true">' .
                        $due . '</span>';
                })
                ->addColumn('action', '@can("purchase.create") <a href="{{action("TransactionPaymentController@addPayment", [$id])}}" class="btn btn-xs btn-success add_payment_modal"><i class="fas fa-money-bill-alt"></i> @lang("purchase.add_payment")</a> @endcan')
                ->removeColumn('supplier_business_name')
                ->editColumn('supplier', '@if(!empty($supplier_business_name)) {{$supplier_business_name}}, <br> @endif {{$supplier}}')
                ->editColumn('ref_no', function ($row) {
                    if (auth()->user()->can('purchase.view')) {
                        return  '<a href="#" data-href="' . action('PurchaseController@show', [$row->id]) . '"
                                    class="btn-modal" data-container=".view_modal">' . $row->ref_no . '</a>';
                    }
                    return $row->ref_no;
                })
                ->removeColumn('id')
                ->removeColumn('final_total')
                ->removeColumn('total_paid')
                ->rawColumns([0, 1, 2, 3])
                ->make(false);
        }
    }

    /**
     * Retrieves payment dues for the purchases.
     *
     * @return \Illuminate\Http\Response
     */
    public function getSalesPaymentDues()
    {
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $today = \Carbon::now()->format("Y-m-d H:i:s");

            $query = Transaction::join(
                'contacts as c',
                'transactions.contact_id',
                '=',
                'c.id'
            )
                ->leftJoin(
                    'transaction_payments as tp',
                    'transactions.id',
                    '=',
                    'tp.transaction_id'
                )
                ->where('transactions.business_id', $business_id)
                ->where('transactions.type', 'sell')
                ->where('transactions.payment_status', '!=', 'paid')
                ->whereNotNull('transactions.pay_term_number')
                ->whereNotNull('transactions.pay_term_type')
                ->whereRaw("DATEDIFF( DATE_ADD( transaction_date, INTERVAL IF(transactions.pay_term_type = 'days', transactions.pay_term_number, 30 * transactions.pay_term_number) DAY), '$today') <= 7");

            //Check for permitted locations of a user
            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $query->whereIn('transactions.location_id', $permitted_locations);
            }

            if (!empty(request()->input('location_id'))) {
                $query->where('transactions.location_id', request()->input('location_id'));
            }

            $dues =  $query->select(
                'transactions.id as id',
                'c.name as customer',
                'c.supplier_business_name',
                'transactions.invoice_no',
                'final_total',
                DB::raw('SUM(tp.amount) as total_paid')
            )
                ->groupBy('transactions.id');

            return Datatables::of($dues)
                ->addColumn('due', function ($row) {
                    $total_paid = !empty($row->total_paid) ? $row->total_paid : 0;
                    $due = $row->final_total - $total_paid;
                    return '<span class="display_currency" data-currency_symbol="true">' .
                        $due . '</span>';
                })
                ->editColumn('invoice_no', function ($row) {
                    if (auth()->user()->can('sell.view')) {
                        return  '<a href="#" data-href="' . action('SellController@show', [$row->id]) . '"
                                    class="btn-modal" data-container=".view_modal">' . $row->invoice_no . '</a>';
                    }
                    return $row->invoice_no;
                })
                ->addColumn('action', '@if(auth()->user()->can("sell.create") || auth()->user()->can("direct_sell.access")) <a href="{{action("TransactionPaymentController@addPayment", [$id])}}" class="btn btn-xs btn-success add_payment_modal"><i class="fas fa-money-bill-alt"></i> @lang("purchase.add_payment")</a> @endif')
                ->editColumn('customer', '@if(!empty($supplier_business_name)) {{$supplier_business_name}}, <br> @endif {{$customer}}')
                ->removeColumn('supplier_business_name')
                ->removeColumn('id')
                ->removeColumn('final_total')
                ->removeColumn('total_paid')
                ->rawColumns([0, 1, 2, 3])
                ->make(false);
        }
    }

    public function get_total_receiveable($business_id)
    {
        $contacts = Contact::where('contacts.business_id', $business_id)
            ->where('contacts.type', 'customer')
            ->join('transactions AS t', 'contacts.id', '=', 't.contact_id')
            ->active()
            ->groupBy('contacts.id')
            ->select(
                DB::raw("SUM(IF(t.type = 'purchase', final_total, 0)) as total_purchase"),
                DB::raw("SUM(IF(t.type = 'purchase_return', final_total, 0)) as total_purchase_return"),
                DB::raw("SUM(IF(t.type = 'sell' AND t.status = 'final', final_total, 0)) as total_invoice"),
                DB::raw("SUM(IF(t.type = 'purchase', (SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as purchase_paid"),
                DB::raw("SUM(IF(t.type = 'sell' AND t.status = 'final', (SELECT SUM(IF(is_return = 1,-1*amount,amount)) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as invoice_received"),
                DB::raw("SUM(IF(t.type = 'sell_return', (SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as sell_return_paid"),
                DB::raw("SUM(IF(t.type = 'purchase_return', (SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as purchase_return_received"),
                DB::raw("SUM(IF(t.type = 'sell_return', final_total, 0)) as total_sell_return"),
                DB::raw("SUM(IF(t.type = 'opening_balance', final_total, 0)) as opening_balance"),
                DB::raw("SUM(IF(t.type = 'opening_balance', (SELECT SUM(IF(is_return = 1,-1*amount,amount)) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as opening_balance_paid"),
                'contacts.id',
                'contacts.created_at',
                'contacts.type as contact_type'
            );

        $contacts = $contacts->get();

        $total_receive = 0;
        foreach ($contacts as $contact){
            $temp = ($contact->total_invoice - $contact->invoice_received - $contact->total_sell_return + $contact->sell_return_paid) - ($contact->total_purchase - $contact->total_purchase_return + $contact->purchase_return_received - $contact->purchase_paid);

            $temp += $contact->opening_balance - $contact->opening_balance_paid;
            $total_receive += $temp;
        }

        return $total_receive;
    }
    public function get_total_payable($business_id)
    {
        $contacts = Contact::where('contacts.business_id', $business_id)
            ->where('contacts.type', 'supplier')
            ->join('transactions AS t', 'contacts.id', '=', 't.contact_id')
            ->active()
            ->groupBy('contacts.id')
            ->select(
                DB::raw("SUM(IF(t.type = 'purchase', final_total, 0)) as total_purchase"),
                DB::raw("SUM(IF(t.type = 'purchase_return', final_total, 0)) as total_purchase_return"),
                DB::raw("SUM(IF(t.type = 'sell' AND t.status = 'final', final_total, 0)) as total_invoice"),
                DB::raw("SUM(IF(t.type = 'purchase', (SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as purchase_paid"),
                DB::raw("SUM(IF(t.type = 'sell' AND t.status = 'final', (SELECT SUM(IF(is_return = 1,-1*amount,amount)) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as invoice_received"),
                DB::raw("SUM(IF(t.type = 'sell_return', (SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as sell_return_paid"),
                DB::raw("SUM(IF(t.type = 'purchase_return', (SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as purchase_return_received"),
                DB::raw("SUM(IF(t.type = 'sell_return', final_total, 0)) as total_sell_return"),
                DB::raw("SUM(IF(t.type = 'opening_balance', final_total, 0)) as opening_balance"),
                DB::raw("SUM(IF(t.type = 'opening_balance', (SELECT SUM(IF(is_return = 1,-1*amount,amount)) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as opening_balance_paid"),
                'contacts.id',
                'contacts.created_at'
            );

        $contacts = $contacts->get();

        $total_pay = 0;
        foreach ($contacts as $contact){
            $temp = ($contact->total_invoice - $contact->invoice_received - $contact->total_sell_return + $contact->sell_return_paid) - ($contact->total_purchase - $contact->total_purchase_return + $contact->purchase_return_received - $contact->purchase_paid);

            $temp -= $contact->opening_balance - $contact->opening_balance_paid;
            $total_pay -= $temp;
        }
        return '- '.$total_pay;
    }
    public function get_stock_value($business_id, $start_date, $end_date)
    {
        $query = PurchaseLine::join(
            'transactions as purchase',
            'purchase_lines.transaction_id',
            '=',
            'purchase.id'
        )
            ->where('purchase.business_id', $business_id);

        $price_query_part = "(purchase_lines.purchase_price + 
                            COALESCE(purchase_lines.item_tax, 0))";


        $query->leftjoin('variations as v', 'v.id', '=', 'purchase_lines.variation_id')
            ->leftjoin('products as p', 'p.id', '=', 'purchase_lines.product_id');


        $query->whereRaw("date(transaction_date) <= '$end_date'");


        $query->select(
            \Illuminate\Support\Facades\DB::raw("SUM((purchase_lines.quantity - purchase_lines.quantity_returned - purchase_lines.quantity_adjusted -
                            (SELECT COALESCE(SUM(tspl.quantity - tspl.qty_returned), 0) FROM 
                            transaction_sell_lines_purchase_lines AS tspl
                            JOIN transaction_sell_lines as tsl ON 
                            tspl.sell_line_id=tsl.id 
                            JOIN transactions as sale ON 
                            tsl.transaction_id=sale.id 
                            WHERE tspl.purchase_line_id = purchase_lines.id AND 
                            date(sale.transaction_date) <= '$end_date') ) * $price_query_part
                        ) as stock")
        );

        //Check for permitted locations of a user
        $permitted_locations = auth()->user()->permitted_locations();
        if ($permitted_locations != 'all') {
            $query->whereIn('purchase.location_id', $permitted_locations);
        }


        $details = $query->first();
        return $details->stock;
    }
    public function get_gross_profit($business_id,$start_date,$end_date)
    {
        $query = TransactionSellLinesPurchaseLines::join('transaction_sell_lines 
                        as SL', 'SL.id', '=', 'transaction_sell_lines_purchase_lines.sell_line_id')
            ->join('transactions as sale', 'SL.transaction_id', '=', 'sale.id')
            ->leftjoin('purchase_lines as PL', 'PL.id', '=', 'transaction_sell_lines_purchase_lines.purchase_line_id')
            ->join('variations as v', 'SL.variation_id', '=', 'v.id')
            ->where('sale.business_id', $business_id);

        if (!empty($start_date) && !empty($end_date) && $start_date != $end_date) {
            $query->whereDate('sale.transaction_date', '>=', $start_date)
                ->whereDate('sale.transaction_date', '<=', $end_date);
        }
        if (!empty($start_date) && !empty($end_date) && $start_date == $end_date) {
            $query->whereDate('sale.transaction_date', $end_date);
        }

//        //Filter by the location
//        if (!empty($location_id)) {
//            $query->where('sale.location_id', $location_id);
//        }

        if (!empty($user_id)) {
            $query->where('sale.created_by', $user_id);
        }

        $gross_profit_obj = $query->select(\Illuminate\Support\Facades\DB::raw('SUM( 
                        (transaction_sell_lines_purchase_lines.quantity - transaction_sell_lines_purchase_lines.qty_returned) * (SL.unit_price_inc_tax - IFNULL(PL.purchase_price_inc_tax, v.default_purchase_price) ) ) as gross_profit'))
            ->first();

        $gross_profit = !empty($gross_profit_obj->gross_profit) ? $gross_profit_obj->gross_profit : 0;

        //Deduct the sell transaction discounts.
        $transaction_totals = $this->getTransactionTotals($business_id, ['sell'], $start_date, $end_date, null, auth()->user()->id);
        $sell_discount = !empty($transaction_totals['total_sell_discount']) ? $transaction_totals['total_sell_discount'] : 0;

        //Get total selling price of products with stock disabled
        $query_2 =
            TransactionSellLine::join('transactions as sale',
                'transaction_sell_lines.transaction_id', '=', 'sale.id')
                ->join('products as p', 'p.id', '=', 'transaction_sell_lines.product_id')
                ->where('sale.business_id', $business_id)
                ->where('sale.status', 'final')
                ->where('sale.type', 'sell')
                ->where('p.enable_stock', 0);

        if (!empty($start_date) && !empty($end_date) && $start_date != $end_date) {
            $query_2->whereBetween(DB::raw('sale.transaction_date'), [$start_date, $end_date]);
        }
        if (!empty($start_date) && !empty($end_date) && $start_date == $end_date) {
            $query_2->whereDate('sale.transaction_date', $end_date);
        }

        //Filter by the location
        if (!empty($location_id)) {
            $query_2->where('sale.location_id', $location_id);
        }

        if (!empty($user_id)) {
            $query_2->where('sale.created_by', $user_id);
        }

        $stock_disabled_product_sell_details =
            $query_2->select(DB::raw('SUM( 
                        (transaction_sell_lines.quantity - transaction_sell_lines.quantity_returned ) * transaction_sell_lines.unit_price_inc_tax ) as gross_profit'))
                ->first();

        $stock_disabled_product_profit = !empty($stock_disabled_product_sell_details->gross_profit) ? $stock_disabled_product_sell_details->gross_profit : 0;

        //KNOWS ISSUE: If products are returned then also the discount gets applied for it.

        return $gross_profit + $stock_disabled_product_profit - $sell_discount;
    }

    public function get_total_suppliers($business_id)
    {
        $query = Contact::where('type','supplier')->where('business_id', $business_id);
        return $query->count();
    }

    public function getTransactionTotals(
        $business_id,
        $transaction_types,
        $start_date = null,
        $end_date = null,
        $location_id = null,
        $created_by = null
    ) {
        $query = Transaction::where('business_id', $business_id);

        //Check for permitted locations of a user
        $permitted_locations = auth()->user()->permitted_locations();
        if ($permitted_locations != 'all') {
            $query->whereIn('transactions.location_id', $permitted_locations);
        }

        if (!empty($start_date) && !empty($end_date)) {
            $query->whereDate('transactions.transaction_date', '>=', $start_date)
                ->whereDate('transactions.transaction_date', '<=', $end_date);
        }

        if (empty($start_date) && !empty($end_date)) {
            $query->whereDate('transactions.transaction_date', '<=', $end_date);
        }

        //Filter by the location
        if (!empty($location_id)) {
            $query->where('transactions.location_id', $location_id);
        }

        //Filter by created_by
        if (!empty($created_by)) {
            $query->where('transactions.created_by', $created_by);
        }

        if (in_array('purchase_return', $transaction_types)) {
            $query->addSelect(
                \Illuminate\Support\Facades\DB::raw("SUM(IF(transactions.type='purchase_return', final_total, 0)) as total_purchase_return_inc_tax"),
                DB::raw("SUM(IF(transactions.type='purchase_return', total_before_tax, 0)) as total_purchase_return_exc_tax")
            );
        }

        if (in_array('sell_return', $transaction_types)) {
            $query->addSelect(
                DB::raw("SUM(IF(transactions.type='sell_return', final_total, 0)) as total_sell_return_inc_tax"),
                DB::raw("SUM(IF(transactions.type='sell_return', total_before_tax, 0)) as total_sell_return_exc_tax")
            );
        }

        if (in_array('sell_transfer', $transaction_types)) {
            $query->addSelect(
                DB::raw("SUM(IF(transactions.type='sell_transfer', shipping_charges, 0)) as total_transfer_shipping_charges")

            );
        }

        if (in_array('expense', $transaction_types)) {
            $query->addSelect(
                DB::raw("SUM(IF(transactions.type='expense', final_total, 0)) as total_expense")
            );

            $query->addSelect(
                DB::raw("SUM(IF(transactions.type='expense_refund', final_total, 0)) as total_expense_refund")
            );
        }

        if (in_array('payroll', $transaction_types)) {
            $query->addSelect(
                DB::raw("SUM(IF(transactions.type='payroll', final_total, 0)) as total_payroll")
            );
        }

        if (in_array('stock_adjustment', $transaction_types)) {
            $query->addSelect(
                DB::raw("SUM(IF(transactions.type='stock_adjustment', final_total, 0)) as total_adjustment"),
                DB::raw("SUM(IF(transactions.type='stock_adjustment', total_amount_recovered, 0)) as total_recovered")
            );
        }

        if (in_array('purchase', $transaction_types)) {
            $query->addSelect(
                DB::raw("SUM(IF(transactions.type='purchase', IF(discount_type = 'percentage', COALESCE(discount_amount, 0)*total_before_tax/100, COALESCE(discount_amount, 0)), 0)) as total_purchase_discount")
            );
        }

        if (in_array('sell', $transaction_types)) {
            $query->addSelect(
                DB::raw("SUM(IF(transactions.type='sell' AND transactions.status='final', IF(discount_type = 'percentage', COALESCE(discount_amount, 0)*total_before_tax/100, COALESCE(discount_amount, 0)), 0)) as total_sell_discount"),
                DB::raw("SUM(IF(transactions.type='sell' AND transactions.status='final', rp_redeemed_amount, 0)) as total_reward_amount"),
                DB::raw("SUM(IF(transactions.type='sell' AND transactions.status='final', round_off_amount, 0)) as total_sell_round_off")
            );
        }

        $transaction_totals = $query->first();
        $output = [];

        if (in_array('purchase_return', $transaction_types)) {
            $output['total_purchase_return_inc_tax'] = !empty($transaction_totals->total_purchase_return_inc_tax) ?
                $transaction_totals->total_purchase_return_inc_tax : 0;

            $output['total_purchase_return_exc_tax'] =
                !empty($transaction_totals->total_purchase_return_exc_tax) ?
                    $transaction_totals->total_purchase_return_exc_tax : 0;
        }

        if (in_array('sell_return', $transaction_types)) {
            $output['total_sell_return_inc_tax'] =
                !empty($transaction_totals->total_sell_return_inc_tax) ?
                    $transaction_totals->total_sell_return_inc_tax : 0;

            $output['total_sell_return_exc_tax'] =
                !empty($transaction_totals->total_sell_return_exc_tax) ?
                    $transaction_totals->total_sell_return_exc_tax : 0;
        }

        if (in_array('sell_transfer', $transaction_types)) {
            $output['total_transfer_shipping_charges'] =
                !empty($transaction_totals->total_transfer_shipping_charges) ?
                    $transaction_totals->total_transfer_shipping_charges : 0;
        }

        if (in_array('expense', $transaction_types)) {
            $total_expense = !empty($transaction_totals->total_expense) ?
                $transaction_totals->total_expense : 0;
            $total_expense_refund = !empty($transaction_totals->total_expense_refund) ?
                $transaction_totals->total_expense_refund : 0;
            $output['total_expense'] = $total_expense - $total_expense_refund;
        }

        if (in_array('payroll', $transaction_types)) {
            $output['total_payroll'] =
                !empty($transaction_totals->total_payroll) ?
                    $transaction_totals->total_payroll : 0;
        }

        if (in_array('stock_adjustment', $transaction_types)) {
            $output['total_adjustment'] =
                !empty($transaction_totals->total_adjustment) ?
                    $transaction_totals->total_adjustment : 0;

            $output['total_recovered'] =
                !empty($transaction_totals->total_recovered) ?
                    $transaction_totals->total_recovered : 0;
        }

        if (in_array('purchase', $transaction_types)) {
            $output['total_purchase_discount'] =
                !empty($transaction_totals->total_purchase_discount) ?
                    $transaction_totals->total_purchase_discount : 0;
        }

        if (in_array('sell', $transaction_types)) {
            $output['total_sell_discount'] =
                !empty($transaction_totals->total_sell_discount) ?
                    $transaction_totals->total_sell_discount : 0;

            $output['total_reward_amount'] =
                !empty($transaction_totals->total_reward_amount) ?
                    $transaction_totals->total_reward_amount : 0;

            $output['total_sell_round_off'] =
                !empty($transaction_totals->total_sell_round_off) ?
                    $transaction_totals->total_sell_round_off : 0;
        }

        return $output;
    }


    public function loadMoreNotifications()
    {
        $notifications = auth()->user()->notifications()->orderBy('created_at', 'DESC')->paginate(10);

        if (request()->input('page') == 1) {
            auth()->user()->unreadNotifications->markAsRead();
        }
        $notifications_data = $this->commonUtil->parseNotifications($notifications);

        return view('layouts.partials.notification_list', compact('notifications_data'));
    }

    /**
     * Function to count total number of unread notifications
     *
     * @return json
     */
    public function getTotalUnreadNotifications()
    {
        $unread_notifications = auth()->user()->unreadNotifications;
        $total_unread = $unread_notifications->count();

        $notification_html = '';
        $modal_notifications = [];
        foreach ($unread_notifications as $unread_notification) {
            if (isset($data['show_popup'])) {
                $modal_notifications[] = $unread_notification;
                $unread_notification->markAsRead();
            }
        }
        if (!empty($modal_notifications)) {
            $notification_html = view('home.notification_modal')->with(['notifications' => $modal_notifications])->render();
        }

        return [
            'total_unread' => $total_unread,
            'notification_html' => $notification_html
        ];
    }

    private function __chartOptions($title)
    {
        return [
            'yAxis' => [
                'title' => [
                    'text' => $title
                ]
            ],
            'legend' => [
                'align' => 'right',
                'verticalAlign' => 'top',
                'floating' => true,
                'layout' => 'vertical'
            ],
        ];
    }

    public function getCalendar()
    {
        $business_id = request()->session()->get('user.business_id');
        $is_admin = $this->restUtil->is_admin(auth()->user(), $business_id);
        $is_superadmin = auth()->user()->can('superadmin');
        if (request()->ajax()) {
            $data = [
                'start_date' => request()->start,
                'end_date' => request()->end,
                'user_id' => ($is_admin || $is_superadmin) && !empty(request()->user_id) ? request()->user_id : auth()->user()->id,
                'location_id' => !empty(request()->location_id) ? request()->location_id : null,
                'business_id' => $business_id,
                'events' => request()->events ?? [],
                'color' => '#007FFF'
            ];
            $events = [];

            if (in_array('bookings', $data['events'])) {
                $events = $this->restUtil->getBookingsForCalendar($data);
            }

            $module_events = $this->moduleUtil->getModuleData('calendarEvents', $data);

            foreach ($module_events as $module_event) {
                $events = array_merge($events, $module_event);
            }

            return $events;
        }

        $all_locations = BusinessLocation::forDropdown($business_id)->toArray();
        $users = [];
        if ($is_admin) {
            $users = User::forDropdown($business_id, false);
        }

        $event_types = [
            'bookings' => [
                'label' => __('restaurant.bookings'),
                'color' => '#007FFF'
            ]
        ];
        $module_event_types = $this->moduleUtil->getModuleData('eventTypes');
        foreach ($module_event_types as $module_event_type) {
            $event_types = array_merge($event_types, $module_event_type);
        }

        return view('home.calendar')->with(compact('all_locations', 'users', 'event_types'));
    }

    public function showNotification($id)
    {
        $notification = DatabaseNotification::find($id);

        $data = $notification->data;

        $notification->markAsRead();

        return view('home.notification_modal')->with([
            'notifications' => [$notification]
        ]);
    }

    public function attachMediasToGivenModel(Request $request)
    {
        if ($request->ajax()) {
            try {

                $business_id = request()->session()->get('user.business_id');

                $model_id = $request->input('model_id');
                $model = $request->input('model_type');
                $model_media_type = $request->input('model_media_type');

                DB::beginTransaction();

                //find model to which medias are to be attached
                $model_to_be_attached = $model::where('business_id', $business_id)
                    ->findOrFail($model_id);

                Media::uploadMedia($business_id, $model_to_be_attached, $request, 'file', false, $model_media_type);

                DB::commit();

                $output = [
                    'success' => true,
                    'msg' => __('lang_v1.success')
                ];
            } catch (Exception $e) {

                DB::rollBack();

                \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());

                $output = [
                    'success' => false,
                    'msg' => __('messages.something_went_wrong')
                ];
            }

            return $output;
        }
    }

    public function getUserLocation($latlng)
    {
        $latlng_array = explode(',', $latlng);

        $response = $this->moduleUtil->getLocationFromCoordinates($latlng_array[0], $latlng_array[1]);

        return ['address' => $response];
    }

    public function findPrice()
    {
        return view('sale_pos.findPrice');
    }

    public function findProduct(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        $product = Product::where('business_id',$business_id)
            ->where('sku',$request->sku)
            ->first();
        if ($product){
            $query = Product::where('business_id',$business_id)
                ->where('sku',$request->sku)
                ->join('variations', 'products.id', '=', 'variations.product_id')
                ->join('product_variations AS pv', 'variations.product_variation_id', '=', 'pv.id')
                ->leftjoin('variation_location_details AS vld', 'variations.id', '=', 'vld.variation_id');

            $find_product = $query->select(
                DB::raw("IF(pv.is_dummy = 0, CONCAT(products.name, 
                    ' (', pv.name, ':',variations.name, ')'), products.name) AS product_name"),
                'variations.sell_price_inc_tax',
                'products.image'
            )->first();

            $render_data =  view('sale_pos.findProduct',compact('find_product'))->render();

            return response()->json([
                'error' => false,
                'product' => $render_data,
            ]);

        }else{
            return response()->json([
                'error' => true,
                'message' => 'No Product Found',
            ]);
        }
    }

    public function packages(Request $request)
    {
        if ($request->ajax()){
            if (request()->ajax()) {
                $business_id = request()->session()->get('user.business_id');

                $packages = CustomPackage::has('customer')
                    ->where('business_id', $business_id);

                return DataTables::of($packages)

                    ->addColumn(
                        'action',
                        function ($row) {
                            $html = '';
                            $html .=
                                '<a href="' . action('HomeController@packageEdit', [$row->id]) . '" class="btn btn-xs btn-primary" ><i class="glyphicon glyphicon-edit"></i> ' . __("messages.edit") . '</a>';

                            $html .=

                                '<a href="' . action('HomeController@packageDelete', [$row->id]) . '" class="margin-left-10 btn btn-xs btn-danger"><i class="fa fa-trash"></i> ' . __("messages.delete") . '</a>';


                            return $html;
                        }
                    )
                    ->addColumn('customer_name', function ($row) {
                        return $row->customer->name;
                    })
                    ->addColumn('total_products', function ($row) {
                        $packageProducts =  PackageProducts::where('package_id',$row->id)->get();
                        $html = null;
                        foreach ($packageProducts as $p){
                            $product = Product::where('id',$p->product_id)->first();
                            if ($product){
                                $html .=    ucfirst($product->name) .' - ('.$p->quantity.') <br>' ;
                            }
                        }
                        return $html;
                    })
                    ->addColumn('remaining_products', function ($row) {
                        $packageProducts =  PackageProducts::where('package_id',$row->id)->get();
                        $html = null;
                        foreach ($packageProducts as $p){
                            $product = Product::where('id',$p->product_id)->first();
                            if ($product){
                                $html .=    ucfirst($product->name) .' - ('.$p->remaining_quantity.') <br>' ;
                            }
                        }
                        return $html;
                    })
                    ->removeColumn('id')
                    ->rawColumns(['action','total_products','remaining_products'])
                    ->make(true);
            }
        }
        return view('packages.index');
    }

    public function packageCreate(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $customers = Contact::where('contacts.business_id', $business_id)
            ->whereIn('contacts.type', ['customer'])
            ->active()->pluck('name', 'id');
        foreach ($customers as $key => $customer){
            if ($customer == "Walk-In Customer"){
                unset($customers[$key]);
            }
        }
        $products = Product::where('business_id',$business_id)
            ->where('package_product',1)->pluck('name','id');
        return view('packages.create',compact('customers','products'));
    }

    public function getPackageProduct(Request $request,$variation_id)
    {
        $output = [];
        try {
            $business_id = $request->session()->get('user.business_id');
            $query = Variation::join('products AS p', 'variations.product_id', '=', 'p.id')
                ->join('product_variations AS pv', 'variations.product_variation_id', '=', 'pv.id')
                ->leftjoin('variation_location_details AS vld', 'variations.id', '=', 'vld.variation_id')
                ->leftjoin('units', 'p.unit_id', '=', 'units.id')
                ->leftjoin('units as u', 'p.secondary_unit_id', '=', 'u.id')
                ->leftjoin('brands', function ($join) {
                    $join->on('p.brand_id', '=', 'brands.id')
                        ->whereNull('brands.deleted_at');
                })
                ->where('p.business_id', $business_id)
                ->where('variations.id', $variation_id);

            $business_details = $this->businessUtil->getDetails($business_id);
            $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

            $check_qty = !empty($pos_settings['allow_overselling']) ? false : true;

            //Add condition for check of quantity. (if stock is not enabled or qty_available > 0)
            if ($check_qty) {
                $query->where(function ($query) {
                    $query->where('p.enable_stock', '!=', 1)
                        ->orWhere('vld.qty_available', '>', 0);
                });
            }

            $product = $query->select(
                \Illuminate\Support\Facades\DB::raw("IF(pv.is_dummy = 0, CONCAT(p.name, 
                    ' (', pv.name, ':',variations.name, ')'), p.name) AS product_name"),
                'p.id as product_id',
                'p.enable_stock',
                'p.enable_sr_no',
                'p.type as product_type',
                'p.name as product_actual_name',
                'pv.name as product_variation_name',
                'variations.name as variation_name',
                'variations.sell_price_inc_tax',
                'variations.sub_sku',
                'vld.qty_available',
                'variations.id as variation_id',
                DB::raw("(SELECT purchase_price_inc_tax FROM purchase_lines WHERE 
                        variation_id=variations.id ORDER BY id DESC LIMIT 1) as last_purchased_price")
            )
                ->firstOrFail();

            $output['success'] = true;
            $output['html_content'] = view('packages.product_row',compact('product'))->render();

            return $output;
        }catch (\Exception $e) {
            \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());

            $output['success'] = false;
            $output['msg'] = __('lang_v1.item_out_of_stock');
        }
    }

    public function packageSave(Request $request)
    {
        try{
            if ($request->variation){

                DB::beginTransaction();
                $customers = $request->customer_id;
                foreach ($customers as $customer){
                    $business_id = $request->session()->get('user.business_id');
                    $package  =   CustomPackage::create([
                        'name' => $request->name,
                        'customer_id' => $customer,
                        'business_id' => $business_id,
                        'sale_product' => $request->sale_product,
                    ]);

                    foreach ($request->variation as $key => $value){
                        PackageProducts::create([
                            'package_id' => $package->id,
                            'product_id' => $request->product[$key],
                            'variation_id' => $request->variation[$key],
                            'quantity' => $request->quantity[$key],
                            'remaining_quantity' => $request->quantity[$key],
                        ]);
                    }
                }
                DB::commit();

                $output = ['success' => 1,
                    'msg' => 'Package Added Successfully'
                ];
                return redirect()->route('packages')->with('status', $output);
            }else{
                return redirect()->back()->with('danger','please select package products');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());
            $msg = trans("messages.something_went_wrong");

            return redirect()->back()->with('danger',$msg);
        }
    }

    public function packageDelete($id)
    {
        PackageProducts::where('package_id',$id)->delete();
        CustomPackage::destroy($id);

        $output = ['success' => 1,
            'msg' => 'Package Deleted Successfully'
        ];
        return redirect()->back()->with('status', $output);
    }
    public function packageEdit(Request $request,$id)
    {
        $package = CustomPackage::find($id);
        $products = PackageProducts::where('package_id',$id)->get();

        $business_id = $request->session()->get('user.business_id');

        $customers = Contact::where('contacts.business_id', $business_id)
            ->whereIn('contacts.type', ['customer'])
            ->active()->pluck('name', 'id');
        foreach ($customers as $key => $customer){
            if ($customer == "Walk-In Customer"){
                unset($customers[$key]);
            }
        }
        $package_products = Product::where('business_id',$business_id)
            ->where('package_product',1)->pluck('name','id');
        return view('packages.edit',compact('customers','package','products','package_products'));

    }

    public function packageUpdate(CustomPackage $package ,Request $request)
    {
        try{
            if ($request->variation){

                DB::beginTransaction();
                $package->update([
                    'name' => $request->name,
                    'customer_id' => $request->customer_id,
                    'sale_product' => $request->sale_product,
                ]);

                foreach ($request->variation as $key => $value){
                    $check_product = PackageProducts::where('package_id',$package->id)
                        ->where('product_id',$request->product[$key])
                        ->where('variation_id',$request->variation[$key])
                        ->first();

                    if ($check_product){
                        $check_product->update([
                            'remaining_quantity' => $request->quantity[$key],
                        ]);
                    }else{
                        PackageProducts::create([
                            'package_id' => $package->id,
                            'product_id' => $request->product[$key],
                            'variation_id' => $request->variation[$key],
                            'quantity' => $request->quantity[$key],
                            'remaining_quantity' => $request->quantity[$key],
                        ]);
                    }
                }

                DB::commit();


                $output = ['success' => 1,
                    'msg' => 'Package Updated Successfully'
                ];
                return redirect()->route('packages')->with('status', $output);
            }else{
                return redirect()->back()->with('danger','please select product');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());
            $msg = trans("messages.something_went_wrong");

            return redirect()->back()->with('danger',$msg);
        }
    }

    public function getPackageDetail()
    {
        $business_id = request()->session()->get('user.business_id');
        $contact_id = request()->customer_id;


        $packages = CustomPackage::where('business_id',$business_id)
            ->where('customer_id',$contact_id)
            ->get();

        if ($packages->count() >= 1){
            $html = view('packages.modal_data',compact('packages'))->render();
            return response()->json([
                'status' => true,
                'html' => $html
            ]);
        }else{
            return response()->json([
                'status' => false,
            ]);
        }
    }

    public function updatePackageRows(CustomPackage $package ,Request $request)
    {
        try{
            if ($request->variation){

                DB::beginTransaction();

                foreach ($request->variation as $key => $value){
                    $check_product = PackageProducts::where('package_id',$package->id)
                        ->where('product_id',$request->product[$key])
                        ->where('variation_id',$request->variation[$key])
                        ->first();

                        $check_product->update([
                            'remaining_quantity' => $request->quantity[$key],
                        ]);
                }

                DB::commit();


                $output = ['success' => 1,
                    'msg' => 'Package Updated Successfully'
                ];
                return redirect()->route('packages')->with('status', $output);
            }else{
                return redirect()->back()->with('danger','please select product');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());
            $msg = trans("messages.something_went_wrong");

            return redirect()->back()->with('danger',$msg);
        }
    }
}
