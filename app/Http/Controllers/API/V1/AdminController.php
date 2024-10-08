<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Level;
use App\Models\User;
use App\Models\ActivityLog;
use App\Services\NotificationService;
use App\Services\GeneralService;
use App\Utilities\ActivityLogger;
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
            $this->notificationService->userNotification($user, 'Account', 'Suspended', 'Account Suspended', 'Your account has been suspended. Reason: '.$request->reason.' Duration: '.$request->duration, true, [], '', '');
            ActivityLogger::log('User', 'Account Suspended', 'User has been suspended. Reason: '.$request->reason.' Duration: '.$request->duration, $user->id);
        }else{
            $this->notificationService->userNotification($user, 'Account', 'Unsuspended', 'Account Unsuspended', 'Your account has been unsuspended.', true, [], '', '');
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
        ->with('user:id,first_name,last_name,email,country'); // Eager load user data

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
}
