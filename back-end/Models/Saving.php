<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use App\Traits\Common;
use App\Models\Supplier;
use App\Models\Contract;
use App\Models\CurrencyRate;
use App\Models\Category;
use App\Models\SubCategory;
use App\Models\ContractLocationDepartment;
use App\Models\DepartmentsLocation;
use App\Models\SavingMilestone;
use App\Models\SupplierContract;
use App\Models\ActivityLogEntity;
use App\Models\ActivityLogSubmodule;
use App\Models\ActivityLogModule;
use App\Models\CompanySetting;
use App\Models\SavingType;
use App\Models\SavingApproval;

class Saving extends Model
{
    use HasFactory, SoftDeletes, Common;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'savings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name', 'description', 'status', 'category_id', 'sub_category_id', 'currency_id','total_base_line_spend', 'total_project_saving', 'total_project_saving_perc', 'total_realised_saving_base_line', 'total_realised_saving', 'total_realised_saving_perc', 'supplier_id', 'contract_id', 'created_by', 'time_period', 'milestone_frequency', 'milestone_type', 'project_cost_reduction', 'project_cost_avoidance', 'start_date', 'end_date', 'is_draft', 'archive', 'realised_cost_reduction', 'realised_cost_avoidance', 'saving_type', 'approval_status', 'needs_approval', 'reapproval_count'
    ];

    public function savingApprover()
    {
        return $this->belongsTo(SavingApproval::class, 'id', 'saving_id');
    }

    public function user()
    {
        return $this->belongsTo(User:: class, 'created_by')->select('id', 'name');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier:: class, 'supplier_id')->select('id', 'name', 'document_id', 'status');
    }

    public function contract()
    {
        return $this->belongsTo(Contract:: class, 'contract_id')->select('id', 'title');
    }

    public function currency()
    {
        return $this->belongsTo(CurrencyRate:: class, 'currency_id')->select('id', 'currency', 'currency_symbol', 'rate', 'status');
    }

    public function savingTypes()
    {
        return $this->belongsTo(SavingType::class, 'saving_type');
    }

    public function category()
    {
        return $this->belongsTo(Category:: class, 'category_id');
    }

    public function subcategory()
    {
        return $this->belongsTo(SubCategory:: class, 'sub_category_id');
    }

    public function getContractLocationDepartment()
    {
        return $this->hasMany(SavingLocationDepartment:: class, 'saving_id', 'id');
    }

    public function milestones()
    {
        return $this->hasMany(SavingMilestone:: class, 'saving_id', 'id');
    }

    public function parentChild()
    {
        return $this->hasOne(ParentChildRelation:: class, 'record_id')->where('record_type', RECORD_TYPE_SAVINGS);
    }

    public function owners()
    {
        return $this->hasMany(SavingsOwner::class, 'saving_id', 'id')->select('saving_id', 'user_id', 'id');
    }
    public static function getSavingList($postArr)
    {

        $search = $postArr['search'] ?? '';

        $options = $postArr['options'];
        $perPage = $options['itemsPerPage'] ?? 10;
        $page = $options['page'] ?? 1;
        $sortBy = $options['sortBy'][0]['key'] ?? 'created_at';
        $sortOrder = $options['sortBy'][0]['order'] ?? 'DESC';

        $supplierid = $postArr['supplier_id'] ?? '';
        $contractId = $postArr['contract_id'] ?? '';

        // filters
        
        $location = $postArr['location'] ?? [];
        $department = $postArr['department'] ?? [];
        $category = $postArr['category'] ?? [];
        $subCategory = $postArr['subCategory'] ?? [];
        $startDate = $postArr['startDate'] ?? null;
        $endDate = $postArr['endDate'] ?? null;
        //$status = $postArr['status'] ?? '';
        $archive = $postArr['archive'] ?? [0];
        if (!is_array($archive)) {
            $archive = [$archive];
        }
        $createdBy = $postArr['createdBy'] ?? [];

        $saving_type = $postArr['saving_type'] ?? [];
        
        $currency = $postArr['currency'] ?? [];


        $status = $postArr['status'] ?? [];
        if (!is_array($status)) {
            $status = [$status];
        }

        $approvalStatus = $postArr['approval_status'] ?? [STATUS_ACTIVE];
        if (!is_array($approvalStatus)) {
            $approvalStatus = [$approvalStatus];
        }
        
        $supplierIds = $postArr['supplier_ids'] ?? [];

        $columns = Schema::getColumnListing('savings');
        $columns = array_map(fn($item) => "savings.".$item, $columns);
        $list = self::with(['user',
         'currency' => function ($query) {
            $query->withTrashed();
         }, 'supplier', 'supplier.supplierImage', 'contract', 'category' => function ($query) {
            $query->withTrashed();
         }, 'subcategory' => function ($query) {
            $query->withTrashed();
         }, 'getContractLocationDepartment',
        'getContractLocationDepartment.getDpartmentLocation' => function ($query) {
            $query->withTrashed();
        },
        'getContractLocationDepartment.getDpartmentLocation.locations' => function ($query) {
            $query->withTrashed();
        },
        'getContractLocationDepartment.getDpartmentLocation.departments' => function ($query) {
            $query->withTrashed();
        }, 
        'savingTypes' => function ($query) {
                $query->withTrashed();
        },
        'parentChild.saving_record'
        ])
        // ->where(function ($q){
        //     return $q->where(['savings.approval_status' => STATUS_ACTIVE]);
        // })
        ->when($supplierid, function ($query, $supplierid) {
            return $query->where(['savings.supplier_id' => $supplierid, 'savings.archive' => STATUS_INACTIVE, 'savings.is_draft' => STATUS_INACTIVE]);
        })
        ->when($contractId, function ($query, $contractId) {
            return $query->where(['savings.contract_id' => $contractId, 'savings.archive' => STATUS_INACTIVE, 'savings.is_draft' => STATUS_INACTIVE]);
        })
        ->when($search, function ($query, $search) use ($columns) {
                
                    return $query->where(function ($subquery) use ($columns, $search) {
                        foreach ($columns as $column) {
                            if ($column == 'savings.category_id') {
                                $subquery->orwhereHas('category', function ($q) use ($search) {
                                    $q->where('value', "LIKE", '%' . $search . '%')->withTrashed();
                                });
                            } else if ($column == 'savings.sub_category_id') {
                                $subquery->orwhereHas('subcategory', function ($q) use ($search) {
                                    $q->where('value', "LIKE", '%' . $search . '%')->withTrashed();
                                });
                            } else if ($column == 'savings.created_by') {
                                $subquery->orwhereHas('user', function ($q) use ($search) {
                                    $q->where('name', "LIKE", '%' . $search . '%')->withTrashed();
                                });
                            } else if ($column == 'savings.supplier_id') {
                                $subquery->orwhereHas('supplier', function ($q) use ($search) {
                                    $q->where('name', "LIKE", '%' . $search . '%');
                                });
                            } else if ($column == 'savings.currency_id') {
                                $subquery->orwhereHas('currency', function ($q) use ($search) {
                                    $q->where('currency', "LIKE", '%' . $search . '%')->withTrashed();
                                });
                            } else {
                                $subquery->orWhere($column, "LIKE", '%' . $search . '%');
                            }
                        }
                    });
        });

            /** Filters */
        if (isset($location) && !empty($location)) {
                // $list->whereHas('getContractLocationDepartment.getDpartmentLocation.locations', function ($q) use ($location) {
                //     return $q->where('id', '=', $location);
                // });
                $list->whereHas('getContractLocationDepartment.getDpartmentLocation', function ($subquery) use ($location) {
                    $subquery->withTrashed()->whereHas('locations', function ($qq) use ($location) {
                        return $qq->whereIn('id', $location)->withTrashed();
                    });
                });
        }
            
        if (isset($department) && !empty($department)) {
                // $list->whereHas('getContractLocationDepartment.getDpartmentLocation.departments', function ($q) use ($department) {
                //     return $q->where('id', '=', $department);
                // });
                $list->whereHas('getContractLocationDepartment.getDpartmentLocation', function ($subquery) use ($department) {
                    $subquery->withTrashed()->whereHas('departments', function ($qq) use ($department) {
                        return $qq->whereIn('id', $department)->withTrashed();
                    });
                });
        }
            
        if (isset($category) && !empty($category)) {
            // $list->whereHas('category', function ($q) use ($category) {
            //     return $q->where('id', $category)->withTrashed();
            // });
            $list->whereHas('category', function ($q) use ($category) {
                return $q->whereIn('id', $category)->withTrashed();
            });
        }
            
        if (isset($subCategory) && !empty($subCategory)) {
            // $list->whereHas('subcategory', function ($q) use ($subCategory) {
            //     return $q->where('id', $subCategory)->withTrashed();
            // });
            $list->whereHas('subcategory', function ($q) use ($subCategory) {
                return $q->whereIn('id', $subCategory)->withTrashed();
            });
        }

        if (isset($status) && !empty($status)) {
            /***********Multiple with draft**************/
            if (count($status) === 1) {
                if (in_array(STATUS_DRAFT, $status)) {
                    $list->where('is_draft', STATUS_ACTIVE);
                } else {
                    $list->whereIn('status', $status)->where('is_draft', STATUS_INACTIVE);
                }
            } else {
                if (in_array(STATUS_DRAFT, $status)) {
                    $list->where(function ($query) use ($status) {
                        $query->where('is_draft', STATUS_ACTIVE)
                            ->orWhereIn('status', $status);
                    });
                } else {
                    $list->whereIn('status', $status)->where('is_draft', STATUS_INACTIVE);
                }
            }
            /***********Multiple with draft**************/
        }else{
            $list->where('is_draft', STATUS_INACTIVE);
        }

        if (isset($approvalStatus) && !empty($approvalStatus)) {
            $list->whereIn('savings.approval_status', $approvalStatus);
        } else {
            $list->where(['savings.approval_status' => STATUS_ACTIVE]);
        }

            
        if (isset($startDate) && !empty($startDate)) {
                $startDate = Carbon::parse($startDate);
                $list->whereDate('start_date', '>=', $startDate);
        }

        if (isset($endDate) && !empty($endDate)) {
                $endDate = Carbon::parse($endDate);
                $list->whereDate('end_date', '<=', $endDate);
        }
        if (isset($createdBy) && !empty($createdBy)) {
            // $list->whereHas('user', function ($q) use ($createdBy) {
            //     return $q->where('id', $createdBy)->withTrashed();
            // });
            $list->whereHas('user', function ($q) use ($createdBy) {
                return $q->whereIn('id', $createdBy)->withTrashed();
            });
        }

        if (isset($archive) && !empty($archive)) {
                $list->whereIn('archive', $archive);
        }

        if (isset($saving_type) && !empty($saving_type)) {
            //$list->where('saving_type', $saving_type);
            $list->whereIn('saving_type', $saving_type);
        }

        if (isset($currency) && !empty($currency)) {
            $list->whereHas('currency', function ($q) use ($currency) {
                return $q->whereIn('id', $currency)->withTrashed();
            });
        }

        if (!empty($supplierIds)) {
            $list->whereIn('supplier_id', $supplierIds);
        }
        
            /** End Filters */
            
            
            /** Sorting */
        switch ($sortBy) {
            case 'category':
                    $list->orderBy(Category::select('value')->whereColumn('categories.id', 'savings.category_id'), $sortOrder);
                break;
                
            case 'subCategory':
                    $list->orderBy(SubCategory::select('value')->whereColumn('sub_categories.id', 'savings.sub_category_id'), $sortOrder);
                break;
                    
            case 'created_by':
                    $list->orderBy(User::select('name')->whereColumn('users.id', 'savings.created_by'), $sortOrder);
                break;
                    
            case 'supplier_name':
                    $list->orderBy(Supplier::select('name')->whereColumn('suppliers.id', 'savings.supplier_id'), $sortOrder);
                break;

            case 'currency':
                    $list->orderBy(CurrencyRate::select('currency')->whereColumn('currency_rates.id', 'savings.currency_id'), $sortOrder);
                break;

            case 'saving_type':
                $list->orderBy(SavingType::select('name')->whereColumn('saving_types.id', 'savings.saving_type'), $sortOrder);
                break;

            case 'contract_name':
                    $list->orderBy(Contract::select('title')->whereColumn('contracts.id', 'savings.contract_id'), $sortOrder);
                break;

            case 'status':
                    $list->orderByRaw("CASE 
                                    WHEN is_draft = 1 THEN 0
                                    ELSE 1 END, 
                                    CASE 
                                    WHEN status = 0 THEN 'Not Started'
                                    WHEN status = 1 THEN 'In Progress'
                                    WHEN status = 2 THEN 'Completed'
                                    ELSE 'Cancelled' END $sortOrder");
                break;

            default:
                    $list->orderBy($sortBy, $sortOrder);
                break;
        }
            /** End Sorting */

            $totalItems = $list->count();
            $savingsData = $list->offset(($page - 1) * $perPage)->limit($perPage)->get();
            
            foreach ($savingsData as $saving){
                $arr = [
                    'id' => $saving->id,
                    'field_area' => FIELD_AREA_SUMMARY_CARD,
                    'module_type' => MODULE_TYPE_SAVINGS,
                ];
    
                $saving['custom_fields'] = CustomField::customFields($arr);
            }
           
            return [
                'savingsData' => $savingsData,
                'total' => $totalItems,
                'totalPage' => ceil($totalItems / $perPage),
                'page' => $page > ceil($totalItems / $perPage) ? 1 : $page,
                'message' => $totalItems > 0 ? 'success' : 'Saving lists are not found!!',
            ];
    }


    // public static function getSavingListFilter(){
    //     try {

    //         $status = [
    //             ['value' => STATUS_DRAFT, 'title' => 'Draft'],
    //             ['value' => STATUS_CANCELLED, 'title' => 'Cancelled'],
    //             ['value' => STATUS_COMPLETED, 'title' => 'Completed'],
    //             ['value' => STATUS_IN_PROGRESS, 'title' => 'In Progress'],
    //             ['value' => STATUS_NOT_STARTED, 'title' => 'Not Started'],
    //         ];

    //         $archiveStatus = [
    //             ['value' => STATUS_INACTIVE, 'title' => 'Active'],
    //             ['value' => STATUS_ACTIVE, 'title' => 'Archived'],
    //         ];

    //         $response['departments'] = Department::where('status', STATUS_ACTIVE)->orderBy('department_name', 'asc')->withTrashed()->get();
    //         $response['departmentsLocations'] = DepartmentsLocation::where('status', STATUS_ACTIVE)->withTrashed()->get();
    //         $response['createdBy'] = User::select('id', 'name')->get();
    //         $response['locations'] = Location::where('status', STATUS_ACTIVE)->orderBy('location_name', 'asc')->withTrashed()->get();
    //         $response['categories'] = Category::orderBy('value', 'asc')->withTrashed()->get();
    //         $response['SubCategory'] = SubCategory::orderBy('value', 'asc')->withTrashed()->get();
    //         $response['status'] = $status;
    //         $response['currency'] = CurrencyRate::getActiveCurrency();
    //         $response['suppliers'] = Supplier::where('status', STATUS_ACTIVE)->orderBy('name', 'asc')->get();
    //         $response['contracts'] = Contract::where('status', STATUS_ACTIVE)->orderBy('title', 'asc')->get();
    //         $response['SupplierContract'] = SupplierContract::get();
    //         $response['archiveStatus'] = $archiveStatus;

    //         // SupplierContract
    //         return $response;

               
    //     } catch (\Throwable $th) {
            
    //         $response['message'] = $th->getMessage();
    //         $response['statusCode'] = '500';
    //         return $response;
    //     }
        
    // }

    public static function getSavingListFilter()
    {
        try {
            $status = [
                ['value' => STATUS_DRAFT, 'title' => 'Draft'],
                ['value' => STATUS_CANCELLED, 'title' => 'Cancelled'],
                ['value' => STATUS_COMPLETED, 'title' => 'Completed'],
                ['value' => STATUS_IN_PROGRESS, 'title' => 'In Progress'],
                ['value' => STATUS_NOT_STARTED, 'title' => 'Not Started'],
            ];

            $approvalStatus = [
                ['value' => STATUS_SAVING_REJECTED, 'title' => 'Rejected'],
                ['value' => STATUS_SAVING_UNAPPROVED, 'title' => 'Unapproved'],  
            ];

            $archiveStatus = [
                ['value' => STATUS_INACTIVE, 'title' => 'Active'],
                ['value' => STATUS_ACTIVE, 'title' => 'Archived'],
            ];
            $response['departments']  = DB::table('departments AS d')
            ->select('d.id', 'd.department_name')
            ->leftJoin('department_location AS dl', 'd.id', '=', 'dl.department_id')
            ->leftJoin('saving_location_departments AS sld', 'sld.department_location_id', '=', 'dl.id')
            ->leftJoin('savings AS s', 's.id', '=', 'sld.saving_id')
            ->whereNull('d.deleted_at')
            ->orWhere('d.status', DEPARTMENT_ACTIVE)
            ->whereNotNull('sld.saving_id')
            ->whereNull('sld.deleted_at')
            ->orderBy('d.department_name', 'ASC')
            ->distinct()
            ->get();
            
            $response['departmentsLocations'] = DepartmentsLocation::where('status', STATUS_ACTIVE)->withTrashed()->get();
            $response['createdBy'] = User::select('id', 'name')->where('hidden_user', STATUS_INACTIVE)->get();
            $response['locations']  = DB::table('locations AS l')
            ->select('l.id', 'l.location_name')
            ->leftJoin('department_location AS dl', 'l.id', '=', 'dl.location_id')
            ->leftJoin('saving_location_departments AS sld', 'sld.department_location_id', '=', 'dl.id')
            ->leftJoin('savings AS s', 's.id', '=', 'sld.saving_id')
            ->whereNull('l.deleted_at')
            ->orWhere('l.status', LOCATION_ACTIVE)
            ->whereNotNull('sld.saving_id')
            ->whereNull('sld.deleted_at')
            ->orderBy('l.location_name', 'ASC')
            ->distinct()
            ->get();

            $response['categories']=  DB::table('categories')
            ->leftJoin('savings', 'categories.id', '=', 'savings.category_id')
            ->whereNull('categories.deleted_at')
            ->orWhere('categories.status', STATUS_ACTIVE)
            ->whereNotNull('savings.category_id')
            ->select('categories.id', 'categories.value')
            ->distinct()
            ->orderBy('categories.value')
            ->get();
            $response['SubCategory'] = DB::table('sub_categories')
            ->leftJoin('savings', 'sub_categories.id', '=', 'savings.sub_category_id')
            ->whereNull('sub_categories.deleted_at')
            ->orWhere('sub_categories.status', STATUS_ACTIVE)
            ->whereNotNull('savings.sub_category_id')
            ->select('sub_categories.id', 'sub_categories.value', 'sub_categories.code')
            ->distinct()
            ->orderBy('sub_categories.value')
            ->get();
            $response['status'] = $status;
            $response['currency'] = DB::table('currency_rates')
            ->leftJoin('savings', 'currency_rates.id', '=', 'savings.currency_id')
            ->whereNull('currency_rates.deleted_at')
            ->where(function($query) {
                $query->where('currency_rates.status', STATUS_ACTIVE)
                      ->orWhereNotNull('savings.currency_id');
            })
            ->select('currency_rates.id', 'currency_rates.currency', 'currency_rates.currency_symbol', 'currency_rates.country', 'currency_rates.rate', 'currency_rates.currency_name')
            ->distinct()
            ->orderBy('currency_rates.currency_name')
            ->get();
            $response['suppliers'] = Supplier::where('status', STATUS_ACTIVE)->orderBy('name', 'asc')->get();
            $response['contracts'] = Contract::where('status', STATUS_ACTIVE)->orderBy('title', 'asc')->get();
            $response['savingType'] = SavingType::where('status', STATUS_ACTIVE)->orderBy('name', 'asc')->get();
            $response['SupplierContract'] = SupplierContract::get();
            $response['archiveStatus'] = $archiveStatus;
            $response['activeUsers'] = User::getUserList();
            $response['approvalStatus'] = $approvalStatus;
            $response['approverUsers'] = User::getApproverUserListOfSavings();
            $response['savingType'] = SavingType::getsavingTypes();
            $response['savingFilterList'] = self::selectRaw("id, name, CONCAT(id, ' - ', name) as id_name")
                                            ->where('approval_status', STATUS_ACTIVE)
                                            ->where('is_draft', STATUS_INACTIVE)
                                            ->where('archive', STATUS_INACTIVE)
                                            ->get();

            // SupplierContract


            $response['custom_fields'] = CustomField::activeCustomFields([
                'field_area' => FIELD_AREA_SUMMARY_CARD,
                'module_type' => MODULE_TYPE_SAVINGS,
            ]);

            return $response;
        } catch (\Throwable $th) {
            $response['message'] = $th->getMessage();
            $response['statusCode'] = '500';
            return $response;
        }
    }

    public static function getSavingListFilterCreateUpdate()
    {
        try {
            $status = [
                ['value' => STATUS_DRAFT, 'title' => 'Draft'],
                ['value' => STATUS_CANCELLED, 'title' => 'Cancelled'],
                ['value' => STATUS_COMPLETED, 'title' => 'Completed'],
                ['value' => STATUS_IN_PROGRESS, 'title' => 'In Progress'],
                ['value' => STATUS_NOT_STARTED, 'title' => 'Not Started'], 
            ];

            $archiveStatus = [
                ['value' => STATUS_INACTIVE, 'title' => 'Active'],
                ['value' => STATUS_ACTIVE, 'title' => 'Archived'],
            ];

            $response['departments'] = Department::where('status', STATUS_ACTIVE)->orderBy('department_name', 'asc')->get();
            $response['departmentsLocations'] = DepartmentsLocation::where('status', STATUS_ACTIVE)->get();
            $response['createdBy'] = User::select('id', 'name')->get();
            $response['locations'] = Location::where('status', STATUS_ACTIVE)->orderBy('location_name', 'asc')->get();
            $response['categories'] = Category::orderBy('value', 'asc')->get();
            $response['SubCategory'] = SubCategory::orderBy('value', 'asc')->get();
            $response['status'] = $status;
            // $response['currency'] = CurrencyRate::getActiveCurrency();
            $response['currency'] = self::getCurrencyCreateUpdate();
            $response['suppliers'] = Supplier::where('status', STATUS_ACTIVE)->orderBy('name', 'asc')->get();
            $response['contracts'] = Contract::where('status', STATUS_ACTIVE)->orderBy('title', 'asc')->get();
            $response['SupplierContract'] = SupplierContract::get();
            $response['archiveStatus'] = $archiveStatus;
            $response['activeUsers'] = User::getUserList();
            $response['approverUsers'] = User::getApproverUserListOfSavings();
            $response['savingType'] = SavingType::getsavingTypes();
            
            // SupplierContract
            return $response;
        } catch (\Throwable $th) {
            $response['message'] = $th->getMessage();
            $response['statusCode'] = '500';
            return $response;
        }
    }


    public static function getDraftSavingData($userId)
    {
        $getDraftSaving = self::with(['user', 'currency' => function ($query) {
            $query->withTrashed();
        }, 'supplier', 'contract', 'category', 'subcategory', 'getContractLocationDepartment',
        'getContractLocationDepartment.getDpartmentLocation' => function ($query) {
            $query->withTrashed();
        },
        'getContractLocationDepartment.getDpartmentLocation.locations' => function ($query) {
            $query->withTrashed();
        },
        'getContractLocationDepartment.getDpartmentLocation.departments' => function ($query) {
            $query->withTrashed();
        },
        'milestones',
        'savingTypes' => function ($query) {
            $query->withTrashed();
        }])->where(['created_by' => $userId, 'is_draft' => STATUS_ACTIVE])->first();
        return $getDraftSaving;
    }

    public static function addNewSaving($postArr)
    {

        try {
            if (isset($postArr['id']) && !empty($postArr['id'])) {
                $response = self::updateSaving($postArr);
                return $response;
            } else {
                DB::beginTransaction();
                $saveSavingData = self::create($postArr);
                
                if ($saveSavingData->exists) {

                    /************** Save saving with locations & departments*************/

                    if (isset($postArr['location']) && isset($postArr['department'])) {
                        $locations = $postArr['location'];
                        $departments = $postArr['department'];
                        $getDepLocIds = DepartmentsLocation::select('id')->where('location_id', $locations)->where('department_id', $departments)->get();
                        foreach ($getDepLocIds as $key => $val) {
                            $dataWhere = array(
                                'saving_id' => $saveSavingData->id,
                                'department_location_id' => $val->id
                            );

                            $dataUpdate = array(
                                'saving_id' => $saveSavingData->id,
                                'department_location_id' => $val->id
                            );
                            SavingLocationDepartment::updateOrCreate($dataWhere, $dataUpdate);
                        }
                    }
                    /************** Save saving with locations & departments*************/

                    DB::commit();
                    $response['saving_id'] = $saveSavingData->id;
                    $response['message'] = 'Saving data has been added successfully.';
                    $response['statusCode'] = '200';
                    return $response;
                } else {
                    $response['message'] = 'Saving data has not been added.';
                    $response['statusCode'] = '500';
                    return $response;
                }
            }
        } catch (\Throwable $th) {
            DB::rollback();
            $response['message'] = $th->getMessage();
            $response['statusCode'] = '500';
            return $response;
        }
    }


    public static function getSavingById($id)
    {
        $savingData = self::with(['user','milestones',
        'currency'=>function ($query) {
            $query->withTrashed();
        }, 'supplier', 'supplier.supplierImage', 'contract',
        'category'=>function ($query) {
            $query->withTrashed();
        }, 'subcategory'=>function ($query) {
            $query->withTrashed();
        }, 'getContractLocationDepartment',
        'getContractLocationDepartment.getDpartmentLocation' => function ($query) {
            $query->withTrashed();
        },
        'getContractLocationDepartment.getDpartmentLocation.locations' => function ($query) {
            $query->withTrashed();
        },
        'getContractLocationDepartment.getDpartmentLocation.departments' => function ($query) {
            $query->withTrashed();
        },            
        'savingTypes' => function ($query) {
                $query->withTrashed();
        }, 'savingApprover' => function ($query) {
                $query->whereNull('reject_reason');
        }, 
        'parentChild.saving_record'

        ])->find($id);

        $arr = [
            'id' => $id,
            'field_area' => FIELD_AREA_SUMMARY_CARD,
            'module_type' => MODULE_TYPE_SAVINGS,
        ];

        $savingData['custom_fields'] = CustomField::customFields($arr);

        $getCompanySettingData = CompanySetting::select('currency')->first();
        $getCompanySettingCurrency = isset($getCompanySettingData) && $getCompanySettingData->currency != null ? $getCompanySettingData->currency : null;

        if (isset($savingData) && $savingData->currency_id == $getCompanySettingCurrency) {
            $savingData->base_currency = 1;
        } else if (isset($savingData)) {
            $savingData->base_currency = 0;
        }

        // please do not use trash on the below options array
        $optionData['currency'] = CurrencyRate::getAllCurrencies(); // getActiveCurrency()
        $optionData['savingType'] = SavingType::getsavingTypes();
        $optionData['category'] = Category::getcategories();
        $optionData['subcategory'] = SubCategory::getsubcategories();
        $optionData['departments'] = Department::where('status', STATUS_ACTIVE)->orderBy('department_name', 'asc')->get();
        $optionData['departmentsLocations'] = DepartmentsLocation::where('status', STATUS_ACTIVE)->get();
        $optionData['locations'] = Location::where('status', STATUS_ACTIVE)->orderBy('location_name', 'asc')->get();
        $optionData['suppliers'] = Supplier::getSupplierList();
        $optionData['contracts'] = Contract::where('status', STATUS_ACTIVE)->orderBy('title', 'asc')->get();
        $optionData['supplierContracts'] = SupplierContract::get();
        $savingData['savingLogs'] = SavingApproval::with(['linkedSaving', 'linkedUser', 'linkedUser.userImage', 'submittedBy.userImage'])->where('saving_id', $id)->where('status', '<>', STATUS_INACTIVE)->orderBy('id', 'DESC')->get();

        return [
            'savingsData' => $savingData,
            'optionData' => $optionData
        ];
    }

    public static function updateSavingFelds($postArr)
    {
        DB::beginTransaction();
        try {
            $getSavingById = self::find($postArr['id']);
            $updateSavingData = $getSavingById->update($postArr);

            if ($updateSavingData) {
                DB::commit();
                $response['message'] = 'Saving data has been updated successfully.';
                $response['statusCode'] = '200';
                return $response;
            } else {
                $response['message'] = 'Saving data has not not been updated.';
                $response['statusCode'] = '500';
                return $response;
            }
        } catch (\Throwable $th) {
            DB::rollback();
            $response['message'] = $th->getMessage();
            $response['statusCode'] = '500';
            return $response;
        }
    }


    public static function updateSaving($postArr)
    {
        DB::beginTransaction();
        try {
            $getSavingById = self::with('user', 'currency', 'category', 'supplier', 'contract', 'subcategory', 'getContractLocationDepartment', 'getContractLocationDepartment.getDpartmentLocation', 'getContractLocationDepartment.getDpartmentLocation.locations', 'getContractLocationDepartment.getDpartmentLocation.departments', 'savingTypes')->find($postArr['id']);

            $getSavingById->fill($postArr);

            $updatedFieldsList = self::saveUpdatedModel($getSavingById, $postArr, SUBMODULE_SAVING);

            if ($updatedFieldsList) {

                // update custom fields
                if (!empty($postArr['custom_fields'])) {
                    self::updateCustomFieldsAndLogUpdates($postArr['custom_fields'], SUBMODULE_SAVING, $postArr['id']);
                }

                DB::commit();
                self::saveSavingWithLocationsAndDepartments($postArr);
                self::saveParentChild($postArr);
                $response['saving_id'] = $postArr['id'];
                $response['message'] = 'Saving data has been updated successfully.';
                $response['statusCode'] = '200';
                return $response;
            } else {
                $response['message'] = 'Saving data has not not been updated.';
                $response['statusCode'] = '500';
                return $response;
            }
        } catch (\Throwable $th) {
            DB::rollback();
            $response['message'] = $th->getMessage();
            $response['statusCode'] = '500';
            return $response;
        }
    }


    public static function saveUpdatedModel($getSavingById, $postArr, $submodule)
    {

      
            $getModuleId = ActivityLogSubmodule::where('submodule_name', $submodule)->first();
            $i = 0;

        foreach ($getSavingById->getDirty() as $field => $value) {
                $oldValue = $getSavingById->getOriginal($field);

                $updatedFields[$i] = [
                    'module_id' => $getModuleId->module_id,
                    'submodule_id' => $getModuleId->id,
                    'entity_id' => $postArr['id'],
                    'user_id' => Auth::user()->id,
                    'column_name' => self::changeColumn($field),
                    'old_value' => $oldValue,
                    'new_value' => $value,
                ];
                
                if ($field === 'currency_id') {
                    $savingCurrencyModel = $getSavingById->currency;
                    $updatedFields[$i]['old_value'] = $savingCurrencyModel ? $savingCurrencyModel->getOriginal()['currency'] : null;
                    $getCurrency = CurrencyRate::where('id', $value)->select('currency')->first();
                    $updatedFields[$i]['new_value'] = $getCurrency ? $getCurrency->currency : null;
                } else if ($field === 'saving_type') {
                    $savingTypeModel = $getSavingById->savingTypes;
        
                    $updatedFields[$i]['old_value'] = $savingTypeModel ? $savingTypeModel->getOriginal()['name'] : null;
                    $getSavingType = SavingType::where('id', $value)->select('name')->first();
                    $updatedFields[$i]['new_value'] = $getSavingType ? $getSavingType->name : null;
                } else if ($field === 'category_id') {
                    $savingCategoryModel = $getSavingById->category;
                    $updatedFields[$i]['old_value'] = $savingCategoryModel ? $savingCategoryModel->getOriginal()['value'] : null;
                    $getCategory = Category::where('id', $value)->select('value')->first();
                    $updatedFields[$i]['new_value'] = $getCategory ? $getCategory->value : null;
                } else if ($field === 'sub_category_id') {
                    $savingSubCatModel = $getSavingById->subcategory;
                    $updatedFields[$i]['old_value'] = $savingSubCatModel ? $savingSubCatModel->getOriginal()['value'] : null;
                    $getSubCat = SubCategory::where('id', $value)->select('value')->first();
                    $updatedFields[$i]['new_value'] = $getSubCat ? $getSubCat->value : null;
                } else if ($field === 'supplier_id') {
                    $savingSupplierModel = $getSavingById->supplier;
                    $updatedFields[$i]['old_value'] = $savingSupplierModel ? $savingSupplierModel->getOriginal()['name'] : null;
                    $getSupplier = Supplier::where('id', $value)->select('name')->first();
                    $updatedFields[$i]['new_value'] = $getSupplier ? $getSupplier->name : null;
                } else if ($field === 'contract_id') {
                    $savingContractModel = $getSavingById->contract;
                    $updatedFields[$i]['old_value'] = $savingContractModel ? $savingContractModel->getOriginal()['title'] : null;
                    $getContract = Contract::where('id', $value)->select('title')->first();
                    $updatedFields[$i]['new_value'] = $getContract ? $getContract->title : null;
                } else if ($field === 'status') {
                    switch ($oldValue) {
                        case STATUS_IN_PROGRESS:
                            $oldValue = 'In Progress';
                            break;
                        case STATUS_COMPLETED:
                            $oldValue = 'Completed';
                            break;
                        case STATUS_CANCELLED:
                            $oldValue = 'Cancelled';
                            break;
                        default:
                            $oldValue = 'Not Started';
                    }

                    switch ($value) {
                        case STATUS_IN_PROGRESS:
                            $value = 'In Progress';
                            break;
                        case STATUS_COMPLETED:
                            $value = 'Completed';
                            break;
                        case STATUS_CANCELLED:
                            $value = 'Cancelled';
                            break;
                        default:
                            $value = 'Not Started';
                    }


                    $updatedFields[$i]['old_value'] = $oldValue;
                    $updatedFields[$i]['new_value'] = $value;
                } else if ($field === 'is_draft') {
                    $oldValue = $oldValue == STATUS_ACTIVE ? "Draft" : "Active";
                    $value = $value == STATUS_ACTIVE ? "Draft" : "Active";
                    $updatedFields[$i]['old_value'] = $oldValue;
                    $updatedFields[$i]['new_value'] = $value;
                } else if ($field === 'archive') {
                    $oldValue = $oldValue == STATUS_ACTIVE ? "Archived" : "Non Archived";
                    $value = $value == STATUS_ACTIVE ? "Archived" : "Non Archived";
                    $updatedFields[$i]['old_value'] = $oldValue;
                    $updatedFields[$i]['new_value'] = $value;
                } else {
                    $updatedFields[$i]['old_value'] = $oldValue;
                    $updatedFields[$i]['new_value'] = $value;
                }

                ActivityLogEntity::create($updatedFields[$i]);
                $i++;
        }
           
            // Save the updated model
            $updateSaving = $getSavingById->save();
            return $updateSaving;
    }

    public static function saveSavingWithLocationsAndDepartments($postArr)
    {
        $getModuleId = ActivityLogSubmodule::where('submodule_name', SUBMODULE_SAVING)->first();
        /************** Save contract with locations & departments*************/

        if (isset($postArr['location']) && isset($postArr['department'])) {
            $locations = $postArr['location'];
            $departments = $postArr['department'];
            $getDepLocIds = DepartmentsLocation::select('id')->where('location_id', $locations)->where('department_id', $departments)->get();

            $changes = [];

            $existingRecords = SavingLocationDepartment::where('saving_id', $postArr['id'])
                ->get();

            // Store old values before deletion
            foreach ($existingRecords as $existingRecord) {
                $changes[] = [
                    'old_values' => $existingRecord->id,
                    'new_values' => [],  // No new values since the record will be deleted
                ];
            }

            // Delete existing records
            SavingLocationDepartment::whereNotIn('department_location_id', $getDepLocIds)->where('saving_id', $postArr['id'])
                ->delete();

            // Create or update records
            foreach ($getDepLocIds as $key => $val) {
                $dataWhere = [
                    'saving_id' => $postArr['id'],
                    'department_location_id' => $val->id,
                ];

                $dataUpdate = [
                    'saving_id' => $postArr['id'],
                    'department_location_id' => $val->id,
                ];

                // Update or create the record
                $contractDepLocData = SavingLocationDepartment::updateOrCreate($dataWhere, $dataUpdate);

                // Store new values
                $changes[] = [
                    'old_values' => [],
                    'new_values' => $contractDepLocData->id,
                ];
            }

            $combinedOldValues = [];
            $combinedNewValues = [];


            foreach ($changes as $item) {
                if (is_array($item["old_values"]) && !empty($item["old_values"])) {
                    $combinedOldValues = array_merge($combinedOldValues, $item["old_values"]);
                } elseif (!empty($item["old_values"])) {
                    $combinedOldValues[] = $item["old_values"];
                }

                if (is_array($item["new_values"]) && !empty($item["new_values"])) {
                    $combinedNewValues = array_merge($combinedNewValues, $item["new_values"]);
                } elseif (!empty($item["new_values"])) {
                    $combinedNewValues[] = $item["new_values"];
                }
            }

            if (!empty(array_diff($combinedOldValues, $combinedNewValues)) ||
               !empty(array_diff($combinedNewValues, $combinedOldValues))) {
                $getDepLocOldData = SavingLocationDepartment::with([
                    'getDpartmentLocation' => function ($query) {
                        $query->withTrashed();
                    },
                    'getDpartmentLocation.locations' => function ($query) {
                        $query->withTrashed();
                    }, 'getDpartmentLocation.departments' => function ($query) {
                        $query->withTrashed();
                    }])->whereIn('id', $combinedOldValues)->withTrashed()->get();
               
                $getDepLocNewData = SavingLocationDepartment::with(['getDpartmentLocation', 'getDpartmentLocation.locations', 'getDpartmentLocation.departments'])->whereIn('id', $combinedNewValues)->get();
                $oldLoc = [];
                $oldDep = [];
                foreach ($getDepLocOldData as $record) {
                    $oldLoc[] = $record->getDpartmentLocation->locations->location_name;
                    $oldDep[] = $record->getDpartmentLocation->departments->department_name;
                }
                

                foreach ($getDepLocNewData as $record) {
                    $newLoc[] = $record->getDpartmentLocation->locations->location_name;
                    $newDep[] = $record->getDpartmentLocation->departments->department_name;
                }

                $oldLocations = !empty($oldLoc) ? implode(', ', array_unique($oldLoc)) : null;
                $oldDepartments = !empty($oldDep) ? implode(', ', array_unique($oldDep)) : null;

                $newLocations = !empty($newLoc) ? implode(', ', array_unique($newLoc)) : null;
                $newDepartments = !empty($newDep) ? implode(', ', array_unique($newDep)) : null;

                $updatedFieldsForLoc =
                    [
                    'module_id' => $getModuleId->module_id,
                    'submodule_id' => $getModuleId->id,
                    'entity_id' => $postArr['id'],
                    'user_id' => Auth::user()->id,
                    'column_name' => 'locations',
                    'old_value' => $oldLocations ? $oldLocations : null,
                    'new_value' => $newLocations ? $newLocations : null,
                    ];
                $updatedFieldsForDep =
                    [
                    'module_id' => $getModuleId->module_id,
                    'submodule_id' => $getModuleId->id,
                    'entity_id' => $postArr['id'],
                    'user_id' => Auth::user()->id,
                    'column_name' => 'departments',
                    'old_value' => $oldDepartments ? $oldDepartments : null,
                    'new_value' => $newDepartments ? $newDepartments : null,
                    ];

                if (!empty($oldLoc) && !empty(array_diff($oldLoc, $newLoc)) || !empty(array_diff($newLoc, $oldLoc))) {
                    ActivityLogEntity::create($updatedFieldsForLoc);
                }

                if (!empty($oldDep) && !empty(array_diff($oldDep, $newDep)) || !empty(array_diff($newDep, $oldDep))) {
                    ActivityLogEntity::create($updatedFieldsForDep);
                }
            }
        }

        /************** Save contract with locations & departments*************/
    }

    public static function saveParentChild($postArr)
    {

        $savingsId = $postArr['id'];
        $parentId = $postArr['parent_child_id'] ?? null;
        $relationType = $postArr['relation_type'] ?? null;

        $getModuleId = ActivityLogSubmodule::where('submodule_name', SUBMODULE_SAVING)->firstOrFail();

        $dataWhere = [
            'record_id' => $savingsId,
            'record_type' => RECORD_TYPE_SAVINGS,
        ];

        // Prepare base log data
        $logBase = [
            'module_id' => $getModuleId->module_id,
            'submodule_id' => $getModuleId->id,
            'entity_id' => $savingsId,
            'user_id' => auth()->id(),
        ];

        // Delete relation if both parent ID and relation type are empty
        if (empty($parentId) && empty($relationType)) {
            $data = ParentChildRelation::where($dataWhere)->first();
    
            if(!empty($data)){
                $name = Saving::find($data->parent_child_id)?->name ?? 'N/A';

                ActivityLogEntity::create(array_merge($logBase, [
                    'column_name' => 'Parent/Child of Contract',
                    'old_value' => $name,
                    'new_value' => null,
                ]));
    
                ActivityLogEntity::create(array_merge($logBase, [
                    'column_name' => 'Contract Parent/Child Relationship',
                    'old_value' => $data->relation_type == 1 ? 'Parent' : 'Child',
                    'new_value' => null,
                ]));
                
                $data->delete();
            }

            return;
        }

        // Validate required fields
        if ($relationType && empty($parentId)) {
            throw new \Exception('Please select the parent or child');
        }

        if ($parentId && empty($relationType)) {
            throw new \Exception('Please select the relation type');
        }

        $dataUpdate = [
            'parent_child_id' => $parentId,
            'relation_type' => $relationType,
        ];

        $record = ParentChildRelation::firstOrCreate($dataWhere, $dataUpdate);

        if ($record->wasRecentlyCreated) {
            // Log creation
            $savingName = Saving::find($parentId)?->name ?? 'N/A';

            ActivityLogEntity::create(array_merge($logBase, [
                'column_name' => 'Parent/Child of Saving',
                'old_value' => null,
                'new_value' => $savingName,
            ]));

            ActivityLogEntity::create(array_merge($logBase, [
                'column_name' => 'Saving Parent/Child Relationship',
                'old_value' => null,
                'new_value' => $relationType == 1 ? 'Parent' : 'Child',
            ]));
        } else {
            // Check changes and log updates
            $record->fill($dataUpdate);
            $dirty = $record->getDirty();

            foreach ($dirty as $field => $newValue) {
                $oldValue = $record->getOriginal($field);
                $columnName = '';
                $logOld = $oldValue;
                $logNew = $newValue;

                if ($field === 'relation_type') {
                    $columnName = 'Saving Parent/Child Relationship';
                    $logOld = $oldValue == 1 ? 'Parent' : 'Child';
                    $logNew = $newValue == 1 ? 'Parent' : 'Child';
                } elseif ($field === 'parent_child_id') {
                    $columnName = 'Parent/Child of Saving';
                    $logOld = Saving::find($oldValue)?->name ?? 'N/A';
                    $logNew = Saving::find($newValue)?->name ?? 'N/A';
                }

                ActivityLogEntity::create(array_merge($logBase, [
                    'column_name' => $columnName,
                    'old_value' => $logOld,
                    'new_value' => $logNew,
                ]));
            }

            $record->save();
        }

    }

    public static function updateStatus($postArr)
    {
        DB::beginTransaction();
        try {
            if (isset($postArr['archive_status'])) {
                $status = $postArr['archive_status'];
                $postArr['archive'] = $status;
            }

            if (isset($postArr['is_draft'])) {
                $draft_status = $postArr['is_draft'];
                $postArr['is_draft'] = $draft_status;
            }
           
            $getSavingById = self::findOrFail($postArr['id']);
            $getSavingById->fill($postArr);

            $updatedFieldsList = self::saveUpdatedModel($getSavingById, $postArr, SUBMODULE_SAVING);

            DB::commit();
            if ($updatedFieldsList) {
                $response['message'] = 'Status of saving has been changed successfully.';
                $response['statusCode'] = '200';
                return $response;
            } else {
                $response['message'] = 'Status of saving has not been changed.';
                $response['statusCode'] = '500';
                return $response;
            }
        } catch (\Throwable $th) {
            DB::rollback();
            $response['message'] = $th->getMessage();
            $response['statusCode'] = '500';
            return $response;
        }
    }

    public static function updateSavingStatus($postArr, $url)
    {
        DB::beginTransaction();
        try {
            $currentDate = Carbon::now();
            $getSavingById = self::find($postArr['id']);
            $postArr['start_date'] = Carbon::parse($postArr['start_date']);
            $postArr['end_date'] = Carbon::parse($postArr['end_date']);
            $postArr['is_draft'] = STATUS_INACTIVE;
            $updateSavingData = $getSavingById->update($postArr);
            
            if ($updateSavingData) {

                $data = [
                    'user_id' => $getSavingById->created_by,
                    'saving_id' => $getSavingById->id,
                ];

                SavingsOwner::addOwner($data);

                if (isset($postArr['approver_id']) && !empty($postArr['approver_id'])) {
                    $saveSavingForApproval = SavingApproval::create(
                        [
                            'approver_id' => $postArr['approver_id'],
                            'saving_id' => $postArr['id']
                        ]
                    );

                    if ($saveSavingForApproval->exists) {
                        $getSavingById->approval_status = SAVING_STATUS_UNAPPROVED;
                        $getSavingById->save();
                        $getApproverData = User::find($postArr['approver_id']);
                        $message = "Approval Notification";
                        $submessage = Auth::user()->name.' has requested '.$getApproverData->name.' to approved saving ' . $getSavingById->name;

                        $submessageTranslate = '{user_name} has requested {approver_name} to approved saving {saving_name}';
                        $values = json_encode([
                            'user_name' => Auth::user()->name,
                            'approver_name' => $getApproverData->name,
                            'saving_name' => $getSavingById->name
                        ]);
                        $inputNotification = [
                            'message' => $message,
                            'submessage' => $submessage,
                            'user_id' => $getApproverData->id,
                            'category' => SAVINGS_NOTIFICATIONS,
                            'module_id' => $getSavingById->id,
                            'data' => $values,
                            'sub_message_translation' => $submessageTranslate
                        ];


                        broadcast(new \App\Events\TestNotification($message, $submessage, $getApproverData->id, null, $currentDate, false, SAVINGS_NOTIFICATIONS, $getSavingById->id));
                        app('App\Http\Controllers\Api\NotificationController')->storeNotificationApprovals($inputNotification);


                        $approverEmailArr = [
                            'email' => $getApproverData->email,
                            'type' => 'saving-approval',
                            'username' => $getApproverData->name,
                            'saving_name' => $getSavingById->name,
                            'link' => $url . '/savings/view/' . $getSavingById->id
                        ];
                        $result = self::saveEmail($approverEmailArr);
                    }
                }

                DB::commit();
                $response['message'] = 'Savings record has been created successfully.';
                $response['statusCode'] = '200';
                return $response;
            } else {
                $response['message'] = 'Saving data has not not been updated.';
                $response['statusCode'] = '500';
                return $response;
            }
        } catch (\Throwable $th) {
            DB::rollback();
            $response['message'] = $th->getMessage();
            $response['statusCode'] = '500';
            return $response;
        }
    }


    public static function getSavingExportList()
    {

        $list = self::with(['milestones', 'user' => function ($query) {
            $query->withTrashed();
        }, 'currency' => function ($query) {
            $query->withTrashed();
        }, 'supplier', 'supplier.supplierImage', 'contract', 'category' => function ($query) {
            $query->withTrashed();
        }, 'subcategory' => function ($query) {
            $query->withTrashed();
        }, 'getContractLocationDepartment',
        'getContractLocationDepartment.getDpartmentLocation' => function ($query) {
            $query->withTrashed();
        },
        'getContractLocationDepartment.getDpartmentLocation.locations' => function ($query) {
            $query->withTrashed();
        },
        'getContractLocationDepartment.getDpartmentLocation.departments' => function ($query) {
            $query->withTrashed();
        }, 'contract',
        'savingTypes' => function ($query) {
                $query->withTrashed();
        },
        'parentChild.saving_record'
        ])->where(['archive' => STATUS_INACTIVE, 'is_draft' => STATUS_INACTIVE])->where('status', '!=', STATUS_CANCELLED)->orderBy('name', 'ASC');
            
        $totalItems = $list->count();
        $savingsData = $list->get();
        
        foreach ($savingsData as $saving){
            $arr = [
                'id' => $saving->id,
                'field_area' => FIELD_AREA_SUMMARY_CARD,
                'module_type' => MODULE_TYPE_SAVINGS,
            ];

            $saving['custom_fields'] = CustomField::customFields($arr);

            foreach ($saving->milestones as $milestone){
                $arr = [
                    'id' => $milestone->id,
                    'field_area' => FIELD_AREA_SAVINGS_MILESTONE,
                    'module_type' => MODULE_TYPE_SAVINGS,
                ];
    
                $milestone['custom_fields'] = CustomField::customFields($arr);
            }
        }

        return [
            'savingsData' => $savingsData,
            'message' => $totalItems > 0 ? 'success' : 'Savings export list are not found!!',
        ];
    }

    public static function getSavingLogs($postArr)
    {

        $response = ActivityLogEntity::getLogList($postArr, MODULE_ENTITY_SAVING);
        $response['submodule_id'] = ActivityLogSubmodule::where('submodule_name',SUBMODULE_SAVINGS)->first();
        return $response;
    }

    public static function getSavingsSubmodules($moduleType)
    {
        try {
            $getModuleId = ActivityLogModule::where('module_name', $moduleType)->first();
            $response = ActivityLogSubmodule::where('module_id', $getModuleId->id)->get();
        } catch (\Throwable $th) {
            $response['message'] = $th->getMessage();
            $response['statusCode'] = '500';
        }
        return $response;
    }


    public static function getMetricData($postArr)
    {
        try {
            $total_realised_saving = self::totalRealisedSaving($postArr);
            $savings['total_realised_saving'] = $total_realised_saving;
           
            $total_realised_saving_perc = self::totalRealisedSavingPerc($postArr);
            $savings['total_realised_saving_perc'] = $total_realised_saving_perc;

            $total_project_saving = self::totalProjectedSaving($postArr);
            $savings['total_project_saving'] = $total_project_saving;

            $total_project_saving_perc = self::totalProjectedSavingPerc($postArr);
            $savings['total_project_saving_perc'] = $total_project_saving_perc;

            
            $baseCurrencySymbol = null;
            $getBaseCurrency = CompanySetting::with('currency')->first();
            if (isset($getBaseCurrency) && !empty($getBaseCurrency)) {
                $getBaseCurrencySymbol = CurrencyRate::find($getBaseCurrency->currency);
                $baseCurrencySymbol = isset($getBaseCurrencySymbol) && !empty($getBaseCurrencySymbol) ? $getBaseCurrencySymbol->currency_symbol : null;
            }
            
            $savings['currency_symbol'] = $baseCurrencySymbol;

            return [
                'matricData' => $savings,
                'statusCode' => '200'
            ];
        } catch (\Throwable $th) {
            $response['message'] = $th->getMessage();
            $response['statusCode'] = '500';
            return $response;
        }
    }

    public static function totalRealisedSaving($postArr)
    {
       
        $categoryArr = $postArr['category'] ?? [];
        $subcategoryArr = $postArr['sub_category'] ?? [];
        $location = $postArr['location'] ?? [];
        $department = $postArr['department'] ?? [];
        $createdByArr = $postArr['created_by'] ?? [];
        $savingType = $postArr['saving_type'] ?? [];
        $supplierIds = $postArr['supplier_ids'] ?? [];

        return SavingMilestone::selectRaw('SUM(CASE WHEN saving_milestones.total_realised_saving IS NOT NULL THEN saving_milestones.total_realised_saving / (CASE WHEN s.currency_id = cs.currency THEN 1 ELSE saving_milestones.currency_rate END) ELSE 0 END) as total_realised_savings')
        ->join('savings as s', 'saving_milestones.saving_id', '=', 's.id')
        ->leftJoin('company_settings as cs', function ($join) {
            $join->on('s.currency_id', '=', 'cs.currency')->whereNull('cs.deleted_at');
        })
        ->where('s.archive', STATUS_INACTIVE)
        ->where('s.is_draft', STATUS_INACTIVE)
        ->whereNull('s.deleted_at')

            ->whereHas('savings', function ($query) {
                $query->where(['archive' => STATUS_INACTIVE, 'is_draft' => STATUS_INACTIVE])->where('status', '<>', STATUS_CANCELLED);
            })

            ->when(!empty($savingType), function ($query) use ($savingType) {
                $query->whereIn('saving_type', $savingType);
            })
            
            ->when(!empty($categoryArr), function ($query) use ($categoryArr) {
                $query->whereHas('savings.category', function ($subquery) use ($categoryArr) {
                    $subquery->whereIn('id', $categoryArr);
                });
            })
            ->when(!empty($subcategoryArr), function ($query) use ($subcategoryArr) {
                $query->whereHas('savings.subcategory', function ($subquery) use ($subcategoryArr) {
                    $subquery->whereIn('id', $subcategoryArr);
                });
            })
            ->when(!empty($location), function ($query) use ($location) {
                // $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation.locations', function($subquery) use ($location) {
                //     $subquery->where('id', $location);
                // });
                $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation', function ($subquery) use ($location) {
                    $subquery->withTrashed()->whereHas('locations', function ($qq) use ($location) {
                        $qq->whereIn('id', $location)->withTrashed();
                    });
                });
            })
            ->when(!empty($department), function ($query) use ($department) {
                // $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation.departments', function($subquery) use ($department) {
                //     $subquery->where('id', $department);
                // });
                $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation', function ($subquery) use ($department) {
                    $subquery->withTrashed()->whereHas('departments', function ($qq) use ($department) {
                        $qq->whereIn('id', $department)->withTrashed();
                    });
                });
            })
            ->when(!empty($createdByArr), function ($query) use ($createdByArr) {
                $query->whereHas('savings.user', function ($subquery) use ($createdByArr) {
                    $subquery->whereIn('id', $createdByArr);
                });
            })

            ->when(isset($postArr['start_date']) && isset($postArr['end_date']), function ($query) use ($postArr) {
                $startDate = Carbon::parse($postArr['start_date']);
                $endDate = Carbon::parse($postArr['end_date']);

                // $query->whereHas('savings', function($subquery) use ($startDate, $endDate) {
                //     $subquery->whereDate('start_date', '>=',  $startDate)->whereDate('end_date', '<=',  $endDate);
                // });
                $query->whereBetween('saving_milestones.end_date', [$startDate, $endDate]);
            })
            ->when(isset($postArr['start_date']) && !isset($postArr['end_date']), function ($query) use ($postArr) {
                $startDate = Carbon::parse($postArr['start_date']);
                // $query->whereHas('savings', function($subquery) use ($startDate) {
                //     $subquery->whereDate('start_date', '>=', $startDate);
                // });
                $query->whereDate('saving_milestones.end_date', '>=', $startDate);
            })
            ->when(isset($postArr['end_date']) && !isset($postArr['start_date']), function ($query) use ($postArr) {
                $endDate = Carbon::parse($postArr['end_date']);
                // $query->whereHas('savings', function($subquery) use ($endDate) {
                //     $subquery->whereDate('end_date', '<=', $endDate);
                // });
                $query->whereDate('saving_milestones.end_date', '<=', $endDate);
            })
            ->when(isset($postArr['saving_project_id']) && !empty($postArr['saving_project_id']), function ($query) use ($postArr) {
                $query->where('s.id', $postArr['saving_project_id']);
            })
            ->when(!empty($supplierIds), function ($query) use ($supplierIds) {
                $query->whereHas('savings', function ($query) use ($supplierIds) {
                    $query->whereIn('supplier_id', $supplierIds);
                });
            })
            ->value('total_realised_savings');
    }

    // public static function totalRealisedSavingPerc($postArr)
    // {
    //     $categoryArr = $postArr['category'] ?? [];
    //     $subcategoryArr = $postArr['sub_category'] ?? [];
    //     $location = $postArr['location'] ?? null;
    //     $department = $postArr['department'] ?? null;
    //     $createdByArr = $postArr['created_by'] ?? [];
    //     $savingType = $postArr['saving_type'] ?? null;

    //     $totalQuery = SavingMilestone::selectRaw('
    //             SUM(CASE WHEN saving_milestones.total_realised_saving IS NOT NULL THEN (saving_milestones.total_realised_saving / (CASE WHEN s.currency_id = cs.currency THEN 1 ELSE saving_milestones.currency_rate END)) ELSE 0 END) as cal_total_realised_saving,
    //             SUM(CASE WHEN saving_milestones.total_realised_saving_base_line IS NOT NULL THEN (saving_milestones.total_realised_saving_base_line / (CASE WHEN s.currency_id = cs.currency THEN 1 ELSE saving_milestones.currency_rate END)) ELSE 0 END) as cal_total_base_line_spend
    //         ')
    //     ->join('savings as s', 'saving_milestones.saving_id', '=', 's.id')
    //     ->leftJoin('company_settings as cs', function ($join) {
    //         $join->on('s.currency_id', '=', 'cs.currency')->whereNull('cs.deleted_at');
    //     })
    //         ->where('s.archive', STATUS_INACTIVE)
    //         ->where('s.is_draft', STATUS_INACTIVE)
    //         ->whereNull('s.deleted_at')

    //         ->whereHas('savings', function ($query) {
    //             $query->where(['archive' => STATUS_INACTIVE, 'is_draft' => STATUS_INACTIVE])->where('status', '<>', STATUS_CANCELLED);
    //         })

    //         ->when(!empty($savingType), function ($query) use ($savingType) {
    //             $query->where('saving_type', $savingType);
    //         })

    //         ->when(!empty($categoryArr), function ($query) use ($categoryArr) {
    //             $query->whereHas('savings.category', function ($subquery) use ($categoryArr) {
    //                 $subquery->whereIn('id', $categoryArr);
    //             });
    //         })
    //         ->when(!empty($subcategoryArr), function ($query) use ($subcategoryArr) {
    //             $query->whereHas('savings.subcategory', function ($subquery) use ($subcategoryArr) {
    //                 $subquery->whereIn('id', $subcategoryArr);
    //             });
    //         })
    //         ->when(!empty($location), function ($query) use ($location) {
    //             // $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation.locations', function($subquery) use ($location) {
    //             //     $subquery->where('id', $location);
    //             // });
    //             $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation', function ($subquery) use ($location) {
    //                 $subquery->withTrashed()->whereHas('locations', function ($qq) use ($location) {
    //                     $qq->where('id', $location)->withTrashed();
    //                 });
    //             });
    //         })
    //         ->when(!empty($department), function ($query) use ($department) {
    //             // $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation.departments', function($subquery) use ($department) {
    //             //     $subquery->where('id', $department);
    //             // });
    //             $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation', function ($subquery) use ($department) {
    //                 $subquery->withTrashed()->whereHas('departments', function ($qq) use ($department) {
    //                     $qq->where('id', $department)->withTrashed();
    //                 });
    //             });
    //         })
    //         ->when(!empty($createdByArr), function ($query) use ($createdByArr) {
    //             $query->whereHas('savings.user', function ($subquery) use ($createdByArr) {
    //                 $subquery->whereIn('id', $createdByArr);
    //             });
    //         })

    //         ->when(isset($postArr['start_date']) && isset($postArr['end_date']), function ($query) use ($postArr) {
    //             $startDate = Carbon::parse($postArr['start_date']);
    //             $endDate = Carbon::parse($postArr['end_date']);

    //             // $query->whereHas('savings', function($subquery) use ($startDate, $endDate) {
    //             //     $subquery->whereDate('start_date', '>=',  $startDate)->whereDate('end_date', '<=',  $endDate);
    //             // });
    //             $query->whereBetween('saving_milestones.end_date', [$startDate, $endDate]);
    //         })
    //         ->when(isset($postArr['start_date']) && !isset($postArr['end_date']), function ($query) use ($postArr) {
    //             $startDate = Carbon::parse($postArr['start_date']);
    //             // $query->whereHas('savings', function($subquery) use ($startDate) {
    //             //     $subquery->whereDate('start_date', '>=', $startDate);
    //             // });
    //             $query->whereDate('saving_milestones.end_date', '>=', $startDate);
    //         })
    //         ->when(isset($postArr['end_date']) && !isset($postArr['start_date']), function ($query) use ($postArr) {
    //             $endDate = Carbon::parse($postArr['end_date']);
    //             // $query->whereHas('savings', function($subquery) use ($endDate) {
    //             //     $subquery->whereDate('end_date', '<=', $endDate);
    //             // });
    //             $query->whereDate('saving_milestones.end_date', '<=', $endDate);
    //         })
    //         ->first();

    //     $total_realised_saving_perc = 0;
    //     if ($totalQuery->cal_total_base_line_spend != 0) {
    //         $total_realised_saving_perc = ($totalQuery->cal_total_realised_saving / $totalQuery->cal_total_base_line_spend) * 100;
    //         //$total_realised_saving_perc = $totalQuery->cal_total_base_line_spend/($totalQuery->cal_total_realised_saving);
    //     }

    //     return $total_realised_saving_perc;
    // }



    public static function totalRealisedSavingPerc($postArr)
    {
        $categoryArr = $postArr['category'] ?? [];
        $subcategoryArr = $postArr['sub_category'] ?? [];
        $location = $postArr['location'] ?? [];
        $department = $postArr['department'] ?? [];
        $createdByArr = $postArr['created_by'] ?? [];
        $savingType = $postArr['saving_type'] ?? [];
        $supplierIds = $postArr['supplier_ids'] ?? [];

        $totalQuery = SavingMilestone::selectRaw('
            AVG(CASE 
                WHEN saving_milestones.total_realised_saving_perc IS NOT NULL 
                THEN saving_milestones.total_realised_saving_perc 
                ELSE null 
            END) as cal_total_realised_saving_perc
        ')
        ->join('savings as s', 'saving_milestones.saving_id', '=', 's.id')
        ->leftJoin('company_settings as cs', function ($join) {
            $join->on('s.currency_id', '=', 'cs.currency')->whereNull('cs.deleted_at');
        })
            ->where('s.archive', STATUS_INACTIVE)
            ->where('s.is_draft', STATUS_INACTIVE)
            ->whereNull('s.deleted_at')

            ->whereHas('savings', function ($query) {
                $query->where(['archive' => STATUS_INACTIVE, 'is_draft' => STATUS_INACTIVE])->where('status', '<>', STATUS_CANCELLED);
            })

            ->when(!empty($savingType), function ($query) use ($savingType) {
                $query->whereIn('saving_type', $savingType);
            })

            ->when(!empty($categoryArr), function ($query) use ($categoryArr) {
                $query->whereHas('savings.category', function ($subquery) use ($categoryArr) {
                    $subquery->whereIn('id', $categoryArr);
                });
            })
            ->when(!empty($subcategoryArr), function ($query) use ($subcategoryArr) {
                $query->whereHas('savings.subcategory', function ($subquery) use ($subcategoryArr) {
                    $subquery->whereIn('id', $subcategoryArr);
                });
            })
            ->when(!empty($location), function ($query) use ($location) {
                // $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation.locations', function($subquery) use ($location) {
                //     $subquery->where('id', $location);
                // });
                $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation', function ($subquery) use ($location) {
                    $subquery->withTrashed()->whereHas('locations', function ($qq) use ($location) {
                        $qq->whereIn('id', $location)->withTrashed();
                    });
                });
            })
            ->when(!empty($department), function ($query) use ($department) {
                // $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation.departments', function($subquery) use ($department) {
                //     $subquery->where('id', $department);
                // });
                $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation', function ($subquery) use ($department) {
                    $subquery->withTrashed()->whereHas('departments', function ($qq) use ($department) {
                        $qq->whereIn('id', $department)->withTrashed();
                    });
                });
            })
            ->when(!empty($createdByArr), function ($query) use ($createdByArr) {
                $query->whereHas('savings.user', function ($subquery) use ($createdByArr) {
                    $subquery->whereIn('id', $createdByArr);
                });
            })

            ->when(isset($postArr['start_date']) && isset($postArr['end_date']), function ($query) use ($postArr) {
                $startDate = Carbon::parse($postArr['start_date']);
                $endDate = Carbon::parse($postArr['end_date']);

                // $query->whereHas('savings', function($subquery) use ($startDate, $endDate) {
                //     $subquery->whereDate('start_date', '>=',  $startDate)->whereDate('end_date', '<=',  $endDate);
                // });
                $query->whereBetween('saving_milestones.end_date', [$startDate, $endDate]);
            })
            ->when(isset($postArr['start_date']) && !isset($postArr['end_date']), function ($query) use ($postArr) {
                $startDate = Carbon::parse($postArr['start_date']);
                // $query->whereHas('savings', function($subquery) use ($startDate) {
                //     $subquery->whereDate('start_date', '>=', $startDate);
                // });
                $query->whereDate('saving_milestones.end_date', '>=', $startDate);
            })
            ->when(isset($postArr['end_date']) && !isset($postArr['start_date']), function ($query) use ($postArr) {
                $endDate = Carbon::parse($postArr['end_date']);
                // $query->whereHas('savings', function($subquery) use ($endDate) {
                //     $subquery->whereDate('end_date', '<=', $endDate);
                // });
                $query->whereDate('saving_milestones.end_date', '<=', $endDate);
            })
            ->when(isset($postArr['saving_project_id']) && !empty($postArr['saving_project_id']), function ($query) use ($postArr) {
                $query->where('s.id', $postArr['saving_project_id']);
            })
            ->when(!empty($supplierIds), function ($query) use ($supplierIds) {
                $query->whereHas('savings', function ($query) use ($supplierIds) {
                    $query->whereIn('supplier_id', $supplierIds);
                });
            })
            ->first();


            $total_realised_saving_perc = $totalQuery->cal_total_realised_saving_perc;

        return $total_realised_saving_perc;
    }



    // public static function totalProjectedSaving($postArr)
    // {
    //     $categoryArr = $postArr['category'] ?? [];
    //     $subcategoryArr = $postArr['sub_category'] ?? [];
    //     $location = $postArr['location'] ?? null;
    //     $department = $postArr['department'] ?? null;
    //     $createdByArr = $postArr['created_by'] ?? [];
    //     $savingType = $postArr['saving_type'] ?? null;
    //     $totalQuery = SavingMilestone::selectRaw(
    //         'SUM(CASE WHEN saving_milestones.total_project_saving IS NOT NULL THEN saving_milestones.total_project_saving / (CASE WHEN s.currency_id = cs.currency THEN 1 ELSE saving_milestones.currency_rate END) ELSE 0 END) as cal_total_project_saving,
    //          SUM(CASE WHEN saving_milestones.total_realised_saving IS NOT NULL THEN (saving_milestones.total_realised_saving / (CASE WHEN s.currency_id = cs.currency THEN 1 ELSE saving_milestones.currency_rate END)) ELSE 0 END) as cal_total_realised_saving'
    //     )
    //         ->join('savings as s', 'saving_milestones.saving_id', '=', 's.id')
    //         ->leftJoin('company_settings as cs', function ($join) {
    //             $join->on('s.currency_id', '=', 'cs.currency')->whereNull('cs.deleted_at');
    //         })
    //         ->where('s.archive', STATUS_INACTIVE)
    //         ->where('s.is_draft', STATUS_INACTIVE)
    //         ->whereNull('s.deleted_at')

    //         ->whereHas('savings', function ($query) {
    //             $query->where(['archive' => STATUS_INACTIVE, 'is_draft' => STATUS_INACTIVE])->where('status', '<>', STATUS_CANCELLED);
    //         })

    //         ->when(!empty($savingType), function ($query) use ($savingType) {
    //             $query->where('saving_type', $savingType);
    //         })

    //         ->when(!empty($categoryArr), function ($query) use ($categoryArr) {
    //             $query->whereHas('savings.category', function ($subquery) use ($categoryArr) {
    //                 $subquery->whereIn('id', $categoryArr);
    //             });
    //         })
    //         ->when(!empty($subcategoryArr), function ($query) use ($subcategoryArr) {
    //             $query->whereHas('savings.subcategory', function ($subquery) use ($subcategoryArr) {
    //                 $subquery->whereIn('id', $subcategoryArr);
    //             });
    //         })
    //         ->when(!empty($location), function ($query) use ($location) {
    //             // $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation.locations', function($subquery) use ($location) {
    //             //     $subquery->where('id', $location);
    //             // });
    //             $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation', function ($subquery) use ($location) {
    //                 $subquery->withTrashed()->whereHas('locations', function ($qq) use ($location) {
    //                     $qq->where('id', $location)->withTrashed();
    //                 });
    //             });
    //         })
    //         ->when(!empty($department), function ($query) use ($department) {
    //             // $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation.departments', function($subquery) use ($department) {
    //             //     $subquery->where('id', $department);
    //             // });
    //             $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation', function ($subquery) use ($department) {
    //                 $subquery->withTrashed()->whereHas('departments', function ($qq) use ($department) {
    //                     $qq->where('id', $department)->withTrashed();
    //                 });
    //             });
    //         })
    //         ->when(!empty($createdByArr), function ($query) use ($createdByArr) {
    //             $query->whereHas('savings.user', function ($subquery) use ($createdByArr) {
    //                 $subquery->whereIn('id', $createdByArr);
    //             });
    //         })

    //         ->when(isset($postArr['start_date']) && isset($postArr['end_date']), function ($query) use ($postArr) {
    //             $startDate = Carbon::parse($postArr['start_date']);
    //             $endDate = Carbon::parse($postArr['end_date']);

    //             // $query->whereHas('savings', function($subquery) use ($startDate, $endDate) {
    //             //     $subquery->whereDate('start_date', '>=',  $startDate)->whereDate('end_date', '<=',  $endDate);
    //             // });
    //             $query->whereBetween('saving_milestones.end_date', [$startDate, $endDate]);
    //         })
    //         ->when(isset($postArr['start_date']) && !isset($postArr['end_date']), function ($query) use ($postArr) {
    //             $startDate = Carbon::parse($postArr['start_date']);
    //             // $query->whereHas('savings', function($subquery) use ($startDate) {
    //             //     $subquery->whereDate('start_date', '>=', $startDate);
    //             // });
    //             $query->whereDate('saving_milestones.end_date', '>=', $startDate);
    //         })
    //         ->when(isset($postArr['end_date']) && !isset($postArr['start_date']), function ($query) use ($postArr) {
    //             $endDate = Carbon::parse($postArr['end_date']);
    //             // $query->whereHas('savings', function($subquery) use ($endDate) {
    //             //     $subquery->whereDate('end_date', '<=', $endDate);
    //             // });
    //             $query->whereDate('saving_milestones.end_date', '<=', $endDate);
    //         })
    //         ->first();

    //         $total_projected_saving = ($totalQuery->cal_total_project_saving - $totalQuery->cal_total_realised_saving);

    //         return $total_projected_saving;
    // }


    public static function totalProjectedSaving($postArr)
    {
        $categoryArr = $postArr['category'] ?? [];
        $subcategoryArr = $postArr['sub_category'] ?? [];
        $location = $postArr['location'] ?? [];
        $department = $postArr['department'] ?? [];
        $createdByArr = $postArr['created_by'] ?? [];
        $savingType = $postArr['saving_type'] ?? [];
        $supplierIds = $postArr['supplier_ids'] ?? [];

        $totalQuery = SavingMilestone::selectRaw(
            'SUM(CASE WHEN saving_milestones.total_project_saving IS NOT NULL THEN saving_milestones.total_project_saving / (CASE WHEN s.currency_id = cs.currency THEN 1 ELSE saving_milestones.currency_rate END) ELSE 0 END) as cal_total_project_saving'
        )
            ->join('savings as s', 'saving_milestones.saving_id', '=', 's.id')
            ->leftJoin('company_settings as cs', function ($join) {
                $join->on('s.currency_id', '=', 'cs.currency')->whereNull('cs.deleted_at');
            })
            ->where('s.archive', STATUS_INACTIVE)
            ->where('s.is_draft', STATUS_INACTIVE)
            ->whereNull('s.deleted_at')
            ->where('saving_milestones.status', '<>', 2) // Exclude saving_milestones with status 2

            ->whereHas('savings', function ($query) {
                $query->where(['archive' => STATUS_INACTIVE, 'is_draft' => STATUS_INACTIVE])->where('status', '<>', STATUS_CANCELLED);
            })

            ->when(!empty($savingType), function ($query) use ($savingType) {
                $query->whereIn('saving_type', $savingType);
            })

            ->when(!empty($categoryArr), function ($query) use ($categoryArr) {
                $query->whereHas('savings.category', function ($subquery) use ($categoryArr) {
                    $subquery->whereIn('id', $categoryArr);
                });
            })
            ->when(!empty($subcategoryArr), function ($query) use ($subcategoryArr) {
                $query->whereHas('savings.subcategory', function ($subquery) use ($subcategoryArr) {
                    $subquery->whereIn('id', $subcategoryArr);
                });
            })
            ->when(!empty($location), function ($query) use ($location) {
                // $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation.locations', function($subquery) use ($location) {
                //     $subquery->where('id', $location);
                // });
                $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation', function ($subquery) use ($location) {
                    $subquery->withTrashed()->whereHas('locations', function ($qq) use ($location) {
                        $qq->whereIn('id', $location)->withTrashed();
                    });
                });
            })
            ->when(!empty($department), function ($query) use ($department) {
                // $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation.departments', function($subquery) use ($department) {
                //     $subquery->where('id', $department);
                // });
                $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation', function ($subquery) use ($department) {
                    $subquery->withTrashed()->whereHas('departments', function ($qq) use ($department) {
                        $qq->whereIn('id', $department)->withTrashed();
                    });
                });
            })
            ->when(!empty($createdByArr), function ($query) use ($createdByArr) {
                $query->whereHas('savings.user', function ($subquery) use ($createdByArr) {
                    $subquery->whereIn('id', $createdByArr);
                });
            })

            ->when(isset($postArr['start_date']) && isset($postArr['end_date']), function ($query) use ($postArr) {
                $startDate = Carbon::parse($postArr['start_date']);
                $endDate = Carbon::parse($postArr['end_date']);

                // $query->whereHas('savings', function($subquery) use ($startDate, $endDate) {
                //     $subquery->whereDate('start_date', '>=',  $startDate)->whereDate('end_date', '<=',  $endDate);
                // });
                $query->whereBetween('saving_milestones.end_date', [$startDate, $endDate]);
            })
            ->when(isset($postArr['start_date']) && !isset($postArr['end_date']), function ($query) use ($postArr) {
                $startDate = Carbon::parse($postArr['start_date']);
                // $query->whereHas('savings', function($subquery) use ($startDate) {
                //     $subquery->whereDate('start_date', '>=', $startDate);
                // });
                $query->whereDate('saving_milestones.end_date', '>=', $startDate);
            })
            ->when(isset($postArr['end_date']) && !isset($postArr['start_date']), function ($query) use ($postArr) {
                $endDate = Carbon::parse($postArr['end_date']);
                // $query->whereHas('savings', function($subquery) use ($endDate) {
                //     $subquery->whereDate('end_date', '<=', $endDate);
                // });
                $query->whereDate('saving_milestones.end_date', '<=', $endDate);
            })
            ->when(isset($postArr['saving_project_id']) && !empty($postArr['saving_project_id']), function ($query) use ($postArr) {
                $query->where('s.id', $postArr['saving_project_id']);
            })
            ->when(!empty($supplierIds), function ($query) use ($supplierIds) {
                $query->whereHas('savings', function ($query) use ($supplierIds) {
                    $query->whereIn('supplier_id', $supplierIds);
                });
            })
            ->first();

        $total_projected_saving = $totalQuery->cal_total_project_saving;

        return $total_projected_saving;
    }



    // public static function totalProjectedSavingPerc($postArr)
    // {
    //     $categoryArr = $postArr['category'] ?? [];
    //     $subcategoryArr = $postArr['sub_category'] ?? [];
    //     $location = $postArr['location'] ?? null;
    //     $department = $postArr['department'] ?? null;
    //     $createdByArr = $postArr['created_by'] ?? [];
    //     $savingType = $postArr['saving_type'] ?? null;

    //     $totalQuery = SavingMilestone::selectRaw('
    //             SUM(CASE WHEN saving_milestones.total_project_saving IS NOT NULL THEN (saving_milestones.total_project_saving / (CASE WHEN s.currency_id = cs.currency THEN 1 ELSE saving_milestones.currency_rate END)) ELSE 0 END) as cal_total_project_saving,
    //             SUM(CASE WHEN saving_milestones.total_realised_saving IS NOT NULL THEN (saving_milestones.total_realised_saving / (CASE WHEN s.currency_id = cs.currency THEN 1 ELSE saving_milestones.currency_rate END)) ELSE 0 END) as cal_total_realised_saving,
    //             SUM(CASE WHEN saving_milestones.base_line_spend IS NOT NULL THEN (saving_milestones.base_line_spend / (CASE WHEN s.currency_id = cs.currency THEN 1 ELSE saving_milestones.currency_rate END)) ELSE 0 END) as cal_total_base_line_spend
    //         ')
    //         ->join('savings as s', 'saving_milestones.saving_id', '=', 's.id')
    //         ->leftJoin('company_settings as cs', function ($join) {
    //             $join->on('s.currency_id', '=', 'cs.currency')->whereNull('cs.deleted_at');
    //         })
    //         ->where('s.archive', STATUS_INACTIVE)
    //         ->where('s.is_draft', STATUS_INACTIVE)
    //         ->whereNull('s.deleted_at')

    //         ->whereHas('savings', function ($query) {
    //             $query->where(['archive' => STATUS_INACTIVE, 'is_draft' => STATUS_INACTIVE])->where('status', '<>', STATUS_CANCELLED);
    //         })

    //         ->when(!empty($savingType), function ($query) use ($savingType) {
    //             $query->where('saving_type', $savingType);
    //         })

    //         ->when(!empty($categoryArr), function ($query) use ($categoryArr) {
    //             $query->whereHas('savings.category', function ($subquery) use ($categoryArr) {
    //                 $subquery->whereIn('id', $categoryArr);
    //             });
    //         })
    //         ->when(!empty($subcategoryArr), function ($query) use ($subcategoryArr) {
    //             $query->whereHas('savings.subcategory', function ($subquery) use ($subcategoryArr) {
    //                 $subquery->whereIn('id', $subcategoryArr);
    //             });
    //         })
    //         ->when(!empty($location), function ($query) use ($location) {
    //             // $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation.locations', function($subquery) use ($location) {
    //             //     $subquery->where('id', $location);
    //             // });
    //             $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation', function ($subquery) use ($location) {
    //                 $subquery->withTrashed()->whereHas('locations', function ($qq) use ($location) {
    //                     $qq->where('id', $location)->withTrashed();
    //                 });
    //             });
    //         })
    //         ->when(!empty($department), function ($query) use ($department) {
    //             // $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation.departments', function($subquery) use ($department) {
    //             //     $subquery->where('id', $department);
    //             // });
    //             $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation', function ($subquery) use ($department) {
    //                 $subquery->withTrashed()->whereHas('departments', function ($qq) use ($department) {
    //                     $qq->where('id', $department)->withTrashed();
    //                 });
    //             });
    //         })
    //         ->when(!empty($createdByArr), function ($query) use ($createdByArr) {
    //             $query->whereHas('savings.user', function ($subquery) use ($createdByArr) {
    //                 $subquery->whereIn('id', $createdByArr);
    //             });
    //         })

    //         ->when(isset($postArr['start_date']) && isset($postArr['end_date']), function ($query) use ($postArr) {
    //             $startDate = Carbon::parse($postArr['start_date']);
    //             $endDate = Carbon::parse($postArr['end_date']);

    //             // $query->whereHas('savings', function($subquery) use ($startDate, $endDate) {
    //             //     $subquery->whereDate('start_date', '>=',  $startDate)->whereDate('end_date', '<=',  $endDate);
    //             // });
    //             $query->whereBetween('saving_milestones.end_date', [$startDate, $endDate]);
    //         })
    //         ->when(isset($postArr['start_date']) && !isset($postArr['end_date']), function ($query) use ($postArr) {
    //             $startDate = Carbon::parse($postArr['start_date']);
    //             // $query->whereHas('savings', function($subquery) use ($startDate) {
    //             //     $subquery->whereDate('start_date', '>=', $startDate);
    //             // });
    //             $query->whereDate('saving_milestones.end_date', '>=', $startDate);
    //         })
    //         ->when(isset($postArr['end_date']) && !isset($postArr['start_date']), function ($query) use ($postArr) {
    //             $endDate = Carbon::parse($postArr['end_date']);
    //             // $query->whereHas('savings', function($subquery) use ($endDate) {
    //             //     $subquery->whereDate('end_date', '<=', $endDate);
    //             // });
    //             $query->whereDate('saving_milestones.end_date', '<=', $endDate);
    //         })
    //         ->first();

    //         $total_project_saving_perc = 0;
    //     if ($totalQuery->cal_total_base_line_spend != 0) {
    //             $total_project_saving_perc = (($totalQuery->cal_total_project_saving - $totalQuery->cal_total_realised_saving) / $totalQuery->cal_total_base_line_spend) * 100;
    //     }

    //         return $total_project_saving_perc;
    // }


    public static function totalProjectedSavingPerc($postArr)
    {
        $categoryArr = $postArr['category'] ?? [];
        $subcategoryArr = $postArr['sub_category'] ?? [];
        $location = $postArr['location'] ?? [];
        $department = $postArr['department'] ?? [];
        $createdByArr = $postArr['created_by'] ?? [];
        $savingType = $postArr['saving_type'] ?? [];
        $supplierIds = $postArr['supplier_ids'] ?? [];

        $totalQuery = SavingMilestone::selectRaw('
            AVG(CASE 
                WHEN saving_milestones.total_project_saving_perc IS NOT NULL 
                THEN saving_milestones.total_project_saving_perc 
                ELSE null 
            END) as cal_total_project_saving_perc
        ')
        ->join('savings as s', 'saving_milestones.saving_id', '=', 's.id')
        ->leftJoin('company_settings as cs', function ($join) {
            $join->on('s.currency_id', '=', 'cs.currency')->whereNull('cs.deleted_at');
        })
            ->where('s.archive', STATUS_INACTIVE)
            ->where('s.is_draft', STATUS_INACTIVE)
            ->whereNull('s.deleted_at')
            ->where('saving_milestones.status', '<>', 2) // Exclude saving_milestones with status 2

            ->whereHas('savings', function ($query) {
                $query->where(['archive' => STATUS_INACTIVE, 'is_draft' => STATUS_INACTIVE])->where('status', '<>', STATUS_CANCELLED);
            })

            ->when(!empty($savingType), function ($query) use ($savingType) {
                $query->whereIn('saving_type', $savingType);
            })

            ->when(!empty($categoryArr), function ($query) use ($categoryArr) {
                $query->whereHas('savings.category', function ($subquery) use ($categoryArr) {
                    $subquery->whereIn('id', $categoryArr);
                });
            })
            ->when(!empty($subcategoryArr), function ($query) use ($subcategoryArr) {
                $query->whereHas('savings.subcategory', function ($subquery) use ($subcategoryArr) {
                    $subquery->whereIn('id', $subcategoryArr);
                });
            })
            ->when(!empty($location), function ($query) use ($location) {
                // $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation.locations', function($subquery) use ($location) {
                //     $subquery->where('id', $location);
                // });
                $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation', function ($subquery) use ($location) {
                    $subquery->withTrashed()->whereHas('locations', function ($qq) use ($location) {
                        $qq->whereIn('id', $location)->withTrashed();
                    });
                });
            })
            ->when(!empty($department), function ($query) use ($department) {
                // $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation.departments', function($subquery) use ($department) {
                //     $subquery->where('id', $department);
                // });
                $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation', function ($subquery) use ($department) {
                    $subquery->withTrashed()->whereHas('departments', function ($qq) use ($department) {
                        $qq->whereIn('id', $department)->withTrashed();
                    });
                });
            })
            ->when(!empty($createdByArr), function ($query) use ($createdByArr) {
                $query->whereHas('savings.user', function ($subquery) use ($createdByArr) {
                    $subquery->whereIn('id', $createdByArr);
                });
            })

            ->when(isset($postArr['start_date']) && isset($postArr['end_date']), function ($query) use ($postArr) {
                $startDate = Carbon::parse($postArr['start_date']);
                $endDate = Carbon::parse($postArr['end_date']);

                // $query->whereHas('savings', function($subquery) use ($startDate, $endDate) {
                //     $subquery->whereDate('start_date', '>=',  $startDate)->whereDate('end_date', '<=',  $endDate);
                // });
                $query->whereBetween('saving_milestones.end_date', [$startDate, $endDate]);
            })
            ->when(isset($postArr['start_date']) && !isset($postArr['end_date']), function ($query) use ($postArr) {
                $startDate = Carbon::parse($postArr['start_date']);
                // $query->whereHas('savings', function($subquery) use ($startDate) {
                //     $subquery->whereDate('start_date', '>=', $startDate);
                // });
                $query->whereDate('saving_milestones.end_date', '>=', $startDate);
            })
            ->when(isset($postArr['end_date']) && !isset($postArr['start_date']), function ($query) use ($postArr) {
                $endDate = Carbon::parse($postArr['end_date']);
                // $query->whereHas('savings', function($subquery) use ($endDate) {
                //     $subquery->whereDate('end_date', '<=', $endDate);
                // });
                $query->whereDate('saving_milestones.end_date', '<=', $endDate);
            })
            ->when(isset($postArr['saving_project_id']) && !empty($postArr['saving_project_id']), function ($query) use ($postArr) {
                $query->where('s.id', $postArr['saving_project_id']);
            })
            ->when(!empty($supplierIds), function ($query) use ($supplierIds) {
                $query->whereHas('savings', function ($query) use ($supplierIds) {
                    $query->whereIn('supplier_id', $supplierIds);
                });
            })
            ->first();

        $total_project_saving_perc = $totalQuery->cal_total_project_saving_perc;

        return $total_project_saving_perc;
    }


    public static function getGraphData($postArr)
    {
        try {
            $monthlySavings = [];

            $categoryArr = $postArr['category'] ?? [];
            $subcategoryArr = $postArr['sub_category'] ?? [];
            $location = $postArr['location'] ?? [];
            $department = $postArr['department'] ?? [];
            $createdByArr = $postArr['created_by'] ?? [];
            $savingType = $postArr['saving_type'] ?? [];
            $supplierIds = $postArr['supplier_ids'] ?? [];

            $previousSixMonth = Carbon::now()->subMonths(6);
            $nextSixMonth = Carbon::now()->addMonths(6);

            if (isset($postArr['start_date']) && !empty($postArr['start_date']) && isset($postArr['end_date']) && !empty($postArr['end_date'])) {
                $previousSixMonth = Carbon::parse($postArr['start_date']);
                $nextSixMonth = Carbon::parse($postArr['end_date']);
            } else if (isset($postArr['start_date']) && !empty($postArr['start_date'])) {
                $previousSixMonth = Carbon::parse($postArr['start_date']);
                $nextSixMonth = Carbon::parse($postArr['start_date'])->addMonths(12); // Get date after 12 months from now
            } else if (isset($postArr['end_date']) && !empty($postArr['end_date']) && empty($postArr['start_date'])) {
                $previousSixMonth = Carbon::parse($postArr['end_date'])->subMonth(12); // Get date previous 12 months from now
                $nextSixMonth = Carbon::parse($postArr['end_date']);
            }

            $baseCurrencySymbol = null;
            $getBaseCurrency = CompanySetting::with('currency')->first();
            if (isset($getBaseCurrency) && !empty($getBaseCurrency)) {
                $getBaseCurrencySymbol = CurrencyRate::find($getBaseCurrency->currency);
                $baseCurrencySymbol = isset($getBaseCurrencySymbol) && !empty($getBaseCurrencySymbol) ? $getBaseCurrencySymbol->currency_symbol : null;
            }

            while ($previousSixMonth <= $nextSixMonth) {
                $month = $previousSixMonth->month;
                $year = $previousSixMonth->year;

                $savings = SavingMilestone::whereMonth('saving_milestones.end_date', $month)->whereYear('saving_milestones.end_date', $year)
                    ->selectRaw('
                        SUM(CASE WHEN saving_milestones.total_project_saving IS NOT NULL THEN saving_milestones.total_project_saving / (CASE WHEN s.currency_id = cs.currency THEN 1 ELSE saving_milestones.currency_rate END) ELSE 0 END) as total_project_saving, 
                        SUM(CASE WHEN saving_milestones.total_realised_saving IS NOT NULL THEN saving_milestones.total_realised_saving / (CASE WHEN s.currency_id = cs.currency THEN 1 ELSE saving_milestones.currency_rate END) ELSE 0 END) as total_realised_saving')
                    ->join('savings as s', 'saving_milestones.saving_id', '=', 's.id')
                    ->leftJoin('company_settings as cs', function ($join) {
                        $join->on('s.currency_id', '=', 'cs.currency')->whereNull('cs.deleted_at');
                    })
                    ->where('s.archive', STATUS_INACTIVE)
                    ->where('s.is_draft', STATUS_INACTIVE)
                    ->where('s.status', '<>', STATUS_CANCELLED)
                    ->whereNull('s.deleted_at')

                    ->when(!empty($categoryArr), function ($query) use ($categoryArr) {
                        $query->whereHas('savings.category', function ($subquery) use ($categoryArr) {
                            $subquery->whereIn('id', $categoryArr)->withTrashed();
                        });
                    })

                    ->when(!empty($savingType), function ($query) use ($savingType) {
                        $query->whereIn('saving_type', $savingType);
                    })

                    ->when(!empty($subcategoryArr), function ($query) use ($subcategoryArr) {
                        $query->whereHas('savings.subcategory', function ($subquery) use ($subcategoryArr) {
                            $subquery->whereIn('id', $subcategoryArr)->withTrashed();
                        });
                    })
                    ->when(!empty($location), function ($query) use ($location) {
                        // $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation.locations', function($subquery) use ($location) {
                        //     $subquery->where('id', $location);
                        // });

                        $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation', function ($subquery) use ($location) {
                            $subquery->withTrashed()->whereHas('locations', function ($qq) use ($location) {
                                $qq->whereIn('id', $location)->withTrashed();
                            });
                        });
                    })
                    ->when(!empty($department), function ($query) use ($department) {
                        // $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation.departments', function($subquery) use ($department) {
                        //     $subquery->where('id', $department);
                        // });

                        $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation', function ($subquery) use ($department) {
                            $subquery->withTrashed()->whereHas('departments', function ($qq) use ($department) {
                                $qq->whereIn('id', $department)->withTrashed();
                            });
                        });
                    })
                    ->when(!empty($createdByArr), function ($query) use ($createdByArr) {
                        $query->whereHas('savings.user', function ($subquery) use ($createdByArr) {
                            $subquery->whereIn('id', $createdByArr)->withTrashed();
                        });
                    })
                    ->when(isset($postArr['saving_project_id']) && !empty($postArr['saving_project_id']), function ($query) use ($postArr) {
                        $query->where('s.id', $postArr['saving_project_id']);
                    })
                    ->when(!empty($supplierIds), function ($query) use ($supplierIds) {
                        $query->whereHas('savings', function ($subquery) use ($supplierIds) {
                            $subquery->whereIn('supplier_id', $supplierIds);
                        });
                    })
                    ->first();

                // If there are no records for the month, set totalSavings to 0
                $monthlySavings[] = [
                    'month_and_year' => $previousSixMonth->format('M-Y'),
                    'total_project_saving' => $savings->total_project_saving ?? 0,
                    'total_realised_saving' => $savings->total_realised_saving ?? 0
                ];

                $previousSixMonth->setDay(1)->addMonth();
            }


            return [
                'savingsData' => $monthlySavings,
                'current_symbol' => $baseCurrencySymbol,
                'statusCode' => '200'
            ];
        } catch (\Throwable $th) {
            $response['message'] = $th->getMessage();
            $response['statusCode'] = '500';
            return $response;
        }
    }


    public static function getGraphDataByCategory($postArr)
    {
        try {
            $categoryArr = $postArr['category'] ?? [];
            $subcategoryArr = $postArr['sub_category'] ?? [];
            $location = $postArr['location'] ?? [];
            $department = $postArr['department'] ?? [];
            $createdByArr = $postArr['created_by'] ?? [];
            $savingType = $postArr['saving_type'] ?? [];
            $supplierIds = $postArr['supplier_ids'] ?? [];

            $currDate = Carbon::now(); // Get current date
            $endDate = Carbon::now()->addMonths(12); // Get date 12 months from now

            if (isset($postArr['start_date']) && !empty($postArr['start_date']) && isset($postArr['end_date']) && !empty($postArr['end_date'])) {
                $currDate = Carbon::parse($postArr['start_date']);
                $endDate = Carbon::parse($postArr['end_date']);
            } else if (isset($postArr['start_date']) && !empty($postArr['start_date'])) {
                $currDate = Carbon::parse($postArr['start_date']);
                $endDate = Carbon::parse($postArr['start_date'])->addMonths(12); // Get date after 12 months from now
            } else if (isset($postArr['end_date']) && !empty($postArr['end_date']) && empty($postArr['start_date'])) {
                $currDate = Carbon::parse($postArr['end_date'])->subMonth(12); // Get date previous 12 months from now
                $endDate = Carbon::parse($postArr['end_date']);
            }
            $list = Category::select('id', 'value')
            ->withCount([
                'savings as total_project_saving' => function ($query) use ($currDate, $endDate) {
                    $query->select(DB::raw('COALESCE(SUM(CASE WHEN saving_milestones.total_project_saving IS NOT NULL THEN saving_milestones.total_project_saving / (CASE WHEN s.currency_id = cs.currency THEN 1 ELSE saving_milestones.currency_rate END) ELSE 0 END), 0)'))
                        ->join('saving_milestones', 'savings.id', '=', 'saving_milestones.saving_id')
                        ->join('savings as s', 'saving_milestones.saving_id', '=', 's.id')
                        ->leftJoin('company_settings as cs', function ($join) {
                            $join->on('s.currency_id', '=', 'cs.currency')->whereNull('cs.deleted_at');
                        })
                        ->whereBetween('saving_milestones.end_date', [$currDate, $endDate])
                        ->where('s.archive', STATUS_INACTIVE)
                        ->where('s.is_draft', STATUS_INACTIVE)
                        ->where('s.status', '<>', STATUS_CANCELLED)
                        ->whereNull('s.deleted_at');
                },
                'savings as total_realised_saving' => function ($query) use ($currDate, $endDate) {
                    $query->select(DB::raw('COALESCE(SUM(CASE WHEN saving_milestones.total_realised_saving IS NOT NULL THEN saving_milestones.total_realised_saving / (CASE WHEN s.currency_id = cs.currency THEN 1 ELSE saving_milestones.currency_rate END) ELSE 0 END), 0)'))
                        ->join('saving_milestones', 'savings.id', '=', 'saving_milestones.saving_id')
                        ->join('savings as s', 'saving_milestones.saving_id', '=', 's.id')
                        ->leftJoin('company_settings as cs', function ($join) {
                            $join->on('s.currency_id', '=', 'cs.currency')->whereNull('cs.deleted_at');
                        })
                        ->whereBetween('saving_milestones.end_date', [$currDate, $endDate])
                        ->where('s.archive', STATUS_INACTIVE)
                        ->where('s.is_draft', STATUS_INACTIVE)
                        ->where('s.status', '<>', STATUS_CANCELLED)
                        ->whereNull('s.deleted_at');
                }
            ])
            ->when(!empty($categoryArr), function ($query) use ($categoryArr) {
                $query->whereHas('savings.category', function ($subquery) use ($categoryArr) {
                    $subquery->whereIn('id', $categoryArr);
                });
            })
            ->when(!empty($subcategoryArr), function ($query) use ($subcategoryArr) {
                $query->whereHas('savings.subcategory', function ($subquery) use ($subcategoryArr) {
                    $subquery->whereIn('id', $subcategoryArr);
                });
            })

            ->when(!empty($savingType), function ($query) use ($savingType) {
                $query->whereHas('savings', function ($subquery) use ($savingType) {
                    $subquery->whereIn('saving_type', $savingType);
                });
            })

            ->when(!empty($location), function ($query) use ($location) {
                // $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation.locations', function($subquery) use ($location) {
                //     $subquery->where('id', $location);
                // });
                $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation', function ($subquery) use ($location) {
                    $subquery->withTrashed()->whereHas('locations', function ($qq) use ($location) {
                        $qq->whereIn('id', $location)->withTrashed();
                    });
                });
            })
            ->when(!empty($department), function ($query) use ($department) {
                // $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation.departments', function($subquery) use ($department) {
                //     $subquery->where('id', $department);
                // });
                $query->whereHas('savings.getContractLocationDepartment.getDpartmentLocation', function ($subquery) use ($department) {
                    $subquery->withTrashed()->whereHas('departments', function ($qq) use ($department) {
                        $qq->whereIn('id', $department)->withTrashed();
                    });
                });
            })
            ->when(!empty($createdByArr), function ($query) use ($createdByArr) {
                $query->whereHas('savings.user', function ($subquery) use ($createdByArr) {
                    $subquery->whereIn('id', $createdByArr);
                });
            })
            // ->when(isset($postArr['saving_project_id']) && !empty($postArr['saving_project_id']), function ($query) use ($postArr) {
            //     $query->whereHas('savings', function ($subquery) use ($postArr) {
            //         $subquery->where('id', $postArr['saving_project_id']);
            //     });
            // })
            ->when(!empty($supplierIds), function ($query) use ($supplierIds) {
                $query->whereHas('savings', function ($subquery) use ($supplierIds) {
                    $subquery->whereIn('supplier_id', $supplierIds);
                });
            })
            ->orderByDesc('total_project_saving')
            ->take(10)
            ->get();
        
            $baseCurrencySymbol = null;
            $getBaseCurrency = CompanySetting::with('currency')->first();
            if (isset($getBaseCurrency) && !empty($getBaseCurrency)) {
                $getBaseCurrencySymbol = CurrencyRate::find($getBaseCurrency->currency);
                $baseCurrencySymbol = isset($getBaseCurrencySymbol) && !empty($getBaseCurrencySymbol) ? $getBaseCurrencySymbol->currency_symbol : null;
            }
            
            return [
                'savingsData' => $list,
                'current_symbol' => $baseCurrencySymbol,
                'statusCode' => '200'
            ];
        } catch (\Throwable $th) {
            $response['message'] = $th->getMessage();
            $response['statusCode'] = '500';
            return $response;
        }
    }



    public static function getGraphDataByDepartment($postArr)
    {
        try {
            $currDate = Carbon::now(); // Get current date
            $endDate = Carbon::now()->addMonths(12); // Get date 12 months from now

            if (isset($postArr['start_date']) && !empty($postArr['start_date']) && isset($postArr['end_date']) && !empty($postArr['end_date'])) {
                $currDate = Carbon::parse($postArr['start_date']);
                $endDate = Carbon::parse($postArr['end_date']);
            } else if (isset($postArr['start_date']) && !empty($postArr['start_date'])) {
                $currDate = Carbon::parse($postArr['start_date']);
                $endDate = Carbon::parse($postArr['start_date'])->addMonths(12); // Get date after 12 months from now
            } else if (isset($postArr['end_date']) && !empty($postArr['end_date']) && empty($postArr['start_date'])) {
                $currDate = Carbon::parse($postArr['end_date'])->subMonth(12); // Get date previous 12 months from now
                $endDate = Carbon::parse($postArr['end_date']);
            }

            $list = Department::select('id', 'department_name')
            ->with(['locationsdepartments' => function ($query) use ($postArr) {
                $location = $postArr['location'] ?? [];
                if (!empty($location)) {
                    $query->whereIn('location_id', $location)->withTrashed();
                }
            }, 'locationsdepartments.savinglocationdepartments.savings.milestones'])
            ->get();
            $getBaseCurrency = CompanySetting::select('currency')->first();

            $list->transform(function ($department) use ($getBaseCurrency, $currDate, $endDate, $postArr) {
                $processedSavingIds = []; // To keep track of processed saving IDs within the department
                $totalProjectSavingSum = 0; // Define $totalProjectSavingSum here
                $totalRealisedSavingSum = 0; // Define $totalRealisedSavingSum here
                $location = $postArr['location'] ?? [];
                $department->locationsdepartments->each(function ($locationDepartment) use (&$processedSavingIds, &$totalProjectSavingSum, &$totalRealisedSavingSum, $getBaseCurrency, $currDate, $endDate, $postArr) {
                
                    $locationDepartment->savinglocationdepartments->each(function ($savingLocationDepartment) use (&$processedSavingIds, &$totalProjectSavingSum, &$totalRealisedSavingSum, $getBaseCurrency, $currDate, $endDate, $postArr) {

                        $categoryArr = $postArr['category'] ?? [];
                        $subcategoryArr = $postArr['sub_category'] ?? [];
                        $department = $postArr['department'] ?? [];
                        $createdByArr = $postArr['created_by'] ?? [];
                        $savingType = $postArr['saving_type'] ?? [];
                        $supplierIds = $postArr['supplier_ids'] ?? [];

                        $savingLocationDepartment->savings()
                        ->where(['archive' => STATUS_INACTIVE, 'is_draft' => STATUS_INACTIVE])->where('status', '<>', STATUS_CANCELLED)
                        ->when(!empty($savingType), function ($query) use ($savingType) {
                            $query->whereIn('saving_type', $savingType);
                        })
                        ->when(!empty($categoryArr), function ($query) use ($categoryArr) {
                            $query->whereHas('category', function ($subquery) use ($categoryArr) {
                                $subquery->whereIn('id', $categoryArr);
                            });
                        })
                        ->when(!empty($subcategoryArr), function ($query) use ($subcategoryArr) {
                            $query->whereHas('subcategory', function ($subquery) use ($subcategoryArr) {
                                $subquery->whereIn('id', $subcategoryArr);
                            });
                        })

                        ->when(!empty($department), function ($query) use ($department) {
                            // $query->whereHas('getContractLocationDepartment.getDpartmentLocation.departments', function($subquery) use ($department) {
                            //     $subquery->where('id', $department);
                            // });
                            $query->whereHas('getContractLocationDepartment.getDpartmentLocation', function ($subquery) use ($department) {
                                $subquery->withTrashed()->whereHas('departments', function ($qq) use ($department) {
                                    $qq->whereIn('id', $department)->withTrashed();
                                });
                            });
                        })
                        ->when(!empty($createdByArr), function ($query) use ($createdByArr) {
                            $query->whereHas('user', function ($subquery) use ($createdByArr) {
                                $subquery->whereIn('id', $createdByArr);
                            });
                        })
                        ->when(!empty($supplierIds), function ($query) use ($supplierIds) {
                            $query->whereIn('supplier_id', $supplierIds);
                        })
                        // ->when(isset($postArr['saving_project_id']) && !empty($postArr['saving_project_id']), function ($query) use ($postArr) {
                        //     $query->where('id', $postArr['saving_project_id']);
                        // })
                        ->each(function ($saving) use (&$processedSavingIds, &$totalProjectSavingSum, &$totalRealisedSavingSum, $getBaseCurrency, $currDate, $endDate) {
                            if (!in_array($saving->id, $processedSavingIds)) {
                                $processedSavingIds[] = $saving->id;
                                
                                // Initialize sum for this saving record
                                $savingTotalProjectSavingSum = 0;
                                $savingTotalRealisedSavingSum = 0;
                                
                                $saving->milestones->whereBetween('end_date', [$currDate, $endDate])->each(function ($milestone) use (&$savingTotalProjectSavingSum, &$savingTotalRealisedSavingSum, $saving, $getBaseCurrency) {
                                    $currencyRate = $saving->currency_id == isset($getBaseCurrency) && !empty($getBaseCurrency) && $getBaseCurrency->currency ? 1 : $milestone->currency_rate;

                                    if ($currencyRate != 0) {
                                        $savingTotalProjectSavingSum += $milestone->total_project_saving * $currencyRate;
                                        $savingTotalRealisedSavingSum += $milestone->total_realised_saving * $currencyRate;
                                    }
                                });
                                
                                // Add the sum for this saving record to the total sums for the department
                                $totalProjectSavingSum += $savingTotalProjectSavingSum;
                                $totalRealisedSavingSum += $savingTotalRealisedSavingSum;
                            }
                        });
                    });
                });

                $department->total_project_saving_sum = $totalProjectSavingSum;
                $department->total_realised_saving_sum = $totalRealisedSavingSum;

                return $department;
            });
            // Sort the list by total_project_saving_sum in descending order
            $list = $list->sortByDesc('total_project_saving_sum');

            // You can also limit the list to the top 10 departments if needed
            $list = $list->take(10);

            $baseCurrencySymbol = null;
            $getBaseCurrency = CompanySetting::with('currency')->first();
            if (isset($getBaseCurrency) && !empty($getBaseCurrency)) {
                $getBaseCurrencySymbol = CurrencyRate::find($getBaseCurrency->currency);
                $baseCurrencySymbol = isset($getBaseCurrencySymbol) && !empty($getBaseCurrencySymbol) ? $getBaseCurrencySymbol->currency_symbol : null;
            }

            return [
                'savingsData' => $list,
                'current_symbol' => $baseCurrencySymbol,
                'statusCode' => '200'
            ];
        } catch (\Throwable $th) {
            $response['message'] = $th->getMessage();
            $response['statusCode'] = '500';
            return $response;
        }
    }

    public static function approveSavingData($input, $url)
    {
        DB::beginTransaction();
        try {
            $updateSaving = Saving::where('id', $input['saving_id'])->update(['approval_status' => STATUS_ACTIVE, 'needs_approval' => STATUS_INACTIVE]);
            $currentDate = Carbon::now();
            if ($updateSaving) {
                SavingApproval::where(['saving_id' => $input['saving_id']])->whereNull('reject_reason')->update(['status' => STATUS_ACTIVE, 'submitted_by' => Auth::user()->id]);
                DB::commit();
                $savingApproval =  Saving::find($input['saving_id']);
                $message = 'Saving has been approved successfully';
                $creatorName = User::find($savingApproval->created_by);
                $submessage = Auth::user()->name.' has approved saving ' . $savingApproval->name;

                $submessageTranslate = '{approver_name} has approved saving {saving_name}';
                $values = json_encode([
                    'approver_name' => Auth::user()->name,
                    'saving_name' => $savingApproval->name
                ]);
                $inputNotification = [
                    'message' => $message,
                    'submessage' => $submessage,
                    'user_id' => $creatorName->id,
                    'category' => SAVINGS_NOTIFICATIONS,
                    'module_id' => $savingApproval->id,
                    'data' => $values,
                    'sub_message_translation' => $submessageTranslate
                ];

                broadcast(new \App\Events\TestNotification($message, $submessage, $savingApproval->id, null, $currentDate, false, SAVINGS_NOTIFICATIONS, $savingApproval->id));
                app('App\Http\Controllers\Api\NotificationController')->storeNotificationApprovals($inputNotification);


                $data = array(
                    'email' => $creatorName->email,
                    'type' => 'saving-approved',
                    'username' => $creatorName->name,
                    'approver_name' => Auth::user()->name,
                    'saving_name' => $savingApproval->name,
                    'link' => $url . '/savings/view/' . $savingApproval->id,
                );
                //Send email to user by using this function calling in Emails trait
                self::saveEmail($data);
                $code = 200;
            } else {
                $message = 'Saving has not been approved';
                $code = 500;
            }

            $response['message'] = $message;
            $response['statusCode'] = $code;
            return $response;
        } catch (\Throwable $th) {
            DB::rollback();
            $response['message'] = $th->getMessage();
            $response['statusCode'] = '500';
            return $response;
        }
    }


    public static function rejectSavingData($input)
    {
        DB::beginTransaction();
        try {
            $currentDate = Carbon::now();
            $updateSaving = Saving::where('id', $input['saving_id'])->update(['approval_status' => SAVING_STATUS_REJECTED, 'needs_approval' => STATUS_INACTIVE]);

            if ($updateSaving) {
                $update = SavingApproval::where(['saving_id' => $input['saving_id']])->whereNull('reject_reason')->update(['reject_reason' => $input['reject_reason'], 'status' => STATUS_APPROVAL_REJECT, 'submitted_by' => Auth::user()->id]);
                if ($update) {
                    $getCreatorData = User::find($input['user_id']);

                    $message = "Rejection Notification";
                    $submessage = Auth::user()->name . ' has rejected ' . $input['saving_name'];

                    $submessageTranslate = '{user_name} has rejected {saving_name}';
                    $values = json_encode([
                        'user_name' => Auth::user()->name,
                        'saving_name' => $input['saving_name']
                    ]);
                    $inputNotification = [
                        'message' => $message,
                        'submessage' => $submessage,
                        'user_id' => $getCreatorData->id,
                        'category' => SAVINGS_NOTIFICATIONS,
                        'module_id' => $input['saving_id'],
                        'data' => $values,
                        'sub_message_translation' => $submessageTranslate
                    ];

                    broadcast(new \App\Events\TestNotification($message, $submessage, $getCreatorData->id, null, $currentDate, false, SAVINGS_NOTIFICATIONS, $input['saving_id']));
                    app('App\Http\Controllers\Api\NotificationController')->storeNotificationApprovals($inputNotification);

                    $rejectEmailArr = [
                        'email' => $getCreatorData->email,
                        'type' => 'saving-rejection',
                        'username' => $getCreatorData->name,
                        'saving_name' => $input['saving_name'],
                        'approver_name' => Auth::user()->name
                    ];
                    self::saveEmail($rejectEmailArr);
                }
                DB::commit();
                $message = 'Saving rejected';
                $code = 200;
            } else {
                $message = 'Saving has not been rejected';
                $code = 500;
            }
            $response['message'] = $message;
            $response['statusCode'] = $code;
            return $response;
        } catch (\Throwable $th) {
            DB::rollback();
            $response['message'] = $th->getMessage();
            $response['statusCode'] = '500';
            return $response;
        }
    }


    public static function reapproveSavingData($input, $url)
    {
        DB::beginTransaction();
        try {
            $currentDate = Carbon::now();
            $reApprovalCount = $input['reapproval_count'] + 1;

            $getSavingRejectionData = SavingApproval::where('saving_id', $input['saving_id'])->whereNotNull('reject_reason')->orderBy('created_at', 'DESC')->first();
            $updateSaving = Saving::where('id', $input['saving_id'])->update(['approval_status' => SAVING_STATUS_UNAPPROVED, 'needs_approval' => STATUS_ACTIVE, 'reapproval_count' => $reApprovalCount]);

            if ($updateSaving) {
                $saveSavingForApproval = SavingApproval::create([
                    'saving_id' => $input['saving_id'],
                    'approver_id' => $getSavingRejectionData->approver_id
                ]);

                if ($saveSavingForApproval->exists) {
                    $getApproverData = User::find($getSavingRejectionData->approver_id);

                    $message = "Approval Notification";
                    $submessage = Auth::user()->name.' has requested '.$getApproverData->name.' to approved saving ' . $input['saving_name'];

                    $submessageTranslate = '{user_name} has requested {approver_name} to approved saving {saving_name}';
                    $values = json_encode([
                        'user_name' => Auth::user()->name,
                        'approver_name' => $getApproverData->name,
                        'saving_name' => $input['saving_name']
                    ]);
                    $inputNotification = [
                        'message' => $message,
                        'submessage' => $submessage,
                        'user_id' => $getApproverData->id,
                        'category' => SAVINGS_NOTIFICATIONS,
                        'module_id' => $input['saving_id'],
                        'data' => $values,
                        'sub_message_translation' => $submessageTranslate
                    ];

                    broadcast(new \App\Events\TestNotification($message, $submessage, $getApproverData->id, null, $currentDate, false, SAVINGS_NOTIFICATIONS, $input['saving_id']));
                    app('App\Http\Controllers\Api\NotificationController')->storeNotificationApprovals($inputNotification);

                    $approverEmailArr = [
                        'email' => $getApproverData->email,
                        'type' => 'saving-approval',
                        'username' => $getApproverData->name,
                        'saving_name' => $input['saving_name'],
                        'link' => $url . '/savings/view/' . $input['saving_id']
                    ];
                    $result = self::saveEmail($approverEmailArr);
                }

                DB::commit();
                $message = 'Sent for reapproval';
                $code = 200;
            } else {
                $message = 'Error: Not sent for reapproval';
                $code = 500;
            }
            $response['message'] = $message;
            $response['statusCode'] = $code;
            return $response;
        } catch (\Throwable $th) {
            DB::rollback();
            $response['message'] = $th->getMessage();
            $response['statusCode'] = '500';
            return $response;
        }
    }

    public static function updateSavingForZapier($postArr)
    {
        DB::beginTransaction();
        try {
            $getSavingById = self::with('user', 'currency', 'category', 'supplier', 'contract', 'subcategory', 'getContractLocationDepartment', 'getContractLocationDepartment.getDpartmentLocation', 'getContractLocationDepartment.getDpartmentLocation.locations', 'getContractLocationDepartment.getDpartmentLocation.departments', 'savingTypes')->find($postArr['id']);

            $getSavingById->fill($postArr);

            $updatedFieldsList = self::saveUpdatedModel($getSavingById, $postArr, SUBMODULE_SAVING);

            if ($updatedFieldsList) {

                // update custom fields
                if (!empty($postArr['custom_fields'])) {
                    foreach ($postArr['custom_fields'] as $field) {
                        if (!empty($field['id']) && !empty($field['value']) && !empty($field['formatted_value'])) {

                            $value = $field['formatted_value'] ?? null;

                            if ($field['field_type'] == FIELD_TYPE_BOOLEAN) {
                                $value = $value == 'true' ? 1 : 0;
                            }

                            CustomFieldValue::updateOrCreate(
                                [
                                    'record_id' => $postArr['id'],
                                    'custom_field_id' => $field['id']
                                ],
                                [
                                    'value' => $value,
                                ]
                            );
                        }
                    }
                }

                DB::commit();
                self::saveSavingWithLocationsAndDepartments($postArr);
 
                if(!empty($postArr['parent_child_id']) && !empty($postArr['relation_type'])){

                    $dataWhere = [
                        'record_id' => $postArr['id'],
                        'record_type' => RECORD_TYPE_SAVINGS
                    ];

                    $dataUpdate = [
                        'parent_child_id' => $postArr['parent_child_id'],
                        'relation_type' => $postArr['relation_type'],
                    ];
            
                    ParentChildRelation::updateOrCreate($dataWhere, $dataUpdate);
                }

                $response['saving_id'] = $postArr['id'];
                $response['message'] = 'Saving data has been updated successfully.';
                $response['statusCode'] = '200';
                return $response;
            } else {
                $response['message'] = 'Saving data has not not been updated.';
                $response['statusCode'] = '500';
                return $response;
            }
        } catch (\Throwable $th) {
            DB::rollback();
            $response['message'] = $th->getMessage();
            $response['statusCode'] = '500';
            return $response;
        }
    }


}
