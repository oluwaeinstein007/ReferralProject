<?php

namespace App\Http\Controllers\API\V1;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;

class ProductController extends Controller
{
    // Store a newly created product in storage
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'sub_title' => 'required|string|max:255',
            'youtube_url' => 'nullable|url',
            'hidden_information' => 'nullable|string',
            'levels_id' => 'nullable|string',
            'status' => 'nullable|in:submitted,approved,denied',
            'visibility' => 'nullable|in:unpublished,private,public',
            'reward_amount' => 'nullable|integer',
            // 'user_id' => 'required|exists:users,id'
        ]);

        $product = new Product();
        $product->title = $request->title;
        $product->sub_title = $request->sub_title;
        $product->youtube_url = $request->youtube_url;
        $product->hidden_information = $request->hidden_information;
        $product->levels_id = $request->levels_id;
        $product->status = $request->status ?? 'submitted';
        $product->visibility = $request->visibility ?? 'public';
        $product->reward_amount = $request->reward_amount;
        $product->user_id = auth()->user()->id;
        $product->save();

        return response()->json(['message' => 'Product was created', 'data' => $product], 201);
    }


    // Display the specified product
    public function show($id = null)
    {
        if ($id) {
            $product = Product::findOrFail($id);

            if (!$product) {
                return response()->json(['message' => 'Product not found'], 404);
            }

            return response()->json($product, 200);
        }

        return response()->json(['message' => 'Product not found', 'data' => Product::all()], 200);
    }


    // Update the specified product in storage
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'title' => 'nullable|string|max:255',
            'sub_title' => 'nullable|string|max:255',
            'youtube_url' => 'nullable|url',
            'hidden_information' => 'nullable|string',
            'levels_id' => 'nullable|string',
            'status' => 'nullable|in:submitted,approved,denied',
            'visibility' => 'nullable|in:unpublished,private,public',
            'reward_amount' => 'nullable|integer',
            'user_id' => 'required|exists:users,id'
        ]);

        $product->update($request->all());

        return response()->json(['message' => 'Product was updated', 'data' => $product], 200);
    }


    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully.'], 200);
    }


    public function incrementViewCount($id)
    {
        $product = Product::findOrFail($id);
        $product->increment('view_count');

        return response()->json(['message' => 'Product view was incremented', 'data' => $product], 200);
    }


    // Filter products by status, visibility, or user_id
    public function filter(Request $request)
    {
        $query = Product::query();

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


    public function getOwnProducts() {
        $user = auth()->user();
        $products = Product::where('user_id', $user->id)->get();

        return response()->json(['message' => 'Product fetched successfully', 'data' => $products], 200);
    }
}

