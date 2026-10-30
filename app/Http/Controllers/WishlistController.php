<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function index(): View
    {
        $ids = session('wishlist', []);
        $products = Product::query()->active()->with(['images', 'category'])->whereIn('id', $ids)->get();

        return view('store.wishlist', compact('products'));
    }

    public function toggle(Request $request, Product $product): JsonResponse
    {
        $ids = session('wishlist', []);

        if (in_array($product->id, $ids, true)) {
            $ids = array_values(array_diff($ids, [$product->id]));
            $saved = false;
        } else {
            $ids[] = $product->id;
            $ids = array_values(array_unique($ids));
            $saved = true;
        }

        session(['wishlist' => $ids]);

        return response()->json(['saved' => $saved, 'wishlistCount' => count($ids)]);
    }
}
