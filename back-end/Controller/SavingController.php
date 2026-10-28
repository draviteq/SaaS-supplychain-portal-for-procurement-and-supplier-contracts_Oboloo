<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\Saving;
use App\Models\SavingMilestone;
use App\Models\Contract;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\SupplierContract;
use App\Models\SavingAttachment;
use App\Models\SavingsComment;
use App\Models\User;
use App\Models\Notification;
use App\Models\SavingsOwner;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Response as Download;
use Illuminate\Support\Facades\Storage;

class SavingController extends Controller
{

    /**
     * Display a list of savings.
     *
     * @return \Illuminate\Http\Response
     */

      /**
     * @OA\Get(
     *     path="/api/savings",
     *     summary="Get a list of savings based on multiple filters",
     *     description="This endpoint retrieves savings data filtered by various parameters such as search, supplier ID, contract ID, and several other filters like location, department, status, category, etc.",
     *     tags={"Savings"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", example="")
     *     ),
     *     @OA\Parameter(
     *         name="supplier_id",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", example="")
     *     ),
     *     @OA\Parameter(
     *         name="contract_id",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", example="")
     *     ),
     *     @OA\Parameter(
     *         name="location",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="array", @OA\Items(type="integer", example=3))
     *     ),
     *     @OA\Parameter(
     *         name="department",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="array", @OA\Items(type="integer", example=4))
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="array", @OA\Items(type="integer", example=5))
     *     ),
     *     @OA\Parameter(
     *         name="subCategory",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="array", @OA\Items(type="integer", example=6))
     *     ),
     *     @OA\Parameter(
     *         name="startDate",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="")
     *     ),
     *     @OA\Parameter(
     *         name="endDate",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="")
     *     ),
     *     @OA\Parameter(
     *         name="archive",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="array", @OA\Items(type="integer", example=0))
     *     ),
     *     @OA\Parameter(
     *         name="createdBy",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="array", @OA\Items(type="integer", example=7))
     *     ),
     *     @OA\Parameter(
     *         name="saving_type",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="array", @OA\Items(type="integer", example=8))
     *     ),
     *     @OA\Parameter(
     *         name="currency",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="array", @OA\Items(type="integer", example=9))
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="array", @OA\Items(type="integer", example=1))
     *     ),
     *     @OA\Parameter(
     *         name="approval_status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="array", @OA\Items(type="integer", example=1))
     *     ),
     *     @OA\Parameter(
     *         name="options[page]",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="options[itemsPerPage]",
     *         in="query",
     *         description="Items per page for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved savings list",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="savingsData", type="array", 
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="supplier_id", type="integer", example=2),
     *                     @OA\Property(property="contract_id", type="integer", example=3),
     *                     @OA\Property(property="category", type="string", example="SUPPLIERS_NOTIFICATIONS"),
     *                     @OA\Property(property="subcategory", type="string", example="subcategory example"),
     *                     @OA\Property(property="status", type="string", example="In Progress"),
     *                     @OA\Property(property="created_by", type="string", example="John Doe"),
     *                     @OA\Property(property="created_at", type="string", example="2023-12-15T10:00:00"),
     *                     @OA\Property(property="updated_at", type="string", example="2023-12-16T12:00:00")
     *                 )
     *             ),
     *             @OA\Property(property="total", type="integer", example=50),
     *             @OA\Property(property="totalPage", type="integer", example=5),
     *             @OA\Property(property="page", type="integer", example=1),
     *             @OA\Property(property="message", type="string", example="success")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request, validation errors",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example=""),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="")
     *         )
     *     )
     * )
 */



    public function savingList(Request $request)
    {
        try {
            $postArr = $request->all();
            $result = Saving::getSavingList($postArr);
            return response()->json($result, 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }


    /**
     * Display a filter.
     *
     * @return \Illuminate\Http\Response
     */

    public function savingListFilter()
    {
        try {
            $result = Saving::getSavingListFilter();
            return response()->json($result, 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a filter for create/update modal.
     *
     * @return \Illuminate\Http\Response
     */

    public function savingListFilterCreateUpdate()
    {
        try {
            $result = Saving::getSavingListFilterCreateUpdate();
            return response()->json($result, 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * get draft saving data.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    
    public function getDraftSaving()
    {
        try {
            $userId = Auth::user()->id;
            $response = Saving::getDraftSavingData($userId);
            return response()->json($response, 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Create New saving data (1st step)
     * @param Request $request
     * @return Saving
     */

    /**
 * @OA\Post(
 *     path="/api/savings/add",
 *     summary="Create a new saving entry",
 *     description="This endpoint creates a new saving entry based on provided data such as name, description, status, category, etc.",
 *     tags={"Savings"},
 *     security={{"sanctum": {}}},
 *     @OA\Parameter(
 *         name="name",
 *         in="query",
 *         required=true,
 *         @OA\Schema(type="string", example="Saving Name")
 *     ),
 *     @OA\Parameter(
 *         name="description",
 *         in="query",
 *         required=true,
 *         @OA\Schema(type="string", example="Saving Description")
 *     ),
 *     @OA\Parameter(
 *         name="status",
 *         in="query",
*          description="Status of the saving entry. 0 for Npt-Started, 1 for InProgress, 2 for Complete, 3 for Canceled.",
 *         required=true,
 *      @OA\Schema(
 *         type="integer",
 *         enum={0, 1, 2, 3},   
 *         example=0           
 *     ) 
 *    ),
 *     @OA\Parameter(
 *         name="category_id",
 *         in="query",
 *         required=true,
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *     @OA\Parameter(
 *         name="currency_id",
 *         in="query",
 *         required=true,
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *     @OA\Parameter(
 *         name="location",
 *         in="query",
 *         required=true,
 *         @OA\Schema(type="array", @OA\Items(type="integer", example=3))
 *     ),
 *     @OA\Parameter(
 *         name="department",
 *         in="query",
 *         required=true,
 *         @OA\Schema(type="array", @OA\Items(type="integer", example=4))
 *     ),
 *     @OA\Parameter(
 *         name="saving_type",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="array", @OA\Items(type="integer", example=8))
 *     ),
 *     @OA\Parameter(
 *         name="supplier_id",
 *         in="query",
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successfully created saving entry",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Saving entry created successfully"),
 *             @OA\Property(property="data", type="object", 
 *                 @OA\Property(property="id", type="integer", example=1),
 *                 @OA\Property(property="name", type="string", example="Saving Name"),
 *                 @OA\Property(property="description", type="string", example="Saving Description"),
 *                 @OA\Property(property="status", type="string", example="Active"),
 *                 @OA\Property(property="created_by", type="integer", example=1)
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Bad Request, validation errors",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Validation error"),
 *             @OA\Property(property="errors", type="object")
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Internal Server Error",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Internal Server Error")
 *         )
 *     )
 * )
 */


    
    public function add(Request $request)
    {
        
        try {
            $postArr = $request->all();
            //Validated
            $validateData = Validator::make(
                $request->all(),
                [
                    'name' => 'required|max:100',
                    'description' => 'required',
                    'status' => 'required',
                    'category_id' => 'required',
                    'currency_id' => 'required',
                    'location' => "required",
                    'department' => "required",
                ],
                [
                    'currency_id.required' => 'Currency is required',
                    'category_id.required' => 'Category is required',   
                ]
            );

            if ($validateData->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateData->errors()
                ], 403);
            }

            $postArr['created_by'] = Auth::user()->id;
            $response = Saving::addNewSaving($postArr);

            $user = User::with(['userHubspotContact'])->find(Auth::user()->id);
                
            $hubspotData = [
                'created_a_saving' => true,
            ];

            if (isset($user->userHubspotContact) && !empty($user->userHubspotContact)) {
                $controller = app(HubspotController::class);
                $controller->update($hubspotData, $user->userHubspotContact->contact_id);
            }

            return response()->json($response, $response['statusCode']);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Update New saving data (2nd step)
     * @param Request $request
     * @return Saving
     */
    public function updateSavingData(Request $request)
    {
        
        try {
            $postArr = $request->all();

            $response = Saving::updateSavingFelds($postArr);
            return response()->json($response, $response['statusCode']);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * edit the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    
    public function edit(Request $request)
    {
        try {
            $input = $request->all();
            $id = $input['id'];
            $response = Saving::getSavingById($id);
            return response()->json($response, 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Update saving by id.
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    
     /**
 * @OA\Post(
 *     path="/api/savings/update",
 *     summary="Update Saving",
 *     description="Update Saving Data using query parameters",
 *     tags={"Savings"},
 *     security={
 *         {"sanctum": {}}
 *     },
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="The ID of the saving entry to update",
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *     @OA\Parameter(
 *         name="name",
 *         in="query",
 *         required=true,
 *         description="Saving Title",
 *         @OA\Schema(type="string", example="Cost Reduction")
 *     ),
 *     @OA\Parameter(
 *         name="description",
 *         in="query",
 *         required=true,
 *         description="Description of the saving",
 *         @OA\Schema(type="string", example="This saving focuses on reducing costs.")
 *     ),
 *     @OA\Parameter(
 *         name="status",
 *         in="query",
*          description="Status of the saving entry. 0 for Npt-Started, 1 for InProgress, 2 for Complete, 3 for Canceled.",
 *         required=true,
 *      @OA\Schema(
 *         type="integer",
 *         enum={0, 1, 2, 3},   
 *         example=0           
 *     ) 
 *    ),
 *     @OA\Parameter(
 *         name="category_id",
 *         in="query",
 *         required=true,
 *         description="The category ID of the saving",
 *         @OA\Schema(type="integer", example=3)
 *     ),
 *     @OA\Parameter(
 *         name="currency_id",
 *         in="query",
 *         required=true,
 *         description="The currency ID for the saving",
 *         @OA\Schema(type="integer", example=2)
 *     ),
 *     @OA\Parameter(
 *         name="location",
 *         in="query",
 *         required=true,
 *         description="Location associated with the saving",
 *         @OA\Schema(type="integer", example="1")
 *     ),
 *     @OA\Parameter(
 *         name="department",
 *         in="query",
 *         required=true,
 *         description="Department associated with the saving",
 *         @OA\Schema(type="integer", example="1")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Saving updated successfully",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="message", type="string", example="")
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Validation error",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example=""),
 *             @OA\Property(property="errors", type="object")
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Internal Server Error",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="message", type="string", example="")
 *         )
 *     )
 * )
 */


    public function update(Request $request)
    {
        try {
            $postArr = $request->all();
            $id = $postArr['id'];
            //Validated

            $validateData = Validator::make(
                $request->all(),
                [
                    'name' => 'required|max:100',
                    'description' => 'required',
                    'status' => 'required',
                    'category_id' => 'required',
                    'currency_id' => 'required',
                    'location' => "required",
                    'department' => "required",
                ],
                [   'name'=>'Saving Title is required',
                    'currency_id.required' => 'Currency is required',
                    'category_id.required' => 'Category is required',
                ]
            );

            if ($validateData->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateData->errors()
                ], 403);
            }

            $response = Saving::updateSaving($postArr);

            return response()->json([
                'message' => $response['message']
            ], $response['statusCode']);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * change status of saving.
     *
     * @return \Illuminate\Http\Response
     */

    public function savingChangeStatus(Request $request)
    {
        try {
            $input = $request->all();
            $response = Saving::updateStatus($input);
            
            return response()->json([
                'message' => $response['message']
            ], $response['statusCode']);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Create New saving milestone list
     * @param Request $request
     * @return Saving
     */
    public function createMilestone(Request $request)
    {
        
        try {
            $postArr = $request->all();
            
            //Validated
            $validateData = Validator::make(
                $request->all(),
                [
                    'start_date' => 'required',
                    'periods_number' => 'required',
                    'milestone_frequency' => 'required',
                    'total_baseline' => 'required',
                    'cost_reduction' => 'required',
                    'cost_avoidance' => 'required',
                ],
                [
                    'start_date.required' => 'Start date is required',
                    'periods_number.required' => 'Number of time periods is required',
                    'milestone_frequency.required' => 'Frequency Of Milestone is required',
                    'total_baseline.required' => 'Total Baseline Spend is required',
                    'cost_reduction.required' => 'Projected Cost Reduction is required',
                    'cost_avoidance.required' => 'Projected Cost Avoidance is required',
                ]
            );

            if ($validateData->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateData->errors()
                ], 403);
            }

            $i = 1;
            $givenDate = Carbon::parse($postArr['start_date']);
           
            $dateList = [];

            for ($i; $i <= $postArr['periods_number']; $i++) {
                $startDate = $givenDate->format('Y-m-d'); // Store the formatted start date
                $givenDate->addMonths($postArr['milestone_frequency']);
                $endDate = $givenDate->copy()->subDay()->format('Y-m-d'); // Calculate end date before next start date
                $dateList[] = [
                    'name' => "Milestone $i",
                    'number' => $i,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'base_line_spend' => number_format($postArr['total_baseline']/$postArr['periods_number'], 2, '.', ''),
                    'project_cost_reduction' => number_format($postArr['cost_reduction']/$postArr['periods_number'], 2, '.', ''),
                    'project_cost_avoidance' => number_format($postArr['cost_avoidance']/$postArr['periods_number'], 2, '.', '')
                ];
            }

            return response()->json($dateList, 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * add milestone data
     * @param Request $request
     * @return Saving
     */
    public function addMilestone(Request $request)
    {
        
        try {
            $postArr = $request->all();
           
            $response = SavingMilestone::addNewSavingMilestone($postArr);
            return response()->json($response, $response['statusCode']);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }


    /**
     * Display a list of saving milestone.
     *
     * @return \Illuminate\Http\Response
     */

    public function savingMilestonesList(Request $request)
    {
        try {
            $postArr = $request->all();
            $result = SavingMilestone::getSavingMilestoneList($postArr);
            return response()->json($result, 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * edit milestone the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    
    public function milestoneEdit(Request $request)
    {
        try {
            $input = $request->all();
            $id = $input['id'];
            $response = SavingMilestone::getSavingMilestoneById($id);

            $arr = [
                'id' => $id,
                'field_area' => FIELD_AREA_SAVINGS_MILESTONE,
                'module_type' => MODULE_TYPE_SAVINGS,
            ];

            $response['custom_fields'] = CustomField::customFields($arr);

            return response()->json($response, 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Activate saving (final step)
     * @param Request $request
     * @return Saving
     */
    public function updateSavingStatus(Request $request)
    {
        
        try {
            $url = $request->headers->get('origin');
            //Validated
            $validateData = Validator::make(
                $request->all(),
                [
                    'approver_id' => 'required_if:needs_approval,1', // Conditional validation
                ],
                [
                    'approver_id.required_if' => 'Approver is required when approval is needed',
                ]
            );

            if ($validateData->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateData->errors()
                ], 403);
            }

            $postArr = $request->all();
            $response = Saving::updateSavingStatus($postArr, $url);
            return response()->json($response, $response['statusCode']);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

        /**
     * Get Contracts by supplier id
     * @param Request $request
     * @return Saving
     */
    public function getSupplierContracts(Request $request)
    {
        try {
            $postArr = $request->all();
            $response = Contract::getSupplierContractsById($postArr['id']);
            return response()->json($response, 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Display a list of saving attachments.
     *
     * @return \Illuminate\Http\Response
     */

    public function savingAttachmentList(Request $request)
    {
        try {
            $postArr = $request->all();
            $result = SavingAttachment::getSavingAttachmentList($postArr);
            return response()->json($result, 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    
    public function deleteAttachment(Request $request)
    {
        DB::beginTransaction();
        try {
            $input = $request->all();
            $id = $input['id'];
            $attachment = SavingAttachment::find($id);
            $attachment->delete();
            DB::commit();
            return response()->json([
                'message' => 'Attachment has been deleted successfully'
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * add attachment data
     * @param Request $request
     * @return Saving
     */
    public function addAttachment(Request $request)
    {
        try {
            $postArr = $request->all();
            $validateData = Validator::make(
                $request->all(),
                [
                    'title' => 'required',
                    'file' => 'required|mimes:pdf,xlsx,docx,pptx,xls,doc,ppt,jpg,png,jpeg,txt,csv,zip|max:16384', // Max size is 16384 KB (16 MB)
                ],
                [
                    'title.required' => 'Title is required',
                    'file.required' => 'File is required',
                    'file.mimes' => 'File must be one of the following types: pdf, xlsx, docx, pptx, xls, doc, ppt, jpg, png, jpeg, txt, csv, zip',
                    'file.max' => 'File may not be greater than 16 MB',
                ]
            );


            if ($validateData->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateData->errors()
                ], 403);
            }
            $response = SavingAttachment::addNewAttachment($postArr);
            return response()->json($response, $response['statusCode']);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * return a list of savings.
     *
     * @return \Illuminate\Http\Response
     */

    public function savingExportList(Request $request)
    {
        try {
            $result = Saving::getSavingExportList();
            return response()->json($result, 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }
    

    /**
     * @OA\Post(
     *     path="/api/savings/milestones/add",
     *     summary="Add a new milestone",
     *     description="This endpoint allows you to add a new milestone by providing its details.",
     *     operationId="addNewMilestone",
     *     tags={"Savings"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="saving_id", type="integer", description="ID of the saving related to the milestone", example=""),
     *                 @OA\Property(property="name", type="string", description="Name of the milestone", example=""),
     *                 @OA\Property(property="start_date", type="string", format="date", description="Start date of the milestone", example="2025-01-01"),
     *                 @OA\Property(property="end_date", type="string", format="date", description="End date of the milestone", example="2025-12-31"),
     *                 @OA\Property(property="description", type="string", description="Additional description about the milestone", example=""),
     *                 @OA\Property(property="base_line_spend", type="number", format="float", description="Base line spend for the milestone", example="100000.00"),
     *                 @OA\Property(property="project_cost_reduction", type="number", format="float", description="Projected cost reduction for the milestone", example="15000.00"),
     *                 @OA\Property(property="project_cost_avoidance", type="number", format="float", description="Projected cost avoidance for the milestone", example="12000.00"),
     *                 required={"saving_id", "name", "start_date", "end_date"}
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Milestone added successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="validation error"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error message here")
     *         )
     *     ),
     *     security={
     *         {"sanctum": {}}
     *     }
     * )
     */
    public function addNewMilestone(Request $request)
    {
        
        try {
            $postArr = $request->all();
            $postArr['created_by'] = Auth::user()->id;
            $validateData = Validator::make(
                $request->all(),
                [
                    'name' => 'required',
                    'start_date' => 'required',
                    'end_date' => 'required',
                ],
                [
                    'start_date.required' => 'Start date is required',
                    'end_date.required' => 'End date is required',
                ]
            );

            if ($validateData->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateData->errors()
                ], 403);
            }

            $response = SavingMilestone::addNewMilestone($postArr);
            return response()->json($response, $response['statusCode']);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/savings/milestones/update",
     *     summary="Update an existing milestone",
     *     description="This endpoint allows you to update an existing milestone by providing updated details.",
     *     tags={"Savings"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="id", type="integer", description="ID of the milestone to update", example=""),
     *                 @OA\Property(property="start_date", type="string", format="date", description="Updated start date of the milestone", example="2025-01-10"),
     *                 @OA\Property(property="end_date", type="string", format="date", description="Updated end date of the milestone", example="2025-12-15"),
     *                 @OA\Property(property="note", type="string", description="Updated description of the milestone", example=""),
     *                 @OA\Property(property="base_line_spend", type="number", format="float", description="Updated base line spend for the milestone", example="110000.00"),
     *                 @OA\Property(property="project_cost_reduction", type="number", format="float", description="Updated projected cost reduction for the milestone", example="17000.00"),
     *                 @OA\Property(property="project_cost_avoidance", type="number", format="float", description="Updated projected cost avoidance for the milestone", example="14000.00"),
     *                 required={"id", "start_date", "end_date"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Milestone updated successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Validation error",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error message here")
     *         )
     *     ),
     *     security={
     *         {"sanctum": {}}
     *     }
     * )
     */
    public function updateMilestone(Request $request)
    {
        
        try {
            $postArr = $request->all();

            $validateData = Validator::make(
                $request->all(),
                [
                    'start_date' => 'required',
                    'end_date' => 'required',
                ],
                [
                    'start_date.required' => 'Start date is required',
                    'end_date.required' => 'End date is required',
                ]
            );

            if ($validateData->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateData->errors()
                ], 403);
            }

            $response = SavingMilestone::updateMilestone($postArr);
            return response()->json($response, $response['statusCode']);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * milestone mark as complete from the milestone tab
     * @param Request $request
     * @return Saving
     */
    public function markAsComplete(Request $request)
    {
        
        try {
            $postArr = $request->all();
            $response = SavingMilestone::markAsCompleteMilestone($postArr);
            return response()->json($response, $response['statusCode']);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }


    /**
     * Display a listing of the logs.
     *
     * @return \Illuminate\Http\Response
     */
    
    public function getlogs(Request $request)
    {
       
        try {
            $postArr = $request->all();
            $result = Saving::getSavingLogs($postArr);
            
            return response()->json($result, 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Display a listing of the log submodules.
     *
     * @return \Illuminate\Http\Response
     */
    
    public function getSubmodules(Request $request)
    {
       
        try {
            $postArr = $request->all();
            $result = Saving::getSavingsSubmodules(MODULE_ENTITY_SAVING);
            
            return response()->json($result, 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }



    /**
     * Saving dashboard metrices.
     *
     */

    public function savingMetricData(Request $request)
    {

        try {
            $postArr = $request->all();
            $response = Saving::getMetricData($postArr);
            return response()->json(
                $response,
                $response['statusCode']
            );
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Saving dashboard created by listing.
     *
     */

    public function savingDashboardFilterOptions(Request $request)
    {

        try {
            $response = User::pluck('name', 'id');
            return response()->json(
                $response,
                200
            );
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }
    /**
     * Saving Projected Vs Realised Savings by Month
     *
     */

    public function getSavingGraphDataByFilters(Request $request)
    {

        try {
            $postArr = $request->all();
            $response = Saving::getGraphData($postArr);
            return response()->json(
                $response,
                $response['statusCode']
            );
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Saving Projected Vs Realised Savings by Category
     *
     */

    public function getSavingGraphDataByCategory(Request $request)
    {

        try {
            $postArr = $request->all();
            $response = Saving::getGraphDataByCategory($postArr);
            return response()->json(
                $response,
                $response['statusCode']
            );
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Saving Projected Vs Realised Savings by Department
     *
     */

    public function getSavingGraphDataByDepartment(Request $request)
    {

        try {
            $postArr = $request->all();
            $response = Saving::getGraphDataByDepartment($postArr);
            return response()->json(
                $response,
                $response['statusCode']
            );
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function getRecentSavingNotifications(Request $request)
    {
       
        try {
            $result = Notification::getRecentNotificationsList(SAVINGS_NOTIFICATIONS, 10);
            
            return response()->json($result, 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function getCommentList(Request $request)
    {
       
        try {
            $postArr = $request->all();
            $id = $postArr['saving_id'];
            $search = $request->search;

            $options = $request->options;
            $perPage = $options['itemsPerPage'] ?? 10;
            $page = $options['page'] ?? 1;
            $sortBy = $options['sortBy'][0]['key'] ?? 'id';
            $sortOrder = $options['sortBy'][0]['order'] ?? 'DESC';
            $columns = Schema::getColumnListing('saving_comments');

            $list = SavingsComment::with(['users', 'users.userImage'])
                ->where(['saving_id' => $id])
                ->select('id', 'comment', 'saving_id', 'user_id', "created_at as created")
                ->when($search, function ($query, $search) use ($columns) {
                    $query->where(function ($subquery) use ($columns, $search) {
                        foreach ($columns as $column) {
                            if ($column == 'user_id') {
                                $subquery->orwhereHas('users', function ($q) use ($search) {
                                    return $q->where('name', "LIKE", '%' . $search . '%');
                                });
                            } else {
                                $subquery->orWhere($column, 'LIKE', '%' . $search . '%');
                            }
                        }
                    });
                });
                
            if ($sortBy == 'name') {
                $list->whereHas('users', function ($q) use ($sortOrder) {
                    $q->orderBy('name', $sortOrder);
                });
            } else {
                $list->orderBy($sortBy, $sortOrder);
            }
            

            $totalItems = $list ->count();
            $savingscomment = $list ->offset(($page - 1) * $perPage)->limit($perPage)->get();


            foreach ($savingscomment as $comment){

                $arr = [
                    'id' => $comment->id,
                    'field_area' => FIELD_AREA_NOTES,
                    'module_type' => MODULE_TYPE_SAVINGS,
                ];
    
                $comment['custom_fields'] = CustomField::customFields($arr);

            }

            $custom_fields = CustomField::activeCustomFields([
                'field_area' => FIELD_AREA_NOTES,
                'module_type' => MODULE_TYPE_SAVINGS,
            ]);

            return response()->json([
                'data' => $savingscomment,
                'custom_fields' => $custom_fields,
                'authUser' => Auth::user()->id,
                'totalItems' => $totalItems,
                'totalPage' => ceil($totalItems / $perPage),
                'page' => $page > ceil($totalItems / $perPage) ? 1 : $page,
                'message' => $totalItems > 0 ? 'success' : 'Savings Document List are not found!!',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }
    public function addsavingComment(Request $request)
    {
        DB::beginTransaction();
        try {
            $postArr = $request->all();
            //Validated
            $validateData = Validator::make(
                $request->all(),
                [
                    'comment' => 'required'
                ]
            );

            if ($validateData->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateData->errors()
                ], 403);
            }
            $postArr['user_id'] = Auth::user()->id;
            $saveComment = SavingsComment::create($postArr);

            if (!empty($postArr['custom_fields'])) {
                foreach ($postArr['custom_fields'] as $field) {
                    if (!empty($field['id'])) {

                        $value = $field['formatted_value'] ?? null;

                        if ($field['field_type'] == FIELD_TYPE_BOOLEAN) {
                            $value = $value == 'true' ? 1 : 0;
                        }

                        CustomFieldValue::updateOrCreate(
                            [
                                'record_id' => $saveComment->id,
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
            if ($saveComment->exists) {
                return response()->json([
                    'message' => 'Comment has been added Successfully'
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Comment has not been added'
                ], 500);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function view(Request $request)
    {
        DB::beginTransaction();
        try {
            $input = $request->all();
            $id = $input['id'];
            $supplierComment = SavingsComment::with('users')->find($id);
            DB::commit();
            return response()->json([
                'data' => $supplierComment
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }
    public function delete(Request $request)
    {
        DB::beginTransaction();
        try {
            $input = $request->all();
            $id = $input['id'];
            $supplierComment = SavingsComment::find($id);
            $supplierComment->delete();
            DB::commit();
            return response()->json([
                'message' => 'Comment has been deleted successfully'
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * put saving to approve stage.
     *
     * @return \Illuminate\Http\Response
     */

    public function approveSaving(Request $request)
    {

        $postArr = $request->all();
        try {
            $url = $request->headers->get('origin');
            $result = Saving::approveSavingData($postArr, $url);
            return response()->json($result, $result['statusCode']);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
        return $result;
    }


    /**
     * put saving to reject stage.
     *
     * @return \Illuminate\Http\Response
     */

    public function rejectSaving(Request $request)
    {
        try {

            //Validated
            $validateData = Validator::make(
                $request->all(),
                [
                    'reject_reason' => 'required',
                ],
                [
                    'reject_reason.required' => 'Reason to reject is required',
                ]
            );

            if ($validateData->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateData->errors()
                ], 403);
            }
            $postArr = $request->all();
            $result = Saving::rejectSavingData($postArr);
            return response()->json($result, $result['statusCode']);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
        return $result;
    }


    /**
     * put saving to reapprove stage.
     *
     * @return \Illuminate\Http\Response
     */

    public function reApproveSaving(Request $request)
    {
        $url = $request->headers->get('origin');
        $postArr = $request->all();
        try {
            $result = Saving::reapproveSavingData($postArr, $url);
            return response()->json($result, $result['statusCode']);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
        return $result;
    }

    public function downloadFile(Request $request)
    {
        $input = $request->all();
        $fileName = $input['filename'];

        $tenantId = tenant()->id; // Assuming you have a way to get the tenant ID

        // Construct the full path of the file in S3
        $filePath = UPLOAD_FOLDER.'/' . $tenantId . '/saving_attachments/' . $fileName;

        // Check if the file exists
        if (!Storage::disk('s3')->exists($filePath)) {
            return response()->json(['message' => 'File not found.'], Response::HTTP_NOT_FOUND);
        }

        $fileContent = Storage::disk('s3')->get($filePath);

        $headers = [
            'Content-Type'        => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        return response($fileContent, Response::HTTP_OK, $headers);
    }

    /**
     * Display a listing of the parent child suppliers
     *
     * @return \Illuminate\Http\Response
     */

    public function parentChildList(Request $request)
    {
        try {
            $params = $request->all();

            $result = Saving::where('is_draft', STATUS_INACTIVE)->where('archive', STATUS_INACTIVE)->where('approval_status', STATUS_ACTIVE)->whereNot('id', $params['id'])->get();

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * return a listing of savings owner
     *
     * @return \Illuminate\Http\Response
     */

    public function fetchOwners(Request $request)
    {
        try {

            $result['savingOwnerList'] = SavingsOwner::fetchOwners($request->all());
            $result['ownerList'] = User::orderBy('name', 'ASC')->where('users.status', STATUS_ACTIVE)->where('hidden_user', STATUS_INACTIVE)->get();

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }


    public function deleteOwner(Request $request)
    {
        $id = $request['id'];
        $deleted = SavingsOwner::deleteOwner($id);

        if ($deleted) {
            return response()->json(['message' => 'Owner deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Owner not found'], 404);
        }
    }

    public function addOwner(Request $request)
    {
        try {
            $validated = $request->validate([
                'saving_id' => 'required',
                'user_id' => 'required',
            ], [
                'saving_id.required' => 'Saving is required',
                'user_id.required' => 'Owner is required',
            ]);
    
            $owner = SavingsOwner::addOwner($validated);
    
            if (!$owner) {
                return response()->json([
                    'message' => 'Failed to add owner. Please try again.'
                ], 500);
            }
    
            return response()->json([
                'message' => 'Owner added successfully!',
                'owner' => $owner
            ], 201);
    
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while adding the owner',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateThroughZapier(Request $request)
    {
        try {
            $postArr = $request->all();

            // Check for required ID early to avoid unnecessary work
            if (empty($postArr['id'])) {
                return response()->json([
                    'message' => 'Saving ID is required',
                ], 400);
            }

            $validateData = Validator::make(
                $request->all(),
                [
                    'name' => 'max:100',
                ]
            );

            if ($validateData->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateData->errors()
                ], 403);
            }

            $filteredData = self::filterNullValues($postArr);

            $response = Saving::updateSavingForZapier($filteredData);

            return response()->json([
                'message' => $response['message']
            ], $response['statusCode']);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Remove all keys from the array where the value is null.
     *
     * @param array $array
     * @return array
     */
    function filterNullValues(array $array): array
    {
        return array_filter($array, function ($value) {
            if (is_null($value)) {
                return false;
            }
    
            if (is_array($value)) {
                // Remove arrays that are empty or only contain null values
                $filtered = array_filter($value, fn($item) => !is_null($item));
                return count($filtered) > 0;
            }
    
            return true;
        });
    }
}
