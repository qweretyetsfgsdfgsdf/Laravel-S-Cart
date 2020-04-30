<?php
#app/Http/Admin/Controllers/ShopCustomerController.php
namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ShopCountry;
use App\Models\ShopLanguage;
use App\Models\ShopUser;
use App\Http\Controllers\Auth\AuthTrait;
use Validator;

class ShopCustomerController extends Controller
{
    use AuthTrait;
    public $languages, $countries;

    public function __construct()
    {
        $this->languages = ShopLanguage::getList();
        $this->countries = ShopCountry::getList();

    }

    public function index()
    {
        $data = [
            'title' => trans('customer.admin.list'),
            'subTitle' => '',
            'icon' => 'fa fa-indent',
            'menuRight' => [],
            'menuLeft' => [],
            'topMenuRight' => [],
            'topMenuLeft' => [],
            'urlDeleteItem' => route('admin_customer.delete'),
            'removeList' => 1, // 1 - Enable function delete list item
            'buttonRefresh' => 0, // 1 - Enable button refresh
            'buttonSort' => 1, // 1 - Enable button sort
            'css' => '', 
            'js' => '',
        ];

        $listTh = [
            'id' => trans('customer.id'),
            'email' => trans('customer.email'),
            'name' => trans('customer.name'),
            'phone' => trans('customer.phone'),
            'address1' => trans('customer.address1'),
            'address2' => trans('customer.address2'),
            'country' => trans('customer.country'),
            'status' => trans('customer.status'),
            'created_at' => trans('customer.created_at'),
            'action' => trans('customer.admin.action'),
        ];
        $sort_order = request('sort_order') ?? 'id_desc';
        $keyword = request('keyword') ?? '';
        $arrSort = [
            'id__desc' => trans('customer.admin.sort_order.id_desc'),
            'id__asc' => trans('customer.admin.sort_order.id_asc'),
            'first_name__desc' => trans('customer.admin.sort_order.first_name_desc'),
            'first_name__asc' => trans('customer.admin.sort_order.first_name_asc'),
            'last_name__desc' => trans('customer.admin.sort_order.last_name_desc'),
            'last_name__asc' => trans('customer.admin.sort_order.last_name_asc'),
        ];
        $obj = new ShopUser;

        if ($keyword) {
            $obj = $obj->whereRaw('(id = ' . (int) $keyword . ' OR email like "%' . $keyword . '%" OR first_name like "%' . $keyword . '%" OR last_name like "%' . $keyword . '%"  )');
        }
        if ($sort_order && array_key_exists($sort_order, $arrSort)) {
            $field = explode('__', $sort_order)[0];
            $sort_field = explode('__', $sort_order)[1];
            $obj = $obj->orderBy($field, $sort_field);

        } else {
            $obj = $obj->orderBy('id', 'desc');
        }
        $dataTmp = $obj->paginate(20);

        $dataTr = [];
        foreach ($dataTmp as $key => $row) {
            $dataTr[] = [
                'id' => $row['id'],
                'email' => $row['email'],
                'name' => $row['name'],
                'phone' => $row['phone'],
                'address1' => $row['address1'],
                'address2' => $row['address2'],
                'country' => $this->countries[$row['country']]->name ?? '',
                'status' => $row['status'] ? '<span class="label label-success">ON</span>' : '<span class="label label-danger">OFF</span>',
                'created_at' => $row['created_at'],
                'action' => '
                    <a href="' . route('admin_customer.edit', ['id' => $row['id']]) . '"><span title="' . trans('customer.admin.edit') . '" type="button" class="btn btn-flat btn-primary"><i class="fa fa-edit"></i></span></a>&nbsp;

                    <span onclick="deleteItem(' . $row['id'] . ');"  title="' . trans('admin.delete') . '" class="btn btn-flat btn-danger"><i class="fa fa-trash"></i></span>'
                ,
            ];
        }

        $data['listTh'] = $listTh;
        $data['dataTr'] = $dataTr;
        $data['pagination'] = $dataTmp->appends(request()->except(['_token', '_pjax']))->links('admin.component.pagination');
        $data['resultItems'] = trans('customer.admin.result_item', ['item_from' => $dataTmp->firstItem(), 'item_to' => $dataTmp->lastItem(), 'item_total' => $dataTmp->total()]);

//menuRight
        $data['menuRight'][] = '<a href="' . route('admin_customer.create') . '" class="btn  btn-success  btn-flat" title="New" id="button_create_new">
                           <i class="fa fa-plus"></i><span class="hidden-xs">' . trans('admin.add_new') . '</span>
                           </a>';
//=menuRight

//menuSort        
        $optionSort = '';
        foreach ($arrSort as $key => $status) {
            $optionSort .= '<option  ' . (($sort_order == $key) ? "selected" : "") . ' value="' . $key . '">' . $status . '</option>';
        }
        $data['urlSort'] = route('admin_customer.index');
        $data['optionSort'] = $optionSort;
//=menuSort

//menuSearch        
        $data['topMenuRight'][] = '
                <form action="' . route('admin_customer.index') . '" id="button_search">
                   <div onclick="$(this).submit();" class="btn-group pull-right">
                           <a class="btn btn-flat btn-primary" title="Refresh">
                              <i class="fa  fa-search"></i><span class="hidden-xs"> ' . trans('admin.search') . '</span>
                           </a>
                   </div>
                   <div class="btn-group pull-right">
                         <div class="form-group">
                           <input type="text" name="keyword" class="form-control" placeholder="' . trans('customer.admin.search_place') . '" value="' . $keyword . '">
                         </div>
                   </div>
                </form>';
//=menuSearch

        return view('admin.screen.list')
            ->with($data);
    }

/**
 * Form create new order in admin
 * @return [type] [description]
 */
    public function create()
    {
        $data = [
            'title' => trans('customer.admin.add_new_title'),
            'subTitle' => '',
            'title_description' => trans('customer.admin.add_new_des'),
            'icon' => 'fa fa-plus',
            'countries' => (new ShopCountry)->getList(),
            'customer' => [],
            'url_action' => route('admin_customer.create'),

        ];

        return view('admin.screen.customer')
            ->with($data);
    }

/**
 * Post create new order in admin
 * @return [type] [description]
 */
    public function postCreate()
    {
        $data = request()->all();
        $dataMapping = $this->mappingValidator($data);
        $validator =  Validator::make($data, $dataMapping['validate'], $dataMapping['messages']);
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        ShopUser::createCustomer($dataMapping['dataInsert']);

        return redirect()->route('admin_customer.index')->with('success', trans('customer.admin.create_success'));

    }

/**
 * Form edit
 */
    public function edit($id)
    {
        $customer = ShopUser::find($id);
        if ($customer === null) {
            return 'no data';
        }
        $data = [
            'title' => trans('customer.admin.edit'),
            'subTitle' => '',
            'title_description' => '',
            'icon' => 'fa fa-pencil-square-o',
            'customer' => $customer,
            'countries' => (new ShopCountry)->getArray(),
            'url_action' => route('admin_customer.edit', ['id' => $customer['id']]),
        ];
        return view('admin.screen.customer')
            ->with($data);
    }

/**
 * update status
 */
    public function postEdit($id)
    {
        $data = request()->all();
        $data['id'] = $id;
        $dataMapping = $this->mappingValidatorEdit($data);

        $validator =  Validator::make($data, $dataMapping['validate'], $dataMapping['messages']);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        ShopUser::updateInfo($dataMapping['dataUpdate'], $id);
//
        return redirect()->route('admin_customer.index')->with('success', trans('customer.admin.edit_success'));

    }

/*
Delete list Item
Need mothod destroy to boot deleting in model
 */
    public function deleteList()
    {
        if (!request()->ajax()) {
            return response()->json(['error' => 1, 'msg' => 'Method not allow!']);
        } else {
            $ids = request('ids');
            $arrID = explode(',', $ids);
            ShopUser::destroy($arrID);
            return response()->json(['error' => 0, 'msg' => '']);
        }
    }

}
