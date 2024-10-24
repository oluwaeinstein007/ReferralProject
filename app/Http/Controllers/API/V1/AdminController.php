<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Level;
use App\Models\User;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\Community;
use App\Models\Setting;
use App\Models\CommunityRule;
use App\Models\ActivityLog;
use App\Services\NotificationService;
use App\Services\GeneralService;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    protected $generalService;
    protected $notificationService;

    public function __construct(GeneralService $generalService, NotificationService $notificationService)
    {
        $this->generalService = $generalService;
        $this->notificationService = $notificationService;
        // $this->middleware('auth');

    }
    //User Management
    public function getUsers(Request $request, $id = null)
    {
        if ($id) {
            $user = User::where('id', $id)->first();
            if (!$user) {
                return $this->failure('User not found', null, 404);
            }
            return $this->success('User retrieved successfully', $user, [], 200);
        }

        $validator = Validator::make($request->query(), [
            'perPage' => 'sometimes|integer',
            'page' => 'sometimes|integer',
            'user_role' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $query = User::query();
        // $query = User::withCount(['posts', 'likes', 'comments']);
        if ($request->input('user_role')) {
            $query->where('user_role_id', $request->input('user_role')); // Filter users based on user_role
        }

        $perPage = $request->input('perPage', 100);
        $users = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        if ($users->isEmpty()) {
            return $this->failure('No users found', null, 404);
        }
        $user_count = $query->count();
        $total_users = User::count();
        $data = [
            'count' => $user_count,
            'total_users' => $total_users,
            'users' => $users,
        ];

        return $this->success('Users retrieved successfully', $data, [], 200);
    }

    public function getUserMetaData()
    {
        $users = User::all();
        $totalUsers = $users->count();
        $activeUsers = $users->where('is_active', true)->count();
        $verifiedUsers = $users->where('email_verified_at', '!=', null)->count();
        $inactiveUsers = $users->where('is_active', false)->count();
        $deletedUsers = User::onlyTrashed()->count();
        // get count of all posts, likes, dislikes and comments where is_admin is true
        // $adminPosts = DB::table('posts')->where('is_admin', true)->count();
        // $getAdminPost = DB::table('posts')->where('is_admin', true)->get();
        // $communityPosts = DB::table('posts')->where('is_admin', false)->count();

        $adminLikes = 0;
        $adminDislikes = 0;
        $adminComments = 0;
        // foreach ($getAdminPost as $post) {
        //     $adminLikes = $post->likes_count;
        //     $adminDislikes = $post->dislikes_count;
        //     $adminComments = $post->comments_count;
        // }

        $data = [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'verified_users' => $verifiedUsers,
            'inactive_users' => $inactiveUsers,
            'deleted_users' => $deletedUsers,
            // 'admin_posts' => $adminPosts,
            'admin_likes' => $adminLikes,
            'admin_dislikes' => $adminDislikes,
            'admin_comments' => $adminComments,
            // 'community_posts' => $communityPosts,
        ];

        return $this->success('User metadata retrieved successfully', $data, [], 200);
    }

    public function suspendUser(Request $request, $id)
    {
        $request->validate([
            'suspend' => 'required|boolean',
            'reason' => 'nullable|string',
            'duration' => 'nullable|string',
        ]);

        $superadmin = auth()->user();

        // If the current admin is the same as the one being suspended, return an error
        // if ($superadmin->id == $id) {
        //     return $this->failure('You cannot suspend your own account. Please contact another admin.', null, 403);
        // }

        $user = User::find($id);
        if (!$user) {
            return $this->failure('User not found', null, 404);
        }
        $user->update(['is_suspended' => $request->suspend, 'status' => $request->suspend ? 'suspended' : 'active', 'suspension_reason' => $request->reason ?? '', 'suspension_duration' => $request->duration ?? '', 'suspension_date' => $request->suspend ? Carbon::now() : null]);

        if($request->suspend){
            $this->notificationService->userNotification($user, 'Account', 'Suspended', 'Account Suspended', 'Your account has been suspended. Reason: '.$request->reason.' Duration: '.$request->duration, true);
            ActivityLogger::log('User', 'Account Suspended', 'User has been suspended. Reason: '.$request->reason.' Duration: '.$request->duration, $user->id);
        }else{
            $this->notificationService->userNotification($user, 'Account', 'Unsuspended', 'Account Unsuspended', 'Your account has been unsuspended.', true);
            ActivityLogger::log('User', 'Account Unsuspended', 'User has been unsuspended.', $user->id);
        }

        $message = $user->is_suspended ? 'Account suspended successfully.' : 'Account unsuspended successfully.';

        return $this->success($message, $user, [], 200);
    }
    // Delete user
    public function deleteUser($id)
    {
        $user = User::where('id', $id)->first();
        if (!$user) {
            return $this->failure('User not found', null, 404);
        }
        $user->delete();
        return $this->success('User deleted successfully', [], 200);
    }

    //restore user account
    public function restoreUser($id)
    {
        $user = User::withTrashed()->where('id', $id)->first();
        if (!$user) {
            return $this->failure('User not found', null, 404);
        }
        $user->restore();
        return $this->success('User account restored successfully', [], 200);
    }


    // Get user activities
    public function getUserActivities($id = null)
    {
        // Apply conditionally based on whether an ID is provided
        $userActivitiesQuery = ActivityLog::when($id, function ($query) use ($id) {
            return $query->where('user_id', $id);
        })
        ->with('user:id,full_name,email,country'); // Eager load user data

        // Get activity count and paginate results to improve efficiency
        $activityCount = $userActivitiesQuery->count();
        $userActivities = $userActivitiesQuery->paginate(10); // Adjust pagination limit as needed

        $data = [
            'count' => $activityCount,
            'activities' => $userActivities,
        ];

        // Return response with a success message and the data
        return $this->success('User Activities retrieved successfully', $data, [], 200);
    }

    // Create a new Level (Create)
    public function createLevel(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'referrer_1_percentage' => 'required|numeric|min:0|max:100',
            'referrer_2_percentage' => 'required|numeric|min:0|max:100',
            'admin_percentage' => 'required|numeric|min:0|max:100',
        ]);

        $totalPercentage = $validated['referrer_1_percentage']
                        + $validated['referrer_2_percentage']
                        + $validated['admin_percentage'];

        if ($totalPercentage !== 100) {
            return response()->json([
                'message' => 'The sum of referrer_1_percentage, referrer_2_percentage, and admin_percentage must be 100%'
            ], 422);
        }

        $level = Level::create($validated);

        return response()->json(['message' => 'Level created successfully', 'data' => $level], 201);
    }



    public function getLevels($id = null){
        if ($id) {
            $level = Level::find($id);

            if (!$level) {
                return response()->json(['message' => 'Level not found'], 404);
            }

            return response()->json($level, 200);
        }

        return response()->json(Level::all(), 200);
    }


    // Update an level (Update)
    public function updateLevel(Request $request, $id)
    {
        $level = Level::find($id);

        if (!$level) {
            return response()->json(['message' => 'Level not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric',
            'referrer_1_percentage' => 'sometimes|numeric|min:0|max:100',
            'referrer_2_percentage' => 'sometimes|numeric|min:0|max:100',
            'admin_percentage' => 'sometimes|numeric|min:0|max:100',
        ]);

        $referrer_1_percentage = $validated['referrer_1_percentage'] ?? $level->referrer_1_percentage;
        $referrer_2_percentage = $validated['referrer_2_percentage'] ?? $level->referrer_2_percentage;
        $admin_percentage = $validated['admin_percentage'] ?? $level->admin_percentage;
        $totalPercentage = $referrer_1_percentage + $referrer_2_percentage + $admin_percentage;

        if ($totalPercentage !== 100) {
            return response()->json([
                'message' => 'The sum of referrer_1_percentage, referrer_2_percentage, and admin_percentage must be 100%'
            ], 422);
        }

        $level->update($validated);

        return response()->json(['message' => 'Level updated successfully', 'data' => $level], 200);
    }


    // Delete an Level (Delete)
    public function deleteLevel($id)
    {
        $level = Level::find($id);

        if (!$level) {
            return response()->json(['message' => 'Level not found'], 404);
        }

        $level->delete();

        return response()->json(['message' => 'Level deleted successfully'], 200);
    }


    public function adminGetProduct(Request $request, $id = null)
    {
        $query = Product::query();

        if ($id) {
            $product = $query->findOrFail($id);

            if (!$product) {
                return response()->json(['message' => 'Product not found'], 404);
            }

            return response()->json($product, 200);
        }

        if ($request->has('is_approved')) {
            $query->where('is_approved', $request->is_approved);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('visibility')) {
            $query->where('visibility', $request->visibility);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $products = $query->get();
        return response()->json(['message' => 'Product filter successfully', 'data' => $products], 200);
    }


    public function approveProduct(Request $request, $id)
    {

        $status = $request->status;
        $status = $status == 'true' ? true : false;
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $product->is_approved = $status;
        $product->status = $status ? 'approved' : 'denied';

        $product->save();

        $message = $status ? 'Product approved successfully' : 'Product rejected successfully';

        return response()->json(['message' => $message, 'data' => $product], 200);
    }



    //Community Management
    // Create a new community
    public function createCommunity(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'community_type' => 'required|string',
            'description' => 'nullable|string',
            'link' => 'required|url',
            'rules' => 'nullable|array',
            'rules.*' => 'string'
        ]);

        $community = Community::create($data);

        if (isset($data['rules'])) {
            foreach ($data['rules'] as $rule) {
                CommunityRule::create([
                    'community_id' => $community->id,
                    'rule' => $rule,
                ]);
            }
        }

        return response()->json($community->load('rules'), 201);
    }


    public function getCommunity($id = null){
        if ($id) {
            $community = Community::with('rules')->findOrFail($id);

            if (!$community) {
                return response()->json(['message' => 'Level not found'], 404);
            }

            return response()->json($community, 200);
        }

        return response()->json(Community::with('rules')->get(), 200);
    }

    // Update a community
    public function updateCommunity(Request $request, $id)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string',
            'community_type' => 'sometimes|required|string',
            'description' => 'sometimes|string',
            'link' => 'sometimes|required|url',
            'rules' => 'nullable|array',
            'rules.*' => 'string'
        ]);

        $community = Community::findOrFail($id);
        $community->update($data);

        if (isset($data['rules'])) {
            // Delete old rules
            $community->rules()->delete();

            // Add new rules
            foreach ($data['rules'] as $rule) {
                CommunityRule::create([
                    'community_id' => $community->id,
                    'rule' => $rule,
                ]);
            }
        }

        return response()->json($community->load('rules'));
    }

    // Delete a community
    public function deleteCommunity($id)
    {
        $community = Community::findOrFail($id);
        $community->delete();
        return response()->json(['message' => 'Community deleted successfully.']);
    }


    public function getTransactions(Request $request) {
        $transactionId = $request->input('transactionId');
        $userId = $request->input('userId');
        $userEmail = $request->input('userEmail');
        $type = $request->input('type');

        if ($transactionId) {
            $transaction = Transaction::where('transaction_id', $transactionId)
                ->with([
                    'sender:id,full_name,email,phone_number,whatsapp_number',
                    'receiver:id,full_name,email,bank_name,bank_account_name,bank_account_number,phone_number,whatsapp_number'
                ])->first();

            return $transaction
                ? response()->json(['message' => 'Transaction fetched successfully.', 'data' => $transaction], 200)
                : response()->json(['message' => 'Transaction not found.'], 404);
        }

        if ($userEmail) {
            $user = User::where('email', $userEmail)->first();
            $userId = $user->id ?? null;
        }

        if ($userId) {
            $sendOrReceive = $type == 'incoming' ? 'sender_user_id' : 'receiver_user_id';
            $transactions = Transaction::where($sendOrReceive, $userId)
                ->with([
                    'sender:id,full_name,email,phone_number,whatsapp_number',
                    'receiver:id,full_name,email,bank_name,bank_account_name,bank_account_number,phone_number,whatsapp_number'
                ])->get();

            return $transactions->isNotEmpty()
                ? response()->json(['message' => 'Transactions fetched successfully.', 'data' => $transactions], 200)
                : response()->json(['message' => 'No transactions found for this user.'], 404);
        }

        $transactions = Transaction::with([
                'sender:id,full_name,email,phone_number,whatsapp_number',
                    'receiver:id,full_name,email,bank_name,bank_account_name,bank_account_number,phone_number,whatsapp_number'
            ])->get();

        return $transactions->isNotEmpty()
            ? response()->json(['message' => 'Transactions fetched successfully.', 'data' => $transactions], 200)
            : response()->json(['message' => 'No transactions found.'], 404);
    }


    public function changeTransactionStatus(Request $request)
    {
        $request->validate([
            'transactionId' => 'required|string',
            'status' => 'required|string|in:pending,completed,failed',
        ]);
        $transactionId = $request->transactionId;
        $transaction = Transaction::where('transaction_id', $transactionId)->first();
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found.'], 404);
        }

        $transaction->status = $request->status;
        $transaction->save();

        if($transaction->status == 'completed'){
            $user = User::find($transaction->receiver_user_id);
            $receiver = User::find($transaction->sender_user_id);
            $this->generalService->adjustBalance($transaction->receiver_user_id, $transaction->amount);
            $this->generalService->adjustRefSort($transaction->receiver_user_id);
            $this->notificationService->userNotification($receiver, 'Payment', 'Payment received', 'Transaction Complete.', 'You have received a transaction with ID: ' . $transaction->transaction_id. ' from ' . $user['full_name'], false);
            $this->notificationService->userNotification($user, 'Payment', 'Payment sent', 'Transaction Complete.', 'You have sent a transaction with ID: ' . $transaction->transaction_id. ' to ' . $receiver['full_name'], false);
            ActivityLogger::log('Payment', 'Transaction Complete', 'The transaction with ID: ' . $transaction->transaction_id . ' has been completed, initiated by ' . $user['full_name'] . ' and received by ' . $receiver['full_name'], $receiver->id);

            // update user level
            $user->level_id = $transaction->level_id;
            $user->ongoing_transaction = false;
            $user->save();
            $receiver->ongoing_transaction = false;
            $receiver->save();
            $this->notificationService->userNotification($user, 'Level', 'Upranking', 'You are now in next level', 'Congratulations! You have successfully completed a transaction and you are now in the next level.', false);
            ActivityLogger::log('Level', 'Upranking', 'User ' . $user['full_name'] . ' has been upranked to the next level' . Level::find($transaction->level_id)->name, $user->id);
        }

        return response()->json(['message' => 'Transaction status updated successfully.', 'data' => $transaction], 200);
    }


    public function adminSettings(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'value' => 'required',
        ]);

        $name = $request->name;
        $value = $request->value;

        // Using firstOrCreate for better efficiency and readability
        $setting = Setting::updateOrCreate(
            ['name' => $name],
            ['value' => $value]
        );

        // Return a response indicating whether the setting was created or updated
        if ($setting->wasRecentlyCreated) {
            return response()->json(['message' => 'Setting created successfully.', 'data' => $setting], 201);
        } else {
            return response()->json(['message' => 'Setting updated successfully.', 'data' => $setting], 200);
        }
    }



    public function getAdminSettings(Request $request)
    {
        $settings = Setting::all();
        return response()->json(['message' => 'Settings fetched successfully.', 'data' => $settings], 200);
    }

    public function deleteAdminSetting($id)
    {
        $setting = Setting::find($id);
        if (!$setting) {
            return response()->json(['message' => 'Setting not found.'], 404);
        }

        $setting->delete();
        return response()->json(['message' => 'Setting deleted successfully.'], 200);
    }


    public function updateAdminSetting(Request $request, $id)
    {
        $setting = Setting::find($id);
        if (!$setting) {
            return response()->json(['message' => 'Setting not found.'], 404);
        }

        $request->validate([
            'value' => 'required|string',
        ]);

        $setting->update(['value' => $request->value]);
        return response()->json(['message' => 'Setting updated successfully.', 'data' => $setting], 200);
    }

}
