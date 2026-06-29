<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class BarcodeController extends Controller
{
    public function index()
    {
        $products = Product::where('is_active', true)->orderBy('name')->get();

        return view('barcode.index', compact('products'));
    }

    public function generate(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
            'type' => 'required|in:ean13,code128,qr',
            'label_size' => 'required|in:small,medium,large',
        ]);

        $products = Product::whereIn('id', $request->product_ids)->get();
        $type = $request->type;
        $labelSize = $request->label_size;

        return view('barcode.print', compact('products', 'type', 'labelSize'));
    }

    public function generateForProduct(Product $product)
    {
        return view('barcode.print', [
            'products' => collect([$product]),
            'type' => 'code128',
            'labelSize' => 'medium',
        ]);
    }
}
