<?php


namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function index(Request $request)
    {

        $this->authorizeAdmin();

        // in case we have a lot of users the app can crash
    //$users = User::all();


    return view('admin.users.index');

    }

    public function search(Request $request)
{
    $this->authorizeAdmin();

    $query = User::query()->with('plan:id,name');

    if($request->filled('query')) {
        $q = $request->query('query');
        $query->where(function($q2) use ($q){
            $q2->where('id','like',"%$q%")
               ->orWhere('name','like',"%$q%")
               ->orWhere('email','like',"%$q%");
        });
    }

    if($request->filled('role')) {
        $query->where('role', $request->query('role'));
    }

    $sortField = $request->query('sort','id');
    $direction = $request->query('direction','asc');

    $users = $query->orderBy($sortField, $direction)->paginate(20);

    return response()->json($users);
}

    public function destroy(User $user)
    {
        $this->authorizeAdmin();


        if ($user->id === 1) {
            return redirect()->route('admin.users.index')->with('error', 'You cannot delete the primary admin.');
        }

        if (Auth::id() == $user->id) {
            return redirect()->route('admin.users.index')->with('error', 'You cannot delete yourself');
        }

        if ($user->id === 1) {
            return response()->json(['error' => 'Cannot change default admin'], 403);
        }


        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully');
    }

    private function authorizeAdmin()
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Not authorized');
        }

    }

    public function updateRole(Request $request, User $user)
    {
        if(Auth::user()->role !== 'admin') {
            return response()->json(['error'=>'Unauthorized'],403);
        }

        if($user->id === 1){
            return response()->json(['error'=>'Cannot change default admin'],403);
        }

        $role = $request->input('role');
        if(!in_array($role, ['user','admin'])){
            return response()->json(['error'=>'Invalid role'],422);
        }

        $user->role = $role;
        $user->save();

        return response()->json(['success'=>'Role updated successfully','role'=>$user->role]);
    }

    public function updatePlan(Request $request, User $user)
    {
        $this->authorizeAdmin();

        if ($user->id === 1) {
            return response()->json(['error' => 'Cannot change default admin'], 403);
        }

        $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $plan = \App\Models\Plan::findOrFail($request->plan_id);

        $user->plan_id = $plan->id;
        $user->save();

        return response()->json([
            'success' => 'Plan updated successfully',
            'plan_id' => $user->plan_id,
            'plan_name' => $plan->name,
        ]);
    }



}



